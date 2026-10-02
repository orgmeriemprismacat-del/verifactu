<?php

require_once __DIR__ . '/SifRedsysCourseStatusClient.php';

function uc014ResolvePaymentReturn(string $browserReturn): array
{
    $browserReturn = strtoupper(trim($browserReturn));
    $dsOrder = trim((string) ($_GET['order'] ?? ''));
    $idPagRaw = trim((string) ($_GET['idPag'] ?? ''));

    $courseCutoverEnabled = filter_var(
        getenv('SIF_REDSYS_COURSE_CUTOVER_ENABLED') ?: '0',
        FILTER_VALIDATE_BOOLEAN
    );
    $legacyDrainConfirmed = filter_var(
        getenv('SIF_REDSYS_COURSE_LEGACY_DRAIN_CONFIRMED') ?: '0',
        FILTER_VALIDATE_BOOLEAN
    );
    $sifCallbackUrl = trim((string) getenv('SIF_REDSYS_CALLBACK_URL'));
    $statusEnabled = $courseCutoverEnabled
        && $legacyDrainConfirmed
        && $sifCallbackUrl !== ''
        && str_starts_with($sifCallbackUrl, 'https://');

    $paymentStatus = null;
    if ($statusEnabled && $dsOrder !== '' && ctype_digit($idPagRaw) && (int) $idPagRaw > 0) {
        try {
            $paymentStatus = (new SifRedsysCourseStatusClient())->get($dsOrder, (int) $idPagRaw);
        } catch (Throwable $exception) {
            // Fail closed: browser return is never upgraded to CONFIRMED on lookup failure.
            $paymentStatus = null;
        }
    }

    $authoritative = is_array($paymentStatus);
    $status = $authoritative
        ? strtoupper((string) ($paymentStatus['status'] ?? 'PENDING'))
        : 'PENDING';

    if ($status === 'CONFIRMED') {
        return [
            'title' => 'Pagament confirmat',
            'message' => 'El pagament consta confirmat i processat correctament.',
            'status' => 'CONFIRMED',
            'authoritative' => true,
        ];
    }

    if ($status === 'REJECTED') {
        return [
            'title' => 'Pagament no autoritzat',
            'message' => 'El pagament no consta autoritzat. Pots tornar-ho a provar o contactar amb el teu banc.',
            'status' => 'REJECTED',
            'authoritative' => true,
        ];
    }

    if ($status === 'REVIEW') {
        return [
            'title' => 'Pagament en revisió',
            'message' => 'La notificació s’ha rebut però necessita revisió. No facis un segon pagament fins que es resolgui.',
            'status' => 'REVIEW',
            'authoritative' => true,
        ];
    }

    $message = $authoritative
        ? 'La notificació s’ha rebut i encara s’està processant. No repeteixis el pagament mentre estigui pendent.'
        : ($browserReturn === 'KO'
            ? 'El TPV ha retornat una incidència o cancel·lació, però la confirmació definitiva depèn de la notificació servidor a servidor.'
            : 'El TPV ha retornat al web, però el pagament encara no està confirmat pel sistema autoritatiu.');

    return [
        'title' => 'Pagament pendent de confirmació',
        'message' => $message,
        'status' => $authoritative ? $status : 'UNVERIFIED',
        'authoritative' => $authoritative,
    ];
}

function uc014RenderPaymentReturn(string $browserReturn): void
{
    $view = uc014ResolvePaymentReturn($browserReturn);
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
