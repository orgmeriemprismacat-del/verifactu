<?php
include("inc/comprovarSessio.php");

if (!$configOk) {
    ?><script>window.location.href = "https://intranet.prisma.cat/"</script><?php
    return;
}

if (!isset($_SESSION['sif_verifactu_csrf']) || !is_string($_SESSION['sif_verifactu_csrf'])) {
    $_SESSION['sif_verifactu_csrf'] = bin2hex(random_bytes(32));
}
$csrf = htmlspecialchars($_SESSION['sif_verifactu_csrf'], ENT_QUOTES, 'UTF-8');
?>
<!doctype html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>VERI*FACTU | Intranet</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet"
          integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/general.min.css?ver=1.0">
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/general_v5.min.css?ver=1.0">
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/sif-verifactu.css?ver=1.0">
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
                        aria-label="Obre/tanca el menú"><i class="material-icons">more_vert</i></button>
                <i class="material-icons me-3">verified</i>
                <ol class="breadcrumb mb-0">
                    <li class="breadcrumb-item">Facturació</li>
                    <li class="breadcrumb-item fw-bold">VERI*FACTU</li>
                </ol>
            </nav>
        </div>

        <div id="sif-verifactu-app" class="px-3 pb-4">
            <div id="sif-verifactu-alert" class="alert d-none" role="alert"></div>

            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
                <div>
                    <h1 class="h4 mb-1">VERI*FACTU · resum operatiu</h1>
                    <p class="text-muted mb-0">La intranet només mostra resum i accessos. La gestió oficial d'incidències es fa al SIF.</p>
                </div>
                <div class="d-flex flex-wrap gap-2">
                    <a href="https://intranet.prisma.cat/sif-registres-aeat.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fa-solid fa-file-circle-check me-1"></i> Registres AEAT
                    </a>
                    <button id="sif-open-incidents" class="btn btn-primary btn-sm" type="button">
                        <i class="fa-solid fa-triangle-exclamation me-1"></i> Obrir incidències SIF
                    </button>
                    <button id="sif-verifactu-refresh" class="btn btn-outline-primary btn-sm" type="button">
                        <i class="fa-solid fa-rotate me-1"></i> Actualitzar
                    </button>
                </div>
            </div>

            <section id="sif-verifactu-summary" class="row g-2 mb-4" aria-label="Resum d'incidències"></section>

            <section class="card shadow-sm">
                <div class="card-header bg-white d-flex justify-content-between align-items-center">
                    <strong>Incidències recents</strong>
                    <span class="small text-muted">Només lectura</span>
                </div>
                <div class="table-responsive">
                    <table class="table table-sm table-hover align-middle mb-0">
                        <thead><tr>
                            <th>ID</th><th>Prioritat</th><th>Estat</th><th>Tipus</th><th>Responsable</th><th>Actualització</th>
                        </tr></thead>
                        <tbody id="sif-verifactu-incidents"></tbody>
                    </table>
                </div>
            </section>
        </div>
    </main>
</div>

<script src="https://intranet.prisma.cat/js/general_v5.js?ver=1.0"></script>
<script src="https://intranet.prisma.cat/js/sif-verifactu.js?ver=1.0"></script>
</body>
</html>
