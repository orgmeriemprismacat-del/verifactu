<?php
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ca" prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# video: http://ogp.me/ns/video#">
	<head>
		<!-- Sense analytics: aquesta pàgina conté un token de confirmació sensible. -->
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">
		<?php include("./inc/buscarMetaTags.php");flush();?>
		<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/estructura.min.css?ver=5.0' />
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
	</head>
	<body class="index" style='height: 100vh !important'>
		<header></header>

		<div id='cnt-inscripcio' class="prisma-container container separacio-peu" role="main"></div>
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
			var footerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/footer.min.css?ver=6.1' />";
			$('head').append(headerCSS);
			$('head').append(footerCSS);
		</script>
		<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/confirmacioInscripcio.min.css?ver=5.0' />
		<script async src="https://www.prisma.cat/js1619773569/mostrarConfirmacioInscripcioTastetAutomatic.min.js?ver=2.2"></script>
		<script async src="https://www.prisma.cat/js1619773569/lazysizes.min.js?ver=1.0"></script>
		<script async src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
	</body>
</html>
