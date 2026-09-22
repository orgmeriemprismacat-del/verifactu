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
		<?php include("./inc/buscarMetaTags.php");flush();?>
		<style>.url-no-mostrar{display:none}</style>
		<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/estructura.min.css?ver=6.0' />
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<script>
			$.getJSON("/json/cdd.jsonld",function(data){$("<script/>",{"type":"application/ld+json",html:JSON.stringify(data)}).appendTo("head");});
		</script>
	</head>
	<body class="index">
		<header></header>

		<div id='contingut_perfils' class="prisma-container" role="main"></div>
		<footer class="prisma-footer"></footer>
		<script async>
			var headerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.2' />";
			if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
				headerCSS += "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header_mbl.min.css?ver=1.0' />";
			}
			else {
				headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px)' href='https://www.prisma.cat/css1619773569/header_wind.min.css?ver=3.0' />";
				headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px) and (max-width: 992px)' href='https://www.prisma.cat/css1619773569/header_wind_769.min.css?ver=1.0' />";
				headerCSS += "<link rel='stylesheet' media='screen and (max-width: 768px)' href='https://www.prisma.cat/css1619773569/header_mbl.min.css?ver=1.0' />";
			}
			var footerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/footer.min.css?ver=6.1' />";
			$('head').append(headerCSS);
			$('head').append(footerCSS);
		</script>
      <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/pagina.min.css?ver=7.0' />
      <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/cdd.min.css?ver=7.2' />
		<script async src="https://www.prisma.cat/js1619773569/mostrarCDD.min.js?ver=6.1"></script>
		<script async src="https://www.prisma.cat/js1619773569/lazysizes.min.js?ver=1.0"></script>
		<script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
	</body>
</html>
