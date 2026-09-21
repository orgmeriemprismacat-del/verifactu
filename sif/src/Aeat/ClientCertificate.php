<?php

namespace Prisma\Sif\Aeat;

final class ClientCertificate
{
    public function __construct(private string $path, #[\SensitiveParameter] private string $password)
    {
    }

    /** Local cryptographic checks do not establish AEAT trust, revocation or representation. */
    public function inspect(?int $now = null): array
    {
        $now ??= time();
        $path = realpath($this->path);
        $repo = realpath(dirname(__DIR__, 3));
        if ($path === false || !is_file($path) || !is_readable($path)
            || ($repo !== false && $this->inside($path, $repo))) {
            throw new \RuntimeException('Client certificate must be readable outside the repository/webroot.');
        }
        $parts = [];
        if (!@openssl_pkcs12_read((string) file_get_contents($path), $parts, $this->password)) {
            throw new \RuntimeException('Cannot unlock client PKCS#12 certificate.');
        }
        $cert = openssl_x509_read($parts['cert'] ?? '');
        $key = openssl_pkey_get_private($parts['pkey'] ?? '');
        $info = $cert === false ? false : openssl_x509_parse($cert);
        if (!$info || !$key || !openssl_x509_check_private_key($cert, $key)) {
            throw new \RuntimeException('Client certificate/private key mismatch.');
        }
        if ($now < $info['validFrom_time_t'] || $now >= $info['validTo_time_t']) {
            throw new \RuntimeException('Client certificate is not currently valid.');
        }
        return [
            'fingerprint_sha256' => openssl_x509_fingerprint($cert, 'sha256'),
            'valid_to_utc' => gmdate('c', $info['validTo_time_t']),
            'expires_in_days' => (int) floor(($info['validTo_time_t'] - $now) / 86400),
        ];
    }

    public function curlOptions(): array
    {
        $this->inspect();
        return [CURLOPT_SSLCERT => $this->path, CURLOPT_SSLCERTTYPE => 'P12',
            CURLOPT_SSLCERTPASSWD => $this->password];
    }

    private function inside(string $path, string $root): bool
    {
        $path = strtolower(str_replace('\\', '/', $path));
        $root = rtrim(strtolower(str_replace('\\', '/', $root)), '/') . '/';
        return str_starts_with($path, $root);
    }
}
