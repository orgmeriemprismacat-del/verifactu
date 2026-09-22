<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/revisions.php
/intranet/informes.php */
	//
	// if ($grup == "gestio")
	// 	echo "<div class=\"nav_on ppal_on\" style=\"margin-left:19px;\">GESTIÓ</div>";
	// else if ( $_SESSION['rol'] == 'admin')
	// 	echo "<div class=\"nav ppal\" style=\"margin-left:19px;\"><a href=\"primera_reclamacio.php\" style=\"margin-left:1px;\">GESTIÓ</a></div>";

	if ($grup == "secretaria")
		echo "<div class=\"nav_on ppal_on\" style=\"margin-left:19px;\">SECRETARIA</div>";
	else if ( $_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'tut' )
		echo "<div class=\"nav ppal\" style=\"margin-left:19px;\"><a href=\"informes.php\" style=\"margin-left:1px;\">SECRETARIA</a></div>";
		//echo "<div class=\"nav ppal\"><a href=\"alumnes_secre.php\" style=\"margin-left:1px;\">SECRETARIA</a></div>";

	if ($grup == "coordinacio")
		echo "<div class=\"nav_on ppal_on\">COORDINACIÓ</div>";
	else if ( $_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'tut' )
		echo "<div class=\"nav ppal\"><a href=\"estadistiques.php\" style=\"margin-left:1px;\">COORDINACIÓ</a></div>";

	if ($grup == "administracio")
		echo "<div class=\"nav_on ppal_on\">ADMINISTRACIÓ</div>";
	else if ( $_SESSION['rol'] == 'admin' || $_SESSION['rol'] == 'tut' )
		echo "<div class=\"nav ppal\"><a href=\"resum_mes.php\" style=\"margin-left:1px;\">ADMINISTRACIÓ</a></div>";

	// if ($grup == "suport")
	// 	echo "<div class=\"nav_on ppal_on\">SUPORT</div>";
	// else if ( $_SESSION['rol'] == 'admin' )
	// 	echo "<div class=\"nav ppal\"><a href=\"moodle.php\" style=\"margin-left:1px;\">SUPORT</a></div>";

?>
