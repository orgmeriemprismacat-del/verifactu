<?php
if ($_POST["submit"]=="Accedir")
{
	if(($_POST["contrasenya"]<>"Contrasenya") && ($_POST["usuari"]<>"Usuari"))
	{
		$contrasenya=$_POST["contrasenya"];
		$usuari=$_POST["usuari"];

		$contrasenya_encriptada = md5($contrasenya);

		include("./inc/conex.php");
		$link=Conectarse();
		$sql="select Nom, rol from intranet where usuari='".$_POST[usuari]."' and contrasenya='".$contrasenya_encriptada."' and (rol='admin' or rol='tutor')";
		$result=mysql_query($sql,$link);
		if(mysql_num_rows($result)>0)
		{
			$row = mysql_fetch_array($result);

			if ($_POST[usuari] == "87456992")
			{
				session_name("sessio_tutor_extern");
				session_start();
				$_SESSION['usuari']="B87456992";
				$_SESSION['rol']=$row['rol'];
				$_SESSION['name'] = "José Antonio";
				$_SESSION['contrasenya_encriptada']=$contrasenya_encriptada;
				$_SESSION['nom_factura']='Zazil';
				$_SESSION['nom_factura_complert']='Grupo ZAZIL SL';
				Header ("Location: ./extern/gestio_cobraments_extern.php");
			}
			else if ($_POST[usuari] == "25750407")
			{
				session_name("sessio_tutor_extern");
				session_start();
				//$_SESSION['usuari']="B25750407";
				$_SESSION['usuari']="43400030L";
				$_SESSION['rol']=$row['rol'];
				$_SESSION['name'] = "Daniel";
				$_SESSION['contrasenya_encriptada']=$contrasenya_encriptada;
				$_SESSION['nom_factura']='Daniel_Gabarro';
				$_SESSION['nom_factura_complert']='Boira Editorial. Formacio i serveis. SL';
				Header ("Location: ./extern/gestio_cobraments_extern.php");
			}
			else if ($_POST[usuari] == "17843830")
			{
				session_name("sessio_tutor_extern");
				session_start();
				$_SESSION['usuari']="G17843830";
				$_SESSION['rol']=$row['rol'];
				$_SESSION['contrasenya_encriptada']=$contrasenya_encriptada;
				$_SESSION['name'] = "ADS";
				$_SESSION['nom_factura']='Ads_escola';
				$_SESSION['nom_factura_complert']='ADs Escola';
				Header ("Location: ./extern/gestio_cobraments_extern.php");
			}
			else if ($_POST[usuari] == "67253443") // SIST
			{
				session_name("sessio_tutor_extern");
				session_start();
				$_SESSION['usuari']="G67253443";
				$_SESSION['rol']=$row['rol'];
				$_SESSION['contrasenya_encriptada']=$contrasenya_encriptada;
				$_SESSION['name'] = "AESH";
				$_SESSION['nom_factura']='AESH';
				$_SESSION['nom_factura_complert']='AESH';
				Header ("Location: ./extern/gestio_cobraments_extern.php");
			}
			else if ($_POST[usuari] == "77897279")
			{
				session_name("sessio_tutor_extern");
				session_start();
				$_SESSION['usuari']="77897279M";
				$_SESSION['rol']=$row['rol'];
				$_SESSION['contrasenya_encriptada']=$contrasenya_encriptada;
				$_SESSION['name'] = "Carmen";
				$_SESSION['nom_factura']='Carmen_Boix_Casas';
				$_SESSION['nom_factura_complert']='Carmen Boix Casas';
				Header ("Location: ./extern/gestio_cobraments_extern.php");
			}
			else if ($_POST[usuari] == "39352558")
			{
				session_name("sessio_tutor_extern");
				session_start();
				$_SESSION['usuari']="39352558H";
				$_SESSION['rol']=$row['rol'];
				$_SESSION['name'] = "Neus";
				$_SESSION['contrasenya_encriptada']=$contrasenya_encriptada;
				$_SESSION['nom_factura']='Neus_Ballesteros_Ventura';
				$_SESSION['nom_factura_complert']='Neus Ballesteros Ventura';
				Header ("Location: ./extern/gestio_cobraments_extern.php");
			}
			mysql_free_result($result);
			mysql_close($link);
		}
		else
		{
		?>
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>Intranet</title>
        <script language="Javascript" src="./js/validacions_formulariid.js"></script>
        <link rel="stylesheet" href="./css/estilo_back.css"/>
        </head>

        <body topmargin="0">

        <table width="350" height="180" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main_content" >
            <tr>
				<td align="center">
                    <img src="./img/formacio_quadrat_2.png" />

                    <hr size="1" noshade="noshade" width="300" color="#CCCCCC" style="width:300px;background-color:#CCCCCC; border:1px none; color:#CCCCCC; height:1px; margin-top:0;"/>
                </td>
           	</tr>
            <tr>
				<td align="center">
                    <div id="login">
                    <form name="inisessio" id="inisessio" method="post" action="<?php echo $PHP_SELF ?>" onSubmit="return comprovar_ident()">
                      <input type="text" name="usuari" value="Usuari" onfocus="if (this.value=='Usuari') {this.value='';this.style.color='#000000'}" onblur="if (this.value=='') {this.value='Usuari';this.style.color='#999999'}" style="width:110px" maxlength="8" class="enter"  /> &nbsp;<input type="password" name="contrasenya" value="Contrasenya" onfocus="if (this.value=='Contrasenya') {this.value='';this.style.color='#000000'}" onblur="if (this.value=='') {this.value='Contrasenya';this.style.color='#999999'}" style="width:110px; margin-left:5px;" maxlength="11" class="enter" />
                            <br />
                            <p style="color:#D70000">No coincideixen Usuari i Contrasenya.</p>
                            <br />
                            <br />

                            <input type="submit" name="submit" class="botones" value="Accedir" style="text-align:center;"/>
                            <input type="hidden" name="tipus" value="<?php echo $_POST[rol]; ?>" />
                    </form>
                    </div>           		</td>
           	</tr>
       	</table>
        <div style="text-align:center; width:350px; margin: 10px auto 0 auto">Espai exclusiu per a personal de PrisMa.<br /><br />
        <div style="text-align:center"><a href="javascript:window.close()">Tancar finestra</a></div>
        </div>
	</body>
</html>
		<?
        }
	}
	else
	{
	?>	<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>Intranet</title>
        <script language="Javascript" src="./js/validacions_formulariid.js"></script>
        <link rel="stylesheet" href="./css/estilo_back.css"/>
        </head>

        <body topmargin="0">

        <table width="350" height="200" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main_content" >
            <tr>
				<td align="center">
                    <img src="./img/formacio_quadrat_2.png" />

                    <hr size="1" noshade="noshade" width="300" color="#CCCCCC" style="width:300px;background-color:#CCCCCC; border:1px none; color:#CCCCCC; height:1px; margin-top:20px;"/>
           		</td>
           	</tr>
			<tr>
				<td align="center">
                    <div id="login">
                    <form name="inisessio" id="inisessio" method="post" action="<?php echo $PHP_SELF ?>" onSubmit="return comprovar_ident()">
                    		<input type="text" name="usuari" value="Usuari" onfocus="if (this.value=='Usuari') {this.value='';this.style.color='#000000'}" onblur="if (this.value=='') {this.value='Usuari';this.style.color='#999999'}" style="width:115px" maxlength="8" class="enter"  />

                            <input type="password" name="contrasenya" value="Contrasenya" onfocus="if (this.value=='Contrasenya') {this.value='';this.style.color='#000000'}" onblur="if (this.value=='') {this.value='Contrasenya';this.style.color='#999999'}" style="width:115px; margin-left:5px;" maxlength="11" class="enter" />
                            <br />
                            <p style="color:#D70000">Cal omplir els camps Usuari i Contrasenya!</p>
                            <br />
                            <br />

                            <input type="submit" name="submit" class="botones" value="Accedir" style="text-align:center;"/>

                    </form>
                    </div>

           		</td>
           	</tr>
        </table>
	</body>
</html>

<?php
    }
}
else
{
?>
      <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />
        <title>Intranet</title>
        <script language="Javascript" src="./js/validacions_formulariid.js"></script>
        <link rel="stylesheet" href="./css/estilo_back.css"/>
        </head>

        <body topmargin="0">

        <table width="350" height="200" align="center" bgcolor="#FFFFFF" cellspacing="0" cellpadding="0" id="main_content" >
            <tr>
				<td align="center">
                    <img src="./img/formacio_quadrat_2.png" />

                    <hr size="1" noshade="noshade" width="300" color="#CCCCCC" style="width:300px;background-color:#CCCCCC; border:1px none; color:#CCCCCC; height:1px; margin-top:20px;"/>
           		</td>
           	</tr>
            <tr>
				<td align="center">
                    <div id="login">
                    <form name="inisessio" id="inisessio" method="post" action="<?php echo $PHP_SELF ?>" onSubmit="return comprovar_ident()">
                    		<input type="text" name="usuari" value="Usuari" onfocus="if (this.value=='Usuari') {this.value='';this.style.color='#000000'}" onblur="if (this.value=='') {this.value='Usuari';this.style.color='#999999'}" style="width:120px" maxlength="8" class="enter"  />

                            <input type="password" name="contrasenya" value="Contrasenya" onfocus="if (this.value=='Contrasenya') {this.value='';this.style.color='#000000'}" onblur="if (this.value=='') {this.value='Contrasenya';this.style.color='#999999'}" style="width:120px; margin-left:5px;" maxlength="12" class="enter" />
                            <br />
                            <br />

                            <input type="submit" name="submit" class="botones" value="Accedir" style="text-align:center;"/>

                    </form>
                    </div>

           		</td>
           	</tr>
      	</table>
        <div style="text-align:center; width:350px; margin: 10px auto 0 auto">Espai exclusiu per a personal de PrisMa.<br /><br />
        <div style="text-align:center"><a href="javascript:window.close()">Tancar finestra</a></div>
        </div>
	</body>
</html>

<?php
}
?>
