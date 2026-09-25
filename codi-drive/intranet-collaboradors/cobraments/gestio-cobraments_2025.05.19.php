<?php
require('../../config.php');

if (
	$USER->username=="43400030" ||
	$USER->username=="43674436" or $USER->username=="79302336"
) { //Daniel Gabarro i sistèmiques
	header("Location: https://www.prisma.cat/intranet/acces_extern.php");
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

    		<title>Intranet | Gestió de cobraments</title>

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
              if ($USER->username == '33881437') { // Elisabet Carbonell
                $nif = '33881437E';
                $cuho = 123;
                $text = "(DNI_TUTOR LIKE '33881437%' OR DNI_TUTOR LIKE '78100121%')";
                $text_h = "(h.DNI_TUTOR LIKE '33881437%' OR h.DNI_TUTOR LIKE '78100121%')";
                $text_ht = "(h.DNI_TUTOR LIKE '33881437%')";
                $text_p = "p.DNI LIKE '33881437%'";
                $text_e = "DNI LIKE '33881437%'";
              }
              else if ($USER->username == '78100121') { // Tània Montoto
                $nif = '78100121X';
                $cuho = 124;
                $text = "(DNI_TUTOR LIKE '33881437%' OR DNI_TUTOR LIKE '78100121%')";
                $text_h = "(h.DNI_TUTOR LIKE '33881437%' OR h.DNI_TUTOR LIKE '78100121%')";
                $text_ht = "(h.DNI_TUTOR LIKE '78100121%')";
                $text_p = "p.DNI LIKE '78100121%'";
                $text_e = "DNI LIKE '78100121%'";
              }			  
			  /************QUAN SÓN DUES TUTORES ***********/
			  else if ($USER->username == '35000798' && (date("Ymd")<'20250401')) { // Àngels Miret
			  echo date("Y");
			  echo date("m"); 
                $nif = '35000798L';
                $cuho = 222;
                $text = "( ((DNI_TUTOR LIKE '35000798%' OR DNI_TUTOR LIKE '39352558%') AND c.CURS = 'GED') OR DNI_TUTOR LIKE '35000798%')";
                $text_h = "( ((h.DNI_TUTOR LIKE '35000798%' OR h.DNI_TUTOR LIKE '39352558%') AND c.CURS = 'GED') OR h.DNI_TUTOR LIKE '35000798%' )";
                $text_ht = "(h.DNI_TUTOR LIKE '35000798%')";
                $text_p = "p.DNI LIKE '35000798%'";
                $text_e = "DNI LIKE '35000798%'";
              }
			  else if ($USER->username == '39352558' && (date("Ymd")<'20250401')) { // Neus Ballesteros
                $nif = '39352558H';
                $cuho = 211;
                $text = "( ((DNI_TUTOR LIKE '35000798%' OR DNI_TUTOR LIKE '39352558%') AND c.CURS = 'GED') OR DNI_TUTOR LIKE '39352558%')";
                $text_h = "( ((h.DNI_TUTOR LIKE '35000798%' OR h.DNI_TUTOR LIKE '39352558%') AND c.CURS = 'GED') OR h.DNI_TUTOR LIKE '39352558%' )";
                $text_ht = "(h.DNI_TUTOR LIKE '39352558%')";
                $text_p = "p.DNI LIKE '39352558%'";
                $text_e = "DNI LIKE '39352558%'";
              }
			  /************QUAN ÉS UNA TUTORA ***********/
			  else if ($USER->username == '35000798') { // Àngels Miret
                $nif = '35000798L';
                $cuho = 222;
                $text = "( ((DNI_TUTOR LIKE '35000798%') AND c.CURS = 'GED') OR DNI_TUTOR LIKE '35000798%')";
                $text_h = "( ((h.DNI_TUTOR LIKE '35000798%') AND c.CURS = 'GED') OR h.DNI_TUTOR LIKE '35000798%' )";
                $text_ht = "(h.DNI_TUTOR LIKE '35000798%')";
                $text_p = "p.DNI LIKE '35000798%'";
                $text_e = "DNI LIKE '35000798%'";
              }	
			  else if ($USER->username == '39352558') { // Neus Ballesteros
                $nif = '39352558H';
                $cuho = 227;
                $text = "( ((DNI_TUTOR LIKE '39352558%') AND c.CURS = 'GED') OR DNI_TUTOR LIKE '39352558%')";
                $text_h = "( ((h.DNI_TUTOR LIKE '39352558%') AND c.CURS = 'GED') OR h.DNI_TUTOR LIKE '39352558%' )";
                $text_ht = "(h.DNI_TUTOR LIKE '39352558%')";
                $text_p = "p.DNI LIKE '39352558%'";
                $text_e = "DNI LIKE '39352558%'";
              }			  
              else {
                $text = "DNI_TUTOR LIKE '%".$USER->username."%'";
                $text_h = "h.DNI_TUTOR LIKE '%".$USER->username."%'";
                $text_ht = "h.DNI_TUTOR LIKE '%".$USER->username."%'";
                $text_p = "p.DNI LIKE '%".$USER->username."%'";
                $text_e = "DNI LIKE '%".$USER->username."%'";
              }

              include('./inc/dades.php');

              $bestreta = 'X';

              //REVISIÓ DEL TUTOR
              if ($_POST['revisio']=="ENVIAR PER A REVISIÓ")
              {
                $to = "secretaria@prisma.cat";
								// $to = "meriem.prisma.cat@gmail.com";

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: ".$_POST[qui]." <".$_POST[correu].">\nReply-To: ".$_POST[correu]."";

                $subject = "Revisió cobrament curs (".$_POST[any].$_POST[curs].$_POST[mes].") (Tutor)";

                $titol = str_replace("\'","'",$_POST[titol]);
                $dates = str_replace("\'","'",$_POST[dates]);
                $comentaris = str_replace("\'","'",$_POST[comentaris]);

                $message = "<p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                <p><strong>CURS:</strong> ".$titol."<br>
                <strong>DATES:</strong> ".$dates."<br>
                <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                <strong>IMPORT A COBRAR (BRUT):</strong> ".$_POST[import]." euros</p>

                <p style=text-decoration:underline><strong>COMENTARIS PER A REVISIÓ</strong></p>
                <p>".$comentaris."</p>";

                if (mail($to, $subject, $message, $headers))
								echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
								<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
								<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
								Els comentaris per a revisió s'han enviat correctament.
								</p></div></div>";

                ?><div align="center"><br /><input type="button" name="boto" class="botones boto-blau px-4 d-flex text-white" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='https://campus.prisma.cat/collaboradors/gestio-cobraments/'"/></div><?php
              }

              //REVISIÓ DEL DUO
              else if ($_POST['revisio2']=="ENVIAR PER A REVISIÓ")
              {
                //$to = "suport.informatic@prisma.cat";
                $to = "secretaria@prisma.cat";
								// $to = "meriem.prisma.cat@gmail.com";

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: ".$_POST[qui]." <".$_POST[correu].">\nReply-To: ".$_POST[correu]."";

                $titol = str_replace("\'","'",$_POST[titol]);
                $dates = str_replace("\'","'",$_POST[dates]);
                $comentaris = str_replace("\'","'",$_POST[comentaris]);

                $mesD = intval($_POST[mes]);
                if ($mesD == 12 || $mesD == 11 || $mesD == 10) {
                  $mesDuo = "4T";
                  $dates = "Octubre-Novembre-Desembre";
                }
                else if ($mesD == 9 || $mesD == 8 || $mesD == 7) {
                  $mesDuo = "3T";
                  $dates = "Juliol-Agost-Setembre";
                }
                else if ($mesD == 6 || $mesD == 5|| $mesD == 4) {
                  $mesDuo = "2T";
                  $dates = "Abril-Maig-Juny";
                }
                else if ($mesD == 3 || $mesD == 2 || $mesD == 1) {
                  $mesDuo = "1T";
                  $dates = "Gener-Febrer-Març";
                }

                $subject = "Revisió cobrament curs (".$_POST[any].$_POST[curs].$mesDuo.") (Autor i coordinador)";

                $message = "<p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                <p><strong>CURS:</strong> ".$titol."<br>
                <strong>DATES:</strong> ".$dates."<br>
                <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                <strong>IMPORT A COBRAR (BRUT):</strong> ".$_POST[import]." euros</p>

                <p style=text-decoration:underline><strong>COMENTARIS PER A REVISIÓ</strong></p>
                <p>".$comentaris."</p>";

                if (mail($to, $subject, $message, $headers))
								echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
								<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
								<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
								Els comentaris per a revisió s'han enviat correctament.
								</p></div></div>";

                ?><div align="center"><br /><input type="button" name="boto" class="botones boto-blau px-4 d-flex text-white" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='https://campus.prisma.cat/collaboradors/gestio-cobraments/'"/></div><?php
              }

              //REVISIÓ DEL AUTOR
              else if ($_POST['revisio3']=="ENVIAR PER A REVISIÓ")
              {
                //$to = "suport.informatic@prisma.cat";
                $to = "secretaria@prisma.cat";
								// $to = "meriem.prisma.cat@gmail.com";

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: ".$_POST[qui]." <".$_POST[correu].">\nReply-To: ".$_POST[correu]."";

                $titol = str_replace("\'","'",$_POST[titol]);
                $dates = str_replace("\'","'",$_POST[dates]);
                $comentaris = str_replace("\'","'",$_POST[comentaris]);

                $mesAC = intval($_POST[mes]);
                if ($mesAC == 12 || $mesAC == 11 || $mesAC == 10) {
                  $mesAutor = "4T";
                  $dates = "Octubre-Novembre-Desembre";
                }
                if ($mesAC == 9 || $mesAC == 8 || $mesAC == 7) {
                  $mesAutor = "3T";
                  $dates = "Juliol-Agost-Setembre";
                }
                if ($mesAC == 6 || $mesAC == 5|| $mesAC == 4) {
                  $mesAutor = "2T";
                  $dates = "Abril-Maig-Juny";
                }
                if ($mesAC == 3 || $mesAC == 2 || $mesAC == 1) {
                  $mesAutor = "1T";
                  $dates = "Gener-Febrer-Març";
                }

                $subject = "Revisió cobrament curs (".$_POST[any].$_POST[curs].$mesAutor.") (Autor)";

                $message = "<p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                <p><strong>CURS:</strong> ".$titol."<br>
                <strong>DATES:</strong> ".$dates."<br>
                <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                <strong>IMPORT A COBRAR (BRUT):</strong> ".$_POST[import]." euros</p>

                <p style=text-decoration:underline><strong>COMENTARIS PER A REVISIÓ</strong></p>
                <p>".$comentaris."</p>";

                if (mail($to, $subject, $message, $headers))
								echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
								<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
								<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
								Els comentaris per a revisió s'han enviat correctament.
								</p></div></div>";

                ?><div align="center"><br /><input type="button" name="boto" class="botones boto-blau px-4 d-flex text-white" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='https://campus.prisma.cat/collaboradors/gestio-cobraments/'"/></div><?php
              }

              //REVISIÓ DEL COORDINADOR
              else if ($_POST['revisio4']=="ENVIAR PER A REVISIÓ")
              {
                //$to = "suport.informatic@prisma.cat";
                $to = "secretaria@prisma.cat";
								// $to = "meriem.prisma.cat@gmail.com";

                $headers  = "MIME-Version: 1.0\r\n";
                $headers .= "Content-type: text/html; charset=UTF-8\r\n";
                $headers .= "From: ".$_POST[qui]." <".$_POST[correu].">\nReply-To: ".$_POST[correu]."";

                $titol = str_replace("\'","'",$_POST[titol]);
                $dates = str_replace("\'","'",$_POST[dates]);
                $comentaris = str_replace("\'","'",$_POST[comentaris]);

                $mesC = intval($_POST[mes]);
                if ($mesC == 12 || $mesC == 11 || $mesC == 10) {
                  $mesAutor = "4T";
                  $dates = "Octubre-Novembre-Desembre";
                }
                if ($mesC == 9 || $mesC == 8 || $mesC == 7) {
                  $mesAutor = "3T";
                  $dates = "Juliol-Agost-Setembre";
                }
                if ($mesC == 6 || $mesC == 5|| $mesC == 4) {
                  $mesAutor = "2T";
                  $dates = "Abril-Maig-Juny";
                }
                if ($mesC == 3 || $mesC == 2 || $mesC == 1) {
                  $mesAutor = "1T";
                  $dates = "Gener-Febrer-Març";
                }

                $subject = "Revisió cobrament curs (".$_POST[any].$_POST[curs].$mesAutor.") (Coordinador)";

                $message = "<p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                <p><strong>CURS:</strong> ".$titol."<br>
                <strong>DATES:</strong> ".$dates."<br>
                <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                <strong>IMPORT A COBRAR (BRUT):</strong> ".$_POST[import]." euros</p>

                <p style=text-decoration:underline><strong>COMENTARIS PER A REVISIÓ</strong></p>
                <p>".$comentaris."</p>";

                if (mail($to, $subject, $message, $headers))
								echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
								<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
								<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
								Els comentaris per a revisió s'han enviat correctament.
								</p></div></div>";

                ?><div align="center"><br /><input type="button" name="boto" class="botones boto-blau px-4 d-flex text-white" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='https://campus.prisma.cat/collaboradors/gestio-cobraments/'"/></div><?php
              }

              //CONFIRMAR COBRAMENT TUTOR
              else if ($_POST['confirmar']=="HI ESTIC D'ACORD")
              {
                $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                mysqli_set_charset ($connexio, "utf8");

                if (mysqli_connect_errno())
                {
                  echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                }
                else
                {
                  $irpfTutor = $_POST[irpf];
                  $dates = str_replace("'","\'",$_POST[dates]);
                  if ($_POST[irpf] <= 0)
                  {
                    $irpfTutor = 0;
                  }

                  $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT) VALUES
                  ('".$_POST[dni]."','T',".$_POST[any].",'".$_POST[curs]."','".$_POST[mes]."','".$dates."',".$_POST[inscrits].",".$_POST[import].",".$irpfTutor.",".$_POST[importnet].",CURRENT_DATE)");

                  function form_mail()
                  {
                    $bHayFicheros = 0;
                    $sCabeceraTexto = "";
                    $sAdjuntos = "";

                    $titol = str_replace("\'","'",$_POST[titol]);
                    $dates = str_replace("\'","'",$_POST[dates]);

                    //$to = "suport.informatic@prisma.cat";
                    $to = "facturacio@prisma.cat, secretaria@prisma.cat, suport.informatic@prisma.cat, suport@prisma.cat";
										// $to = "meriem.prisma.cat@gmail.com";

                    $subject = "Factura curs (".$_POST[any].$_POST[curs].$_POST[mes].")";

                    $headers = "From: ".$_POST[qui]." <".$_POST[correu].">\n";
                    $headers .= "MIME-version: 1.0\n";

                    if ($_POST[percentatge] <= 0 || $_POST[irpf] <= 0)
                    {
                      $sTexto = "<p>Factura enviada!</p>
                      <p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                      <p><strong>CURS:</strong> ".$titol."<br>
                      <strong>DATES:</strong> ".$dates."<br>
                      <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                      <strong>IMPORT (BRUT):</strong> ".$_POST[import]." euros<br>
                      <strong>IRPF (0%):</strong> No aplica<br>
                      <strong>IMPORT A COBRAR (NET):</strong> ".$_POST[importnet]." euros</p>
                      <p><em>Nota: cal comprovar que els imports del correu i la factura coincideixin.</em></p>";
                    }
                    else
                    {
                      $sTexto = "<p>Factura enviada!</p>
                      <p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                      <p><strong>CURS:</strong> ".$titol."<br>
                      <strong>DATES:</strong> ".$dates."<br>
                      <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                      <strong>IMPORT (BRUT):</strong> ".$_POST[import]." euros<br>
                      <strong>IRPF (".$_POST[percentatge]."%):</strong> ".$_POST[irpf]." euros<br>
                      <strong>IMPORT A COBRAR (NET):</strong> ".$_POST[importnet]." euros</p>
                      <p><em>Nota: cal comprovar que els imports del correu i la factura coincideixin.</em></p>";
                    }
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

                      $mesos_=intval($_POST[mes]);
                      if($MES<10) {
                        $pmes = "0".$mesos_;
                      }
                      else {
                        $pmes = $mesos_;
                      }

                      $any_data_actual = date("Y");
                      $mes_data_actual = date("m");

                      $dir_destino = './factures/';
                      $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
                      $modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';
                      $nom_tutor = utf8_decode(str_replace(' ','_',$_POST[qui]));
                      $nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
                      $nom_tutor = utf8_encode($nom_tutor);
                      $extension = substr(basename($_FILES['archivo1']['name']),-4,4);
                      $imagen_subida = $dir_destino.$any_data_actual.$mes_data_actual."_Curs_".$_POST[any].$_POST[curs].$_POST[mes]."_Tutoria_".$nom_tutor.$extension;

                      //Variables del metodo POST
                      if(!is_writable($dir_destino)){
                      }
                      else{
                        if(is_uploaded_file($_FILES['archivo1']['tmp_name'])){
                          if (move_uploaded_file($_FILES['archivo1']['tmp_name'], $imagen_subida)) {
														/*echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
														<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
														<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
														L'arxiu s'ha enviat correctament.
														</p></div></div>";*/
                          }
                        }
                      }
                    }

                    if ($bHayFicheros)
                      $sTexto .= $sAdjuntos."\n\n----_Separador-de-mensajes_----\n";
                    return(mail($to, $subject, $sTexto, $headers));

                  }

                  if (form_mail())
                    echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
										<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
										<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
										Has enviat la factura correctament!
										</p></div></div>";

                  ?><div align="center"><br /><input type="button" name="boto" class="botones boto-blau px-4 d-flex text-white" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='https://campus.prisma.cat/collaboradors/gestio-cobraments/'"/></div><?php
                }
              }

              //CONFIRMAR COBRAMENT DUO
              else if ($_POST['confirmar2']=="HI ESTIC D'ACORD")
              {
                $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                mysqli_set_charset ($connexio, "utf8");

                if (mysqli_connect_errno())
                {
                  echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                }
                else
                {
                  $mesD = intval($_POST[mes]);
                  if ($mesD == 12 || $mesD == 11 || $mesD == 10) {
                    $mesDuo = "4T";
                    $dates = "Octubre-Novembre-Desembre";
                  }
                  else if ($mesD == 9 || $mesD == 8 || $mesD == 7) {
                    $mesDuo = "3T";
                    $dates = "Juliol-Agost-Setembre";
                  }
                  else if ($mesD == 6 || $mesD == 5|| $mesD == 4) {
                    $mesDuo = "2T";
                    $dates = "Abril-Maig-Juny";
                  }
                  else if ($mesD == 3 || $mesD == 2 || $mesD == 1) {
                    $mesDuo = "1T";
                    $dates = "Gener-Febrer-Març";
                  }

                  if ($_POST[irpf] <= 0) {
                    $irpf2 = 0;
                  } else {
                    $irpf2 = $_POST[irpf];
                  }

                  if ($_POST[bestreta]!='X')
                  {
                    if ($_POST[a_pagar] >= 0 )
                    {
                      $pagat = $_POST[importnet];
                    }
                    else
                    {
                      $pagat=$_POST[bestreta];
                    }

                    $descripcio =  $mesDuo."/".$_POST[any];

                    $result = mysqli_query ($connexio,"INSERT INTO bestretes (CURS,AUTOR,DESCRIPCIO,QUANTITAT,DATA_PAG) VALUES
                    ('".$_POST[curs]."','".$_POST[dni]."','".$descripcio."',-".$pagat.",CURRENT_DATE)");

                    if($_POST[a_pagar]<=0)
                      $import_a_cobrar = $_POST[import_a_cobrar];

                    $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT,BESTRETA,APAGAR_REAL) VALUES
                    ('".$_POST[dni]."','D',".$_POST[any].",'".$_POST[curs]."','".$mesDuo."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf2,".$_POST[importnet].",CURRENT_DATE,1,'".$import_a_cobrar."')");
                  }
                  else
                  {
                    $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT) VALUES
                    ('".$_POST[dni]."','D',".$_POST[any].",'".$_POST[curs]."','".$mesDuo."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf2,".$_POST[importnet].",CURRENT_DATE)");
                  }

                  function form_mail()
                  {
                    $bHayFicheros = 0;
                    $sCabeceraTexto = "";
                    $sAdjuntos = "";

                    $titol = str_replace("\'","'",$_POST[titol]);
                    $dates = str_replace("\'","'",$_POST[dates]);

                    //$to = "suport.informatic@prisma.cat";
                    $to = "facturacio@prisma.cat, secretaria@prisma.cat, suport.informatic@prisma.cat, suport@prisma.cat";
										// $to = "meriem.prisma.cat@gmail.com";

                    $mesD = intval($_POST[mes]);
                    if ($mesD == 12 || $mesD == 11 || $mesD == 10) {
                      $mesDuo = "4T";
                      $dates = "Octubre-Novembre-Desembre";
                    }
                    else if ($mesD == 9 || $mesD == 8 || $mesD == 7) {
                      $mesDuo = "3T";
                      $dates = "Juliol-Agost-Setembre";
                    }
                    else if ($mesD == 6 || $mesD == 5|| $mesD == 4) {
                      $mesDuo = "2T";
                      $dates = "Abril-Maig-Juny";
                    }
                    else if ($mesD == 3 || $mesD == 2 || $mesD == 1) {
                      $mesDuo = "1T";
                      $dates = "Gener-Febrer-Març";
                    }

                    $subject = "Factura curs (".$_POST[any].$_POST[curs].$mesDuo.")";

                    if($_POST[bestreta]>0 and $_POST[a_pagar]>=0)
                      $import_a_cobrar = $_POST[import_a_cobrar];
                    else if ($_POST[bestreta]>0 and $_POST[a_pagar]>0)
                      $import_a_cobrar = $_POST[import_a_cobrar];
                    else
                      $import_a_cobrar = $_POST[importnet];

                    $headers = "From: ".$_POST[qui]." <".$_POST[correu].">\n";
                    $headers .= "MIME-version: 1.0\n";

                    if ($_POST[percentatge] <= 0 || $_POST[irpf] <= 0)
                    {
                      $sTexto = "<p>Factura enviada!</p>
                      <p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                      <p><strong>CURS:</strong> ".$titol."<br>
                      <strong>DATES:</strong> ".$dates."<br>
                      <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                      <strong>IMPORT (BRUT):</strong> ".$_POST[import]." euros<br>
                      <strong>IRPF (0%):</strong> No aplica<br>
                      <strong>IMPORT A COBRAR (NET):</strong> ".$import_a_cobrar." euros</p>
                      <p><em>Nota: cal comprovar que els imports del correu i la factura coincideixin.</em></p>";
                    }
                    else
                    {
                      $sTexto = "<p>Factura enviada!</p>
                      <p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                      <p><strong>CURS:</strong> ".$titol."<br>
                      <strong>DATES:</strong> ".$dates."<br>
                      <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                      <strong>IMPORT (BRUT):</strong> ".$_POST[import]." euros<br>
                      <strong>IRPF (".$_POST[percentatge]."%):</strong> ".$_POST[irpf]." euros<br>
                      <strong>IMPORT A COBRAR (NET):</strong> ".$import_a_cobrar." euros</p>
                      <p><em>Nota: cal comprovar que els imports del correu i la factura coincideixin.</em></p>";
                    }
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

                      $mesos_=intval($_POST[mes]);
                      if($MES<10) {
                        $pmes = "0".$mesos_;
                      }
                      else {
                        $pmes = $mesos_;
                      }

                      $any_data_actual = date("Y");
                      $mes_data_actual = date("m");

                      $dir_destino = './factures/';
                      $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
                      $modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';
                      $nom_tutor = utf8_decode(str_replace(' ','_',$_POST[qui]));
                      $nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
                      $nom_tutor = utf8_encode($nom_tutor);
                      $extension = substr(basename($_FILES['archivo1']['name']),-4,4);
                      $imagen_subida = $dir_destino.$any_data_actual.$mes_data_actual."_Curs_".$_POST[any].$_POST[curs].$mesDuo."_Autoria_Coordinacio_".$nom_tutor.$extension;

                      //Variables del metodo POST
                      if(!is_writable($dir_destino)){
                      }
                      else {
                        if(is_uploaded_file($_FILES['archivo1']['tmp_name'])){
                          if (move_uploaded_file($_FILES['archivo1']['tmp_name'], $imagen_subida)) {
														/*echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
														<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
														<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
														L'arxiu s'ha enviat correctament.
														</p></div></div>";*/
                          }
                        }
                      }
                    }

                    if ($bHayFicheros)
                      $sTexto .= $sAdjuntos."\n\n----_Separador-de-mensajes_----\n";
                    return(mail($to, $subject, $sTexto, $headers));

                  }

                  if (form_mail())
                  {
										echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
										<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
										<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
										Has enviat la factura correctament!.
										</p></div></div>";
                  }

                  ?><div align="center"><br /><input type="button" name="boto" class="botones boto-blau px-4 d-flex text-white" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='https://campus.prisma.cat/collaboradors/gestio-cobraments/'"/></div><?php

                }
              }

              //CONFIRMAR COBRAMENT AUTOR
              else if ($_POST['confirmar3']=="HI ESTIC D'ACORD")
              {
                $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                mysqli_set_charset ($connexio, "utf8");

                if (mysqli_connect_errno())
                {
                  echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                }
                else
                {
                  $mesA = intval($_POST[mes]);
                  if ($mesA == 12 || $mesA == 11 || $mesA == 10) {
                    $mesAutor = "4T";
                    $dates = "Octubre-Novembre-Desembre";
                  }
                  else if ($mesA == 9 || $mesA == 8 || $mesA == 7) {
                    $mesAutor = "3T";
                    $dates = "Juliol-Agost-Setembre";
                  }
                  else if ($mesA == 6 || $mesA == 5|| $mesA == 4) {
                    $mesAutor = "2T";
                    $dates = "Abril-Maig-Juny";
                  }
                  else if ($mesA == 3 || $mesA == 2 || $mesA == 1) {
                    $mesAutor = "1T";
                    $dates = "Gener-Febrer-Març";
                  }

                  if ($_POST[irpf] <= 0) {
                    $irpf3 = 0;
                  } else {
                    $irpf3 = $_POST[irpf];
                  }

                  if ($_POST[bestreta]!='X')
                  {
                    if ($_POST[a_pagar] >= 0 )
                    {
                      $pagat = $_POST[importnet];
                    }
                    else
                    {
                      $pagat=$_POST[bestreta];
                    }

                    $descripcio =  $mesAutor."/".$_POST[any];

                    $result = mysqli_query ($connexio,"INSERT INTO bestretes (CURS,AUTOR,DESCRIPCIO,QUANTITAT,DATA_PAG) VALUES
                    ('".$_POST[curs]."','".$_POST[dni]."','".$descripcio."',-".$pagat.",CURRENT_DATE)");

                    if($_POST[a_pagar]<=0)
                      $import_a_cobrar = $_POST[import_a_cobrar];

                    $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT,BESTRETA,APAGAR_REAL) VALUES
                    ('".$_POST[dni]."','A',".$_POST[any].",'".$_POST[curs]."','".$mesAutor."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf3,".$_POST[importnet].",CURRENT_DATE,1,'".$import_a_cobrar."')");
                  }
                  else
                  {
                    $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT) VALUES
                    ('".$_POST[dni]."','A',".$_POST[any].",'".$_POST[curs]."','".$mesAutor."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf3,".$_POST[importnet].",CURRENT_DATE)");
                  }

                  function form_mail()
                  {
                    $bHayFicheros = 0;
                    $sCabeceraTexto = "";
                    $sAdjuntos = "";

                    $titol = str_replace("\'","'",$_POST[titol]);
                    $dates = str_replace("\'","'",$_POST[dates]);

                    //$to = "suport.informatic@prisma.cat";
                    $to = "facturacio@prisma.cat, secretaria@prisma.cat, suport.informatic@prisma.cat, suport@prisma.cat";
										// $to = "meriem.prisma.cat@gmail.com";

                    $mesA = intval($_POST[mes]);
                    if ($mesA == 12 || $mesA == 11 || $mesA == 10) {
                      $mesAutor = "4T";
                      $dates = "Octubre-Novembre-Desembre";
                    }
                    else if ($mesA == 9 || $mesA == 8 || $mesA == 7) {
                      $mesAutor = "3T";
                      $dates = "Juliol-Agost-Setembre";
                    }
                    else if ($mesA == 6 || $mesA == 5|| $mesA == 4) {
                      $mesAutor = "2T";
                      $dates = "Abril-Maig-Juny";
                    }
                    else if ($mesA == 3 || $mesA == 2 || $mesA == 1) {
                      $mesAutor = "1T";
                      $dates = "Gener-Febrer-Març";
                    }

                    $subject = "Factura curs (".$_POST[any].$_POST[curs].$mesAutor.")";

                    if($_POST[bestreta]>0 and $_POST[a_pagar]>=0)
                      $import_a_cobrar = $_POST[import_a_cobrar];
                    else if ($_POST[bestreta]>0 and $_POST[a_pagar]>0)
                      $import_a_cobrar = $_POST[import_a_cobrar];
                    else
                      $import_a_cobrar = $_POST[importnet];

                    $headers = "From: ".$_POST[qui]." <".$_POST[correu].">\n";
                    $headers .= "MIME-version: 1.0\n";

                    if ($_POST[percentatge] <= 0 || $_POST[irpf] <= 0)
                    {
                      $sTexto = "<p>Factura enviada!</p>
                      <p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                      <p><strong>CURS:</strong> ".$titol."<br>
                      <strong>DATES:</strong> ".$dates."<br>
                      <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                      <strong>IMPORT (BRUT):</strong> ".$_POST[import]." euros<br>
                      <strong>IRPF (0%):</strong> No aplica<br>
                      <strong>IMPORT A COBRAR (NET):</strong> ".$import_a_cobrar." euros</p>
                      <p><em>Nota: cal comprovar que els imports del correu i la factura coincideixin.</em></p>";
                    }
                    else
                    {
                      $sTexto = "<p>Factura enviada!</p>
                      <p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                      <p><strong>CURS:</strong> ".$titol."<br>
                      <strong>DATES:</strong> ".$dates."<br>
                      <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                      <strong>IMPORT (BRUT):</strong> ".$_POST[import]." euros<br>
                      <strong>IRPF (".$_POST[percentatge]."%):</strong> ".$_POST[irpf]." euros<br>
                      <strong>IMPORT A COBRAR (NET):</strong> ".$import_a_cobrar." euros</p>
                      <p><em>Nota: cal comprovar que els imports del correu i la factura coincideixin.</em></p>";
                    }
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

                      $mesos_=intval($_POST[mes]);
                      if($MES<10) {
                        $pmes = "0".$mesos_;
                      }
                      else {
                        $pmes = $mesos_;
                      }

                      $any_data_actual = date("Y");
                      $mes_data_actual = date("m");

                      $dir_destino = './factures/';
                      $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
                      $modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';
                      $nom_tutor = utf8_decode(str_replace(' ','_',$_POST[qui]));
                      $nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
                      $nom_tutor = utf8_encode($nom_tutor);
                      $extension = substr(basename($_FILES['archivo1']['name']),-4,4);
                      $imagen_subida = $dir_destino.$any_data_actual.$mes_data_actual."_Curs_".$_POST[any].$_POST[curs].$mesAutor."_Autoria_".$nom_tutor.$extension;

                      //Variables del metodo POST
                      if(!is_writable($dir_destino)){
                      }
                      else {
                        if(is_uploaded_file($_FILES['archivo1']['tmp_name'])){
                          if (move_uploaded_file($_FILES['archivo1']['tmp_name'], $imagen_subida)) {
														/*echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
														<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
														<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
														L'arxiu s'ha enviat correctament.
														</p></div></div>";*/
                          }
                        }
                      }
                    }

                    if ($bHayFicheros)
                      $sTexto .= $sAdjuntos."\n\n----_Separador-de-mensajes_----\n";
                    return(mail($to, $subject, $sTexto, $headers));

                  }

                  if (form_mail())
                  {
										echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
										<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
										<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
										Has enviat la factura correctament!.
										</p></div></div>";
                  }

                  ?><div align="center"><br /><input type="button" name="boto" class="botones boto-blau px-4 d-flex text-white" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='https://campus.prisma.cat/collaboradors/gestio-cobraments/'"/></div><?php

                }
              }

              //CONFIRMAR COBRAMENT COORDINADOR
              else if ($_POST['confirmar4']=="HI ESTIC D'ACORD")
              {
                $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                mysqli_set_charset ($connexio, "utf8");

                if (mysqli_connect_errno())
                {
                  echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                }
                else
                {
                  $mesC = intval($_POST[mes]);
                  if ($mesC == 12 || $mesC == 11 || $mesC == 10) {
                    $mesCoord = "4T";
                    $dates = "Octubre-Novembre-Desembre";
                  }
                  else if ($mesC == 9 || $mesC == 8 || $mesC == 7) {
                    $mesCoord = "3T";
                    $dates = "Juliol-Agost-Setembre";
                  }
                  else if ($mesC == 6 || $mesC == 5|| $mesC == 4) {
                    $mesCoord = "2T";
                    $dates = "Abril-Maig-Juny";
                  }
                  else if ($mesC == 3 || $mesC == 2 || $mesC == 1) {
                    $mesCoord = "1T";
                    $dates = "Gener-Febrer-Març";
                  }

                  if ($_POST[irpf] <= 0) {
                    $irpf4 = 0;
                  } else {
                    $irpf4 = $_POST[irpf];
                  }

                  $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT) VALUES
                  ('".$_POST[dni]."','C',".$_POST[any].",'".$_POST[curs]."','".$mesCoord."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf4,".$_POST[importnet].",CURRENT_DATE)");

                  function form_mail()
                  {
                    $bHayFicheros = 0;
                    $sCabeceraTexto = "";
                    $sAdjuntos = "";

                    $titol = str_replace("\'","'",$_POST[titol]);
                    $dates = str_replace("\'","'",$_POST[dates]);

                    //$to = "suport.informatic@prisma.cat";
                    $to = "facturacio@prisma.cat, secretaria@prisma.cat, suport.informatic@prisma.cat, suport@prisma.cat";
										// $to = "meriem.prisma.cat@gmail.com";

                    $mesC = intval($_POST[mes]);
                    if ($mesC == 12 || $mesC == 11 || $mesC == 10) {
                      $mesCoord = "4T";
                      $dates = "Octubre-Novembre-Desembre";
                    }
                    else if ($mesC == 9 || $mesC == 8 || $mesC == 7) {
                      $mesCoord = "3T";
                      $dates = "Juliol-Agost-Setembre";
                    }
                    else if ($mesC == 6 || $mesC == 5|| $mesC == 4) {
                      $mesCoord = "2T";
                      $dates = "Abril-Maig-Juny";
                    }
                    else if ($mesC == 3 || $mesC == 2 || $mesC == 1) {
                      $mesCoord = "1T";
                      $dates = "Gener-Febrer-Març";
                    }

                    $subject = "Factura curs (".$_POST[any].$_POST[curs].$mesCoord.")";

                    $import_a_cobrar = $_POST[importnet];

                    $headers = "From: ".$_POST[qui]." <".$_POST[correu].">\n";
                    $headers .= "MIME-version: 1.0\n";

                    if ($_POST[percentatge] <= 0 || $_POST[irpf] <= 0)
                    {
                      $sTexto = "<p>Factura enviada!</p>
                      <p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                      <p><strong>CURS:</strong> ".$titol."<br>
                      <strong>DATES:</strong> ".$dates."<br>
                      <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                      <strong>IMPORT (BRUT):</strong> ".$_POST[import]." euros<br>
                      <strong>IRPF (0%):</strong> No aplica<br>
                      <strong>IMPORT A COBRAR (NET):</strong> ".$import_a_cobrar." euros</p>
                      <p><em>Nota: cal comprovar que els imports del correu i la factura coincideixin.</em></p>";
                    }
                    else
                    {
                      $sTexto = "<p>Factura enviada!</p>
                      <p style=text-decoration:underline><strong>DADES DEL CURS</strong></p>
                      <p><strong>CURS:</strong> ".$titol."<br>
                      <strong>DATES:</strong> ".$dates."<br>
                      <strong>TOTAL ALUMNES INSCRITS:</strong> ".$_POST[inscrits]."<br>
                      <strong>IMPORT (BRUT):</strong> ".$_POST[import]." euros<br>
                      <strong>IRPF (".$_POST[percentatge]."%):</strong> ".$_POST[irpf]." euros<br>
                      <strong>IMPORT A COBRAR (NET):</strong> ".$import_a_cobrar." euros</p>
                      <p><em>Nota: cal comprovar que els imports del correu i la factura coincideixin.</em></p>";
                    }
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

                      $mesos_=intval($_POST[mes]);
                      if($MES<10) {
                        $pmes = "0".$mesos_;
                      }
                      else {
                        $pmes = $mesos_;
                      }

                      $any_data_actual = date("Y");
                      $mes_data_actual = date("m");

                      $dir_destino = './factures/';
                      $originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
                      $modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';
                      $nom_tutor = utf8_decode(str_replace(' ','_',$_POST[qui]));
                      $nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
                      $nom_tutor = utf8_encode($nom_tutor);
                      $extension = substr(basename($_FILES['archivo1']['name']),-4,4);
                      $imagen_subida = $dir_destino.$any_data_actual.$mes_data_actual."_Curs_".$_POST[any].$_POST[curs].$mesCoord."_Coordinacio_".$nom_tutor.$extension;

                      //Variables del metodo POST
                      if(!is_writable($dir_destino)){
                      }
                      else {
                        if(is_uploaded_file($_FILES['archivo1']['tmp_name'])){
                          if (move_uploaded_file($_FILES['archivo1']['tmp_name'], $imagen_subida)) {
														/*echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
														<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
														<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
														L'arxiu s'ha enviat correctament.
														</p></div></div>";*/
                          }
                        }
                      }
                    }

                    if ($bHayFicheros)
                      $sTexto .= $sAdjuntos."\n\n----_Separador-de-mensajes_----\n";
                    return(mail($to, $subject, $sTexto, $headers));

                  }

                  if (form_mail())
                  {
										echo "<div class='card card-blau mt-0 pt-2'><div class='card-body px-0 d-flex flex-column align-items-center justify-content-center'>
										<img src='https://campus.prisma.cat/intranet/img/ok.png' class='w-25 mb-4'>
										<p class='text-center font-weight-bold mt-2 mb-0' style='font-size: 1.2rem;'>
										Has enviat la factura correctament!.
										</p></div></div>";
                  }

                  ?><div align="center"><br /><input type="button" name="boto" class="botones boto-blau px-4 d-flex text-white" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='https://campus.prisma.cat/collaboradors/gestio-cobraments/'"/></div><?php

                }
              }

              //GESTIONAR
              else if ($_POST['gestiook']=="S")
              {
                //Gestió d'una tutoria
                if ($_POST[gestio."$_POST[num]"]=="GESTIONAR")
                {
                  $n=$_POST[num];

                  $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                  mysqli_set_charset ($connexio, "utf8");

                  if (mysqli_connect_errno())
                  {
                    echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                  }
                  else // mostrem les dades del curs a gestionar
                  {
                    $result = mysqli_query ($connexio,"SELECT c.CURS, c.MES, c.ANY, `NOM CURS` AS titol, AULA,
                       Data_llarga_Inici, Data_llarga_Fin, data_informe, DNI_TUTOR, id_cuho, SUM(CASE WHEN (`INSC CURS` = '1' OR `INSC CURS` = 'M') THEN 1 ELSE 0 END) inscrits
                       FROM cursos AS c, inscripcions AS i WHERE ".$text." and c.CURS='".$_POST[curs_tut."$n"]."'
                       AND c.ANY=".$_POST[any_tut."$n"]." AND c.MES='".$_POST[mes_tut."$n"]."' AND pagat='N' AND
                       c.CURS=i.CURS AND c.ANY=i.ANY AND c.MES=i.MES AND AULA=Grup GROUP BY Grup");

                    $inscrits_totals = 0;
                    $informe = "";

                    if(mysqli_num_rows($result)>0)
                    {
                      while ($row = mysqli_fetch_array($result))
                      {
                        $curs = $row['CURS'];
                        $any = $row['ANY'];
                        $mes = $row['MES'];
                        $titol = $row['titol'];
                        $dates = "Del dia ".$row['Data_llarga_Inici']." al dia ".$row['Data_llarga_Fin'];
                        if ($row['CURS']=='NEURO') {
                          $dni = $nif;
                          $id_cuho = $cuho;
                        }
						else if ($row['CURS']=='GED') {
                          $dni = $nif;
                          $id_cuho = $cuho;
                        }
                        else {
                          $dni = $row['DNI_TUTOR'];
                          $id_cuho = $row['id_cuho'];
                        }

                        $inscrits_totals = $inscrits_totals + $row['inscrits'];
                        if (is_null($row['data_informe']))
                          $informe .= strtolower($row['AULA']);
                      }
                    }

								echo "<div id='contacte' class='card card-blau mt-5 d-flex flex-column justify-content-center align-items-center'>
									<div class='d-flex flex-column justify-content-center align-items-center w-100 text-center border-bottom card-header'>
										<p class='title font-weight-bold text-center py-3 mb-0 align-items-center
										justify-content-center d-flex'>Gestiona el curs</p>
									</div>
									<div class='card-body px-0 w-100 ps2'>
										<div class='d-flex flex-column justify-content-center align-items-center my-2'>
											";
                       echo "<p class='w-100 text-left'><strong>CURS:</strong> ".$titol."</p>";
                       echo "<p class='w-100 text-left'><strong>DATES:</strong> ".$dates."</p>";
                       echo "<p class='w-100 text-left'><strong>TOTAL ALUMNES INSCRITS:</strong> ".$inscrits_totals."</p>";

                    if ($informe != "")
                    {
                      $quant = strlen($informe);
                      $inf="";

                      for ($i=0; $i<$quant; $i++)
                      {
                        $inf .= "<a href=https://campus.prisma.cat/intranet-collaboradors/informes/".strtolower($curs).".php?shortname=".$any.strtoupper($curs).$mes.$informe[$i]." target=_blank>".$any.$curs.$mes.strtoupper($informe[$i])."</a> ";
                      }

                      echo "<p class='w-100 text-left'><strong>INFORMES:</strong> ".$inf."</p>";
                    }
                    else
                    {
                      if (mysqli_num_rows($result)>1)
                        echo "<p class='w-100 text-left'><strong>INFORMES:</strong> enviats</p>";
                      else
                        echo "<p class='w-100 text-left'><strong>INFORME:</strong> enviat</p>";
                    }

                    $result_i = mysqli_query ($connexio,"SELECT PREU_ALUMNE AS preu, IRPF, NOM, COGNOMS, MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT FROM
                       (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID)
                       INNER JOIN personal as p ON h.DNI_TUTOR = p.DNI
                       WHERE r.id_cuho=".$id_cuho." AND h.PERFIL='tutor'");

                    $row_i = mysqli_fetch_array($result_i);

                    $row_irpf_username = $row_i['IRPF'];
                    $row_IBAN_username = $row_i['IBAN'];
                    $row_OBS_COBRAMENT_username = $row_i['OBS_COBRAMENT'];
                    $row_nom_username = $row_i['NOM'];
                    $row_cog_username = $row_i['COGNOMS'];
                    $row_correu_username = $row_i['correu'];

                    if ( $_POST[mes_tut."$n"] == '04' || $_POST[mes_tut."$n"] == '05' || $_POST[mes_tut."$n"] == '06' ) {
                       $import = $inscrits_totals * $row_i['preu'];
                    }
                    else {
                       if ($inscrits_totals >=15 || $curs == 'GED')
                         $import = $inscrits_totals * $row_i['preu'];
                       else
                       {
                         $import = 15 * $row_i['preu'];
                         $nota = "<p align=left style=color:#666666><em>NOTA: es cobra un mínim de 15 alumnes per edició.</em></p>";
                       }
                    }

                    if ( $curs == 'SUI' ) {
                       //
                       $result_preu_fix_sui = mysqli_query ($connexio,"SELECT VALOR AS preu FROM params WHERE TIPUS = 'preu-fix-edicio-sui' AND (DATAI <= CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME <= DATAF))");
                       $row_preu_fix_sui = mysqli_fetch_array($result_preu_fix_sui);

                       $import = $row_preu_fix_sui['preu'];

                       $nota = "<p align=left style=color:#666666><em>NOTA: en aquest curs es cobra un preu fix per edició.</em></p>";
                    }

					if ( $curs == 'SDA' ) {
                       //
                       $result_preu_fix_sda = mysqli_query ($connexio,"SELECT VALOR AS preu FROM params WHERE TIPUS = 'preu-fix-edicio-sda' AND (DATAI <= CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME <= DATAF))");
                       $row_preu_fix_sda = mysqli_fetch_array($result_preu_fix_sda);

                       $import = $row_preu_fix_sda['preu'];

                       $nota = "<p align=left style=color:#666666><em>NOTA: en aquest curs es cobra un preu fix per edició.</em></p>";
                    }

                    if ( $curs == 'GED' ) {

                       $result_preu_fix_ged = mysqli_query ($connexio,"SELECT VALOR AS preu FROM params WHERE TIPUS = 'preu-fix-edicio-ged' AND (DATAI <= CURRENT_TIME AND (DATAF IS NULL OR CURRENT_TIME <= DATAF))");
                       $row_preu_fix_ged = mysqli_fetch_array($result_preu_fix_ged);

                       $import += $row_preu_fix_ged['preu'];

                       $nota = "<p align=left style=color:#666666><em>NOTA: en aquest curs es cobra un preu fix amb un increment per alumne per edició.</em></p>";

                    }

                    $irpf = ($import * $row_i['IRPF']) / 100;
                    if ($irpf <= 0)
                    {
                      $importnet = $import;
                    }
                    else
                    {
                      $importnet = $import - $irpf;
                    }

                  }
                }
                else if ($_POST[gestio2."$_POST[num]"]=="GESTIONAR") //Gestió d'un duo
                {
                  $n=$_POST[num];
                  $mes_num=$_POST[mes_num];

                  $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                  mysqli_set_charset ($connexio, "utf8");

                  if (mysqli_connect_errno())
                  {
                    echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                  }
                  else // mostrem les dades del curs a gestionar
                  {
                    $curs = $_POST[curs_duo."$mes_num"];
                    $any = $_POST[any_duo."$mes_num"];
                    $mes =  $_POST[mes_duo."$mes_num"];

                    if ($mes=='01' || $mes=='02' || $mes=='03')
                    {
                      $mesD = 3;
                      $trimestre="1er trimestre: Gener-Febrer-Març";
                    }
                    else if ($mes=='04' || $mes=='05' || $mes=='06')
                    {
                      $mesD = 6;
                      $trimestre="2nd trimestre: Abril-Maig-Juny";
                    }
                    else if ($mes=='07' || $mes=='08' || $mes=='09')
                    {
                      $mesD = 9;
                      $trimestre="3er trimestre: Juliol-Agost-Setembre";
                    }
                    else if ($mes=='10' || $mes=='11' || $mes=='12')
                    {
                      $mesD = 12;
                      $trimestre="4t trimestre: Octubre-Novembre-Desembre";
                    }

                    $dates=$trimestre;

                    if ($mesD > 10)
                    {
                      $mes1 = strval($mesD-2);
                      $mes2 = strval($mesD-1);
                      $mes3 = strval($mesD);
                    }
                    else
                    {
                      $mes1 = "0".strval($mesD-2);
                      $mes2 = "0".strval($mesD-1);
                      $mes3 = "0".strval($mesD);
                    }

                    $result_dades_duo = mysqli_query ($connexio, "SELECT c.CURS, c.ANY, `NOM CURS` AS titol, AULA, Data_llarga_Inici, Data_llarga_Fin, data_informe, c.id_cuho, r.id_hono, h.perfil, h.DNI_TUTOR, PREU_ALUMNE as preu, IRPF, p.NOM, p.COGNOMS, p.MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT, SUM(CASE WHEN (`INSC CURS` = '1' OR `INSC CURS` = 'M') THEN 1 ELSE 0 END) inscrits FROM (((cursos AS c INNER JOIN inscripcions AS i ON c.CURS = i.CURS AND c.MES = i.MES AND c.ANY = i.ANY AND i.Grup=c.AULA) INNER JOIN rel_cuho as r ON c.id_cuho = r.id_cuho) INNER JOIN honoraris as h ON h.id = r.id_hono ) INNER JOIN personal as p ON (h.DNI_TUTOR = p.DNI) WHERE c.CURS='".$curs."' AND c.ANY=".$any." AND (c.MES='".$mes1."' or c.MES='".$mes2."' or c.MES='".$mes3."') AND pagat='N' AND h.perfil='duo' AND ".$text_h." GROUP BY r.id_hono");

                    $inscrits_totals = 0;
                    $import = 0;
                    $irpf = 0;
                    $row_irpf_username = 0;

                    while ($row_dades_duo = mysqli_fetch_array($result_dades_duo))
                    {
                      $titol = $row_dades_duo['titol'];
                      $inscrits = $row_dades_duo['inscrits'];
                      $import_unic = $inscrits * $row_dades_duo['preu'];
                      $irpf_unic = ($import_unic * $row_dades_duo['IRPF']) / 100;
                      $row_irpf_username = $row_irpf_username + $row_dades_duo['IRPF'];

                      $inscrits_totals = $inscrits_totals + $inscrits;
                      $import = $import + $import_unic;
                      $irpf = $irpf + $irpf_unic;

                      $row_IBAN_username = $row_dades_duo['IBAN'];
                      $row_OBS_COBRAMENT_username = $row_dades_duo['OBS_COBRAMENT'];
                      $row_nom_username = $row_dades_duo['NOM'];
                      $row_cog_username = $row_dades_duo['COGNOMS'];
                      $row_correu_username = $row_dades_duo['correu'];
                      $dni = $row_dades_duo['DNI_TUTOR'];
                    }

                    if ($irpf <= 0)
                    {
                      $importnet = $import;
                    }
                    else
                    {
                      $importnet = $import - $irpf;
                    }

                    echo "<p class='w-100 text-left'><strong>CURS:</strong> ".$titol."</p>";
                    echo "<p class='w-100 text-left'><strong>TRIMESTRE:</strong> ".$trimestre."</p>";
                    echo "<p class='w-100 text-left'><strong>TOTAL ALUMNES INSCRITS:</strong> ".$inscrits_totals."</p>";

                    $bestreta = 'X';
                    $result_b = mysqli_query ($connexio,"SELECT SUM(QUANTITAT) AS bestreta, PAGAT FROM bestretes WHERE AUTOR LIKE '".$USER->username."%' AND CURS ='".$curs."'");
                    $row_b = mysqli_fetch_array($result_b);

                    if (mysqli_num_rows($result_b)>0 and $row_b['bestreta']>0)
                    {
                      $bestreta = $row_b['bestreta'];

                      $a_pagar = $bestreta - $importnet;

                      if ($a_pagar >= 0 )
                      {
                        $pendent_bestreta = $bestreta - $importnet;
                        $import_a_cobrar = 0;
                      }
                      else
                      {
                        $pendent_bestreta= 0;
                        $import_a_cobrar = $importnet - $bestreta;
                      }
                      //echo 'Bestreta: '.$bestreta.' Import net:'.$importnet.' Pendent bestreta:'.$pendent_bestreta.' Import a cobrar:'.$import_a_cobrar;
                    }
                  }

                }
                else if ($_POST[gestio3."$_POST[num]"]=="GESTIONAR") //Gestió d'un autor
                {
                  $n=$_POST[num];
                  $mes_num=$_POST[mes_num];

                  $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                  mysqli_set_charset ($connexio, "utf8");

                  if (mysqli_connect_errno())
                  {
                    echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                  }
                  else // mostrem les dades del curs a gestionar
                  {
                    $curs = $_POST[curs_aut."$mes_num"];
                    $any = $_POST[any_aut."$mes_num"];
                    $mes =  $_POST[mes_aut."$mes_num"];

                    if ($mes=='01' || $mes=='02' || $mes=='03')
                    {
                      $mesA = 3;
                      $trimestre="1er trimestre: Gener-Febrer-Març";
                    }
                    else if ($mes=='04' || $mes=='05' || $mes=='06')
                    {
                      $mesA = 6;
                      $trimestre="2nd trimestre: Abril-Maig-Juny";
                    }
                    else if ($mes=='07' || $mes=='08' || $mes=='09')
                    {
                      $mesA = 9;
                      $trimestre="3er trimestre: Juliol-Agost-Setembre";
                    }
                    else if ($mes=='10' || $mes=='11' || $mes=='12')
                    {
                      $mesA = 12;
                      $trimestre="4t trimestre: Octubre-Novembre-Desembre";
                    }

                    $dates=$trimestre;

                    if ($mesA > 10)
                    {
                      $mes1 = strval($mesA-2);
                      $mes2 = strval($mesA-1);
                      $mes3 = strval($mesA);
                    }
                    else
                    {
                      $mes1 = "0".strval($mesA-2);
                      $mes2 = "0".strval($mesA-1);
                      $mes3 = "0".strval($mesA);
                    }

                    $result_dades_autor = mysqli_query ($connexio, "SELECT c.CURS, c.ANY, `NOM CURS` AS titol, AULA, Data_llarga_Inici, Data_llarga_Fin, data_informe, c.id_cuho, r.id_hono, h.perfil, h.DNI_TUTOR, PREU_ALUMNE as preu, IRPF, p.NOM, p.COGNOMS, p.MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT, SUM(CASE WHEN (`INSC CURS` = '1' OR `INSC CURS` = 'M') THEN 1 ELSE 0 END) inscrits FROM (((cursos AS c INNER JOIN inscripcions AS i ON c.CURS = i.CURS AND c.MES = i.MES AND c.ANY = i.ANY AND i.Grup=c.AULA) INNER JOIN rel_cuho as r ON c.id_cuho = r.id_cuho) INNER JOIN honoraris as h ON h.id = r.id_hono ) INNER JOIN personal as p ON (h.DNI_TUTOR = p.DNI) WHERE c.CURS='".$curs."' AND c.ANY=".$any." AND (c.MES='".$mes1."' or c.MES='".$mes2."' or c.MES='".$mes3."') AND pagat='N' AND h.perfil='autor' AND ".$text_ht." GROUP BY r.id_hono");

                    $inscrits_totals = 0;
                    $import = 0;
                    $irpf = 0;
                    $row_irpf_username = 0;

                    while ($row_dades_autor = mysqli_fetch_array($result_dades_autor))
                    {
                      $titol = $row_dades_autor['titol'];
                      $inscrits = $row_dades_autor['inscrits'];
                      $import_unic = $inscrits * $row_dades_autor['preu'];
                      $irpf_unic = ($import_unic * $row_dades_autor['IRPF']) / 100;
                      $row_irpf_username = $row_irpf_username + $row_dades_autor['IRPF'];

                      $inscrits_totals = $inscrits_totals + $inscrits;
                      $import = $import + $import_unic;
                      $irpf = $irpf + $irpf_unic;

                      $row_IBAN_username = $row_dades_autor['IBAN'];
                      $row_OBS_COBRAMENT_username = $row_dades_autor['OBS_COBRAMENT'];
                      $row_nom_username = $row_dades_autor['NOM'];
                      $row_cog_username = $row_dades_autor['COGNOMS'];
                      $row_correu_username = $row_dades_autor['correu'];
                      $dni = $row_dades_autor['DNI_TUTOR'];
                    }

                    if ($irpf <= 0)
                    {
                      $importnet = $import;
                    }
                    else
                    {
                      $importnet = $import - $irpf;
                    }

                    echo "<p class='w-100 text-left'><strong>CURS:</strong> ".$titol."</p>";
                    echo "<p class='w-100 text-left'><strong>TRIMESTRE:</strong> ".$trimestre."</p>";
                    echo "<p class='w-100 text-left'><strong>TOTAL ALUMNES INSCRITS:</strong> ".$inscrits_totals."</p>";

                    $bestreta = 'X';
                    $result_b = mysqli_query ($connexio,"SELECT SUM(QUANTITAT) AS bestreta, PAGAT FROM bestretes WHERE AUTOR LIKE '".$USER->username."%' AND CURS ='".$curs."'");
                    $row_b = mysqli_fetch_array($result_b);

                    if (mysqli_num_rows($result_b)>0 and $row_b['bestreta']>0)
                    {
                      $bestreta = $row_b['bestreta'];

                      $a_pagar = $bestreta - $importnet;

                      if ($a_pagar >= 0 )
                      {
                        $pendent_bestreta = $bestreta - $importnet;
                        $import_a_cobrar = 0;
                      }
                      else
                      {
                        $pendent_bestreta= 0;
                        $import_a_cobrar = $importnet - $bestreta;
                      }
                      //echo 'Bestreta: '.$bestreta.' Import net:'.$importnet.' Pendent bestreta:'.$pendent_bestreta.' Import a cobrar:'.$import_a_cobrar;
                    }
                  }

                }
                else if ($_POST[gestio4."$_POST[num]"]=="GESTIONAR") //Gestió d'un coordinador
                {
                  $n=$_POST[num];
                  $mes_num=$_POST[mes_num];

                  $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                  mysqli_set_charset ($connexio, "utf8");

                  if (mysqli_connect_errno())
                  {
                    echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                  }
                  else // mostrem les dades del curs a gestionar
                  {

                    $curs = $_POST[curs_coord."$mes_num"];
                    $any = $_POST[any_coord."$mes_num"];
                    $mes =  $_POST[mes_coord."$mes_num"];

                    if ($mes=='01' || $mes=='02' || $mes=='03')
                    {
                      $mesC = 3;
                      $trimestre="1er trimestre: Gener-Febrer-Març";
                    }
                    else if ($mes=='04' || $mes=='05' || $mes=='06')
                    {
                      $mesC = 6;
                      $trimestre="2nd trimestre: Abril-Maig-Juny";
                    }
                    else if ($mes=='07' || $mes=='08' || $mes=='09')
                    {
                      $mesC = 9;
                      $trimestre="3er trimestre: Juliol-Agost-Setembre";
                    }
                    else if ($mes=='10' || $mes=='11' || $mes=='12')
                    {
                      $mesC = 12;
                      $trimestre="4t trimestre: Octubre-Novembre-Desembre";
                    }

                    $dates=$trimestre;

                    if ($mesC > 10)
                    {
                      $mes1 = strval($mesC-2);
                      $mes2 = strval($mesC-1);
                      $mes3 = strval($mesC);
                    }
                    else
                    {
                      $mes1 = "0".strval($mesC-2);
                      $mes2 = "0".strval($mesC-1);
                      $mes3 = "0".strval($mesC);
                    }

                    $result_dades_coord = mysqli_query ($connexio, "SELECT c.CURS, c.ANY, `NOM CURS` AS titol, AULA, Data_llarga_Inici, Data_llarga_Fin, data_informe, c.id_cuho, r.id_hono, h.perfil, h.DNI_TUTOR, PREU_ALUMNE as preu, IRPF, p.NOM, p.COGNOMS, p.MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT, SUM(CASE WHEN (`INSC CURS` = '1' OR `INSC CURS` = 'M') THEN 1 ELSE 0 END) inscrits FROM (((cursos AS c INNER JOIN inscripcions AS i ON c.CURS = i.CURS AND c.MES = i.MES AND c.ANY = i.ANY AND i.Grup=c.AULA) INNER JOIN rel_cuho as r ON c.id_cuho = r.id_cuho) INNER JOIN honoraris as h ON h.id = r.id_hono ) INNER JOIN personal as p ON (h.DNI_TUTOR = p.DNI) WHERE c.CURS='".$curs."' AND c.ANY=".$any." AND (c.MES='".$mes1."' or c.MES='".$mes2."' or c.MES='".$mes3."') AND pagat='N' AND h.perfil='coord' AND ".$text_h." GROUP BY r.id_hono");

                    $inscrits_totals = 0;
                    $import = 0;
                    $irpf = 0;
                    $row_irpf_username = 0;

                    while ($row_dades_coord = mysqli_fetch_array($result_dades_coord))
                    {
                      $titol = $row_dades_coord['titol'];
                      $inscrits = $row_dades_coord['inscrits'];
                      $import_unic = $inscrits * $row_dades_coord['preu'];
                      $irpf_unic = ($import_unic * $row_dades_coord['IRPF']) / 100;
                      $row_irpf_username = $row_irpf_username + $row_dades_coord['IRPF'];

                      $inscrits_totals = $inscrits_totals + $inscrits;
                      $import = $import + $import_unic;
                      $irpf = $irpf + $irpf_unic;

                      $row_IBAN_username = $row_dades_coord['IBAN'];
                      $row_OBS_COBRAMENT_username = $row_dades_coord['OBS_COBRAMENT'];
                      $row_nom_username = $row_dades_coord['NOM'];
                      $row_cog_username = $row_dades_coord['COGNOMS'];
                      $row_correu_username = $row_dades_coord['correu'];
                      $dni = $row_dades_coord['DNI_TUTOR'];
                    }

                    if ($irpf <= 0)
                    {
                      $importnet = $import;
                    }
                    else
                    {
                      $importnet = $import - $irpf;
                    }

                    echo "<p class='w-100 text-left'><strong>CURS:</strong> ".$titol."</p>";
                    echo "<p class='w-100 text-left'><strong>TRIMESTRE:</strong> ".$trimestre."</p>";
                    echo "<p class='w-100 text-left'><strong>TOTAL ALUMNES INSCRITS:</strong> ".$inscrits_totals."</p>";
                  }

                }


                ?>
                <form class='w-100' name="gestions" method="post" action="<?php echo $PHP_SELF ?>" enctype="multipart/form-data" onSubmit="validar(this)">
                  <p class='w-100 text-left'><strong>IMPORT (BRUT):</strong> <?php echo $import; ?> euros</p>
                  <?php echo $nota;
                  if ($row_irpf_username < 0)
                  {
                    ?><p class='w-100 text-left'><strong>IRPF: </strong>No aplica</p><?php
                  }
                  else
                  {
                    ?><p class='w-100 text-left'><strong>IRPF (<?php echo $row_irpf_username; ?>%):</strong> <?php echo $irpf; ?> euros</p><?php
                  }
                  ?>
                  <p class='w-100 text-left'><strong>IMPORT (NET):</strong> <?php echo $importnet; ?> euros</p>
                  <?php if ($bestreta != 'X')
                  {
                    ?><p class='w-100 text-left'><strong>BESTRETA: </strong> <?php echo $bestreta; ?> euros</p>
                    <p class='w-100 text-left'><strong>PENDENT BESTRETA: </strong> <?php echo $pendent_bestreta; ?> euros</p>
                    <p class='w-100 text-left'><strong>IMPORT A COBRAR: </strong> <?php echo $import_a_cobrar; ?> euros</p><?php
                  }
                  ?>
                  <p class='w-100 text-left'><strong>IBAN:</strong> <?php echo $row_IBAN_username ?></p>
                  <p class='w-100 text-left'><strong>OBSERVACIONS COBRAMENT:</strong> <?php if ($row_OBS_COBRAMENT_username == NULL || $row_OBS_COBRAMENT_username == "") { echo "No n'hi ha cap"; } else { echo $row_OBS_COBRAMENT_username; } ?></p>
                  <p class='w-100 text-left'><br />Adjuntar factura/rebut: <input type='file' name='archivo1' id='archivo1' accept=".pdf"></p>

                  <?php
                  if ($_POST[gestio."$_POST[num]"]=="GESTIONAR")
                  {
                    ?><div align="center"><br /><input type="submit" name="confirmar" class="botones boto-blau px-4 d-flex" value="HI ESTIC D'ACORD" onclick="return comprovar_validar()"/></div><?php
                  }
                  if ($_POST[gestio2."$_POST[num]"]=="GESTIONAR")
                  {
                    ?><div align="center"><br /><input type="submit" name="confirmar2" class="botones boto-blau px-4 d-flex" value="HI ESTIC D'ACORD" onclick="return comprovar_validar()"/></div><?php
                  }
                  if ($_POST[gestio3."$_POST[num]"]=="GESTIONAR")
                  {
                    ?><div align="center"><br /><input type="submit" name="confirmar3" class="botones boto-blau px-4 d-flex" value="HI ESTIC D'ACORD" onclick="return comprovar_validar()"/></div><?php
                  }
                  if ($_POST[gestio4."$_POST[num]"]=="GESTIONAR")
                  {
                    ?><div align="center"><br /><input type="submit" name="confirmar4" class="botones boto-blau px-4 d-flex" value="HI ESTIC D'ACORD" onclick="return comprovar_validar()"/></div><?php
                  }
                  ?>

                  <input type="hidden" name="curs" value="<?php echo $curs; ?>" />
                  <input type="hidden" name="any" value="<?php echo $any; ?>" />
                  <input type="hidden" name="mes" value="<?php echo $mes; ?>" />
                  <input type="hidden" name="titol" value="<?php echo $titol; ?>" />
                  <input type="hidden" name="dates" value="<?php echo $dates; ?>" />
                  <input type="hidden" name="trimestre" value="<?php echo $trimestre; ?>" />
                  <input type="hidden" name="semestre" value="<?php echo $semestre; ?>" />
                  <input type="hidden" name="inscrits" value="<?php echo $inscrits_totals; ?>" />
                  <input type="hidden" name="informe" value="<?php echo $informe; ?>" />
                  <input type="hidden" name="import" value="<?php echo $import; ?>" />
                  <input type="hidden" name="irpf" value="<?php echo $irpf; ?>" />
                  <input type="hidden" name="percentatge" value="<?php echo $row_irpf_username ?>" />
                  <input type="hidden" name="importnet" value="<?php echo $importnet; ?>" />

                  <input type="hidden" name="bestreta" value="<?php echo $bestreta; ?>" />
                  <input type="hidden" name="a_pagar" value="<?php echo $a_pagar; ?>" />
                  <input type="hidden" name="pendent_bestreta" value="<?php echo $pendent_bestreta; ?>" />
                  <input type="hidden" name="import_a_cobrar" value="<?php echo $import_a_cobrar; ?>" />

                  <input type="hidden" name="dni" value="<?php echo $dni; ?>" />
                  <input type="hidden" name="correu" value="<?php echo $row_correu_username; ?>" />
                  <input type="hidden" name="qui" value="<?php echo $row_nom_username." ".$row_cog_username; ?>" />

                  <p class='w-100 text-left'><br /><br />Hi ha alguna dada incorrecta? (Quantitat d'alumnes, número de compte, el botó «HI ESTIC D'ACORD» està desactivat...) Pots especificar-ho en el camp següent i clicar al botó «ENVIAR PER A REVISIÓ».</p>
                  <textarea name="comentaris" style="width:990px; height:65px"></textarea>

                  <?php
                  if ($_POST[gestio."$_POST[num]"]=="GESTIONAR")
                  {
                    ?><div align="center"><br /><input type="submit" name="revisio" class="botones boto-blau px-4 d-flex" value="ENVIAR PER A REVISIÓ" onclick="return  comprovar_revisar(this.form)"/></div><?php
                  }
                  if ($_POST[gestio2."$_POST[num]"]=="GESTIONAR")
                  {
                    ?><div align="center"><br /><input type="submit" name="revisio2" class="botones boto-blau px-4 d-flex" value="ENVIAR PER A REVISIÓ" onclick="return  comprovar_revisar(this.form)"/></div><?php
                  }
                  if ($_POST[gestio3."$_POST[num]"]=="GESTIONAR")
                  {
                    ?><div align="center"><br /><input type="submit" name="revisio3" class="botones boto-blau px-4 d-flex" value="ENVIAR PER A REVISIÓ" onclick="return  comprovar_revisar(this.form)"></div><?php
                  }
                  if ($_POST[gestio4."$_POST[num]"]=="GESTIONAR")
                  {
                    ?><div align="center"><br /><input type="submit" name="revisio4" class="botones boto-blau px-4 d-flex" value="ENVIAR PER A REVISIÓ" onclick="return  comprovar_revisar(this.form)"></div><?php
                  }
                  ?>
                  <input type="hidden" name="revisio_o_gestionar" id="revisio_o_gestionar" value="0" />
                </form>
                <?php
								echo "</div></div></div>";
              }

              else {

                $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                mysqli_set_charset ($connexio, "utf8");

                if (mysqli_connect_errno())
                {
                  echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport.informatic@prisma.cat. <br>Disculpeu les molèsties.";
                }
                else
                {
                  //Buquem tots els cursos relacionats, dels quals són tutors
                  if ($USER->username == '40526355')
                      $tutories = mysqli_query ($connexio, "SELECT c.ANY, c.CURS, c.MES, `DATA INICI` AS datai, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, h.DNI_TUTOR, c.id_cuho, h.preu_alumne, h.irpf, h.perfil FROM cursos as c, honoraris as h, rel_cuho as r WHERE ".$text_h." AND (DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0 ) AND c.pagat = 'N' AND PERFIL = 'tutor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id AND c.ANY>2019 GROUP BY c.CURS, c.ANY, c.MES ORDER BY c.MES, c.CURS, c.ANY");
                  else
                    $tutories = mysqli_query ($connexio, "SELECT c.ANY, c.CURS, c.MES, `DATA INICI` AS datai, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, h.DNI_TUTOR, c.id_cuho, h.preu_alumne, h.irpf, h.perfil FROM cursos as c, honoraris as h, rel_cuho as r WHERE ".$text_h." AND (DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0 ) AND c.pagat = 'N' AND PERFIL = 'tutor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY c.CURS, c.ANY, c.MES ORDER BY c.MES, c.CURS, c.ANY");

                  //Busquem tots els cursos duo relacionats amb la persona
                  //$duos = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE h.DNI_TUTOR LIKE '".$dni_tutor_username."' AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat =  'N'  AND perfil =  'duo' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");
                  $duos = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE ".$text_h." AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0) AND pagat = 'N' AND perfil = 'duo' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS");

                  //Busquem tots els cursos autoria relacionats amb la persona
                  //$autories = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE h.DNI_TUTOR LIKE '".$dni_tutor_username."' AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'autor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id ORDER BY c.MES, c.CURS, c.ANY");


                  $autories = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE ".$text_ht." AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0) AND pagat = 'N' AND perfil = 'autor' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS");

                  //Busquem tots els cursos coordinació relacionats amb la persona
                  //$coordinacions = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE h.DNI_TUTOR LIKE '".$dni_tutor_username."' AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'coord' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id ORDER BY c.MES, c.CURS, c.ANY");
                  $coordinacions = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE ".$text_h." AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0) AND pagat = 'N' AND perfil = 'coord' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS");

                  //Si existeix alguna tutoria, duo o coordinació, mostrarla
                  if (mysqli_num_rows($tutories)>0 || mysqli_num_rows($duos)>0 || mysqli_num_rows($autories)>0 || mysqli_num_rows($coordinacions)>0)
                  {
                    ?>
                    <form name="gestions" method="post" action="<?php echo $PHP_SELF ?>">
                      <input type="hidden" name="gestiook" value="N" />
                      <input type="hidden" name="num" value="0" />
                      <input type="hidden" name="mes_num" value="0" />
                    <?php
                    if (mysqli_num_rows($tutories)>0)
                    {
                      //mostrem l'últim estat laboral
                      $result_el = mysqli_query ($connexio, "SELECT SITUACIO, DATA, OBSERVACIONS FROM estat WHERE ".$text_e." ORDER BY DATA DESC LIMIT 1");

                      $row_el = mysqli_fetch_array($result_el);

                      $data = date_create($row_el['DATA']);
                      $data = date_format($data,"d/m/Y");

                      if ($row_el['OBSERVACIONS'] != NULL)
                        $obs = "&nbsp;&nbsp;|&nbsp;&nbsp;<strong>Observacions:</strong> ".$row_el['OBSERVACIONS'];

                      if ($row_el['SITUACIO'] != NULL)
                      {
                        $mostrar_actualitzacio = "<p style=text-align:left; color:#666666><strong>Darrera actualització:</strong> ";
                        $mostrar_actualitzacio = $mostrar_actualitzacio.$data;
                        $mostrar_actualitzacio = $mostrar_actualitzacio."&nbsp;&nbsp;|&nbsp;&nbsp;<strong>Estat laboral:</strong> ";
                        $mostrar_actualitzacio = $mostrar_actualitzacio.$row_el['SITUACIO'].$obs;
                        $mostrar_actualitzacio = $mostrar_actualitzacio."</p>";

                        echo $mostrar_actualitzacio;
                      }
                      ?>
                      <table class='table table-striped table-hover table-order text-center info_pay'>
                      <?php
                      $i = 1;
                      $cap = true;
                      while ($row_t = mysqli_fetch_array($tutories))
                      {
                          //comprovem si la tutoria ja està pagada
                          if ($row_t['CURS'] == 'NEURO'  || $USER->username == '39352558' || $USER->username == '35000798')
                          {
                          $tutories_ja_pagades = mysqli_query ($connexio, "SELECT ID FROM cobraments WHERE DNI_TUTOR ='".$nif."' AND ANY=".$row_t['ANY']." AND CURS='".$row_t['CURS']."' AND MES='".$row_t['MES']."' AND ROL='T'");
                          }
						                    else if ($row_t['CURS'] == 'GED')
                          {
                          $tutories_ja_pagades = mysqli_query ($connexio, "SELECT ID FROM cobraments WHERE DNI_TUTOR ='".$nif."' AND ANY=".$row_t['ANY']." AND CURS='".$row_t['CURS']."' AND MES='".$row_t['MES']."' AND ROL='T'");
                          }
                          else {
                            $tutories_ja_pagades = mysqli_query ($connexio, "SELECT ID FROM cobraments WHERE ".$text." AND ANY=".$row_t['ANY']." AND CURS='".$row_t['CURS']."' AND MES='".$row_t['MES']."' AND ROL='T'");
                          }

                        //si el resultat és 0, vol dir que la tutoria no està gestionada
                        if(mysqli_num_rows($tutories_ja_pagades)==0)
                        {
                          //echo "<tr><td>PER CADA TUTORIA NO PAGADA:"."SELECT ID FROM cobraments WHERE DNI_TUTOR LIKE '".$dni_tutor_username."' AND ANY=".$row_t['ANY']." AND CURS='".$row_t['CURS']."' AND MES='".$row_t['MES']."' AND ROL='T'"." </td></tr>";
                          ?>
                            <input type="hidden" name="<?php echo "any_tut".$i ?>" value="<?php echo($row_t['ANY']); ?>"  />
                            <input type="hidden" name="<?php echo "curs_tut".$i ?>" value="<?php echo($row_t['CURS']); ?>"  />
                            <input type="hidden" name="<?php echo "mes_tut".$i ?>" value="<?php echo($row_t['MES']); ?>"  />
                          <?php

                          if ($cap == true)
                          {
                             $cap = false;
                             ?>
                             <tr><td class='text-center font-weight-bold text-white' style="width:80px; background-color:#3b6952; font-size: 1.2rem;" colspan="6">Tutoria</td></tr>
                             <tr>
                               <td style="width:80px; text-align:center"><b>ANY</b></td>
                               <td style="width:80px; text-align:center"><b>CURS</b></td>
                               <td style="width:80px; text-align:center"><b>MES</b></td>
                               <td style="width:120px; text-align:center"><b>DIES FINALITZAT</b></td>
                               <td style="width:110px; text-align:center"></td>
                            </tr>
                          <?php
                          }

                          $mes_curs = intval($row_t['MES']);
                          $any_curs = intval($row_t['ANY']);
                          $dni_tutor_curs = $row_t['DNI_TUTOR'];

                          $posterior = true;

                          $result_jubilat = mysqli_query ($connexio,"SELECT e.ID FROM personal AS p, estat AS e WHERE p.DNI=e.DNI AND ".$text_p." AND SITUACIO = 'Estic jubilat/da.'");


                          if ($row_t['datai'] < '2018-07-01')  // els certificats es comencen a demanar a partir d'aquesta data
                          {
                            $posterior=false;
                          }
                          else if ($dni_tutor_curs == "36557520D") //si és una empresa, no se li demanarà l'estat laboral
                          {
                            $posterior=false;
                          }
                          else if (mysqli_num_rows($result_jubilat)>0)
                          {
                              $posterior=false;
                          }
                          else
                          {
                            if ($mes_curs <= 6) // (mesos 1-6)
                            {
                              //mirem el primer semestre de l'any actual i comprovem que hagi tutoritzat algun curs
                              $result_el = mysqli_query ($connexio,"SELECT e.ID FROM personal AS p, estat AS e, cursos AS c WHERE p.DNI=e.DNI AND ".$text_p." AND (DATA BETWEEN '".$any_curs."-01-01' AND '".$any_curs."-06-30') AND ".$text." AND ((CAST(MES AS SIGNED) >=1) AND (CAST(MES AS SIGNED) < 7)) AND ANY = ".$any_curs." ORDER BY DATA");
                            }
                            else
                            {
                              //mirem el segon semestre de l'any actual i comprovem que hagi tutoritzat algun curs
                              $result_el = mysqli_query ($connexio,"SELECT e.ID FROM personal AS p, estat AS e, cursos AS c WHERE p.DNI=e.DNI AND ".$text_p." AND (DATA BETWEEN '".$any_curs."-07-01' AND '".$any_curs."-12-31') AND ".$text." AND ((CAST(MES AS SIGNED) >=7) AND (CAST(MES AS SIGNED) <= 12)) AND ANY =  ".$any_curs." ORDER BY DATA");
                            }

                            $row_el = mysqli_fetch_array($result_el);
                          }
                          ?>
                          <tr>
                          <td style="width:80px; text-align:center"><?php echo($row_t['ANY']); ?></td>
                          <td style="width:80px; text-align:center"><?php echo($row_t['CURS']); ?></td>
                          <td style="width:80px; text-align:center"><?php echo($row_t['MES']); ?></td>
                          <td style="width:120px; text-align:center"><?php echo($row_t['dies_passats']); ?></td>
                          <td style="width:110px; text-align:center" align="center">
                          <?php
                          //si té pendent enviar certificat estat laboral
                          if((mysqli_num_rows($result_el)==0) && ($posterior) && ($USER->username != "45171998") && ($USER->username != "33881437")&& ($USER->username != "78100121") && ($USER->username != "77618906"))
                          {
                            echo "<em>AVÍS <i class='fa-solid fa-asterisk ml-1' style='background: var(--tutorPrisMa);color: white;border-radius: 50%;font-size: 1.2rem;font-weight: 900;padding: 5px 8px;'></i></em>";
                            $nota = "<div class='d-flex justify-content-center align-items-center p-3' style='background: #d8e4cb'><i class='fa-solid fa-asterisk mr-3' style='background: var(--tutorPrisMa);color: white;border-radius: 50%;font-size: 1.7rem;font-weight: 900;padding: 8px 10px;'></i> <p class='font-weight-bold mb-0'>Per gestionar aquesta factura has hagut d'haver enviat el <em>Certificat d'estar al corrent en les obligacions de la Seguretat Social</em>. Pots clicar <a href='https://campus.prisma.cat/collaboradors/estat-laboral' title='Clica aquí' target='_blank' style='text-decoration: none;color: #54851f;font-weight: 900;'>aquí</a> per fer-ho.</p></div>";
                          }
                          else if ($row_t['dies_passats'] >= 15)
                          {
                            ?>
                            <input type="submit" name="<?php echo "gestio".$i ?>" value="GESTIONAR" onclick="gest(<?php echo $i; ?>,<?php echo $i; ?>)" />
                            <?php
                          }
                          else
                          {
                            echo "-";
                          }

                          ?>
                          </td>
                          </tr>
                          <?php
                          $i++;
                        }
                      }
                      ?>
                      </table>
                      <?php
                    }
                    if (mysqli_num_rows($duos)>0)
                    {
                    ?>
                      <table class='table table-striped table-hover table-order text-center info_pay'>
                      <?php
                      $i2 = 1;
                      $cap2 = true;
                      $mesDuo = 0;
                      while ($row_d = mysqli_fetch_array($duos))
                      {
                        //Comprovar a quin trimestre pertoca
                        if($row_d['MES']=='01' || $row_d['MES']=='02' || $row_d['MES']=='03')
                        {
                          $trim_mes_final = '03';
                          $trim_segon_mes = '02';
                          $trimestre = "1r";
                          $mesDuo=3;
                          $tri = "1T";
                        }
                        else if($row_d['MES']=='04' || $row_d['MES']=='05' || $row_d['MES']=='06')
                        {
                          $trim_mes_final = '06';
                          $trim_segon_mes = '05';
                          $trimestre = "2n";
                          $mesDuo=6;
                          $tri = "2T";
                        }
                        else if($row_d['MES']=='07' || $row_d['MES']=='08' || $row_d['MES']=='09')
                        {
                          $trim_mes_final = '09';
                          $trim_segon_mes = '08';
                          $trimestre = "3r";
                          $mesDuo=9;
                          $tri = "3T";
                        }
                        if($row_d['MES']=='10' || $row_d['MES']=='11' || $row_d['MES']=='12')
                        {
                          $trim_mes_final = '12';
                          $trim_segon_mes = '11';
                          $trimestre = "4t";
                          $mesDuo=12;
                          $tri = "4T";
                        }

                        $curs_final_a_comprovar= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE ".$text_h." AND MES='".$trim_mes_final."' AND c.curs='".$row_d['CURS']."' AND ANY=".$row_d['ANY']." AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'duo' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");
                        $curs_segon_a_comprovar= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE ".$text_h." AND MES='".$trim_segon_mes."' AND c.curs='".$row_d['CURS']."' AND ANY=".$row_d['ANY']." AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'duo' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");

                        $OK="false";

                        if(mysqli_num_rows($curs_final_a_comprovar)==0)
                        {
                          if(mysqli_num_rows($curs_segon_a_comprovar)==0)
                          {
                            if (STRCMP($row_d['MES'],'01') == 0 OR STRCMP($row_d['MES'],'04') == 0  OR STRCMP($row_d['MES'],'07') == 0 OR STRCMP($row_d['MES'],'10') == 0 )
                              $OK = "true";
                          }
                          else
                          {
                            if (STRCMP($row_d['MES'],'02') == 0 OR STRCMP($row_d['MES'],'05') == 0  OR STRCMP($row_d['MES'],'08') == 0 OR STRCMP($row_d['MES'],'11') == 0 )
                              $OK = "true";
                          }
                        }
                        else
                        {
                          if (STRCMP($row_d['MES'],'03') == 0 OR STRCMP($row_d['MES'],'06') == 0  OR STRCMP($row_d['MES'],'09') == 0 OR STRCMP($row_d['MES'],'12') == 0 )
                            $OK = "true";
                        }

                        $any_actual = $row_d['ANY'];
                        $any_anterior = $row_d['ANY']-1;
                        $any_seguent = $row_d['ANY']+1;

                        if ($tri=="1T" or $tri=="2T")
                          $curs_escolar_actual = $any_anterior."/".$any_actual;
                        else
                          $curs_escolar_actual = $any_actual."/".$any_seguent;

                        $result_dates = mysqli_query ($connexio, "SELECT * FROM dates_cobraments WHERE HORES=".$row_d['HORES']." AND curs_escolar='".$curs_escolar_actual."'");
                        $row_dates = mysqli_fetch_array($result_dates);

                        if ($tri=="1T")
                        {
                          $data_finalitzacio = $row_dates['1T'];
                        }
                        else if ($tri=="2T")
                        {
                          $data_finalitzacio = $row_dates['2T'];
                        }
                        else if ($tri== "3T")
                        {
                          $data_finalitzacio = $row_dates['3T'];
                        }
                        else if ($tri=="4T")
                        {
                          $data_finalitzacio = $row_dates['4T'];
                        }

                        if ($data_finalitzacio!="")
                        {
                          $data_actual = date("d-m-Y");

                          $dias	= (strtotime($data_actual)-strtotime($data_finalitzacio))/86400;
                          $dias = floor($dias);

                          $dies_passats = $dias;
                        }
                        else
                        {
                          $dies_passats = -1;
                        }

                        if ($OK=="true" && $dies_passats>=0)
                        {
                          $duos_ja_pagats = mysqli_query ($connexio, "SELECT * FROM cobraments WHERE ".$text." AND ANY=".$row_d['ANY']." AND CURS='".$row_d['CURS']."' AND MES='".$tri."' AND ROL='D'");

                          $posterior = true;
                          if ($row_d['ANY']<2018 || ($row_d['ANY']==2018 && ($tri == "1T" || $tri == "2T")))
                            $posterior = false;

                          //si el resultat és 0, vol dir que el duo no està pagat
                          if(mysqli_num_rows($duos_ja_pagats)==0 && $posterior)
                          {
                            ?>
                              <input type="hidden" name="<?php echo "any_duo".$i2 ?>" value="<?php echo($row_d['ANY']); ?>"  />
                              <input type="hidden" name="<?php echo "curs_duo".$i2 ?>" value="<?php echo($row_d['CURS']); ?>"  />
                              <input type="hidden" name="<?php echo "mes_duo".$i2 ?>" value="<?php echo($row_d['MES']); ?>"  />
                            <?php
                            if ($cap2 == true)
                            {
                               $cap2 = false;
                               ?>
                               <tr><td class='text-center font-weight-bold text-white' style="width:80px; background-color:#3b6952; font-size: 1.2rem;" colspan="6">Autoria i coordinació</td></tr>
                               <tr>
                                 <td style="width:80px; text-align:center"><b>ANY</b></td>
                                 <td style="width:80px; text-align:center"><b>CURS</b></td>
                                 <td style="width:80px; text-align:center"><b>TRIMESTRE</b></td>
                                 <td style="width:120px; text-align:center"><b>DIES FINALITZAT</b></td>
                                 <td style="width:110px; text-align:center"></td>
                              </tr>
                            <?php
                            }
                            ?>
                              <tr>
                              <td style="width:80px; text-align:center"><?php echo($row_d['ANY']); ?></td>
                              <td style="width:80px; text-align:center"><?php echo($row_d['CURS']); ?></td>
                              <td style="width:80px; text-align:center"><?php echo($trimestre); ?></td>
                              <td style="width:120px; text-align:center"><?php echo($dies_passats); ?></td>
                              <td style="width:110px; text-align:center" align="center">
                                <?php
                                if ($dies_passats >= 25)
                                {
                                  ?><input type="submit" name="<?php echo "gestio2".$mesDuo ?>" value="GESTIONAR" onclick="gest(<?php echo $mesDuo; ?>,<?php echo $i2; ?>)" /><?php
                                }
                                else
                                  echo "-";
                                ?>
                              </td>
                              </tr>
                            <?php
                            $i2++;
                          }
                        }
                      }
                      ?>
                      </table>
                      <?php
                    }
                    if (mysqli_num_rows($autories)>0)
                    {
                    ?>
                      <table class='table table-striped table-hover table-order text-center info_pay'>
                      <?php
                      $i3 = 1;
                      $cap3 = true;
                      $mesAutor = 0;
                      while ($row_a = mysqli_fetch_array($autories))
                      {
                        //Comprovar a quin trimestre pertoca
                        if($row_a['MES']=='01' || $row_a['MES']=='02' || $row_a['MES']=='03')
                        {
                          $trim_mes_final_a = '03';
                          $trim_segon_mes_a = '02';
                          $trimestre_a = "1r";
                          $mesAutor=3;
                          $tri_a = "1T";
                        }
                        else if($row_a['MES']=='04' || $row_a['MES']=='05' || $row_a['MES']=='06')
                        {
                          $trim_mes_final_a = '06';
                          $trim_segon_mes_a = '05';
                          $trimestre_a = "2n";
                          $mesAutor=6;
                          $tri_a = "2T";
                        }
                        else if($row_a['MES']=='07' || $row_a['MES']=='08' || $row_a['MES']=='09')
                        {
                          $trim_mes_final_a = '09';
                          $trim_segon_mes_a = '08';
                          $trimestre_a = "3r";
                          $mesAutor=9;
                          $tri_a = "3T";
                        }
                        if($row_a['MES']=='10' || $row_a['MES']=='11' || $row_a['MES']=='12')
                        {
                          $trim_mes_final_a = '12';
                          $trim_segon_mes_a = '11';
                          $trimestre_a = "4t";
                          $mesAutor=12;
                          $tri_a = "4T";
                        }

                        $curs_final_a_comprovar_a= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE ".$text_h." AND MES='".$trim_mes_final_a."' AND c.curs='".$row_a['CURS']."' AND ANY=".$row_a['ANY']." AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'autor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");
                        $curs_segon_a_comprovar_a= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE ".$text_h." AND MES='".$trim_segon_mes_a."' AND c.curs='".$row_a['CURS']."' AND ANY=".$row_a['ANY']." AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'autor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");

                        $OK="false";

                        if(mysqli_num_rows($curs_final_a_comprovar_a)==0)
                        {
                          if(mysqli_num_rows($curs_segon_a_comprovar_a)==0)
                          {
                            if (STRCMP($row_a['MES'],'01') == 0 OR STRCMP($row_a['MES'],'04') == 0  OR STRCMP($row_a['MES'],'07') == 0 OR STRCMP($row_a['MES'],'10') == 0 )
                              $OK = "true";
                          }
                          else
                          {
                            if (STRCMP($row_a['MES'],'02') == 0 OR STRCMP($row_a['MES'],'05') == 0  OR STRCMP($row_a['MES'],'08') == 0 OR STRCMP($row_a['MES'],'11') == 0 )
                              $OK = "true";
                          }
                        }
                        else
                        {
                          if (STRCMP($row_a['MES'],'03') == 0 OR STRCMP($row_a['MES'],'06') == 0  OR STRCMP($row_a['MES'],'09') == 0 OR STRCMP($row_a['MES'],'12') == 0 )
                            $OK = "true";
                        }

                        $any_actual_a = $row_a['ANY'];
                        $any_anterior_a = $row_a['ANY']-1;
                        $any_seguent_a = $row_a['ANY']+1;

                        if ($tri_a=="1T" or $tri_a=="2T")
                          $curs_escolar_actual_a = $any_anterior_a."/".$any_actual_a;
                        else
                          $curs_escolar_actual_a = $any_actual_a."/".$any_seguent_a;

                        $result_dates_a = mysqli_query ($connexio, "SELECT * FROM dates_cobraments WHERE HORES=".$row_a['HORES']." AND curs_escolar='".$curs_escolar_actual_a."'");
                        $row_dates_a = mysqli_fetch_array($result_dates_a);

                        if ($tri_a=="1T")
                        {
                          $data_finalitzacio = $row_dates_a['1T'];
                        }
                        else if ($tri_a=="2T")
                        {
                          $data_finalitzacio = $row_dates_a['2T'];
                        }
                        else if ($tri_a == "3T")
                        {
                          $data_finalitzacio = $row_dates_a['3T'];
                        }
                        else if ($tri_a=="4T")
                        {
                          $data_finalitzacio = $row_dates_a['4T'];
                        }

                        if ($data_finalitzacio!="")
                        {
                          $data_actual = date("d-m-Y");

                          $dias	= (strtotime($data_actual)-strtotime($data_finalitzacio))/86400;
                          $dias = floor($dias);

                          $dies_passats = $dias;
                        }
                        else
                        {
                          $dies_passats = -1;
                        }

                        if ($OK=="true" && $dies_passats>=0)
                        {
                            if ($row_a['CURS'] == 'NEURO' || $USER->username == '39352558' || $USER->username == '35000798')
                              $autories_ja_pagades = mysqli_query ($connexio, "SELECT * FROM cobraments WHERE DNI_TUTOR ='".$nif."' AND ANY=".$row_a['ANY']." AND CURS='".$row_a['CURS']."' AND MES='".$tri_a."' AND ROL='A'");
                            else
                              $autories_ja_pagades = mysqli_query ($connexio, "SELECT * FROM cobraments WHERE ".$text." AND ANY=".$row_a['ANY']." AND CURS='".$row_a['CURS']."' AND MES='".$tri_a."' AND ROL='A'");

                            $posterior = true;

                            if ($row_a['ANY']<2018 || ($row_a['ANY']==2018 && ($tri_a == "1T" || $tri_a == "2T")))
                              $posterior = false;

                          //si el resultat és 0, vol dir que l'autoria ja està pagada
                          if(mysqli_num_rows($autories_ja_pagades)==0 && $posterior)
                          {
                            ?>
                              <input type="hidden" name="<?php echo "any_aut".$i3 ?>" value="<?php echo($row_a['ANY']); ?>"  />
                              <input type="hidden" name="<?php echo "curs_aut".$i3 ?>" value="<?php echo($row_a['CURS']); ?>"  />
                              <input type="hidden" name="<?php echo "mes_aut".$i3 ?>" value="<?php echo($row_a['MES']); ?>"  />
                            <?php
                            if ($cap3 == true)
                            {
                               $cap3 = false;
                               ?>
                               <tr><td class='text-center font-weight-bold text-white' style="width:80px; background-color:#3b6952; font-size: 1.2rem;" colspan="6">Autoria</td></tr>
                               <tr>
                                 <td style="width:80px; text-align:center"><b>ANY</b></td>
                                 <td style="width:80px; text-align:center"><b>CURS</b></td>
                                 <td style="width:80px; text-align:center"><b>TRIMESTRE</b></td>
                                 <td style="width:120px; text-align:center"><b>DIES FINALITZAT</b></td>
                                 <td style="width:110px; text-align:center"></td>
                              </tr>
                            <?php
                            }
                            ?>
                              <tr>
                              <td style="width:80px; text-align:center"><?php echo($row_a['ANY']); ?></td>
                              <td style="width:80px; text-align:center"><?php echo($row_a['CURS']); ?></td>
                              <td style="width:80px; text-align:center"><?php echo($trimestre_a); ?></td>
                              <td style="width:120px; text-align:center"><?php echo($dies_passats); ?></td>
                              <td style="width:110px; text-align:center" align="center">
                                <?php
                                if ($dies_passats >= 25)
                                {
                                  ?><input type="submit" name="<?php echo "gestio3".$mesAutor ?>" value="GESTIONAR" onclick="gest(<?php echo $mesAutor; ?>,<?php echo $i3; ?>)" /><?php
                                }
                                else
                                  echo "-";
                                ?>
                              </td>
                              </tr>
                            <?php
                            $i3++;
                          }
                        }
                      }
                      ?>
                      </table>
                      <?php
                    }
                    if (mysqli_num_rows($coordinacions)>0)
                    {
                    ?>
                      <table class='table table-striped table-hover table-order text-center info_pay'>
                      <?php
                      $i4 = 1;
                      $cap4 = true;
                      $mesCoord = 0;
                      while ($row_c = mysqli_fetch_array($coordinacions))
                      {
                        //Comprovar a quin trimestre pertoca
                        if($row_c['MES']=='01' || $row_c['MES']=='02' || $row_c['MES']=='03')
                        {
                          $trim_mes_final_c = '03';
                          $trim_segon_mes_c = '02';
                          $trimestre_c = "1r";
                          $mesCoord=3;
                          $tri_c = "1T";
                        }
                        else if($row_c['MES']=='04' || $row_c['MES']=='05' || $row_c['MES']=='06')
                        {
                          $trim_mes_final_c = '06';
                          $trim_segon_mes_c = '05';
                          $trimestre_c = "2n";
                          $mesCoord=6;
                          $tri_c = "2T";
                        }
                        else if($row_c['MES']=='07' || $row_c['MES']=='08' || $row_c['MES']=='09')
                        {
                          $trim_mes_final_c = '09';
                          $trim_segon_mes_c = '08';
                          $trimestre_c = "3r";
                          $mesCoord=9;
                          $tri_c = "3T";
                        }
                        else if($row_c['MES']=='10' || $row_c['MES']=='11' || $row_c['MES']=='12')
                        {
                          $trim_mes_final_c = '12';
                          $trim_segon_mes_c = '11';
                          $trimestre_c = "4t";
                          $mesCoord=12;
                          $tri_c = "4T";
                        }

                        $curs_final_a_comprovar_c= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE ".$text_h." AND MES='".$trim_mes_final_c."' AND c.curs='".$row_c['CURS']."' AND ANY=".$row_c['ANY']." AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil = 'coord' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");
                        $curs_segon_a_comprovar_c= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE ".$text_h." AND MES='".$trim_segon_mes_c."' AND c.curs='".$row_c['CURS']."' AND ANY=".$row_c['ANY']." AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil = 'coord' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");

                        $OK="false";

                        if(mysqli_num_rows($curs_final_a_comprovar_c)==0)
                        {
                          if(mysqli_num_rows($curs_segon_a_comprovar_c)==0)
                          {
                            if (STRCMP($row_c['MES'],'01') == 0 OR STRCMP($row_c['MES'],'04') == 0  OR STRCMP($row_c['MES'],'07') == 0 OR STRCMP($row_c['MES'],'10') == 0 )
                              $OK = "true";
                          }
                          else
                          {
                            if (STRCMP($row_c['MES'],'02') == 0 OR STRCMP($row_c['MES'],'05') == 0  OR STRCMP($row_c['MES'],'08') == 0 OR STRCMP($row_c['MES'],'11') == 0 )
                              $OK = "true";
                          }
                        }
                        else
                        {
                          if (STRCMP($row_c['MES'],'03') == 0 OR STRCMP($row_c['MES'],'06') == 0  OR STRCMP($row_c['MES'],'09') == 0 OR STRCMP($row_c['MES'],'12') == 0 )
                            $OK = "true";
                        }

                        $any_actual_c = $row_c['ANY'];
                        $any_anterior_c = $row_c['ANY']-1;
                        $any_seguent_c = $row_c['ANY']+1;

                        if ($tri_c=="1T" or $tri_c=="2T")
                          $curs_escolar_actual_c = $any_anterior_c."/".$any_actual_c;
                        else
                          $curs_escolar_actual_c = $any_actual_c."/".$any_seguent_c;

                        $result_dates_c = mysqli_query ($connexio, "SELECT * FROM dates_cobraments WHERE HORES=".$row_c['HORES']." AND curs_escolar='".$curs_escolar_actual_c."'");
                        $row_dates_c = mysqli_fetch_array($result_dates_c);

                        if ($tri_c=="1T")
                        {
                          $data_finalitzacio = $row_dates_c['1T'];
                        }
                        else if ($tri_c=="2T")
                        {
                          $data_finalitzacio = $row_dates_c['2T'];
                        }
                        else if ($tri_c == "3T")
                        {
                          $data_finalitzacio = $row_dates_c['3T'];
                        }
                        else if ($tri_c=="4T")
                        {
                          $data_finalitzacio = $row_dates_c['4T'];
                        }

                        if ($data_finalitzacio!="")
                        {
                          $data_actual = date("d-m-Y");

                          $dias	= (strtotime($data_actual)-strtotime($data_finalitzacio))/86400;
                          $dias = floor($dias);

                          $dies_passats = $dias;
                        }
                        else
                        {
                          $dies_passats = -1;
                        }

                        if ($OK=="true" && $dies_passats>=0)
                        {
                          $coordinacions_ja_pagades = mysqli_query ($connexio, "SELECT * FROM cobraments WHERE ".$text." AND ANY=".$row_c['ANY']." AND CURS='".$row_c['CURS']."' AND MES='".$tri_c."' AND ROL='C'");

                          $posterior = true;
                          if ($row_c['ANY']<2018 || ($row_c['ANY']==2018 && ($tri_c == "1T" || $tri_c == "2T")))
                            $posterior = false;

                          //si el resultat és 0, vol dir que l'autoria ja està pagada
                          if(mysqli_num_rows($coordinacions_ja_pagades)==0 && $posterior)
                          {
                            //echo "row_c['ANY'] ".$row_c['ANY']." tri_c:".$tri_c."<br>";
                            ?>
                              <input type="hidden" name="<?php echo "any_coord".$i4 ?>" value="<?php echo($row_c['ANY']); ?>"  />
                              <input type="hidden" name="<?php echo "curs_coord".$i4 ?>" value="<?php echo($row_c['CURS']); ?>"  />
                              <input type="hidden" name="<?php echo "mes_coord".$i4 ?>" value="<?php echo($row_c['MES']); ?>"  />
                            <?php
                            if ($cap4 == true)
                            {
                               $cap4 = false;
                               ?>
                               <tr><td class='text-center font-weight-bold text-white' style="width:80px; background-color:#3b6952; font-size: 1.2rem;" colspan="6">Coordinacions</td></tr>
                               <tr>
                                 <td style="width:80px; text-align:center"><b>ANY</b></td>
                                 <td style="width:80px; text-align:center"><b>CURS</b></td>
                                 <td style="width:80px; text-align:center"><b>TRIMESTRE</b></td>
                                 <td style="width:120px; text-align:center"><b>DIES FINALITZAT</b></td>
                                 <td style="width:110px; text-align:center"></td>
                              </tr>
                            <?php
                            }
                            ?>
                              <tr>
                              <td style="width:80px; text-align:center"><?php echo($row_c['ANY']); ?></td>
                              <td style="width:80px; text-align:center"><?php echo($row_c['CURS']); ?></td>
                              <td style="width:80px; text-align:center"><?php echo($trimestre_c); ?></td>
                              <td style="width:120px; text-align:center"><?php echo($dies_passats); ?></td>
                              <td style="width:110px; text-align:center" align="center">
                                <?php
                                if ($dies_passats >= 25)
                                {
                                  ?><input type="submit" name="<?php echo "gestio4".$mesCoord ?>" value="GESTIONAR" onclick="gest(<?php echo $mesCoord; ?>,<?php echo $i4; ?>)" /><?php
                                }
                                else
                                  echo "-";
                                ?>
                              </td>
                              </tr>
                            <?php
                            $i4++;
                          }
                        }
                      }
                      ?>
                      </table>
                      <?php
                    }
                    ?>
                    </form>
                    <?php
                  }

                  if (mysqli_num_rows($tutories)<=0 && mysqli_num_rows($duos)<=0 && mysqli_num_rows($autories)<=0 && mysqli_num_rows($coordinacions)<=0)
                  {
                    echo "No tens cap curs pendent de gestionar.";
                  }
                  else
                  {
                    $text = "<p class='font-weight-bold'>";
                    $text_a_partir_de_a_c = " només es podran gestionar a partir del vint-i-cinquè dia a partir de la data de finalització de l'ultim curs del trimestre";
                    $text_a_partir_de_t = " només es podran gestionar a partir del quinzè dia de la seva finalització";
                    if (mysqli_num_rows($tutories)>0)
                    {
                      $text = $text."Les «tutories» dels cursos ".$text_a_partir_de_t;
                    }
                    if (mysqli_num_rows($duos)>0)
                    {
                      if (mysqli_num_rows($tutories)>0)
                        $text = $text.", i les «coordinacions i autories»".$text_a_partir_de_a_c;
                      else {
                        $text = $text."Les «coordinacions i autories»".$text_a_partir_de_a_c;
                      }
                    }
                    if ((mysqli_num_rows($autories)>0 || mysqli_num_rows($coordinacions)>0) && mysqli_num_rows($duos)<=0)
                    {
                      if (mysqli_num_rows($tutories)>0 && mysqli_num_rows($duos)<=0)
                      {
                        $text = $text.", i les ";
                      }
                      else if (mysqli_num_rows($tutories)<=0 && mysqli_num_rows($duos)<=0)
                      {
                        $text = $text."Les ";
                      }
                      if (mysqli_num_rows($autories)>0 && mysqli_num_rows($coordinacions)==0)
                        $text = $text."«autories»";
                      if (mysqli_num_rows($coordinacions)>0 && mysqli_num_rows($autories)==0)
                        $text = $text."«coordinacions»";
                        if (mysqli_num_rows($coordinacions)>0 && mysqli_num_rows($autories)>0)
                          $text = $text."«autories» i «coordinacions»";
                      $text = $text.$text_a_partir_de_a_c;
                    }
                    $text = $text.".</p>";
                    echo $text;
                    echo $nota;
                  }
                }
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
    		<link rel="stylesheet" href="https://campus.prisma.cat/intranet-collaboradors/cobraments/css/gestio-cobraments.css?ver=6.0"/>
    		<script src="https://campus.prisma.cat/intranet-collaboradors/cobraments/js/general.js?ver=7.0"></script>
    		<script src="https://campus.prisma.cat/intranet-collaboradors/cobraments/js/gestio-cobraments.js?ver=7.0"></script>
    	</body>
    </html>
    <?php
  }
}

?>
