<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ca" prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# video: http://ogp.me/ns/video#">
<head>
	<meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1">
	<?php include("./inc/buscarMetaTags.php");flush();?>
    <style>.url-no-mostrar{display:none}</style>
	<link rel='stylesheet' href='https://www.prisma.cat/web/css/estructura-prisma.min.css?ver=1.0' />
	<script src="https://www.prisma.cat/web/js/jquery.min.js"></script>
</head>
<body class="index">
	<header></header>
	<div class="prisma-container" role="main"><div id="contingut_perfils"></div></div>
  <footer class="prisma-footer"></footer>
	<script async>
		var headerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/web/css/header.min.css' />";
		if(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
			headerCSS += "<link rel='stylesheet' href='https://www.prisma.cat/web/css/header_mbl.min.css' />";
		}
		else {
			headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px)' href='https://www.prisma.cat/web/css/header_wind.min.css' />";
			headerCSS += "<link rel='stylesheet' media='screen and (min-width: 769px) and (max-width: 992px)' href='https://www.prisma.cat/web/css/header_wind_769.min.css' />";
			headerCSS += "<link rel='stylesheet' media='screen and (max-width: 768px)' href='https://www.prisma.cat/web/css/header_mbl.min.css' />";
		}
		var footerCSS = "<link rel='stylesheet' href='https://www.prisma.cat/web/css/footer-all.min.css' />";
		$('head').append(headerCSS);
		$('head').append(footerCSS);
	</script>
	<script async src="https://www.prisma.cat/web/js/mostrarPerfils.min.js"></script>
	<script async src="https://www.prisma.cat/web/js/materialize.min.js"></script>
    <script async src="https://www.prisma.cat/web/js/bootstrap.min.js"></script>
</body>
</html>
