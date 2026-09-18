<?php

declare(strict_types=1);

namespace Sanchescom\Serial;

/** Lists serial devices that look like something you could open. A candidate list, not a detector. */
final class SerialPortLocator
{
    private const POSIX_PATTERNS = [
        'Darwin' => ['/dev/cu.usbserial*', '/dev/cu.usbmodem*', '/dev/cu.wchusbserial*', '/dev/cu.*'],
        'Linux' => ['/dev/ttyUSB*', '/dev/ttyACM*', '/dev/rfcomm*', '/dev/serial*', '/dev/ttyS*'],
    ];

    private const EXCLUDED = ['Bluetooth-Incoming', 'debug', 'wlan'];

    private \Closure $glob;
    private \Closure $run;

    /**
     * @param \Closure(string): list<string>|null $glob
     * @param \Closure(string): array{int, list<string>}|null $run
     */
    public function __construct(?\Closure $glob = null, ?\Closure $run = null)
    {
        $this->glob = $glob ?? static fn (string $pattern): array => glob($pattern) ?: [];
        $this->run = $run ?? Shell::run(...);
    }

    /** @return list<string> sorted, unique */
    public function candidates(string $osFamily = PHP_OS_FAMILY): array
    {
        $found = $osFamily === 'Windows' ? $this->registry() : $this->devices($osFamily);
        $found = array_values(array_unique($found));
        sort($found);

        return $found;
    }

    /** @return list<string> */
    private function devices(string $osFamily): array
    {
        $found = [];
        foreach (self::POSIX_PATTERNS[$osFamily] ?? self::POSIX_PATTERNS['Linux'] as $pattern) {
            foreach (($this->glob)($pattern) as $device) {
                foreach (self::EXCLUDED as $noise) {
                    if (str_contains($device, $noise)) {
                        continue 2;
                    }
                }
                $found[] = $device;
            }
        }

        return $found;
    }

    /** @return list<string> */
    private function registry(): array
    {
        [$exitCode, $lines] = ($this->run)('reg query HKLM\HARDWARE\DEVICEMAP\SERIALCOMM');
        if ($exitCode !== 0) {
            return [];
        }
        $found = [];
        foreach ($lines as $line) {
            if (preg_match('/\sREG_SZ\s+(COM\d+)\s*$/i', $line, $match) === 1) {
                $found[] = strtoupper($match[1]);
            }
        }

        return $found;
    }
}
