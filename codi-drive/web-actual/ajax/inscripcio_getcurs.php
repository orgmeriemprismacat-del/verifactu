<?php
$servername = "localhost";
$username = "suport";
$password = "1324GiRoNa";
$dbname = "gestio";

$codi = $_REQUEST['codi'];
$opcio = $_REQUEST['opcio'];
$dte = $_REQUEST['dte'];

$opcio = substr($opcio, -2);
$mes_dte =  $_REQUEST['mes_dte'];
//$mes_dte='12';

	$conn = mysqli_connect($servername, $username, $password, $dbname);
	if (mysqli_connect_errno())
	{
		echo "No es pot connectar: " . mysqli_connect_error();
	}
	mysqli_set_charset($conn, "utf8");

	//Sel·lecciono el nombre d'hores de l'última edició que s'ha fet del curs amb codi $codi
	$sql = "SELECT HORES FROM cursos as c WHERE c.curs = '".$codi."'  AND DNI_TUTOR!='0' AND `DATA RESOL`!='' ORDER BY c.ANY DESC , c.MES DESC LIMIT 1";
	$result_curs = mysqli_query ($conn, $sql);
	$row_curs = mysqli_fetch_array($result_curs, MYSQLI_ASSOC);

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
			$promo = " (-20% promo maig aplicat)";

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
	}

	echo $text_preu;
	mysqli_close($conn);
?>
