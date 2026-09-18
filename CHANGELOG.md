# Changelog

## 3.0.0 — unreleased

Rewritten from scratch under the MIT License. No code from 2.x remains.

- `SerialPort` opens and configures the device in the constructor: baud, data bits, parity, stop bits, flow control.
- `read`, `readUntil`, `readLine` with a timeout; `readAvailable` never waits; `stream()` for event loops.
- `SerialPortLocator::candidates()` lists serial devices on Linux, macOS and Windows.
- Linux and macOS through `stty` (device opened first, configured second). Windows through `mode`, not yet verified on hardware.
- Tests on real pseudo-terminals via `socat`; loopback run on a Raspberry Pi in `docs/verified-on.md`.

## 2.0.4 — 2026-09-17

- README now states the actual license of 2.x, GPL-3.0.
