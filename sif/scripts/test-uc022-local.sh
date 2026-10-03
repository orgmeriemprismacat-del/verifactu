#!/usr/bin/env bash
set -euo pipefail

DB_NAME="${SIF_TEST_DB_NAME:-sif_test_uc022}"
DB_HOST="${SIF_TEST_DB_HOST:-127.0.0.1}"
DB_PORT="${SIF_TEST_DB_PORT:-3306}"
DB_USER="${SIF_TEST_DB_USER:-root}"
DB_PASSWORD="${SIF_TEST_DB_PASSWORD:-}"
PHP_BIN="${PHP_BIN:-php}"
REPORT_DIR="${SIF_TEST_REPORT_DIR:-sif/test-results}"
STAMP="$(date +%Y%m%d-%H%M%S)"
REPORT_FILE="${REPORT_DIR}/uc022-${STAMP}.log"

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

UC022_PHP_FILES=(
  "sif/src/Repository/SifAuditEventRepository.php"
  "sif/src/Service/PaymentActionGateway.php"
  "sif/src/Service/PaymentService.php"
  "sif/src/Service/ManualPaymentService.php"
  "sif/src/Service/ManualPaymentPayloadBuilder.php"
  "sif/src/Service/ManualTransferCommandService.php"
  "sif/src/Service/GeneratedInvoiceLegacyPaymentSyncService.php"
  "sif/src/Service/ManualTransferLegacyProjectionService.php"
  "sif/public/api/payments/manual-transfer.php"
  "codi-drive/intranet-nova-canvis-verifactu/SifPaymentSessionGuard.php"
  "codi-drive/intranet-nova-canvis-verifactu/SifInternalApiClient.php"
  "codi-drive/intranet-nova-canvis-verifactu/SifManualTransferGateway.php"
  "codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/obtenirTokenPagamentSif.php"
  "codi-drive/intranet-nova-canvis-verifactu/ajax/alumnes/registrarTransferenciaSif.php"
)

{
  echo "UC-022 local/test verification"
  echo "timestamp=$(date -Iseconds)"
  echo "database=${DB_NAME}"
  echo "host=${DB_HOST}"
  echo "php=$("${PHP_BIN}" -r 'echo PHP_VERSION;')"
  if command -v git >/dev/null && git rev-parse --is-inside-work-tree >/dev/null 2>&1; then
    echo "git_revision=$(git rev-parse HEAD)"
    echo "git_branch=$(git rev-parse --abbrev-ref HEAD)"
  fi
  echo

  echo "== PHP syntax: UC-022 surface =="
  for file in "${UC022_PHP_FILES[@]}"; do
    if [[ ! -f "${file}" ]]; then
      echo "ERROR: missing ${file}" >&2
      exit 3
    fi
    "${PHP_BIN}" -l "${file}"
  done

  echo
  echo "== Prepare isolated MySQL database =="
  mysql "${MYSQL_ARGS[@]}" -Nse "CREATE DATABASE IF NOT EXISTS \`${DB_NAME}\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

  export SIF_ENV=test
  export SIF_DB_DSN="mysql:host=${DB_HOST};port=${DB_PORT};dbname=${DB_NAME};charset=utf8mb4"
  export SIF_DB_USER="${DB_USER}"
  export SIF_DB_PASSWORD="${DB_PASSWORD}"

  echo
  echo "== Full SIF suite =="
  "${PHP_BIN}" sif/tests/run-tests.php

  echo
  echo "== UC-022 test inventory present in output tree =="
  for file in \
    sif/tests/Integration/ManualPaymentServiceTest.php \
    sif/tests/Integration/ManualTransferCommandServiceTest.php \
    sif/tests/Integration/ManualTransferAuditedFlowTest.php \
    sif/tests/Integration/ManualTransferHttpEndpointTest.php \
    sif/tests/Integration/ManualTransferIntranetAdapterTest.php \
    sif/tests/Unit/ManualPaymentPayloadBuilderTest.php \
    sif/tests/Unit/GeneratedInvoiceLegacyPaymentSyncServiceTest.php
  do
    test -f "${file}"
    echo "present=${file}"
  done

  echo
  echo "RESULT=PASS"
} 2>&1 | tee "${REPORT_FILE}"

echo "Evidence written to ${REPORT_FILE}"
