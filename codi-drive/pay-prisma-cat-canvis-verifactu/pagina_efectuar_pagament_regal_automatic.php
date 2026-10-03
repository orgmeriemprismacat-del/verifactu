<!DOCTYPE HTML PUBLIC "-/W3C/DTD HTML 4.01/EN" "http:/www.w3.org/TR/html4/strict.dtd">
<html lang="ca" prefix="og: http:/ogp.me/ns# fb: http:/ogp.me/ns/fb# video: http:/ogp.me/ns/video#">
<head>
	<script async src="https://www.googletagmanager.com/gtag/js?id=G-DD77YFRVS0"></script>
	<script>
	  window.dataLayer = window.dataLayer || [];
	  function gtag(){dataLayer.push(arguments);}
	  gtag('js', new Date());
	  gtag('config', 'G-DD77YFRVS0');
     gtag('config', 'AW-10936157157');
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
   <script>
      let pathNom = "/json/efectuar_pagament_regal.jsonld";
      $.getJSON(pathNom,function(data){$("<script/>",{"type":"application/ld+json",html:JSON.stringify(data)}).appendTo("head");});
   </script>
</head>
<body class="index">
   <header></header>
   <?php include('inc/analitics.html'); ?>
   <div id='cnt-pagament' class="prisma-container container separacio-peu" role="main">
		<div id='codiCurs' style='display:none'><?php echo $_POST['codiCurs']?></div>
		<div id='codiRegal' style='display:none'><?php echo $_POST['codiRegal']?></div>
		<div id='titol' style='display:none'><?php echo $_POST['titol']?></div>
      <div id='nom-titular' style='display:none'><?php echo $_POST['nom-titular']?></div>
		<div id='dni' style='display:none'><?php echo $_POST['dni']?></div>
		<div id='import' style='display:none'><?php echo htmlspecialchars($importPag ?? '', ENT_QUOTES, 'UTF-8')?></div>

      <?php

      include("./ConnexioBBDD_PreparedStatment.php");
      include("./inc/buscarPaginaStmt.php");
      include("./inc/missatgesError.php");
      include("./inc/apiRedsys.php");
      include("./Mail.php");

      $codiCurs = trim((string) ($_POST['codiCurs'] ?? ''));
      $codiRegal = trim((string) ($_POST['codiRegal'] ?? ''));
      $titolPag = trim((string) ($_POST['titol'] ?? ''));
      $nomTitularPag = trim((string) ($_POST['nom-titular'] ?? ''));
      $dniTitularPag = trim((string) ($_POST['dni'] ?? ''));

      $miObj = new RedsysAPI;

      // UC-017: DS_ORDER, gift ID i import provenen del snapshot autoritatiu SIF.
      // El navegador no és font d'autoritat econòmica.
      require_once __DIR__ . '/SifRedsysGiftIntentClient.php';

      $fuc = trim((string) getenv('REDSYS_MERCHANT_CODE'));
      if ($fuc === '') {
         throw new RuntimeException('REDSYS_MERCHANT_CODE_NOT_CONFIGURED');
      }
      $terminal = trim((string) getenv('REDSYS_TERMINAL'));
      if ($terminal === '') {
         throw new RuntimeException('REDSYS_TERMINAL_NOT_CONFIGURED');
      }
      $moneda = "978";
      $trans = "0";

      try {
         $intent = (new SifRedsysGiftIntentClient())->create($codiRegal, $terminal);
      } catch (Throwable $exception) {
         http_response_code(503);
         exit('No podem preparar el pagament del regal en aquest moment. Torna-ho a provar més tard o contacta amb secretaria.');
      }

      $order = (string) $intent['ds_order'];
      $giftId = (int) $intent['gift_id'];
      $importPag = (string) $intent['amount'];
      if (!preg_match('/^\d{1,10}\.\d{2}$/D', $importPag)) {
         throw new RuntimeException('INVALID_SIF_GIFT_AMOUNT');
      }
      [$amountEuros, $amountDecimals] = explode('.', $importPag, 2);
      $amount = ((int) $amountEuros * 100) + (int) $amountDecimals;
      $id = $order;

      $legacyMerchantUrl = "https://pay.prisma.cat/regal/doit.php";
      $giftCutoverEnabled = filter_var(
         getenv('SIF_REDSYS_GIFT_CUTOVER_ENABLED') ?: '0',
         FILTER_VALIDATE_BOOLEAN
      );
      $legacyDrainConfirmed = filter_var(
         getenv('SIF_REDSYS_GIFT_LEGACY_DRAIN_CONFIRMED') ?: '0',
         FILTER_VALIDATE_BOOLEAN
      );
      $sifMerchantUrl = trim((string) getenv('SIF_REDSYS_CALLBACK_URL'));
      if ($giftCutoverEnabled && !$legacyDrainConfirmed) {
         throw new RuntimeException('SIF_REDSYS_GIFT_LEGACY_DRAIN_NOT_CONFIRMED');
      }
      if ($giftCutoverEnabled) {
         if ($sifMerchantUrl === '') {
            throw new RuntimeException('SIF_REDSYS_CALLBACK_URL_REQUIRED_FOR_CUTOVER');
         }
         if (!str_starts_with($sifMerchantUrl, 'https://')) {
            throw new RuntimeException('SIF_REDSYS_CALLBACK_URL_MUST_USE_HTTPS');
         }
         $urlPag = $sifMerchantUrl;
      } else {
         $urlPag = $legacyMerchantUrl;
      }

      $returnQuery = http_build_query(['order' => $order], '', '&', PHP_QUERY_RFC3986);
      $urlOK = "https://pay.prisma.cat/respostaOkPagamentRegal.php?" . $returnQuery;
      $urlKO = "https://pay.prisma.cat/respostaKoPagamentRegal.php?" . $returnQuery;

      // MerchantData està signat per Redsys i només serveix al fallback llegat.
      $merchantData = 'UC017G' . $giftId . 'A' . $amount;

      $name = 'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa';
      $producto = $codiCurs . " | regal";

      $miObj->setParameter("DS_MERCHANT_AMOUNT", $amount);
      $miObj->setParameter("DS_MERCHANT_ORDER", $order);
      $miObj->setParameter("DS_MERCHANT_MERCHANTDATA", $merchantData);
      $miObj->setParameter("DS_MERCHANT_MERCHANTCODE", $fuc);
      $miObj->setParameter("DS_MERCHANT_CURRENCY", $moneda);
      $miObj->setParameter("DS_MERCHANT_PRODUCTDESCRIPTION", $producto);
      $miObj->setParameter("DS_MERCHANT_TITULAR", $nomTitularPag);
      $miObj->setParameter("DS_MERCHANT_TRANSACTIONTYPE", $trans);
      $miObj->setParameter("DS_MERCHANT_TERMINAL", $terminal);
      $miObj->setParameter("DS_MERCHANT_MERCHANTURL", $urlPag);
      $miObj->setParameter("DS_MERCHANT_URLOK", $urlOK);
      $miObj->setParameter("DS_MERCHANT_URLKO", $urlKO);

      $gatewayUrl = trim((string) getenv('REDSYS_GATEWAY_URL'));
      if ($gatewayUrl === '') {
         throw new RuntimeException('REDSYS_GATEWAY_URL_NOT_CONFIGURED');
      }
      if (!str_starts_with($gatewayUrl, 'https://')) {
         throw new RuntimeException('REDSYS_GATEWAY_URL_MUST_USE_HTTPS');
      }

      $version = "HMAC_SHA512_V2";
      $kc = trim((string) getenv('REDSYS_MERCHANT_KEY'));
      if ($kc === '') {
         throw new RuntimeException('REDSYS_MERCHANT_KEY_NOT_CONFIGURED');
      }

      $request = "";
      $params = $miObj->createMerchantParametersV2();
      $signature = $miObj->createMerchantSignatureV2($kc);

      ?>
      <h1>Pagament amb targeta</h1>
      <div class='d-flex flex-column tota-pagina'><div class='container'><div class='row'>
         <div class='d-flex flex-column cnt_enviar_dades border-0 align-items-center w-100 mb-4'>
            <p><span class='font-weight-bold'>Titular de la targeta: </span><?php echo $nomTitularPag; ?></p>
            <p><span class='font-weight-bold'>DNI: </span><?php echo $dniTitularPag; ?></p>
            <p><span class='font-weight-bold'>Import a pagar: </span><?php echo $importPag; ?> euros</p>
         </div>
         <form id='frm' name='frm' action='<?php echo htmlspecialchars($gatewayUrl, ENT_QUOTES, 'UTF-8'); ?>' method='post'>
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
   <link rel='stylesheet' href='https://pay.prisma.cat/css/efectuarPagamentRegal.min.css?ver=5.0' />
   <script async src="https://pay.prisma.cat/js/mostrarEfectuarPagamentRegal.min.js?ver=5.0"></script>
   <script async src="https://www.prisma.cat/js1619773569/obrirTancar.min.js?ver=5.0"></script>
   <script async src="https://www.prisma.cat/js1619773569/lazysizes.min.js?ver=5.0"></script>
	 <!-- Bootstrap JS -->
		<script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
		<!-- Fontawesome -->
		<script async src="https://kit.fontawesome.com/5b3303ad4a.js"></script>

</body>
</html>
