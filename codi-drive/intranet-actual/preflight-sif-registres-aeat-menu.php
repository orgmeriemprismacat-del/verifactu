<?php

if (PHP_SAPI !== 'cli') {
    fwrite(STDERR, "This script can only run from CLI.\n");
    exit(1);
}

$root = __DIR__;
chdir($root);
require_once $root . '/ConnexioIntranet.php';

$targetUrl = '/sif-registres-aeat.php';
$result = [
    'ok' => false,
    'scope' => 'uc-009-intranet-menu-discovery',
    'read_only' => true,
    'target_url' => $targetUrl,
    'existing_target' => [],
    'candidates' => [],
];

$connexioIntra = null;

try {
    $connexioIntra = new ConnexioIntranet();
    $connexioIntra->connectarBD();
    $db = $connexioIntra->connexio;

    $exact = $db->prepare(
        'SELECT ID, ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR, ORDRE
         FROM apartats
         WHERE URL = ?
         ORDER BY ID'
    );
    if ($exact === false) {
        throw new RuntimeException('Could not prepare exact UC-009 menu lookup');
    }
    $exact->bind_param('s', $targetUrl);
    $exact->execute();
    $exact->bind_result($id, $icona, $nom, $nivell, $url, $pare, $rols, $ordre);
    while ($exact->fetch()) {
        $result['existing_target'][] = row($id, $icona, $nom, $nivell, $url, $pare, $rols, $ordre);
    }
    $exact->close();

    $candidates = $db->prepare(
        'SELECT ID, ICONA, NOM, NIVELL, URL, ID_NIVELL_PARE, ROLS_VISUALITZAR, ORDRE
         FROM apartats
         WHERE NOM LIKE ? OR NOM LIKE ? OR URL LIKE ? OR URL LIKE ?
         ORDER BY NIVELL, ID_NIVELL_PARE, ORDRE, ID'
    );
    if ($candidates === false) {
        throw new RuntimeException('Could not prepare UC-009 menu candidate lookup');
    }

    $nomFactur = '%Factur%';
    $nomAeat = '%AEAT%';
    $urlSif = '%sif%';
    $urlAeat = '%aeat%';
    $candidates->bind_param('ssss', $nomFactur, $nomAeat, $urlSif, $urlAeat);
    $candidates->execute();
    $candidates->bind_result($id, $icona, $nom, $nivell, $url, $pare, $rols, $ordre);
    while ($candidates->fetch()) {
        $result['candidates'][] = row($id, $icona, $nom, $nivell, $url, $pare, $rols, $ordre);
    }
    $candidates->close();

    $result['existing_target_count'] = count($result['existing_target']);
    $result['candidate_count'] = count($result['candidates']);
    $result['status'] = match (true) {
        $result['existing_target_count'] === 1 => 'ALREADY_PRESENT',
        $result['existing_target_count'] > 1 => 'DUPLICATE_TARGET_URL',
        default => 'CONFIRM_PARENT_ROLES_ORDER_BEFORE_INSERT',
    };
    $result['ok'] = $result['existing_target_count'] <= 1;
} catch (Throwable $exception) {
    $result['error'] = $exception->getMessage();
} finally {
    if ($connexioIntra instanceof ConnexioIntranet
        && $connexioIntra->connexio instanceof mysqli
    ) {
        $connexioIntra->desconectarBD();
    }
}

echo json_encode(
    $result,
    JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
), PHP_EOL;

exit($result['ok'] ? 0 : 1);

function row($id, $icona, $nom, $nivell, $url, $pare, $rols, $ordre): array
{
    return [
        'id' => (int) $id,
        'icon' => (string) $icona,
        'name' => (string) $nom,
        'level' => (int) $nivell,
        'url' => (string) $url,
        'parent_id' => (int) $pare,
        'roles' => (string) $rols,
        'order' => (int) $ordre,
    ];
}
