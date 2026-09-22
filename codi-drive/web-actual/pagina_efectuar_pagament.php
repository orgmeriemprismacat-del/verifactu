<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ca" prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# video: http://ogp.me/ns/video#">
	<head>
		<script>
  window.dataLayer = window.dataLayer || [];
  function gtag(){dataLayer.push(arguments);}
  if ( !localStorage.getItem("acceptCookies") ) {
    console.log('consent default');
    gtag('consent', 'default', {
      'ad_storage': 'denied',
      'ad_user_data': 'denied',
      'ad_personalization': 'denied',
      'analytics_storage': 'denied'
    });
  }
	else {
		console.log('consent');
		gtag('consent', 'default', {
      'ad_storage': 'granted',
      'ad_user_data': 'granted',
      'ad_personalization': 'granted',
      'analytics_storage': 'granted'
    });
	}
</script>
<script async src="https://www.googletagmanager.com/gtag/js?id=G-DD77YFRVS0"></script>
<script>
  gtag('js', new Date());
  gtag('config', 'G-DD77YFRVS0');gtag('config', 'AW-10936157157');

  function consentGrantedAdStorage() {
    console.log('consentGrantedAdStorage');
    gtag('consent', 'update', {
      'ad_storage': 'granted'
    });
  }
  function consentGrantedAdUserData() {
    console.log('consentGrantedAdUserData');
    gtag('consent', 'update', {
      'ad_user_data': 'granted'
    });
  }
  function consentGrantedAdPersonalization() {
    console.log('consentGrantedAdPersonalization');
    gtag('consent', 'update', {
      'ad_personalization': 'granted'
    });
  }
  function consentGrantedAnalyticsStorage() {
    console.log('consentGrantedAnalyticsStorage');
    gtag('consent', 'update', {
      'analytics_storage': 'granted'
    });
  }
</script>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php include("./inc/buscarMetaTags.php");flush(); ?>
		<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/estructura.min.css?ver=5.0' />
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.4.1/jquery.min.js"></script>
	</head>
	<body class="index">
		<header></header>

		<div id='cnt-pagament' class="prisma-container container separacio-peu" role="main">
			<div id='codiCurs' style='display:none'><?php echo $_POST['codiCurs']?></div>
			<div id='titol' style='display:none'><?php echo $_POST['titol']?></div>
			<div id='dni' style='display:none'><?php echo $_POST['dni']?></div>
			<div id='nom-titular' style='display:none'><?php echo $_POST['nom-titular']?></div>
			<div id='nom-alumne' style='display:none'><?php echo $_POST['nom-alumne']?></div>
			<div id='import' style='display:none'><?php echo $_POST['import']?></div>
			<div id='importPagat' style='display:none'><?php echo $_POST['importPagat']?></div>
			<div id='frac' style='display:none'><?php echo $_POST['frac']?></div>
			<div id='email' style='display:none'><?php echo $_POST['email']?></div>
			<?php
				include("./ConnexioBBDD_PreparedStatment.php");
				include("./inc/buscarPaginaStmt.php");
				include("./inc/missatgesError.php");
				include("./inc/apiRedsys.php");
				include("./Mail.php");

				$cursPag = $_POST['codiCurs'];
				$titolPag = $_POST['titol'];
				$dniTitularPag = $_POST['dni'];//
				$nomTitularPag = $_POST['nom-titular'];
				$nomAlumnePag = $_POST['nom-alumne'];
				$importPag = $_POST['import'];
				$importPagat = floatval($_POST['importPagat']);
				$frac = $_POST['frac'];

				$miObj = new RedsysAPI;

				// Valores de entrada
				$fuc="11250743";
				$terminal="1";
				$moneda="978";
				$trans="0";
				$url="https://www.prisma.cat/";
				$urlOKKO="";
				$id=time();
				$amount=$importPag * 100;
				if ($importPagat!=0) $amount=$importPagat * 100;

				// Valors afegits
				$name='Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa';
				$producto=$cursPag." | ".stripslashes($titolPag);
				$titular=$dniTitularPag;
				$tit=stripslashes($nomTitularPag);
				$alumn=stripslashes($nomAlumnePag);

				// Se Rellenan los campos
				$miObj->setParameter("DS_MERCHANT_AMOUNT",$amount);
				$miObj->setParameter("DS_MERCHANT_ORDER",strval($id));
				$miObj->setParameter("DS_MERCHANT_MERCHANTCODE",$fuc);
				$miObj->setParameter("DS_MERCHANT_CURRENCY",$moneda);
				$miObj->setParameter("DS_MERCHANT_PRODUCTDESCRIPTION",$producto);
				$miObj->setParameter("DS_MERCHANT_TITULAR",$titular);
				$miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE",$trans);
				$miObj->setParameter("DS_MERCHANT_TERMINAL",$terminal);
				$miObj->setParameter("DS_MERCHANT_MERCHANTURL",$url);
				$miObj->setParameter("DS_MERCHANT_URLOK",$urlOKKO);
				$miObj->setParameter("DS_MERCHANT_URLKO",$urlOKKO);

				//Datos de configuración
				$version="HMAC_SHA256_V1";
				$kc = 'N5LhVEkBj0Btcimodf7F+6Pj6ZJTydPb';//Clave recuperada de CANALES
				// $kc = 'sq7HjrUOBfKmC576ILgskD5srU870gJ7';//Clave recuperada de CANALES prova

				// Se generan los parámetros de la petición
				$request = "";
				$params = $miObj->createMerchantParameters();
				$signature = $miObj->createMerchantSignature($kc);
			?>
			<h1>Pagament amb targeta</h1>
			<div class='d-flex flex-column tota-pagina'><div class='container'><div class='row'>
				<div class='d-flex flex-column cnt_enviar_dades sense-border align-items-center w-100 mb-4'>
					<p><span class='font-weight-bold'>Titular de la targeta: </span><?php echo $nomTitularPag; ?></p>
					<p><span class='font-weight-bold'>Alumne/a: </span><?php echo $nomAlumnePag; ?></p>
					<p><span class='font-weight-bold'>DNI: </span><?php echo $dniTitularPag; ?></p>
					<?php
						if ($frac=='0') {
					?>
						<p><span class='font-weight-bold'>Import a pagar: </span><?php echo $importPag; ?> euros</p>
					<?php
						}
						else {
					?>
						<p><span class='font-weight-bold'>Preu del curs: </span><?php echo $importPag; ?> euros</p>
						<p><span class='font-weight-bold'>Import a pagar ara: </span><?php echo $importPagat; ?> euros</p>
					<?php
						}
					?>
				</div>
				<form id='frm' name='frm' action='https://sis.redsys.es/sis/realizarPago' method='post'>
				<!-- <form id='frm' name='frm' action='https://sis-t.redsys.es:25443/sis/realizarPago' method='post'> -->
					<input type="hidden" name="producto" value="<?php echo $producto; ?>"/>
					<input type="hidden" name="rebut" value="<?php echo $id; ?>"/>
					<input type="hidden" name="Ds_SignatureVersion" value="<?php echo $version; ?>"/>
					<input type="hidden" style="height:50px; width:2000px" name="Ds_MerchantParameters" value="<?php echo $params; ?>"/>
					<input type="hidden" name="Ds_Signature" style="height:50px; width:500px" value="<?php echo $signature; ?>"/>
				</form>
				<div class='d-flex cnt_enviar_dades sense-border justify-content-center w-100'>
					<a id='form_cancelar_dades' role='button' class='boto-blau-disable
					border-0 border-radius-2 text-center negreta500 m-0 mr-3'>En un altre moment</a>
					<a id='form_enviar_dades' role='button' class='boto-blau
					border-0 border-radius-2 text-center negreta500 color-white m-0'>Confirmo el pagament</a>
				</div>
			</div></div></div>
		</div>
		<footer class="prisma-footer"></footer>
		<script async>
		var headerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.2' />";
		if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
			headerCSS += "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header_mbl.min.css?ver=5.0' />";
		}
		else {
			headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px)' href='https://www.prisma.cat/css1619773569/header_wind.min.css?ver=5.0' />";

			headerCSS += "<link rel='stylesheet' media='screen and (max-width: 768px)' href='https://www.prisma.cat/css1619773569/header_mbl.min.css?ver=5.0' />";
		}
		var footerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/footer.min.css?ver=6.0' />";
		$('head').append(headerCSS);
		$('head').append(footerCSS);
		</script>
		<script async src="https://www.prisma.cat/js1619773569/mostrarEfectuarPagament.min.js?ver=7.0"></script>
		<script async src="https://www.prisma.cat/js1619773569/lazysizes.min.js"></script>
		<script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
	</body>
</html>
