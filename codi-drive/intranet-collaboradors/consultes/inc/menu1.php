<?php

$primer = false; // per saber si deixar un espai inicial o no

$mes_actual = intval(date('m'));
$any_actual = intval(date('Y'));

// comprovem l'estat laboral

// dades connexi�
include('./inc/dades.php');

$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);

mysqli_set_charset ($connexio, "utf8");

if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les mol�sties.";
}

$result_jubilat = mysqli_query ($connexio,"SELECT e.ID FROM personal AS p, estat AS e WHERE p.DNI=e.DNI AND p.DNI LIKE '%".$USER->username."%' AND SITUACIO = 'Estic jubilat/da.'");

$excepcio = false; //no hi han excepcions

if (($USER->username == "36557520") || ($USER->username == "45171998") || ($USER->username == "77618906")) // cas empresa (N�ria Banal) i Pablo
{
	$excepcio = true;
}

if(mysqli_num_rows($result_jubilat)==0 && !$excepcio) // no hi ha cap registre de jubilaci�
{
 	$result_porta_curs = mysqli_query ($connexio,"SELECT MES FROM cursos WHERE DNI_TUTOR LIKE '%".$USER->username."%' AND ANY = ".$any_actual."");

	$row_pc = mysqli_fetch_array($result_porta_curs);
	$trobat = false;

 	if ($mes_actual <= 6) // (mesos 1-6)
	{
		for ($i=1; ($i<=mysqli_num_rows($result_porta_curs) && (!$trobat)); $i++)
		{
			$porta = intval($row_pc['MES']);

			if ($porta>=1 && $porta<7)
				$trobat = true;

			$row_pc = mysqli_fetch_array($result_porta_curs);
		}
 	}
	else
	{
		for ($i=1; ($i<=mysqli_num_rows($result_porta_curs) && (!$trobat)); $i++)
		{
			$porta = intval($row_pc['MES']);

			if ($porta>=7 && $porta<=12)
				$trobat = true;

			$row_pc = mysqli_fetch_array($result_porta_curs);
		}
	}

 	if ($trobat) // t� assignat un curs en l'actual semestre
	{
		if ($mes_actual <= 6) // (mesos 1-6)
		{
			// mirem el primer semestre de l'any actual i comprovem que hagi tutoritzat algun curs
			//$result_menu = mysqli_query ($connexio,"SELECT e.ID FROM personal AS p, estat AS e, cursos AS c WHERE p.DNI=e.DNI AND p.DNI LIKE '%".$USER->username."%' AND (DATA BETWEEN '".$any_actual."-01-01' AND '".$any_actual."-06-30') AND ((CAST(MES AS SIGNED) >=1) AND (CAST(MES AS SIGNED) < 7)) AND ANY = ".$any_actual.") AND DNI_TUTOR LIKE '%".$USER->username."%' ORDER BY DATA");
			$result_menu = mysqli_query ($connexio,"SELECT DISTINCT e.ID FROM personal AS p, estat AS e, cursos AS c WHERE (DATA BETWEEN '".$any_actual."-01-01' AND '".$any_actual."-06-30') AND ((CAST(MES AS SIGNED) >=1) AND (CAST(MES AS SIGNED) < 7)) AND ANY=".$any_actual." AND DNI_TUTOR LIKE '%".$USER->username."%' AND p.DNI=e.DNI AND p.DNI=c.DNI_TUTOR ORDER BY DATA");
		}
		else
		{
			// mirem el segon semestre de l'any actual i comprovem que hagi tutoritzat algun curs
			//$result_menu = mysqli_query ($connexio,"SELECT e.ID FROM personal AS p, estat AS e, cursos AS c WHERE p.DNI=e.DNI AND p.DNI LIKE '%".$USER->username."%' AND ((DATA BETWEEN '".$any_actual."-07-01' AND '".$any_actual."-12-31' AND (CAST(MES AS SIGNED) >=7) AND (CAST(MES AS SIGNED) <= 12)) AND ANY = ".$any_actual.") AND DNI_TUTOR LIKE '%".$USER->username."%' ORDER BY DATA");
			$result_menu = mysqli_query ($connexio,"SELECT DISTINCT e.ID FROM personal AS p, estat AS e, cursos AS c WHERE (DATA BETWEEN '".$any_actual."-07-01' AND '".$any_actual."-12-31' AND (CAST(MES AS SIGNED) >=7) AND (CAST(MES AS SIGNED) <= 12)) AND ANY=".$any_actual." AND DNI_TUTOR LIKE '%".$USER->username."%' AND p.DNI=e.DNI AND p.DNI=c.DNI_TUTOR ORDER BY DATA");
		}


		//si t� pendent enviar certificat estat laboral
		if(mysqli_num_rows($result_menu)==0)
		{
			$primer = true;

			if ($pagina == "estat_laboral")
			{
			?>
				<div class="nav_on2" style="margin-left:20px; background-color:rgba(143,169,122,0.7) !important; color: #666666 !important"><span style="font-size:10px">NOTIFICACI&Oacute;</span><br />ESTAT LABORAL</div>
			<?php
			}
			else
			{
			?>
				<div class="nav2" style="margin-left:20px;"><a href="estat_laboral.php" style="margin-left:1px; background-color:rgba(143,169,122,0.7) !important; color: #666666 !important"><span style="font-size:10px">NOTIFICACI&Oacute;</span><br />ESTAT LABORAL</a></div>
			<?php
			}
		}
		else
		{
			if ($pagina == "estat_laboral")
			{
			?>	<script>
					window.location.assign("https://campus.prisma.cat/intranet-collaboradors/cobraments/alumnes.php");
				</script>
			<?php
			}
		}
	}
	else
	{
		if ($pagina == "estat_laboral")
		{
		?>	<script>
				window.location.assign("https://campus.prisma.cat/intranet-collaboradors/cobraments/alumnes.php");
			</script>
		<?php
		}
	}
}
else
{
	if ($pagina == "estat_laboral")
	{
	?>	<script>
			window.location.assign("https://campus.prisma.cat/intranet-collaboradors/cobraments/alumnes.php");
		</script>
	<?php
	}
}


if ($pagina == "alumnes_curs")
{
	if ($primer == false)
		$marge = "20px";
	else
		$marge = "1px";
?>
	<div class="nav_on2" style="margin-left:<?php echo $marge; ?>"><span style="font-size:10px">ALUMNES</span><br />CURS</div>
<?php
}
else
{
?>
	<div class="nav2" style="margin-left:20px;"><a href="alumnes.php" style="margin-left:1px;"><span style="font-size:10px">ALUMNES</span><br />CURS</a></div>
<?php
}


if ($pagina == "consulta_informes")
{
?>
	<div class="nav_on2" style="margin-left:1px;"><span style="font-size:10px">CONSULTA</span><br />INFORMES</div>
<?php
}
else
{
?>
	<div class="nav2"><a href="consulta_informes.php" style="margin-left:1px;"><span style="font-size:10px">CONSULTA</span><br />INFORMES</a></div>
<?php
}

if ($pagina == "consulta_revisions")
{
?>
	<div class="nav_on2" style="margin-left:1px;"><span style="font-size:10px">CONSULTA</span><br />REVISIONS</div>
<?php
}
else
{
?>
	<div class="nav2"><a href="consulta_revisions.php" style="margin-left:1px;"><span style="font-size:10px">CONSULTA</span><br />REVISIONS</a></div>
<?php
}


if ($pagina == "gestio_cobraments")
{
?>
	<div class="nav_on2" style="margin-left:1px;"><span style="font-size:10px">GESTI&Oacute;</span><br />COBRAMENTS</div>
<?php
}
else
{
?>
	<div class="nav2"><a href="gestio_cobraments.php" style="margin-left:1px;"><span style="font-size:10px">GESTI&Oacute;</span><br />COBRAMENTS</a></div>
<?php
}


if ($pagina == "consulta_cobraments")
{
?>
	 <div class="nav_on2" style="margin-left:1px;"><span style="font-size:10px">CONSULTA</span><br />COBRAMENTS</div>
<?php
}
else
{
?>
	<div class="nav2"><a href="consulta_cobraments.php" style="margin-left:1px;"><span style="font-size:10px">CONSULTA</span><br />COBRAMENTS</a></div>
<?php
}
?>
