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

function IsAlphabetic(str)
{
	var re =  /^[a-zA-Z]+$/;
	if (re.test(str))
		return true
	else
		return false
}

function tiene_numeros(texto)
{
	var numeros="0123456789";

   for(i=0; i<texto.length; i++)
   {
      if (numeros.indexOf(texto.charAt(i),0)!=-1)
	  {
         return true;
      }
   }
   return false;
}


function comprovar_radios()
{
	avis1="";
	var fin="no";

	x=1;

	while(fin=="no")
	{
		g=document.getElementsByName('preg'+x);
		if(g.length!=0)
		{
			for(y=0;y<g.length;y++)
			{
				e=0;
				if (g[y].checked==true)
				{
					e=1;
					break;
				}
			}
			if(e==0)
			{
				valid=1;
				avis1="Puntuar alguns ítems\n";
				break;
				break;
			}
		}
		else
		{
			fin="si";
		}
		x++;
	}
}

function comprovar_expectatives()
{
	avis2="";
	if(document.informe.expectatives.value=="")
	{
		avis2="An\u00E0lisi d'expectatives\n";
		valid=1;
	}
}

function comprovar_mes_debat()
{
	avis3="";
	if(document.informe.mes_debat.value=="")
	{
		avis3="Activitats que m\u00E9s debat han generat\n";
		valid=1;
	}
}

function comprovar_menys_debat()
{
	avis4="";
	if(document.informe.menys_debat.value=="")
	{
		avis4="Activitats que menys debat han generat\n";
		valid=1;
	}
}

function comprovar_dubtes()
{
	avis5="";
	if(document.informe.dubtes.value=="")
	{
		avis5="Dubtes i consultes?\n";
		valid=1;
	}
}

function comprovar_incidencies()
{
	avis6="";
	if(document.informe.incidencies.value=="")
	{
		avis6="Incid\u00E8ncies?\n";
		valid=1;
	}
}

function comprovar_enquesta()
{
	avis7="";
	if(document.informe.enquesta.value=="")
	{
		avis7="An\u00E0lisi de l'enquesta\n";
		valid=1;
	}
}

function comprovar_valoracions()
{
	avis8="";
	if(document.informe.valoracions.value=="")
	{
		avis8="An\u00E0lisi de valoracions\n";
		valid=1;
	}
}

function comprovar_diferencia()
{
	avis_diferencia=avis_diferencia2=avis_diferencia3="";

	if(document.informe.diferencia_aprovats.value!=0)
	{
		avis_diferencia="Hi ha una difer\u00E8ncia en el nombre d'alumnes aprovats\n";
		valid=1;
	}
	if(document.informe.diferencia_suspesos.value!=0)
	{
		avis_diferencia2="Hi ha una difer\u00E8ncia en el nombre d'alumnes no superats\n";
		valid=1;
	}
	if(avis_diferencia!="" || avis_diferencia2!="")
		avis_diferencia3= "Heu de contactar amb Secretaria per corregir l'error\n";
}

function comprovar_suspesos(n)
{
	avis_suspesos="";

	for (i=1; i<n; i++)
	{
		if(document.getElementById('seguiment'+i).value=="")
		{
			avis_suspesos="Falta completar el seguiment d'alumnes no superats\n";
			valid=1;
		}
	}
}


//------------------ FUNCIO QUE CRIDA LA RESTA -----------------------

function comprovar(form,boton,n)
{
	valid=0;
	missatge="Confirmar?";

	if (boton == 'Desa sense enviar')
	{
		document.informe.botPress.value = boton;
        document.informe.submit();
	}
	else // Envia i acaba
	{
		comprovar_diferencia();
		comprovar_suspesos(n);
		comprovar_radios();
		comprovar_expectatives();
		comprovar_mes_debat();
		comprovar_menys_debat();
		comprovar_dubtes();
		comprovar_incidencies();
		comprovar_enquesta();
		comprovar_valoracions();

		if(valid==0)
		{
			if(confirm(missatge))
			{
				valid=0;
				document.informe.botPress.value = boton;
        		document.informe.submit();
			}
			else
				valid=1;
		}
		else
		{

			atencio=separacio=falta="";

			if(avis_diferencia3!="" || avis_suspesos!="")
			{
				atencio="ATENCI\u00D3:\n";
			}
			if ((avis_diferencia3!="" || avis_suspesos!="") && (avis1!="" || avis2!="" || avis3!="" || avis4!="" || avis5!="" || avis6!="" || avis7!="" || avis8!=""))
			{
				separacio="\n\n";
			}
			if(avis1!="" || avis2!="" || avis3!="" || avis4!="" || avis5!="" || avis6!="" || avis7!="" || avis8!="")
			{
				falta="FALTA:\n";
			}

			alert(atencio + avis_diferencia + avis_diferencia2 + avis_diferencia3 + avis_suspesos + separacio + falta + avis1 + avis2 + avis3 + avis4 + avis5 + avis6 + avis7 + avis8);
		}
	}
}
