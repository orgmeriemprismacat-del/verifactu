<!DOCTYPE HTML PUBLIC "-/W3C/DTD HTML 4.01/EN" "http:/www.w3.org/TR/html4/strict.dtd">
<html lang="ca" prefix="og: http:/ogp.me/ns# fb: http:/ogp.me/ns/fb# video: http:/ogp.me/ns/video#">
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
   <?php include("./inc/buscarMetaTags.php");flush();?>
   <style>.url-no-mostrar{display:none}</style>
	 <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/estructura.min.css?ver=5.0' />
	 <!-- Bootstrap CSS -->
	<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"/>
	<!-- CSS General -->
	 <link rel='stylesheet' href='https://pay.prisma.cat/css/general.min.css?ver=1.0' />
	<!-- jQuery-->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
</head>
<body class="index">
   <header></header>

   <div id='cnt-pagament' class="prisma-container container separacio-peu" role="main">
      <div id='dni' style='display:none'><?php echo $_POST['dni']?></div>
      <div id='nom-titular' style='display:none'><?php echo $_POST['nom-titular']?></div>
      <div id='import' style='display:none'><?php echo $_POST['import']?></div>
      <div id='importPagat' style='display:none'><?php echo $_POST['importPagat']?></div>
      <div id='email' style='display:none'><?php echo $_POST['email']?></div>

      <?php
      include("./ConnexioBBDD_PreparedStatment.php");
      include("./inc/buscarPaginaStmt.php");
      include("./inc/missatgesError.php");
      include("./inc/apiRedsys.php");
      include("./Mail.php");

      $idPag = $_POST['idPag'];

      //Buscar el curs i el nom del titol del cursos
      $connexio = new ConnexioBBDDSTMT();
   	$connexio->connectarBD();

      $cns = "SELECT c.NOM_CURS, c.CURS, i.TIPUS_INSC FROM inscripcions as i INNER JOIN curs as c
       ON c.ANY = i.ANY AND c.MES = i.MES AND c.CURS = i.CURS WHERE IDPAG=? ORDER BY A_PAGAR DESC LIMIT 1";
		if ( $stmt=$connexio->prepare($cns) ) {
   		$stmt->bind_param("d", $idPag);
   		$stmt->execute();
   		$stmt->store_result();
   		if ( $stmt->num_rows() > 0 ) {
   			$stmt->bind_result($cursPag, $titolPag, $tipusInsc);
            $stmt->fetch();
         }
      }
      $connexio->closeStmt();
      $connexio->desconectarBD();

      $dniTitularPag = $_POST['dni'];
      $nomTitularPag = $_POST['nom-titular'];
      $email = $_POST['email'];
      $importAPagar = $_POST['import'];
      $importPagare = floatval($_POST['importPagare']);
      $importPagat = floatval($_POST['importPagat']);
      $frac = floatval($_POST['frac']);

      $titular=stripslashes($nomTitularPag);
      $alumn=stripslashes($nomAlumnePag);

      $miObj = new RedsysAPI;

      // Valores de entrada
      $fuc="11250743";
      $terminal="1";
		// $terminal="001";
      $moneda="978";
      $trans="0";
      $id=time();
      $order = strval($id);

      $url="https://www.prisma.cat/realitzaPagamentGrupAutomatic.php?idPag=".$idPag."&dni=".$dniTitularPag."&order=".$order."&import=".$importPagare."&tipusInsc=".$tipusInsc;
      $urlOK="https://www.prisma.cat/respostaOkPagamentAutomatic.php?email=".$email;
      $urlKO="https://www.prisma.cat/respostaKoPagamentAutomatic.php?email=".$email;

      if ( $tipusInsc == 'G' )
         $url="https://www.prisma.cat/realitzaPagamentGrupAutomatic.php?idPag=".$idPag."&dni=".$dniTitularPag."&order=".$order."&import=".$importPagare."&tipusInsc=".$tipusInsc;
      if ( $tipusInsc == 'P' )
         $url="https://www.prisma.cat/realitzaPagamentPackAutomatic.php?idPag=".$idPag."&dni=".$dniTitularPag."&order=".$order."&import=".$importPagare."&tipusInsc=".$tipusInsc."&frac=".$frac;

      // echo $url."<br />";

      $amount=$importPagare * 100;

      $name='Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa';

      $producto=$dniTitularPag." | ".stripslashes($titolPag);

      // Se Rellenan los campos
      $miObj->setParameter("DS_MERCHANT_AMOUNT",$amount);
      $miObj->setParameter("DS_MERCHANT_ORDER",$order);
      $miObj->setParameter("DS_MERCHANT_MERCHANTCODE",$fuc);
      $miObj->setParameter("DS_MERCHANT_CURRENCY",$moneda);
      $miObj->setParameter("DS_MERCHANT_PRODUCTDESCRIPTION",$producto);
      $miObj->setParameter("DS_MERCHANT_TITULAR",$dniTitularPag);
      $miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE",$trans);
      $miObj->setParameter("DS_MERCHANT_TERMINAL",$terminal);
      $miObj->setParameter("DS_MERCHANT_MERCHANTURL",$url);
      $miObj->setParameter("DS_MERCHANT_URLOK",$urlOK);
      $miObj->setParameter("DS_MERCHANT_URLKO",$urlKO);

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
         <div class='d-flex flex-column cnt_enviar_dades border-0 align-items-center w-100 mb-4'>
            <p><span class='font-weight-bold'>Titular de la targeta: </span><?php echo $nomTitularPag; ?></p>
            <p><span class='font-weight-bold'>DNI: </span><?php echo $dniTitularPag; ?></p>
            <?php
               if ($frac=='0') {
            ?>
               <p><span class='font-weight-bold'>Import a pagar: </span><?php echo $importAPagar; ?> euros</p>
            <?php
               }
            ?>
         </div>
         <form id='frm' name='frm' action='https://sis.redsys.es/sis/realizarPago' method='post'>
   		<!-- <form id='frm' name='frm' action='https://sis-t.redsys.es:25443/sis/realizarPago' method='post'> -->
   		   <input type="hidden" name="producto" value="<?php echo $producto; ?>"/>
            <input type="hidden" name="rebut" value="<?php echo $id; ?>"/>
            <input type="hidden" name="Ds_SignatureVersion" value="<?php echo $version; ?>"/>
            <input type="hidden" name="Ds_MerchantParameters" value="<?php echo $params; ?>"/>
            <input type="hidden" name="Ds_Signature" value="<?php echo $signature; ?>"/>
         </form>
         <div class='d-flex cnt_enviar_dades border-0 justify-content-center w-100'>
            <a id='form_cancelar_dades' role='button' class='boto-blau-disable
               position-relative border-0 border-radius-2 text-center
               w-100 mr-0 mr-md-2 px-4 py-2 my-2'>En un altre moment</a>
            <a id='form_enviar_dades' role='button' class='boto-blau
               position-relative border-0 border-radius-2 text-white text-center
               w-100 mr-0 mr-md-2 px-4 py-2 my-2'>Confirmo el pagament</a>
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
   <link rel="stylesheet" href="https://pay.prisma.cat/css/efectuarPagamentGrup.min.css?ver=5.0"/>
   <script async src="https://pay.prisma.cat/js/mostrarEfectuarPagamentGrup.min.js?ver=2.0"></script>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,400,500,700|Nunito+Sans&display=swap"/>
   <link rel="stylesheet" href="https://www.prisma.cat/css1619773569/404.min.css?ver=1.0"/>
	 <!-- Bootstrap JS -->
		<script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
		<!-- Fontawesome -->
		<script async src="https://kit.fontawesome.com/5b3303ad4a.js"></script>

</body>
</html>
