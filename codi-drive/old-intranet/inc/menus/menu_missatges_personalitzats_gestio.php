<?php 
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/primera_reclamacio.php
/intranet/baixes.php 
/intranet/recordatori_pagament_final.php*/ ?>

<div style="text-align:left; border-top:solid 1px #CCCCCC"></div>
<div id="menu_botons" style="clear:both; padding-left: 50px;"> 
<?php
	if ($visualitzacio == "primera_reclamacio")
		echo "<div class=\"nav_on2_rodo\" style=\"margin-left:25px;\"><span style=\"font-size:10px\">CORREU</span><br />1a RECLAMACIÓ</div>";
	else
		echo "<div class=\"nav2_rodo\" style=\"margin-left:25px;\"><a href=\"primera_reclamacio.php\" style=\"margin-left:1px;\"><span style=\"font-size:10px\">CORREU</span><br />1a RECLAMACIÓ</a></div>";
	
	if ($visualitzacio == "baixes")
		echo "<div class=\"nav_on2_rodo\"><span style=\"font-size:10px\">BAIXES</span><br />2a SETMANA</div>";
	else
		echo "<div class=\"nav2_rodo\"><a href=\"baixes.php\"><span style=\"font-size:10px\">BAIXES</span><br />2a SETMANA</a></div>";
	
	if ($visualitzacio == "recordatori_pagament_final")
		echo "<div class=\"nav_on2_rodo\"><span style=\"font-size:10px\">RECORDATORI</span><br />PAGAMENT FINAL</div>";
	else
		echo "<div class=\"nav2_rodo\"><a href=\"recordatori_pagament_final.php\"><span style=\"font-size:10px\">RECORDATORI</span><br />PAGAMENT FINAL</a></div>";
	
	
?>
</div>