<?php

$result_m = mysqli_query ($conexion_m,"SELECT SUM(CASE WHEN (finalgrade=100) THEN 1 ELSE 0 END) superats, SUM(CASE WHEN (finalgrade<100) THEN 1 ELSE 0 END) AS no_superats FROM mdl_grade_grades AS gg INNER JOIN mdl_grade_items AS gi ON gi.id=gg.itemid INNER JOIN (SELECT userid FROM mdl_role_assignments AS ass WHERE ass.roleid=5 AND ass.contextid = (SELECT cx.id FROM mdl_context AS cx WHERE contextlevel=50 AND instanceid=(SELECT id from mdl_course WHERE shortname='".$curs."'))) AS resultat ON resultat.userid = gg.userid WHERE gi.itemtype='course' AND gi.courseid=(SELECT id from mdl_course WHERE shortname='".$curs."')");

?>
