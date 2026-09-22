<?php
/* AQUEST FITXER ÉS UITLITZAT PER:
/intranet/js/revisions.js  */

include('../inc/dades.php');

$any = $_REQUEST['any'];
$mes = $_REQUEST['mes'];

$conn = mysqli_connect('localhost',$usuari,$pw,$bbdd);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($conn, "utf8");

$html_cursos = "<table class=\"table table-hover\" id=\"taula_cursos\">
<thead class=\"thead-light\">
	<tr>
	  <th scope=\"col\">ANY</th>
	  <th scope=\"col\">CURS</th>
	  <th scope=\"col\">MES</th>
	  <th scope=\"col\">AULA</th>
	  <th scope=\"col\" style=\"text-align: left\">NOM CURS</th>
	  <th scope=\"col\" style=\"text-align: left\">TUTOR</th>
	  <th scope=\"col\">DATA</th>
	  <th scope=\"col\">REVISIÓ</th>
	  <th scope=\"col\">INCIDÈNCIES</th>
	</tr>
</thead>
<tbody>";

//$sql_curs = "SELECT DISTINCT ANY, MES, CURS, AULA, `NOM CURS`AS NOM, DNI_TUTOR, HORES FROM cursos WHERE ANY=".$any." and MES=".$mes." and DNI_TUTOR<>0 ORDER BY ".$order."";
$sql_curs = "SELECT DISTINCT ANY, MES, CURS, AULA, `NOM CURS`AS NOM_CURS, DNI_TUTOR, HORES, NOM, COGNOMS, `DATA INICI` AS datai FROM cursos, personal WHERE cursos.dni_tutor=personal.dni and ANY=".$any." and MES=".$mes." and DNI_TUTOR<>'0' ORDER BY id_Curs";

$result_cursos = mysqli_query ($conn, $sql_curs);

//Per cada curs mostrem les dades
for ($n=0; $n<mysqli_num_rows($result_cursos); $n++) {
	$row = mysqli_fetch_array($result_cursos);

	$dni_tutor = $row['DNI_TUTOR'];
	$shortname = $row['ANY'].$row['CURS'].$row['MES'].$row['AULA'];

	$nom_cognoms_tutor = $row['NOM']." ".$row['COGNOMS'];

	$sql_revisio = "SELECT finalitzat, incidencies, incidencies_corregides, INC_GENERAL, INC_LECTURES, INC_MEDIATECA, INC_BIBLIO, INC_ALTRES FROM revisio_tutor WHERE codic='$shortname'";
	$result_revisio = mysqli_query ($conn, $sql_revisio);
	$row_revisio = mysqli_fetch_array($result_revisio);

	$data_f = date_create($row_revisio['finalitzat']);
	$data = date_format($data_f,"d-m-Y");

	$data_i = date_create($row['datai']);
	$datai = date_format($data_i,"d-m-Y");

	$interval = date_diff($data_i, $data_f);
	$danger = $interval->format('%R%a');

	$interval=0;
	if ($row_revisio['finalitzat']!="") //s'ha revisat
	{
		//Si s'ha revisat, posarem la data de revisió
		$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/".$row['HORES']."_hores.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		
		if ($row['HORES']==15 && $row['CURS']=="SUI") {
			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/sui.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		}
		else if ($row['HORES']==30 && $row['CURS']=="EINES") {
			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/eines.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		}
		else if ($row['HORES']==30 && $row['CURS']=="ACO") {
			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/aco.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		}
		else if ($row['HORES']==40 && $row['CURS']=="CAT") {
			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/cat.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		}
		else if ($row['HORES']==50 && $row['CURS']=="TICS") {
			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/tics.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		}
		else if ($row['HORES']==60 && $row['CURS']=="DFD") {
			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/dfd.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		}
		else if ($row['HORES']==70 && $row['CURS']=="ACOS") {
			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/acos.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
		}				
		else if ($row['HORES']==100 && ($row['CURS']=="CLEE" || $row['CURS']=="LOGO")) {
		  $estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/".$row['HORES']."_hores_2.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
	  }
	  else if ($row['HORES']==100 && $row['CURS']=="HTP") {
		  $estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/htp.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
	  }

	  $teIncidenca = 0;
	  if ( $row_revisio['incidencies'] != null && $row_revisio['incidencies'] != '' ) {
	    if ($row['HORES']==15 && $row['CURS']=="SUI")
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
		else if ($row['HORES']==30)
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
  		else if ($row['HORES']==40 && $row['CURS']=="CAT")
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
  		else if ($row['HORES']==40 && $row['CURS']!="CAT")
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
  		else if ($row['HORES']==60 && $row['CURS']!="DFD")
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
  		else if ($row['HORES']==60 && $row['CURS']=="DFD") {
  			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/dfd.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
  		}
  		else if ($row['HORES']==100 && $row['CURS']!="CLEE" && $row['CURS']!="LOGO" && $row['CURS']!="HTP")
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
  		else if ($row['HORES']==100 && ($row['CURS']=="CLEE" || $row['CURS']=="LOGO")) {
  			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/".$row['HORES']."_hores_2.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
  		}
  		else if ($row['HORES']==100 && $row['CURS']=="HTP") {
  			$estat_revisio = "<a href=\"https://old.prisma.cat/intranet/revisions/htp.php?shortname=".$shortname."\" target=\"_blank\">OBRIR</button>";
  			$cap_incidencia = "#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap#Cap";
  		}
		if ($row_revisio['incidencies']!=$cap_incidencia)
			$teIncidenca = 1;
	  }
	  else {
		  $incGeneral = $row_revisio["INC_GENERAL"];
	     $incLectures = $row_revisio["INC_LECTURES"];
	     $incBiblio = $row_revisio["INC_BIBLIO"];
	     $incMediateca = $row_revisio["INC_MEDIATECA"];
	     $incAltres = $row_revisio["INC_ALTRES"];
	     $incidencies = $row_revisio["incidencies"];

		  $vectAux = explode( '[REV]', $incGeneral);
		  $i=1; $trobat = 0;
        while ( $i< count($vectAux) && !$trobat ) {
           $dada = $vectAux[$i];
           if ( $dada != "Cap" )
              $trobat = 1;
			  $i++;
        }

		  $vectAux = explode( '[REV]', $incLectures);
		  $i=1;
        while ( $i< count($vectAux) && !$trobat ) {
           $dada = $vectAux[$i];
           if ( $dada != "Cap" )
              $trobat = 1;
			  $i++;
        }

		  $vectAux = explode( '[REV]', $incBiblio);
		  $i=1;
        while ( $i< count($vectAux) && !$trobat ) {
           $dada = $vectAux[$i];
           if ( $dada != "Cap" )
              $trobat = 1;
			  $i++;
        }
		  $vectAux = explode( '[REV]', $incMediateca);
		  $i=1;
        while ( $i< count($vectAux) && !$trobat ) {
           $dada = $vectAux[$i];
           if ( $dada != "Cap" )
              $trobat = 1;
			  $i++;
        }

		  $vectAux = explode( '[REV]', $incAltres);
		  $i=1;
        while ( $i< count($vectAux) && !$trobat ) {
           $dada = $vectAux[$i];
           if ( $dada != "Cap" )
              $trobat = 1;
			  $i++;
        }

		  if ( $trobat ) $teIncidenca = 1;
		  else $teIncidenca = 0;
	  }


		/*Buscar la data inici del curs que estem buscant*/

		/*$sql_datai = "SELECT `DATA INICI` as datai FROM revisio_tutor WHERE codic='$shortname'";
		$result_datai = mysqli_query ($conn, $sql_datai);
		$row_datai = mysqli_fetch_array($result_datai);*/
		if ( $teIncidenca )  // si hi ha incidència
		{
			$estat_incidencies = $row_revisio['incidencies_corregides'];

			if ($estat_incidencies == 0) { //si està pendent, text en taronja
				$html_cursos .= "<tr style=\"color: #f68d0d;\">";
				$estat_incidencies = "Pendent";
			}
			else { //si no està pendent
				if($danger >= 0) {
					$html_cursos .= "<tr class=\"text-danger\">";
				}
				else {
					$html_cursos .= "<tr>";
				}

				if ($estat_incidencies == 1) {
					$estat_incidencies = "Resolt";
				}
				else if ($estat_incidencies == 2) {
					$estat_incidencies = "Falsa alarma";
				}
			}
		}
		else {
			if($danger >= 0) {
				$html_cursos .= "<tr class=\"text-danger\">";
			}
			else {
				$html_cursos .= "<tr>";
			}

			$estat_incidencies = "Cap";
			//$html_cursos .= "<tr>";
		}
	}
	else
	{
		//Si no s'ha revisat, posarem un guió
		$estat_revisio = "-";
		$estat_incidencies = "-";
		$html_cursos .= "<tr class=\"linia_desactivada\">";
	}

	$html_cursos .= "<td id=\"any".$n."\" \">".$row['ANY']."</td>";
	$html_cursos .= "<td id=\"mes".$n."\" \">".$row['MES']."</td>";
	$html_cursos .= "<td id=\"curs".$n."\" \">".$row['CURS']."</td>";
	$html_cursos .= "<td id=\"aula".$n."\" \">".$row['AULA']."</td>";
	$html_cursos .= "<td id=\"nom".$n."\"  style=\"text-align: left\" \">".$row['NOM_CURS']."</td>";
	$html_cursos .= "<td id=\"tutor".$n."\"  style=\"text-align: left\" \">".$nom_cognoms_tutor."</td>";
	$html_cursos .= "<td id=\"data".$n."\"  \">".$data."</td>";
	$html_cursos .= "<td id=\"revisio".$n."\" \">".$estat_revisio."</td>";
	$html_cursos .= "<td id=\"incidencies".$n."\" \">".$estat_incidencies."</td>";
	$html_cursos .= "</tr>";
}

$html_cursos .= "</tbody></table>";

echo $html_cursos;
mysqli_close($conn);
?>
