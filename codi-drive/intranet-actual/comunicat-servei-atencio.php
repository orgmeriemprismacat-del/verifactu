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

		<title>Comunicat / Servei d'atenció | Intranet</title>

		<!-- Bootstrap CSS -->
		<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet" integrity="sha384-QWTKZyjpPEjISv5WaRU9OFeRpok6YctnYmDr5pNlyT2bRjXh0JMhjY6hW+ALEwIH" crossorigin="anonymous">
		<!-- CSS General -->
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/general.min.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/general_v5.min.css?ver=1.0"/>

		<!-- jQuery-->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
		<!-- Popper JS -->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/popper.js/1.12.9/umd/popper.min.js"></script>
		<!-- Bootstrap JS -->
		<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>
		<!-- Fontawesome -->
		<script src="https://kit.fontawesome.com/efc6febcf3.js" crossorigin="anonymous"></script>
	</head>
	<body>
		<div class="contingut">
			<div class="sidebar h-100 position-fixed bg-white"></div>
			<div class="mainpanel h-100 position-relative float-end ps"></div>
		</div>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/forms.min.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/table.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/table-responsive.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/alerts.min.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/modals.min.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/comunicat-servei-atencio.min.css?ver=1.0"/>
		<script src="https://intranet.prisma.cat/js/general_v5.js?ver=1.0"></script>
		<script src="https://intranet.prisma.cat/js/comunicat-servei-atencio.min.js?ver=1.3"></script>

	</body>
</html>
<?php } ?>
