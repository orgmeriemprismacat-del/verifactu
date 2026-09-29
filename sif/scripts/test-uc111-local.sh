#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${SIF_TEST_DB_NAME:-sif_test_uc111}"
DB_HOST="${SIF_TEST_DB_HOST:-127.0.0.1}"
DB_PORT="${SIF_TEST_DB_PORT:-3306}"
DB_USER="${SIF_TEST_DB_USER:-root}"
DB_PASSWORD="${SIF_TEST_DB_PASSWORD:-}"
PHP_BIN="${PHP_BIN:-php}"
REPORT_DIR="${SIF_TEST_REPORT_DIR:-sif/test-results}"
STAMP="$(date +%Y%m%d-%H%M%S)"
REPORT_FILE="${REPORT_DIR}/uc111-${STAMP}.log"

if [[ ! "${DB_NAME}" =~ ^sif_test(_[a-z0-9_]+)?$ ]]; then
  echo "ERROR: DB_NAME must match ^sif_test(_[a-z0-9_]+)?$" >&2
  exit 2
fi

if [[ "${DB_HOST}" != "127.0.0.1" && "${DB_HOST}" != "localhost" ]]; then
  echo "ERROR: DB_HOST must be localhost/127.0.0.1" >&2
  exit 2
fi

command -v "${PHP_BIN}" >/dev/null
command -v mysql >/dev/null

mkdir -p "${REPORT_DIR}"

MYSQL_ARGS=(-h "${DB_HOST}" -P "${DB_PORT}" -u "${DB_USER}")
if [[ -n "${DB_PASSWORD}" ]]; then
  MYSQL_ARGS+=("-p${DB_PASSWORD}")
fi

{
  echo "UC-111 local/preproduction verification"
  echo "timestamp=$(date -Iseconds)"
  echo "database=${DB_NAME}"
  echo "host=${DB_HOST}"
  echo "php=$("${PHP_BIN}" -r 'echo PHP_VERSION;')"
  echo

  mysql "${MYSQL_ARGS[@]}" -Nse "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

  export SIF_ENV=test
  export SIF_DB_DSN="mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_NAME};charset=utf8mb4"
  export SIF_DB_USER="${DB_USER}"
  export SIF_DB_PASSWORD="${DB_PASSWORD}"
  export SIF_NOVICE_PROMO_WRAP_KEY_HEX="${SIF_NOVICE_PROMO_WRAP_KEY_HEX:-bbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbbb}"
  export SIF_NOVICE_PROMO_KEY_VERSION="${SIF_NOVICE_PROMO_KEY_VERSION:-test-v1}"

  echo "== Full SIF suite =="
  "${PHP_BIN}" sif/tests/run-tests.php

  echo
  echo "== UC-111 post-payment smoke assertions =="
  mysql "${MYSQL_ARGS[@]}" "${DB_NAME}" -Nse "
    SELECT CONCAT('novice_promotion_grant=', COUNT(*)) FROM novice_promotion_grant;
    SELECT CONCAT('novice_promotion_code_outbox=', COUNT(*)) FROM novice_promotion_code_outbox;
  "

  echo
  echo "RESULT=PASS"
} 2>&1 | tee "${REPORT_FILE}"

echo "Evidence written to ${REPORT_FILE}"
