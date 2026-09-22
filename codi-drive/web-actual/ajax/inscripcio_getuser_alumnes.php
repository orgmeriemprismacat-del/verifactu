<?php
require_once('../lib/nusoap.php');

include('../parametres_connexio.php');

$dni = $_REQUEST['dni'];
$codi = $_REQUEST['codi'];
$carnet = $_REQUEST['carnet_jove'];
$opcio = $_REQUEST['opcio'];
$dte = $_REQUEST['dte'];

$opcio = substr($opcio, -2);
$mes_dte =  $_REQUEST['mes_dte'];

//$mes_dte = '12';
$carnet_val = "no";
$text_preu="Preu a calcular";

$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
if (mysqli_connect_errno())
{
	echo "No es pot connectar: " . mysqli_connect_error();
}
mysqli_set_charset($connexio, "utf8");

/******************************* Comprovem que l'usuari té carnet jove *******************************/
if ($carnet=="yes") {
	//Paràmetres d'entrada
	$usuari = "entitat_colaboradora";
	$contrasenya = "h14np0PG5s";

	//url del webservice
	$wsdl="http://www.carnetjove.cat/carnetjove/service/webService?wsdl";

	//intanciant un nou objecte client per el webservice
	$client=new nusoap_client($wsdl,true);
	//passant per parametres a un array
	$param=array(
		"login"=> $usuari,
		"password" => $contrasenya,
		"numeroDocument" => $dni
	);
	//crida al mètode i passant amb els parametres
	$resultado = $client->call('usuariExistent', $param);;
	if ($resultado['return']=="true")
		$carnet_val="yes";
	else
		$carnet_val="no";
}
/****************************************************************/

//Sel·leccionem tots els registres els quals la persona amb dni $dni han pagat un curs si havien de pagar.
$sql = "SELECT ID FROM inscripcions WHERE DNI = '".$dni."' AND ((A_PAGAR>0 AND PAGAMENT>0) OR (A_PAGAR=0 AND OBSERVACIONS LIKE '%CURS REGAL%') OR (GENERAT=1)) AND `INSC CURS` != 'D'";
$result = mysqli_query ($connexio, $sql);

//Sel·lecciono el nombre d'hores de l'última edició que s'ha fet del curs amb codi $codi
$sql = "SELECT HORES FROM cursos as c WHERE c.curs = '".$codi."'  AND DNI_TUTOR!='0' AND `DATA RESOL`!='' ORDER BY c.ANY DESC , c.MES DESC LIMIT 1";
$result_curs = mysqli_query ($connexio, $sql);
$row_curs = mysqli_fetch_array($result_curs, MYSQLI_ASSOC);

//si la persona ha pagat un curs i el curs existeix, se li aplica un descompte
if (mysqli_num_rows($result)>0 && mysqli_num_rows($result_curs)>0)
{
	//mirem si aquesta persona ha participat a les jornades de MAT02
	$result_mat02 = mysqli_query ($connexio,"SELECT ID FROM inscripcions WHERE DNI = '".$dni."' AND CURS='MAT02' AND (A_PAGAR>0) AND (PAGAMENT>0)");
	//mirem si aquesta persona ha participat a les jornades de REG01
	$result_reg01 = mysqli_query ($connexio,"SELECT ID FROM inscripcions WHERE DNI = '".$dni."' AND CURS='REG01' AND (A_PAGAR>0) AND (PAGAMENT>0)");
	if ($codi == "REG" and mysqli_num_rows($result_reg01)>0)
		$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">135 euros</span> <strong>70 euros</strong>  <span style=\"font-weight: bold; color: #647eba\">(Se t'ha aplicat el preu per haver assistit a la Jornada presencial de PrisMa)</span>";
	else {
		if ($mes_dte!=$opcio || $dte==0) {
			$dte = 0;
		}
		else {
			$promo = " i un 10% dte. promo Nadal";
		}

		if ($row_curs['HORES']==30)
		{
			$preu=round(60-($dte*60)/100);
			$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">70 euros</span> <strong>".$preu." euros</strong>";
		}
		else if ($row_curs['HORES']==40)
		{
			$preu=round(80-($dte*80)/100);
			$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">90 euros</span> <strong>".$preu." euros</strong>";
		}
		else if ($row_curs['HORES']==60)
		{
			$preu=round(120-($dte*120)/100);
			$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">135 euros</span> <strong>".$preu." euros</strong>";
		}
		else if ($row_curs['HORES']==100)
		{
			$preu=round(180-($dte*180)/100);
			$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">200 euros</span> <strong>".$preu." euros</strong>";
		}
		$text_preu = $text_preu." <span style=\"font-weight: bold; color: #647eba\" style=\"height: 20px; margin-bottom: -5px; margin-right: 5px; margin-left: 8px;\">(se t'ha aplicat el preu d'alumne PrisMa".$promo.")</span>";
	}
}
else {
	if ($carnet_val=="yes" && mysqli_num_rows($result_curs)>0) //si la persona te carnet jove se li aplica un descompte
	{
		if ($mes_dte!=$opcio || $dte==0) {
			$dte = 0;
		}
		else {
			$promo_jove = " (-10% promo Nadal aplicat)";
		}

		if ($row_curs['HORES']==30)
		{
			$preu=round(60-($dte*60)/100);
			$text_preu="Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">70 euros</span> <strong>".$preu." euros</strong>".$promo;
		}
		else if ($row_curs['HORES']==40)
		{
			$preu=round(80-($dte*80)/100);
			$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">90 euros</span> <strong>".$preu." euros</strong>".$promo;
		}
		else if ($row_curs['HORES']==60)
		{
			$preu=round(120-($dte*120)/100);
			$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">135 euros</span> <strong>".$preu." euros</strong>".$promo;
		}
		else if ($row_curs['HORES']==100)
		{
			$preu=round(180-($dte*180)/100);
			$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">200 euros</span> <strong>".$preu." euros</strong>".$promo;
		}

		$text_preu = $text_preu." <span style=\"font-weight: bold; color: #60a05a\"><img src=\"https://www.prisma.cat/informacio/img/ok.png\" style=\"height: 20px; margin-bottom: -5px; margin-right: 5px; margin-left: 8px;\">NIF/NIE del Carnet Jove verificat".$promo_jove."</span>";
	}
	else // cas sense cap descompte
	{
		//Comprovem si existeix el curs
		if (mysqli_num_rows($result_curs)>0)
		{
			if ($mes_dte!=$opcio || $dte==0)
			{
				if ($row_curs['HORES']==30)
					$text_preu= "Preu: <strong>70 euros</strong>";
				else if ($row_curs['HORES']==40)
					$text_preu= "Preu: <strong>90 euros</strong>";
				else if ($row_curs['HORES']==60)
					$text_preu= "Preu: <strong>135 euros</strong>";
				else if ($row_curs['HORES']==100)
					$text_preu= "Preu: <strong>200 euros</strong>";
			}
			else
			{
				$promo = " (-10% promo Nadal aplicat)";

				if ($row_curs['HORES']==30)
				{
					$preu=round(70-($dte*70)/100);
					$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">70 euros</span> <strong>".$preu." euros</strong>";
				}
				else if ($row_curs['HORES']==40)
				{
					$preu=round(90-($dte*90)/100);
					$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">90 euros</span> <strong>".$preu." euros</strong>";
				}
				else if ($row_curs['HORES']==60)
				{
					$preu=round(135-($dte*135)/100);
					$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">135 euros</span> <strong>".$preu." euros</strong>";
				}
				else if ($row_curs['HORES']==100)
				{
					$preu=round(200-($dte*200)/100);
					$text_preu= "Preu: <span style=\"text-decoration: line-through; color: #D1D1D1\">200 euros</span> <strong>".$preu." euros</strong>";
				}
				$text_preu = $text_preu.$promo;
			}

			if ($carnet_val=="no" and $carnet=="yes")
				$text_preu = $text_preu." <span style=\"font-weight: bold; color: #b22b2d\"><img src=\"https://www.prisma.cat/informacio/img/creu.png\" style=\"height: 20px; margin-bottom: -5px; margin-right: 5px; margin-left: 8px;\">No hem pogut verificar que el teu NIF/NIE estigui associat a un Carnet Jove</span>";
		}
	}
}
echo $text_preu;

mysqli_close($connexio);

?>
