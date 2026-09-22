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
  gtag('config', 'G-DD77YFRVS0');

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
   <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.0' />
   <link rel='stylesheet' href='https://www.prisma.cat/css1619773569/footer.min.css?ver=5.0' />
   <link rel='shortcut icon' type='image/x-icon' href='https://www.prisma.cat/favicon.ico'/>
   <script src="https://kit.fontawesome.com/5b3303ad4a.js"></script>
</head>
<body class="index">
   <header></header>
   <?php

   $email = $_GET['email'];

   $mostrar = "<div id='contingut' class='prisma-container container separacio-peu' role='main'>
      <div class='container' id='notfound'>
         <div class='col-md-12'>
            <div class='page-error-content text-center'>
               <h1>El pagament s'ha registrat correctament</h1>
               <p class='mb-4'>El pagament s'ha registrat correctament.
               Consulta la safata d'entrada o el correu brossa (<em>spam</em>)
               de l'adreça <span class='font-weight-bold email'>".$email."</span>
               per comprovar que has rebut el missatge de confirmació del pagament.</p>
            </div>
         </div>
      </div>
	</div>";

   echo $mostrar;
   ?>
	<footer class="prisma-footer"></footer>
   <script async>
      var headerCSS;
      if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
         headerCSS += "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.0' />";
      }
      else {
         headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px)' href='https://www.prisma.cat/css1619773569/header_wind.min.css?ver=5.0' />";
         headerCSS += "<link rel='stylesheet' media='screen and (max-width: 768px)' href='https://www.prisma.cat/css1619773569/header.min.css?ver=5.0' />";
      }
      $('head').append(headerCSS);
   </script>
   <script async src="https://www.prisma.cat/js1619773569/mostrarRespostaPagamentRegal.min.js?ver=2.0"></script>
   <script async src="https://www.prisma.cat/js1619773569/obrirTancar.min.js?ver=1.0"></script>
   <script async src="https://www.prisma.catjs1619773569/lazysizes.min.js"></script>
   <script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js?ver=1.0"></script>
</body>
</html>
