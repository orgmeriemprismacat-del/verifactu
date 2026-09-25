<?php
require('../../config.php');

if (
	$USER->username=="43400030" ||
	$USER->username=="43674436" or $USER->username=="79302336"
) { //Daniel Gabarro i sistèmiques
	header("Location: https://old.prisma.cat/intranet/acces_extern.php");
	exit;
}
else
{
  if ( !isloggedin() ) {
    ?>
  	<script>window.location.href = "https://campus.prisma.cat/login"</script>
  	<?php
  }
  else {
    ?>
    <!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01//EN" "http://www.w3.org/TR/html4/strict.dtd">
    <html lang="ca" prefix="og: http://ogp.me/ns# fb: http://ogp.me/ns/fb# video: http://ogp.me/ns/video#">
    	<head>
    		<meta charset="utf-8">
    		<meta http-equiv="X-UA-Compatible" content="IE=edge">
    		<meta name="viewport" content="width=device-width, initial-scale=1">

    		<title>Intranet | Estat laboral</title>

    		<!-- Bootstrap CSS -->
    		<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/css/bootstrap.min.css"/>
    		<!-- CSS General -->
    		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-collaboradors/cobraments/css/general.css?ver=6.0"/>

    		<!-- jQuery-->
    		<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/3.5.1/jquery.min.js"></script>
    		<!-- Bootstrap JS -->
    		<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.0/js/bootstrap.min.js"></script>
    		<!-- Fontawesome -->
    		<script src="https://kit.fontawesome.com/5b3303ad4a.js"></script>

    		<link rel="preconnect" href="https://fonts.googleapis.com">
    		<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    		<link href="https://fonts.googleapis.com/css2?family=Roboto:ital,wght@0,400;0,500;0,700;1,400;1,500;1,700&display=swap" rel="stylesheet">
    	</head>
    	<body>
				<div id='mdl-user-username' class='d-none'><?php echo $USER->username ?></div>
    		<div class="contingut">
    			<div class="sidebar active h-100 position-fixed bg-white"></div>
    			<div class="mainpanel active h-100 position-relative float-right ps">
            <div id='head-title'></div>
            <div id='content-page' class='px-3 py-2'>
            <?php
							include('./inc/dades.php');
							if ($_POST['enviar']=="ENVIAR") {
								$connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
								mysqli_set_charset ($connexio, "utf8");

								if (mysqli_connect_errno())
								{
									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
								}
								else {
									$result1 = mysqli_query ($connexio,"SELECT NOM, COGNOMS, MAIL_PRISMA, DNI FROM personal WHERE DNI LIKE '%".$USER->username."%'");

									$row1 = mysqli_fetch_array($result1);

									$observacions = str_replace("'","''",$_POST[observacions]);

									$result2 = mysqli_query ($connexio,"INSERT INTO estat (DNI,SITUACIO,DATA, OBSERVACIONS) VALUES ('".$row1[DNI]."','".$_POST[estat]."',CURRENT_DATE,'".$observacions."')");

									$nom = str_replace("\'","'",$row1['NOM']);
									$cognoms = str_replace("\'","'",$row1['COGNOMS']);


									// li enviem un correu de confirmació al tutor/a
									$to0 = $row1[MAIL_PRISMA];
									// $to0 = "meriem.prisma.cat@gmail.com";

									$headers0  = "MIME-Version: 1.0\r\n";
									$headers0 .= "Content-type: text/html; charset=UTF-8\r\n";
									$headers0 .= "From: ".$nom." <".$row1[MAIL_PRISMA].">\nReply-To: gestio@prisma.cat";

									$subject0 = "Informació estat laboral - ".$nom." ".$cognoms;

									$message0 = "<p>Bon dia, ".$nom.",</p>
									<p>T'informem que hem rebut correctament el <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em>.</p>
									<p>Recorda que l'espai «NOTIFICACIÓ ESTAT LABORAL» només t'apareixerà a la teva Intranet cada sis mesos, a principis de gener i a principis de juliol. Un cop hagis enviat el document acreditatiu que verifiqui el teu estat laboral, aquesta pestanya es tornarà a ocultar fins al proper període descrit.</p>
									<p>T'agrairem que ens comuniquis directament a facturacio@prisma.cat les modificacions que es produeixin fora d'aquestes dates. </p>
									<p>Gràcies per la teva col·laboració, i quedem a la teva disposició per a qualsevol dubte o consulta.</p>
									<p>Equip PrisMa</p>";
									// fi correu confirmació

									// ens enviem el fitxer
									$bHayFicheros = 0;
									$sCabeceraTexto = "";
									$sAdjuntos = "";

									$to = "facturacio@prisma.cat, gestio@prisma.cat, suport@prisma.cat";
									// $to = "meriem.prisma.cat@gmail.com";

									$subject = "Estat laboral - ".$nom." ".$cognoms;

									$headers = "From: ".$nom." <".$row1[MAIL_PRISMA].">\nReply-To: ".$row1[MAIL_PRISMA]."\n";
									$headers .= "MIME-version: 1.0\n";

									$sTexto = "<p><em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em> enviat!</p> ";

									$comentaris = str_replace("\'","'",$_POST[observacions]);

									$sTexto .= "<p><br />".$comentaris."</p>";

									foreach ($_FILES as $vAdjunto)
									{
										if ($bHayFicheros == 0)
										{
											$bHayFicheros = 1;
											$headers .= "Content-type: multipart/mixed;";
											$headers .= "boundary=\"--_Separador-de-mensajes_--\"\n";

											$sCabeceraTexto = "----_Separador-de-mensajes_--\n";
											$sCabeceraTexto .= "Content-type: text/html;charset=iso-8859-1\n";
											$sCabeceraTexto .= "Content-transfer-encoding: 7BIT\n";

											$sTexto = $sCabeceraTexto.$sTexto;
										}
										if ($vAdjunto["size"] > 0)
										{
											$sAdjuntos .= "\n\n----_Separador-de-mensajes_--\n";
											$sAdjuntos .= "Content-type: ".$vAdjunto["type"].";name=\"".$vAdjunto["name"]."\"\n";;
											$sAdjuntos .= "Content-Transfer-Encoding: BASE64\n";
											$sAdjuntos .= "Content-disposition: attachment;filename=\"".$vAdjunto["name"]."\"\n\n";

											$oFichero = fopen($vAdjunto["tmp_name"], 'r');
											$sContenido = fread($oFichero, filesize($vAdjunto["tmp_name"]));
											$sAdjuntos .= chunk_split(base64_encode($sContenido));
											fclose($oFichero);
										}
									}

									if ($bHayFicheros)
									{
										$sTexto .= $sAdjuntos."\n\n----_Separador-de-mensajes_----\n";
										if (mail($to0, $subject0, $message0, $headers0) && mail($to, $subject, $sTexto, $headers))
										echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
										<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
										<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
										Has enviat el <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em> correctament.
										</p></div></div>";
										?>
										<script>
											$("#modalLoading .loading-text").removeClass('hide');
											$("#modalLoading .loading-text").html( 'Redireccionant...' );
											$("#modalLoading").modal('show');
											setTimeout(function() {
												window.location.href = "https://campus.prisma.cat/collaboradors/gestio-cobraments/";
											}, 3500);
										</script>
										<?php
									}
									// fi missatge
								}
							}
							else {
								?>
								<div class='card card-blau mt-0 pt-2'>
									<form name="gestions" method="post" action="<?php echo $PHP_SELF ?>" enctype="multipart/form-data" class='card-body px-0'>
										<p align="justify">L'Associació PrisMa estableix que la col·laboració docent que fan els tutors/autors és una activitat complementària a la seva activitat laboral o professional principal. Per tal de verificar el compliment adequat d'aquest criteri, et sol·licitem que semestralment ens confirmis la teva situació laboral escollint una de les opcions següents i adjuntant-nos una còpia actual del teu <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em>.</p>
										<ul>
												<li><input type="radio" name="estat" value="Sóc autònom/a." required onclick="posar()" /> Sóc autònom/a.</li>
												<li><input type="radio" name="estat" value="Treballo per compte aliè." onclick="posar()" /> Treballo per compte aliè.</li>
												<li><input type="radio" name="estat" value="Estic jubilat/da." onclick="treure()" /> Estic jubilat/da.</li>
												<li><input type="radio" name="estat" value="No estic treballant." onclick="posar()" /> No estic treballant.</li>
												<li><input type="radio" name="estat" value="Altres..." onclick="posar()" /> Altres... </li>
										</ul>

										<div class='form-group field-wrap position-relative mb-0 p-1 w-100 '>
											<label class=''>Observacions</label>
											<textarea id="observacions" name="observacions" class='form-control'></textarea>
										</div>
										<div id="adj">
											<p class='mt-3'><strong>Adjuntar el <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em></strong> (pots consultar les instruccions per obtenir aquest «Certificat» clicant <a href="https://sede.seg-social.gob.es/wps/portal/sede/sede/EmpresasyProfesionales/EmpresasyProfDetalle/!ut/p/z1/rVVNU9swEP0r4cBRo9WHbeloGGoICWkIhsQXRpbloBY7wXah9NdXTmmZKbXNNPgm7frt6u3bXZzgJU5K9WjXqrGbUt278yrxbxnxOZFAJhGcnUAYT89jyeYsmjN8s3OAji8EnPT_f40TnOiy2TZ3eFWbzNzqTdmY0mab-hDai0MwxbYytXJnW-abqjD16HmkTdXY3Gq1c6TgSU5arK22GV4J4isFgUGaegRxRgCJgGkEOcmFnxOmGX_JvSM5kIO537TxBhB2Dn309Aah_oBD6OGVe0Xw6gAQOeD4CBbXQAlELs1Ha55wXDrmXEUXryQBp5yAL1EW8BRxnmqUZlogqpXUmdBpDhKfwt8RIjL32ggzcXY5JyCCPSP8gvdmAsgY6AT8qYBQzhfx5IIwCPie8OMhhp3G7ZeHhyR0Qmy1973Byw4lavstU5kq25vKrG1hypGpt0ZbdT_KzP2oUNUhkE5bG4tW0-Pp2j1BNXeoFTRedrv3QL2pyhGHkMyufHp6QmDWR5vxtCSBRDkBz9HmZ0gaKVCWBllgFOFM6f6qRED2hO-vyhn536qo2tbO7Jga1aq0jaqsascDBc7fUHbu2iSMP80kjwMKvW8CUCkFjWgGrFVajqRymnO6I8BSHXgeG4JnHwLf2SfBnvDjoUHzb-12jGS8BNJpen9p3zH6u-MMdIjfTdi71sdAh9A94cdD6-djaXzZoNsiLgR7Rl8vT38cXaDoOBVPV3lxI-p8esK8dXE7bXY3vw2fJ38MdXhw8BPRUxKm/dz/d5/L2dBISEvZ0FBIS9nQSEh/" title="Clica aquí" target="_blank">AQUÍ</a>):</p>
											<p><input type='file' name='archivo1' id='archivo1' required></p>
										</div>
										<p class="text-justify">T'agraïm la teva col·laboració i quedem a la teva disposició per a qualsevol dubte o consulta.</p>

										<div align="center"><br />
											<input type="submit" name="enviar" class="botones boto-blau px-4 d-flex" value="ENVIAR"/>
										</div>

									</form>
								</div>
								<?php
							}
            ?>
    			   </div>
    			</div>
    		</div>
    		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-collaboradors/cobraments/css/forms.css?ver=6.0"/>
    		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-collaboradors/cobraments/css/table.css?ver=6.0"/>
    		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-collaboradors/cobraments/css/alerts.css?ver=6.0"/>
    		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-collaboradors/cobraments/css/modals.css?ver=6.0"/>
    		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-collaboradors/cobraments/css/inici.css?ver=6.0"/>
    		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-collaboradors/cobraments/css/estat-laboral.css?ver=6.0"/>
    		<script src="https://campus.prisma.cat/intranet-collaboradors/cobraments/js/general.js?ver=7.0"></script>
    		<script src="https://campus.prisma.cat/intranet-collaboradors/cobraments/js/estat-laboral.js?ver=7.0"></script>
    	</body>
    </html>
    <?php
  }
}

?>
