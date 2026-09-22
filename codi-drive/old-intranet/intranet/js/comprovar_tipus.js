function triat()
{	
	avis1="";
	if ((document.moodle.tipus[0].checked == false) && (document.moodle.tipus[1].checked == false))
	{
		avis1="Falta triar l'opci\u00F3\n";
		valid=1;
	}
}

function camp()
{	
	avis2="";
	if ((document.moodle.valor.value == ""))
	{
		avis2="Falta introduir la id\n";
		valid=1;
	}
}

function camp_act()
{	
	avis3="";
	if ((document.actualitzar.timestamp.value == ""))
	{
		avis3="Falta introduir la data/hora de modificaci\u00F3 (timestamp)\n";
		valid=1;
	}
}

//------------------ FUNCIO QUE CRIDA LA RESTA -----------------------
		
function comprovar_tipus()
{	
	valid=0;
	
	triat();
	camp();
	
	if(valid==1) 	
		alert(avis1 + avis2);
	
	return(valid==0)	
}

function comprovar_actualitzacio()
{	
	valid=0;
	
	camp_act();
	
	if(valid==1) 	
		alert(avis3);
	
	return(valid==0)	
}



