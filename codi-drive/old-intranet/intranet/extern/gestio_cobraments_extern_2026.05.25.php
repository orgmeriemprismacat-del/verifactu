<?php
  session_name("sessio_tutor_extern");
  session_start();

  if(isset($_SESSION['usuari']) && isset($_SESSION['contrasenya_encriptada']))
  {
    $nom_tutor_factura = $_SESSION['nom_factura'];
    // els dni es passen des del fitxer acces_extern.php
  	if ($_SESSION['usuari']=="G67253443") //Poso com a dni el de l'empresa AEHS, el dni de la Laura, de l'Ester
  		$dni = 	$_SESSION['usuari']."' OR c.DNI_TUTOR LIKE '43674436N' OR c.DNI_TUTOR LIKE '79302336S'";
  	/*if ($_SESSION['usuari']=="B25750407") //Si és el Daniel, poso com a dni el d'en Daniel i el de l'empresa Boira
  		$dni = 	$_SESSION['usuari']."' OR c.DNI_TUTOR LIKE '43400030L";*/
  /*	if ($_SESSION['usuari']=="B87456992") //Si és el FB poso com a dni el seu.
  		$dni = 	$_SESSION['usuari'];*/

    $idioma = "ca";

   /* if ($_SESSION['usuari']=="B87456992") //Si és la Neus o el FB, poso com a dni el seu.
    		$idioma = "es";*/
    ?>
    <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
    <html xmlns="http://www.w3.org/1999/xhtml">
    <head>
      <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
      <title>Intranet | Gestió cobraments</title>
      <link rel="stylesheet" href="../css/estilo_back_tutors.css"/>
      <script>
        function gest(n,mes_num)
        {
          document.gestions.gestiook.value = 'S';
          document.gestions.num.value = n;
          document.gestions.mes_num.value = mes_num;
          console.log("gest. n: " + n + " mes: " + mes_num);
        }

        function comprovar_validar()
      	{
      		informeenviat=false;
      		facturaadjuntada=false;
      		facturaPDF=false;

      		//comprovem que s'ha enviat l'informe
      		if (document.gestions.informe.value != "")
      			alert ("Cal enviar l'informe de curs abans d'enviar la teva conformitat!");
      		else
      			informeenviat=true;

      		//comprovem que s'ha adjuntat un fitxer
      		if (document.gestions.archivo1.value!="")
          {
      			if (document.gestions.archivo1.value.substr(-3)!="pdf")
      				alert ("El fitxer adjunt ha de ser un PDF");
      			else
      				facturaPDF = true;
      			facturaadjuntada=true;
      		}
      		else {
      			alert("Adjunta un fitxer!");
      		}
      		return (informeenviat && facturaadjuntada && facturaPDF)
      	}

        //comprovar si has escrit en el cap revisió, en cas que volguis fer una revisió
      	function comprovar_revisar()
      	{
      		enviarmail=false;
      		document.gestions.revisio_o_gestionar.value="1";
      		if (document.gestions.comentaris.value == "")
      			alert ("No has escrit cap text per a revisió!");
      		else
      			enviarmail=true;

      		return (enviarmail)
      	}
      </script>
    </head>
    <body topmargin="0">
      <table width="1100" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main">
        <tr>
          <td>
            <img src="../img/formacio_rectangular_2.jpg" style="float:left" />
            <div style="position:relative; width:920px; padding-right:25px; padding-top:10px; text-align:right">Hola <?php echo $_SESSION['name'] ?></div>
            <div id="menu" style="clear:both">
              <div class="nav_on2" style="margin-left:20px;"><span style="font-size:10px">GESTIÓ</span><br />COBRAMENTS</div>
              <div class="nav2"><a href="consulta_cobraments_extern.php" style="margin-left:1px;"><span style="font-size:10px">CONSULTA</span><br />COBRAMENTS</a></div>
            </div>
          </td>
        </tr>
        <tr>
          <td align="center">
            <div id="login">
              <div id="llegenda_curs" class="ge">
                <div style="text-align:left; border-top:solid 1px #CCCCCC"></div><br /><br />

                <?php
                  include('../inc/dades.php');

                  $bestreta = 'X';

                  //REVISIÓ DEL TUTOR
                  if ($_POST['revisio']=="ENVIAR PER A REVISIO")
                  {
                    //$to = "suport.informatic@prisma.cat";
                    $to = "secretaria@prisma.cat";

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
    									echo "Els comentaris per a revisió s'han enviat correctament.";

                    ?><div align="center"><br /><input type="button" name="boto" class="botones" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='./gestio_cobraments_extern.php'"/></div><?php
                  }

                  //REVISIÓ DEL DUO
                  else if ($_POST['revisio2']=="ENVIAR PER A REVISIO")
                  {
                    //$to = "suport.informatic@prisma.cat";
                    $to = "secretaria@prisma.cat";

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
    									echo "Els comentaris per a revisió s'han enviat correctament.";

                    ?><div align="center"><br /><input type="button" name="boto" class="botones" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='./gestio_cobraments_extern.php'"/></div><?php
                  }

                  //REVISIÓ DEL AUTOR
                  else if ($_POST['revisio3']=="ENVIAR PER A REVISIO")
                  {
                    //$to = "suport.informatic@prisma.cat";
                    $to = "secretaria@prisma.cat";

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
    									echo "Els comentaris per a revisió s'han enviat correctament.";

                    ?><div align="center"><br /><input type="button" name="boto" class="botones" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='./gestio_cobraments_extern.php'"/></div><?php
                  }

                  //REVISIÓ DEL COORD
                  else if ($_POST['revisio4']=="ENVIAR PER A REVISIO")
                  {
                    //$to = "suport.informatic@prisma.cat";
                    $to = "secretaria@prisma.cat";

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
    									echo "Els comentaris per a revisió s'han enviat correctament.";

                    ?><div align="center"><br /><input type="button" name="boto" class="botones" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='./gestio_cobraments_extern.php'"/></div><?php
                  }

                  //CONFIRMAR COBRAMENT TUTOR
                  else if ($_POST['confirmar']=="HI ESTIC DACORD")
                  {
                    $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                    mysqli_set_charset ($connexio, "utf8");

                    if (mysqli_connect_errno())
                    {
                      echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
                    }
                    else
                    {
                      $irpfTutor = $_POST[irpf];
                      $dates = str_replace("\\","",$_POST[dates]);
    									$dates = str_replace("'","",$dates);
    									if ($_POST[irpf] <= 0)
    									{
    										$irpfTutor = 0;
    									}

                      $dni2=$_POST[dni];
                     				 if ($_POST[curs] == "SIST") //cas AEHS
    									{
    										$dni2="G67253443";											
    									}
    									else if ($_POST[dni] == "25750407" or $_POST[dni] == "43400030L") //cas Boira
    									{
    										$dni2="B25750407";
											//$dni2="43400030L";
    									}
    									else
    									{
    										$dni2=$_SESSION['usuari'];
    									}

    									$result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT) VALUES
    									('".$dni2."','T',".$_POST[any].",'".$_POST[curs]."','".$_POST[mes]."','".$dates."',".$_POST[inscrits].",".$_POST[import].",".$irpfTutor.",".$_POST[importnet].",CURRENT_DATE)");

                      function form_mail()
    									{
    										$bHayFicheros = 0;
    										$sCabeceraTexto = "";
    										$sAdjuntos = "";

    										$titol = str_replace("\'","'",$_POST[titol]);
    										$dates = str_replace("\'","'",$_POST[dates]);

    										//$to = "suport@prisma.cat";
                        $to = "facturacio@prisma.cat, secretaria@prisma.cat, suport.informatic@prisma.cat, suport@prisma.cat";

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

    											$dir_destino = '../../campus/intranet/tutors/factures/';
    											$originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
        									$modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    											$nom_tutor = utf8_decode(str_replace(' ','_',$_POST[nom_factura]));
    											$nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
    											$nom_tutor = utf8_encode($nom_tutor);
    											$extension = substr(basename($_FILES['archivo1']['name']),-4,4);
    											$imagen_subida = $dir_destino.$any_data_actual.$mes_data_actual."_Curs_".$_POST[any].$_POST[curs].$_POST[mes]."_Tutoria_".$nom_tutor.$extension;

                          //Variables del metodo POST
    											if(!is_writable($dir_destino))
                          {
    											}
    											else
                          {
    												if(is_uploaded_file($_FILES['archivo1']['tmp_name'])){
    													if (move_uploaded_file($_FILES['archivo1']['tmp_name'], $imagen_subida)) {
    														echo "L'arxiu s'ha enviat correctament.<br>";
    													}
    												}
    											}
    										}

    										if ($bHayFicheros)
    											$sTexto .= $sAdjuntos."\n\n----_Separador-de-mensajes_----\n";
    										return(mail($to, $subject, $sTexto, $headers));

    									}

    									if (form_mail())
    										echo "Has enviat la factura correctament.";

    									?><div align="center"><br /><input type="button" name="boto" class="botones" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='./gestio_cobraments_extern.php'"/></div><?php
                    }
                  }

				          //CONFIRMAR COBRAMENT DUO
                  else if ($_POST['confirmar2']=="HI ESTIC DACORD") //cas en que hem decidit gestionar una coordinació i s'hagi confirmat
    							{
                    $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                    mysqli_set_charset ($connexio, "utf8");

    								if (mysqli_connect_errno())
    								{
    									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
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

                      $dni2=$_POST[dni];
                      /*if ($_POST[curs] == "SIST" && $_SESSION['usuari']!="77897279M") //cas AD'S.
          						{
          							$dni2="G67253443";
          							$dni_bestreta="G67253443";

          							if ($_POST[any] > 2019 || ($_POST[any]==2019 && ($_POST[mes]=='09' or $_POST[mes]=='10' or $_POST[mes]=='11' or $_POST[mes]=='12')) ) {
          								$dni2="G67253443";
          								$dni_bestreta="G67253443";
          							}
          							else {
          								$dni2="G17843830";
          								$dni_bestreta="G17843830";
          							}
          						}
          						else */ if ($_POST[dni] == "25750407" or $_POST[dni] == "43400030L") //cas Boira
          						{
          							$dni2="B25750407";
          							$dni_bestreta="43400030L";
          							//$dni2="43400030L";
          						}
          						else
          						{
          							$dni2=$_SESSION['usuari'];
          							$dni_bestreta=$_SESSION['usuari'];
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
    									  ('".$_POST[curs]."','".$dni_bestreta."','".$descripcio."',-".$pagat.",CURRENT_DATE)");

                        if($_POST[a_pagar]<=0)
                          $import_a_cobrar = $_POST[import_a_cobrar];

                        $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT,BESTRETA,APAGAR_REAL) VALUES
      									('".$dni2."','D',".$_POST[any].",'".$_POST[curs]."','".$mesDuo."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf2,".$_POST[importnet].",CURRENT_DATE,1,'".$import_a_cobrar."')");
                      }
                      else
                      {
                        $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT) VALUES
    									  ('".$dni2."','D',".$_POST[any].",'".$_POST[curs]."','".$mesDuo."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf2,".$_POST[importnet].",CURRENT_DATE)");
                      }

    									function form_mail()
    									{
    										$bHayFicheros = 0;
    										$sCabeceraTexto = "";
    										$sAdjuntos = "";

    										$titol = str_replace("\'","'",$_POST[titol]);
    										$dates = str_replace("\'","'",$_POST[dates]);

    										//$to = "suport@prisma.cat";
                        $to = "facturacio@prisma.cat, secretaria@prisma.cat, suport.informatic@prisma.cat, suport@prisma.cat";

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

    											$dir_destino = '../../campus/intranet/tutors/factures/';
    											$originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
        									$modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    											$nom_tutor = utf8_decode(str_replace(' ','_',$_POST[nom_factura]));
    											$nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
    											$nom_tutor = utf8_encode($nom_tutor);
    											$extension = substr(basename($_FILES['archivo1']['name']),-4,4);
    											$imagen_subida = $dir_destino.$any_data_actual.$mes_data_actual."_Curs_".$_POST[any].$_POST[curs].$mesDuo."_Autoria_Coordinacio_".$nom_tutor.$extension;

                          //Variables del metodo POST
    											if(!is_writable($dir_destino)){
    											}
                          else
                          {
    												if(is_uploaded_file($_FILES['archivo1']['tmp_name'])){
    													if (move_uploaded_file($_FILES['archivo1']['tmp_name'], $imagen_subida)) {
    														echo "L'arxiu s'ha enviat correctament.<br>";
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
    										echo "Has enviat la factura correctament.";
    									}

    									?><div align="center"><br /><input type="button" name="boto" class="botones" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='./gestio_cobraments_extern.php'"/></div><?php

                    }

    							}

                  //CONFIRMAR COBRAMENT AUTOR
                  else if ($_POST['confirmar3']=="HI ESTIC DACORD")
                  {
                    $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                    mysqli_set_charset ($connexio, "utf8");

                    if (mysqli_connect_errno())
    								{
    									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
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

                      if ($_POST[irpf] <= 0)
                      {
    										$irpf3 = 0;
    									}
                      else
                      {
    										$irpf3 = $_POST[irpf];
    									}

                      $dni2=$_POST[dni];
                     /* if ($_POST[curs] == "SIST" && $_SESSION['usuari']!="77897279M") //cas AD'S.
    									{
    										if ($_POST[any] > 2019 || ($_POST[any]==2019 && ($_POST[mes]=='09' or $_POST[mes]=='10' or $_POST[mes]=='11' or $_POST[mes]=='12')) )
												$dni2="G67253443";
											else
												$dni2="G17843830";
    									}
    									else */ if ($_POST[dni] == "25750407" or $_POST[dni] == "43400030L") //cas Boira
    									{
    										$dni2="B25750407";
											$dni_bestreta="43400030L";
											//$dni2="43400030L";
    									}
                                        else if ($_POST[dni] == "43674436") //cas Laura Soliva
    									{
    										$dni2="43674436N";
											$dni_bestreta="43674436N";
    									}
    									else
    									{
    										$dni2=$_SESSION['usuari'];
											$dni_bestreta=$_SESSION['usuari'];
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
    									  ('".$_POST[curs]."','".$dni_bestreta."','".$descripcio."',-".$pagat.",CURRENT_DATE)");

                        if($_POST[a_pagar]<=0)
                          $import_a_cobrar = $_POST[import_a_cobrar];

                        $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT,BESTRETA,APAGAR_REAL) VALUES
      									('".$dni2."','A',".$_POST[any].",'".$_POST[curs]."','".$mesAutor."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf3,".$_POST[importnet].",CURRENT_DATE,1,'".$import_a_cobrar."')");
                      }
                      else
                      {
                        $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT) VALUES
    									  ('".$dni2."','A',".$_POST[any].",'".$_POST[curs]."','".$mesAutor."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf3,".$_POST[importnet].",CURRENT_DATE)");
                      }

    									function form_mail()
    									{
    										$bHayFicheros = 0;
    										$sCabeceraTexto = "";
    										$sAdjuntos = "";

    										$titol = str_replace("\'","'",$_POST[titol]);
    										$dates = str_replace("\'","'",$_POST[dates]);

    										//$to = "suport@prisma.cat";
                        $to = "facturacio@prisma.cat, secretaria@prisma.cat, suport.informatic@prisma.cat, suport@prisma.cat";

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

    											$dir_destino = '../../campus/intranet/tutors/factures/';
    											$originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
        									$modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    											$nom_tutor = utf8_decode(str_replace(' ','_',$_POST[nom_factura]));
    											$nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
    											$nom_tutor = utf8_encode($nom_tutor);
    											$extension = substr(basename($_FILES['archivo1']['name']),-4,4);
    											$imagen_subida = $dir_destino.$any_data_actual.$mes_data_actual."_Curs_".$_POST[any].$_POST[curs].$mesAutor."_Autoria_".$nom_tutor.$extension;

                          //Variables del metodo POST
    											if(!is_writable($dir_destino)){
    											}
                          else
                          {
    												if(is_uploaded_file($_FILES['archivo1']['tmp_name'])){
    													if (move_uploaded_file($_FILES['archivo1']['tmp_name'], $imagen_subida)) {
    														echo "L'arxiu s'ha enviat correctament.<br>";
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
    										echo "Has enviat la factura correctament.";
    									}

    									?><div align="center"><br /><input type="button" name="boto" class="botones" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='./gestio_cobraments_extern.php'"/></div><?php

                    }
                  }

                  //CONFIRMAR COBRAMENT COORDINADOR
                  else if ($_POST['confirmar4']=="HI ESTIC DACORD")
                  {
                    $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                    mysqli_set_charset ($connexio, "utf8");

                    if (mysqli_connect_errno())
    								{
    									echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
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

                      if ($_POST[irpf] <= 0)
                      {
    										$irpf4 = 0;
    									}
                      else
                      {
    										$irpf4 = $_POST[irpf];
    									}

                      $dni2=$_POST[dni];
                      /*if ($_POST[curs] == "SIST" && $_SESSION['usuari']!="77897279M") //cas AD'S.
    									{
    										if ($_POST[any] > 2019 || ($_POST[any]==2019 && ($_POST[mes]=='09' or $_POST[mes]=='10' or $_POST[mes]=='11' or $_POST[mes]=='12')) )
												$dni2="G67253443";
											else
												$dni2="G17843830";
    									}
    									else */ if ($_POST[dni] == "25750407" or $_POST[dni] == "43400030L") //cas Boira
    									{
    										$dni2="B25750407";
											//$dni2="43400030L";
    									}
    									else
    									{
    										$dni2=$_SESSION['usuari'];
    									}

                      $result = mysqli_query ($connexio,"INSERT INTO cobraments (DNI_TUTOR,ROL,ANY,CURS,MES,DATES,ALUMNES,IMPORT,IRPF,APAGAR,GESTIONAT) VALUES
    									('".$dni2."','C',".$_POST[any].",'".$_POST[curs]."','".$mesCoord."','".$dates."',".$_POST[inscrits].",".$_POST[import].",$irpf4,".$_POST[importnet].",CURRENT_DATE)");

    									function form_mail()
    									{
    										$bHayFicheros = 0;
    										$sCabeceraTexto = "";
    										$sAdjuntos = "";

    										$titol = str_replace("\'","'",$_POST[titol]);
    										$dates = str_replace("\'","'",$_POST[dates]);

    										//$to = "suport@prisma.cat";
											$to = "facturacio@prisma.cat, secretaria@prisma.cat, suport.informatic@prisma.cat, suport@prisma.cat";

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

    											$dir_destino = '../../campus/intranet/tutors/factures/';
    											$originales = 'ÀÁÂÃÄÅÆÇÈÉÊËÌÍÎÏÐÑÒÓÔÕÖØÙÚÛÜÝÞßàáâãäåæçèéêëìíîïðñòóôõöøùúûýýþÿŔŕ';
        									$modificadas = 'AAAAAAACEEEEIIIIDNOOOOOOUUUUYBSaaaaaaaceeeeiiiidnoooooouuuyybyRr';
    											$nom_tutor = utf8_decode(str_replace(' ','_',$_POST[nom_factura]));
    											$nom_tutor = strtr($nom_tutor, utf8_decode($originales), $modificadas);
    											$nom_tutor = utf8_encode($nom_tutor);
    											$extension = substr(basename($_FILES['archivo1']['name']),-4,4);
    											$imagen_subida = $dir_destino.$any_data_actual.$mes_data_actual."_Curs_".$_POST[any].$_POST[curs].$mesCoord."_Coordinacio_".$nom_tutor.$extension;

                          //Variables del metodo POST
    											if(!is_writable($dir_destino)){
    											}
                          else
                          {
    												if(is_uploaded_file($_FILES['archivo1']['tmp_name'])){
    													if (move_uploaded_file($_FILES['archivo1']['tmp_name'], $imagen_subida)) {
    														echo "L'arxiu s'ha enviat correctament.<br>";
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
    										echo "Has enviat la factura correctament.";
    									}

    									?><div align="center"><br /><input type="button" name="boto" class="botones" value="GESTIONAR UN ALTRE CURS" onclick="window.location.href='./gestio_cobraments_extern.php'"/></div><?php

                    }
                  }

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
          							echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
          						}
                      else // mostrem les dades del curs a gestionar
                      {
          					  	if ($_SESSION['usuari']=="G67253443") {								
								      							       $result = mysqli_query ($connexio,"SELECT c.CURS, c.MES, c.ANY, `NOM CURS` AS titol, AULA, Data_llarga_Inici, Data_llarga_Fin, data_informe, DNI_TUTOR, id_cuho, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits FROM cursos AS c, inscripcions AS i WHERE (DNI_TUTOR LIKE '43674436N' OR DNI_TUTOR LIKE '79302336S') and c.CURS='".$_POST[curs_tut."$n"]."' AND c.ANY=".$_POST[any_tut."$n"]." AND c.MES='".$_POST[mes_tut."$n"]."' AND pagat='N' AND c.CURS=i.CURS AND c.ANY=i.ANY AND c.MES=i.MES AND AULA=Grup GROUP BY Grup");
                             }
          						  else
  							          $result = mysqli_query ($connexio,"SELECT c.CURS, c.MES, c.ANY, `NOM CURS` AS titol, AULA, Data_llarga_Inici, Data_llarga_Fin, data_informe, DNI_TUTOR, id_cuho, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits FROM cursos AS c, inscripcions AS i WHERE (DNI_TUTOR LIKE '".$_SESSION['usuari']."') and c.CURS='".$_POST[curs_tut."$n"]."' AND c.ANY=".$_POST[any_tut."$n"]." AND c.MES='".$_POST[mes_tut."$n"]."' AND pagat='N' AND c.CURS=i.CURS AND c.ANY=i.ANY AND c.MES=i.MES AND AULA=Grup GROUP BY Grup");

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
                            $dni2 = $row['DNI_TUTOR'];
                            $id_cuho = $row['id_cuho'];
    												$inscrits_totals = $inscrits_totals + $row['inscrits'];
                            if (is_null($row['data_informe']))
    													$informe .= strtolower($row['AULA']);
    											}
                        }

                        echo "<p align=left><strong>CURS:</strong> ".$titol."</p>";
                        echo "<p align=left><strong>DATES:</strong> ".$dates."</p>";
                        echo "<p align=left><strong>TOTAL ALUMNES INSCRITS:</strong> ".$inscrits_totals."</p>";

                        if ($informe != "")
                        {
                          $quant = strlen($informe);
                          $inf="";

                          for ($i=0; $i<$quant; $i++)
    											{
    												$inf .= "<a href=https://www.prisma.cat/campus/intranet/tutors/informes/cursos/".strtolower($curs).".php?shortname=".$any.strtoupper($curs).$mes.$informe[$i]." target=_blank>".$any.$curs.$mes.strtoupper($informe[$i])."</a> ";
    											}

                          echo "<p align=left><strong>INFORMES:</strong> ".$inf."</p>";
                        }
                        else
                        {
                          if (mysqli_num_rows($result)>1)
    												echo "<p align=left><strong>INFORMES:</strong> enviats</p>";
    											else
    												echo "<p align=left><strong>INFORME:</strong> enviat</p>";
                        }
						
						if ($_SESSION['usuari']=="G67253443") {
							$result_i = mysqli_query ($connexio,"SELECT PREU_ALUMNE AS preu, IRPF, NOM, COGNOMS, MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT from personal as p, honoraris as h WHERE p.DNI=h.DNI_TUTOR  and h.ID=314 AND h.PERFIL='tutor'");
						}
						else {
							$result_i = mysqli_query ($connexio,"SELECT PREU_ALUMNE AS preu, IRPF, NOM, COGNOMS, MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT FROM (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN personal as p ON h.DNI_TUTOR = p.DNI WHERE r.id_cuho=".$id_cuho." AND h.PERFIL='tutor'");
						}

                        $row_i = mysqli_fetch_array($result_i);

                        $row_irpf_username = $row_i['IRPF'];
                        $row_IBAN_username = $row_i['IBAN'];
                        $row_OBS_COBRAMENT_username = $row_i['OBS_COBRAMENT'];
                        $row_nom_username = $row_i['NOM'];
                        $row_cog_username = $row_i['COGNOMS'];
                        $row_correu_username = $row_i['correu'];

                        if ($inscrits_totals >=15)
    											$import = $inscrits_totals * $row_i['preu'];
    										else
    										{
    											$import = 15 * $row_i['preu'];
    											$nota = "<p align=left style=color:#666666><em>NOTA: es cobra un mínim de 15 alumnes per edició.</em></p>";
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
          							echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
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

                        /*if ($curs == "SIST" && $_SESSION['usuari']!="77897279M") //cas AD'S.
            						{
                          				if ($any > 2019 || ($any==2019 && ($mes=='09' or $mes=='10' or $mes=='11' or $mes=='12'))) {
            								$dni2="G67253443";
            							}
            							else {
            								$dni2="G17843830";
            							}
            						}
            						else  */ if ($_SESSION['usuari']=="B25750407") //cas Boira
            						{
            							//$dni2="B25750407";
            							$dni2="43400030L";
            						}
            						else
            						{
            							$dni2=$_SESSION['usuari'];
            						}

                        $result_dades_duo = mysqli_query ($connexio, "SELECT c.CURS, c.ANY, `NOM CURS` AS titol, AULA, Data_llarga_Inici, Data_llarga_Fin, data_informe, c.id_cuho, r.id_hono, h.perfil, h.DNI_TUTOR, PREU_ALUMNE as preu, IRPF, p.NOM, p.COGNOMS, p.MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits FROM (((cursos AS c INNER JOIN inscripcions AS i ON c.CURS = i.CURS AND c.MES = i.MES AND c.ANY = i.ANY AND i.Grup=c.AULA) INNER JOIN rel_cuho as r ON c.id_cuho = r.id_cuho) INNER JOIN honoraris as h ON h.id = r.id_hono ) INNER JOIN personal as p ON (h.DNI_TUTOR = p.DNI) WHERE c.CURS='".$curs."' AND c.ANY=".$any." AND (c.MES='".$mes1."' or c.MES='".$mes2."' or c.MES='".$mes3."') AND pagat='N' AND h.perfil='duo' AND h.DNI_TUTOR LIKE '".$dni2."' GROUP BY r.id_hono");

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
                        }

                        if ($irpf <= 0)
                        {
            							$importnet = $import;
            						}
            						else
            						{
            							$importnet = $import - $irpf;
            						}

                        echo "<p align=left><strong>CURS:</strong> ".$titol."</p>";
            						echo "<p align=left><strong>TRIMESTRE:</strong> ".$trimestre."</p>";
            						echo "<p align=left><strong>TOTAL ALUMNES INSCRITS:</strong> ".$inscrits_totals."</p>";

                        $bestreta = 'X';
						            $result_b = mysqli_query ($connexio,"SELECT SUM(QUANTITAT) AS bestreta, PAGAT FROM bestretes WHERE AUTOR LIKE '".$dni2."' AND CURS ='".$curs."'");
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
    										echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
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

                            if ($curs == "SIST" && $_SESSION['usuari']=="67253443") //cas EHS
                            {
                                 $dni2="";
                            }
                       
                            else if ($curs == "SIST" && $_SESSION['usuari']=="43674436") //cas Laura Soliva
                            {
                                 $dni2="43674436N";
                            }
                       /* if ($curs == "SIST" && $_SESSION['usuari']!="77897279M") //cas AD'S.
                            {
                                if ($any > 2019 || ($any==2019 && ($mes=='09' or $mes=='10' or $mes=='11' or $mes=='12')) )
                                    $dni2="G67253443";
                                else
                                    $dni2="G17843830";
                            }*/ 
                             
                            else if ($_POST[dni] == "25750407" or $_POST[dni] == "43400030L") //cas Boira
                            {
                                $dni2="B25750407";
                                //$dni2="43400030L";
                            }
                            else
                            {
                                $dni2=$_SESSION['usuari']; echo $dni2;
                            }


                        $result_dades_autor = mysqli_query ($connexio, "SELECT c.CURS, c.ANY, `NOM CURS` AS titol, AULA, Data_llarga_Inici, Data_llarga_Fin, data_informe, c.id_cuho, r.id_hono, h.perfil, h.DNI_TUTOR, PREU_ALUMNE as preu, IRPF, p.NOM, p.COGNOMS, p.MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits FROM (((cursos AS c INNER JOIN inscripcions AS i ON c.CURS = i.CURS AND c.MES = i.MES AND c.ANY = i.ANY AND i.Grup=c.AULA) INNER JOIN rel_cuho as r ON c.id_cuho = r.id_cuho) INNER JOIN honoraris as h ON h.id = r.id_hono ) INNER JOIN personal as p ON (h.DNI_TUTOR = p.DNI) WHERE c.CURS='".$curs."' AND c.ANY=".$any." AND (c.MES='".$mes1."' or c.MES='".$mes2."' or c.MES='".$mes3."') AND pagat='N' AND h.perfil='autor' AND h.DNI_TUTOR LIKE '".$dni2."' GROUP BY r.id_hono");

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
                        }

                        if ($irpf <= 0)
                        {
            							$importnet = $import;
            						}
                        else
                        {
            							$importnet = $import - $irpf;
            						}

                        echo "<p align=left><strong>CURS:</strong> ".$titol."</p>";
            						echo "<p align=left><strong>TRIMESTRE:</strong> ".$trimestre."</p>";
            						echo "<p align=left><strong>TOTAL ALUMNES INSCRITS:</strong> ".$inscrits_totals."</p>";

                        $bestreta = 'X';
            						$result_b = mysqli_query ($connexio,"SELECT SUM(QUANTITAT) AS bestreta, PAGAT FROM bestretes WHERE AUTOR LIKE '".$dni2."' AND CURS ='".$curs."'");
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
            						echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
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

                       /*  if ($curs == "SIST" && $_SESSION['usuari']!="77897279M") //cas AD'S.
            						{
            							if ($any > 2019 || ($any==2019 && ($mes=='09' or $mes=='10' or $mes=='11' or $mes=='12')) )
            								$dni2="G67253443";
            							else
            								$dni2="G17843830";
            						}
            						else */ if ($_POST[dni] == "25750407" or $_POST[dni] == "43400030L") //cas Boira
            						{
            							$dni2="B25750407";
            							//$dni2="43400030L";
            						}
            						else
            						{
            							$dni2=$_SESSION['usuari'];
            						}
                          

                        $result_dades_coord = mysqli_query ($connexio, "SELECT c.CURS, c.ANY, `NOM CURS` AS titol, AULA, Data_llarga_Inici, Data_llarga_Fin, data_informe, c.id_cuho, r.id_hono, h.perfil, h.DNI_TUTOR, PREU_ALUMNE as preu, IRPF, p.NOM, p.COGNOMS, p.MAIL_PRISMA AS correu, IBAN, OBS_COBRAMENT, SUM(CASE WHEN (`INSC CURS` = '1') THEN 1 ELSE 0 END) inscrits FROM (((cursos AS c INNER JOIN inscripcions AS i ON c.CURS = i.CURS AND c.MES = i.MES AND c.ANY = i.ANY AND i.Grup=c.AULA) INNER JOIN rel_cuho as r ON c.id_cuho = r.id_cuho) INNER JOIN honoraris as h ON h.id = r.id_hono ) INNER JOIN personal as p ON (h.DNI_TUTOR = p.DNI) WHERE c.CURS='".$curs."' AND c.ANY=".$any." AND (c.MES='".$mes1."' or c.MES='".$mes2."' or c.MES='".$mes3."') AND pagat='N' AND h.perfil='coord' AND h.DNI_TUTOR LIKE '".$dni2."' GROUP BY r.id_hono");

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
                        }

                        if ($irpf <= 0)
                        {
            							$importnet = $import;
            						}
                        else
                        {
            							$importnet = $import - $irpf;
            						}

                        echo "<p align=left><strong>CURS:</strong> ".$titol."</p>";
            						echo "<p align=left><strong>TRIMESTRE:</strong> ".$trimestre."</p>";
            						echo "<p align=left><strong>TOTAL ALUMNES INSCRITS:</strong> ".$inscrits_totals."</p>";
                      }

                    }

                    ?>
                    <form name="gestions" method="post" action="<?php echo $PHP_SELF ?>" enctype="multipart/form-data" onSubmit="validar(this)">
                      <p align="left"><strong>IMPORT (BRUT):</strong> <?php echo $import; ?> euros</p>
                      <?php echo $nota;
                      if ($row_irpf_username < 0)
                      {
                        ?><p align="left"><strong>IRPF: </strong>No aplica</p><?php
                      }
                      else
                      {
                        ?><p align="left"><strong>IRPF (<?php echo $row_irpf_username; ?>%):</strong> <?php echo $irpf; ?> euros</p><?php
                      }
                      ?>
                      <p align="left"><strong>IMPORT (NET):</strong> <?php echo $importnet; ?> euros</p>
                      <?php if ($bestreta != 'X')
                      {
                        ?><p align="left"><strong>BESTRETA: </strong> <?php echo $bestreta; ?> euros</p>
                        <p align="left"><strong>PENDENT BESTRETA: </strong> <?php echo $pendent_bestreta; ?> euros</p>
                        <p align="left"><strong>IMPORT A COBRAR: </strong> <?php echo $import_a_cobrar; ?> euros</p><?php
                      }
                      ?>
                      <p align="left"><strong>IBAN:</strong> <?php echo $row_IBAN_username ?></p>
                      <p align="left"><strong>OBSERVACIONS COBRAMENT:</strong> <?php if ($row_OBS_COBRAMENT_username == NULL || $row_OBS_COBRAMENT_username == "") { echo "No n'hi ha cap"; } else { echo $row_OBS_COBRAMENT_username; } ?></p>
                      <p align="left"><br />Adjuntar factura/rebut: <input type='file' name='archivo1' id='archivo1' accept=".pdf"></p>

                      <?php
          						if ($_POST[gestio."$_POST[num]"]=="GESTIONAR")
          						{
          						  ?><div align="center"><br /><input type="submit" name="confirmar" class="botones" value="HI ESTIC DACORD" onclick="return comprovar_validar()"/></div><?php
          						}
          						if ($_POST[gestio2."$_POST[num]"]=="GESTIONAR")
          						{
          						  ?><div align="center"><br /><input type="submit" name="confirmar2" class="botones" value="HI ESTIC DACORD" onclick="return comprovar_validar()"/></div><?php
          						}
          						if ($_POST[gestio3."$_POST[num]"]=="GESTIONAR")
          						{
          						  ?><div align="center"><br /><input type="submit" name="confirmar3" class="botones" value="HI ESTIC DACORD" onclick="return comprovar_validar()"/></div><?php
          						}
          		        if ($_POST[gestio4."$_POST[num]"]=="GESTIONAR")
          						{
          						  ?><div align="center"><br /><input type="submit" name="confirmar4" class="botones" value="HI ESTIC DACORD" onclick="return comprovar_validar()"/></div><?php
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

                      <input type="hidden" name="dni" value="<?php echo $_SESSION['usuari']; ?>" />
                      <input type="hidden" name="correu" value="<?php echo $row_correu_username; ?>" />
                      <input type="hidden" name="qui" value="<?php echo $_SESSION['nom_factura_complert']; ?>" />
      								<input type="hidden" name="nom_factura" value="<?php echo $_SESSION['nom_factura'] ?>" />

                      <p align="left"><br /><br />Hi ha alguna dada incorrecta? (Quantitat d'alumnes, número de compte, el botó «HI ESTIC DACORD» està desactivat...) Pots especificar-ho en el camp següent i clicar al botó «ENVIAR PER A REVISIO.</p>
                      <textarea name="comentaris" style="width:990px; height:65px"></textarea>

                      <?php
            					if ($_POST[gestio."$_POST[num]"]=="GESTIONAR")
            					{
            					  ?><div align="center"><br /><input type="submit" name="revisio" class="botones" value="ENVIAR PER A REVISIO" onclick="return  comprovar_revisar(this.form)"/></div><?php
            					}
            					if ($_POST[gestio2."$_POST[num]"]=="GESTIONAR")
            					{
            					  ?><div align="center"><br /><input type="submit" name="revisio2" class="botones" value="ENVIAR PER A REVISIO" onclick="return  comprovar_revisar(this.form)"/></div><?php
            					}
            					if ($_POST[gestio3."$_POST[num]"]=="GESTIONAR")
            					{
            					  ?><div align="center"><br /><input type="submit" name="revisio3" class="botones" value="ENVIAR PER A REVISIO" onclick="return comprovar_revisar(this.form)"/></div><?php
                      }
                      if ($_POST[gestio4."$_POST[num]"]=="GESTIONAR")
            					{
            					  ?><div align="center"><br /><input type="submit" name="revisio4" class="botones" value="ENVIAR PER A REVISIO" onclick="return comprovar_revisar(this.form)"/></div><?php
                      }
                      ?>
                      <input type="hidden" name="revisio_o_gestionar" id="revisio_o_gestionar" value="0" />
                    </form>
                    <?php
                  }

                  else
                  {
                    $connexio = mysqli_connect('localhost',$usuari,$pw,$bbdd);
                    mysqli_set_charset ($connexio, "utf8");

                    if (mysqli_connect_errno())
                    {
                      echo "No es pot connectar: " . mysqli_connect_error() . "<br><br>Si continua, podeu posar-vos en contacte amb suport@prisma.cat. <br>Disculpeu les molèsties.";
                    }
                    else
                    {
                       $dni_tutor_username=$_SESSION['usuari'];

          						if ($_SESSION['usuari']=="G67253443") {//Poso com a dni el de l'empresa d'EHS, el dni de la Laura, l'Ester 
          							//if (date(Y) > 2019 || (date(Y)==2019 && date(n)>=9) ) {
          								$dni_tutor_username2 = 	"G67253443' OR h.DNI_TUTOR LIKE '43674436N' OR h.DNI_TUTOR LIKE '79302336S";
          								$dni_tutor_username= 'G67253443';
          							/*}
          							else {
          								$dni_tutor_username2 = 	"G17843830' OR h.DNI_TUTOR LIKE '43674436N' OR h.DNI_TUTOR LIKE '79302336S'";
          								$dni_tutor_username='G17843830';
          							}*/

          							//$dni_user = '43674436N'; // dni Laura
          						}
          						if ($_SESSION['usuari']=="43400030L") { //Si és el Daniel, poso com a dni el d'en Daniel i el de l'empresa Boira
          							$dni_tutor_username2 = '43400030L'; //buscar els cursos relacionats amb una persona
          							$dni_tutor_username = 'B25750407';

          							//$dni_tutor_username2 = 	$_SESSION['usuari']."' OR h.DNI_TUTOR LIKE '43400030L";
          							//$dni_user = '43400030L';
          						}
          						/*if ($_SESSION['usuari']=="39352558H" or $_SESSION['usuari']=="B87456992" or $_SESSION['usuari']=="77897279M") {//Si és la Neus o el FB o la Carmen boix, poso com a dni el seu.
          							$dni_tutor_username2 = $_SESSION['usuari'];
          							$dni_user = $_SESSION['usuari'];
          						}*/
                                if ($_SESSION['usuari']=="43674436")
                                    $dni_tutor_username2 = "43674436N";
                        
                        
                                //$dni_tutor_username2 = $_SESSION['usuari'];
                                

        					   if ($_SESSION['usuari']=="G67253443") {							  
        						  //Buquem tots els cursos relacionats, dels quals són tutors
        						  $tutories = mysqli_query ($connexio, "SELECT c.ANY, c.CURS, c.MES, `DATA INICI` AS datai, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, c.DNI_TUTOR, c.id_cuho, h.preu_alumne, h.irpf, h.perfil FROM cursos as c, honoraris as h, rel_cuho as r WHERE (c.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND (DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0 ) AND c.pagat = 'N' AND PERFIL = 'tutor'  AND (c.ANY > 2019 OR (c.ANY=2019 AND c.MES>='09')) AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY c.CURS, c.ANY, c.MES ORDER BY c.MES, c.CURS, c.ANY");

        						}
        				  	 /* else if ($_SESSION['usuari']=="G17843830") {
        						  //Buquem tots els cursos relacionats, dels quals són tutors
        						  $tutories = mysqli_query ($connexio, "SELECT c.ANY, c.CURS, c.MES, `DATA INICI` AS datai, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, c.DNI_TUTOR, c.id_cuho, h.preu_alumne, h.irpf, h.perfil FROM cursos as c, honoraris as h, rel_cuho as r WHERE (c.DNI_TUTOR LIKE '".$dni_tutor_username2."') AND (DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0 ) AND c.pagat = 'N' AND PERFIL = 'tutor'  AND (FALSE)) AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY c.CURS, c.ANY, c.MES ORDER BY c.MES, c.CURS, c.ANY");

        						 }*/
        					  else {
        					  	 $tutories = mysqli_query ($connexio, "SELECT c.ANY, c.CURS, c.MES, `DATA INICI` AS datai, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, c.DNI_TUTOR, c.id_cuho, h.preu_alumne, h.irpf, h.perfil FROM cursos as c, honoraris as h, rel_cuho as r WHERE (c.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND (DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0 ) AND c.pagat = 'N' AND PERFIL = 'tutor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY c.CURS, c.ANY, c.MES ORDER BY c.MES, c.CURS, c.ANY");
        						 }

						          //Busquem tots els cursos duo relacionats amb la persona
                      //$duos = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE h.DNI_TUTOR LIKE '".$dni_tutor_username."' AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat =  'N'  AND perfil =  'duo' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");

        					  /*if ($_SESSION['usuari']=="G17843830") {
        					  	$duos = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."') AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N' AND FALSE  AND perfil =  'duo' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS");
        					  }
        					  else {*/
        					  	$duos = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'duo' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS");
        						//}
        					  //Busquem tots els cursos autoria relacionats amb la persona
                              //$autories = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE h.DNI_TUTOR LIKE '".$dni_tutor_username."' AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'autor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id ORDER BY c.MES, c.CURS, c.ANY");
                           
                            if ($_SESSION['usuari'] == '43674436') {
        						$autories = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE (h.DNI_TUTOR LIKE '43674436N') AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'autor' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS");
                            }
                            else {
                                $autories = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'autor' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS"); 
                            }
                        
        						//Busquem tots els cursos coordinació relacionats amb la persona
                              //$coordinacions = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE h.DNI_TUTOR LIKE '".$dni_tutor_username."' AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'coord' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id ORDER BY c.MES, c.CURS, c.ANY");
        						$coordinacions = mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'coord' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS");
                      
        						/*

        						echo "SELECT c.ANY, c.CURS, c.MES, `DATA INICI` AS datai, DATEDIFF(CURRENT_DATE,`DATA FIN`) AS dies_passats, c.DNI_TUTOR, c.id_cuho, h.preu_alumne, h.irpf, h.perfil FROM cursos as c, honoraris as h, rel_cuho as r WHERE (c.DNI_TUTOR LIKE '".$dni_tutor_username2."') AND (DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0 ) AND c.pagat = 'N' AND PERFIL = 'tutor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY c.CURS, c.ANY, c.MES ORDER BY c.MES, c.CURS, c.ANY<br>";

        						echo "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."') AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'duo' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS<br>";

        					  echo "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."') AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'autor' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS<br>";

        						echo "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM  (rel_cuho as r INNER JOIN honoraris as h ON r.id_hono = h.ID) INNER JOIN cursos AS c ON c.id_cuho = r.id_cuho WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."')AND (DATEDIFF(CURRENT_DATE, `DATA FIN`)>=0)  AND pagat =  'N'  AND perfil =  'coord' GROUP BY c.CURS, ANY, MES ORDER BY c.ANY,c.MES, c.CURS<br>";

        						*/

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
                          ?>
                          <table style="width:470px; clear: both;" class="info_pay" cellspacing="0" align="left">
                          <?php
                          $i = 1;
                          $cap = true;
                          while ($row_t = mysqli_fetch_array($tutories))
                          {
                            //comprovem si la tutoria ja està pagada
                            $tutories_ja_pagades = mysqli_query ($connexio, "SELECT ID FROM cobraments WHERE DNI_TUTOR LIKE '".$dni_tutor_username."' AND ANY=".$row_t['ANY']." AND CURS='".$row_t['CURS']."' AND MES='".$row_t['MES']."' AND ROL='T'");
                            //si el resultat és 0, vol dir que la tutoria no està pagada
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
                                  
                                  if ($_SESSION['usuari'] != '43674436') {
                                 ?>
                                 <tr><td style="width:80px; text-align:center; background-color:#839872; font-weight: bold; color:#FFFFFF;" colspan="6">Tutoria</td></tr>
                                 <tr>
                                   <td style="width:80px; text-align:center"><b>ANY</b></td>
                                   <td style="width:80px; text-align:center"><b>CURS</b></td>
                                   <td style="width:80px; text-align:center"><b>MES</b></td>
                                   <td style="width:120px; text-align:center"><b>DIES FINALITZAT</b></td>
                                   <td style="width:110px; text-align:center"></td>
         												</tr>
                              <?php
                                  }
                              }

                              $mes_curs = intval($row_t['MES']);
                              $any_curs = intval($row_t['ANY']);
                              $dni_tutor_curs = $row_t['DNI_TUTOR'];
                                                        
                                if ($_SESSION['usuari'] != '43674436') {
                                                
                              ?>
                              <tr>
                              <td style="width:80px; text-align:center"><?php echo($row_t['ANY']); ?></td>
                              <td style="width:80px; text-align:center"><?php echo($row_t['CURS']); ?></td>
                              <td style="width:80px; text-align:center"><?php echo($row_t['MES']); ?></td>
                              <td style="width:120px; text-align:center"><?php echo($row_t['dies_passats']); ?></td>
                              <td style="width:110px; text-align:center" align="center">
                              <?php
                              //si té pendent enviar certificat estat laboral
                              if ($row_t['dies_passats'] >= 15)
                              {
                                ?>
                                <input type="submit" name="<?php echo "gestio".$i ?>" value="GESTIONAR" onclick="gest(<?php echo $i; ?>,<?php echo $i; ?>)" />
                                <?php
                              }
                              else
                              {
                                echo "-";
                              }
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
                          <table style="width:470px; clear: both;" class="info_pay" cellspacing="0" align="left">
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

                            $curs_final_a_comprovar= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND MES='".$trim_mes_final."' AND ANY=".$row_d['ANY']." AND h.CURS='".$row_d['CURS']."' AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'duo' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");
                            $curs_segon_a_comprovar= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND MES='".$trim_segon_mes."' AND ANY=".$row_d['ANY']." AND h.CURS='".$row_d['CURS']."' AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'duo' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");

              							/*if ($row_d['ANY']==2019 or $row_d['ANY']==2018) {
              								echo $row_d['CURS']."<BR>";
              								echo "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."') AND MES='".$trim_mes_final."' AND ANY=".$row_d['ANY']." AND h.CURS='".$row_d['CURS']."'  AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'duo' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY<br>";
              								echo "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."') AND MES='".$trim_segon_mes."' AND ANY=".$row_d['ANY']." AND h.CURS='".$row_d['CURS']."'  AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'duo' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY<br><br>";
              							} */

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
                              $duos_ja_pagats = mysqli_query ($connexio, "SELECT * FROM cobraments WHERE DNI_TUTOR LIKE '".$dni_tutor_username."' AND ANY=".$row_d['ANY']." AND CURS='".$row_d['CURS']."' AND MES='".$tri."' AND ROL='D'");


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
                                    
                                    if ($_SESSION['usuari'] == 'G67253443') {
                                   ?>
                                   <tr><td style="width:80px; text-align:center; background-color:#839872; font-weight: bold; color:#FFFFFF;" colspan="6">Coordinació</td></tr>
                                  <?php } else { ?>
                                       <tr><td style="width:80px; text-align:center; background-color:#839872; font-weight: bold; color:#FFFFFF;" colspan="6">Autoria i coordinació</td></tr>
                                    <?php }
                                    ?>                                   
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

                        // autories
                        if ((mysqli_num_rows($autories)>0) && ($_SESSION['usuari'] != 'G67253443'))
                        {
                        ?>
                          <table style="width:470px; clear: both;" class="info_pay" cellspacing="0" align="left">
                          <?php
                          $i3 = 1;
                          $cap3 = true;
                          $mesAuto = 0;
                          while ($row_a = mysqli_fetch_array($autories))
                          {
                            //Comprovar a quin trimestre pertoca
                            if($row_a['MES']=='01' || $row_a['MES']=='02' || $row_a['MES']=='03')
                            {
                              $trim_mes_final = '03';
                              $trim_segon_mes = '02';
                              $trimestre = "1r";
                              $mesAuto=3;
                              $tri = "1T";
                            }
                            else if($row_a['MES']=='04' || $row_a['MES']=='05' || $row_a['MES']=='06')
                            {
              								$trim_mes_final = '06';
              								$trim_segon_mes = '05';
                                            $trimestre = "2n";
                                            $mesAuto=6;
                                            $tri = "2T";
              							}
              							else if($row_a['MES']=='07' || $row_a['MES']=='08' || $row_a['MES']=='09')
                            {
              							$trim_mes_final = '09';
              							$trim_segon_mes = '08';
                                            $trimestre = "3r";
                                            $mesAuto=9;
                                            $tri = "3T";
              							}
              							if($row_a['MES']=='10' || $row_a['MES']=='11' || $row_a['MES']=='12')
                            {
              								$trim_mes_final = '12';
              								$trim_segon_mes = '11';
                              $trimestre = "4t";
                              $mesAuto=12;
                              $tri = "4T";
    												}
                            $curs_final_a_comprovar= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND MES='".$trim_mes_final."' AND ANY=".$row_a['ANY']." AND h.CURS='".$row_a['CURS']."' AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'autor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");

                              
                            $curs_segon_a_comprovar= mysqli_query ($connexio, "SELECT h.id, h.DNI_TUTOR, h.PREU_ALUMNE, h.CURS, h.PERFIL, c.ANY, c.CURS, c.MES, c.HORES, DATEDIFF( CURRENT_DATE, `DATA FIN`) AS dies_passats, c.id_cuho FROM honoraris AS h, rel_cuho AS r, cursos AS c WHERE (h.DNI_TUTOR LIKE '".$dni_tutor_username2."%') AND MES='".$trim_segon_mes."' AND ANY=".$row_a['ANY']." AND h.CURS='".$row_a['CURS']."' AND DATEDIFF(CURRENT_DATE,`DATA FIN`)>=0  AND pagat = 'N'  AND perfil =  'autor' AND c.id_cuho = r.id_cuho AND r.id_hono = h.id GROUP BY  id_cuho, MES, ANY ORDER BY c.MES, c.CURS, c.ANY");

                            $OK="false";

                            if(mysqli_num_rows($curs_final_a_comprovar)==0)
                            {

                              if(mysqli_num_rows($curs_segon_a_comprovar)==0)
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

                            $any_actual = $row_a['ANY'];
                            $any_anterior = $row_a['ANY']-1;
                            $any_seguent = $row_a['ANY']+1;

                            if ($tri=="1T" or $tri=="2T")
                              $curs_escolar_actual = $any_anterior."/".$any_actual;
                            else
                              $curs_escolar_actual = $any_actual."/".$any_seguent;

                            $result_dates = mysqli_query ($connexio, "SELECT * FROM dates_cobraments WHERE HORES=".$row_a['HORES']." AND curs_escolar='".$curs_escolar_actual."'");
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
                              $autories_ja_pagats = mysqli_query ($connexio, "SELECT * FROM cobraments WHERE DNI_TUTOR LIKE '".$dni_tutor_username."%' AND ANY=".$row_a['ANY']." AND CURS='".$row_a['CURS']."' AND MES='".$tri."' AND ROL='A'");


              					$posterior = true;
              					if ($row_a['ANY']<2018 || ($row_a['ANY']==2018 && ($tri == "1T" || $tri == "2T")))
              						$posterior = false;


                              //si el resultat és 0, vol dir que l'autoria no està pagada
                              if(mysqli_num_rows($autories_ja_pagats)==0 && $posterior)
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
                                   <tr><td style="width:80px; text-align:center; background-color:#839872; font-weight: bold; color:#FFFFFF;" colspan="6">Autoria</td></tr>
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
                                  <td style="width:80px; text-align:center"><?php echo($trimestre); ?></td>
                                  <td style="width:120px; text-align:center"><?php echo($dies_passats); ?></td>
                                  <td style="width:110px; text-align:center" align="center">
                                    <?php
                                    if ($dies_passats >= 25)
                                    {
                                      ?><input type="submit" name="<?php echo "gestio3".$mesAuto ?>" value="GESTIONAR" onclick="gest(<?php echo $mesAuto; ?>,<?php echo $i3; ?>)" /><?php
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
			                }

                      ?>
                      </form>
                      <?php






                      if (mysqli_num_rows($tutories)<=0 && mysqli_num_rows($duos)<=0 && mysqli_num_rows($autories)<=0 && mysqli_num_rows($coordinacions)<=0)
                      {
                        echo "No tens cap curs pendent de gestionar.";
                      }
                      else
                      {
                        $text = "<p style=text-align:left;clear:both; color:#666666><br><br>";
                        $text_a_partir_de_a_c = " només es podran gestionar a partir del vint-i-cinquè dia al complir el trimestre";
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
          </td>
        </tr>
      </table>
    </body>

    </html>

    <?php
  }
  else
  {
    header("Location: ../acces_extern.php");
    exit;
  }
?>
