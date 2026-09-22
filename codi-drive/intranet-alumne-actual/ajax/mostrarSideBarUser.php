<?php
require('../../config.php');

include ('../ConnexioIntranet.php');
include ('../ConnexioWeb.php');
include ('../ConnexioMoodle.php');
include ('../Text.php');
include ('../Usuari.php');
include ('../IntranetAlumne.php');
include ('../inc/missatgesError.php');
session_start();

try {
	$objUsuari = unserialize( $_SESSION['objUsuariMdl'] );
	$objIntranet = unserialize( $_SESSION['objIntranetAlumne'] );

	$usuari = '';
	$nom = '';
	$cognoms = '';
	$idUser = '';
	$idPicture = '';
	if ( $objUsuari->getUsuari() )
		$usuari = $objUsuari->getUsuari()->get();
	if ( $objUsuari->getNom() )
		$nom = $objUsuari->getNom()->get();
	if ( $objUsuari->getCognom() )
		$cognoms = $objUsuari->getCognom()->get();
	if ( $objUsuari->getIdUser() )
		$idUser = $objUsuari->getIdUser();
	if ( $objUsuari->getIdPicture() )
		$idPicture = $objUsuari->getIdPicture();
	$connexioMoodle = new ConnexioMoodle();
	$connexioMoodle->connectarBD();

	$cnsMdlFile = "SELECT contextid FROM mdl_files WHERE id = ?";

	if ( $stmtMdl = $connexioMoodle->prepare($cnsMdlFile) ) {
		$stmtMdl->bind_param("d", $idPicture);
		$stmtMdl->execute();
		$stmtMdl->bind_result($contextIdUser);
		$stmtMdl->fetch();
		$connexioMoodle->closeStmt();
	}
	else {
		throw new Exception('', 7014);
	}

	$connexioMoodle->desconectarBD();

	if ( $contextIdUser != '' )
		$urlImg = "https://campus.prisma.cat/pluginfile.php/".$contextIdUser."/user/icon/f3";
	else
		$urlImg = "https://campus.prisma.cat/intranet-alumnes/img/not-found.png";

	$url_exists = $objIntranet->url_exists( $urlImg ) ? "1" : "0";
	if( !$url_exists )
		$urlImg = "https://www.prisma.cat/campus/intranet-alumnes/img/not-found.png";

	$mostrar="
	<div class='d-flex align-items-center mx-2'>
		<img class='photo rounded-circle pointer ' src='".$urlImg."'>
    <div class='user-info' style='flex: 1 0 auto'>
        <a data-toggle='collapse' href='#collapseUser' class='collapsed' aria-expanded='false'>
            <p>".$nom." ".$cognoms."</p>
        </a>
    </div>
	</div>";
}
catch (Exception $e) {
  echo missatgeError( $e->getCode() );
}

echo $mostrar;
?>
