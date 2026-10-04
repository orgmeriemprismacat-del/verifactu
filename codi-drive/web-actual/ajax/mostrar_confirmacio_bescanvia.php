<?php

declare(strict_types=1);

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
	http_response_code(405);
	header('Allow: POST');
	exit;
}

header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-store, private');
header('Pragma: no-cache');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');

include("../ConnexioBBDD_PreparedStatment.php");
include("../inc/GiftRedemptionConfirmationToken.php");

try {
	$token = trim((string) ($_POST['token'] ?? ''));
	if ($token === '') {
		throw new RuntimeException('Missing gift confirmation token');
	}

	$connexio = new ConnexioBBDDSTMT();
	$connexio->connectarBD();

	$cnsParam = "SELECT VALOR FROM params WHERE TIPUS=? AND DATAI<=CURRENT_TIMESTAMP
					AND (DATAF IS NULL OR DATAF>=CURRENT_TIMESTAMP)";
	$stmt = $connexio->prepare($cnsParam);
	$stmt->bind_param("s", $tipusParam);
	$tipusParam = 'keyEncriptar';
	$stmt->execute();
	$stmt->bind_result($keyEncr);
	$hasKey = $stmt->fetch();
	$connexio->closeStmt();

	if (!$hasKey || trim((string) $keyEncr) === '') {
		throw new RuntimeException('Gift confirmation key is unavailable');
	}

	$originalId = GiftRedemptionConfirmationToken::parse(
		$token,
		(string) $keyEncr
	);

	$cnsGift = "SELECT ID FROM regal WHERE USAT=? LIMIT 2";
	$stmt = $connexio->prepare($cnsGift);
	$stmt->bind_param("i", $originalId);
	$stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows() !== 1) {
		$connexio->closeStmt();
		throw new RuntimeException('Gift confirmation does not match one redeemed gift');
	}
	$stmt->bind_result($giftId);
	$stmt->fetch();
	$connexio->closeStmt();

	$cnsEnrollment = "SELECT CORREU FROM inscripcions WHERE ID=?";
	$stmt = $connexio->prepare($cnsEnrollment);
	$stmt->bind_param("i", $originalId);
	$stmt->execute();
	$stmt->store_result();
	if ($stmt->num_rows() !== 1) {
		$connexio->closeStmt();
		throw new RuntimeException('Gift confirmation enrollment was not found');
	}
	$stmt->bind_result($email);
	$stmt->fetch();
	$connexio->closeStmt();

	$email = trim((string) $email);
	$parts = explode('@', $email, 2);
	if (count($parts) === 2 && $parts[0] !== '' && $parts[1] !== '') {
		$visible = substr($parts[0], 0, min(2, strlen($parts[0])));
		$maskedEmail = $visible . '***@' . $parts[1];
	} else {
		$maskedEmail = 'l’adreça indicada a la inscripció';
	}

	$maskedEmailHtml = htmlspecialchars(
		$maskedEmail,
		ENT_QUOTES | ENT_SUBSTITUTE,
		'UTF-8'
	);

	echo "
	<h1 class='mb-4'>Confirmació de la inscripció</h1>
	<div class='info-banner mb-4 pt-3'>
		<picture>
			<source type='image/webp' class='w-100 border-radius-2 banner-img'
				data-srcset='https://www.prisma.cat/img/portades/bescanvia-curs.webp'
				alt='Bescanvia la targeta regal!'
				srcset='https://www.prisma.cat/img/portades/bescanvia-curs.webp'>
			<source type='image/jpeg' class='w-100 border-radius-2 banner-img'
				data-srcset='https://www.prisma.cat/img/portades/bescanvia-curs.jpg'
				alt='Bescanvia la targeta regal!'
				srcset='https://www.prisma.cat/img/portades/bescanvia-curs.jpg'>
			<img role='img' class='w-100 border-radius-2 banner-img lazyloaded'
				data-src='https://www.prisma.cat/img/portades/bescanvia-curs.jpg'
				alt='Bescanvia la targeta regal!'
				src='https://www.prisma.cat/img/portades/bescanvia-curs.jpg'>
		</picture>
	</div>
	<p>Acabes d'utilitzar correctament la targeta regal.</p>
	<p>
		Consulta la <span class='font-weight-bold'>safata d'entrada o el correu
		brossa (<em>spam</em>)</span> de l'adreça
		<span class='email font-weight-bold'>" . $maskedEmailHtml . "</span>
		per comprovar que has rebut el missatge de confirmació.
	</p>
	<p>
		Si la inscripció no s'ha realitzat correctament, contacta amb nosaltres
		al telèfon <span class='font-weight-bold'>972 21 75 65</span> o a través del
		<a class='font-weight-bold' href='https://www.prisma.cat/contacte'
		title='Contacta amb PrisMa'>formulari de contacte</a>.
	</p>
	<p>Gràcies per confiar en PrisMa.</p>";

	$connexio->desconectarBD();
} catch (Throwable $e) {
	if (isset($connexio)) {
		try {
			$connexio->desconectarBD();
		} catch (Throwable $ignored) {
		}
	}
	http_response_code(400);
	echo "<p>No s'ha pogut validar la confirmació de la inscripció.</p>";
}

?>