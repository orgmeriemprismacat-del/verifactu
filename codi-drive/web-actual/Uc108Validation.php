<?php

class Uc108Validation {
	public static function normalitzarNifNie($document) {
		return strtoupper(preg_replace('/[\s\.\-]+/', '', trim($document)));
	}

	public static function validarNifNie($document) {
		$document = self::normalitzarNifNie($document);
		$letters = 'TRWAGMYFPDXBNJZSQVHLCKE';

		if (preg_match('/^[0-9]{8}[A-Z]$/', $document)) {
			$numero = intval(substr($document, 0, 8));
			return $letters[$numero % 23] === substr($document, -1);
		}

		if (preg_match('/^[XYZ][0-9]{7}[A-Z]$/', $document)) {
			$prefix = ['X' => '0', 'Y' => '1', 'Z' => '2'];
			$numero = intval($prefix[$document[0]].substr($document, 1, 7));
			return $letters[$numero % 23] === substr($document, -1);
		}

		return false;
	}

	public static function validarEmail($email) {
		return filter_var(trim($email), FILTER_VALIDATE_EMAIL) !== false;
	}
}

?>