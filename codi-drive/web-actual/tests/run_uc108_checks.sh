#!/usr/bin/env bash
set -euo pipefail

BASE_DIR="$(cd "$(dirname "$0")" && pwd)"

php -l "$BASE_DIR/../Uc108Validation.php"
php -l "$BASE_DIR/../Uc108ConfirmationToken.php"
php -l "$BASE_DIR/uc108_validation_test.php"
php -l "$BASE_DIR/uc108_confirmation_token_test.php"

php "$BASE_DIR/uc108_validation_test.php"
php "$BASE_DIR/uc108_confirmation_token_test.php"
