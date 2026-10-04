<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Http\VersionPanelSession;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\PanelLaunchAuthenticator;

$config = require dirname(__DIR__, 3) . '/config/sif.php';
$panelConfig = $config['panel'] ?? [];
$versionConfig = $config['version_governance'] ?? [];

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");

$session = new VersionPanelSession((string) ($panelConfig['session_name'] ?? 'SIFPANELSESSID'));
$session->start();
$launchPath = (string) ($panelConfig['version_launch_path'] ?? '/sif/versions/');

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {
    try {
        $db = ConnectionFactory::make($config);
        $actor = (new PanelLaunchAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            (string) ($panelConfig['launch_key_id'] ?? ''),
            (string) ($panelConfig['launch_secret'] ?? ''),
            (int) ($panelConfig['max_clock_skew_seconds'] ?? 120)
        ))->authenticate($_POST, $launchPath);

        $session->establish($actor);
        header('Location: ' . $launchPath, true, 303);
        return;
    } catch (\Throwable $exception) {
        http_response_code(in_array((int) $exception->getCode(), [401, 403, 409, 422], true) ? (int) $exception->getCode() : 500);
        $launchError = 'No s\'ha pogut validar l\'accés al panell de versions SIF.';
    }
}

$actor = null;
try {
    $actor = $session->actor();
} catch (\Throwable) {
    if (!isset($launchError)) {
        http_response_code(401);
        $launchError = 'Cal obrir aquest panell des de la intranet PrisMa.';
    }
}

if (!is_array($actor)) {
    ?><!doctype html>
<html lang="ca"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Versions SIF</title><link rel="stylesheet" href="../incidencies/style.css"></head>
<body><main class="shell"><section class="empty-state"><h1>Versions SIF</h1>
<p><?= htmlspecialchars($launchError, ENT_QUOTES, 'UTF-8') ?></p>
<p><a href="https://intranet.prisma.cat/sif-verifactu.php">Tornar a la intranet</a></p></section></main></body></html><?php
    return;
}

$roles = array_values(array_filter(array_map(
    static fn ($role): string => strtoupper(trim((string) $role)),
    (array) ($actor['roles'] ?? [])
)));
$readRoles = array_values(array_filter(array_map(
    static fn ($role): string => strtoupper(trim((string) $role)),
    (array) ($versionConfig['read_roles'] ?? [])
)));
$manageRoles = array_values(array_filter(array_map(
    static fn ($role): string => strtoupper(trim((string) $role)),
    (array) ($versionConfig['manage_roles'] ?? [])
)));
$allowedRoles = array_values(array_unique(array_merge($readRoles, $manageRoles)));
if ($allowedRoles === [] || array_intersect($roles, $allowedRoles) === []) {
    http_response_code(403);
    ?><!doctype html>
<html lang="ca"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Versions SIF</title><link rel="stylesheet" href="../incidencies/style.css"></head>
<body><main class="shell"><section class="empty-state"><h1>Versions SIF</h1>
<p>No tens permisos per consultar la governança de versions del SIF.</p>
<p><a href="https://intranet.prisma.cat/sif-verifactu.php">Tornar a la intranet</a></p></section></main></body></html><?php
    return;
}
$canManage = array_intersect($roles, $manageRoles) !== [];
$csrf = $session->csrfToken();
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>Configuració i versions SIF | PrisMa</title>
    <link rel="stylesheet" href="../incidencies/style.css">
</head>
<body>
<header class="topbar">
    <div><strong>SIF PrisMa</strong><span class="muted"> / Configuració i versions</span></div>
    <div class="actor"><span><?= htmlspecialchars((string) $actor['actor_id'], ENT_QUOTES, 'UTF-8') ?></span>
        <button id="logout" type="button" class="button ghost">Sortir</button></div>
</header>

<main id="version-app" class="shell"
      data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
      data-can-manage="<?= $canManage ? '1' : '0' ?>">
    <section class="hero">
        <div>
            <h1>Governança de configuració i versió</h1>
            <p>Registra i activa només el runtime que el servidor pot demostrar amb bytes, configuració, esquema i declaració verificats.</p>
        </div>
        <button id="refresh" class="button primary" type="button">Actualitzar</button>
    </section>

    <div id="alert" class="alert hidden" role="alert"></div>

    <section class="panel">
        <div class="panel-head"><h2>Runtime observat</h2></div>
        <div id="runtime" class="detail-grid"></div>
    </section>

    <?php if ($canManage): ?>
    <section class="panel">
        <div class="panel-head"><h2>Registrar candidata des del runtime actual</h2></div>
        <form id="register-form" class="actions-grid">
            <div class="action-card">
                <label>Codi de versió <input name="version_code" maxlength="80" required placeholder="2026.10.03-1"></label>
                <label>Motiu <input name="reason_code" maxlength="80" required value="RELEASE_CANDIDATE"></label>
                <button class="button primary" type="submit">Registrar candidata</button>
            </div>
        </form>
        <p class="muted">Els hashes no són editables: es calculen al servidor sobre el runtime desplegat.</p>
    </section>
    <?php endif; ?>

    <section class="panel">
        <div class="panel-head"><h2>Versions registrades</h2></div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Versió</th><th>Estat</th><th>Git</th><th>BD</th><th>Activada</th><th></th></tr></thead>
                <tbody id="version-rows"></tbody>
            </table>
        </div>
    </section>

    <section id="detail" class="panel hidden">
        <div class="panel-head">
            <div><h2>Versió <span id="detail-code"></span></h2><div id="detail-uuid" class="muted"></div></div>
            <button id="close-detail" class="button ghost" type="button">Tancar</button>
        </div>
        <div id="detail-fields" class="detail-grid"></div>

        <h3>Declaració aprovada</h3>
        <div id="declaration" class="detail-grid"></div>

        <h3>Historial d'activacions</h3>
        <div id="activations" class="timeline"></div>

        <?php if ($canManage): ?>
        <div class="actions-grid">
            <form id="declaration-form" class="action-card">
                <h3>Vincular declaració</h3>
                <input name="declaration_version" maxlength="40" required placeholder="v1">
                <input name="storage_key" maxlength="255" required placeholder="2026/declaracio-v1.pdf">
                <input name="reason_code" maxlength="80" required value="DECLARATION_APPROVAL">
                <button class="button primary" type="submit">Verificar bytes i vincular</button>
            </form>

            <form id="preflight-form" class="action-card">
                <h3>Preflight d'activació</h3>
                <input name="backup_evidence_uuid" maxlength="36" placeholder="UUID evidència backup (si és obligatori)">
                <button class="button" type="submit">Comprovar gate</button>
                <div id="preflight-result" class="muted"></div>
            </form>

            <form id="activate-form" class="action-card">
                <h3>Registrar activació</h3>
                <input name="backup_evidence_uuid" maxlength="36" placeholder="UUID evidència backup">
                <input name="reason_code" maxlength="80" required value="APPROVED_RELEASE">
                <button class="button danger" type="submit">Activar versió verificada</button>
                <p class="muted">Aquesta acció no desplega codi: registra la versió que ja coincideix amb el runtime.</p>
            </form>
        </div>
        <?php endif; ?>
    </section>
</main>

<script src="./app.js" defer></script>
</body>
</html>
