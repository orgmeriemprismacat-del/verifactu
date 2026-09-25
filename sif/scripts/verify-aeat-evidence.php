<?php

require dirname(__DIR__) . '/src/autoload.php';

if (PHP_SAPI !== 'cli') {
    exit(1);
}
try {
    $options = [];
    foreach (array_slice($argv, 1) as $arg) {
        if (!preg_match('/^--(directory|attempt)=(.+)$/D', $arg, $match) || isset($options[$match[1]])) {
            throw new \InvalidArgumentException('Usage: --directory=PRIVATE_DIR --attempt=ATTEMPT_ID');
        }
        $options[$match[1]] = $match[2];
    }
    if (count($options) !== 2) {
        throw new \InvalidArgumentException('Usage: --directory=PRIVATE_DIR --attempt=ATTEMPT_ID');
    }
    $result = (new \Prisma\Sif\Aeat\EvidenceVerifier())->verify($options['directory'], $options['attempt']);
    echo json_encode($result, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT), PHP_EOL;
    exit($result['integrity_ok'] && $result['state'] === 'RESPONSE_RECORDED' ? 0 : 1);
} catch (\Throwable) {
    // No paths, XML, secrets or stack traces on stdout/stderr.
    echo json_encode(['integrity_ok' => false, 'state' => 'INVALID', 'errors' => ['VERIFICATION_FAILED']]), PHP_EOL;
    exit(1);
}
