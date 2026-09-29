<tr class="apartat">
    <td>
    	DADES DEL CURS
    </td>
    <td>
    	(tutor / secretaria)
    </td>
</tr>
<tr>
    <td class="celda" width="700">                                
        <p align="justify"><strong>Tutor/a: </strong><?php echo $row['tnom']." ".$row['tcogs']; ?></p>
       </td>
    <td class="celda" width="300">
        <p align="justify"><strong>Alumnes inscrits:</strong> <?php echo $row['inscrits']; ?></p>
        </td>
</tr>
<tr>
    <td class="celda">
        <p align="justify"><strong>Curs:</strong> <?php echo $row['ncurs']." - Aula ".$row['AULA'] ;  ?></p>
        </td>
    <td class="celda">
        <p align="justify"><strong>Aprovats:</strong>
          <?php if ($row['dies_passats']>=0) {if ($row_m['superats']!='NULL') {echo $row_m['superats'];}}; if ($row['dies_passats']>1) {if(($row_m['superats']-$row['aprovats'])<>0) {echo " / <span style=color:red>".$row['aprovats']."</span>";} else {echo " / ".$row['aprovats'];}}; ?><input type="hidden" name="diferencia_aprovats" value="<?php echo ($row['aprovats']-$row_m['superats']); ?>"></p>

  	</td>
</tr>
<tr>
    <td class="celda" valign="top">
        <p align="justify"><strong>Convocatòria:</strong> <?php echo $row['nmes']; ?></p>
    </td>
    <td class="celda">
        <p align="justify"><strong>No superats:</strong> <!-- els alumnes que no han participat mai, no surten com a no_superats, llavors hem de fer la resta amb els inscrits de la bdd i els superats -->
        	<?php if ($row['dies_passats']>=0) {echo ($row['inscrits']-$row_m['superats']);} if ($row['dies_passats']>1) {if((($row['inscrits']-$row_m['superats'])-$row['pendents'])<>0) {echo " / <span style=color:red>".$row['pendents']."</span>";} else {echo " / ".$row['pendents'];}}; ?><input type="hidden" name="diferencia_suspesos" value="<?php echo ($row['inscrits']-$row_m['superats']-$row['pendents']); ?>"></p>

	</td>
</tr>
