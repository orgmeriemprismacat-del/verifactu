<?php

namespace Prisma\Sif\Aeat;

/** Private, append-only attempts; no certificate/key/password material is accepted. */
final class EvidenceStore
{
    public function __construct(private string $directory)
    {
        $real = realpath($directory);
        $repo = realpath(dirname(__DIR__, 3));
        if ($real === false || !is_dir($real) || !is_writable($real)
            || str_starts_with(strtolower(str_replace('\\', '/', $real)) . '/',
                strtolower(str_replace('\\', '/', (string) $repo)) . '/')) {
            throw new \RuntimeException('Evidence requires a private writable directory outside the repository/webroot.');
        }
        $this->directory = $real;
    }

    public function begin(string $request, array $metadata): string
    {
        $id = gmdate('Ymd\THis\Z') . '-' . bin2hex(random_bytes(12));
        if (!mkdir($this->directory . '/' . $id, 0700)) {
            throw new \RuntimeException('Cannot create private AEAT evidence attempt.');
        }
        $this->write($id, 'request.xml', $request);
        $metadata['request_sha256'] = hash('sha256', $request);
        $this->write($id, 'request.json', json_encode($metadata, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
        return $id;
    }

    public function response(string $id, string $response, int $httpStatus): void
    {
        $this->write($id, 'response.xml', $response);
        $this->write($id, 'response.json', json_encode([
            'received_at_utc' => gmdate('c'), 'http_status' => $httpStatus,
            'response_sha256' => hash('sha256', $response),
        ], JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT));
    }

    public function failure(string $id, string $code): void
    {
        $this->write($id, 'failure.json', json_encode([
            'at_utc' => gmdate('c'), 'code' => $code,
        ], JSON_THROW_ON_ERROR));
    }

    private function write(string $id, string $name, string $contents): void
    {
        if (!preg_match('/^\d{8}T\d{6}Z-[a-f0-9]{24}$/D', $id)) {
            throw new \InvalidArgumentException('Invalid evidence identifier.');
        }
        $path = $this->directory . '/' . $id . '/' . $name;
        $file = @fopen($path, 'xb');
        if ($file === false) {
            throw new \RuntimeException('Cannot create immutable AEAT evidence file.');
        }
        try {
            if (fwrite($file, $contents) !== strlen($contents) || !fflush($file)
                || (function_exists('fsync') && !fsync($file))) {
                throw new \RuntimeException('Cannot durably write AEAT evidence.');
            }
            chmod($path, 0600);
        } finally {
            fclose($file);
        }
    }
}
