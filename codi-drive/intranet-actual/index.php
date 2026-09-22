<?php
session_start();
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
		<!-- Font Family Roboto  -->
		<link rel='stylesheet' href='https://fonts.googleapis.com/css?family=Roboto:100,100i,300,400,400i,500,500i,700,700i|Nunito+Sans&display=swap' />

		<!-- jQuery-->
		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
		<!-- Bootstrap JS -->
		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
		<!-- Fontawesome -->
		<script src="https://kit.fontawesome.com/4c5c8acff3.js"></script>
	</head>
	<body class="d-flex justify-content-center align-items-center w-100">
		<div class='contingut w-100 h-100 d-flex justify-content-center align-items-center'>
			<div class='bg-white w-100 d-flex flex-column justify-content-center align-items-center border p-4 card'>
				<div class="d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header">
					<p class="title font-weight-bold text-center py-3 mb-0 align-items-center justify-content-center d-flex">ACCÉS A LA INTRANET <i class="material-icons ml-1">login</i></p>
				</div>
				<div class="d-flex flex-column justify-content-center align-items-center w-100 text-center py-3 position-relative ">
					<div class="md-form w-100 pt-3 pb-3">
						<i class="fa fa-user position-absolute"></i>
						<label class="position-absolute" for="username">DNI sense lletra</label>
						<input type="text" name="username" id="username" class="form-control" required="">
					</div>
					<div class="md-form w-100 pt-2 pb-2">
						<i class="fas fa-key position-absolute"></i>
						<label class="position-absolute" for="password">Password</label>
						<input type="password" name="password" id="password" class="form-control" required="">
					</div>
				</div>
				<div id='alerts' class='d-flex w-100'></div>
				<div class="d-flex flex-column justify-content-center align-items-center w-100 text-center">
					<button role="button" class="boto-blau border-0 text-center text-white w-100 mb-2">ACCEDIR</button>
					<a role="link" class="mes-informacio" href="https://campus.prisma.cat/login/forgot-password">Heu oblidat la contrasenya?</a>
				</div>
			</div>
		</div>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/alerts.min.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/forms.min.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/modals.min.css?ver=1.0"/>
		<link rel="stylesheet" href="https://intranet.prisma.cat/css/access.css?ver=1.3"/>
		<script src="https://intranet.prisma.cat/js/general.js?ver=1.1"></script>
		<script src="https://intranet.prisma.cat/js/access.js?ver=1.3"></script>
	</body>
</html>
