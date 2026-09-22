<?php
include("ConnexioBBDD.php");
$parts_url = explode('?',$_SERVER['REQUEST_URI']);
if (substr($parts_url[0],-1)=='/')
	$url_amigable = substr($parts_url[0],0,-1);
else
	$url_amigable = $parts_url[0];

$connexio = new ConnexioBBDD();
$connexio->connectarBD();

/* Consulta a la taula HEAD buscant el registre on ID_URL = $parts_ul[0] */
$connexio->consultarBD("SELECT ID FROM amigable WHERE URL = '".$url_amigable."'");
$row = $connexio->obtenirResultat();
$id_url = $row['ID'];

/* Consulta a la taula HEAD buscant el registre on ID_URL = $parts_ul[0] */
$connexio->consultarBD("SELECT ID_META, ID_OG, ID_TWITTER, ID_CANONICA FROM head WHERE ID_URL = ".$id_url."");
$row = $connexio->obtenirResultat();

if ($connexio->obtenirNumRows()>0) {
	$id_meta = $row['ID_META'];
	$id_og = $row['ID_OG'];
	$id_twitter = $row['ID_TWITTER'];
	$id_canon = $row['ID_CANONICA'];


	/* Consulta a la taula META buscant el registre on ID = ID_META anterior */
	$connexio->consultarBD("SELECT TITLE, AUTHOR, DESCRIPCIO FROM meta WHERE ID = ".$id_meta."");
	$row = $connexio->obtenirResultat();
	if ($connexio->obtenirNumRows()>0) {
		$meta_title = $row['TITLE'];
		$meta_autor = $row['AUTHOR'];
		$meta_descripcio = $row['DESCRIPCIO'];

		$meta = "<title>".$meta_title."</title>";
		$meta .= "<meta name='description' content=\"".$meta_descripcio."\" />";
		$meta .= "<meta name='author' content=\"".$meta_autor."\" />";
	}

	/* Consulta a la taula OG buscant el registre on ID = ID_OG anterior */
	$connexio->consultarBD("SELECT TITLE, SITE_NAME, DESCRIPTION, LOCALE, TYPE, ID_URL, ID_IMG, VIDEO FROM og WHERE ID = ".$id_og."");
	$row = $connexio->obtenirResultat();

	if ($connexio->obtenirNumRows()>0) {
		$og_title = $row['TITLE'];
		$og_sitename = $row['SITE_NAME'];
		$og_descripcio = $row['DESCRIPTION'];
		$og_locale = $row['LOCALE'];
		$og_type = $row['TYPE'];
		$og_id_url = $row['ID_URL'];
		$og_id_img = $row['ID_IMG'];
		$og_video = $row['VIDEO'];

		/* Buscar link de la url */
		$connexio->consultarBD("SELECT URL FROM amigable WHERE ID = ".$og_id_url."");
		$row = $connexio->obtenirResultat();
		$link_url = $row['URL'];

		/* Buscar alt i link de la imatge */
		$connexio->consultarBD("SELECT ALT, URL FROM imatges WHERE ID = ".$og_id_img."");
		$row = $connexio->obtenirResultat();
		$link_img = $row['URL'];
		$alt_img = $row['ALT'];

		/* Separar $og_video en tres parts */
		if ($og_video!="") $parts_video = explode('|',$og_video);

		$og = "<meta property='og:url' content='https://www.prisma.cat".$link_url."' />";
		$og .= "<meta property='og:type' content='".$og_type."'/>";
		$og .= "<meta property='og:title' content=\"".$og_title."\" />";
		$og .= "<meta property='og:description' content=\"".$og_descripcio."\" />";
		if ($og_video!="")
		{
			$og .= "<meta property='og:video' content='https://www.youtube.com/embed/".$parts_video[0]."' />";
			$og .= "<meta property='og:video:width' content='".$parts_video[1]."' />";
			$og .= "<meta property='og:video:height' content='".$parts_video[2]."' />";
		}
		$og .= "<meta property='og:image' content='https://www.prisma.cat".$link_img."' />";
		$og .= "<meta property='og:image:alt' content=\"".$alt_img."\" />";
		$og .= "<meta property='og:image:width' content='600' />";
		$og .= "<meta property='og:image:height' content='315' />";
		$og .= "<meta property='og:site_name' content=\"".$og_sitename."\"/>";
		$og .= "<meta property='og:locale' content=\"".$og_locale."\"/>";

	}

	/* Consulta a la taula buscant el registre a twitter on ID = ID_TWITTER anterior */
	$connexio->consultarBD("SELECT TITLE, SITE, DESCRIPTION, CARD, ID_IMG, VIDEO FROM twitter WHERE ID = ".$id_twitter."");
	$row = $connexio->obtenirResultat();

	if ($connexio->obtenirNumRows()>0) {
		$tw_title = $row['TITLE'];
		$tw_site = $row['SITE'];
		$tw_descripcio = $row['DESCRIPTION'];
		$tw_card = $row['CARD'];
		$tw_id_img = $row['ID_IMG'];
		$tw_video = $row['VIDEO'];

		if ($tw_video!="") $parts_video = explode('|',$tw_video);

		/* Buscar alt i link de la imatge */
		$connexio->consultarBD("SELECT URL FROM imatges WHERE ID = ".$tw_id_img."");
		$row = $connexio->obtenirResultat();
		$link_img = $row['URL'];

		$twitter = "<meta name='twitter:card' content='".$tw_card."'>";
		$twitter .= "<meta name='twitter:site' content=\"".$tw_site."\">";
		$twitter .= "<meta name='twitter:creator' content='".$tw_site."'>";
		$twitter .= "<meta name='twitter:title' content=\"".$tw_title."\">";
		$twitter .= "<meta name='twitter:description' content=\"".$tw_descripcio."\">";
		if ($tw_video!="")
		{
			$twitter .= "<meta name='twitter:player' content='https://www.youtube.com/embed/".$parts_video[0]."?rel=0'>";
			$twitter .= "<meta name='twitter:player:width' content='".$parts_video[1]."'>";
			$twitter .= "<meta name='twitter:player:height' content='".$parts_video[2]."'>";
		}
		$twitter .= "<meta name='twitter:image' content='https://www.prisma.cat".$link_img."'>";
	}

	/* Consulta a la taula CANNONICA buscant el registre on ID = ID_CANNOINCA anterior */
	$connexio->consultarBD("SELECT LINK FROM canonica WHERE ID = ".$id_canon."");
	$row = $connexio->obtenirResultat();
	if ($connexio->obtenirNumRows()>0) {
		$link = $row['LINK'];

		$canonical = "<link rel = \"canonical\" href=\"https://www.prisma.cat".$link."\" />";
	}
}
$connexio->lliurarConsulta();
$connexio->desconectarBD();

echo $canonical;
echo $meta;
echo $og;
echo $twitter;
?>
