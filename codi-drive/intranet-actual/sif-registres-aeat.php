<?php
include("inc/comprovarSessio.php");

if (!$configOk) {
    ?>
    <script>window.location.href = "https://intranet.prisma.cat/"</script>
    <?php
    return;
}

if (!isset($_SESSION['sif_aeat_csrf']) || !is_string($_SESSION['sif_aeat_csrf'])) {
    $_SESSION['sif_aeat_csrf'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars($_SESSION['sif_aeat_csrf'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Registres AEAT | Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/general.min.css?ver=1.0">
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/general_v5.min.css?ver=1.0">
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/sif-registres-aeat.css?ver=1.0">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
            integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
            crossorigin="anonymous"></script>
    <script src="https://kit.fontawesome.com/efc6febcf3.js" crossorigin="anonymous"></script>
</head>
<body>
<div class="contingut">
    <div class="sidebar h-100 position-fixed bg-white"></div>
    <main class="mainpanel h-100 position-relative float-end ps" data-csrf="<?= $csrf ?>">
        <div class="p-3">
            <nav class="breadcrump d-flex align-items-center pb-2 border-bottom" aria-label="breadcrumb">
                <button type="button"
                        class="btn-menu d-flex text-white border-0 bg-pris align-items-center justify-content-center me-3 rounded-circle"
                        aria-label="Obre/tanca el menú">
                    <i class="material-icons">more_vert</i>
                </button>
                <i class="material-icons me-3">receipt_long</i>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">Facturació</li>
                    <li class="breadcrumb-item fw-bold">Registres AEAT</li>
                </ol>
            </nav>
        </div>

        <div id="sif-aeat-app" class="px-3 pb-4">
            <div id="sif-aeat-alert" class="alert d-none" role="alert"></div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h1 class="h4 mb-1">Control de remissió AEAT</h1>
                    <p class="text-muted mb-0">Cua fiscal, resultat del registre, intents i incidències.</p>
                </div>
                <div class="d-flex gap-2">
                    <a href="https://intranet.prisma.cat/sif-verifactu.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-shield-halved me-1"></i> VERI*FACTU
                    </a>
                    <button id="sif-aeat-preflight" class="btn btn-outline-secondary btn-sm" type="button">
                        <i class="fa-solid fa-shield-halved me-1"></i> Preflight
                    </button>
                    <button id="sif-aeat-refresh" class="btn btn-primary btn-sm" type="button">
                        <i class="fa-solid fa-rotate me-1"></i> Actualitzar
                    </button>
                </div>
            </div>

            <section class="row g-2 mb-4" id="sif-aeat-summary" aria-label="Resum AEAT"></section>

            <section class="card shadow-sm mb-4">
                <div class="card-header bg-white d-flex flex-wrap gap-2 align-items-center justify-content-between">
                    <strong>Cua fiscal</strong>
                    <div class="d-flex gap-2">
                        <select id="sif-aeat-status" class="form-select form-select-sm" aria-label="Filtra per estat">
                            <option value="">Tots els estats</option>
                            <option>PENDING</option>
                            <option>PROCESSING</option>
                            <option>RETRY</option>
                            <option>REVIEW</option>
                            <option>SENT</option>
                            <option>DEAD_LETTER</option>
                        </select>
                    </div>
                </div>
                <div class="table-responsive">
                    <table class="table table-hover table-sm align-middle mb-0">
                        <thead>
                        <tr>
                            <th>ID</th>
                            <th>Factura</th>
                            <th>Estat cua</th>
                            <th>Intents</th>
                            <th>CSV / error</th>
                            <th>Actualització</th>
                            <th class="text-end">Acció</th>
                        </tr>
                        </thead>
                        <tbody id="sif-aeat-queue"></tbody>
                    </table>
                </div>
            </section>

            <section id="sif-aeat-detail" class="card shadow-sm d-none">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Detall del registre</strong>
                    <button id="sif-aeat-close-detail" class="btn btn-sm btn-outline-secondary" type="button">Tancar</button>
                </div>
                <div class="card-body">
                    <div id="sif-aeat-detail-summary" class="row g-3 mb-3"></div>

                    <h2 class="h6">Intents de remissió</h2>
                    <div class="table-responsive mb-4">
                        <table class="table table-sm">
                            <thead>
                            <tr>
                                <th>Intent</th>
                                <th>Núm.</th>
                                <th>Estat</th>
                                <th>CSV</th>
                                <th>Inici</th>
                                <th>Fi</th>
                                <th class="text-end">Reconciliació</th>
                            </tr>
                            </thead>
                            <tbody id="sif-aeat-attempts"></tbody>
                        </table>
                    </div>

                    <h2 class="h6">Incidències AEAT</h2>
                    <div id="sif-aeat-incidents"></div>
                </div>
            </section>
        </div>
    </main>
</div>

<div class="modal fade" id="sif-aeat-preflight-modal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-scrollable">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title fs-5">Preflight AEAT</h2>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tancar"></button>
            </div>
            <div class="modal-body" id="sif-aeat-preflight-body"></div>
        </div>
    </div>
</div>

<script src="https://intranet.prisma.cat/js/general_v5.js?ver=1.0"></script>
<script src="https://intranet.prisma.cat/js/sif-registres-aeat.js?ver=1.0"></script>
</body>
</html>
