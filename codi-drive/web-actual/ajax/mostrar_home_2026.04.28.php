<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Text.php");
include("../Url.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");

try {
    $dispositiu = $_GET['dispositiu'];

    /* ########################     APARTATS     ######################## */
    $slider = "<div class='cnt-slider'></div>";
    $titol = "<div class='separacio-bloc cnt-titol'></div>";
    $puntsForts = "<div class='separacio-bloc cnt-punts-forts py-5'></div>";
    $pagExt = "<div class='separacio-bloc cnt-pag-ext py-5'></div>";
    $comptador = "<div class='separacio-bloc cnt-comptador-usuaris py-5'></div>";
    $butlletí = "<div class='separacio-bloc cnt-butlleti py-5'></div>";

    /* ########################   APARTATS NO    ######################## */
    $cercador = "<div class='cnt-cercador'></div>";
    $anys = "<div class='cnt-baner anys'>
    <div class='back-banner position-relative p-0 m-0'>
    <img data-src='https://www.prisma.cat/img/banners/20_anys.png' alt='20 anys!' src='https://www.prisma.cat/img/banners/20_anys.png' class='baner w-100 h-100 text-white d-flex flex-column justify-content-center align-items-center position-relative'>
    </div></div>";
    $spot = "<div class='cnt-spot pb-5'></div>";

    /* ########################     CURSOS     ######################## */
    $cursos = "<div class='separacio-bloc cnt-cursos py-5'>
       <div class='cnt-titol-filtres'></div>
       <div class='container'><div class='row'>
       <div class='cnt-bloc-filtres col-12 col-lg-3 mb-3 mb-lg-0'></div>
       <div class='cnt-bloc-cursos col-12 col-lg-9'></div>
       </div></div>

       <div class='modal' id='modalLoading' tabindex='-1' role='dialog' aria-labelledby='modalLoading' style='display: none' aria-modal='true'>
       <div class='modal-dialog modal-dialog-centered' role='document'>
       <div class='modal-content w-100 border-0'>
        <div class='modal-body'>
           <div id='loading-wrapper'>
              <div id='loading-text'>Buscant...</div>
              <div id='loading-content'></div>
           </div>
       </div></div></div></div>
    </div>";

    /* ########################     BANNERS     ######################## */
    $bannerRegala = "<div class='cnt-baner regala'></div>";
    $bannerCDD = "<div class='cnt-baner cdd'></div>";
    $bannerCursosEstiu = "<div class='cnt-baner cursos-estiu'></div>";
    $bannerDteSocials = "<div class='cnt-baner descomptes-socials'></div>";
    $bannerDteGrup = "<div class='cnt-baner descomptes'></div>";
    $bannerSubvencions = "<div class='cnt-baner subvencions'></div>";
    $bannerTrobades = "<div class='cnt-baner trobades'></div>";
    $bannerTaller = "<div class='cnt-baner taller'></div>";

    /* ########################    CARROUSSELS    ######################## */
    $carrPerfil = "<div class='cnt-perfils pb-5'></div>";
    $carrFISS = "<div class='cnt-fiss pb-5'></div>";
    $carrTastet = "<div class='cnt-tastets pb-5'></div>";

    /* ########################       VISTA       ######################## */

    $vistaHTML = "<div class='cnt-capcalera d-flex flex-column'>";
    $vistaHTML .= $slider;
    $vistaHTML .= $titol;
    $vistaHTML .= "</div>";
    $vistaHTML .= $puntsForts;
    $vistaHTML .= $cercador;
    // $vistaHTML .= $anys;
    // $vistaHTML .= $spot;
    $vistaHTML .= $bannerCDD;
    // $vistaHTML .= $bannerCursosEstiu;
    // $vistaHTML .= $bannerTaller;
    $vistaHTML .= $cursos;
    $vistaHTML .= $bannerDteSocials;
    $vistaHTML .= $carrPerfil;
    $vistaHTML .= $bannerDteGrup;
    $vistaHTML .= $carrFISS;
    $vistaHTML .= $bannerRegala;
    $vistaHTML .= $carrTastet;
    $vistaHTML .= $bannerTrobades;
    $vistaHTML .= $pagExt;
    $vistaHTML .= $comptador;
    $vistaHTML .= $butlletí;

    $modals .= "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>";
    $modals .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>";
    $modals .= "<div class='modal-content w-100 border-0'><div class='modal-header sense-border color-white'>";
    $modals .= "<p class='modal-title modal-title-danger color-white posicio-esquerra' id='modalErrorsTitle'>Errors</p>";
    $modals .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='color-white'>×</span></button></div>";
    $modals .= "<div class='modal-body' id='modalErrorsBody'></div>";
    $modals .= "<div class='modal-footer justify-content-center text-centrat border-0'>";
    $modals .= "<a role='button' class='btn btn-danger negreta500' aria-label='Close' data-dismiss='modal'>Tanca</a>";
    $modals .= "</div></div></div></div>";

    $modals .= "<div class='modal fade in' id='modalSuccess' tabindex='-1' role='dialog' aria-labelledby='modalSuccessTitle' aria-hidden='true'>";
    $modals .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center' role='document'>";
    $modals .= "<div class='modal-content w-100 border-0'><div class='modal-header sense-border color-white background-prisma'>";
    $modals .= "<p class='modal-title modal-title-success color-white posicio-esquerra' id='modalSuccessTitle'>Sol·licitud enviada</p>";
    $modals .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='color-white'>×</span></button></div>";
    $modals .= "<div class='modal-body' id='modalSuccessBody'></div>";
    $modals .= "<div class='modal-footer justify-content-center text-centrat border-0'>";
    $modals .= "<a role='button' class='btn boto-blau color-white negreta500' id='close-sucess' aria-label='Close' data-dismiss='modal'>Tanca</a>";
    $modals .= "</div></div></div></div>";

    $vistaHTML .= $modals;

	echo $vistaHTML;
}
catch(Exception $e) {
   if ($e->getCode()==404)
      echo mostrarPagina404();
   else
      echo missatgeError($e->getCode());
}
?>
