<?php

declare(strict_types=1);

namespace Sanchescom\Serial\Test;

use PHPUnit\Framework\TestCase;
use Sanchescom\Serial\SerialPort;
use Sanchescom\Serial\TimeoutException;

/** Two pseudo-terminals wired back to back by socat: what the Pi loopback does with a jumper. */
final class SocatTest extends TestCase
{
    /** @var resource|null */
    private $socat;
    /** @var array<int, resource> */
    private array $pipes = [];
    private string $deviceA;
    private string $deviceB;

    protected function setUp(): void
    {
        $binary = trim((string) shell_exec('command -v socat 2>/dev/null'));
        if ($binary === '') {
            self::markTestSkipped('socat is not installed');
        }
        $process = proc_open(
            [$binary, '-d', '-d', 'pty,raw,echo=0', 'pty,raw,echo=0'],
            [2 => ['pipe', 'w']],
            $this->pipes,
        );
        self::assertIsResource($process);
        $this->socat = $process;

        $devices = [];
        $deadline = microtime(true) + 5.0;
        stream_set_blocking($this->pipes[2], false);
        while (count($devices) < 2 && microtime(true) < $deadline) {
            $read = [$this->pipes[2]];
            $write = $except = null;
            if (stream_select($read, $write, $except, 0, 200_000) === 1) {
                while (($line = fgets($this->pipes[2])) !== false) {
                    if (preg_match('~PTY is (/dev/\S+)~', $line, $match) === 1) {
                        $devices[] = $match[1];
                    }
                }
            }
        }
        self::assertCount(2, $devices, 'socat did not report two ptys');
        [$this->deviceA, $this->deviceB] = $devices;
        usleep(100_000); // let socat finish wiring before stty touches the ptys
    }

    protected function tearDown(): void
    {
        if (isset($this->socat)) {
            proc_terminate($this->socat);
            proc_close($this->socat);
        }
    }

    public function testLineWrittenOnOneEndIsReadOnTheOther(): void
    {
        $a = new SerialPort($this->deviceA, 9600);
        $b = new SerialPort($this->deviceB, 9600);
        $a->write("AT\r\n");
        self::assertSame('AT', $b->readLine(1.0));
        $a->close();
        $b->close();
    }

    public function testReadLineTimesOutWhenNothingArrives(): void
    {
        $b = new SerialPort($this->deviceB, 9600);
        $start = hrtime(true);
        try {
            $b->readLine(0.2);
            self::fail('expected TimeoutException');
        } catch (TimeoutException) {
        }
        $elapsed = (hrtime(true) - $start) / 1e9;
        self::assertGreaterThanOrEqual(0.19, $elapsed);
        self::assertLessThan(0.5, $elapsed);
        $b->close();
    }

    public function testReadReturnsEmptyOnTimeoutAndDataWhenPresent(): void
    {
        $a = new SerialPort($this->deviceA, 115200);
        $b = new SerialPort($this->deviceB, 115200);
        self::assertSame('', $b->read(8, 0.1));
        $a->write('hello');
        self::assertSame('hello', $b->read(8, 1.0));
        $a->close();
        $b->close();
    }

    public function testTailAfterDelimiterSurvivesForTheNextCall(): void
    {
        $a = new SerialPort($this->deviceA, 9600);
        $b = new SerialPort($this->deviceB, 9600);
        $a->write("first\nsec");
        self::assertSame('first', $b->readLine(1.0));
        $a->write("ond\n");
        self::assertSame('second', $b->readLine(1.0));
        self::assertSame('', $b->readAvailable());
        $a->close();
        $b->close();
    }

    public function testSttyAppliedTheRequestedSpeed(): void
    {
        $port = new SerialPort($this->deviceA, 19200);
        $flag = PHP_OS_FAMILY === 'Darwin' ? '-f' : '-F';
        $report = (string) shell_exec(sprintf('stty %s %s -a 2>&1', $flag, escapeshellarg($this->deviceA)));
        self::assertStringContainsString('19200', $report);
        $port->close();
    }
}
