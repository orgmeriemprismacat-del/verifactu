#!/usr/bin/env sh
# Validació local de sintaxi i CSV, sense connectar-se a cap BD.
# Execució: sh tests/run_ao_checks.sh des del directori intranet-actual.
set -eu
root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
for file in \
    "$root/cursos-fi-cursos-pujar-aules-obertes.php" \
    "$root/Intranet.php" \
    "$root/inc/AOBatchCsv.php" \
    "$root/ajax/inici/processarLotAO.php" \
    "$root/ajax/inici/descarregarFitxerAO.php" \
    "$root/ajax/inici/crearFitxerAO.php" \
    "$root/ajax/inici/pujarAulesObertes.php" \
    "$root/tests/ao_csv_test.php"
do
    php -l "$file"
done
node --check "$root/js/cursos-fi-cursos-pujar-aules-obertes.js"
php "$root/tests/ao_csv_test.php"
echo "PASS: sintaxi PHP/JS i proves CSV."
