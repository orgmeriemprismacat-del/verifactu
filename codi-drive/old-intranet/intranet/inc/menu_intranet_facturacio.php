<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/facturacio/pagaments_efectuats.php */ ?>

 <td style="padding-top: 25px; padding-left: 25px; padding-bottom: 10px;">
	<img src="../img/formacio_rectangular.jpg" style="float:left" />
	<div id="menu" style="clear:both">
		<?php
		if ($pagina == "pagaments_pendents")
			echo "<div class=\"nav_on2\"><span style=\"font-size:10px\">PAGAMENTS</span><br />PENDENTS</div>";
		else
			echo "<div class=\"nav2\"><a href=\"pagaments_pendents.php\" style=\"margin-left:19px;\"><span style=\"font-size:10px\">PAGAMENTS</span><br />PENDENTS</a></div>";

		if ($pagina == "pagaments_efectuats")
			echo "<div class=\"nav_on2\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">PAGAMENTS</span><br />EFECTUATS</div>";
		else
			echo "<div class=\"nav2\" style=\"margin-left:19px;\"><a href=\"pagaments_efectuats.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">PAGAMENTS</span><br />EFECTUATS</a></div>";
		
		/*if ($pagina == "factures")
			echo "<div class=\"nav_on2\"><span style=\"font-size:10px\">DESCARREGAR</span><br />FACTURES</div>";
		else
			echo "<div class=\"nav2\"><a href=\"factures.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">DESCARREGAR</span><br />FACTURES</a></div>";*/

		?>
	</div>
</td>
