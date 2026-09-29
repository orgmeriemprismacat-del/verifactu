#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"
WEB_DIR="$(cd "$BASE_DIR/.." && pwd)"

PHP_FILES=(
  "$WEB_DIR/Tastet.php"
  "$WEB_DIR/InscripcioTastet.php"
  "$WEB_DIR/MailSMTPComvive.php"
  "$WEB_DIR/PaginaConfirmacioTastet.php"
  "$WEB_DIR/Uc108Validation.php"
  "$WEB_DIR/Uc108ConfirmationToken.php"
  "$WEB_DIR/ajax/buscarSiHaRealitzatElTastet.php"
  "$WEB_DIR/ajax/enviarInscripcioTastet.php"
  "$WEB_DIR/ajax/mostrar_confirmacio_inscripcio_tastet_automatic.php"
  "$WEB_DIR/ajax/obtenirCodiTastet.php"
  "$WEB_DIR/inc/missatgesError.php"
  "$WEB_DIR/pagina_confirmacio_tastets_automatic.php"
  "$WEB_DIR/pagina_inscripcions_tastets.php"
  "$BASE_DIR/uc108_validation_test.php"
  "$BASE_DIR/uc108_confirmation_token_test.php"
)

for file in "${PHP_FILES[@]}"; do
  php -l "$file"
done

php "$BASE_DIR/uc108_validation_test.php"
php "$BASE_DIR/uc108_confirmation_token_test.php"

echo "UC-108 checks OK"
