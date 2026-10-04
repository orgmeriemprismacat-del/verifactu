<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");
include("../Numero.php");
include("../Edicio.php");
include("../PagamentGrupAutomatic.php");
require_once __DIR__ . '/../inc/PackConfirmationToken.php';

header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');

try {
    $encr = trim((string) ($_GET['keyEncr'] ?? ''));
    if ($encr === '' || strlen($encr) > 2048) {
        http_response_code(400);
        echo missatgeError('1501');
        return;
    }

    $connexio = new ConnexioBBDDSTMT();
    $connexio->connectarBD();

    $cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
                    AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
    $stmt = $connexio->prepare($cnsParam);
    $stmt->bind_param("s", $tipusParam);
    $tipusParam = 'keyEncriptar';
    $stmt->execute();
    $stmt->bind_result($keyEncr);
    $stmt->fetch();
    $connexio->closeStmt();

    try {
        // UC-015: el token v2 autentica IV + ciphertext abans de desxifrar.
        $original_idInsc = PackConfirmationToken::decode($encr, $keyEncr);
    } catch (Throwable $tokenException) {
        $connexio->desconectarBD();
        http_response_code(400);
        echo missatgeError('1501');
        return;
    }

    $pagament = new PagamentGrupAutomatic($original_idInsc);
    $mostrar = $pagament->mostrarPaginaConfirmacio();

    $connexio->desconectarBD();
    echo $mostrar;
}
catch(Exception $e) {
    if ($e->getCode() == 404) {
        echo mostrarPagina404();
    }
    else if ($e->getCode() == 2409) {
        $connexio = new ConnexioBBDDSTMT();
        $connexio->connectarBD();

        $cnsInsc = "SELECT IDPAG FROM inscripcions WHERE ID=?";
        $stmt = $connexio->prepare($cnsInsc);
        $stmt->bind_param("d", $original_idInsc);
        $stmt->execute();
        $stmt->store_result();
        if ($stmt->num_rows() > 0) {
            $stmt->bind_result($idPag);
            $stmt->fetch();
            $pagament = new PagamentGrupAutomatic($idPag);
            $mostrar = $pagament->mostrarPaginaConfirmacio();
        }
        $connexio->closeStmt();
        $connexio->desconectarBD();

        echo $mostrar;
    }
    else {
        echo missatgeError($e->getCode());
    }
}

?>
