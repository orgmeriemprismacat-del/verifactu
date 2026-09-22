<?php 
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/facturacio/pagaments_efectuats.php
/intranet/facturacio/pagaments_efectuats_tutor.php 
/intranet/facturacio/pagaments_efectuats_curs.php 
/intranet/facturacio/pagaments_efectuats_any_mes.php */ ?>

<div style="text-align:left; border-top:solid 1px #CCCCCC"></div>
<div id="menu_botons" style="clear:both;"> 
<?php
	if ($visualitzacio == "tots")
		echo "<div class=\"nav_on2_rodo\" style=\"margin-left:25px;\"><span style=\"font-size:10px\">VISUALITZAR</span><br />TOTS</div>";
	else
		echo "<div class=\"nav2_rodo\" style=\"margin-left:25px;\"><a href=\"pagaments_efectuats.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">VISUALITZAR</span><br />TOTS</a></div>";
	
	if ($visualitzacio == "tutor")
		echo "<div class=\"nav_on2_rodo\"><span style=\"font-size:10px\">BUSCAR PER</span><br />TUTOR</div>";
	else
		echo "<div class=\"nav2_rodo\"><a href=\"pagaments_efectuats_tutor.php\" style=\"margin-left:10px;\"><span style=\"font-size:10px\">BUSCAR PER</span><br />TUTOR</a></div>";
	
	if ($visualitzacio == "curs")
		echo "<div class=\"nav_on2_rodo\" style=\"margin-left:10px;\"><span style=\"font-size:10px\">BUSCAR PER </span><br />CURS</div>";
	else
		echo "<div class=\"nav2_rodo\"><a href=\"pagaments_efectuats_curs.php\" style=\"margin-left:10px;\"><span style=\"font-size:10px\">BUSCAR PER </span><br />
		CURS</a></div>";
	
	if ($visualitzacio == "data")
		echo "<div class=\"nav_on2_rodo\" style=\"margin-left:10px;\"><span style=\"font-size:10px\">BUSCAR PER </span><br />ANY I MES</div>";
	else
		echo "<div class=\"nav2_rodo\"><a href=\"pagament_efectuat_any_mes.php\" style=\"margin-left:10px;\"><span style=\"font-size:10px\">BUSCAR PER </span><br />ANY I MES</a></div>";
?>
</div>