<!DOCTYPE html>
<html>
<head>
</head>
<body>

<?php
$any = $_GET['any'];

	$conexion = mysqli_connect('localhost','suport','1324GiRoNa','gestio');
	if (mysqli_connect_errno())  
	{
		echo "No es pot connectar: " . mysqli_connect_error();
	}
	mysqli_set_charset($conexion, "utf8");
	
	$result_mes = mysqli_query ($conexion, "SELECT DISTINCT MES FROM cobraments WHERE ANY=".$any." ORDER BY cobraments.MES");
	
	for ($n=0; $n<mysqli_num_rows($result_mes); $n++)
	{
		$a = mysqli_fetch_array($result_mes);
		echo "<option value=".$a['MES'].">".$a['MES']."</option>";
	}

	mysqli_close($conexion);

?>

</body>
</html>