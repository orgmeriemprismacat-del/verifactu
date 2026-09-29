<?php

class Uc108ConfirmationToken {
	const PREFIX = 'v2.';
	const CIPHER = 'AES-128-CBC';
	const HMAC_LENGTH = 32;

	public static function issue($id, $urlTastet, $key, $issuedAt = null) {
		$id = intval($id);
		if ($id <= 0 || $key === '')
			throw new InvalidArgumentException('Invalid UC-108 token input');

		if ($issuedAt === null)
			$issuedAt = time();

		$payload = json_encode([
			'id' => $id,
			'url' => (string)$urlTastet,
			'iat' => intval($issuedAt)
		]);

		$ivLength = openssl_cipher_iv_length(self::CIPHER);
		$iv = random_bytes($ivLength);
		$encryptionKey = self::deriveEncryptionKey($key);
		$macKey = self::deriveMacKey($key);

		$ciphertext = openssl_encrypt($payload, self::CIPHER, $encryptionKey, OPENSSL_RAW_DATA, $iv);
		if ($ciphertext === false)
			throw new RuntimeException('Unable to encrypt token');

		$hmac = hash_hmac('sha256', $iv.$ciphertext, $macKey, true);
		return self::PREFIX.self::base64UrlEncode($iv.$hmac.$ciphertext);
	}

	public static function verify($token, $urlTastet, $key) {
		if (strpos($token, self::PREFIX) !== 0)
			return null;

		$raw = self::base64UrlDecode(substr($token, strlen(self::PREFIX)));
		if ($raw === false)
			return null;

		$ivLength = openssl_cipher_iv_length(self::CIPHER);
		if (strlen($raw) <= ($ivLength + self::HMAC_LENGTH))
			return null;

		$iv = substr($raw, 0, $ivLength);
		$hmac = substr($raw, $ivLength, self::HMAC_LENGTH);
		$ciphertext = substr($raw, $ivLength + self::HMAC_LENGTH);
		$encryptionKey = self::deriveEncryptionKey($key);
		$macKey = self::deriveMacKey($key);
		$calcMac = hash_hmac('sha256', $iv.$ciphertext, $macKey, true);

		if (!hash_equals($hmac, $calcMac))
			return null;

		$payloadRaw = openssl_decrypt($ciphertext, self::CIPHER, $encryptionKey, OPENSSL_RAW_DATA, $iv);
		if ($payloadRaw === false)
			return null;

		$payload = json_decode($payloadRaw, true);
		if (!is_array($payload) || !isset($payload['id']) || intval($payload['id']) <= 0)
			return null;

		$urlToken = isset($payload['url']) ? (string)$payload['url'] : '';
		if ($urlToken !== '') {
			if ($urlTastet === '' || !hash_equals($urlToken, (string)$urlTastet))
				return null;
		}

		$payload['id'] = intval($payload['id']);
		$payload['iat'] = isset($payload['iat']) ? intval($payload['iat']) : null;
		return $payload;
	}

	public static function verifyLegacy($token, $key) {
		$raw = base64_decode($token, true);
		if ($raw === false)
			return null;

		$ivLength = openssl_cipher_iv_length(self::CIPHER);
		if (strlen($raw) <= ($ivLength + self::HMAC_LENGTH))
			return null;

		$iv = substr($raw, 0, $ivLength);
		$hmac = substr($raw, $ivLength, self::HMAC_LENGTH);
		$ciphertext = substr($raw, $ivLength + self::HMAC_LENGTH);
		$calcMac = hash_hmac('sha256', $ciphertext, $key, true);

		if (!hash_equals($hmac, $calcMac))
			return null;

		$id = openssl_decrypt($ciphertext, self::CIPHER, $key, OPENSSL_RAW_DATA, $iv);
		if (!is_numeric($id) || intval($id) <= 0)
			return null;

		return intval($id);
	}

	private static function deriveEncryptionKey($key) {
		return substr(hash_hmac('sha256', 'uc108-encryption', $key, true), 0, 16);
	}

	private static function deriveMacKey($key) {
		return hash_hmac('sha256', 'uc108-authentication', $key, true);
	}

	private static function base64UrlEncode($raw) {
		return rtrim(strtr(base64_encode($raw), '+/', '-_'), '=');
	}

	private static function base64UrlDecode($encoded) {
		$padding = strlen($encoded) % 4;
		if ($padding > 0)
			$encoded .= str_repeat('=', 4 - $padding);
		return base64_decode(strtr($encoded, '-_', '+/'), true);
	}
}

?>