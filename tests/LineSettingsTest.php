<?php

declare(strict_types=1);

namespace Sanchescom\Serial\Test;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Sanchescom\Serial\FlowControl;
use Sanchescom\Serial\LineSettings;
use Sanchescom\Serial\Parity;
use Sanchescom\Serial\StopBits;

final class LineSettingsTest extends TestCase
{
    private static function defaults(): LineSettings
    {
        return new LineSettings(57600, 8, Parity::None, StopBits::One, FlowControl::None);
    }

    public function testDefaultLinuxCommandIsTheFemusLine(): void
    {
        self::assertSame(
            "stty -F '/dev/x' raw -echo 57600 cs8 -cstopb -parenb -crtscts -ixon -ixoff",
            self::defaults()->command('/dev/x', 'Linux'),
        );
    }

    public function testDarwinUsesLowercaseF(): void
    {
        self::assertStringStartsWith(
            "stty -f '/dev/cu.x' raw -echo ",
            self::defaults()->command('/dev/cu.x', 'Darwin')
        );
    }

    public function testDefaultWindowsCommand(): void
    {
        self::assertSame(
            'mode COM3 BAUD=57600 PARITY=n DATA=8 STOP=1 xon=off octs=off rts=on dtr=on to=off',
            self::defaults()->command('COM3', 'Windows'),
        );
    }

    public function testRawComesBeforeEveryExplicitFlag(): void
    {
        // macOS `raw` is cfmakeraw(): it forces cs8 -parenb, GNU raw forces -ixon -ixoff.
        // Explicit settings must therefore follow `raw`, never precede it.
        $command = (new LineSettings(9600, 7, Parity::Even, StopBits::Two, FlowControl::XonXoff))
            ->command('/dev/x', 'Darwin');
        self::assertSame(
            "stty -f '/dev/x' raw -echo 9600 cs7 cstopb parenb -parodd -crtscts ixon ixoff",
            $command
        );
    }

    /** @return iterable<string, array{Parity, string, string}> */
    public static function parity(): iterable
    {
        yield 'none' => [Parity::None, '-parenb', 'PARITY=n'];
        yield 'even' => [Parity::Even, 'parenb -parodd', 'PARITY=e'];
        yield 'odd' => [Parity::Odd, 'parenb parodd', 'PARITY=o'];
    }

    #[DataProvider('parity')]
    public function testParity(Parity $parity, string $stty, string $mode): void
    {
        $settings = new LineSettings(57600, 8, $parity, StopBits::One, FlowControl::None);
        self::assertStringContainsString(" {$stty} ", $settings->command('/dev/x', 'Linux'));
        self::assertStringContainsString(" {$mode} ", $settings->command('COM1', 'Windows'));
    }

    /** @return iterable<string, array{StopBits, string, string}> */
    public static function stopBits(): iterable
    {
        yield 'one' => [StopBits::One, '-cstopb', 'STOP=1'];
        yield 'two' => [StopBits::Two, 'cstopb', 'STOP=2'];
    }

    #[DataProvider('stopBits')]
    public function testStopBits(StopBits $stopBits, string $stty, string $mode): void
    {
        $settings = new LineSettings(57600, 8, Parity::None, $stopBits, FlowControl::None);
        self::assertStringContainsString(" {$stty} ", $settings->command('/dev/x', 'Linux'));
        self::assertStringContainsString(" {$mode} ", $settings->command('COM1', 'Windows'));
    }

    /** @return iterable<string, array{FlowControl, string, string}> */
    public static function flowControl(): iterable
    {
        yield 'none' => [FlowControl::None, '-crtscts -ixon -ixoff', 'xon=off octs=off rts=on'];
        yield 'rtscts' => [
            FlowControl::RtsCts,
            'crtscts -ixon -ixoff',
            'xon=off octs=on rts=hs',
        ];
        yield 'xonxoff' => [
            FlowControl::XonXoff,
            '-crtscts ixon ixoff',
            'xon=on octs=off rts=on',
        ];
    }

    #[DataProvider('flowControl')]
    public function testFlowControl(FlowControl $flowControl, string $stty, string $mode): void
    {
        $settings = new LineSettings(57600, 8, Parity::None, StopBits::One, $flowControl);
        self::assertStringEndsWith(' ' . $stty, $settings->command('/dev/x', 'Linux'));
        self::assertStringContainsString(" {$mode} ", $settings->command('COM1', 'Windows'));
    }

    public function testDataBitsAndBaudAreEmitted(): void
    {
        $settings = new LineSettings(115200, 5, Parity::None, StopBits::One, FlowControl::None);
        self::assertStringContainsString(' 115200 cs5 ', $settings->command('/dev/x', 'Linux'));
        self::assertStringContainsString(' BAUD=115200 PARITY=n DATA=5 ', $settings->command('COM1', 'Windows'));
    }

    public function testDevicePathIsShellEscapedOnPosix(): void
    {
        self::assertStringContainsString("'/dev/it'\\''s'", self::defaults()->command("/dev/it's", 'Linux'));
    }

    public function testRejectsDataBitsOutsideFiveToEight(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LineSettings(9600, 9, Parity::None, StopBits::One, FlowControl::None);
    }

    public function testRejectsNonPositiveBaudRate(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new LineSettings(0, 8, Parity::None, StopBits::One, FlowControl::None);
    }
}
