function IsNumeric(valor) 
{ 
	var log=valor.length; 
	var sw="S"; 
	for (x=0; x<log; x++) 
	{ 
		v1=valor.substr(x,1); 
		v2 = parseInt(v1); 
		if (isNaN(v2)) 
			sw= "N";
	} 	
	
	if (sw=="S") 
		return true
	else
		return false
} 

function comprovar_usuari()
{	
	avis1="";
	if(document.inisessio.usuari.value!="Usuari")
	{
		if((document.inisessio.usuari.value.length>9) || !(IsNumeric(document.inisessio.usuari.value)))
		{
			avis1="Usuari incorrecte\n";
			valid=1;
		}
	}	
	else
	{
		avis1="Falta usuari\n";
		valid=1;
	}
}

function comprovar_contrasenya()
{	
	avis2="";
	if(document.inisessio.contrasenya.value!="Contrasenya")
	{
		if((document.inisessio.contrasenya.value.length>13) || (document.inisessio.contrasenya.value.length<6))
		{
			avis2="Contrasenya incorrecta\n";
			valid=1;
		}
	}	
	else
	{
		avis2="Falta contrasenya\n";
		valid=1;
	}
}

//------------------ FUNCIO QUE CRIDA LA RESTA -----------------------
		
function comprovar_ident()
{	
	valid=0;
	
	comprovar_usuari();
	comprovar_contrasenya();
	if(valid==1) 	
		alert(avis1 + avis2);
	
	return(valid==0)	
}