<?php

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/buscarPaginaStmt.php");
include("../inc/missatgesError.php");
include("../Text.php");

try {

	$mostrar = "<div class='prisma-footer-content color-footer'>";
	$mostrar .= "<div class='container'>";
	$mostrar .= "<div class='recon text-center w-100'>
		Cursos reconeguts com a formaci&oacute; permanent del professorat pel Departament d'Educaci&oacute; de la Generalitat de Catalunya
	</div>";

	$mostrar .= "<div class='cnt-footer d-flex flex-column flex-lg-row align-items-start px-3 w-100'>";
	$mostrar .= "<div class='d-flex flex-row col-12 col-lg-6 flex-wrap p-0'>
		<div class='col-12 col-sm-6 p-0'>
			<button class='accordion-footer seccio border-0 font-weight-bold'>Cursos en l&iacute;nia</button>
			<div class='panel border-0'>
				<ul>
					<li><a role='link' class='color-white' target='_self' title='Cursos en l&iacute;nea que ofereix PrisMa' href='https://www.prisma.cat/cursos'>Tots els cursos</a></li>
					<li><a role='link' class='color-white' target='_self' title='Regala un curs on-line de PrisMa' href='https://www.prisma.cat/regal'>Regala un curs</a></li>
					<li><a role='link' class='color-white' target='_self' title='Bescanvia un regal que t'hagin fet de PrisMa' href='https://www.prisma.cat/bescanvia-regal'>Bescanvia targeta regal</a></li>
					<li><a role='link' class='color-white' target='_self' title='Veure els tastets disponibles' href='https://www.prisma.cat/tastets'>Tastets gratuïts</a></li>
					<!--<li><a role='link' class='color-white' target='_self' title='Veure els cursos que només s'ofereixen a l'estiu' href='https://www.prisma.cat/cursos-exclusius-estiu'>Cursos que només s'ofereixen a l'estiu</a></li>-->
					<!--<li><a role='link' class='color-white' target='_self' title='Veure tots els packs de cursos' href='https://www.prisma.cat/packs'><em>Packs</em> de cursos</a></li>-->
                    <!--<li><a role='link' class='color-white' target='_self' title='Veure tots els cursos de juliol' href='https://www.prisma.cat/cursos/07/2026'>Juliol</a></li>
                    <li><a role='link' class='color-white' target='_self' title='Veure tots els cursos de l&#39;estiu' href='https://www.prisma.cat/cursos/08/2026'>Cursos d'estiu</a></li>-->
                    <li><a role='link' class='color-white' target='_self' title='Veure tots els packs de cursos' href='https://www.prisma.cat/packs'><em>Packs</em> de cursos</a></li>
				</ul>
			</div>
		</div>
		<div class='col-12 col-sm-6 p-0'>
			<button class='accordion-footer seccio border-0 font-weight-bold'>Nosaltres</button>
			<div class='panel border-0'>
				<ul>
					<li><a role='link' class='color-white' target='_self' title='Sobre l'equip, la tasca i els valors de PrisMa' href='https://www.prisma.cat/qui-som'>Qui som</a></li>
					<li><a role='link' class='color-white' target='_self' title= 'Els docents de PrisMa' href='https://www.prisma.cat/docents'>Docents</a></li>
					<li><a role='link' class='color-white' target='_self' title= 'Els autors de PrisMa' href='https://www.prisma.cat/autors'>Autors</a></li>
					<li><a role='link' class='color-white' target='_self' title='Funcionament dels cursos on-line de PrisMa' href='https://www.prisma.cat/metodologia'>Metodologia</a></li>
					<li><a role='link' class='color-white' target='_self' title='Preguntes freq&uuml;ents de PrisMa' href='https://www.prisma.cat/preguntes-frequents'>Preguntes freq&uuml;ents</a></li>
				</ul>
			</div>
		</div>
		<div class='col-12 col-sm-6 p-0'>
			<button class='accordion-footer seccio border-0 font-weight-bold'>Informaci&oacute;</button>
			<div class='panel border-0'>
				<ul>
					<li><a role='link' class='color-white' target='_self' title='Veure la informaci&oacute; dels reconeixements i certificació dels cursos de PrisMa' href='https://www.prisma.cat/reconeixements-certificacio'>Reconeixements i certificaci&oacute;</a></li>
					<li><a role='link' class='color-white' target='_self' title='Cursos de PrisMa amb  perfils professionals' href='https://www.prisma.cat/perfils-professionals'>Perfils professionals</a></li>
					<li><a role='link' class='color-white' target='_self' title='Veure el llistat de cursos de PrisMa reconeguts com a FISS' href='https://www.prisma.cat/activitats-formacio-interes-serveis-socials'>Formaci&oacute; inter&egrave;s serveis socials</a></li>
					<li><a role='link' class='color-white' target='_self' title='Veure la informació dels nomenaments de juliol' href='https://www.prisma.cat/nomenaments-juliol'>Nomenaments de juliol</a></li>
				</ul>
			</div>
		</div>
		<div id='altres' class='col-12 col-sm-6 p-0'>
			<button class='accordion-footer seccio border-0 font-weight-bold'>Altres serveis</button>
			<div class='panel border-0'>
				<ul>
					<li><a role='link' class='color-white' target='_self' title='Veure els descomptes disponibles' href='https://www.prisma.cat/descomptes'>Descomptes</a></li>
                    <li><a role='link' class='color-white' target='_self' title='Veure la informació de la formació 100 % bonificada' href='https://www.prisma.cat/formacio-bonificada'>Bonificació de cursos</a></li>
					<!--<li><a role='link' rel='noopener' class='color-white' target='_blank' title='Botiga on-line de llibres i revistes relacionades amb l'educació' href='https://shop.prisma.cat/ca/'>Botiga <em>on-line</em></a></li>-->
					<li><a role='link' rel='noopener' class='color-white' target='_blank' title='Educat. Blog de Psicopedagogia' href='https://www.educat.cat/'>Educat. Blog de Psicopedagogia</a></li>
					<li><a role='link' rel='noopener' class='color-white' target='_blank' title='Formació i assessorament dels nostres col·laboradors' href='https://www.prisma.cat/formacio-colaboradors'>Formació i assessorament col·laboradors</a></li>
				</ul>
			</div>
		</div>
	</div>";

	$calendari = "";

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cns = "SELECT DATA, DESCR FROM events WHERE ESTAT = 1 AND DATAF >= CURRENT_TIME ORDER BY DATA LIMIT 3";
	$stmt=$connexio->prepare($cns);
	$stmt->execute();
	$stmt->bind_result($dataEv, $descrEv);
	while ( $stmt->fetch() ) {

		$dateTimeEv = new DateTime($dataEv);

		$day = $dateTimeEv->format('j');

		$month = $dateTimeEv->format('m');
		$objMonth = new Text($month);
		$monthMaj = new Text( $objMonth->obtenirMesLlarg() );
		$month = substr($monthMaj->convertirMajPrimParaula(), 0, 3);

		$year = $dateTimeEv->format('Y');

		$calendari .= "<div class='calendari d-flex flex-row align-items-center'>
			<div class='col-6 d-flex flex-column data align-items-center'>
				<span>".$day."</span>
				<span>".$month.", ".$year."</span>
			</div>
			<div class='col-6 d-flex flex-column p-0'>
				<div class='color-footer'>".$descrEv."</div>
			</div>
		</div>";
	}
	$connexio->closeStmt();

	$connexio->desconectarBD();

	$mostrar .= "<div class='d-flex flex-row col-12 col-lg-6 flex-wrap p-0'>
		<div id='calendari' class='peu-calendari col-12 col-sm-6 p-0'>
			<div class='index-events'>
				<p class='seccio titol font-weight-bold'>Esdeveniments</p>
				".$calendari."
         </div>
      </div>
		<div class='peu-contacte col-12 col-sm-6 p-0'>
			<p class='seccio titol font-weight-bold'>BUTLLET&Iacute; ELECTR&Ograve;NIC</p>
	      <div class='form-footer d-flex align-items-start w-100 ' role='search'>
				<label class='position-absolute' data-error='wrong' data-success='right' for='adreca-electronica'>Adre&ccedil;a electr&ograve;nica...</label>
			 	<input id='adreca-electronica' name='adreca-electronica' type='text' class='form-control' title=\"Introdueix l'adre&ccedil;a electr&ograve;nica\">
	         <button aria-label='Newsletter' class='news border-0 align-self-end' id='news'><i class='fa fa-envelope'></i></button>
				<label for='butlletiSpam' class='comprovaSpam d-none'>Si veus això, no omplis el camp!</label>
				<input id='butlletiSpam' name='comprovaSpam' class='comprovaSpam d-none' value=''>
	       </div>
			 <p class='seccio titol associacio font-weight-bold'>Associació PrisMa</p>
			 <div class='d-flex flex-column adreca'>	 			
	 			<div>
	 			   <i class='fa fa-phone'></i>972 21 75 65
	 			</div>
	 			<div>
	 				<i class='fa fa-mobile-phone'></i>678 123 687
	 			</div>
				<div>
	 				<i class='fab fa-whatsapp'></i><a class='whats' href='https://wa.me/34678123687' target='_blank'>678 123 687</a>
	 			</div>
	 		 </div>

  		  <div class='xarxes d-flex flex-row flex-wrap justify-content-start mt-3'>
		  	  <div class='d-flex justify-content-center align-items-center mr-0'>
				  <a class='d-flex align-items-center justify-content-center'
					  aria-label='Instagram' role='link' rel='noopener nofollow' title='Instagram de PrisMa'
					  href='https://www.instagram.com/prisma.educacio/' target='_blank'>
					  <i class='fab fa-instagram peu'></i>
				  </a>
			  </div>
				<div class='no-xarxes d-flex justify-content-center align-items-center mr-2'>
					<!--<span id='insta-nou' class='position-relative text-center text-white ml-1'>NOU</span>-->
				</div>
				<div class='d-flex justify-content-center align-items-center mr-2'>
					<a class='d-flex align-items-center justify-content-center'
						aria-label='Youtube' role='link' rel='noopener nofollow' title='Youtube de PrisMa'
						href='https://www.youtube.com/channel/UCy5M8DYXgHm5IjI4MIUCXqg' target='_blank'>
						<i class='fab fa-youtube peu'></i>
					</a>
				</div>
				<div class='d-flex justify-content-center align-items-center mr-2'>
					<a class='d-flex align-items-center justify-content-center'
						aria-label='Twitter' role='link' rel='noopener nofollow' title='Twitter de PrisMa'
						href='https://twitter.com/PrisMaFormacio' target='_blank'>
						<i class='fab fa-twitter peu'></i>
					</a>
				</div>
				<div class='d-flex justify-content-center align-items-center mr-2'>
					<a class='d-flex align-items-center justify-content-center'
						aria-label='Twitter' role='link' rel='noopener nofollow' title='Twitter de PrisMa'
						href='https://www.facebook.com/PrisMaFormacio' target='_blank'>
						<i class='fab fa-facebook-f peu'></i>
					</a>
				</div>
				<div class='d-flex justify-content-center align-items-center mr-2'>
					<a class='d-flex align-items-center justify-content-center'
						aria-label='Tik Tok' role='link' rel='noopener nofollow' title='Tik Tok de PrisMa'
						href='https://www.tiktok.com/@prisma.educacio' target='_blank'>
						<i class='fab fa-tiktok peu'></i>
					</a>
				</div>
  		  </div>
		  <div class='mt-3'>	
			<img class='w-100' title='Departament d&#39;Educació i Formació Professional de la Generalitat de Catalunya ' src='https://identitatcorporativa.gencat.cat/web/.content/Documentacio/descarregues/dpt/BN/Educacio/educacio_bn_h2.png' style='max-width: 199px; margin-left: 5px;'>
		  </div>
		</div>
	</div>";

   $mostrar .= "</div>";

   $mostrar .= "<div class='footer-info w-100 mt-2 px-3 pb-3 pt-2 text-center'>
				&copy; ".date("Y")." Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa |
				<a class='color-text' href='https://www.prisma.cat/comptes-anuals/2024.pdf' aria-label='Comptes anuals' role='link' title='Comptes anuals' target='_blank' rel='noopener nofollow'>Comptes anuals</a> |
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
