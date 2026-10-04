<?php
header('Cache-Control: private, no-store, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header("Content-Security-Policy: frame-ancestors 'none'");
header('X-Frame-Options: DENY');
header('X-Robots-Tag: noindex, nofollow, noarchive');

$uc015ConfirmationCookie = 'uc015_pack_confirmation';
$uc015ConfirmationCookiePath = '/ajax/mostrar_pagina_confirmacio_pagament_grup_automatic.php';

$setUc015ConfirmationCookie = static function ($token) use (
    $uc015ConfirmationCookie,
    $uc015ConfirmationCookiePath
) {
    setcookie($uc015ConfirmationCookie, $token, [
        'expires' => time() + 86400,
        'path' => $uc015ConfirmationCookiePath,
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict',
    ]);
};

if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? '')) === 'POST') {
    $postedToken = trim((string) ($_POST['confirmationToken'] ?? ''));
    if (strlen($postedToken) <= 2048
        && preg_match('/^v2\.[A-Za-z0-9_-]+$/D', $postedToken) === 1
    ) {
        $setUc015ConfirmationCookie($postedToken);
    }
    else {
        http_response_code(400);
    }
}
else {
    // Compatibilitat temporal: un client amb JS 7.6 pot haver enviat el token v2
    // al path. Es captura abans de carregar scripts i es redirigeix a la URL neta.
    $requestPath = parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);
    if (is_string($requestPath)
        && preg_match(
            '#^/packs/confirmacio/(v2\.[A-Za-z0-9_-]+)/?$#D',
            $requestPath,
            $pathMatch
        ) === 1
    ) {
        $setUc015ConfirmationCookie($pathMatch[1]);
        header('Location: https://www.prisma.cat/packs/confirmacio/', true, 303);
        exit;
    }
}
?>
<!DOCTYPE HTML PUBLIC "-/W3C/DTD HTML 4.01/EN" "http:/www.w3.org/TR/html4/strict.dtd">
<html lang="ca" prefix="og: http:/ogp.me/ns# fb: http:/ogp.me/ns/fb# video: http:/ogp.me/ns/video#">
<head>
	<script>
	(function uc015FragmentMigration() {
		if (!window.location.hash || window.location.hash.length <= 1) return;
		var token = '';
		try {
			token = decodeURIComponent(window.location.hash.substring(1));
		}
		catch (e) {
			token = '';
		}
		if (!/^v2\.[A-Za-z0-9_-]+$/.test(token)) {
			window.history.replaceState(null, document.title, window.location.pathname);
			return;
		}
		window.history.replaceState(null, document.title, '/packs/confirmacio/');
		var form = document.createElement('form');
		form.method = 'POST';
		form.action = 'https://www.prisma.cat/packs/confirmacio/';
		var input = document.createElement('input');
		input.type = 'hidden';
		input.name = 'confirmationToken';
		input.value = token;
		form.appendChild(input);
		document.documentElement.appendChild(form);
		form.submit();
	})();
	</script>
	<meta name="robots" content="noindex,nofollow,noarchive">
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
  gtag('config', 'G-DD77YFRVS0', {'send_page_view': false});
  gtag('config', 'AW-10936157157', {'send_page_view': false});

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
   <script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
   <script>
      let pathNom = "/json/confirmacio_regal_curs.jsonld";
      $.getJSON(pathNom,function(data){$("<script/>",{"type":"application/ld+json",html:JSON.stringify(data)}).appendTo("head");});
   </script>
</head>
<body class="index">
   <header></header>

   <div id="cnt-pagament-grup" class="prisma-container container separacio-peu" role="main">Confirmació pagament</div>
   <footer class="prisma-footer"></footer>
   <<script async>
      var headerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.2' />";
      if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
         headerCSS += "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header_mbl.min.css?ver=5.0' />";
      }
      else {
         headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px)' href='https://www.prisma.cat/css1619773569/header_wind.min.css?ver=5.0' />";
         headerCSS += "<link rel='stylesheet' media='screen and (max-width: 768px)' href='https://www.prisma.cat/css1619773569/header_mbl.min.css?ver=5.0' />";
      }
		var footerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/footer.min.css?ver=6.1' />";
      $('head').append(headerCSS);
      $('head').append(footerCSS);
   </script>
   <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/pagamentGrup.min.css?ver=5.0' />
   <script async src="https://www.prisma.cat/js1619773569/mostrarConfirmacioPagamentGrupAutomatic.min.js?ver=2.3"></script>
   <script async src="https://www.prisma.cat/js1619773569/obrirTancar.min.js"></script>
   <script async src="https://www.prisma.cat/js1619773569/lazysizes.min.js"></script>
   <script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
</body>
</html>
