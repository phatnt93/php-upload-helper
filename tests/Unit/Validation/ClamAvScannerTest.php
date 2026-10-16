<?php

declare(strict_types=1);

namespace UploadHelper\Tests\Unit\Validation;

use UploadHelper\Exceptions\SecurityException;
use UploadHelper\Tests\TestCase;
use UploadHelper\Validation\Scanners\ClamAvScanner;

/**
 * Talks to a fake clamd daemon started in a child PHP process, so no real ClamAV is needed.
 */
final class ClamAvScannerTest extends TestCase
{
    /** @var list<resource> */
    private array $processes = [];

    protected function tearDown(): void
    {
        foreach ($this->processes as $process) {
            proc_terminate($process);
            proc_close($process);
        }
        $this->processes = [];

        parent::tearDown();
    }

    public function testPingAgainstFakeDaemon(): void
    {
        $scanner = new ClamAvScanner($this->startFakeClamd('PONG'), 2);

        $this->assertTrue($scanner->ping());
    }

    public function testPingFailsWhenDaemonIsDown(): void
    {
        $this->assertFalse((new ClamAvScanner('tcp://127.0.0.1:1', 1))->ping());
    }

    public function testCleanStreamIsAccepted(): void
    {
        $scanner = new ClamAvScanner($this->startFakeClamd('stream: OK'), 2);

        $this->assertTrue($scanner->scan($this->createTempFile(str_repeat('clean-data', 2000), 'bin')));
        $this->assertNull($scanner->getThreatName());
    }

    public function testInfectedStreamReportsThreatName(): void
    {
        $scanner = new ClamAvScanner($this->startFakeClamd('stream: Win.Test.Trojan FOUND'), 2);

        $this->assertFalse($scanner->scan($this->createTempFile('payload', 'bin')));
        $this->assertSame('Win.Test.Trojan', $scanner->getThreatName());
    }

    public function testUnparsableFoundResponseUsesGenericThreat(): void
    {
        $scanner = new ClamAvScanner($this->startFakeClamd('FOUND'), 2);

        $this->assertFalse($scanner->scan($this->createTempFile('payload', 'bin')));
        $this->assertSame('Malware-Detected', $scanner->getThreatName());
    }

    public function testDaemonErrorThrows(): void
    {
        $scanner = new ClamAvScanner($this->startFakeClamd('INSTREAM size limit exceeded. ERROR'), 2);

        $this->expectException(SecurityException::class);
        $scanner->scan($this->createTempFile('payload', 'bin'));
    }

    public function testEicarSignatureIsDetectedWithoutDaemon(): void
    {
        $scanner = new ClamAvScanner('tcp://127.0.0.1:1', 1);

        $this->assertFalse($scanner->scan($this->createTempFile('X5O!P%@AP EICAR-STANDARD-ANTIVIRUS-TEST-FILE!', 'txt')));
        $this->assertSame('Malware-Test-Signature', $scanner->getThreatName());
    }

    public function testUnreachableDaemonOrMissingFileFailsOpen(): void
    {
        $scanner = new ClamAvScanner('tcp://127.0.0.1:1', 1);

        $this->assertTrue($scanner->scan($this->createTempFile('data', 'bin')));
        $this->assertTrue($scanner->scan('/missing/file.bin'));
    }

    /**
     * Start a one-shot fake clamd that answers every request with the given response.
     *
     * @return string Socket address for ClamAvScanner
     */
    private function startFakeClamd(string $response): string
    {
        $script = $this->createTempFile(<<<'PHP'
            <?php
            $server = stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
            if ($server === false) {
                exit(1);
            }
            echo stream_socket_get_name($server, false), "\n";
            $conn = stream_socket_accept($server, 10);
            if ($conn === false) {
                exit(1);
            }
            $command = '';
            while (!str_ends_with($command, "\0") && !feof($conn)) {
                $command .= fread($conn, 1);
            }
            if ($command === "zINSTREAM\0") {
                while (!feof($conn)) {
                    $len = unpack('N', (string) fread($conn, 4))[1] ?? 0;
                    if ($len === 0) {
                        break;
                    }
                    $read = 0;
                    while ($read < $len && !feof($conn)) {
                        $read += strlen((string) fread($conn, $len - $read));
                    }
                }
            }
            fwrite($conn, $argv[1] . "\0");
            fclose($conn);
            PHP, 'php');

        $process = proc_open(
            [PHP_BINARY, $script, $response],
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
        );
        if (!is_resource($process)) {
            $this->markTestSkipped('Cannot start the fake clamd process.');
        }
        $this->processes[] = $process;

        $address = trim((string) fgets($pipes[1]));
        if ($address === '') {
            $this->markTestSkipped('Fake clamd did not start.');
        }

        return 'tcp://' . $address;
    }
}
