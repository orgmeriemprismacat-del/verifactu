<?php
require('../config.php');

session_start();
include("inc/comprovarSessio.php");

// print_r($_SESSION);

if (!$configOk) {
	?>
	<script>window.location.href = "https://campus.prisma.cat/login"</script>
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

		<title>Cursos que no has realitzat | Intranet</title>

		<!-- Bootstrap CSS -->
		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"/>
		<!-- CSS General -->
		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-alumnes/css/general.css?ver=6.0"/>

		<!-- jQuery-->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<!-- Bootstrap JS -->
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>

		<link rel="preconnect" href="https://fonts.googleapis.com">
		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
		<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&display=swap" rel="stylesheet">
	</head>
	<body>
		<div class="contingut">
			<div class="sidebar active h-100 position-fixed bg-white active"></div>
			<div class="mainpanel h-100 position-relative float-right ps active">
			</div>
		</div>
		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-alumnes/css/forms.css?ver=6.0"/>
		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-alumnes/css/table.css?ver=6.0"/>
		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-alumnes/css/alerts.css?ver=6.0"/>
		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-alumnes/css/modals.css?ver=6.0"/>
		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-alumnes/css/meus-cursos.css?ver=6.0"/>
		<script src="https://campus.prisma.cat/intranet-alumnes/js/general.js?ver=7.0"></script>
		<script src="https://campus.prisma.cat/intranet-alumnes/js/meus-cursos.js?ver=7.0"></script>
	</body>
</html>
<?php } ?>
