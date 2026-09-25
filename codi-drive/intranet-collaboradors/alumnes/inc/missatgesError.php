<?php

function missatgeError( $codiError ) {
$error = "<div class='container no-trobat'>";
$error .= "<img src='https://www.prisma.cat/img/error_404.png' title='Error'>";
$error .= "<h1 class='text-centrat'>Error ".$codiError."</h1>";
$error .= "<p class='text-centrat'>Refresca la pàgina. Si segueixes tenint el mateix error, contacte amb nosaltres a partir del nostre <a href='https://www.prisma.cat/contacte' title='Contacta amb PrisMa'>formulari de contacte</a> indicant l'error per poder-te ajudar més ràpidament.</p>";
$error .= "</div>";

echo $error;
}

 ?>
