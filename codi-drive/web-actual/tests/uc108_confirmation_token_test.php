<?php

require_once __DIR__.'/../Uc108ConfirmationToken.php';

$key = 'uc108-test-key-32-bytes-minimum';
$route = '/tastets/demo';
$token = Uc108ConfirmationToken::issue(123, $route, $key, 1700000000);
$payload = Uc108ConfirmationToken::verify($token, $route, $key);

$last = substr($token, -1);
$replacement = ($last === 'A') ? 'B' : 'A';
$tampered = substr($token, 0, -1).$replacement;

$legacyIvLength = openssl_cipher_iv_length(Uc108ConfirmationToken::CIPHER);
$legacyIv = str_repeat("\x01", $legacyIvLength);
$legacyCiphertext = openssl_encrypt('321', Uc108ConfirmationToken::CIPHER, $key, OPENSSL_RAW_DATA, $legacyIv);
$legacyHmac = hash_hmac('sha256', $legacyCiphertext, $key, true);
$legacyToken = base64_encode($legacyIv.$legacyHmac.$legacyCiphertext);

$tests = [
	['prefix v2', strpos($token, 'v2.') === 0],
	['token URL-safe', preg_match('/^[A-Za-z0-9._-]+$/', $token) === 1],
	['payload vàlid', is_array($payload) && $payload['id'] === 123],
	['iat preservat', is_array($payload) && $payload['iat'] === 1700000000],
	['ruta equivocada rebutjada', Uc108ConfirmationToken::verify($token, '/tastets/altre', $key) === null],
	['token manipulat rebutjat', Uc108ConfirmationToken::verify($tampered, $route, $key) === null],
	['clau equivocada rebutjada', Uc108ConfirmationToken::verify($token, $route, 'wrong-key') === null],
	['legacy compatible', Uc108ConfirmationToken::verifyLegacy($legacyToken, $key) === 321],
];

$errors = [];
foreach ($tests as $test) {
	if (!$test[1])
		$errors[] = $test[0];
}

if (count($errors) > 0) {
	fwrite(STDERR, "UC-108 token tests FAILED:\n- ".implode("\n- ", $errors)."\n");
	exit(1);
}

echo "UC-108 token tests OK (".count($tests)." assertions)\n";
exit(0);

?>