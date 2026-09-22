<?php
/* AQUEST FITXER S'UTILITZA A:
/intranet/recordatori_pagament_final.php */
/*if ($pagina == "nombre_alumnes")
	echo "<div class=\"nav_on2\" style=\"margin-left:19px;\"><span style=\"font-size:10px\">NOMBRE</span><br />D'ALUMNES</div>";
else
	echo "<div class=\"nav2\"><a href=\"nombre_alumnes.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">NOMBRE</span><br />D'ALUMNES</a></div>";
*/
if ($pagina == "missatges_personalitzats_gestio")
	echo "<div class=\"nav_on2\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">MISSATGES</span><br />PERSONALITZATS</div>";
else
	echo "<div class=\"nav2\"><a href=\"primera_reclamacio.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">MISSATGES</span><br />PERSONALITZATS</a></div>";
/*
	if ($pagina == "alumnes")
		echo "<div class=\"nav_on2\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">ALUMNES</div>";
	else
		echo "<div class=\"nav2\"><a href=\"alumnes.php\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">ALUMNES</a></div>";
*/
if ($pagina == "certificats")
	echo "<div class=\"nav_on2\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">CERTIFICATS</div>";
else
	echo "<div class=\"nav2\"><a href=\"certificats_all.php\" style=\"margin-left:1px; padding-top: 12px; height: 25px;\">CERTIFICATS</a></div>";
