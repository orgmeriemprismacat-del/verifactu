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
	<meta name="theme-color" content="#496BAA"/>
	<?php include("./inc/buscarMetaTagsStmt.php");flush();?>
	<link rel="manifest" href="https://www.prisma.cat/manifest.json">
	<link rel="apple-touch-icon" href="https://www.prisma.cat/apple-touch-icon-120x120.png">
	<link rel='shortcut icon' type='image/x-icon' href='https://www.prisma.cat/favicon.ico'/>
	<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/estructura.min.css?ver=6.0' />
	<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
  <script src="https://kit.fontawesome.com/65815b6a64.js" crossorigin="anonymous"></script>
	<script async>
		let path = window.location.pathname.split('?')[0];
		if (path.substr(-1) == "/") path = path.substr(0, path.length - 1);
		let vectPath = path.split('/'),
				pathNom = vectPath[2];
		$.getJSON("/json/curs_"+pathNom+".jsonld",function(data){$("<script/>",{"type":"application/ld+json",html:JSON.stringify(data)}).appendTo("head");});
	</script>
</head>
<body class="index">
   <header></header>

   <div id="contingut_curs" class="prisma-container" role="main"></div>
   <footer class="prisma-footer"></footer>
   <script async>
      var headerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.1' />";
      if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
         headerCSS += "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header_mbl.min.css?ver=5.2' />";
      }
      else {
         headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px)' href='https://www.prisma.cat/css1619773569/header_wind.min.css?ver=5.0' />";
         headerCSS += "<link rel='stylesheet' media='screen and (max-width: 768px)' href='https://www.prisma.cat/css1619773569/header_mbl.min.css?ver=5.2' />";
      }
      var footerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/footer.min.css?ver=6.1' />";
      $('head').append(headerCSS);
      $('head').append(footerCSS);

	   var divAlerta;
   </script>
   <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/curs.min.css?ver=8.1' />
   <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/info.min.css?ver=8.5' />

   <script async src="https://www.prisma.cat/js1619773569/mostrarTaller.min.js?ver=9.0"></script>
   <script defer src="https://www.prisma.cat/js1619773569/mostrarTallerPostLoad.min.js?ver=9.0"></script>
   <script async src="https://www.prisma.cat/js1619773569/obrirTancar.min.js?ver=1.0"></script>
   <script async src="https://www.prisma.cat/js1619773569/lazysizes.min.js?ver=1.0"></script>
   <script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

   <link rel="preload" href="https://www.prisma.cat/css1619773569/404.min.css?ver=2.0" as="style" onload="this.onload=null;this.rel='stylesheet'">
   <noscript><link rel="stylesheet" href="https://www.prisma.cat/css1619773569/404.min.css?ver=2.0"></noscript>
   <link rel="preload" href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,400,400i,500,500i,700,700i|Nunito+Sans&display=swap" as="style" onload="this.onload=null;this.rel='stylesheet'">
   <noscript><link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Roboto:100,100i,300,400,400i,500,500i,700,700i|Nunito+Sans&display=swap"></noscript>

</body>
</html>
