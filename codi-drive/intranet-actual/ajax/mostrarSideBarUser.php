<?php
session_start();

include ('../ConnexioIntranet.php');
include ('../ConnexioWeb.php');
include ('../Text.php');
include ('../Usuari.php');
include ('../inc/missatgesError.php');

try {
	$_SESSION['usuari'] = unserialize($_SESSION['usuari']);
	$_SESSION['intranet'] 	= unserialize($_SESSION['intranet']);

	$path = "https://intranet.prisma.cat/perfil/";

	$usuari = $_SESSION['usuari']->getUsuari()->get();
	$password = $_SESSION['usuari']->getHashPass()->get();

	$connexioIntra = new ConnexioIntranet();
	$connexioIntra->connectarBD();
	$cnsPass = "SELECT IMG_PERFIL, NOM, COGNOMS, DEPARTAMENT FROM usuaris WHERE USUARI LIKE ?";
	$stmtIntra=$connexioIntra->prepare($cnsPass);
	$stmtIntra->bind_param("s", $usuari);
	$stmtIntra->execute();
	$stmtIntra->store_result();
	if ($stmtIntra->num_rows() > 0) {
		$stmtIntra->bind_result($urlImg, $nom, $cognoms, $departament);
		$stmtIntra->fetch();
	}
	else {
		throw new Exception('',3001);
	}
	$connexioIntra->closeStmt();
	$connexioIntra->desconectarBD();

	$_SESSION['usuari']->setNom($nom);
	$_SESSION['usuari']->setCognoms($cognoms);
	$_SESSION['usuari']->setDepartament($departament);

	$mostrar="
		<div class='d-flex align-items-center'>
			<div class='photo float-left mr-2 ml-2 rounded-circle'>
				<img class='w-100' src='".$urlImg."'>
			</div>
			<div class='user-info'>
				<a data-toggle='collapse' href='#collapseUser' class='collapsed' aria-expanded='false'>
					<p>".$nom." ".$cognoms."<b class='caret'></b></p>
				</a>
			</div>
			<i class='material-icons text-white pointer logout'>logout</i>
		</div>
		<div id='collapseUser' class='collapse' style=''>
			<ul class='nav'>
				<li class='nav-item'>
					<a href='".$path."/meu-perfil' class='nav-link'>
						<span class='sidebar-mini'>MP</span>
						<span class='sidebar-normal'>El Meu Perfil</span>
					</a>
				</li>
				<li class='nav-item'>
					<a href='".$path."/perfil/edita' class='nav-link'>
						<span class='sidebar-mini'>EP</span>
						<span class='sidebar-normal'>Edita perfil</span>
					</a>
				</li>
				<li class='nav-item'>
					<a href='".$path."/perfil/configuracio' class='nav-link'>
						<span class='sidebar-mini'>C</span>
						<span class='sidebar-normal'>Configuració</span>
					</a>
				</li>
			</ul>
		</div>";
	$mostrar="
	<div class='d-flex align-items-center mx-2'>
		<img class='photo rounded-circle pointer' src='".$urlImg."'>
		<div class='user-info' style='flex: 1 0 auto;'>
			<a data-toggle='collapse' href='#collapseUser' class='collapsed' aria-expanded='false'>
				<p>".$nom." ".$cognoms."</p>
			</a>
		</div>
		<i class='material-icons text-white pointer logout'>logout</i>
	</div>";

	$_SESSION['usuari'] = serialize($_SESSION['usuari']);
	$_SESSION['intranet'] = serialize($_SESSION['intranet']);

}
catch(Exception $e) {
	echo missatgeError($e->getCode());
}

echo $mostrar;
?>
