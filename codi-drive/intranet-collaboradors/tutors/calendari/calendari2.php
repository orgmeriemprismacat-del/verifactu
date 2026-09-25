<?php
error_reporting(-1);
require_once("./config.inc2.php");

$codi_curs = $_GET['codi_curs'];
echo "CALENDARI2: ".$codi_curs;

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
		if ($array[$e]["fecha"]==$fecha) return true;
	}
}

switch ($_GET["accion"])
{
	case "listar_evento":
	{
		$query=$db->query("select * from ".$tabla." where codi='".$codi_curs."' and data='".$_GET["fecha"]."' order by id asc");
		if ($fila=$query->fetch_array())
		{
			do
			{
				echo "<p>".$fila["text"]."</p>";
			}
			while($fila=$query->fetch_array());
		}
		break;
	}
	case "guardar_evento":
	{
		$query=$db->query("insert into ".$tabla." (codi,data,text) values ('".$codi_curs."','".$_GET["fecha"]."','<font color=#3c7f14>".strip_tags($_GET["evento"])."</font>')");
		if ($query) echo "<p class='ok'>Tasca guardada correctament.</p>";
		else echo "<p class='error'>Hi ha hagut un error guardant la tasca.</p>";
		break;
	}
	case "borrar_evento":
	{
		$query=$db->query("delete from ".$tabla." where id=".$_GET["id"]." limit 1");
		if ($query) echo "<p class='ok'>Tasca eliminada correctament.</p>";
		else echo "<p class='error'>Hi ha hagut un error guardant la tasca.</p>";
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
		$query=$db->query("select data,count(id) as total from ".$tabla." where codi='".$codi_curs."' group by data");
				
		if ($fila=$query->fetch_array())
		{
			do
			{
				$eventos[$fila["data"]]=$fila["total"];				
			}
			while($fila=$query->fetch_array());
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
		if (isset($mostrar)) echo $mostrar;
			
			echo "<table class='calendario' cellspacing='0' cellpadding='0'>";
			echo "<tr><th colspan=7><span class=cap>".$codi_curs.": ".$meses[intval($fecha_calendario[1])]." del ".$fecha_calendario[0]."</span></th></tr><tr>";
			echo "<tr><th>Dilluns</th><th>Dimarts</th><th>Dimecres</th><th>Dijous</th><th>Divendres</th><th>Dissabte</th><th>Diumenge</th></tr><tr>";
			
			/* inicializamos filas de la tabla */
			$tr=0;
			$dia=1;
			
			function es_finde($fecha)
			{
				$cortamos=explode("-",$fecha);
				$dia=$cortamos[2];
				$mes=$cortamos[1];
				$ano=$cortamos[0];
				$fue=date("w",mktime(0,0,0,$mes,$dia,$ano));
				if (intval($fue)==0 || intval($fue)==6) return true;
				else return false;
			}
			
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
						
						if (intval($eventos[$fecha_completa])>0) 
						{
							echo "evento";
							$hayevento=$eventos[$fecha_completa];
						}
						else $hayevento=0;
						
						/* si es hoy coloreamos la celda */
						if (date("Y-m-d")==$fecha_completa) echo " hoy";
						
						echo "'>";
						
						/* recorremos el array de eventos para mostrar los eventos del día de hoy */
						if ($hayevento>0) echo "<a href='#' data-evento='#evento".$dia_actual."' class='modal' rel='".$fecha_completa."' title='Tasques: ".$hayevento."'>".$dia."</a>";
						else echo "$dia";
						
						/* agregamos enlace a nuevo evento si la fecha no ha pasado */
						if (date("Y-m-d")<=$fecha_completa && es_finde($fecha_completa)==false) echo "<a href='#' data-evento='#nuevo_evento' title='Afegir una tasca el ".fecha($fecha_completa)."' class='add agregar_evento' rel='".$fecha_completa."'>&nbsp;</a>";
						
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
			echo "<p><br><a href='./agenda.php?shortname=".substr($codi_curs, 0, -1)."a"."' style=\"text-decoration:none; color:#333333\">Calendari ampliat</a></p>";
		break;
	}
}
?>