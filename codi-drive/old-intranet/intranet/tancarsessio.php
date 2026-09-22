<?php
session_name("sessio_admin");
session_start();
session_unset();
session_destroy();
Header ("Location: acces.php");

$enlace = mysqli_connect('localhost','suport','1324GiRoNa','gestio');

if (!$enlace)
{
    die('No s\'ha pogut connectar: ' . mysqli_error());
}
mysqli_close($enlace);

?>

