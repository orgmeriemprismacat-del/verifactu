function comprovar_mail()
{
	avis1="";
	if (document.tutoria.email.value=="")
	{
		avis1="Correu electr\u00F2nic\n";
		valid=1;
	}	
}

function comprovar_consulta()
{	
	avis2="";
	if(document.tutoria.consulta.value=="")
	{
		avis2="Consulta\n";
		valid=1;
	}
}


//------------------ FUNCIO QUE CRIDA LA RESTA -----------------------
		
function comprovar()
{
	valid=0;
	missatge="Enviar consulta";
	
	comprovar_mail();	
	comprovar_consulta();
	
	if(valid==0) 
	{		
		if(confirm(missatge))
			valid=0;
		else
			valid=1;
	}
	else
	{
		alert("FALTA:\n" + avis1 + avis2);
	}	
	return(valid==0)
}