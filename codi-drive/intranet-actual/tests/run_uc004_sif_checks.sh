#!/usr/bin/env sh
set -eu

intranet_root="$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)"
repo_root="$(CDPATH='' cd -- "$intranet_root/../.." && pwd)"

for file in     "$intranet_root/Usuari.php"     "$intranet_root/SifInternalApiClient.php"     "$intranet_root/SifInvoiceBeforePaymentAccess.php"     "$intranet_root/ajax/alumnes/sifFacturaAbansPagarToken.php"     "$intranet_root/ajax/alumnes/sifFacturaAbansPagarEntitats.php"     "$intranet_root/ajax/alumnes/sifFacturaAbansPagar.php"     "$intranet_root/tests/uc004_sif_static_test.php"     "$repo_root/sif/src/Exception/SifException.php"     "$repo_root/sif/src/Repository/InternalApiRequestRepository.php"     "$repo_root/sif/src/Repository/InvoiceBeforePaymentSelectionRepository.php"     "$repo_root/sif/src/Repository/InvoiceBeforePaymentBillingPartyRepository.php"     "$repo_root/sif/src/Repository/InvoiceBeforePaymentCoverageRepository.php"     "$repo_root/sif/src/Service/InternalApiAuthenticator.php"     "$repo_root/sif/src/Service/InternalInvoiceBeforePaymentScopeResolver.php"     "$repo_root/sif/src/Service/InvoiceBeforePaymentServerPayloadAssembler.php"     "$repo_root/sif/src/Service/InvoiceBeforePaymentLegacyPreparationService.php"     "$repo_root/sif/src/Service/InvoiceBeforePaymentCommandService.php"     "$repo_root/sif/public/api/factures/before-payment.php"     "$repo_root/sif/scripts/preflight-invoice-before-payment-from-legacy.php"     "$repo_root/sif/scripts/preview-invoice-before-payment-from-legacy.php"     "$repo_root/sif/scripts/process-invoice-before-payment-from-legacy.php"
do
    php -l "$file"
done

node --check "$intranet_root/js/alumnes-genera-factura-abans-pagar.js"
php "$intranet_root/tests/uc004_sif_static_test.php"

echo "PASS: sintaxi i contracte estàtic UC-004."
