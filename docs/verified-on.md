# Verified on real hardware — 3.0.0

The commands below were run over SSH on the maintainer's Raspberry Pi on
**2026-09-18** against branch `3.0.0` at commit `b3c9f6f` (this file was added
afterwards; no code changed). Output is pasted verbatim from `journalctl` of the
`systemd-run` unit that executed `/tmp/pi-run.sh` as the unprivileged user `femus`.

| | |
| --- | --- |
| Board | Raspberry Pi, `aarch64`, kernel `6.18.34+rpt-rpi-v8` |
| OS | Debian GNU/Linux 13 (trixie) |
| PHP | `PHP 8.4.24 (cli) (built: Jul 31 2026 05:11:11) (NTS)` |
| Port | `/dev/serial0 -> ttyS0` (`enable_uart=1`, serial console disabled) |
| Wiring | jumper between GPIO14 (TXD) and GPIO15 (RXD), pins 8 and 10: everything written comes back |
| User | `femus`, member of `dialout`; no `sudo` needed for the port |
| Power | `vcgencmd get_throttled` = `0x50005` (under-voltage present during the run; the run is a few seconds long) |
| Clock | the Pi has no NTP sync; the timestamps in the transcript are its local clock, not the real date |
| Test suite on the Pi | `OK, but some tests were skipped! Tests: 55, Assertions: 104, Skipped: 5` (the five `SocatTest` cases skip because `socat` is not installed on the Pi; they run in CI on Linux and macOS) |

## Loopback at 9600 and 115200

```
== 2026-09-17T10:08:26+01:00 commit b3c9f6f
== Linux 6.18.34+rpt-rpi-v8 aarch64; PHP 8.4.24 (cli) (built: Jul 31 2026 05:11:11) (NTS)
== throttled: throttled=0x50005
== port: /dev/serial0 -> ttyS0; user femus groups: femus adm dialout cdrom sudo audio video plugdev games users netdev gpio i2c spi render input
== unit tests
OK, but some tests were skipped!
Tests: 55, Assertions: 104, Skipped: 5.
== php examples/loopback.php /dev/serial0 9600
Opened /dev/serial0 at 9600
readLine: 'ping'
timeout: No "\n" received within 0.500 s
readAvailable: ''
[exit 0]
== php examples/loopback.php /dev/serial0 115200
Opened /dev/serial0 at 115200
readLine: 'ping'
timeout: No "\n" received within 0.500 s
readAvailable: ''
[exit 0]
== stty -F /dev/serial0 -a (after the last run)
speed 115200 baud; rows 0; columns 0; line = 0;
== at-command against the loopback (echo of AT, then timeout)
< AT
No answer within 2 s
[exit 0]
== done 2026-09-17T10:08:30+01:00
```

What this shows:

- `new SerialPort('/dev/serial0', 9600)` and `115200` open and configure the UART
  without `sudo`; `stty -a` afterwards reports the requested speed.
- `write("ping\n")` followed by `readLine(timeout: 1.0)` returns `'ping'` at both speeds.
- `readLine(timeout: 0.5)` with nothing arriving throws `TimeoutException` after the timeout.
- `readAvailable()` on an idle port returns `''` without waiting.
- `examples/at-command.php` against the loopback reads back its own `AT` line and
  then reports the 2 s timeout instead of hanging.

An identical run as `root` (`php-serial-run.service`, same commit, one minute
earlier) produced the same output.

## Continuous integration

GitHub Actions run `35309138350` on commit `b3c9f6f`: PHP 8.2, 8.3, 8.4, 8.5 and
`--prefer-lowest` on `ubuntu-latest` with `socat`, plus PHP 8.4 on `macos-latest`
with `socat`; every job green, the five `SocatTest` pty cases included.

## Not yet verified

- **Windows**: the `mode` command string is unit-tested and the device-name
  normalisation (`COM3`, `COM3:`, `\\.\COM3`) is unit-tested; opening a real COM
  port, `fread` behaviour and the write path have not been run on a Windows
  machine. Planned for 3.0.1 on the maintainer's Windows PC.
- **A real device at the other end** (Arduino Nano over USB, ESP8266 AT firmware):
  pending; the loopback proves the transport, not a conversation.
- Parity, stop-bit and flow-control settings other than the default `8N1`,
  no flow control: the `stty` and `mode` strings are unit-tested, no hardware run.
