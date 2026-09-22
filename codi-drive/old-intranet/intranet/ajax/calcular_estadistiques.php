<?php
/* AQUEST FITXER S'UTILITZA A:
/intranet/js/estadistiques.js  */

$servername = "localhost";
$username = "suport";
$password = "1324GiRoNa";
$dbname = "gestio";

$any_actual = date(Y);
$mes_actual = date(n); // representació numèrica
$curs = $_REQUEST['curs'];

$conn = mysqli_connect($servername, $username, $password, $dbname);
if (mysqli_connect_errno())  
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($conn, "utf8");

$html_cursos .= "<div class=titol><strong>RESUM GENERAL</strong></div>";	
$html_cursos .= "<table class=\"table table-hover\" id=\"taula_cursos\">
<thead class=\"thead-light\">
	<tr>
		<th scope=\"col\"></th>
		<th scope=\"col\">01</th>
		<th scope=\"col\">02</th>
		<th scope=\"col\">03</th>
		<th scope=\"col\">04</th>
		<th scope=\"col\">05</th>
		<th scope=\"col\">06</th>
		<th scope=\"col\">07</th>
		<th scope=\"col\">08</th>
		<th scope=\"col\">09</th>
		<th scope=\"col\">10</th>
		<th scope=\"col\">11</th>
		<th scope=\"col\">12</th>
		<th scope=\"col\">TOTAL</th>
	</tr>
</thead>
<tbody>"; 

$sql_general = "SELECT i.ANY, i.MES, SUM(CASE WHEN (`INSC CURS`=1) THEN 1 ELSE 0 END) INSC, SUM(CASE WHEN (`INSC CURS`=1) THEN A_PAGAR ELSE 0 END) INGRESSOS, HORES,  c.id_cuho FROM inscripcions AS i, cursos AS c, honoraris AS h WHERE i.CURS='".$curs."' AND ((i.ANY<=".$any_actual." AND CONVERT(i.MES, UNSIGNED)<".$mes_actual.") OR (i.ANY<".$any_actual." AND CONVERT(i.MES, UNSIGNED)>=".$mes_actual.")) AND i.ANY=c.ANY AND i.MES=c.MES AND i.CURS=c.CURS AND Grup=AULA AND h.ID=c.id_cuho GROUP BY i.ANY, i.MES ORDER BY i.ANY DESC"; 

$result_general = mysqli_query ($conn, $sql_general);
$row = mysqli_fetch_array($result_general);
$hores = $row['HORES'];

//Per cada any mostrem les dades per mesos
for ($n=0; $n<mysqli_num_rows($result_general); $n++) 
{
	$html_cursos .= "<tr style=\"text-align: center;\">";
	
	$html_cursos .= "<td id=\"any".$n."\"><strong>".$row['ANY']."</strong></td>";
	
	$any = $row['ANY'];
	$continuar = true;
	$net_suma = 0;

	for ($i=1; $i<13 && $continuar; $i++)
	{ 
		if ($any==$row['ANY'])
		{
			if (intval($row['MES']) == $i)
			{			
				if ($row['id_cuho'] == 13) // curs anul·lat
					$html_cursos .= "<td id=\"insc".$i."\" style=\"color:#FF0000;\">A</td>";
				else 
				{
					// fem el càlcul de les despeses
					$sql_despeses = "SELECT PREU_ALUMNE, PERFIL FROM honoraris AS h, rel_cuho AS ch WHERE id_cuho=".$row['id_cuho'] ." AND h.ID=ch.id_hono";
					
					if ($row['INSC'] < 15) // el tutor cobra 15 alumnes com a mínim
						$insc = 15;
					else
						$insc = $row['INSC'];
					
					$result_despeses = mysqli_query ($conn, $sql_despeses);
					
					$despeses = 0; // inicialitzem sempre les despeses
					
					// sumem les despeses del tutor i autors i coordinadors (si n'hi ha)
					for ($r=0; $r<mysqli_num_rows($result_despeses); $r++) 
					{
						$rowdes = mysqli_fetch_array($result_despeses);
						
						if ($rowdes['PERFIL'] == 'tutor')
							$despeses = $despeses + $insc*$rowdes['PREU_ALUMNE'];
						else
							$despeses = $despeses + $row['INSC']*$rowdes['PREU_ALUMNE'];
					}	
					
					$net = $row['INGRESSOS']-$despeses;
					$net_suma = $net_suma + $net;
				
					$h = "";
					if ($row['HORES'] <> $hores)
						$h = "*";
					$html_cursos .= "<td id=\"insc".$i."\">".$row['INSC']." / ".number_format($net, 0, ",", ".")." &euro;".$h."</td>";
				}	
				
				$row = mysqli_fetch_array($result_general);
				$n++;
			}
			else
			{
				if ($any >= $any_actual && $i >= intval($mes_actual))
					$html_cursos .= "<td id=\"insc".$i."\"></td>";  // encara no es pot calcular
				else
					$html_cursos .= "<td id=\"insc".$i."\">-</td>";  // curs no oferit				
			}
		}
		else
		{
			for ($i=$i; $i<13; $i++)
			{
				if ($any >= $any_actual && $i >= intval($mes_actual))
					$html_cursos .= "<td id=\"insc".$i."\"></td>";  // encara no es pot calcular
				else
					$html_cursos .= "<td id=\"insc".$i."\">-</td>";  // curs no oferit	
			}
		}
	}
	
	$n--;
		
	$html_cursos .= "<td style=font-weight:bold>".number_format($net_suma, 0, ",", ".")." &euro;</td>";	
	$html_cursos .= "</tr>";		
}
	
$html_cursos .= "</tbody></table>";

if ($h == "*")
{
	$html_cursos .="<p style=text-align:left>* Edici&oacute; amb diferent durada que l'actual</p>";
}

	$sql_mesos = "SELECT i.ANY, i.MES, SUM(CASE WHEN (UPPER(`INSC CURS`)!='D') THEN 1 ELSE 0 END) SOL, SUM(CASE WHEN (`INSC CURS`=1) THEN 1 ELSE 0 END) INSC, SUM(CASE WHEN (`INSC CURS`='X' OR `INSC CURS`='C') THEN 1 ELSE 0 END) BAIXES, SUM(CASE WHEN (`INSC CURS`=1) THEN A_PAGAR ELSE 0 END) INGRESSOS, HORES, c.id_cuho FROM inscripcions AS i, cursos AS c, honoraris AS h WHERE i.CURS='".$curs."' AND ((i.ANY<=".$any_actual." AND CONVERT(i.MES, UNSIGNED)<".$mes_actual.") OR (i.ANY<".$any_actual." AND CONVERT(i.MES, UNSIGNED)>=".$mes_actual.")) AND i.ANY=c.ANY AND i.MES=c.MES AND i.CURS=c.CURS AND Grup=AULA AND h.ID=c.id_cuho GROUP BY i.MES, i.ANY ORDER BY i.MES ASC, i.ANY DESC";
	
	$sql_anys = "SELECT DISTINCT ANY FROM cursos WHERE CURS='".$curs."' AND ((ANY<=".$any_actual." AND CONVERT(MES, UNSIGNED)<".$mes_actual.") OR (ANY<".$any_actual." AND CONVERT(MES, UNSIGNED)>=".$mes_actual.")) ORDER BY ANY DESC";
	
	$sql_nommes = "SELECT nom FROM mesos ORDER BY id";
		
$result_mesos = mysqli_query ($conn, $sql_mesos);

$rowm = mysqli_fetch_array($result_mesos);
$horesm = $rowm['HORES'];

$result_nommes = mysqli_query ($conn, $sql_nommes);

for ($j=1; $j<13; $j++)
{			
	$rown = mysqli_fetch_array($result_nommes);
		
	$result_anys = mysqli_query ($conn, $sql_anys);
	$rowa = mysqli_fetch_array($result_anys);
	$any = $rowa['ANY'];
	
	$html_cursos .= "<div class=titol><strong>".mb_strtoupper($rown['nom'],'utf-8')."</strong></div>";	
	$html_cursos .= "<table class=\"table table-hover\" id=\"taula_cursos".$j."\">";	
	$html_cursos .= "<thead class=\"thead-light\">
		<tr>
			<th scope=\"col\" style=\"width:70px\">ANY</th>
			<th scope=\"col\" style=\"width:70px\">SOL&middot;LICITUDS</th>
			<th scope=\"col\" style=\"width:70px\">BAIXES</th>
			<th scope=\"col\" style=\"width:70px\">INSCRITS</th>
			<th scope=\"col\" style=\"width:70px\">INGRESSOS</th>
			<th scope=\"col\" style=\"width:70px\">TUTORS</th>
			<th scope=\"col\" style=\"width:70px\">AUT/COOR</th>
			<th scope=\"col\" style=\"width:70px\">GUANYS NETS</th>			
		</tr>
	</thead>
	<tbody>";
				
	for ($a=0; $a<mysqli_num_rows($result_anys); $a++)
	{	
		if ((intval($rowm['MES']) == $j) && ($rowm['ANY'] == $any)) // coincideixen any i mes
		{ 
			if ($rowm['INSC'] == 0) // curs anul·lat
			{
				$html_cursos .= "<tr style=\"text-align: center;\">";
				$html_cursos .= "<td id=\"fila".$j."\">".$rowa['ANY']."</td>";
				$html_cursos .= "<td id=\"fila".$j."\" style=\"color:#FF0000;\" colspan=\"7\">EDICI&Oacute; ANUL&middot;LADA</td>";
				$html_cursos .= "</tr>";
			}
			else 
			{
				$html_cursos .= "<tr style=\"text-align: center;\">";
				$html_cursos .= "<td id=\"fila".$j."\">".$rowm['ANY']."</td>";
				$html_cursos .= "<td id=\"fila".$j."\">".$rowm['SOL']."</td>";
				$html_cursos .= "<td id=\"fila".$j."\">".$rowm['BAIXES']."</td>";
				$html_cursos .= "<td id=\"fila".$j."\">".$rowm['INSC']."</td>";
				$html_cursos .= "<td id=\"fila".$j."\">".number_format($rowm['INGRESSOS'], 0, ",", ".")." &euro;</td>";
								
				// fem el càlcul de les despeses
				$sql_despesesm = "SELECT PREU_ALUMNE, PERFIL FROM honoraris AS h, rel_cuho AS ch WHERE id_cuho=".$rowm['id_cuho'] ." AND h.ID=ch.id_hono";
				
				if ($rowm['INSC'] < 15) // el tutor cobra 15 alumnes com a mínim
					$inscm = 15;
				else
					$inscm = $rowm['INSC'];
				
				$result_despesesm = mysqli_query ($conn, $sql_despesesm);
				
				$despesestutor = 0; // inicialitzem sempre les despeses
				$despesesduo = 0; // inicialitzem sempre les despeses
				
				// sumem les despeses del tutor i autors i coordinadors (si n'hi ha)
				for ($x=0; $x<mysqli_num_rows($result_despesesm); $x++) 
				{
					$rowdesm = mysqli_fetch_array($result_despesesm);
					
					if ($rowdesm['PERFIL'] == 'tutor')
						$despesestutor = $despesestutor + $inscm*$rowdesm['PREU_ALUMNE'];
					else
						$despesesduo = $despesesduo + $rowm['INSC']*$rowdesm['PREU_ALUMNE'];
				}	
				
				$netm = $rowm['INGRESSOS']-$despesestutor-$despesesduo;
			
				$hm = "";
				if ($rowm['HORES'] <> $horesm)
					$hm = "*";
								
				$html_cursos .= "<td id=\"fila".$j."\">".number_format($despesestutor, 0, ",", ".")." &euro;</td>";
				$html_cursos .= "<td id=\"fila".$j."\">".number_format($despesesduo, 0, ",", ".")." &euro;</td>";
				$html_cursos .= "<td id=\"insc".$j."\">".number_format($netm, 0, ",", ".").$hm." &euro;</td>";				
								
				$html_cursos .= "</tr>";
				
			}			
			$rowm = mysqli_fetch_array($result_mesos);
		}
		else // el curs no es va oferir
		{
			if ($any >= $any_actual && $j >= intval($mes_actual))
			{
				$html_cursos .= "<tr style=\"text-align: center;\">";
				$html_cursos .= "<td id=\"fila".$j."\">".$rowa['ANY']."</td>";	
				$html_cursos .= "<td id=\"insc".$i."\" colspan=\"7\"></td>";  // encara no es pot calcular
				$html_cursos .= "</tr>";
			}	
			else
			{
				$html_cursos .= "<tr style=\"text-align: center;\">";
				$html_cursos .= "<td id=\"fila".$j."\">".$rowa['ANY']."</td>";
				$html_cursos .= "<td id=\"insc".$j."\" colspan=\"7\">SENSE EDICI&Oacute;</td>";
				$html_cursos .= "</tr>";
			}
		}
		$rowa = mysqli_fetch_array($result_anys);
		$any = $rowa['ANY'];
	}	
		
	$html_cursos .= "</tr>";		
	$html_cursos .= "</tbody></table>";
}


echo $html_cursos;
mysqli_close($conn);
?>
