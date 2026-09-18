<?php

declare(strict_types=1);

namespace Sanchescom\Serial\Test;

use PHPUnit\Framework\TestCase;
use Sanchescom\Serial\SerialPortLocator;

final class SerialPortLocatorTest extends TestCase
{
    public function testLinuxListsUsbAcmSerialAndOnBoardUarts(): void
    {
        $glob = static fn (string $pattern): array => match ($pattern) {
            '/dev/ttyUSB*' => ['/dev/ttyUSB0'],
            '/dev/ttyACM*' => ['/dev/ttyACM0'],
            '/dev/serial*' => ['/dev/serial0'],
            '/dev/ttyS*' => ['/dev/ttyS0'],
            default => [],
        };
        $locator = new SerialPortLocator($glob, self::neverRun());
        self::assertSame(
            ['/dev/serial0', '/dev/ttyACM0', '/dev/ttyS0', '/dev/ttyUSB0'],
            $locator->candidates('Linux')
        );
    }

    public function testDarwinExcludesNoisePortsAndDeduplicates(): void
    {
        $glob = static fn (string $pattern): array =>
            str_contains($pattern, 'cu.*') || str_contains($pattern, 'usbserial')
            ? [
                '/dev/cu.Bluetooth-Incoming-Port',
                '/dev/cu.debug-console',
                '/dev/cu.usbserial-1420',
                '/dev/cu.HC-05-DevB',
                '/dev/cu.usbserial-1420',
                '/dev/cu.wlan-debug',
            ]
            : [];
        $locator = new SerialPortLocator($glob, self::neverRun());
        self::assertSame(
            ['/dev/cu.HC-05-DevB', '/dev/cu.usbserial-1420'],
            $locator->candidates('Darwin')
        );
    }

    public function testWindowsParsesTheRegistry(): void
    {
        $run = static function (string $command): array {
            self::assertSame('reg query HKLM\\HARDWARE\\DEVICEMAP\\SERIALCOMM', $command);

            return [0, [
                '',
                'HKEY_LOCAL_MACHINE\HARDWARE\DEVICEMAP\SERIALCOMM',
                '    \Device\Serial0    REG_SZ    COM1',
                '    \Device\USBSER000    REG_SZ    COM7',
                '    \Device\Silabser0    REG_SZ    COM10',
                '',
            ]];
        };
        $locator = new SerialPortLocator(
            static fn (): array => self::fail('glob must not run on Windows'),
            $run
        );
        self::assertSame(['COM1', 'COM10', 'COM7'], $locator->candidates('Windows'));
    }

    public function testWindowsWithoutSerialKeyIsEmpty(): void
    {
        $run = static fn (string $command): array =>
            [1, ['ERROR: The system was unable to find the specified registry key or value.']];
        $locator = new SerialPortLocator(static fn (): array => [], $run);
        self::assertSame([], $locator->candidates('Windows'));
    }

    public function testDefaultsRunOnThisMachineWithoutErrors(): void
    {
        $candidates = (new SerialPortLocator())->candidates();
        $sorted = $candidates;
        sort($sorted);

        self::assertSame($sorted, $candidates, 'candidates() must be sorted');
        self::assertSame(array_values(array_unique($candidates)), $candidates, 'candidates() must be unique');
    }

    private static function neverRun(): \Closure
    {
        return static fn (string $command): array => self::fail("unexpected command {$command}");
    }
}
