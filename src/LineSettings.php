<?php

declare(strict_types=1);

namespace Sanchescom\Serial;

/**
 * @internal Turns line settings into the `stty` (Linux, macOS) or `mode` (Windows) command.
 */
final readonly class LineSettings
{
    public function __construct(
        public int $baudRate,
        public int $dataBits,
        public Parity $parity,
        public StopBits $stopBits,
        public FlowControl $flowControl,
    ) {
        if ($baudRate < 1) {
            throw new \InvalidArgumentException("Baud rate must be positive, {$baudRate} given");
        }
        if ($dataBits < 5 || $dataBits > 8) {
            throw new \InvalidArgumentException("Data bits must be 5..8, {$dataBits} given");
        }
    }

    public function command(string $device, string $osFamily): string
    {
        return $osFamily === 'Windows' ? $this->mode($device) : $this->stty($device, $osFamily);
    }

    private function stty(string $device, string $osFamily): string
    {
        // `raw` first: on macOS it is cfmakeraw() and forces cs8 -parenb, on GNU it
        // forces -ixon -ixoff. Everything explicit must come after it.
        return sprintf(
            'stty %s %s raw -echo %d cs%d %s %s %s',
            $osFamily === 'Darwin' ? '-f' : '-F',
            escapeshellarg($device),
            $this->baudRate,
            $this->dataBits,
            $this->stopBits === StopBits::Two ? 'cstopb' : '-cstopb',
            match ($this->parity) {
                Parity::None => '-parenb',
                Parity::Even => 'parenb -parodd',
                Parity::Odd => 'parenb parodd',
            },
            match ($this->flowControl) {
                FlowControl::None => '-crtscts -ixon -ixoff',
                FlowControl::RtsCts => 'crtscts -ixon -ixoff',
                FlowControl::XonXoff => '-crtscts ixon ixoff',
            },
        );
    }

    private function mode(string $device): string
    {
        [$xon, $octs, $rts] = match ($this->flowControl) {
            FlowControl::None => ['off', 'off', 'on'],
            FlowControl::RtsCts => ['off', 'on', 'hs'],
            FlowControl::XonXoff => ['on', 'off', 'on'],
        };

        return sprintf(
            'mode %s BAUD=%d PARITY=%s DATA=%d STOP=%d xon=%s octs=%s rts=%s dtr=on to=off',
            $device,
            $this->baudRate,
            match ($this->parity) {
                Parity::None => 'n',
                Parity::Even => 'e',
                Parity::Odd => 'o',
            },
            $this->dataBits,
            $this->stopBits->value,
            $xon,
            $octs,
            $rts,
        );
    }
}
