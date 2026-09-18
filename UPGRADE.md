# Upgrading from 2.x to 3.0

3.0 is a rewrite. The port is configured in the constructor and is open as soon as the object exists.

| 2.x | 3.0 |
| --- | --- |
| `new Serial()` then `$serial->setDevice('/dev/ttyUSB0')` | `new SerialPort('/dev/ttyUSB0', ...)` |
| `$device->open('r+b')` | done by the constructor |
| `$device->setBaudRate(9600)` | `baudRate: 9600` |
| `$device->setCharacterLength(8)` | `dataBits: 8` |
| `$device->setParity('none' / 'even' / 'odd')` | `parity: Parity::None / Parity::Even / Parity::Odd` |
| `$device->setStopBits(1 / 2)`; `1.5` | `stopBits: StopBits::One / StopBits::Two`; 1.5 dropped |
| `$device->setFlowControl('none' / 'rts/cts' / 'xon/xoff')` | `flowControl: FlowControl::None / FlowControl::RtsCts / FlowControl::XonXoff` |
| `$device->setBlockingMode(int $mode = 0)` | per call: `read($max, timeout:)`, `readLine(timeout:)`, `readUntil($delim, timeout:)` |
| `$device->send($message, $waitForReply)` | `write($bytes)` then a `read*` with a timeout |
| `$device->read($limit)` | `readAvailable()` (no wait), `read($max, $timeout)`, `readLine($timeout)`, `readUntil($delimiter, $timeout)` |
| `$device->flush()` | removed; `write()` writes everything before returning |
| `$device->close()` | `close()` |
| `Serial::setExecutorClass()`, `Serial::setPhpOperationSystem()` | removed; tests inject a `$run` closure |
| `Serial::OS_LINUX`, `Serial::OS_DARWIN`, `Serial::OS_WINDOWS` | removed, no replacement; the platform is read from `PHP_OS_FAMILY` |
| `ExecutorInterface::command()`, `ExecutorInterface::program()`, `Executor` | removed, no replacement; the one command per port runs through an internal `Shell::run()` |
| `Contracts\ConfigInterface`, `Contracts\SystemInterface`, `Contracts\ExecutorInterface` | removed, no replacement; `SerialPort` is the whole API |
| `Systems\AbstractSystem`, `Systems\Linux`, `Systems\Darwin`, `Systems\Windows` | removed, no replacement; one class covers all three platforms |
| `ClosingException`, `CommandException`, `InvalidDeviceException`, `InvalidHandleException`, `SendingException`, `UnknownSystemException` | `SerialException` |
| `InvalidFlowControlException`, `InvalidModeException`, `InvalidParityException`, `InvalidRateException`, `InvalidStopBitException` | `\InvalidArgumentException` (enums make most of them impossible) |
| — | `TimeoutException extends SerialException` |
| — | `SerialPortLocator::candidates()` |
| — | `stream()` for `stream_select` / event loops |
| PHP ^7.2, GPL-3.0 | PHP ^8.2, MIT |
