<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

try {

	$mostrar = "<div class='prisma-footer-content color-footer'>";
	$mostrar .= "<div class='container'>";
	// $mostrar .= "<div class='recon text-center w-100'>
	// 	Cursos reconeguts com a formaci&oacute; permanent del professorat pel Departament d'Educaci&oacute; de la Generalitat de Catalunya
	// </div>";

	// $mostrar .= "<div class='cnt-footer d-flex flex-column flex-lg-row align-items-start px-3 w-100'>";
	// $mostrar .= "<div class='d-flex flex-row col-12 flex-wrap justify-content-center p-0'>
	// 	<div class='peu-contacte py-2'>
	// 		 <p class='seccio titol associacio font-weight-bold mt-0 pt-0'>Associació PrisMa</p>
	// 		 <div class='d-flex flex-column adreca'>
	//  			<div class='d-flex flex-column mb-2'>
	//  				<div>
	//  					<i class='fa fa-map-marker-alt'></i>C. Santa Eugènia, 102, esc. D, entl. 2a
	//  				</div>
	//  			   <div>17006 Girona</div>
	//  			</div>
	//  			<div>
	//  			   <i class='fa fa-phone'></i>972 21 75 65
	//  			</div>
	//  			<div>
	//  				<i class='fa fa-mobile-phone'></i>678 123 687
	//  			</div>
	//  		 </div>
	//
  // 		  <div class='xarxes d-flex flex-row flex-wrap justify-content-start mt-3'>
	// 	  	  <div class='d-flex justify-content-center align-items-center mr-0'>
	// 			  <a class='d-flex align-items-center justify-content-center'
	// 				  aria-label='Instagram' role='link' rel='noopener nofollow' title='Instagram de PrisMa'
	// 				  href='https://www.instagram.com/prisma.educacio/' target='_blank'>
	// 				  <i class='fab fa-instagram peu'></i>
	// 			  </a>
	// 		  </div>
	// 			<div class='no-xarxes d-flex justify-content-center align-items-center mr-2'>
	// 				<!--<span id='insta-nou' class='position-relative text-center text-white ml-1'>NOU</span>-->
	// 			</div>
	// 			<div class='d-flex justify-content-center align-items-center mr-2'>
	// 				<a class='d-flex align-items-center justify-content-center'
	// 					aria-label='Youtube' role='link' rel='noopener nofollow' title='Youtube de PrisMa'
	// 					href='https://www.youtube.com/channel/UCy5M8DYXgHm5IjI4MIUCXqg' target='_blank'>
	// 					<i class='fab fa-youtube peu'></i>
	// 				</a>
	// 			</div>
	// 			<div class='d-flex justify-content-center align-items-center mr-2'>
	// 				<a class='d-flex align-items-center justify-content-center'
	// 					aria-label='Twitter' role='link' rel='noopener nofollow' title='Twitter de PrisMa'
	// 					href='https://twitter.com/PrisMaFormacio' target='_blank'>
	// 					<i class='fab fa-twitter peu'></i>
	// 				</a>
	// 			</div>
	// 			<div class='d-flex justify-content-center align-items-center mr-2'>
	// 				<a class='d-flex align-items-center justify-content-center'
	// 					aria-label='Twitter' role='link' rel='noopener nofollow' title='Twitter de PrisMa'
	// 					href='https://www.facebook.com/PrisMaFormacio' target='_blank'>
	// 					<i class='fab fa-facebook-f peu'></i>
	// 				</a>
	// 			</div>
	// 			<div class='d-flex justify-content-center align-items-center mr-2'>
	// 				<a class='d-flex align-items-center justify-content-center'
	// 					aria-label='Tik Tok' role='link' rel='noopener nofollow' title='Tik Tok de PrisMa'
	// 					href='https://www.tiktok.com/@prisma.educacio' target='_blank'>
	// 					<i class='fab fa-tiktok peu'></i>
	// 				</a>
	// 			</div>
  // 		  </div>
	// 	</div>
	// </div>";
	//
  //  $mostrar .= "</div>";

   $mostrar .= "<div class='footer-info w-100 mt-2 px-3 pb-3 pt-2 text-center'>
				&copy; ".date("Y")." Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa |
				<a class='color-text' href='https://www.prisma.cat/avis-legal' aria-label='Avís legal' role='link' title='Avís legal' target='_blank' rel='noopener nofollow'>Av&iacute;s Legal</a> |
				<a class='color-text' href='https://www.prisma.cat/politica-privacitat' aria-label='Politica de privacitat' role='link' title='Politica de privacitat' target='_blank' rel='noopener nofollow'>Pol&iacute;tica de privacitat</a> |
				<a class='color-text' href='https://www.prisma.cat/cookies' aria-label='Cookies' role='link' title='Cookies' target='_blank' rel='noopener nofollow'>Cookies</a>
	</div>";
	$mostrar .= "</div></div>";

	$mostrar .= "<div class='modal fade in' id='modalError' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' aria-hidden='true'>";
   $mostrar .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>";
   $mostrar .= "<div class='modal-content w-100 border-0'><div class='modal-header border-0 color-white'>";
   $mostrar .= "<p class='modal-title modal-title-danger color-white float-left' id='modalErrorsTitle'>Errors</p>";
   $mostrar .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='color-white'>×</span></button></div>";
   $mostrar .= "<div class='modal-body' id='modalErrorBody'></div>";
   $mostrar .= "<div class='modal-footer justify-content-center text-center border-0'>";
   $mostrar .= "<a role='button' class='btn btn-danger font-weight-bold500' aria-label='Close' data-dismiss='modal'>Tanca</a>";
   $mostrar .= "</div></div></div></div>";

   $mostrar .= "<div class='modal fade in' id='modalOK' tabindex='-1' role='dialog' aria-labelledby='modalSuccessTitle' aria-hidden='true'>";
   $mostrar .= "<div class='modal-dialog modal-dialog-centered modal-notify modal-success justify-content-center text-center' role='document'>";
   $mostrar .= "<div class='modal-content w-100 border-0'><div class='modal-header border-0 color-white background-prisma'>";
   $mostrar .= "<p class='modal-title modal-title-success color-white float-left' id='modalSuccessTitle'>Sol·licitud enviada</p>";
   $mostrar .= "<button role='button' class='close' data-dismiss='modal' aria-label='Close'><span aria-hidden='true' class='color-white'>×</span></button></div>";
   $mostrar .= "<div class='modal-body' id='modalOkBody'></div>";
   $mostrar .= "<div class='modal-footer justify-content-center text-center border-0'>";
   $mostrar .= "<a role='button' class='btn boto-blau color-white font-weight-bold500' id='close-sucess' aria-label='Close' data-dismiss='modal'>Tanca</a>";
   $mostrar .= "</div></div></div></div>";

	$mostrar .= "
	<div id='cntCookies' class='cntCookies cookiesShow'>
		<p>Aquest lloc web utilitza cookies per millorar la vostra experiència. Si continueu navegant, considerarem que n'accepteu el seu ús. <a href='https://www.prisma.cat/cookies' target='_blank' rel='noopener nofollow' title='Més informació sobre les cookies del lloc'>Més informació</a></p>
		<button onclick='acceptCookies()'>Accepta</button>
	</div>";

	echo $mostrar;
}
catch(Exception $e) {
    echo $e->getMessage();
}
?>
