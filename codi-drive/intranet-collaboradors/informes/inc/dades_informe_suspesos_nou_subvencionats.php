<?php $result_s = mysqli_query ($conexion_m,"SELECT u.username,u.firstname,u.lastname,gg.finalgrade FROM mdl_grade_grades AS gg
	INNER JOIN mdl_grade_items AS gi ON gi.id=gg.itemid INNER JOIN mdl_user AS u ON u.id=gg.userid
	INNER JOIN mdl_role_assignments ON (u.id = mdl_role_assignments.userid)
	INNER JOIN mdl_context ON (mdl_role_assignments.contextid = mdl_context.id)
	INNER JOIN mdl_course AS c ON (mdl_context.instanceid = c.id)
	INNER JOIN mdl_course_categories ON (c.category = mdl_course_categories.id)
	WHERE c.shortname='".$curs."' AND gi.itemtype='course' AND gi.courseid=c.id AND mdl_context.contextlevel = 50
	and mdl_role_assignments.roleid = 5 ORDER BY ABS(u.username)");

/*$result_vf = mysqli_query ($conexion_m,"SELECT u.username FROM mdl_assign AS a
	INNER JOIN mdl_assign_grades AS ag ON ag.assignment=a.id
	INNER JOIN mdl_grade_grades AS gg on ag.userid=gg.userid
	INNER JOIN mdl_grade_items AS gi ON gi.id=gg.itemid
	INNER JOIN mdl_user AS u ON u.id=gg.userid
	INNER JOIN mdl_role_assignments ON (u.id = mdl_role_assignments.userid)
	INNER JOIN mdl_context ON (mdl_role_assignments.contextid = mdl_context.id)
	INNER JOIN mdl_course AS c ON (mdl_context.instanceid = c.id)
	INNER JOIN mdl_course_categories ON (c.category = mdl_course_categories.id)
	WHERE c.shortname='".$curs."' AND c.id=a.course AND gi.itemtype='course' AND
	gi.courseid=c.id AND mdl_context.contextlevel = 50 and mdl_role_assignments.roleid = 5 GROUP BY ABS(u.username) ORDER BY ABS(u.username) "); */
	
	// ag.grader=-1 quan el tutor no ha fet cap valoració final
	$result_vf = mysqli_query ($conexion_m,"SELECT u.username FROM mdl_assign AS a
	INNER JOIN mdl_assign_grades AS ag ON ag.assignment=a.id
	INNER JOIN mdl_grade_grades AS gg on ag.userid=gg.userid
	INNER JOIN mdl_user AS u ON u.id=gg.userid
	INNER JOIN mdl_role_assignments ON (u.id = mdl_role_assignments.userid)
	INNER JOIN mdl_context ON (mdl_role_assignments.contextid = mdl_context.id)
	INNER JOIN mdl_course AS c ON (mdl_context.instanceid = c.id)
	INNER JOIN mdl_course_categories ON (c.category = mdl_course_categories.id)
	WHERE c.shortname='".$curs."' AND c.id=a.course AND mdl_context.contextlevel = 50 and mdl_role_assignments.roleid = 5 and ag.grader>0 GROUP BY ABS(u.username) ORDER BY ABS(u.username)"); 

$i=1;
$any= substr($curs, 0, 4);
$codi= substr($curs, 4, -3);
$mes= substr($curs, -3, -1);
$aula= substr($curs, -1);

$result_totvf = mysqli_query ($conexion,"SELECT usuari, NOM, COGNOMS FROM inscripcions
	WHERE ANY=".$any." AND CURS='".$codi."' AND MES='".$mes."' AND Grup='".$aula."' AND `INSC CURS` = '1' ORDER BY usuari");

$trobat = 0;
$frase = "<p class=celda>Per enviar l'informe cal haver fet totes les retroaccions de la VALORACIÓ FINAL.</p>";
$num = mysqli_num_rows($result_totvf);

for ($j=1; ($j<=$num && $trobat == 0); $j++) {
	$row_users=mysqli_fetch_array($result_totvf);
	$row_vf=mysqli_fetch_array($result_vf);
	//echo $row_vf['username']." != ".$row_users['usuari']."<br>";
	if ($row_vf['username'] != $row_users['usuari']) {
		//$trobat = 1;
		$trobat = 0; // No es mira en els subvencionats
	}
}

if (mysqli_num_rows($result_s)>0) // si hi ha suspesos, els mostrem en diferents cel·les
{
    ?>
    <tr>
        <td colspan="2">&nbsp;
        </td>
    </tr>
    <tr class="apartat">
        <td colspan="2">
            ALUMNES PENDENTS DE SUPERAR CURS (Seguiment, acords...)    </td>
    </tr>
    <?php

	$row_s=mysqli_fetch_array($result_s);
	$result_tot = mysqli_query ($conexion,"SELECT usuari, NOM, COGNOMS FROM inscripcions WHERE ANY=".$any." AND CURS='".$codi."' AND MES='".$mes."' AND Grup='".$aula."' AND `INSC CURS` = '1' ORDER BY usuari");

    while($row_tot=mysqli_fetch_array($result_tot))
    {
		if ($row_tot['usuari'] == $row_s['username'])
        { 
			if ($row_s['finalgrade']<66) {
				$result_sa = mysqli_query ($conexion,"SELECT seguiment FROM suspesos WHERE codicurs='".$curs."' AND usuari = ".$row_s['username']."");
				$row_sa=mysqli_fetch_array($result_sa);

		?>
				<input type="hidden" name="<?php echo "usuari".$i ?>" value="<?php echo($row_s['username']); ?>"  />
				<tr>
					<td class="celda" colspan="2">
						<?php echo($row_s['firstname']." ".$row_s['lastname']); ?>

					<?php
						if (mysqli_num_rows($result_sa)>0) // ja estava guardat el registre
						{
					?>
							<textarea style="width:100%" rows="1" name="<?php echo "seguiment".$i ?>" id="<?php echo "seguiment".$i ?>"><?php echo($row_sa['seguiment']); ?></textarea>
							<input type="hidden" name="<?php echo "nou".$i ?>" value="no" />
					<?php
						}
						else
						{
						?>
							<textarea style="width:100%" rows="1" name="<?php echo "seguiment".$i ?>" id="<?php echo "seguiment".$i ?>"></textarea>
							<input type="hidden" name="<?php echo "nou".$i ?>" value="si" />
					<?php
						}
					?>
					</td>
				</tr>
			<?php
			$i++;
        	}

		$row_s=mysqli_fetch_array($result_s);
		}
		else if ($row_tot['usuari'] != $row_s['username']) {
			$result_sa = mysqli_query ($conexion,"SELECT seguiment FROM suspesos WHERE codicurs='".$curs."' AND usuari = ".$row_tot['usuari']."");
            $row_sa=mysqli_fetch_array($result_sa);
    ?>
            <input type="hidden" name="<?php echo "usuari".$i ?>" value="<?php echo($row_tot['usuari']); ?>"  />
            <tr>
                <td class="celda" colspan="2">
                    <?php echo($row_tot['NOM']." ".$row_tot['COGNOMS']); ?>

                <?php
                    if (mysqli_num_rows($result_sa)>0) // ja estava guardat el registre
                    {
                ?>
                        <textarea style="width:100%" rows="1" name="<?php echo "seguiment".$i ?>" id="<?php echo "seguiment".$i ?>"><?php echo($row_sa['seguiment']); ?></textarea>
                        <input type="hidden" name="<?php echo "nou".$i ?>" value="no" />
                <?php
                    }
                    else
                    {
                    ?>
                        <textarea style="width:100%" rows="1" name="<?php echo "seguiment".$i ?>" id="<?php echo "seguiment".$i ?>"></textarea>
                        <input type="hidden" name="<?php echo "nou".$i ?>" value="si" />
                <?php
                    }
                ?>
                </td>
            </tr>
        <?php

        $i++;

		}
    }

    // hay $i - 1 nsuspesos
    $nsuspesos=$i;
    echo "<input type=hidden name=nsuspesos value=".$i." />";

    ?>
    <tr><td colspan="2" align="center" style="padding-top:5px">
        <input type="button" name="desar2" value="Desa sense enviar" onClick="comprovar(this.form,'Desa sense enviar')">
    </td></tr>
<?php
}
else
{
    $nsuspesos=0;
}
?>
