<?php

include("../ConnexioBBDD_PreparedStatment.php");
$connexio = new ConnexioBBDDSTMT();
$connexio->connectarBD();

$idInserit = 117836;

$cipher = "AES-128-CBC";

$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
            AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
$stmt=$connexio->prepare($cnsParam);
$stmt->bind_param("s", $tipusParam);
$tipusParam = 'keyEncriptar';
$stmt->execute();
$stmt->bind_result($keyEncr);
$stmt->fetch();
$connexio->closeStmt();


$ivlen = openssl_cipher_iv_length($cipher);
$iv = openssl_random_pseudo_bytes($ivlen);
$ciphertext_raw = openssl_encrypt($idInserit, $cipher, $keyEncr, $options=OPENSSL_RAW_DATA, $iv);
$hmac = hash_hmac('sha256', $ciphertext_raw, $keyEncr, $as_binary=true);
$hashIdInserit = base64_encode( $iv.$hmac.$ciphertext_raw );

echo $hashIdInserit;

$connexio->desconectarBD();


?>
