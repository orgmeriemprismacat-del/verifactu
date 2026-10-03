<?php

require_once __DIR__ . '/SifRedsysGiftStatusClient.php';

function uc017ResolvePaymentReturn(string $browserReturn): array
{
    $browserReturn = strtoupper(trim($browserReturn));
    $dsOrder = trim((string) ($_GET['order'] ?? ''));

    $giftCutoverEnabled = filter_var(
        getenv('SIF_REDSYS_GIFT_CUTOVER_ENABLED') ?: '0',
        FILTER_VALIDATE_BOOLEAN
    );
    $legacyDrainConfirmed = filter_var(
        getenv('SIF_REDSYS_GIFT_LEGACY_DRAIN_CONFIRMED') ?: '0',
        FILTER_VALIDATE_BOOLEAN
    );
    $sifCallbackUrl = trim((string) getenv('SIF_REDSYS_CALLBACK_URL'));
    $statusEnabled = $giftCutoverEnabled
        && $legacyDrainConfirmed
        && $sifCallbackUrl !== ''
        && str_starts_with($sifCallbackUrl, 'https://');

    $paymentStatus = null;
    if ($statusEnabled && $dsOrder !== '') {
        try {
            $paymentStatus = (new SifRedsysGiftStatusClient())->get($dsOrder);
        } catch (Throwable $exception) {
            $paymentStatus = null;
        }
    }

    $authoritative = is_array($paymentStatus);
    $status = $authoritative
        ? strtoupper((string) ($paymentStatus['status'] ?? 'PENDING'))
        : 'PENDING';

    if ($status === 'CONFIRMED') {
        return [
            'title' => 'Pagament del regal confirmat',
            'message' => 'El cobrament, la factura i la preparació del dret de regal consten processats correctament.',
            'status' => 'CONFIRMED',
            'authoritative' => true,
        ];
    }

    if ($status === 'REJECTED') {
        return [
            'title' => 'Pagament no autoritzat',
            'message' => 'El pagament del regal no consta autoritzat. Pots tornar-ho a provar.',
            'status' => 'REJECTED',
            'authoritative' => true,
        ];
    }

    if ($status === 'REVIEW') {
        return [
            'title' => 'Pagament en revisió',
            'message' => 'El cobrament necessita revisió. No facis un segon pagament fins que es resolgui.',
            'status' => 'REVIEW',
            'authoritative' => true,
        ];
    }

    $message = $authoritative
        ? 'La notificació s’ha rebut i el regal encara s’està processant. No repeteixis el pagament.'
        : ($browserReturn === 'KO'
            ? 'El TPV ha retornat una incidència o cancel·lació, però la confirmació definitiva depèn del callback servidor a servidor.'
            : 'El TPV ha retornat al web, però el pagament del regal encara no està confirmat pel sistema autoritatiu.');

    return [
        'title' => 'Pagament pendent de confirmació',
        'message' => $message,
        'status' => $authoritative ? $status : 'UNVERIFIED',
        'authoritative' => $authoritative,
    ];
}

function uc017RenderPaymentReturn(string $browserReturn): void
{
    $view = uc017ResolvePaymentReturn($browserReturn);
    $title = htmlspecialchars($view['title'], ENT_QUOTES, 'UTF-8');
    $message = htmlspecialchars($view['message'], ENT_QUOTES, 'UTF-8');

    echo "<div id='contingut' class='prisma-container container separacio-peu' role='main'>
        <div class='container' id='notfound'>
            <div class='col-md-12'>
                <div class='page-error-content text-center'>
                    <h1>{$title}</h1>
                    <p class='mb-4'>{$message}</p>
                </div>
            </div>
        </div>
    </div>";
}
