<?php

require dirname(__DIR__, 3) . '/src/autoload.php';

use Prisma\Sif\Database\ConnectionFactory;
use Prisma\Sif\Http\IncidentPanelSession;
use Prisma\Sif\Repository\InternalApiRequestRepository;
use Prisma\Sif\Service\PanelLaunchAuthenticator;

$config = require dirname(__DIR__, 3) . '/config/sif.php';
$panelConfig = $config['panel'] ?? [];
$session = new IncidentPanelSession((string) ($panelConfig['session_name'] ?? 'SIFPANELSESSID'));
$session->start();

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {
    try {
        $db = ConnectionFactory::make($config);
        $actor = (new PanelLaunchAuthenticator(
            $db,
            new InternalApiRequestRepository(),
            (string) ($panelConfig['launch_key_id'] ?? ''),
            (string) ($panelConfig['launch_secret'] ?? ''),
            (int) ($panelConfig['max_clock_skew_seconds'] ?? 120)
        ))->authenticate($_POST, (string) ($panelConfig['launch_path'] ?? '/sif/incidencies/'));

        $session->establish($actor);
        header('Location: ' . (string) ($panelConfig['launch_path'] ?? '/sif/incidencies/'), true, 303);
        return;
    } catch (\Throwable $exception) {
        http_response_code(in_array((int) $exception->getCode(), [401, 403, 409, 422], true) ? (int) $exception->getCode() : 500);
        $launchError = 'No s\'ha pogut validar l\'accés al panell SIF.';
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
<title>Incidències SIF</title><link rel="stylesheet" href="./style.css"></head>
<body><main class="shell"><section class="empty-state"><h1>Incidències SIF</h1>
<p><?= htmlspecialchars($launchError, ENT_QUOTES, 'UTF-8') ?></p>
<p><a href="https://intranet.prisma.cat/sif-verifactu.php">Tornar a la intranet</a></p></section></main></body></html><?php
    return;
}

$roles = array_map(static fn ($role): string => strtoupper(trim((string) $role)), (array) ($actor['roles'] ?? []));
$manageRoles = array_map(static fn ($role): string => strtoupper(trim((string) $role)), (array) (($config['incidents']['manage_roles'] ?? [])));
$canManage = array_intersect($roles, $manageRoles) !== [];
$csrf = $session->csrfToken();
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="referrer" content="same-origin">
    <title>Incidències SIF | PrisMa</title>
    <link rel="stylesheet" href="./style.css">
</head>
<body>
<header class="topbar">
    <div>
        <strong>SIF PrisMa</strong>
        <span class="muted">/ Incidències</span>
    </div>
    <div class="actor">
        <span><?= htmlspecialchars((string) ($actor['actor_id'] ?? ''), ENT_QUOTES, 'UTF-8') ?></span>
        <button id="logout" type="button" class="button ghost">Sortir</button>
    </div>
</header>

<main id="incident-app" class="shell"
      data-csrf="<?= htmlspecialchars($csrf, ENT_QUOTES, 'UTF-8') ?>"
      data-can-manage="<?= $canManage ? '1' : '0' ?>">
    <section class="hero">
        <div>
            <h1>Gestió d’incidències</h1>
            <p>Font oficial del cicle d’incidències SIF. Les reparacions fiscals o econòmiques es fan al cas d’ús corresponent.</p>
        </div>
        <button id="refresh" class="button primary" type="button">Actualitzar</button>
    </section>

    <div id="alert" class="alert hidden" role="alert"></div>

    <section id="summary" class="metrics" aria-label="Resum d'incidències"></section>

    <section class="panel">
        <div class="panel-head">
            <h2>Incidències</h2>
            <div class="filters">
                <select id="filter-status">
                    <option value="">Tots els estats</option>
                    <option>OPEN</option><option>IN_PROGRESS</option><option>RESOLVED</option><option>DISMISSED</option>
                </select>
                <select id="filter-severity">
                    <option value="">Totes les prioritats</option>
                    <option>CRITICAL</option><option>HIGH</option><option>MEDIUM</option><option>LOW</option>
                </select>
                <input id="filter-type" type="search" maxlength="50" placeholder="Tipus exacte">
            </div>
        </div>
        <div class="table-wrap">
            <table>
                <thead><tr><th>ID</th><th>Prioritat</th><th>Estat</th><th>Tipus</th><th>Recurs</th><th>Responsable</th><th>Actualització</th><th></th></tr></thead>
                <tbody id="incident-rows"></tbody>
            </table>
        </div>
    </section>

    <section id="detail" class="panel hidden">
        <div class="panel-head">
            <div><h2>Expedient <span id="detail-id"></span></h2><div id="detail-meta" class="muted"></div></div>
            <button id="close-detail" class="button ghost" type="button">Tancar</button>
        </div>
        <div id="detail-fields" class="detail-grid"></div>
        <h3>Historial</h3>
        <div id="timeline" class="timeline"></div>

        <?php if ($canManage): ?>
        <div class="actions-grid">
            <form id="assign-form" class="action-card">
                <h3>Assignar / triage</h3>
                <input name="assignee_id" maxlength="120" required placeholder="Responsable">
                <select name="severity"><option>LOW</option><option selected>MEDIUM</option><option>HIGH</option><option>CRITICAL</option></select>
                <input name="reason_code" maxlength="80" required value="TRIAGE" placeholder="Motiu">
                <textarea name="details" placeholder="Nota"></textarea>
                <button class="button primary" type="submit">Assignar</button>
            </form>

            <form id="evidence-form" class="action-card">
                <h3>Afegir evidència</h3>
                <input name="reason_code" maxlength="80" required value="INVESTIGATION" placeholder="Motiu">
                <input name="evidence_ref" maxlength="500" required placeholder="Referència / descripció d’evidència">
                <textarea name="details" placeholder="Nota"></textarea>
                <button class="button primary" type="submit">Registrar evidència</button>
            </form>

            <form id="close-form" class="action-card">
                <h3>Resoldre / descartar</h3>
                <select name="close_action"><option value="resolve">RESOLVED</option><option value="dismiss">DISMISSED</option></select>
                <input name="reason_code" maxlength="80" required value="VERIFIED" placeholder="Motiu">
                <textarea name="closure_criteria" required placeholder="Criteri de tancament"></textarea>
                <textarea name="resolution_notes" required placeholder="Notes de resolució"></textarea>
                <input name="evidence_ref" maxlength="500" placeholder="Evidència de verificació (obligatòria per RESOLVED)">
                <button class="button danger" type="submit">Tancar expedient</button>
            </form>

            <form id="reopen-form" class="action-card">
                <h3>Reobrir</h3>
                <input name="reason_code" maxlength="80" required value="NEW_EVIDENCE" placeholder="Motiu">
                <textarea name="details" placeholder="Nova causa o evidència"></textarea>
                <button class="button" type="submit">Reobrir</button>
            </form>
        </div>
        <?php endif; ?>
    </section>
</main>

<script src="./app.js" defer></script>
</body>
</html>
