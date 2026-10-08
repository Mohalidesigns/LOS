<?php

declare(strict_types=1);

namespace Fundly\Integration\Adapters\Clamav;

use Fundly\Integration\Ports\MalwareScan\MalwareScanPort;
use Fundly\Integration\Ports\MalwareScan\ScanVerdict;
use Fundly\Integration\Runtime\CapabilityManifest;
use Fundly\Integration\Runtime\Errors\RequiresInterventionError;
use Fundly\Integration\Runtime\Errors\RetryableError;

/**
 * clamd INSTREAM client (TCP). Binding config: host, port, timeout_seconds.
 * Chunks are length-prefixed (4-byte big-endian); a zero-length chunk ends
 * the stream. "stream: OK" is clean; "stream: <sig> FOUND" is infected.
 * Anything else is an unknown outcome and never treated as clean.
 */
final class ClamdScanner implements MalwareScanPort
{
    public const KEY = 'clamav-clamd';

    public const VERSION = '1.0.0';

    private const CHUNK = 65536;

    /** @param array<string, mixed> $config */
    public function __construct(private readonly array $config) {}

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'on_prem', [
            self::OP_SCAN => ['support' => 'native', 'idempotency' => 'natural'],
        ]);
    }

    public function scan(string $bytes): ScanVerdict
    {
        $host = is_string($this->config['host'] ?? null) ? $this->config['host'] : '127.0.0.1';
        $port = is_int($this->config['port'] ?? null) ? $this->config['port'] : 3310;
        $timeout = is_int($this->config['timeout_seconds'] ?? null) ? $this->config['timeout_seconds'] : 30;
        $socket = @stream_socket_client("tcp://{$host}:{$port}", $errno, $errstr, $timeout);
        if ($socket === false) {
            throw new RetryableError('MALWARE.TRANSPORT.UNREACHABLE', "clamd unreachable: {$errstr}");
        }
        try {
            stream_set_timeout($socket, $timeout);
            fwrite($socket, "zINSTREAM\0");
            foreach (str_split($bytes === '' ? "\0" : $bytes, self::CHUNK) as $chunk) {
                fwrite($socket, pack('N', strlen($chunk)).$chunk);
            }
            fwrite($socket, pack('N', 0));
            $reply = trim((string) stream_get_contents($socket), "\0\r\n ");
        } finally {
            fclose($socket);
        }
        if ($reply === 'stream: OK') {
            return new ScanVerdict(true, null, self::KEY);
        }
        if (preg_match('/^stream: (.+) FOUND$/', $reply, $m) === 1) {
            return new ScanVerdict(false, $m[1], self::KEY);
        }
        throw new RequiresInterventionError('MALWARE.SCAN.UNKNOWN_REPLY', 'clamd returned an unexpected reply; the file stays unreadable.');
    }
}
