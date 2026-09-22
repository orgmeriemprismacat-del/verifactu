<?php

	if ($_SESSION['usuari'] == "45171998")
	{
		$firma = "Pablo Martori";
		$qui = "Pablo";
		$departament = "Departament de Gestió";
		$remitent = "secretaria@prisma.cat";
	}
	else if ($_SESSION['usuari'] == "40360802")
	{
		$firma = "Isabel L&oacute;pez";
		$qui = "Isa";
		$departament = "Departament Informàtic";
		$remitent = "suport@prisma.cat";
	}
	else if ($_SESSION['usuari'] == "77922662")
	{
		$firma = "Meriem Abjil";
		$qui = "Meriem";
		$departament = "Departament Informàtic";
		$remitent = "suport.informatic@prisma.cat";
	}
	else if ($_SESSION['usuari'] == "40342476")
	{
		$firma = "Adam Carmona";
		$qui = "Adam";
		$departament = "Departament de Gestió";
		$remitent = "gestio@prisma.cat";
	}
	else
	{
		$firma = "Equip PrisMa";
		$qui = "PrisMa";
		$departament = "Departament de Formació";
	}

	$coletilla = "<div style='font: 13px/ 1.5  Arial,Helvetica,sans-serif; '>";
 	$coletilla .= "Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa<br>	
	972 21 75 65 - 678 12 36 87<br>
	<a style='text-decoration:none; font-size: 16px; color: #496baa!important' href='https://www.prisma.cat' target='_blank'>www.prisma.cat</a><br />";
	$coletilla .= "<div style='padding-top: 6px; border-bottom: 1px solid #7a7a7b; padding-bottom: 6px; max-width: 352px'>
		<a title='Instagram' name='Instagram' href='https://www.instagram.com/prisma.educacio/' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/instagram.png'>
	  </a><a title='Twitter' name='Twitter' href='https://twitter.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/twitter.png'>
	  </a><a title='YouTube' name='YouTube' href='https://www.youtube.com/channel/UCy5M8DYXgHm5IjI4MIUCXqg' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/youtube.png'>
	  </a><a title='Facebook' name='Facebook' href='https://www.facebook.com/PrisMaFormacio' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/facebook.png'>
		 </a>
		 <a title='TikTok' name='TikTok' href='https://www.tiktok.com/@prisma.educacio' target='_blank' style='text-decoration: none!important;'>
		  <img border='0' style='height: 25px;' src='https://www.prisma.cat/img/social/firma/tiktok.png'>
		 </a>
	</div>";
	$coletilla .= "<p style='font-size:10px' align=justify><br />Aquest missatge es dirigeix exclusivament al seu destinatari; si no &eacute;s aix&iacute;, et preguem que ens ho comuniquis i l’esborris. La informaci&oacute; tractada pot ser confidencial i no est&agrave; permesa la seva comunicaci&oacute;, reproducci&oacute; o distribuci&oacute;. De conformitat amb el que disposa la normativa vigent en protecció de dades (<em>RGPD</em> i <em>LOPD</em>), les teves dades personals estan incorporades als nostres fitxers amb la finalitat de dur a terme correctament les gestions acad&eacute;miques i administratives i mantenir el contacte amb tu per via correu electr&ograve;nic. En qualsevol moment pots exercir els teus drets d'acc&eacute;s, rectificaci&oacute;, cancel·laci&oacute; i oposici&oacute; escrivint a l'Associaci&oacute; per al Desenvolupament Infantil i Familiar PrisMa a atencio.usuari@prisma.cat. Consulta l’<a title=\"Avís legal\" name=\"Avís legal\" href=\"https://www.prisma.cat/avis-legal\" target=\"_blank\">Av&iacute;s legal</a> per a més informaci&oacute;.</p>";

	$coletilla .= "</div>";



?>
