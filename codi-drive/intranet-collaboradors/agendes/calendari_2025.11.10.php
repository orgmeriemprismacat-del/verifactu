<?php
error_reporting(-1);
require('../../config.php');
include ('../ConnexioWeb.php'); //BD cursos
include ('../ConnexioMoodle.php'); //BD cursos
include ('../ConnexioMoodleAntic.php'); //BD cursos
include ('../ConnexioIntranet.php'); //BD cursos
include ('../Text.php');


$codi_curs = $_GET['codi_curs'];

function fecha ($valor)
{
	$timer = explode(" ",$valor);
	$fecha = explode("-",$timer[0]);
	$fechex = $fecha[2]."/".$fecha[1]."/".$fecha[0];
	return $fechex;
}

function buscar_en_array($fecha,$array)
{
	$total_eventos=count($array);
	for($e=0;$e<$total_eventos;$e++)
	{
		if ($array[$e]["data"]==$fecha) return true;
	}
}

switch ($_GET["accion"])
{
	case "guardar_evento":
	{
		$conWeb = new ConnexioWeb();
	    $conWeb->connectarBD();

		try {
			if ( $stmt = $conWeb->prepare( "INSERT INTO calendaris (codi,data,text) VALUES (?, ?, ?)" ) ) {
			  $stmt->bind_param('sss', $codi_curs, $data, $valor);
			  $data = $_GET["fecha"];
			  $valor = strip_tags($_GET["text"]);
		     $stmt->execute();
			  $msg = "<p class='ok'>Tasca guardada correctament.</p>";
		   }
		   else {
				$msg = "<p class='error'>Hi ha hagut un error guardant la tasca.</p>";
		   }
		}
		catch(Exception $e) {
			$msg = "<p class='error'>Hi ha hagut un error guardant la tasca.</p>";
		}

		$conWeb->desconectarBD();
		break;
	}
	case "editar_evento":
	{
		$conWeb = new ConnexioWeb();
	   $conWeb->connectarBD();

		try {
			if ( $stmt = $conWeb->prepare( "UPDATE calendaris SET text = ? WHERE id = ?" ) ) {
			  $stmt->bind_param('sd', $event, $id);
			  $event = strip_tags($_GET["evento"]);
			  $event = $_GET["id"];
		     $stmt->execute();
			  $msg = "<p class='ok'>Tasca guardada correctament.</p>";
		   }
		   else {
				$msg = "<p class='error'>Hi ha hagut un error guardant la tasca.</p>";
		   }
		}
		catch(Exception $e) {
			$msg = "<p class='error'>Hi ha hagut un error guardant la tasca.</p>";
		}

		$conWeb->desconectarBD();
		break;
	}
	case "borrar_evento":
	{
		try {
			$conWeb = new ConnexioWeb();
		   $conWeb->connectarBD();

			if ( $stmt = $conWeb->prepare( "DELETE FROM calendaris WHERE id=? LIMIT 1" ) ) {
			  $stmt->bind_param('d', $id);
			  $id = $_GET["id"];
		     $stmt->execute();
			  $msg = "<p class='ok'>Tasca eliminada correctament.</p>";
		   }
		   else {
		     $msg = "<p class='error'>Hi ha hagut un error guardant la tasca.</p>";
		   }
			$conWeb->desconectarBD();
		}
		catch(Exception $e) {
			$msg = "<p class='error'>Hi ha hagut un error guardant la tasca.</p>";
		}
		break;
	}
	case "generar_calendario":
	{
		$fecha_calendario=array();
		if ($_GET["mes"]=="" || $_GET["anio"]=="")
		{
			$fecha_calendario[1]=intval(date("m"));
			if ($fecha_calendario[1]<10) $fecha_calendario[1]="0".$fecha_calendario[1];
			$fecha_calendario[0]=date("Y");
		}
		else
		{
			$fecha_calendario[1]=intval($_GET["mes"]);
			if ($fecha_calendario[1]<10) $fecha_calendario[1]="0".$fecha_calendario[1];
			else $fecha_calendario[1]=$fecha_calendario[1];
			$fecha_calendario[0]=$_GET["anio"];
		}
		$fecha_calendario[2]="01";

		/* obtenemos el dia de la semana del 1 del mes actual */
		$primeromes=date("N",mktime(0,0,0,$fecha_calendario[1],1,$fecha_calendario[0]));

		/* comprobamos si el a�o es bisiesto y creamos array de d�as */
		if (($fecha_calendario[0] % 4 == 0) && (($fecha_calendario[0] % 100 != 0) || ($fecha_calendario[0] % 400 == 0))) $dias=array("","31","29","31","30","31","30","31","31","30","31","30","31");
		else $dias=array("","31","28","31","30","31","30","31","31","30","31","30","31");

		$eventos=array();

		$conWeb = new ConnexioWeb();
	   $conWeb->connectarBD();

	   if ( $stmt = $conWeb->prepare( "SELECT ID, DATA, TEXT FROM calendaris WHERE codi=? ORDER BY ID" ) ) {
		  $stmt->bind_param('s', $codi_curs);
	     $stmt->execute();
	     $stmt->store_result();
	     if ( $stmt->num_rows() > 0 ) {
	       $stmt->bind_result($id, $data, $text);
			 $posicion=0;
	       while ( $stmt->fetch() ) {
				 $eventos[$posicion]["id"]=$id;
				 $eventos[$posicion]["data"]=$data;
				 $eventos[$posicion]["text"]=$text;
				 $posicion+=1;
			 }
	     }
	   }
	   else {
	     throw new Exception('', 21103);
	   }
		$conWeb->closeStmt();

	   if ( $stmt = $conWeb->prepare( "SELECT ID, DATA, TEXT FROM festius_dates ORDER BY ID" ) ) {
	     $stmt->execute();
	     $stmt->store_result();
	     if ( $stmt->num_rows() > 0 ) {
			  $stmt->bind_result($id, $data, $text);
	       while ( $stmt->fetch() ) {
				$eventos[$posicion]["id"] = $id;
				$eventos[$posicion]["data"] = $data;
				$eventos[$posicion]["text"] = $text;
				$posicion+=1;
			 }
	     }
	   }
	   else {
	     throw new Exception('', 21104);
	   }
		$conWeb->closeStmt();

	   $conWeb->desconectarBD();

		$meses=array("","Gener","Febrer","Mar&ccedil;","Abril","Maig","Juny","Juliol","Agost","Setembre","Octubre","Novembre","Desembre");

		/* calculamos los d�as de la semana anterior al d�a 1 del mes en curso */
		$diasantes=$primeromes-1;

		/* los d�as totales de la tabla siempre ser�n m�ximo 42 (7 d�as x 6 filas m�ximo) */
		$diasdespues=42;

		/* calculamos las filas de la tabla */
		$tope=$dias[intval($fecha_calendario[1])]+$diasantes;
		if ($tope%7!=0) $totalfilas=intval(($tope/7)+1);
		else $totalfilas=intval(($tope/7));

		/* empezamos a pintar la tabla */
		//echo "<h2>Calendari de tasques per a: ".$meses[intval($fecha_calendario[1])]." del ".$fecha_calendario[0]."</h2>";
		if (isset($mostrar)) echo $mostrar;

		echo "<table class='calendario' cellspacing='0' cellpadding='0'>";
			echo "<tr><th colspan=7><span class=cap>".$_GET['codi_curs'].": ".$meses[intval($fecha_calendario[1])]." del ".$fecha_calendario[0]."</span></th></tr><tr>";
			echo "<tr><th>Dilluns</th><th>Dimarts</th><th>Dimecres</th><th>Dijous</th><th>Divendres</th><th>Dissabte</th><th>Diumenge</th></tr><tr>";

			/* inicializamos filas de la tabla */
			$tr=0;
			$dia=1;

			for ($i=1;$i<=$diasdespues;$i++)
			{
				if ($tr<$totalfilas)
				{
					if ($i>=$primeromes && $i<=$tope)
					{
						echo "<td class='";
						/* creamos fecha completa */
						if ($dia<10) $dia_actual="0".$dia; else $dia_actual=$dia;
						$fecha_completa=$fecha_calendario[0]."-".$fecha_calendario[1]."-".$dia_actual;

						if (count($eventos)>0 && buscar_en_array($fecha_completa,$eventos)==true) echo "evento";

						/* si es hoy coloreamos la celda */
						if (date("Y-m-d")==$fecha_completa) echo " hoy";

						echo "'>";

						/* recorremos el array de eventos para mostrar los eventos del d�a de hoy */
						$total_eventos=count($eventos);
						$eventos_del_dia="";

						if ($eventos_del_dia!="")
						{
							echo "<a href='#' data-evento='#evento".$dia_actual."' class='modal' rel='".$fecha_completa."'>".$dia."</a>";
						}
						else echo "<span class=colordia>$dia</span><br>";



						for($e=0;$e<$total_eventos;$e++)
						{
							if ($eventos[$e]["data"]==$fecha_completa)
							{
								echo "<div style=text-align:left>".$eventos[$e]["text"]."</div>";
							}
						}

						echo "</td>";
						$dia+=1;
					}
					else echo "<td class='desactivada'>&nbsp;</td>";
					if ($i==7 || $i==14 || $i==21 || $i==28 || $i==35 || $i==42) {echo "<tr>";$tr+=1;}
				}
			}
			echo "</table>";

			$mesanterior=date("Y-m-d",mktime(0,0,0,$fecha_calendario[1]-1,01,$fecha_calendario[0]));
			$messiguiente=date("Y-m-d",mktime(0,0,0,$fecha_calendario[1]+1,01,$fecha_calendario[0]));
			echo "<p>&laquo; <a href='#' rel='$mesanterior' class='anterior'>Mes anterior</a> - <a href='#' class='siguiente' rel='$messiguiente'>Mes seg&uuml;ent</a> &raquo;</p>";

			// *********** CANVIAR L'ENLLA� PEL CURS ACTUAL
			echo "<p><br><a href='./afegir_calendari.php?shortname=".$codi_curs."' style=\"text-decoration:none; color:#333333\">Afegir tasques</a><br></p>";
		break;
	}
}
?>
