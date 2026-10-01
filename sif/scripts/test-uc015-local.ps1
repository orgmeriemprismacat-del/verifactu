$ErrorActionPreference = 'Stop'

$repo = Split-Path (Split-Path $PSScriptRoot -Parent) -Parent
$reportDir = Join-Path $repo 'sif/test-results'
$stamp = Get-Date -Format 'yyyyMMdd-HHmmss'
$reportFile = Join-Path $reportDir ("uc015-{0}.log" -f $stamp)
$runner = Join-Path $repo 'sif/scripts/local-test.ps1'

if (!(Test-Path -LiteralPath $runner)) {
    throw 'Missing sif/scripts/local-test.ps1'
}

New-Item -ItemType Directory -Path $reportDir -Force | Out-Null

$lines = New-Object System.Collections.Generic.List[string]
$lines.Add('UC-015 local verification')
$lines.Add(('timestamp={0}' -f (Get-Date).ToString('o')))
$lines.Add('runner=sif/scripts/local-test.ps1 -Action Test')
$lines.Add('')

try {
    $output = & $runner -Action Test 2>&1
    $exitCode = $LASTEXITCODE
    foreach ($line in $output) {
        $text = [string]$line
        $lines.Add($text)
        Write-Host $text
    }

    $lines.Add('')
    $lines.Add('Expected focused tests in suite output:')
    $lines.Add('RedsysPaymentIntentTest::testCreatesPackIntentWithFrozenCommercialSnapshot')
    $lines.Add('RedsysPaymentIntentTest::testRejectsPackIntentWhenSnapshotTotalDiffersFromExpectedAmount')
    $lines.Add('RedsysPaymentIntentTest::testRejectsPackIntentWithoutCommercialOrdinal')
    $lines.Add('LegacyPackInvoicePayloadBuilderTest::testUsesCommercialOrdinalWhenSnapshotItemsArriveOutOfOrder')
    $lines.Add('RedsysPackInvoiceServiceTest::testRejectsPackWhenValidatedRedsysAmountDiffersFromInvoiceLines')
    $lines.Add('RedsysPackInvoiceServiceTest::testIntentSnapshotCreatesOneDurableNotificationAcrossRetry')
    $lines.Add('RedsysPackInvoiceServiceTest::testRejectsLegacyPackWithoutCompleteCommercialSnapshot')
    $lines.Add('LegacyPackInvoicePayloadBuilderTest::testRejectsPackLineWithoutExplicitCommercialAmounts')
    $lines.Add('LegacyPackCallbackBoundaryTest::testLegacyPackCallbackIsDisabledByDefaultBeforeLegacyMutationCode')
    $lines.Add('PackCommercialOrderBoundaryTest::testPackPresentationAndEnrollmentUseSameDeterministicOrder')
    $lines.Add('PackCommercialOrderBoundaryTest::testPackOrdinalIsFrozenFromDeterministicComponentLoop')
    $lines.Add('RedsysPackEvidenceVerifierTest::testVerifiesCompletePackEvidenceWithoutExposingPersonalData')
    $lines.Add('RedsysPackEvidenceVerifierTest::testFailsClosedWhenPackOutboxEvidenceIsMissing')
    $lines.Add('PackCheckoutBoundaryTest::testPackCheckoutUsesServerAuthoritativeHolderAndEscapesPostedHtml')
    $lines.Add('RedsysPackWorkerEndToEndTest::testPackWorkerReplayKeepsFiscalEconomicAndOutboxEffectsIdempotent')
    $lines.Add('PackEnrollmentTransportBoundaryTest::testPackEnrollmentMutationUsesPostAndDoesNotReadGetParameters')

    if ($exitCode -ne 0) {
        $lines.Add('RESULT=FAIL')
        throw "UC-015 test suite failed with exit code $exitCode"
    }

    $lines.Add('RESULT=PASS')
}
finally {
    $lines | Set-Content -LiteralPath $reportFile -Encoding UTF8
    Write-Host ("Evidence written to {0}" -f $reportFile)
}
