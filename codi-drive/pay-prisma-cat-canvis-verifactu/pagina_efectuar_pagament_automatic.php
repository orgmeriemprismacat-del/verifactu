<?php
// UC-111: authoritative payment gate BEFORE rendering or building Redsys data.
// This legacy bridge reads the enrollment and secretary decision, never the
// course/amount/approval from the POST form as its source of truth.
require_once __DIR__ . '/ConnexioBBDD_PreparedStatment.php';
require_once __DIR__ . '/inc/JasomNovicePaymentGate.php';
try {
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        throw new RuntimeException('PAYMENT_NOT_AVAILABLE');
    }
    $paymentGateDb = new ConnexioBBDDSTMT();
    $paymentGateDb->connectarBD();
    try {
        $validatedCheckout = JasomNovicePaymentGate::assertCanPrepare($paymentGateDb->connexio, $_POST);
    } finally {
        $paymentGateDb->desconectarBD();
    }
} catch (Throwable $exception) {
    http_response_code(409);
    header('Content-Type: text/plain; charset=utf-8');
    // Do not leak whether the participant's academic documents were approved.
    exit('Aquest pagament no està disponible. Torna a la inscripció o contacta amb secretaria.');
}
?>
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
	 <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
	<!-- CSS General -->
	 <link rel='stylesheet' href='https://pay.prisma.cat/css/general.min.css?ver=1.0' />
	<!-- jQuery-->
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>

</head>
<body class="index">
   <header></header>

   <div id='cnt-pagament' class="prisma-container container separacio-peu" role="main">
      <div id='codiCurs' style='display:none'><?php echo htmlspecialchars($validatedCheckout['course_code'], ENT_QUOTES, 'UTF-8'); ?></div>
      <div id='titol' style='display:none'><?php echo $_POST['titol']?></div>
      <div id='dni' style='display:none'><?php echo $_POST['dni']?></div>
      <div id='nom-titular' style='display:none'><?php echo $_POST['nom-titular']?></div>
      <div id='nom-alumne' style='display:none'><?php echo $_POST['nom-alumne']?></div>
      <div id='import' style='display:none'><?php echo htmlspecialchars($validatedCheckout['total_amount'], ENT_QUOTES, 'UTF-8'); ?></div>
      <div id='importPagat' style='display:none'><?php echo htmlspecialchars($validatedCheckout['already_paid_amount'], ENT_QUOTES, 'UTF-8'); ?></div>
      <div id='frac' style='display:none'><?php echo $_POST['frac']?></div>
      <div id='email' style='display:none'><?php echo $_POST['email']?></div>

      <?php
      include_once("./ConnexioBBDD_PreparedStatment.php");
      include("./inc/buscarPaginaStmt.php");
      include("./inc/missatgesError.php");
      include("./inc/apiRedsys.php");
      include("./Mail.php");

      $idPag = $validatedCheckout['idpag'];
      $cursPag = $validatedCheckout['course_code'];
      $titolPag = $_POST['titol'];
      $dniTitularPag = trim($_POST['dni']);//
      $nomTitularPag = $_POST['nom-titular'];
      $email = $_POST['email'];
      $importAPagar = (float) $validatedCheckout['total_amount'];
      $importPagare = (float) $validatedCheckout['payment_amount'];
      $importPagat = (float) $validatedCheckout['already_paid_amount'];
      $frac = $_POST['frac'];

      $titular=stripslashes($nomTitularPag);
      $alumn=stripslashes($nomAlumnePag);

      $miObj = new RedsysAPI;

      // UC-014: l'import i el DS_ORDER deixen de ser autoritat del navegador.
      // El SIF rellegeix la inscripció a la BD llegada i crea/reutilitza la intenció.
      require_once __DIR__ . '/SifRedsysCourseIntentClient.php';

      $fuc = trim((string) getenv('REDSYS_MERCHANT_CODE'));
      if ($fuc === '') {
         throw new RuntimeException('REDSYS_MERCHANT_CODE_NOT_CONFIGURED');
      }
      $terminal = trim((string) (getenv('REDSYS_TERMINAL') ?: '1'));
      $moneda="978";
      $trans="0";

      try {
         $intent = (new SifRedsysCourseIntentClient())->create(
            (int) $idPag,
            (float) $importPagare,
            $terminal
         );
      } catch (Throwable $exception) {
         http_response_code(503);
         exit('No podem preparar el pagament en aquest moment. Torna-ho a provar més tard o contacta amb secretaria.');
      }
      $order = (string) $intent['ds_order'];
      $importPagare = (float) $intent['amount'];
      $id = $order;

      $legacyMerchantUrl="https://pay.prisma.cat/doit.php?idPag=".rawurlencode((string) $idPag)
         ."&codiCurs=".rawurlencode((string) $cursPag)
         ."&dni=".rawurlencode((string) $dniTitularPag)
         ."&order=".rawurlencode((string) $order)
         ."&frac=".rawurlencode((string) $frac)
         ."&import=".rawurlencode(number_format((float) $importPagare, 2, '.', ''));

      // UC-014: el tall de MerchantURL és explícit. Configurar una URL SIF
      // per si sola no canvia el callback; cal habilitar també el flag de cutover.
      $courseCutoverEnabled = filter_var(
         getenv('SIF_REDSYS_COURSE_CUTOVER_ENABLED') ?: '0',
         FILTER_VALIDATE_BOOLEAN
      );
      $sifMerchantUrl = trim((string) getenv('SIF_REDSYS_CALLBACK_URL'));
      if ($courseCutoverEnabled) {
         if ($sifMerchantUrl === '') {
            throw new RuntimeException('SIF_REDSYS_CALLBACK_URL_REQUIRED_FOR_CUTOVER');
         }
         if (!str_starts_with($sifMerchantUrl, 'https://')) {
            throw new RuntimeException('SIF_REDSYS_CALLBACK_URL_MUST_USE_HTTPS');
         }
         $url = $sifMerchantUrl;
      } else {
         $url = $legacyMerchantUrl;
      }

      $returnQuery = http_build_query([
         'email' => $email,
         'order' => $order,
         'idPag' => (int) $idPag,
      ], '', '&', PHP_QUERY_RFC3986);
      $urlOK="https://pay.prisma.cat/respostaOkPagamentAutomatic.php?".$returnQuery;
      $urlKO="https://pay.prisma.cat/respostaKoPagamentAutomatic.php?".$returnQuery;

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

      // Datos de configuració: cap secret Redsys queda al codi.
      $version="HMAC_SHA256_V1";
      $kc = trim((string) getenv('REDSYS_MERCHANT_KEY'));
      if ($kc === '') {
         throw new RuntimeException('REDSYS_MERCHANT_KEY_NOT_CONFIGURED');
      }

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
               else {
            ?>
               <p><span class='font-weight-bold'>Preu del curs: </span><?php echo $importAPagar; ?> euros</p>
               <p><span class='font-weight-bold'>Import pagat: </span><?php echo $importPagat; ?> euros</p>
               <p><span class='font-weight-bold'>Import a pagar ara: </span><?php echo $importPagare; ?> euros</p>
            <?php
               }
            ?>
         </div>
         <!-- <form id='frm' name='frm' action='https://sis.redsys.es/sis/realizarPago' method='post'> -->
   			<form id='frm' name='frm' action='https://sis-t.redsys.es:25443/sis/realizarPago' method='post'>

					<!-- cal afegir tots els camps per confirmar les dades de facturació -->
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
   <script async src="https://pay.prisma.cat/js/mostrarEfectuarPagament.js?ver=2.0"></script>
   <link rel="stylesheet" href="https://pay.prisma.cat/css/efectuarPagament.min.css?ver=5.0"/>
   <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,400,500,700|Nunito+Sans&display=swap"/>
   <link rel="stylesheet" href="https://www.prisma.cat/css1619773569/404.min.css?ver=1.0"/>
	 <!-- Bootstrap JS -->
	 <script async src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
		<!-- Fontawesome -->
		<script async src="https://kit.fontawesome.com/efc6febcf3.js" crossorigin="anonymous"></script>
 </body>
</html>
