function comprovar1()
{	
	avis1="";
	if(document.resum.any.value=="Cap")
	{
		avis1="Falta triar l'any\n";
		valid=1;
	}
	else
	{
		if(document.resum.curs.value=="Cap")
		{
			avis1="Falta triar la formaci\u00F3\n";
			valid=1;
		}
		else if(document.resum.curs.value=="Sense")
		{
			avis1="No hi ha cap formaci\u00F3 per triar\n";
			valid=1;
		}
	}
}

function comprovar2()
{	
	avis1="";
	if(document.resum.any.value=="Cap")
	{
		avis1="Falta triar l'any\n";
		valid=1;
	}
	else
	{
		if(document.resum.curs.value=="Cap")
		{
			avis1="Falta triar el curs\n";
			valid=1;
		}		
	}
}

function comprovar3()
{	
	avis1="";
	if(document.resum.any.value=="Cap")
	{
		avis1="Falta triar l'any\n";
		valid=1;
	}
	else
	{
		if(document.resum.mesos.value=="Cap")
		{
			avis1="Falta triar el mes\n";
			valid=1;
		}		
	}
}

//------------------ FUNCIO QUE CRIDA LA RESTA -----------------------
		
function validar_altres_formacions()
{	
	valid=0;
	
	comprovar1();
	
	if(valid==1) 	
		alert(avis1);
	
	return(valid==0)	
}

function validar_curs()
{	
	valid=0;
	
	comprovar2();
	
	if(valid==1) 	
		alert(avis1);
	
	return(valid==0)	
}

function validar_mes()
{	
	valid=0;
	
	comprovar3();
	
	if(valid==1) 	
		alert(avis1);
	
	return(valid==0)	
}