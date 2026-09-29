<?php

require_once __DIR__.'/../Uc108Validation.php';

$tests = [
	['NIF vàlid', Uc108Validation::validarNifNie('12345678Z') === true],
	['NIF amb separadors', Uc108Validation::validarNifNie('12.345.678-Z') === true],
	['NIF lletra incorrecta', Uc108Validation::validarNifNie('12345678A') === false],
	['NIE X vàlid', Uc108Validation::validarNifNie('X1234567L') === true],
	['NIE Y vàlid', Uc108Validation::validarNifNie('Y1234567X') === true],
	['NIE Z vàlid', Uc108Validation::validarNifNie('Z1234567R') === true],
	['Document incomplet', Uc108Validation::validarNifNie('1234') === false],
	['Email TLD llarg', Uc108Validation::validarEmail('persona@example.education') === true],
	['Email invàlid', Uc108Validation::validarEmail('persona@@example.com') === false],
	['Normalització NIF/NIE', Uc108Validation::normalitzarNifNie(' x-1234567-l ') === 'X1234567L'],
];

$errors = [];
foreach ($tests as $test) {
	if (!$test[1])
		$errors[] = $test[0];
}

if (count($errors) > 0) {
	fwrite(STDERR, "UC-108 validation tests FAILED:\n- ".implode("\n- ", $errors)."\n");
	exit(1);
}

echo "UC-108 validation tests OK (".count($tests)." assertions)\n";
exit(0);

?>