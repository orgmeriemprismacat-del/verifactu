<?php
$h = $_GET['h'];
$c = $_GET['c'];
$dte =  $_GET['dte'];
$mes_dte =  $_GET['mes_dte'];

	$conexion = mysqli_connect('localhost','suport','1324GiRoNa','gestio');
	if (mysqli_connect_errno())
	{
		echo "No es pot connectar: " . mysqli_connect_error();
	}
	mysqli_set_charset($conexion, "utf8");

	if ($h==100) // per als cursos de 100h donem 2 setmanes més
		$result_curs = mysqli_query ($conexion, "SELECT DISTINCT MES, ANY, DATEDIFF(CURRENT_DATE,`DATA INICI`), Data_llarga_inici AS datai, Data_llarga_Fin AS dataf FROM cursos WHERE CURS='".$c."' AND `DNI_TUTOR` <> '0' AND DATEDIFF(CURRENT_DATE,`DATA INICI`) <= 14 AND `Data Resol` IS NOT NULL ORDER BY ANY, MES LIMIT 3");
		//TANQUEM MÉS TARD
		//$result_curs = mysqli_query ($conexion, "SELECT DISTINCT MES, ANY, DATEDIFF(CURRENT_DATE,`DATA INICI`), Data_llarga_inici AS datai, Data_llarga_Fin AS dataf FROM cursos WHERE CURS='".$c."' AND `DNI_TUTOR` <> '0' AND DATEDIFF(CURRENT_DATE,`DATA INICI`) <= 21 AND `Data Resol` IS NOT NULL ORDER BY ANY, MES LIMIT 8");
	else // per a la resta donem 1 setmana més
		$result_curs = mysqli_query ($conexion, "SELECT DISTINCT MES, ANY, DATEDIFF(CURRENT_DATE,`DATA INICI`), Data_llarga_inici AS datai, Data_llarga_Fin AS dataf FROM cursos WHERE CURS='".$c."' AND `DNI_TUTOR` <> '0' AND DATEDIFF(CURRENT_DATE,`DATA INICI`) <= 7 AND `Data Resol` IS NOT NULL ORDER BY ANY, MES LIMIT 3");
		//TANQUEM MÉS TARD
		//$result_curs = mysqli_query ($conexion, "SELECT DISTINCT MES, ANY, DATEDIFF(CURRENT_DATE,`DATA INICI`), Data_llarga_inici AS datai, Data_llarga_Fin AS dataf FROM cursos WHERE CURS='".$c."' AND `DNI_TUTOR` <> '0' AND DATEDIFF(CURRENT_DATE,`DATA INICI`) <= 14 AND `Data Resol` IS NOT NULL ORDER BY ANY, MES LIMIT 8");

		$numdates = mysqli_num_rows($result_curs);
	?>

	<select id="dates" name="dates" class="normal" onChange="passar_dates(this.options[this.selectedIndex].innerHTML);validacio_carnet_jove(document.f1.nif.value, document.f1.llnif.value, <?php echo "'".$c."'" ?>,document.f1.dates.value, <?php echo $dte ?>,  <?php echo $mes_dte ?>); validacio_exalumne(document.f1.nif.value,document.f1.llnif.value, <?php echo "'".$c."'" ?>,document.f1.dates.value, <?php echo $dte ?>,  <?php echo $mes_dte ?>);">
         <option value="Cap" selected>-- Tria l'edició a què et vols inscriure --</option>
	<?php


	for ($n=0; $n<$numdates; $n++)
	{
		$row = mysqli_fetch_array($result_curs);

		// per cursos tancats abans d'hora
		$cursos_tancats = (($c=='TUT' && $row['MES']=='08' && $row['ANY']==2020) or ($c=='TUT' && $row['MES']=='07' && $row['ANY']==2020) or ($c=='ART' && $row['MES']=='07' && $row['ANY']==2020) or ($c=='ACRE' && $row['MES']=='07' && $row['ANY']==2020) or ($c=='LING' && $row['MES']=='07' && $row['ANY']==2020) or ($c=='AUDOC' && $row['MES']=='07' && $row['ANY']==2020) or ($c=='DOL' && $row['MES']=='08' && $row['ANY']==2020) or ($c=='CEMI' && $row['MES']=='08' && $row['ANY']==2020) or ($c=='NEURO' && $row['MES']=='09' && $row['ANY']==2020));

		if (!$cursos_tancats)
			echo "<option value=".$row['ANY']."/".$row['MES'].">Del dia ".$row['datai']." al dia ".$row['dataf']."</option>";
	}

	?>
    </select>
    <?php

	mysqli_close($conexion);

?>
