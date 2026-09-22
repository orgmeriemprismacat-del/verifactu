<?php
/* AQUEST FITXER S'UTILITZA A:
/intranet/proposta_tutoritzacio.php
/intranet/confirmacio_tutoritzacio.php
/intranet/revisio_curs.php */

// if ($pagina == "proposta_tutoritzacio")
// 	echo "<div class=\"nav_on2\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">PROPOSTA</span><br />TUTORITZACIÓ</div>";
// else
// 	echo "<div class=\"nav2\"><a href=\"proposta_tutoritzacio.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">PROPOSTA</span><br />TUTORITZACIÓ</a></div>";

// if ($pagina == "confirmacio_tutoritzacio")
// 	echo "<div class=\"nav_on2\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">CONFIRMACIÓ</span><br />TUTORITZACIÓ</div>";
// else
// 	echo "<div class=\"nav2\"><a href=\"confirmacio_tutoritzacio.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">CONFIRMACIÓ</span><br />TUTORITZACIÓ</a></div>";

if ($pagina == "revisio")
	echo "<div class=\"nav_on2\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">REVISIÓ</div>";
else if ( $_SESSION['rol'] == 'admin')
	echo "<div class=\"nav2\"><a href=\"revisio_curs.php\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">REVISIÓ</a></div>";

if ($pagina == "estadistiques")
	echo "<div class=\"nav_on2\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">ESTADÍSTIQUES</div>";
else if ( $_SESSION['rol'] == 'admin')
	echo "<div class=\"nav2\"><a href=\"estadistiques.php\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">ESTADÍSTIQUES</a></div>";

/*if ($pagina == "previsio")
	echo "<div class=\"nav_on2\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">PREVISIÓ</div>";
else if ( $_SESSION['rol'] == 'admin')
	echo "<div class=\"nav2\"><a href=\"previsio.php\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">PREVISIÓ</a></div>";*/
