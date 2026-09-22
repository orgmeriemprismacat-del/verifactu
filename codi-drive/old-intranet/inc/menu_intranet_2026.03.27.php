<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/alumnes.php
/intranet/revisions.php
/intranet/informes.php */
?>

<!--<td style="padding-top: 25px; padding-left: 25px; padding-bottom: 10px;">-->
<td>
	<img src="./img/formacio_rectangular.jpg" style="float:left" />
	<div style="position:relative; padding-right:25px; padding-top:10px; text-align:right"><input type="button" class="myButton" name="myButton" value="Tancar sessió" onclick="window.location.href='tancarsessio.php';" /></div>
	<div id="menu-ppal" style="clear:both;">
		<?php
			include('./inc/menu_general.php');
		?>
	</div>

	<div id="menu" style="clear:both; padding-top:10px; padding-left:50px">
		<?php
			if ($grup == "secretaria")
				include('./inc/menu_secretaria.php');
			else if ($grup == "coordinacio")
				include('./inc/menus/menu_coordinacio.php');
			// else if ($grup == "gestio")
			// 	include('./inc/menus/menu_gestio.php');

		?>
	</div>

	<!--<div id="menu_botons" style="clear:both; padding-left:50px">   -->
		<?php
			if ($pagina == "inici_cursos")
				include('./inc/menus/menu_inici_cursos.php');
			if ($pagina == "missatges_personalitzats")
				include('./inc/menus/menu_missatges_personalitzats.php');
			// if ($pagina == "missatges_personalitzats_gestio")
			// 	include('./inc/menus/menu_missatges_personalitzats_gestio.php');
		?>

		<!--<div class="nav_on2_rodo"><span style="font-size:10px">AVISAR CURSOS</span><br />PENDENTS</div>
		<div class="nav2_rodo"><a href="inici_cursos_pendents.php"><span style="font-size:10px">INICI CURSOS</span><br />PENDENTS</a></div>
		<div class="nav2_rodo"><a href="cursos_anulats.php"><span style="font-size:10px">AVISAR CURSOS</span><br />ANUL·LATS</a></div>
	</div>-->

</td>
