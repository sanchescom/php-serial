<?php

declare(strict_types=1);

namespace Sanchescom\Serial\Test;

use PHPUnit\Framework\TestCase;
use Sanchescom\Serial\SerialException;
use Sanchescom\Serial\SerialPort;
use Sanchescom\Serial\TimeoutException;

final class SerialPortTest extends TestCase
{
    /** @var resource */
    private $remote;
    private SerialPort $port;

    protected function setUp(): void
    {
        $pair = stream_socket_pair(STREAM_PF_UNIX, STREAM_SOCK_STREAM, STREAM_IPPROTO_IP);
        self::assertIsArray($pair);
        [$local, $this->remote] = $pair;
        stream_set_blocking($local, false);
        $this->port = SerialPort::fromStream($local);
    }

    protected function tearDown(): void
    {
        $this->port->close();
        if (is_resource($this->remote)) {
            fclose($this->remote);
        }
    }

    private function feed(string $bytes): void
    {
        fwrite($this->remote, $bytes);
    }

    public function testWriteReachesTheOtherEnd(): void
    {
        $this->port->write("AT\r\n");
        self::assertSame("AT\r\n", fread($this->remote, 16));
    }

    public function testReadAvailableNeverWaitsAndReturnsEmptyWhenIdle(): void
    {
        $start = hrtime(true);
        self::assertSame('', $this->port->readAvailable());
        self::assertLessThan(50_000_000, hrtime(true) - $start);
    }

    public function testReadAvailableReturnsEverythingIncludingBufferedTail(): void
    {
        $this->feed("one\ntwo");
        self::assertSame('one', $this->port->readLine(0.5));
        $this->feed('-more');
        self::assertSame('two-more', $this->port->readAvailable());
    }

    public function testReadReturnsUpToMaxLengthAndKeepsTheRest(): void
    {
        $this->feed('abcdef');
        self::assertSame('abc', $this->port->read(3, 0.5));
        self::assertSame('def', $this->port->read(10, 0.5));
    }

    public function testReadReturnsEmptyStringOnTimeout(): void
    {
        $start = hrtime(true);
        self::assertSame('', $this->port->read(10, 0.1));
        $elapsed = (hrtime(true) - $start) / 1e9;
        self::assertGreaterThanOrEqual(0.09, $elapsed);
        self::assertLessThan(0.3, $elapsed);
    }

    public function testReadServesBufferedBytesFirst(): void
    {
        $this->feed("ab\ncd");
        self::assertSame('ab', $this->port->readLine(0.5));
        self::assertSame('c', $this->port->read(1, 0.5));
        self::assertSame('d', $this->port->read(1, 0.5));
    }

    public function testReadLineStripsCarriageReturnAndKeepsTheTail(): void
    {
        $this->feed("OK\r\nERR");
        self::assertSame('OK', $this->port->readLine(0.5));
        $this->feed("OR\r\n");
        self::assertSame('ERROR', $this->port->readLine(0.5));
    }

    public function testReadUntilThrowsTimeoutAndKeepsPartialBytes(): void
    {
        $this->feed('half');
        $start = hrtime(true);
        try {
            $this->port->readUntil("\n", 0.1);
            self::fail('expected TimeoutException');
        } catch (TimeoutException) {
        }
        self::assertGreaterThanOrEqual(0.09, (hrtime(true) - $start) / 1e9);
        $this->feed("-line\n");
        self::assertSame('half-line', $this->port->readUntil("\n", 0.5));
    }

    public function testReadUntilWithMultiByteDelimiter(): void
    {
        $this->feed("a\r\nb\r\n");
        self::assertSame('a', $this->port->readUntil("\r\n", 0.5));
        self::assertSame('b', $this->port->readUntil("\r\n", 0.5));
    }

    public function testZeroTimeoutReturnsBufferedLineWithoutWaiting(): void
    {
        $this->feed("x\ny\n");
        self::assertSame('x', $this->port->readLine(0.5));
        self::assertSame('y', $this->port->readLine(0.0));
    }

    public function testReadUntilTimesOutWhileBytesKeepArrivingWithoutTheDelimiter(): void
    {
        // A single process, no shell: nothing survives proc_terminate(). /dev/zero never
        // yields a "\n", so the delimiter can never arrive however much data does.
        $process = proc_open(['head', '-c', '200000000', '/dev/zero'], [1 => ['pipe', 'w']], $pipes);
        self::assertIsResource($process);
        stream_set_blocking($pipes[1], false);
        $port = SerialPort::fromStream($pipes[1]);

        try {
            $start = hrtime(true);
            try {
                $port->readUntil("\n", 0.2);
                self::fail('expected TimeoutException');
            } catch (TimeoutException) {
            }
            $elapsed = (hrtime(true) - $start) / 1e9;
            self::assertGreaterThanOrEqual(0.19, $elapsed);
            self::assertLessThan(1.0, $elapsed);
        } finally {
            proc_terminate($process);
            proc_close($process);
        }
    }

    public function testReadUntilThrowsWhenTheOtherEndCloses(): void
    {
        fclose($this->remote);
        $this->expectException(SerialException::class);
        $this->expectExceptionMessage('closed');
        $this->port->readUntil("\n", 0.5);
    }

    public function testReadThrowsWhenTheOtherEndCloses(): void
    {
        fclose($this->remote);
        $this->expectException(SerialException::class);
        $this->expectExceptionMessage('closed');
        $this->port->read(10, 0.5);
    }

    public function testRejectsNegativeTimeout(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->port->read(10, -1.0);
    }

    public function testRejectsEmptyDelimiter(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->port->readUntil('', 0.1);
    }

    public function testRejectsZeroMaxLength(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->port->read(0, 0.1);
    }

    public function testStreamIsTheUnderlyingNonBlockingResource(): void
    {
        $stream = $this->port->stream();
        self::assertFalse(stream_get_meta_data($stream)['blocked']);
    }

    public function testCloseIsIdempotentAndStreamThrowsAfterwards(): void
    {
        $this->port->close();
        $this->port->close();
        $this->expectException(SerialException::class);
        $this->port->stream();
    }
}
