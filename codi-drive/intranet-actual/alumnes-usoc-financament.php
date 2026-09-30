<?php
include("inc/comprovarSessio.php");

if (!$configOk) {
    ?>
    <script>window.location.href = "https://intranet.prisma.cat/"</script>
    <?php
} else {
    if (empty($_SESSION['csrf_usoc_financament'])) {
        $_SESSION['csrf_usoc_financament'] = bin2hex(random_bytes(32));
    }
    $csrfUsoc = $_SESSION['csrf_usoc_financament'];
?>
<!DOCTYPE html>
<html lang="ca">
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token-usoc-financament" content="<?php echo htmlspecialchars($csrfUsoc, ENT_QUOTES, 'UTF-8'); ?>">
    <title>Finançament USOC | Intranet</title>
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"/>
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/general.min.css?ver=1.0"/>
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/forms.min.css?ver=1.0"/>
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/alerts.min.css?ver=1.0"/>
    <link rel="stylesheet" href="https://intranet.prisma.cat/css/modals.min.css?ver=1.0"/>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    <script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</head>
<body>
<div class="container-fluid p-4" id="usoc-financament">
    <div class="d-flex justify-content-between align-items-center mb-3">
        <div>
            <h1 class="h3 mb-1">Finançament USOC</h1>
            <p class="text-muted mb-0">Consulta l'expedient, emet la factura de l'entitat i registra els seus cobraments.</p>
        </div>
        <span id="usoc-status-badge" class="badge badge-secondary">Sense carregar</span>
    </div>

    <div id="usoc-alert" class="alert d-none" role="alert"></div>

    <div class="card mb-3">
        <div class="card-header">1. Identificar expedient</div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label for="usoc-id-insc">ID inscripció</label>
                    <input type="number" min="1" class="form-control" id="usoc-id-insc">
                </div>
                <div class="form-group col-md-3">
                    <label for="usoc-idpag">IDPAG</label>
                    <input type="number" min="1" class="form-control" id="usoc-idpag">
                </div>
                <div class="form-group col-md-6 d-flex align-items-end">
                    <button type="button" class="btn btn-primary mr-2" id="usoc-consultar">Consultar</button>
                    <button type="button" class="btn btn-outline-secondary" id="usoc-reconciliar">Reconciliar</button>
                </div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">2. Estat de l'expedient</div>
        <div class="card-body">
            <div class="row">
                <div class="col-md-4"><strong>Factura alumne</strong><div id="usoc-student-invoice">—</div></div>
                <div class="col-md-2"><strong>Import alumne</strong><div id="usoc-student-amount">—</div></div>
                <div class="col-md-2"><strong>Estat alumne</strong><div id="usoc-student-status">—</div></div>
                <div class="col-md-4"><strong>Factura entitat</strong><div id="usoc-entity-invoice">—</div></div>
            </div>
            <hr>
            <div class="row">
                <div class="col-md-2"><strong>Import entitat</strong><div id="usoc-entity-amount">—</div></div>
                <div class="col-md-2"><strong>Estat entitat</strong><div id="usoc-entity-status">—</div></div>
                <div class="col-md-4"><strong>Estat expedient</strong><div id="usoc-case-status">—</div></div>
                <div class="col-md-4"><strong>Correlació</strong><div id="usoc-correlation">—</div></div>
            </div>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">3. Emetre factura de la part USOC</div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-6">
                    <label for="usoc-student-uuid">UUID factura alumne</label>
                    <input type="text" class="form-control" id="usoc-student-uuid">
                </div>
                <div class="form-group col-md-3">
                    <label for="usoc-student-input-amount">Import alumne</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="usoc-student-input-amount">
                </div>
                <div class="form-group col-md-3">
                    <label for="usoc-entity-input-amount">Import USOC</label>
                    <input type="number" step="0.01" min="0.01" class="form-control" id="usoc-entity-input-amount">
                </div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-6"><label>Raó social</label><input type="text" class="form-control" id="usoc-billing-name"></div>
                <div class="form-group col-md-3"><label>NIF/CIF</label><input type="text" class="form-control" id="usoc-billing-nif"></div>
                <div class="form-group col-md-3"><label>Correu</label><input type="email" class="form-control" id="usoc-billing-email"></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-5"><label>Adreça</label><input type="text" class="form-control" id="usoc-billing-address"></div>
                <div class="form-group col-md-2"><label>CP</label><input type="text" class="form-control" id="usoc-billing-cp"></div>
                <div class="form-group col-md-2"><label>Població</label><input type="text" class="form-control" id="usoc-billing-city"></div>
                <div class="form-group col-md-2"><label>Província</label><input type="text" class="form-control" id="usoc-billing-province"></div>
                <div class="form-group col-md-1"><label>País</label><input type="text" maxlength="2" value="ES" class="form-control" id="usoc-billing-country"></div>
            </div>
            <button type="button" class="btn btn-warning" id="usoc-emetre-entitat">Emetre factura USOC</button>
        </div>
    </div>

    <div class="card mb-3">
        <div class="card-header">4. Registrar cobrament de la factura USOC</div>
        <div class="card-body">
            <div class="form-row">
                <div class="form-group col-md-5"><label>UUID factura entitat</label><input type="text" class="form-control" id="usoc-payment-uuid"></div>
                <div class="form-group col-md-2"><label>Import</label><input type="number" step="0.01" min="0.01" class="form-control" id="usoc-payment-amount"></div>
                <div class="form-group col-md-2"><label>Data moviment</label><input type="date" class="form-control" id="usoc-payment-date"></div>
                <div class="form-group col-md-3"><label>Referència bancària</label><input type="text" class="form-control" id="usoc-payment-reference"></div>
            </div>
            <div class="form-row">
                <div class="form-group col-md-3">
                    <label>Mètode</label>
                    <select class="form-control" id="usoc-payment-method">
                        <option value="TRANSFERENCIA">Transferència</option>
                        <option value="MANUAL">Manual</option>
                    </select>
                </div>
                <div class="form-group col-md-3"><label>Banc</label><input type="text" class="form-control" id="usoc-payment-bank"></div>
                <div class="form-group col-md-6"><label>Observacions</label><input type="text" class="form-control" id="usoc-payment-notes"></div>
            </div>
            <button type="button" class="btn btn-success" id="usoc-registrar-cobrament">Registrar cobrament</button>
        </div>
    </div>
</div>
<script src="https://intranet.prisma.cat/js/alumnes-usoc-financament.js?ver=1.0"></script>
</body>
</html>
<?php } ?>