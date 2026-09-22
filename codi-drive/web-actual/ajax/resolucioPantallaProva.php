<?php
include("../parametres_connexio.php");
include("../ConnexioBBDD_PreparedStatment.php");
include("../Mail.php");

$width = $_GET['width'];
$height = $_GET['height'];
$navegador = $_GET['navegador'];

$data = new DateTime();
$timestamp = $data->getTimestamp();

$missatge  = "<p><strong>width:</strong> ".$width."</p>";
$missatge .= "<p><strong>height:</strong> ".$height."</p>";
$missatge .= "<p><strong>navegador:</strong> ".$navegador."</p>";

$prisma2 = new Mail();
$subject="Accés a la pàgina de la home ".$timestamp;
$prisma2->addSubject($subject);
$correu = 'webmaster@prisma.cat';
$prisma2->addHeaders("Suport", $correu, $correu);
$prisma2->addTo($correu);
$prisma2->addMissatge($missatge);
$prisma2->sendMessage();

?>
