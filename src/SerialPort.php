<?php

declare(strict_types=1);

namespace Sanchescom\Serial;

final class SerialPort
{
    /** Seconds a write with no progress at all is retried before it is called a stall. */
    private const WRITE_STALL_TIMEOUT = 1.0;

    /** @var resource|null */
    private $stream;

    private string $pending = '';

    /**
     * Opens and configures the port. Open first, configure second: on macOS the
     * termios settings applied by stty are discarded once the device is fully
     * closed, so the port is held open while stty runs.
     *
     * @param \Closure(string): array{int, list<string>}|null $run command runner, tests only
     */
    public function __construct(
        string $device,
        int $baudRate = 57600,
        int $dataBits = 8,
        Parity $parity = Parity::None,
        StopBits $stopBits = StopBits::One,
        FlowControl $flowControl = FlowControl::None,
        ?\Closure $run = null,
    ) {
        $settings = new LineSettings($baudRate, $dataBits, $parity, $stopBits, $flowControl);
        $windows = PHP_OS_FAMILY === 'Windows';
        if ($windows) {
            $device = self::windowsDevice($device);
        }

        $stream = @fopen($windows ? '\\\\.\\' . $device : $device, 'r+b');
        if ($stream === false) {
            throw new SerialException("Failed to open {$device} (permissions? device connected?)");
        }
        @stream_set_blocking($stream, false); // not implemented for plain files on Windows

        [$exitCode, $output] = ($run ?? Shell::run(...))($settings->command($device, PHP_OS_FAMILY));
        if ($exitCode !== 0) {
            fclose($stream);
            throw new SerialException("Failed to configure port {$device}: " . implode("\n", $output));
        }

        $this->stream = $stream;
    }

    /**
     * Normalises a Windows device name: COM3, com3: and \\.\COM3 all become COM3.
     *
     * @internal exposed so the Windows naming rules can be tested from any platform
     */
    public static function windowsDevice(string $device): string
    {
        $name = $device;
        if (str_starts_with($name, '\\\\.\\')) {
            $name = substr($name, 4);
        }
        $name = strtoupper(rtrim($name, ':'));
        if (preg_match('/^COM\d+$/', $name) !== 1) {
            throw new \InvalidArgumentException("Windows device must look like COM3, {$device} given");
        }

        return $name;
    }

    /**
     * @internal tests only: wrap an already-open non-blocking resource, no configuration.
     * @param resource $stream
     */
    public static function fromStream($stream): self
    {
        /** @var self $port */
        $port = (new \ReflectionClass(self::class))->newInstanceWithoutConstructor();
        $port->stream = $stream;

        return $port;
    }

    /**
     * Writes every byte, however many calls that takes.
     *
     * @throws SerialException when the stream makes no progress at all for WRITE_STALL_TIMEOUT seconds
     */
    public function write(string $bytes): void
    {
        $stream = $this->stream();
        while ($bytes !== '') {
            $written = @fwrite($stream, $bytes);
            if ($written === false) {
                throw new SerialException('Write error (device disconnected?)');
            }
            if ($written === 0 && !$this->wait($stream, forWrite: true, timeout: self::WRITE_STALL_TIMEOUT)) {
                throw new SerialException('Write stalled (flow control? device disconnected?)');
            }
            $bytes = substr($bytes, $written);
        }
    }

    /** Everything received so far. Never waits. */
    public function readAvailable(): string
    {
        $bytes = $this->pending . (string) stream_get_contents($this->stream());
        $this->pending = '';

        return $bytes;
    }

    /**
     * Up to $maxLength bytes, waiting at most $timeout seconds for the first one. Empty string on timeout.
     *
     * @throws SerialException when the other end has closed the port
     */
    public function read(int $maxLength, float $timeout): string
    {
        if ($maxLength < 1) {
            throw new \InvalidArgumentException("Max length must be positive, {$maxLength} given");
        }
        self::assertTimeout($timeout);

        if ($this->pending !== '') {
            $bytes = substr($this->pending, 0, $maxLength);
            $this->pending = substr($this->pending, $maxLength);

            return $bytes;
        }

        $stream = $this->stream();
        if (!$this->wait($stream, forWrite: false, timeout: $timeout)) {
            return '';
        }

        $bytes = (string) fread($stream, $maxLength);
        if ($bytes === '' && feof($stream)) {
            throw new SerialException('Port closed by the other end');
        }

        return $bytes;
    }

    /**
     * Bytes up to (not including) $delimiter; the delimiter is consumed, later bytes stay buffered.
     *
     * The deadline is checked before every wait, so a stream that keeps delivering bytes without the
     * delimiter still times out. With a zero timeout only the internal buffer is inspected.
     *
     * @throws TimeoutException when the delimiter has not arrived within $timeout seconds; partial bytes stay buffered
     * @throws SerialException when the other end has closed the port
     */
    public function readUntil(string $delimiter, float $timeout): string
    {
        if ($delimiter === '') {
            throw new \InvalidArgumentException('Delimiter must not be empty');
        }
        self::assertTimeout($timeout);

        $stream = $this->stream();
        $deadline = hrtime(true) + (int) ($timeout * 1e9);
        while (true) {
            $at = strpos($this->pending, $delimiter);
            if ($at !== false) {
                $head = substr($this->pending, 0, $at);
                $this->pending = substr($this->pending, $at + strlen($delimiter));

                return $head;
            }

            $remaining = ($deadline - hrtime(true)) / 1e9;
            if ($remaining <= 0.0 || !$this->wait($stream, forWrite: false, timeout: $remaining)) {
                throw new TimeoutException(sprintf('No %s received within %.3f s', json_encode($delimiter), $timeout));
            }
            $chunk = (string) fread($stream, 4096);
            if ($chunk === '' && feof($stream)) {
                throw new SerialException('Port closed by the other end');
            }
            $this->pending .= $chunk;
        }
    }

    /** readUntil($eol) with a trailing "\r" removed, so "OK\r\n" reads as "OK". */
    public function readLine(float $timeout, string $eol = "\n"): string
    {
        return rtrim($this->readUntil($eol, $timeout), "\r");
    }

    /**
     * The underlying non-blocking resource, for stream_select / event loops.
     * Reading it directly bypasses the internal buffer: do not mix with read*().
     *
     * @return resource
     */
    public function stream()
    {
        if ($this->stream === null) {
            throw new SerialException('Port is closed');
        }

        return $this->stream;
    }

    public function close(): void
    {
        if ($this->stream !== null) {
            fclose($this->stream);
            $this->stream = null;
        }
    }

    /** @param resource $stream */
    private function wait($stream, bool $forWrite, float $timeout): bool
    {
        if (PHP_OS_FAMILY === 'Windows') {
            // ponytail: stream_select is not supported on file handles on Windows and
            // non-blocking mode is not implemented there; fread/fwrite block per the COM
            // port's own timeouts. Not verified on hardware yet, see README.
            return true;
        }
        $read = $forWrite ? null : [$stream];
        $write = $forWrite ? [$stream] : null;
        $except = null;
        $seconds = (int) floor($timeout);
        $micro = (int) round(($timeout - $seconds) * 1e6);

        return @stream_select($read, $write, $except, $seconds, $micro) === 1;
    }

    private static function assertTimeout(float $timeout): void
    {
        if ($timeout < 0.0) {
            throw new \InvalidArgumentException("Timeout must not be negative, {$timeout} given");
        }
    }
}
