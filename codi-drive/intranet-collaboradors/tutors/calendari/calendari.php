<?php
error_reporting(-1);
require_once("./config.inc.php");

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
		$query="insert into calendaris (codi,data,text) values ('".$codi_curs."','".$_GET["data"]."','".strip_tags($_GET["text"])."')";
		mysql_select_db($dbname);
		if ($resultado=mysql_query($query)) echo "<p class='ok'>Tasca guardada correctament.</p>";
		else echo "<p class='error'>Hi ha hagut un error en afegir la tasca.</p>";
		break;
	}
	case "editar_evento":
	{
		$query="update calendaris set text='".strip_tags($_GET["evento"])."' where id='".$_GET["id"]."'";
		mysql_select_db($dbname);
		if ($resultado=mysql_query($query)) echo "<p class='ok'>Tasca modificada correctament.</p>";
		else echo "<p class='error'>Hi ha hagut un error en modificar la tasca.</p>";
		break;
	}
	case "borrar_evento":
	{
		$query="delete from calendaris where id='".$_GET["id"]."' limit 1";
		mysql_select_db($dbname);
		if ($resultado=mysql_query($query)) echo "<p class='ok'>Tasca eliminada correctament.</p>";
		else echo "<p class='error'>Hi ha hagut un error en eliminar la tasca.</p>";
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
			
		/* comprobamos si el año es bisiesto y creamos array de días */
		if (($fecha_calendario[0] % 4 == 0) && (($fecha_calendario[0] % 100 != 0) || ($fecha_calendario[0] % 400 == 0))) $dias=array("","31","29","31","30","31","30","31","31","30","31","30","31");
		else $dias=array("","31","28","31","30","31","30","31","31","30","31","30","31");
		
		$eventos=array();
		
		$query="select * from calendaris where codi='".$_GET['codi_curs']."' order by id";
		mysql_select_db($dbname);
		$resultado=mysql_query($query);
		if ($fila=mysql_fetch_array($resultado))
		{
			$posicion=0;
			do
			{
				$eventos[$posicion]["id"]=$fila["id"];
				$eventos[$posicion]["data"]=$fila["data"];
				$eventos[$posicion]["text"]=$fila["text"];				
				$posicion+=1;
			}
			while($fila=mysql_fetch_array($resultado));
		}
		
		$query="select * from festius_dates order by id";
		mysql_select_db($dbname);
		$resultado=mysql_query($query);
		if ($fila=mysql_fetch_array($resultado))
		{
			do
			{
				$eventos[$posicion]["id"]=$fila["id"];
				$eventos[$posicion]["data"]=$fila["data"];
				$eventos[$posicion]["text"]=$fila["text"];				
				$posicion+=1;
				/*$day = getdate($fila["data"]);
				if ($day[wday]==1) //dilluns
				{
					//creo una data, data-3 per l'avis festiu
					$day_avis_festiu = strtotime ('-1 day' , strtotime($fila["data"]));
					$day_avis_festiu = date ('Y-m-j' , $day_avis_festiu);
					$eventos[$posicion]["id"]=$fila["id"];
					$eventos[$posicion]["data"]=$day_avis_festiu;
					$eventos[$posicion]["text"]='&raquo; AV&Iacute;S FESTIU';
					$posicion++;
					$eventos[$posicion]["id"]=$fila["id"];
					$eventos[$posicion]["data"]=$fila["data"];
					$eventos[$posicion]["text"]="<p style='color:#FF0000; font-weight:bold; text-align:center'>FESTIU</p>";
					$posicion+=1;
				}
				else if ($day[wday]!=0 && $day[wday]!=6 && $day[wday]!=1) 
				{
					//creo una data, data-1 per l'avis festiu
					$day_avis_festiu = strtotime ('-1 day' , strtotime($fila["data"]));
					$day_avis_festiu = date ( 'Y-m-j' , $day_avis_festiu );
					
					$eventos[$posicion]["id"]=$fila["id"];
					$eventos[$posicion]["data"]=$day_avis_festiu;
					$eventos[$posicion]["text"]='&raquo; AV&Iacute;S FESTIU';
					$posicion++;
					$eventos[$posicion]["id"]=$fila["id"];
					$eventos[$posicion]["data"]=$fila["data"];
					$eventos[$posicion]["text"]="<p style='color:#FF0000; font-weight:bold; text-align:center'>FESTIU</p>";
					$posicion+=1;					
				}*/
			}
			while($fila=mysql_fetch_array($resultado));
		}
		
		$meses=array("","Gener","Febrer","Mar&ccedil;","Abril","Maig","Juny","Juliol","Agost","Setembre","Octubre","Novembre","Desembre");
		
		/* calculamos los días de la semana anterior al día 1 del mes en curso */
		$diasantes=$primeromes-1;
			
		/* los días totales de la tabla siempre serán máximo 42 (7 días x 6 filas máximo) */
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
						
						/* recorremos el array de eventos para mostrar los eventos del día de hoy */
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
			
			// *********** CANVIAR L'ENLLAÇ PEL CURS ACTUAL
			echo "<p><br><a href='./afegir_calendari.php?shortname=".$codi_curs."' style=\"text-decoration:none; color:#333333\">Afegir tasques</a><br></p>";
		break;
	}
}
?>