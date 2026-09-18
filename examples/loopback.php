<?php

declare(strict_types=1);

// Wire TX to RX (on a Raspberry Pi: GPIO14 to GPIO15) and run:
//   php examples/loopback.php /dev/serial0 115200

require __DIR__ . '/../vendor/autoload.php';

use Sanchescom\Serial\SerialException;
use Sanchescom\Serial\SerialPort;
use Sanchescom\Serial\TimeoutException;

$device = $argv[1] ?? '/dev/serial0';
$baud = (int) ($argv[2] ?? 115200);

try {
    $port = new SerialPort($device, $baud);
    echo "Opened {$device} at {$baud}\n";

    $port->write("ping\n");
    echo 'readLine: ', var_export($port->readLine(timeout: 1.0), true), "\n";

    try {
        $port->readLine(timeout: 0.5);
    } catch (TimeoutException $e) {
        echo 'timeout: ', $e->getMessage(), "\n";
    }

    echo 'readAvailable: ', var_export($port->readAvailable(), true), "\n";
    $port->close();
} catch (SerialException $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
