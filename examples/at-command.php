<?php

declare(strict_types=1);

// Talk to a modem or an ESP8266 with AT firmware:
//   php examples/at-command.php /dev/ttyUSB0 115200

require __DIR__ . '/../vendor/autoload.php';

use Sanchescom\Serial\SerialException;
use Sanchescom\Serial\SerialPort;
use Sanchescom\Serial\SerialPortLocator;
use Sanchescom\Serial\TimeoutException;

$device = $argv[1] ?? (new SerialPortLocator())->candidates()[0] ?? null;
if ($device === null) {
    fwrite(STDERR, "No serial device found\n");
    exit(1);
}

try {
    $port = new SerialPort($device, (int) ($argv[2] ?? 115200));
    $port->write("AT\r\n");

    try {
        do {
            $line = $port->readLine(timeout: 2.0);
            echo "< {$line}\n";
        } while ($line !== 'OK' && $line !== 'ERROR');
    } catch (TimeoutException) {
        echo "No answer within 2 s\n";
    }

    $port->close();
} catch (SerialException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
