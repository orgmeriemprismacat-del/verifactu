<?php
include("parametres_connexio.php");
include("ConnexioBBDD_PreparedStatment.php");

/* ########################        Buscar path        ######################## */
$parts_url = explode('?',$_SERVER['REQUEST_URI']);
if (substr($parts_url[0],-1)=='/')
	$pathUrl = substr($parts_url[0],0,-1);
else
	$pathUrl = $parts_url[0];

// $pathUrl = "/cursos/neuroeducacio-aprendre-ensenyar-cervell";

/* ########################         Consultes         ######################## */

$cnsUrl = "SELECT ID FROM amigable WHERE URL=?";
$cnsHead = "SELECT ID_META, ID_OG, ID_TWITTER, ID_CANONICA FROM head WHERE ID_URL=?";
$cnsMeta = "SELECT TITLE, AUTHOR, DESCRIPCIO FROM meta WHERE ID=?";
$cnsOG = "SELECT TITLE, SITE_NAME, DESCRIPTION, LOCALE, TYPE, ID_URL, ID_IMG, VIDEO FROM og WHERE ID=?";
$cnsTw = "SELECT TITLE, SITE, DESCRIPTION, CARD, ID_IMG, VIDEO FROM twitter WHERE ID=?";
$cnsCan = "SELECT LINK FROM canonica WHERE ID=?";

$connexio = new ConnexioBBDDSTMT();
$connexio->connectarBD();

$canonical="";
$meta="";
$og="";
$twitter="";

/* Consultem la id de la taula amigable corresponent al path */
$stmUrl = $connexio->prepare($cnsUrl);
$stmUrl->bind_param("s", $pathUrl);
$stmUrl->execute();
$stmUrl->bind_result($id_url);
$stmUrl->fetch();
$connexio->closeStmt();

/* Consultem els ids ID_META, ID_OG, ID_TWITTER, ID_CANONICA de la taula amigable corresponent a la ID_URL $id_url */
$stmHead = $connexio->prepare($cnsHead);
$stmHead->bind_param("d", $id_url);
$stmHead->execute();
$stmHead->store_result();
if ($stmHead->num_rows()>0) {
	$stmHead->bind_result($id_meta, $id_og, $id_tw, $id_can);
	$stmHead->fetch();
	$connexio->closeStmt();

	/* Consulta a la taula META buscant el registre on ID = ID_META anterior */
	$stmMeta = $connexio->prepare($cnsMeta);
	$stmMeta->bind_param("d", $id_meta);
	$stmMeta->execute();
	$stmMeta->store_result();
	if ($stmMeta->num_rows()>0) {
		$stmMeta->bind_result($metaTitle, $metaAuthor, $metaDescripcio);
		$stmMeta->fetch();

		$meta = '<title>'.$metaTitle.'</title>';
		$meta .= "<meta name='description' content=\"".$metaDescripcio."\" />";
		$meta .= "<meta name='author' content=\"".$metaAuthor."\" />";
	}
	$connexio->closeStmt();

	/* Consulta a la taula OG buscant el registre on ID = ID_OG anterior */
	$stmOG = $connexio->prepare($cnsOG);
	$stmOG->bind_param("d", $id_og);
	$stmOG->execute();
	$stmOG->store_result();
	if ($stmOG->num_rows()>0) {
		$stmOG->bind_result($ogTitle, $ogSiteName, $ogDescription, $ogLocale, $ogType, $ogIdUrl, $ogIdImg, $ogVideo);
		$stmOG->fetch();
		$connexio->closeStmt();

		$og = "";

		/* Buscar link de la url */
		$stmLink = $connexio->prepare("SELECT URL FROM amigable WHERE ID=?");
		$stmLink->bind_param("d", $ogIdUrl);
		$stmLink->execute();
		$stmLink->store_result();
		if ( $stmLink->num_rows()>0 ) {
			$stmLink->bind_result($linkUrl);
			$stmLink->fetch();
			$og .= "<meta property='og:url' content='https://www.prisma.cat".$linkUrl."' />";
		}
		$connexio->closeStmt();

		$og .= "<meta property='og:type' content='".$ogType."'/>";
		$og .= "<meta property='og:title' content=\"".$ogTitle."\" />";
		$og .= "<meta property='og:description' content=\"".$ogDescription."\" />";

		if ($ogVideo!="") {
			$partsVideo = explode('|',$ogVideo);
			$og .= "<meta property='og:video' content='https://www.youtube.com/embed/".$partsVideo[0]."' />";
			$og .= "<meta property='og:video:width' content='".$partsVideo[1]."' />";
			$og .= "<meta property='og:video:height' content='".$partsVideo[2]."' />";
		}

		/* Buscar alt i link de la imatge */
		$stmImg = $connexio->prepare("SELECT ALT, URL FROM imatges WHERE ID=?");
		$stmImg->bind_param("d", $ogIdImg);
		$stmImg->execute();
		$stmImg->store_result();
		if ( $stmImg->num_rows()>0 ) {
			$stmImg->bind_result($altImg, $linkImg);
			$stmImg->fetch();

			$og .= "<meta property='og:image' content='https://www.prisma.cat".$linkImg."' />";
			$og .= "<meta property='og:image:alt' content='".$altImg."' />";
			$og .= "<meta property='og:image:width' content='600' />";
			$og .= "<meta property='og:image:height' content='315' />";
		}
		$connexio->closeStmt();

		$og .= "<meta property='og:site_name' content='".$ogSiteName."'/>";
		$og .= "<meta property='og:locale' content='".$ogLocale."'/>";
	}
	else
		$connexio->closeStmt();

	/* Consulta a la taula TW buscant el registre on ID = ID_TW anterior */
	$stmTw = $connexio->prepare($cnsTw);
	$stmTw->bind_param("d", $id_tw);
	$stmTw->execute();
	$stmTw->store_result();
	if ($stmTw->num_rows()>0) {
		$stmTw->bind_result($twTitle, $twSite, $twDescription, $twCard, $twIdImg, $twVideo);
		$stmTw->fetch();
		$connexio->closeStmt();

		$twitter = "<meta name='twitter:card' content='".$twCard."'>";
		$twitter .= "<meta name='twitter:site' content='".$twSite."'>";
		$twitter .= "<meta name='twitter:creator' content='".$twSite."'>";
		$twitter .= "<meta name='twitter:title' content=\"".$twTitle."\">";
		$twitter .= "<meta name='twitter:description' content=\"".$twDescription."\">";
		if ($twVideo!='') {
			$partsVideo = explode('|',$twVideo);
			$twitter .= "<meta name='twitter:player' content='https://www.youtube.com/embed/".$partsVideo[0]."?rel=0'>";
			$twitter .= "<meta name='twitter:player:width' content='".$partsVideo[1]."'>";
			$twitter .= "<meta name='twitter:player:height' content='".$partsVideo[2]."'>";
		}

		/* Buscar link de la url */
		$stmLinkImg = $connexio->prepare("SELECT URL FROM imatges WHERE ID=?");
		$stmLinkImg->bind_param("d", $twIdImg);
		$stmLinkImg->execute();
		$stmLinkImg->store_result();
		if ($stmLinkImg->num_rows()>0) {
			$stmLinkImg->bind_result($linkImg);
			$stmLinkImg->fetch();
			$twitter .= "<meta name='twitter:image' content='https://www.prisma.cat".$linkImg."'>";
		}
		$connexio->closeStmt();
	}
	else
		$connexio->closeStmt();

	/* Consulta a la taula Cannonica buscant el registre on ID = ID_CANNOINCA anterior */
	$stmCan = $connexio->prepare($cnsCan);
	$stmCan->bind_param("d", $id_can);
	$stmCan->execute();
	$stmCan->store_result();
	if ($stmCan->num_rows()>0) {
		$stmCan->bind_result($canLink);
		$stmCan->fetch();
		$connexio->closeStmt();

		$canonical = "<link rel='canonical' href=\"https://www.prisma.cat".$canLink."\"/>";
	}
	$connexio->closeStmt();
}
else
	$connexio->closeStmt();

$connexio->desconectarBD();

echo $canonical;
echo $meta;
echo $og;
echo $twitter;
?>
