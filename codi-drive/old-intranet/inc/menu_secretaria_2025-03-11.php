<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/alumnes.php
/intranet/revisions.php
/intranet/informes.php */
	/*if ($pagina == "alumnes")
		echo "<div class=\"nav_on\" style=\"margin-left:1px;\">ALUMNES</div>";
	else if ( $_SESSION['rol'] == 'admin')
		echo "<div class=\"nav\"><a href=\"alumnes_secre.php\" style=\"margin-left:1px;\">ALUMNES</a></div>";
*/
// if ($pagina == "avisar_cursos_pendents")
// 		echo "<div class=\"nav_on\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">INICI </span><br />CURSOS</div>";
// 	else
// 		echo "<div class=\"nav2\"><a href=\"avisar_cursos_pendents.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">INICI </span><br />CURSOS</a></div>";

	/*if ($pagina == "correu_campus" || $pagina == "missatges_personalitzats")
		echo "<div class=\"nav_on2\" style=\"margin-left:1px;padding-left: 5px;padding-right: 5px;\"><span style=\"font-size:10px\">MISSATGES</span><br />PERSONALITZATS</div>";
	else if ( $_SESSION['rol'] == 'admin')
		echo "<div class=\"nav2\"><a href=\"correu_campus.php\" style=\"margin-left:1px;;\"><span style=\"font-size:10px\">MISSATGES</span><br />PERSONALITZATS</a></div>";*/

	if ($pagina == "informes")
		echo "<div class=\"nav_on\" style=\"margin-left:1px;\">INFORMES</div>";
	else if ( $_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'tut' )
		echo "<div class=\"nav\"><a href=\"informes.php\" style=\"margin-left:1px;\">INFORMES</a></div>";

	if ($pagina == "revisions")
		echo "<div class=\"nav_on\" style=\"margin-left:1px;\">REVISIONS</div>";
	else if ( $_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'tut' )
		echo "<div class=\"nav\"><a href=\"revisions.php\" style=\"margin-left:1px;\">REVISIONS</a></div>";

	if ($pagina == "comunicats")
		echo "<div class=\"nav_on\" style=\"margin-left:1px;\">COMUNICATS</div>";
	else if ( $_SESSION['rol'] == 'admin')
		echo "<div class=\"nav\"><a href=\"comunicats_campus_nou.php\" style=\"margin-left:1px;\">COMUNICATS</a></div>";

/*	if ($pagina == "inici")
		echo "<div class=\"nav_on\" style=\"margin-left:1px;\">INICI</div>";
	else if ( $_SESSION['rol'] == 'admin')
		echo "<div class=\"nav\"><a href=\"inici.php\" style=\"margin-left:1px;\">INICI</a></div>";*/

?>
