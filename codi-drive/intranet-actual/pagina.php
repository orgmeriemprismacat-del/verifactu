<?php

include("inc/comprovarSessio.php");

if (!$configOk) {
	?>
	<script>window.location.href = "https://intranet.prisma.cat/"</script>
	<?php
}
else {
?>
<!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
<html lang="ca" prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# video: http://ogp.me/ns/video#">
	<head>
		<meta charset="utf-8">
		<meta http-equiv="X-UA-Compatible" content="IE=edge">
		<meta name="viewport" content="width=device-width, initial-scale=1">

		<title>Intranet</title>

		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"/>
		<!-- CSS General -->
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/general.min.css?ver=1.1"/>

		<!-- jQuery-->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<!-- Bootstrap JS -->
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

	</head>
	<body>
		<header>
			<!-- -->
		</header>
		<div class='contingut'>
			<!-- -->
		</div>
		<footer>
			<!-- -->
		</footer>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/access.css?ver=1.0"/>
		<script src="https://intranet.prisma.cat/js/access.js?ver=1.0"></script>
	</body>
</html>
<?php } ?>
