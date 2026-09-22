var urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let hashUrl = null;
if ( window.location.hash.split('#')[1])
	hashUrl = window.location.hash.split('#')[1].split('/')[1];

let dni = "",
	email = "",
	nom = "",
	cognoms = "",
	telefon = "",
	any = "",
	mes = "",
	curs = "",
	inscrit = "",
	certificat = "",
	poblacio = "",
	perfil = "",
	titulacio = "",
	comhapagat = "",
	obspagament = "",
	reclamat = "",
	obs = "",
	comentaris = "",
	databaixa = "",
	perenne = "",
	cercaPer = "",
	elementsCercats = "",
	numeroCanvi = -1,
	paginaFactura,
	numPaginesFactura,
	dniesProv = [],
	dniesCercats = [],
	primeraCerca = 1,
	numElemntCerc = 0,
	numResCerc = 0;

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain_v5.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

requestMain.done(function( message ) {
	$('.mainpanel').html(message);
	activaFuncions();
	if ( hashUrl ) {
		$('#mostrar-alumne #dni').val(hashUrl);
		$('#mostrar-alumne #dni').prev().addClass('active');
		$('#cercar-alumne').click();
	}
});
requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function activaFuncions() {
	//quan es clica a qualsevol lloc fora del select, amago el desplegable
	$(window).click(function() {
		//amago el desplegable
		$('.select .select-list').hide();
		//retorno el triangle com esta per defecte
		var triangle = $('.select').find("i");
		triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
	});

	//quan estas focus en el camp, elimino el marcatge de l'input
	$('#mostrar-alumne').on('focus', '.form-control', function() {
		$(this).parent().removeClass('element-cercat-marcat');
	});

	//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
	$('#mostrar-alumne').on('blur', '.form-control', function() {
		if ($(this).val().trim() == '')
			$(this).removeClass('element-cercat-marcat');
		else
			$(this).addClass('element-cercat-marcat');
	});

	$("#mostrar-alumne .select").click(function(e) {
		e.stopPropagation();
		var lista = $(this).find("ul"),
			triangle = $(this).find("i");
		e.preventDefault();
		$(this).find("ul").toggle();
		if (lista.is(":hidden")) {
			triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
		} else {
			triangle.removeClass("fa-angle-down").addClass("fa-angle-up");
		}
	});

	$("#mostrar-alumne .select").on("click", "li", function(e) {
		$("#modalCanviCurs .error").removeClass('error');
		var texto = $(this).text(),
			element = $(this).parent().prev(),
			lista = $(this).closest("ul"),
			triangle = $(this).parent().next(),
			id = $(this).attr('id');
		e.preventDefault();
		e.stopPropagation();
		element.text(texto);
		lista.hide();
		triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
		$(this).parent().parent().prev().addClass('active');
		//marco el select de la cerca quan s'ha escrit alguna cosa en el camp
		if ($(this).parent().prev().html() == '' || $(this).parent().prev().html().toLowerCase().includes("qualsevol"))
			$(this).parent().parent().removeClass('element-cercat-marcat');
		else
			$(this).parent().parent().addClass('element-cercat-marcat');
	});

	/* Si premo la tecla ENTER, es reprodueix l'event de clicar del cercar-alumne*/
	$("#mostrar-alumne").keyup(function(evObject) {
		if (evObject.keyCode == 13) $('#cercar-alumne').click();
	});

	/* Busco l'alumne o els diferents registres que poden coincidir amb la cerca */
	$('#cercar-alumne').on('click', function() {
		numResCerc = 0;
		numElemntCerc = 0;
		dni = $('#dni').val().trim();
		email = $('#email').val().trim();
		nom = $('#nom').val().trim();
		cognoms = $('#cog').val().trim();
		telefon = $('#tel').val().trim();
		any = $('#anys-dispo .element-selected').html().trim();
		mes = $('#mesos-dispo .element-selected').html().trim();
		curs = $('#cursos-dispo .element-selected').html().trim();
		inscrit = $('#inscrit .element-selected').html().trim();
		certificat = $('#cert').val().trim();
		poblacio = $('#poblacio').val().trim();
		perfil = $('#perfil').val().trim();
		titulacio = $('#titol').val().trim();
		comhapagat = $('#com-ha-pagat .element-selected').html().trim();
		obspagament = $('#obs-pag').val().trim();
		reclamat = $('#reclamat').val().trim();
		obs = $('#obs').val().trim();
		comentaris = $('#comentaris').val().trim();
		databaixa = $('#data-baixa').val().trim();
		perenne = $('#perenne .element-selected').html().trim();
		cercaPer = "RESULTATS DE LA CERCA PER ";
		elementsCercats = "";

		//si la cerca avançada esta magada, posem tots els camps de la cerca a buit
		if ($('#amaga-cerca-avancada').css('display') == 'none') {
			telefon = "";
			any = "";
			mes = "";
			curs = "";
			inscrit = "";
			certificat = "";
			poblacio = "";
			perfil = "";
			titulacio = "";
			comhapagat = "";
			obspagament = "";
			reclamat = "";
			obs = "";
			comentaris = "";
			databaixa = "";
			perenne = "";
		} else {
			// Si algun desplegable té la opció marcada "qualsevol", el transformem a caracter buit
			if (any.toLowerCase().includes("qualsevol"))
				any = "";
			if (mes.toLowerCase().includes("qualsevol"))
				mes = "";
			if (curs.toLowerCase().includes("qualsevol"))
				curs = "";
			if (inscrit.toLowerCase().includes("qualsevol"))
				inscrit = "";
			if (perenne.toLowerCase().includes("qualsevol"))
				perenne = "";
			if (comhapagat.toLowerCase().includes("qualsevol"))
				comhapagat = "";
		}

		if (
			(
				($('#amaga-cerca-avancada').css('display') == 'none') &&
				(dni == '' && email == '' && nom == '' && cognoms == '')
			) ||
			(
				($('#amaga-cerca-avancada').css('display') != 'none') &&
				(dni == '' && email == '' && nom == '' && cognoms == '' &&
					telefon == '' && any == '' && mes == '' && curs == '' &&
					inscrit == '' && certificat == '' && poblacio == '' && perfil == '' &&
					titulacio == '' && comhapagat == '' && obspagament == '' && reclamat == '' &&
					obs == '' && comentaris == '' && databaixa == '' && perenne == '')
			)
		) {
			afegirHeaderModalError("Omple un camp per poder fer la cerca");
			mostrarModalError();
		}
		else {
			if (dni != '') {
				elementsCercats += "DNI «" + dni + "»"; numElemntCerc++;
			}
			if (email != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "E-MAIL «" + email + "»";
				numElemntCerc++;
			}
			if (nom != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "NOM «" + nom + "»";
				numElemntCerc++;
			}
			if (cognoms != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "COGNOMS «" + cognoms + "»";
				numElemntCerc++;
			}
			if (telefon != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "TELEFON «" + telefon + "»";
				numElemntCerc++;
			}
			if (any != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "ANY «" + any + "»";
				numElemntCerc++;
			}
			if (mes != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "MES «" + mes + "»";
				numElemntCerc++;
			}
			if (curs != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "CURS «" + curs + "»";
				numElemntCerc++;
			}
			if (inscrit != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "INSCRIT «" + inscrit + "»";
				numElemntCerc++;
			}
			if (certificat != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "CERTIFICAT «" + certificat + "»";
				numElemntCerc++;
			}
			if (poblacio != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "POBLACIÓ «" + poblacio + "»";
				numElemntCerc++;
			}
			if (perfil != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "PERFIL «" + perfil + "»";
				numElemntCerc++;
			}
			if (titulacio != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "TITULACIÓ «" + titulacio + "»";
				numElemntCerc++;
			}
			if (comhapagat != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "COM HA PAGAT «" + comhapagat + "»";
				numElemntCerc++;
			}
			if (obspagament != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "OBSERVACIONS PAGAMENT «" + obspagament + "»";
				numElemntCerc++;
			}
			if (reclamat != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "RECLAMAT «" + reclamat + "»";
				numElemntCerc++;
			}
			if (obs != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "OBSERVACIONS «" + obs + "»";
				numElemntCerc++;
			}
			if (comentaris != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "COMENTARIS «" + comentaris + "»";
				numElemntCerc++;
			}
			if (databaixa != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "DATA BAIXA «" + databaixa + "»";
				numElemntCerc++;
			}
			if (perenne != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "PERENNE «" + perenne + "»";
				numElemntCerc++;
			}

			cercaPer += elementsCercats;
			mostrarModalLoading();

			buscarUsuaris(dni, email, nom, cognoms, telefon, any, mes, curs, inscrit, certificat,
			poblacio, perfil, titulacio, comhapagat, obspagament, reclamat, obs,
			comentaris, databaixa, perenne);
		}
	});

	$('#mostra-cerca-avancada').on('click', function() {
		$('#mostra-cerca-avancada').fadeOut('fast', function() {
			$('#mostrar-alumne .cerca-av').fadeIn('fast', function() {});
			$('#amaga-cerca-avancada').fadeIn('fast', function() {});
			$('#eliminar-filtres').fadeIn('fast', function() {});
		});
	});
	$('#amaga-cerca-avancada').on('click', function() {
		$('#amaga-cerca-avancada').fadeOut('fast', function() {
			$('#eliminar-filtres').fadeOut('fast', function() {});
			$('#mostrar-alumne .cerca-av').fadeOut('fast', function() {});
			$('#mostra-cerca-avancada').fadeIn('fast', function() {});
		});
	});

	$('#eliminar-filtres').on('click', function() {
		$('#mostrar-alumne .select .element-selected').html('');
		$('#mostrar-alumne input').prev().removeClass('active');
		$('#mostrar-alumne .select').prev().removeClass('active');
		$('#mostrar-alumne input').val('');
		$('#mostrar-alumne .element-cercat-marcat').removeClass('element-cercat-marcat');
	});
}

function buscarUsuaris(dni, email, nom, cognoms, telefon, any, mes, curs, inscrit, certificat,
poblacio, perfil, titulacio, comhapagat, obspagament, reclamat, obs,
comentaris, databaixa, perenne) {
	console.log("buscarusuaris");
	if ( dni != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Dni", dni);
	}
	if ( email != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Email", email);
	}
	if ( nom != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Nom", nom);
	}
	if ( cognoms != '' && ( (cognoms.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Cognoms", dni);
	}
	if ( telefon != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Tel", telefon);
	}
	if ( any != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Any", any);
	}
	if ( mes != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Mes", mes);
	}
	if ( curs != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Curs", curs);
	}
	if ( inscrit != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Insc", inscrit);
	}
	if ( certificat != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Cert", certificat);
	}
	if ( poblacio != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Poble", poblacio);
	}
	if ( perfil != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Perfil", perfil);
	}
	if ( titulacio != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Titul", titulacio);
	}
	if ( comhapagat != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("ComHaPagat", comhapagat);
	}
	if ( obspagament != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("ObsPag", obspagament);
	}
	if ( reclamat != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Recl", reclamat);
	}
	if ( obs != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Obs", obs);
	}
	if ( comentaris != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Coment", comentaris);
	}
	if ( databaixa != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("DataBaixa", databaixa);
	}
	if ( perenne != '' && ( (dniesCercats.length != 0 && !primeraCerca) || (primeraCerca) ) ) {
		searchUsuariBy("Perenne", perenne);
	}

}

// function searchUsuariBy(param, elem) {
// 	console.log("searchusuariby: " + param + " " + elem);
// 	var req1 = $.ajax({
// 	   url: path + "alumnes/searchUserBy"+param+".php",
// 	   method: "GET",
// 	   data: {
// 	      by: elem
// 	   },
// 	   dataType: "html"
// 	});
//
// 	req1.done(function( res ) {
// 	   if ( primeraCerca ) {
// 	      dniesCercats = res.split('|'); primeraCerca = 0;
// 			console.log("primera cerca " + dniesCercats);
// 	   }
// 	   else {
// 			console.log("----");
// 			console.log("DNI" + dniesCercats);
// 	      var array1 = dniesCercats;
// 			console.log("ARRAY1" + array1);
// 	      var array2 = res.split('|');
// 			console.log("ARRAY2" + array2);
// 	      var dniesCercats = array1.filter(function(n) {
// 	          return array2.indexOf(n) !== -1;
// 	      });
// 	   }
// 		numResCerc++;
// 		if ( numResCerc == numElemntCerc ) {
// 			if ( dniesCercats.length == 0 ) {
// 				amagarLoadingModal();
// 				afegirHeaderModalError("Alerta");
// 				afegirTextModalError("No s'han trobat resultats");
// 				mostrarModalError();
// 			}
// 			else if (dniesCercats.length > 2000) {
// 				amagarLoadingModal();
// 				afegirHeaderModalError("Alerta");
// 				afegirTextModalError("El volum de dades cercat és molt gran. Si us plau, afegeix algun filtre més per acotar el volum de dades");
// 				mostrarModalError();
// 			}
// 			else {
// 				if ( dniesCercats.length == 1 ) { //Hi ha un sol usuari amb la cerca realitzada
// 					cercarUSuari(dniesCercats[0]);
// 				}
// 				else { //Hi ha més d'un sol usuari amb la cerca realitzada
// 					//Mostra la taula amb els usuaris trobats a partir de la cerca realitzada
// 					mostraLlistatUsuaris(dniesCercats, 'cog', 'asc')
// 				}
// 			}
// 		}
// 	});
//
// 	req1.fail(function( jqXHR, textStatus, errorThrown ) {
// 	   rerrorFunction( jqXHR, textStatus, errorThrown,
// 	      "Hi ha hagut algun error a l'hora de cercar el parametre: " );
// 	});
// }
function searchUsuariBy(param, elem) {
	console.log("searchusuariby: " + param + " " + elem);
	var req1 = $.ajax({
	   url: path + "alumnes/searchUserBy"+param+".php",
	   method: "GET",
	   data: {
	      by: elem
	   },
	   dataType: "html"
	});

	req1.done(function( res ) {
      dniesProv[numResCerc] = res.split('|');
		console.log("cerca " + dniesProv[numResCerc]);
		numResCerc++;
		if ( numResCerc == numElemntCerc ) {
			dniesCercats = dniesProv[0];
			for ( i=1; i < numElemntCerc; i++ ) {
				console.log("----");
		      var array1 = dniesCercats;
				console.log("ARRAY1" + array1);
		      var array2 = dniesProv[i];
				console.log("ARRAY2" + array2);
				dniesCercats = array1.filter(function(n) {
		          return array2.indexOf(n) !== -1;
		      });
				console.log("DNI" + dniesCercats);
			}
			if ( dniesCercats.length == 0 ) {
				amagarLoadingModal();
				afegirHeaderModalError("Alerta");
				afegirTextModalError("No s'han trobat resultats");
				mostrarModalError();
			}
			else if (dniesCercats.length > 2000) {
				amagarLoadingModal();
				afegirHeaderModalError("Alerta");
				afegirTextModalError("El volum de dades cercat és molt gran. Si us plau, afegeix algun filtre més per acotar el volum de dades");
				mostrarModalError();
			}
			else {
				if ( dniesCercats.length == 1 ) { //Hi ha un sol usuari amb la cerca realitzada
					cercarUSuari(dniesCercats[0]);
				}
				else { //Hi ha més d'un sol usuari amb la cerca realitzada
					//Mostra la taula amb els usuaris trobats a partir de la cerca realitzada
					mostraLlistatUsuaris(dniesCercats, 'cog', 'asc')
				}
			}
		}
	});

	req1.fail(function( jqXHR, textStatus, errorThrown ) {
	   rerrorFunction( jqXHR, textStatus, errorThrown,
	      "Hi ha hagut algun error a l'hora de cercar el parametre: " );
	});
}

//Mostra la taula amb els usuaris trobats a partir de la cerca realitzada ordenada
//per cognoms si orderby es cog, per nom si ordery es nom i per dni si orderby
//es dni i en ascendentment si asc és 1 i en descendentment si és 0.
function mostraLlistatUsuaris(dnies, orderby, asc) {
	$('#resultats-cerca .card-body').off();
	$('.table-order').off();

	//Mostra la taula amb els usuaris trobats a partir de la cerca realitzada
	var req1 = $.ajax({
	   url: path + "alumnes/mostrarTaulaUsuaris.php",
	   method: "GET",
	   data: {
	      dnies : JSON.stringify(dnies),
	      orderBy : orderby,
	      asc : asc
	   },
	   dataType: "html"
	});

	req1.done(function( info ) {
		amagarLoadingModal();
		$('#resultats-cerca').html(info);
		$('#resultats-cerca').show();
		//Quan sel·lecciono un registre de la taula, mostro la informació de l'usuari
		$('#resultats-cerca').on('click', '.seleccionar', function() {
			mostrarModalLoading();
			cercarUSuari($(this).attr('id'));
		});
		//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
		//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
		$('.table-order').on('click', '.sorting', function() {
			var id = $(this).attr('id').substr(3, $(this).attr('id').length);
			if ($(this).hasClass('asc'))
				mostraLlistatUsuaris(dnies, id, 0);
			else
				mostraLlistatUsuaris(dnies, id, 1);
		});
	});

	req1.fail(function( jqXHR, textStatus, errorThrown ) {
	   rerrorFunction( jqXHR, textStatus, errorThrown,
	      "Hi ha hagut algun error a l'hora de mostrar taula usuaris: " );
	});
}

//Mostrar informació de l' usuari a partir del dni de l'usuari cercat
function cercarUSuari(dniUser) {
	var req1 = $.ajax({
	   url: path + "alumnes/mostrarInformacioUsuari.php",
	   method: "GET",
	   data: {
	      dni : dniUser,
	      cercaPer : cercaPer
	   },
	   dataType: "html"
	});

	req1.done(function( info ) {
		amagarLoadingModal();

		if (dniUser.trim() != '') {
			$('#resultats-cerca').html(info);
			$('#resultats-cerca').show();
		}
		else {
			afegirHeaderModalError("Aquest alumne no té DNI!");
			mostrarModalError();

			$('#modalErrors').on('click', '.btn-danger', function() {
				amagarModalError();
			});
			$('#modalErrors').on('click', '.close', function() {
				amagarModalError();
			});
		}
		//Es necessita perquè el tooltip funcioni
		$(function() {
			$('[data-toggle="tooltip"]').tooltip();
		})

		$("#resultats-cerca .card-body").off();
		$("#resultats-cerca .card-body").on("click", ".tipus", function() {
			var idTipus = $(this).attr('id').split('-')[1];
			var tipus = $(this).attr('id').split('-')[2];
			var tipusInsc = $("#tipus-"+idTipus).html().trim();

			var getModal = $.ajax({
				url: path + "alumnes/mostrarModalInfoPag.php",
				global: false,
				method: "GET",
				data: {
					id: idTipus,
					tipus: tipusInsc
				},
				dataType: "html"
			});
			getModal.done(function( msg ) {
				if ( !msg.toLowerCase().includes("error") || (msg.toLowerCase().includes("error")  || !msg.toLowerCase().includes("found") )) {
					$('#infoPag'+tipus+' .modal-body').html(msg);
					bootstrap.Modal.getOrCreateInstance(document.getElementById('#infoPag'+tipus)).show();

					$(".cnsDadesAlumne").remove();

				}
				else {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la informació del pagament");
					mostrarModalError();

					$('#modalErrors').on('click', '.btn-danger', function() {
						amagarModalError();
					});
					$('#modalErrors').on('click', '.close', function() {
						amagarModalError();
					});
				}
			});
			getModal.fail(function( jqXHR, textStatus, errorThrown ) {
				errorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut algun error a l'hora de mostrar la informació del pagament:");
			});
		});

		//es desactiva qualsevol event que depengui de dades-personals
		$('#dades-personals').off();

		/*Si es clica el botó d'.editar-apartat' a l'apartat'#dades-personals',
		s'habilita l'edició en els inputs de l'apartat,
		s'amaga el botó d'edita i s'afageix el botó de guardar resultat i cancel·lar */
		$('#dades-personals').on('click', '.editar-apartat', function() {
			if ( tePermisEdicio ) {
				$('#resultats-cerca #dades-personals .apartat .form-control').removeClass('no-edit');
				$('#resultats-cerca #dades-personals .apartat .form-control').addClass('edit');

				$("#resultats-cerca #dades-personals .apartat .form-group .form-control").each(function() {
					var id = $(this).attr('id');
					var text = $(this).html();
					var parent = $(this).parent();
					$(this).remove();
					parent.append("<input type='text' class='form-control edit' id='" + id + "' name='" + id + "' value=\"" + text + "\">");
				});

				$('#resultats-cerca #dades-personals .editar-apartat').html("save");
				$('#resultats-cerca #dades-personals .editar-apartat').addClass("save-result");
				$('#resultats-cerca #dades-personals .editar-apartat').removeClass("editar-apartat");
				$('#resultats-cerca #dades-personals .titol-apartat').append("<i class='material-icons ml-2 cancelar-apartat'>cancel</i>");
			}
			else {
				mostrarModalNoTensPermisos();
			}
		});

		/*Si es clica el botó d'.save-result' a l'apartat'#dades-personals',
		es guarden els resultats a la BD a la ultima inscripció de la BD amb dni dni,
		s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
		$('#dades-personals').on('click', '.save-result', function() {
			if ( tePermisEdicio ) {
				var idInsc = $('#id-cercat').val().trim();
				let nomcercat = $('#nom-cercat').val().trim();
				let cogcercat = $('#cognoms-cercat').val().trim();
				let dnicercat = $('#dni-cercat').val().trim();
				let emailcercat = $('#email-cercat').val().trim();
				let telcercat = $('#telefon-cercat').val().trim();
				let adrecacercat = $('#adreca-cercat').val().trim();
				let cpcercat = $('#codipostal-cercat').val().trim();
				let poblaciocercat = $('#poblacio-cercat').val().trim();
				let perfilcercat = $('#perfil-cercat').val().trim();
				let titulaciocercat = $('#titulacio-cercat').val().trim();

				$('#resultats-cerca #dades-personals .apartat input').removeClass('error');

				if (!campBuit(nomcercat) && !campBuit(cogcercat) && !campBuit(dnicercat) &&
					!campBuit(emailcercat) && !campBuit(telcercat) && !campBuit(adrecacercat) &&
					!campBuit(cpcercat) && !campBuit(poblaciocercat) && !campBuit(perfilcercat) &&
					!campBuit(titulaciocercat) && validTel(telcercat, dnicercat).length == 0
				) {
					mostrarModalLoading();
					$.ajax({
						url: path + "alumnes/guardarDadesPersonals.php?idInsc=" + idInsc +
							"&nom=" + nomcercat + "&cog=" + cogcercat + "&dni=" + dnicercat + "&email=" +
							emailcercat + "&tel=" + telcercat + "&adreca=" + adrecacercat + "&cp=" +
							cpcercat + "&poblacio=" + poblaciocercat + "&perfil=" + perfilcercat +
							"&titulacio=" + titulaciocercat,
						cache: !1,
						global: false,
						type: "GET",
						success: function(res) {
							amagarLoadingModal();
							if (!res.includes("Error") && !res.includes("error")) {
								afegirHeaderModalSuccess("Els canvis s'han guardat correctament");
								afegirTextModalError('');
								mostrarModalSuccess();
								// $('#resultats-cerca #dades-personals .apartat .form-control').removeClass('edit');
								// $('#resultats-cerca #dades-personals .apartat .form-control').addClass('no-edit');
								$("#resultats-cerca #dades-personals .apartat .form-group .form-control").each(function() {
									var id = $(this).attr('id');
									var text = $(this).val();
									var parent = $(this).parent();
									$(this).remove();
									parent.append("<div class='form-control no-edit' id='" + id + "'>" + text + "</div>");
								});

								$('#resultats-cerca #dades-personals .save-result').html("edit");
								$('#resultats-cerca #dades-personals .save-result').addClass("editar-apartat");
								$('#resultats-cerca #dades-personals .save-result').removeClass("save-result");
								$('#resultats-cerca #dades-personals .cancelar-apartat').remove();
							} else {
								afegirHeaderModalError(res);
								mostrarModalError();

								$('#modalErrors').on('click', '.btn-danger', function() {
									amagarModalError();
									reloadUrl();
								});
								$('#modalErrors').on('click', '.close', function() {
									amagarModalError();
									reloadUrl();
								});
							}
						}
					});
				} else {
					var errors = "";
					if (campBuit(nomcercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("NOM") + "</span>";
						$('#nom-cercat').addClass('error');
					}
					if (campBuit(cogcercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("COGNOMS") + "</span>";
						$('#cognoms-cercat').addClass('error');
					}
					if (campBuit(dnicercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("DNI") + "</span>";
						$('#dni-cercat').addClass('error');
					}
					if (campBuit(emailcercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("E-MAIL") + "</span>";
						$('#email-cercat').addClass('error');
					}
					if (campBuit(telcercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("TELÈFON") + "</span>";
						$('#telefon-cercat').addClass('error');
					} else if (!validTel(telcercat, dnicercat).length == 0) {
						errors += "<span>" + validTel(telcercat, dnicercat) + "</span>";
						$('#telefon-cercat').addClass('error');
					}
					if (campBuit(adrecacercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("ADREÇA") + "</span>";
						$('#adreca-cercat').addClass('error');
					}
					if (campBuit(cpcercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("CODI POSTAL") + "</span>";
						$('#codipostal-cercat').addClass('error');
					}
					if (campBuit(poblaciocercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("POBLACIÓ") + "</span>";
						$('#poblacio-cercat').addClass('error');
					}
					if (campBuit(perfilcercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("TREBALLA A") + "</span>";
						$('#perfil-cercat').addClass('error');
					}
					if (campBuit(titulaciocercat)) {
						errors += "<span>" + missatgeNoPotEstarBuit("TITULACIÓ") + "</span>";
						$('#titulacio-cercat').addClass('error');
					}

					var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
					msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
					msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
					msgError += "<i class='material-icons'>close</i></button>";
					msgError += errors + "</div>";

					$('#resultats-cerca #dades-personals').append(msgError);
				}
			}
			else {
				mostrarModalNoTensPermisos();
			}

		});

		/*Si es clica el botó d'.cancelar-apartat' a l'apartat'#dades-personals',
		es deshabilita l'edició en els inputs de l'apartat,
		s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
		$('#dades-personals').on('click', '.cancelar-apartat', function() {
			if ( tePermisEdicio ) {
				$("#resultats-cerca #dades-personals .apartat .form-group .form-control").each(function() {
					var id = $(this).attr('id');
					var text = $(this).val();
					var parent = $(this).parent();
					$(this).remove();
					parent.append("<div class='form-control no-edit' id='" + id + "'>" + text + "</div>");
				});

				$('#resultats-cerca #dades-personals .save-result').html("edit");
				$('#resultats-cerca #dades-personals .save-result').addClass("editar-apartat");
				$('#resultats-cerca #dades-personals .save-result').removeClass("save-result");
				$('#resultats-cerca #dades-personals .cancelar-apartat').remove();
			}
			else {
				mostrarModalNoTensPermisos();
			}
		});

		//es desactiva qualsevol event que depengui de cursos-pendents, cursos actius o cursos acabats
		$('.regCursos').off();

		$('#mostra-tots-registres').html("Mostra tots els registres");
		/* Si es clica el boto 'mostra-tots-registres', es mostren tots els registres */
		$('#dades-personals').on('click', '#mostra-tots-registres', function() {
			if ($('#tots-cursos').css('display') == 'none') {
				$('#tots-cursos').fadeIn('fast', function() {
					$('#mostra-tots-registres').html("Oculta tots els registres");
				});
			} else {
				$('#tots-cursos').fadeOut('fast', function() {
					$('#mostra-tots-registres').html("Mostra tots els registres");
				});
			}
		});

		/*Si es clica el botó d'.cns-informacio' als apartats '.regCursos',
		es mostra el modal amb la informació de la inscripció amb una id
		d'inscripció igual a l'id del botó */
		$('.regCursos').on('click', '.cns-informacio', function() {
			var id = $(this).attr('id').split('-')[1];
			mostrarModalLoading();
			mostrarModalConsultaInformacio(id);
		});

		/*Si es clica el botó d'.canvi-curs' als apartats '.regCursos',
		es mostra el modal amb el cavni de curs/edicio de la inscripció amb una id
		d'inscripció igual a l'id del botó */
		$('.regCursos').on('click', '.canvi-curs', function() {
			if ( tePermisEdicio ) {
				var id = $(this).attr('id').split('-')[1];
				mostrarModalLoading();
				mostrarModalCanviCurs(id);
			}
			else {
				mostrarModalNoTensPermisos();
			}
		});

		/*Si es clica el botó d'.donar-baixa' als apartats '.regCursos',
		es mostra el modal per donar de baixar de la inscripció amb una id
		d'inscripció igual a l'id del botó */
		$('.regCursos').on('click', '.donar-baixa', function() {
			if ( tePermisEdicio ) {
				var id = $(this).attr('id').split('-')[1];
				mostrarModalLoading();
				mostrarModalDonarBaixa(id);
			}
			else {
				mostrarModalNoTensPermisos();
			}
		});

		/*Si es clica el botó d'.cns-factura' als apartats '.regCursos',
		es mostra el modal per consultar la factura de la inscripció amb una id
		d'inscripció igual a l'id del botó */
		$('.regCursos').on('click', '.cns-factura', function() {
			var id = $(this).attr('id').split('-')[1];
			mostrarModalLoading();
			mostrarModalConsultaFactura(id);
		});

		/*Si es clica el botó d'.cns-certificat' als apartats '.regCursos',
		es mostra el modal per consultar el certificat de la inscripció amb una id
		d'inscripció igual a l'id del botó */
		$('.regCursos').on('click', '.cns-certificat', function() {
			var id = $(this).attr('id').split('-')[1];
			mostrarModalLoading();
			mostrarModalConsultaCertificat(id, "DIGITAL");
		});
		/*Si es clica el botó d'.cns-certificat-inscrit' als apartats '.regCursos',
		es mostra el modal per consultar el certificat de la inscripció amb una id
		d'inscripció igual a l'id del botó */
		$('.regCursos').on('click', '.cns-certificat-inscrit', function() {
			if ( tePermisEdicio ) {
				var id = $(this).attr('id').split('-')[1];
				mostrarModalLoading();
				mostrarModalConsultaCertificat(id, "INSCRIT");
			}
			else {
				mostrarModalNoTensPermisos();
			}
		});

		//es desactiva qualsevol event que depengui
		$('#observacions-generals').off();
		$('#modalObservacions').off();

		/*Si es clica el botó d'.afegir-observacio' a l'apartat'#observacions-generals',
		es mostra el modal d'observacions,
		Si es clica el botó d'.save-observacio' i el camp del modal no està buit,
		es guarda l'observació a la BD */
		$('#observacions-generals').on('click', '.afegir-observacio', function() {
			$('#obs-afegit').html().trim();
			mostrarModalObservacions();
			$('#modalObservacions').on('click', '.save-observacio', function() {
				let obs = $('#obs-afegit').val().trim();
				if (obs != '') {
					mostrarModalLoading();
					$.ajax({
						url: path + "alumnes/afegirObservacio.php?obs=" + obs + "&dni=" + dni,
						cache: !1,
						global: false,
						type: "GET",
						success: function(res) {
							if (!res.includes("Error") && !res.includes("error")) {
								$.ajax({
									url: path + "alumnes/mostrarObservacions.php?dni=" + dni,
									cache: !1,
									global: false,
									type: "GET",
									success: function(obsGen) {
										$('#observacions-generals').html(obsGen);
										amagarLoadingModal();
										amagarModalObservacions();
										afegirHeaderModalSuccess("Els canvis s'han guardat correctament");
										mostrarModalSuccess();

										$('#modalSuccess').on('click', '.btn-success', function() {
											amagarModalSuccess();
										});
										$('#modalSuccess').on('click', '.close', function() {
											amagarModalSuccess();
										});
									}
								});
							} else {
								amagarLoadingModal();
								afegirHeaderModalError(res);
								mostrarModalError();
								reloadUrl();
							}
						}
					});
				} else {
					amagarModalObservacions();
					afegirHeaderModalError("Alerta");
					afegirTextModalError("El camp d'observació no pot estar buit.");
					mostrarModalError();
				}
			});
		});

		/*Si es clica el botó d'ocultar amagar-observacio,
		s'ocultarà el registre a la BD i s'eliminarà el registre */
		$('#observacions-generals').on('click', '.amagar-observacio', function() {
			if ( tePermisEdicio ) {
				var id = $(this).attr('id').split('-')[1];
				var element = $(this);
				mostrarModalLoading();

				//amaga la observacio del bd
				$.ajax({
					url: path + "alumnes/amagarObservacio.php?id=" + id,
					cache: !1,
					global: false,
					type: "GET",
					success: function(res) {
						if (!res.includes("Error") && !res.includes("error")) {
							amagarLoadingModal();
							element.parent().parent().fadeOut('fast', function() {
								element.parent().parent().remove();
							});
						} else {
							amagarLoadingModal();
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora d'amagar el registre");
							mostrarModalError();
							reloadUrl();
						}
					}
				});
			}
			else {
				mostrarModalNoTensPermisos();
			}
		});
	});

	req1.fail(function( jqXHR, textStatus, errorThrown ) {
	   rerrorFunction( jqXHR, textStatus, errorThrown,
	      "Hi ha hagut algun error a l'hora de cercar el parametre: " );
	});
}


//Mostra el modal de consulta la informació de la inscripcio id
function mostrarModalConsultaInformacio(id) {
	//
	var view = $.ajax({
		url: path + "alumnes/mostraModalConsultaInformacio.php",
		method: "GET",
		data: {
			idInsc : id
		},
		dataType: "html"
	});

	view.done(function( res ) {
			if (!res.toLowerCase().includes("error") || (res.toLowerCase().includes("error") && !res.toLowerCase().includes("not found")) ) {
				$("#modalConsultaInformacio .modal-body").html(res);
				amagarLoadingModal();
				bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsultaInformacio')).show();
				$(function() {
					$('[data-toggle="tooltip"]').tooltip();
				})

				$('.modal-info').on('click', '.btn-action', function() {
					// var id = $(this).attr('id').substr(3,$(this).attr('id').length);
					var id = $(this).attr('id').split('-');
					var link;
					if (id[1] == "usuarisInscrits") {
						if (id[0] == 'moodle')
							link = "https://campus.prisma.cat/user/index.php?id=" + id[2];
						else
							link = "https://www.prisma.cat/campus/enrol/users.php?id=" + id[2];
					}
					else if (id[1] == "qualificacio") {
						if (id[0] == 'moodle')
							link = "https://campus.prisma.cat/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];
						else
							link = "https://www.prisma.cat/campus/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];
					}
					else if (id[1] == "participacions") {
						if (id[0] == 'moodle')
							link = "https://campus.prisma.cat/report/outline/user.php?id=" + id[3] + "&course=" + id[2] + "&mode=complete";
						else
							link = "https://www.prisma.cat/campus/report/outline/user.php?id=" + id[3] + "&course=" + id[2] + "&mode=complete";
					}
					else if (id[1] == "dadesCursIntranet") {
						link = "https://intranet.prisma.cat/curs/mostrar-curs/#/" + id[2];
					}
					else if (id[1] == "cursMoodle") {
						if (id[0] == 'moodle')
							link = "https://campus.prisma.cat/course/view.php?id=" + id[2];
						else
							link = "https://www.prisma.cat/campus/course/view.php?id=" + id[2];
					}
					window.open(link, '_blank');
				});

				$('#modalConsultaInformacio').on('click', '.btn-success', function() {
					bootstrap.Modal.getInstance(document.getElementById('modalConsultaInformacio')).hide();
					$('#cercar-alumne').click();
				});
				$('#modalConsultaInformacio').on('click', '.close', function() {
					bootstrap.Modal.getInstance(document.getElementById('modalConsultaInformacio')).hide();
					$('#cercar-alumne').click();
				});

				/*Si es clica el botó d'.editar-apartat' a l'apartat'#dades-inscripcio',
				s'habilita l'edició en els inputs de l'apartat,
				s'amaga el botó d'edita i s'afageix el botó de guardar resultat i cancel·lar */
				$('#dades-inscripcio').on('click', '.editar-apartat', function() {
					if ( tePermisEdicio ) {
						editarApartat('#modalConsultaInformacio #dades-inscripcio');
					}
					else {
					   mostrarModalNoTensPermisos();
					}
				});

				$('#dades-pagament').on('click', '.editar-apartat', function() {
					if ( tePermisEdicio ) {
						editarApartat('#modalConsultaInformacio #dades-pagament');
					}
					else {
					   mostrarModalNoTensPermisos();
					}
				});

				/*Si es clica el botó d'.save-result' a l'apartat'#dades-inscripcio',
				es guarden els resultats a la BD a la ultima inscripció de la BD amb dni dni,
				s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
				$('#dades-inscripcio').on('click', '.save-result', function() {
					if ( tePermisEdicio ) {
						var idinsc = $('#id-insc').html().trim();
						let nominsc = $('#nom-insc').val().trim();
						let coginsc = $('#cognoms-insc').val().trim();
						let dniinsc = $('#dni-insc').val().trim();
						let emailinsc = $('#email-insc').val().trim();
						let telinsc = $('#telefon-insc').val().trim();
						let adrecainsc = $('#adreca-insc').val().trim();
						let cpinsc = $('#codipostal-insc').val().trim();
						let poblacioinsc = $('#poblacio-insc').val().trim();
						let perfilinsc = $('#perfil-insc').val().trim();
						let titulacioinsc = $('#titulacio-insc').val().trim();
						let datesinsc = $('#data-insc').val().trim();
						let inscritinsc = $('#inscrit-insc').val().trim();
						let aulaobertainsc = $('#aula-oberta-insc').val().trim();
						let mailinginsc = $('#mailing-insc').val().trim();
						let certificatinsc = $('#certificat-insc').val().trim();
						let obscertificatinsc = $('#obs-certificat-insc').val().trim();
						let generatinsc = $('#generat-insc').val().trim();
						let obsinsc = $('#obs-insc').val().trim();
						let comentarisinsc = $('#comentaris-insc').val().trim();
						let databaixainsc = $('#data-baixa-insc').val().trim();
						let baixainsc = $('#baixa-insc').val().trim();
						let quibaixainsc = $('#qui-baixa-insc').val().trim();

						$('#modalConsultaInformacio #dades-inscripcio .apartat input').removeClass('error');
						$('#modalConsultaInformacio #dades-inscripcio .alert-danger').remove();

						if (!campBuit(nominsc) && !campBuit(coginsc) && !campBuit(dniinsc) &&
							!campBuit(emailinsc) && !campBuit(telinsc) && !campBuit(adrecainsc) &&
							!campBuit(cpinsc) && !campBuit(poblacioinsc) && !campBuit(perfilinsc) &&
							!campBuit(titulacioinsc) && !campBuit(datesinsc) && !campBuit(inscritinsc) &&
							!campBuit(aulaobertainsc) && !campBuit(mailinginsc) && !campBuit(generatinsc) &&
							validTel(telinsc, dniinsc).length == 0 && validInsc(inscritinsc) && validInsc(aulaobertainsc) &&
							validInsc(mailinginsc) && validNumero(generatinsc) && validData(datesinsc) &&
							validData(databaixainsc)
						) {
							$('#modalConsultaInformacio #dades-inscripcio .apartat').addClass('opacity-02');
							$('#modalConsultaInformacio #dades-inscripcio .loading-wrapper').removeClass('hide');

							var requestSavePers = $.ajax({
								url: path + "alumnes/guardarDadesPersonals_ConsultaInformacio.php",
								method: "GET",
								data: {
									idinsc : idinsc,
									nom : nominsc,
									cog : coginsc,
									dni : dniinsc,
									email : emailinsc,
									tel : telinsc,
									adreca : adrecainsc,
									cp : cpinsc,
									poblacio : poblacioinsc,
									perfil : perfilinsc,
									titulacio : titulacioinsc,
									dates : datesinsc,
									inscrit : inscritinsc,
									ao : aulaobertainsc,
									mailing : mailinginsc,
									certificat : certificatinsc,
									obscertificat : obscertificatinsc,
									generat : generatinsc,
									obs : obsinsc,
									coment : comentarisinsc,
									databaixa : databaixainsc,
									motiubaixa : baixainsc,
									quibaixa : quibaixainsc
								},
								dataType: "html"
							});

							requestSavePers.done(function( res ) {
								if (!res.includes("Error") && !res.includes("error")) {
									$('#modalConsultaInformacio #dades-inscripcio .loading-wrapper').addClass('hide');
									var msgOK = "<div class='alert alert-success alert-with-icon w-100 mb-2'>";
									msgOK += "<i class='material-icons' data-notify='icon'>notifications</i>";
									msgOK += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
									msgOK += "<i class='material-icons'>close</i></button>";
									msgOK += "<span>El canvi s'ha guardat correctament</span></div>";
									$('#modalConsultaInformacio #dades-inscripcio .result-success').html(msgOK);
									$('#modalConsultaInformacio #dades-inscripcio .result-success').removeClass('hide');
								}
								else {
									$('#modalConsultaInformacio #dades-inscripcio .loading-wrapper').addClass('hide');
									var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
									msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
									msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
									msgError += "<i class='material-icons'>close</i></button>";
									msgError += "<span>Hi ha hagut un error amb el registre</span></div>";
									$('#modalConsultaInformacio #dades-inscripcio .result-success').html(msgError);
									$('#modalConsultaInformacio #dades-inscripcio .result-success').addClass('danger');
									$('#modalConsultaInformacio #dades-inscripcio .result-success').removeClass('hide');
								}
								setTimeout(function() {
									$('#modalConsultaInformacio #dades-inscripcio .result-success').fadeOut('slow', function() {
										$('#modalConsultaInformacio #dades-inscripcio .result-success').addClass('hide');
										$('#modalConsultaInformacio #dades-inscripcio .apartat').removeClass('opacity-02');
										$("#modalConsultaInformacio #dades-inscripcio .apartat .form-group .form-control").each(function() {
											var id = $(this).attr('id');
											var text = $(this).val();
											var parent = $(this).parent();
											$(this).remove();
											parent.append("<div class='form-control no-edit' id='" + id + "'>" + text + "</div>");
										});
										$('#modalConsultaInformacio #dades-inscripcio .save-result').html("edit");
										$('#modalConsultaInformacio #dades-inscripcio .save-result').addClass("editar-apartat");
										$('#modalConsultaInformacio #dades-inscripcio .save-result').removeClass("save-result");
										$('#modalConsultaInformacio #dades-inscripcio .cancelar-apartat').remove();
									});
								}, 1500);
							});

							requestSavePers.fail(function( jqXHR, textStatus, errorThrown ) {
								errorFunction( jqXHR, textStatus, errorThrown,
								"Hi ha hagut un error en guardar les dades personals: " );
							});
						} else {
							var errors = "";
							if (campBuit(nominsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("NOM") + "</span>";
								$('#nom-insc').addClass('error');
							}
							if (campBuit(coginsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("COGNOMS") + "</span>";
								$('#cognoms-insc').addClass('error');
							}
							if (campBuit(dniinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("DNI") + "</span>";
								$('#dni-insc').addClass('error');
							}
							if (campBuit(emailinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("E-MAIL") + "</span>";
								$('#email-insc').addClass('error');
							}
							if (campBuit(telinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("TELÈFON") + "</span>";
								$('#telefon-insc').addClass('error');
							} else if (!validTel(telinsc, dniinsc).length == 0) {
								errors += "<span>" + validTel(telinsc, dniinsc) + "</span>";
								$('#telefon-insc').addClass('error');
							}
							if (campBuit(adrecainsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("ADREÇA") + "</span>";
								$('#adreca-insc').addClass('error');
							}
							if (campBuit(cpinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("CODI POSTAL") + "</span>";
								$('#codipostal-insc').addClass('error');
							}
							if (campBuit(poblacioinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("POBLACIÓ") + "</span>";
								$('#poblacio-insc').addClass('error');
							}
							if (campBuit(perfilinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("TREBALLA A") + "</span>";
								$('#perfil-insc').addClass('error');
							}
							if (campBuit(titulacioinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("TITULACIÓ") + "</span>";
								$('#titulacio-insc').addClass('error');
							}
							if (campBuit(datesinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("DATA INSC") + "</span>";
								$('#data-insc').addClass('error');
							} else if (!validData(datesinsc)) {
								errors += "<span>" + missatgeNoTeFormatData("DATA INSC") + "</span>";
								$('#data-insc').addClass('error');
							}
							if (campBuit(inscritinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("INSCRIPCIÓ") + "</span>";
								$('#inscrit-insc').addClass('error');
							} else if (!validInsc(inscritinsc)) {
								errors += "<span>" + missatgeInscritNoValid("INSCRIPCIÓ") + "</span>";
								$('#inscrit-insc').addClass('error');
							}
							if (campBuit(aulaobertainsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("AULA OBERTA") + "</span>";
								$('#aula-oberta-insc').addClass('error');
							} else if (!validInsc(aulaobertainsc)) {
								errors += "<span>" + missatgeInscritNoValid("AULA OBERTA") + "</span>";
								$('#aula-oberta-insc').addClass('error');
							}
							if (campBuit(mailinginsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("INSC MAILING") + "</span>";
								$('#mailing-insc').addClass('error');
							} else if (!validInsc(mailinginsc)) {
								errors += "<span>" + missatgeInscritNoValid("INSC MAILING") + "</span>";
								$('#mailing-insc').addClass('error');
							}
							if (campBuit(generatinsc)) {
								errors += "<span>" + missatgeNoPotEstarBuit("GENERAT") + "</span>";
								$('#generat-insc').addClass('error');
							} else if (!validNumero(generatinsc)) {
								errors += "<span>" + missatgeNoEsNumero("GENERAT") + "</span>";
								$('#generat-insc').addClass('error');
							}
							if (!validData(databaixainsc)) {
								errors += "<span>" + missatgeNoTeFormatData("DATA BAIXA") + "</span>";
								$('#data-baixa-insc').addClass('error');
							}

							var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
							msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
							msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
							msgError += "<i class='material-icons'>close</i></button>";
							msgError += errors + "</div>";

							$('#modalConsultaInformacio #dades-inscripcio').append(msgError);
						}
					}
					else {
					   mostrarModalNoTensPermisos();
					}
				});
				$('#dades-pagament').on('click', '.save-result', function() {
					if ( tePermisEdicio ) {
						guardarPagamentInfo('save');
					}
					else {
					   mostrarModalNoTensPermisos();
					}
				});
				$('#dades-pagament').on('click', '.send-result', function() {
					if ( tePermisEdicio ) {
						guardarPagamentInfo('save-send');
					}
					else {
					   mostrarModalNoTensPermisos();
					}
				});

				$('#dades-pagament').on('click', '#recordatoriPagament-insc', function() {
					if ( $(this).hasClass('lightBlue') ) {
						$(this).addClass('lightRed');
						$(this).removeClass('lightBlue');
						$(this).find('span').html('block');
					}
					else {
						$(this).addClass('lightBlue');
						$(this).removeClass('lightRed');
						$(this).find('span').html('check');
					}
				});

				/*Si es clica el botó d'.cancelar-apartat' a l'apartat'#dades-inscripcio',
				es deshabilita l'edició en els inputs de l'apartat,
				s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
				$('#dades-inscripcio').on('click', '.cancelar-apartat', function() {
					if ( tePermisEdicio ) {
						cancelEditarApartat('#modalConsultaInformacio #dades-inscripcio');
					}
					else {
					   mostrarModalNoTensPermisos();
					}
				});
				$('#dades-pagament').on('click', '#factura-insc.no-edit', function() {
					var numFactura = parseInt( $('#factura-insc').html() );
					var urlConf = 'https://intranet.prisma.cat/alumnes/factura/#/factRel/'+numFactura;
					window.location.replace(urlConf);
				});

				$('#dades-pagament').on('click', '.cancelar-apartat', function() {
					if ( tePermisEdicio ) {
						cancelEditarApartat('#modalConsultaInformacio #dades-pagament');
					}
					else {
					   mostrarModalNoTensPermisos();
					}
				});
			}
			else {
				afegirHeaderModalError("Alerta!");
	         afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la informació de la inscripció");
	         amagarLoadingModal();
	         mostrarModalError();

	         $('#modalErrors').on('click', '.btn-danger', function() {
	            amagarModalError();
	         });
	         $('#modalErrors').on('click', '.close', function() {
	            amagarModalError();
	         });
			}
	});

	view.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora d'actualitzar reclamacions': " );
	});
}

function guardarPagamentInfo(type) {
	console.log(type);
	if ( type == 'save' )
		guardarResultatPagamentInfo( 'guardarDadesPagament_ConsultaInformacio' );
	else if ( type == 'save-send' )
		guardarResultatPagamentInfo( 'guardarEnviarDadesPagament_ConsultaInformacio' );
}

//Guarda el resultat després d'haver editat la informació de pagament des de la pestanya de consulta informació
function guardarResultatPagamentInfo( textFuncio ) {
	console.log(textFuncio);

	var idInsc = $('#id-insc').html().trim();
	let apagarInsc = $('#apagar-insc').val().trim();
	let pagarnsc = $('#pag-insc').val().trim();
	let datapagInsc = $('#data-pag-insc').val().trim();
	let idpagInsc = $('#idpag-insc').val().trim();
	let pagObsInsc = $('#pag-obs-insc').val().trim();
	let fraccioInsc = $('#fraccionat-insc').val().trim();
	let comFraccioInsc = $('#com-fraccionat-insc').val().trim();
	let facturaInsc = $('#factura-insc').val().trim();
	let dataReclamatInsc = $('#data-rec-insc').val().trim();
	let reclamatInsc = $('#reclamat-insc').val().trim();
	let recodatoriPagamentInsc;
	if ( $('#recordatoriPagament-insc').hasClass('lightBlue') ) recodatoriPagamentInsc = 1;
	else recodatoriPagamentInsc = 0;


	$('#modalConsultaInformacio #dades-pagament .apartat input').removeClass('error');
	$('#modalConsultaInformacio #dades-pagament .alert-danger').remove();

	if (!campBuit(apagarInsc) && !campBuit(pagarnsc) && (campBuit(datapagInsc) ||
		(!campBuit(datapagInsc) && validData(datapagInsc)) ) && !campBuit(idpagInsc)
		&& !campBuit(fraccioInsc) && validNumero(apagarInsc) && validNumero(pagarnsc)
		&& validNumero(idpagInsc) && validNumero(fraccioInsc) &&
		validNumero(facturaInsc) && validData(dataReclamatInsc)
	) {
		$('#modalConsultaInformacio #dades-pagament .apartat').addClass('opacity-02');
		$('#modalConsultaInformacio #dades-pagament .loading-wrapper').removeClass('hide');

		var requestSavePag = $.ajax({
			url: path + "alumnes/" + textFuncio + ".php",
			method: "GET",
			data: {
				idInsc: idInsc,
				apagar: apagarInsc,
				pagament: pagarnsc,
				datapag: datapagInsc,
				idpag: idpagInsc,
				pagobs: pagObsInsc,
				fraccio: fraccioInsc,
				comfraccio: comFraccioInsc,
				factura: facturaInsc,
				datarec: dataReclamatInsc,
				rec: reclamatInsc,
				recPag: recodatoriPagamentInsc
			},
			dataType: "html"
		});

		requestSavePag.done(function(res) {
			if (!res.includes("Error") && !res.includes("error")) {
				$('#modalConsultaInformacio #dades-pagament .loading-wrapper').addClass('hide');
				$('#modalConsultaInformacio #dades-pagament .result-success').html("Els canvis s'han guardat correctament");
				$('#modalConsultaInformacio #dades-pagament .result-success').removeClass('hide');
			}
			else {
				$('#modalConsultaInformacio #dades-pagament .result-success').html("Hi ha hagut un error amb el registre");
				$('#modalConsultaInformacio #dades-pagament .result-success').addClass('danger');
				$('#modalConsultaInformacio #dades-pagament .result-success').removeClass('hide');
			}

			setTimeout(function() {
				$('#modalConsultaInformacio #dades-pagament .result-success').fadeOut('slow', function() {
					$('#modalConsultaInformacio #dades-pagament .result-success').addClass('hide');
					$('#modalConsultaInformacio #dades-pagament .apartat').removeClass('opacity-02');
					$("#modalConsultaInformacio #dades-pagament .apartat .form-group .form-control").each(function() {
						var id = $(this).attr('id');
						var text = $(this).val();
						var parent = $(this).parent();
						$(this).remove();
						parent.append("<div class='form-control no-edit' id='" + id + "'>" + text + "</div>");
					});
					$('#modalConsultaInformacio #dades-pagament .save-result').html("edit");
					$('#modalConsultaInformacio #dades-pagament .save-result').addClass("editar-apartat");
					$('#modalConsultaInformacio #dades-pagament .save-result').removeClass("save-result");
					$('#modalConsultaInformacio #dades-pagament .cancelar-apartat').remove();
				});
			}, 1500);
		});

		requestSavePag.fail(function(jqXHR, textStatus, errorThrown) {
			bootstrap.Modal.getInstance(document.getElementById('modalConsultaInformacio')).hide();
			errorFunction(jqXHR, textStatus, errorThrown,
				"Hi ha hagut un error a l'hora de guardar les dades de pagament: ");
		});
	}
	else {
		var errors = "";
		if (campBuit(apagarInsc)) {
			errors += "<span>" + missatgeNoPotEstarBuit("A PAGAR") + "</span>";
			$('#apagar-insc').addClass('error');
		}
		else if (!validNumero(apagarInsc)) {
			errors += "<span>" + missatgeNoEsNumero("A PAGAR") + "</span>";
			$('#apagar-insc').addClass('error');
		}
		if (campBuit(pagarnsc)) {
			errors += "<span>" + missatgeNoPotEstarBuit("PAGAMENT") + "</span>";
			$('#pag-insc').addClass('error');
		}
		else if (!validNumero(pagarnsc)) {
			errors += "<span>" + missatgeNoEsNumero("PAGAMENT") + "</span>";
			$('#pag-insc').addClass('error');
		}
		if (!campBuit(datapagInsc) && !validData(datapagInsc)) {
			errors += "<span>" + missatgeNoTeFormatData("DATA PAGAMENT") + "</span>";
			$('#data-pag-insc').addClass('error');
		}
		if (campBuit(idpagInsc)) {
			errors += "<span>" + missatgeNoPotEstarBuit("IDPAG") + "</span>";
			$('#idpag-insc').addClass('error');
		}
		else if (!validNumero(idpagInsc)) {
			errors += "<span>" + missatgeNoEsNumero("IDPAG") + "</span>";
			$('#idpag-insc').addClass('error');
		}
		if (campBuit(fraccioInsc)) {
			errors += "<span>" + missatgeNoPotEstarBuit("FRACCIONAT") + "</span>";
			$('#fraccionat-insc').addClass('error');
		}
		else if (!validNumero(fraccioInsc)) {
			errors += "<span>" + missatgeNoEsNumero("FRACCIONAT") + "</span>";
			$('#fraccionat-insc').addClass('error');
		}
		if (!validNumero(facturaInsc)) {
			errors += "<span>" + missatgeNoEsNumero("FACTURA") + "</span>";
			$('#factura-insc').addClass('error');
		}
		if (!validData(dataReclamatInsc)) {
			errors += "<span>" + missatgeNoTeFormatData("DATA RECLAMAT") + "</span>";
			$('#data-rec-insc').addClass('error');
		}

		var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
		msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
		msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
		msgError += "<i class='material-icons'>close</i></button>";
		msgError += errors + "</div>";

		$('#modalConsultaInformacio #dades-pagament').append(msgError);
	}
}

//Mostra el modal del canvi de curs de la inscripcio id
function mostrarModalCanviCurs(id) {
	$('.modal-info').off();
	$('#modalCanviCurs').off();
	$("#dades-canvi .apartat .select").off();

	var req = $.ajax({
	   url: path + "alumnes/mostrarModalCanviCurs.php",
	   method: "GET",
	   data: {
	      idInsc : id
	   },
	   dataType: "html"
	});

	req.done(function( res ) {
	   if (!res.toLowerCase().includes("error") || !res.toLowerCase().includes("404")) {
	      $("#modalCanviCurs .modal-body").html(res);
	      amagarLoadingModal();
	      bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCanviCurs')).show();

	      $(window).click(function() {
	         $('.select-list').hide();
	      });

	      /* Es mostra o s'oculta el llistat */
	      $("#dades-canvi .apartat .select").click(function(e) {
	         e.stopPropagation();
	         var lista = $(this).find("ul"),
	            triangle = $(this).find("i");
	         e.preventDefault();
	         $(this).find("ul").toggle();
	         if (lista.is(":hidden")) {
	            triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
	         } else {
	            triangle.removeClass("fa-angle-down").addClass("fa-angle-up");
	         }
	      });
	      $("#dades-canvi .apartat .select").on("click", "li", function(e) {
	         $("#modalCanviCurs .error").removeClass('error');
	         var texto = $(this).text(),
	            element = $(this).parent().prev(),
	            lista = $(this).closest("ul"),
	            triangle = $(this).parent().next(),
	            id = $(this).attr('id');
	         e.preventDefault();
	         e.stopPropagation();
	         element.text(texto);
	         lista.hide();
	         triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
	         if ($(this).parent().parent().attr('id') == 'dades-canvi-any') {
	            buscarMesos_modalCanviCurs();
	            buscarCursos_modalCanviCurs();
	            buscarPreuAPagar();
	         } else if ($(this).parent().parent().attr('id') == 'dades-canvi-mes' && !$(this).attr('id').toLowerCase().includes("triar")) {
	            buscarAnys_modalCanviCurs();
	            buscarCursos_modalCanviCurs();
	            buscarPreuAPagar();
	         } else if ($(this).parent().parent().attr('id') == 'dades-canvi-curs') {
	            buscarAnys_modalCanviCurs();
	            buscarMesos_modalCanviCurs();
	            buscarPreuAPagar();
	         } else if ($(this).parent().parent().attr('id') == 'dades-canvi-numero') {
	            var idNumCanvi = $(this).attr('id');
	            numeroCanvi = parseInt(idNumCanvi.substr(idNumCanvi.length - 1, 1));
	            calcularDespesGestio(numeroCanvi);
	         }
	      });

	      /* Realitzar el calcul del preu pendent quan es modifca el valor del
	      preu a pagar, pagat o despeses */
	      $('#modalCanviCurs').on('blur', '#apagar-nou-registre', function() {
	         realitzaCanvisPreus__modalCanviCurs();
	      });
	      $('#modalCanviCurs').on('blur', '#pagat-nou-registre', function() {
	         realitzaCanvisPreus__modalCanviCurs();
	      });
	      $('#modalCanviCurs').on('blur', '#despeses-registre', function() {
	         realitzaCanvisPreus__modalCanviCurs();
	      });

	      /*Si es clica el botó d'.save-result' a l'apartat'#dades-inscripcio',
	      es guarden els resultats a la BD a la ultima inscripció de la BD amb dni dni,
	      s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
	      $('#modalCanviCurs').on('click', '.save-result', function() {
	         var id = $('#dades-curs-canvi #id-canvi-curs-actual').html().trim();
	         var anyActual = $('#dades-curs-canvi #any-canvi-curs-actual').html().trim();
	         var mesActual = $('#dades-curs-canvi #mes-canvi-curs-actual').html().trim();
	         var cursActual = $('#dades-curs-canvi #curs-canvi-curs-actual').html().trim();
	         var any = $('#dades-canvi #dades-canvi-any .element-selected').html().trim();
	         var mes = $('#dades-canvi #dades-canvi-mes .element-selected').html().trim();
	         var curs = $('#dades-canvi #dades-canvi-curs .element-selected').html().trim();
	         var canvi = $('#dades-canvi #dades-canvi-numero .element-selected').html().trim();
	         var apagar = $('#dades-nou-registre #apagar-nou-registre').val().trim();
	         var pagat = $('#dades-nou-registre #pagat-nou-registre').val().trim();
	         var pendent = $('#dades-nou-registre #pendent-nou-registre').val().trim();
	         var despeses = $('#dades-nou-registre #despeses-registre').val().trim();
	         var obs = $('#dades-nou-registre #obs-canvi').val().trim();
	         var motiu = $('#dades-nou-registre #motiu-canvi').val().trim();
	         var enviarCoreu = $('#dades-nou-registre #no-enviar-correu-alumne').prop('checked');
	         var tipusDesc = $('#tipusDesc-registre').html().trim();
	         var validDesc = $('#validDesc-registre').html().trim();
	         var missatgeError1 = '',
	            missatgeError2 = '';

	         $('#dades-nou-registre .alert-danger').remove();

	         if (id != '' && any != '' && mes != '' && !mes.includes("Triar") && curs != '' && numeroCanvi != -1 && motiu != '') {
	            if (any == anyActual && mes == mesActual && curs == cursActual) {
	               missatgeError1 += "<span>Compte! Has escollit la mateixa edició</span>";
	            }
	            else {
	               amagarModalCanviCurs();
	               $('#modalConfirmacioCanvi').off();

	               var msgConfirmacioCanvi = "<p><strong>Canvi: </strong>";
	               msgConfirmacioCanvi += "<span class='font-weight-bold text-marcat'>" + canvi + " </span></p>";
	               msgConfirmacioCanvi += "<p><strong>A pagar: </strong>";
	               msgConfirmacioCanvi += "<span class='font-weight-bold text-marcat'>" + apagar + " €</span> (a pagar) + ";
	               msgConfirmacioCanvi += "<span class='font-weight-bold text-marcat'>" + despeses + " €</span> (despeses) = ";
	               msgConfirmacioCanvi += "<span class='font-weight-bold text-marcat'>" + (parseFloat(apagar) + parseFloat(despeses)) + " €</span></p>";
	               msgConfirmacioCanvi += "<p><strong>Pagat: </strong>";
	               msgConfirmacioCanvi += "<span class='font-weight-bold text-marcat'>" + pagat + " €</span></p>";
	               msgConfirmacioCanvi += "<p><strong>Pendent: </strong>";
	               msgConfirmacioCanvi += "<span class='font-weight-bold text-marcat'>" + (parseFloat(apagar) + parseFloat(despeses) - parseFloat(pagat)) + " €</span></p>";
	               msgConfirmacioCanvi += "<p><strong>Observacions per al nou curs:</strong> ";
	               msgConfirmacioCanvi += "<span class='font-weight-bold text-marcat'>" + obs + "</span></p>";
	               msgConfirmacioCanvi += "<p><strong>Motiu canvi:</strong> ";
	               msgConfirmacioCanvi += "<span class='font-weight-bold text-marcat'>" + motiu + "</span></p>";

	               afegirTextModalConfirmacio(msgConfirmacioCanvi);
	               bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirmacioCanvi')).show();

	               $('#modalConfirmacioCanvi').on('click', '#confirmar-canvi', function() {
	                  mostrarModalLoading();
	                  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirmacioCanvi')).hide();
	                  var req = $.ajax({
	                     url: path + "alumnes/realitzarCanviCurs_CanviCurs.php",
	                     method: "GET",
	                     data: {
	                        idinsc : id,
	                        any : any,
	                        mes : mes,
	                        curs : curs,
	                        numero : numeroCanvi,
	                        apagar : apagar,
	                        pagat : pagat,
	                        despeses : despeses,
	                        obs : obs,
	                        motiu : motiu,
	                        enviarCoreu : enviarCoreu,
	                        tipusDesc : tipusDesc,
	                        validDesc : validDesc
	                     },
	                     dataType: "html"
	                  });

	                  req.done(function( res ) {
	                     amagarLoadingModal();
	                     if (!res.toLowerCase().includes("error")) {
	                        afegirHeaderModalSuccess("");
	                        mostrarModalSuccess();
	                        //torno a carregar el resultat de la cerca
	                        afegirHeaderModalSuccess("Genial!");
	                        afegirTextModalSuccess("El canvi s'ha realitzat correctaments!");
	                        mostrarModalSuccess();

	                        $('#modalSuccess').on('click', '.btn-success', function() {
	                           amagarModalSuccess();
	                           $('#cercar-alumne').click();
	                        });
	                        $('#modalSuccess').on('click', '.close', function() {
	                           amagarModalSuccess();
	                           $('#cercar-alumne').click();
	                        });
	                     }
	                     else {
	                        afegirHeaderModalError("Alerta!");
	                        afegirTextModalError("Hi ha hagut un error al realitzar el canvi");
	                        mostrarModalError();

	                        $('#modalErrors').on('click', '.btn-danger', function() {
	                           amagarModalError();
	                           reloadUrl();
	                        });
	                        $('#modalErrors').on('click', '.close', function() {
	                           amagarModalError();
	                           reloadUrl();
	                        });
	                     }
	                  });

	                  req.fail(function( jqXHR, textStatus, errorThrown ) {
	                     rerrorFunction( jqXHR, textStatus, errorThrown,
	                        "Hi ha hagut algun error a l'hora de consultar les dades: " );
	                  });
	               });
	               $('#modalConfirmacioCanvi').on('click', '#torna-canvi', function() {
	                  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConfirmacioCanvi')).hide();
	                  bootstrap.Modal.getOrCreateInstance(document.getElementById('modalCanviCurs')).show();
	               });

	            }
	         }
	         else {
	            if (any == '') {
	               missatgeError1 += "<span>Cal escollir un any</span>";
	               $('#dades-canvi #dades-canvi-any').addClass('error');
	            }
	            if (mes == '' || mes.includes("Triar")) {
	               missatgeError1 += "<span>Cal escollir un mes</span>";
	               $('#dades-canvi #dades-canvi-mes').addClass('error');
	            }
	            if (curs == '') {
	               missatgeError1 += "<span>Cal escollir un curs</span>";
	               $('#dades-canvi #dades-canvi-curs').addClass('error');
	            }
	            if (numeroCanvi == -1) {
	               missatgeError1 += "<span>Cal escollir un número de canvi</span>";
	               $('#dades-canvi #dades-canvi-numero').addClass('error');
	            }
	            if (motiu == '') {
	               missatgeError2 += "<span>Cal indicar un motiu de canvi</span>";
	               $('#dades-nou-registre #motiu-canvi').addClass('error');
	            }
	         }

	         if (missatgeError1 != '') {
	            var htmlMsgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
	            htmlMsgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
	            htmlMsgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
	            htmlMsgError += "<i class='material-icons'>close</i></button>";
	            htmlMsgError += missatgeError1 + "</div>";

	            $('#dades-nou-registre').append(htmlMsgError);
	         }
	         if (missatgeError2 != '') {
	            var htmlMsgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
	            htmlMsgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
	            htmlMsgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
	            htmlMsgError += "<i class='material-icons'>close</i></button>";
	            htmlMsgError += missatgeError2 + "</div>";

	            $('#dades-nou-registre').append(htmlMsgError);
	         }
	      });
	   }
	   else {
	      afegirHeaderModalError("Alerta!");
	      afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la finestra per realitzar el canvi de curs");
	      amagarLoadingModal();
	      mostrarModalError();

	      $('#modalErrors').on('click', '.btn-danger', function() {
	         amagarModalError();
	         reloadUrl();
	      });
	      $('#modalErrors').on('click', '.close', function() {
	         amagarModalError();
	         reloadUrl();
	      });
	   }
	});

	req.fail(function( jqXHR, textStatus, errorThrown ) {
	   rerrorFunction( jqXHR, textStatus, errorThrown,
	      "Hi ha hagut algun error a l'hora de consultar les dades: " );
	});

	/* Buscar els anys disponibles */
	function buscarAnys_modalCanviCurs() {
		var curs = $('#dades-canvi-curs .element-selected').html().trim();
		var req = $.ajax({
		   url: path + "alumnes/buscarAnysDisponibles_modalCanviCurs.php",
		   method: "GET",
		   data: {
		      curs : curs
		   },
		   dataType: "html"
		});

		req.done(function( res ) {
		   $('#dades-canvi-any .select-list').html(res);
		});

		req.fail(function( jqXHR, textStatus, errorThrown ) {
		   rerrorFunction( jqXHR, textStatus, errorThrown,
		      "Hi ha hagut algun error a l'hora de consultar les dades: " );
		});
	}

	/* Buscar els mesos disponibles */
	function buscarMesos_modalCanviCurs() {
		var any = $('#dades-canvi-any .element-selected').html().trim();
		var curs = $('#dades-canvi-curs .element-selected').html().trim();
		var req = $.ajax({
		   url: path + "alumnes/buscarMesosDisponibles_modalCanviCurs.php",
		   method: "GET",
		   data: {
		      any : any,
		      curs : curs
		   },
		   dataType: "html"
		});

		req.done(function( res ) {
		   $('#dades-canvi-mes .select-list').html(res);
		});

		req.fail(function( jqXHR, textStatus, errorThrown ) {
		   rerrorFunction( jqXHR, textStatus, errorThrown,
		      "Hi ha hagut algun error a l'hora de consultar les dades: " );
		});
	}

	/* Buscar els cursos disponibles */
	function buscarCursos_modalCanviCurs() {
		var any = $('#dades-canvi-any .element-selected').html().trim();
		var mes = $('#dades-canvi-mes .element-selected').html().trim();
		if (!mes.toLowerCase().includes("triar")) {
			var req = $.ajax({
			   url: path + "alumnes/buscarCursosDisponibles_modalCanviCurs.php",
			   method: "GET",
			   data: {
			      any : any,
			      mes : mes
			   },
			   dataType: "html"
			});

			req.done(function( res ) {
			      $('#dades-canvi-curs .select-list').html(res);
			});

			req.fail(function( jqXHR, textStatus, errorThrown ) {
			   rerrorFunction( jqXHR, textStatus, errorThrown,
			      "Hi ha hagut algun error a l'hora de consultar les dades: " );
			});
		}
	}

	/* Buscar el preu que ha de pagar */
	function buscarPreuAPagar() {
		var anyC = $('#dades-canvi-any .element-selected').html().trim();
		var mesC = $('#dades-canvi-mes .element-selected').html().trim();
		var cursC = $('#dades-canvi-curs .element-selected').html().trim();
		var apagar = $('#apagar-nou-registre').val().trim();
		var id = $('#id-canvi-curs-actual').html().trim();
		var validDesc = $('#validDesc-registre').html().trim();
		var tipusDesc = $('#tipusDesc-registre').html().trim();
		var req = $.ajax({
		   url: path + "alumnes/buscarPreuAPagar_modalCanviCurs.php",
		   method: "GET",
		   data: {
		      id : id,
		      any : anyC,
		      mes : mesC,
		      curs : cursC,
		      apagar : apagar,
		      tipusDesc : tipusDesc,
		      validDesc : validDesc
		   },
		   dataType: "html"
		});

		req.done(function( res ) {
		      amagarLoadingModal();
		       if ( res.toLowerCase().includes("error")  || res.toLowerCase().includes("404") ) {
		         afegirHeaderModalError(res);
		         mostrarModalError();
		         $('#modalErrors').on('click', '.btn-danger', function() {
		            amagarModalError();
		            reloadUrl();
		         });
		         $('#modalErrors').on('click', '.close', function() {
		            amagarModalError();
		            reloadUrl();
		         });
		      }
		      else {
		      if (res == '') apagar = 0;
		      else apagar = parseFloat(res);
		      $('#apagar-nou-registre').val(apagar);
		      realitzaCanvisPreus__modalCanviCurs();
		      }
		});

		req.fail(function( jqXHR, textStatus, errorThrown ) {
		   rerrorFunction( jqXHR, textStatus, errorThrown,
		      "Hi ha hagut algun error a l'hora de consultar les dades: " );
		});
	}

	/* Caclular despeses de gestió */
	function calcularDespesGestio(numero) {
		var any = $('#any-canvi-curs-actual').html().trim();
		var mes = $('#mes-canvi-curs-actual').html().trim();
		var curs = $('#curs-canvi-curs-actual').html().trim();
		var id = $('#id-canvi-curs-actual').html().trim();
		var apagar = $('#apagar-nou-registre').val();
		var pagat = $('#pagat-nou-registre').val();
		var faltapagar = parseFloat(apagar) - parseFloat(pagat);
		if (numero == 4) {
			var req = $.ajax({
			   url: path + "alumnes/calcularDespesesGestio_modalCanviCurs.php",
			   method: "GET",
			   data: {
			      any : any,
			      mes : mes,
			      curs : curs
			   },
			   dataType: "html"
			});

			req.done(function( despeses ) {
			   $('#despeses-registre').val(despeses);
			   faltapagar += parseFloat(despeses);
			   $('#pendent-nou-registre').val(faltapagar);
			});

			req.fail(function( jqXHR, textStatus, errorThrown ) {
			   rerrorFunction( jqXHR, textStatus, errorThrown,
			      "Hi ha hagut algun error a l'hora de calcular les despeses de gestió': " );
			});
		} else {
			$('#despeses-registre').val("0");
			$('#pendent-nou-registre').val(faltapagar);
		}
	}
}

/* Afegeix en el modal de confirmació el text msgConfirmacioCanvi  */
function afegirTextModalConfirmacio(msgConfirmacioCanvi) {
	$('#modalConfirmacioCanvi .modal-body').html(msgConfirmacioCanvi);
}

/* Es marca en vermell i el titols del preu a pagar nou i de l'antic per
indicar que són diferents preus */
function realitzaCanvisPreus__modalCanviCurs() {
	var apagar = $('#apagar-nou-registre').val();
	if (parseFloat(apagar) != parseFloat($('#apagar-canvi-curs-actual').html())) {
		$('#apagar-nou-registre').prev().addClass('text-danger font-weight-bold');
		$('#apagar-canvi-curs-actual').prev().addClass('text-danger font-weight-bold');
	} else {
		$('#apagar-nou-registre').prev().removeClass('text-danger font-weight-bold');
		$('#apagar-canvi-curs-actual').prev().removeClass('text-danger font-weight-bold');
	}
	var pagat = $('#pagat-nou-registre').val();
	var despeses = $('#despeses-registre').val();
	var faltapagar = parseFloat(apagar) - parseFloat(pagat) + parseFloat(despeses);
	$('#pendent-nou-registre').val(faltapagar);
}

/*Si es clica el botó d'.editar-apartat' a l'apartat idApartat,
s'habilita l'edició en els inputs de l'apartat,
s'amaga el botó d'edita i s'afageix el botó de guardar resultat i cancel·lar */
function editarApartat(idApartat) {
	// $(idApartat+' .apartat .form-control').removeClass('no-edit');
	// $(idApartat+' .apartat .form-control').addClass('edit');
	$(idApartat + " .apartat .form-group .form-control").each(function() {
		var id = $(this).attr('id');
		var text = $(this).html();
		var parent = $(this).parent();
		$(this).remove();
		parent.append("<input type='text' class='form-control edit' id='" + id + "' name='" + id + "' value=\"" + text + "\">");
	});
	$(idApartat + ' .editar-apartat').html("save");
	$(idApartat + ' .editar-apartat').addClass("save-result");
	$(idApartat + ' .editar-apartat').removeClass("editar-apartat");
	$(idApartat + ' .titol-apartat').append("<i class='material-icons ml-2 cancelar-apartat'>cancel</i>");
}

/*Si es clica el botó d'.cancelar-apartat' a l'apartat idApartat,
es deshabilita l'edició en els inputs de l'apartat,
s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
function cancelEditarApartat(idApartat) {
	// $(idApartat+' .apartat .form-control').removeClass('edit');
	// $(idApartat+' .apartat .form-control').addClass('no-edit');
	$(idApartat + " .apartat .form-group .form-control").each(function() {
		var id = $(this).attr('id');
		var text = $(this).val();
		var parent = $(this).parent();
		$(this).remove();
		parent.append("<div class='form-control no-edit' id='" + id + "'>" + text + "</div>");
	});
	$(idApartat + ' .save-result').html("edit");
	$(idApartat + ' .save-result').addClass("editar-apartat");
	$(idApartat + ' .save-result').removeClass("save-result");
	$(idApartat + ' .cancelar-apartat').remove();
}


//Mostra el modal per donar de baixa a la inscripció amb id id
function mostrarModalDonarBaixa(id) {
	var upd = $.ajax({
	   url: path + "alumnes/mostraModalDonarBaixa.php",
	   method: "GET",
	   data: {
	      idInsc : id
	   },
	   dataType: "html"
	});

	upd.done(function( res ) {
	       if ( res.toLowerCase().includes("error")  || res.toLowerCase().includes("404") ) {
	         afegirHeaderModalError("Alerta!");
	         afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la finestra per donar de baixa");
	         amagarLoadingModal();
	         mostrarModalError();

	         $('#modalErrors').on('click', '.btn-danger', function() {
	            amagarModalError();
	            reloadUrl();
	         });
	         $('#modalErrors').on('click', '.close', function() {
	            amagarModalError();
	            reloadUrl();
	         });
	      }
	      else {
	         $("#modalDonarBaixa .modal-body").html(res);
	         amagarLoadingModal();
	         bootstrap.Modal.getOrCreateInstance(document.getElementById('modalDonarBaixa')).show();

	         $('#modalDonarBaixa').on('click', '.btn-success', function() {
	            bootstrap.Modal.getInstance(document.getElementById('modalDonarBaixa')).hide();
	         });
	         $('#modalDonarBaixa').on('click', '.close', function() {
	            bootstrap.Modal.getInstance(document.getElementById('modalDonarBaixa')).hide();
	         });

				$('.confirma-baixa').off();

				$('.confirma-baixa').on('click', function() {
					var id2 = $('#dades-baixa-inscripcio #id-baixa').html().trim();
					var motiu = $('#dades-baixa-motiu #motiu-baixa').val().trim();
					var enviarCoreu = $('#dades-baixa-motiu #no-enviar-correu-alumne-baixa').prop('checked');

					var missatgeError = '';

					if (id2 != '' && motiu != '') {
						amagarModalDonarBaixa();
						mostrarModalLoading();

						var upd2 = $.ajax({
						   url: path + "alumnes/confirmacioBaixa_DonarBaixa.php",
						   method: "GET",
						   data: {
						      idinsc : id2,
						      motiu : motiu,
						      enviarCoreu : enviarCoreu
						   },
						   dataType: "html"
						});

						upd2.done(function( res ) {
						      amagarLoadingModal();
						       if ( res.toLowerCase().includes("error")  || res.toLowerCase().includes("404") ) {
						         afegirHeaderModalError("Hi ha hagut un error al realitzar la baixa!");
						         afegirTextModalError("");
						         amagarLoadingModal();
						         mostrarModalError();

						         $('#modalErrors').on('click', '.btn-danger', function() {
						            amagarModalError();
						            reloadUrl();
						         });
						         $('#modalErrors').on('click', '.close', function() {
						            amagarModalError();
						            reloadUrl();
						         });
						      }
						      else {
						         afegirHeaderModalSuccess("La baixa s'ha efectuat correctament");
						         afegirTextModalSuccess('');
						         amagarLoadingModal();
						         mostrarModalSuccess();

						         $('#modalSuccess').on('click', '.btn-success', function() {
						            amagarModalSuccess();
						            $('#cercar-alumne').click();
						         });
						         $('#modalSuccess').on('click', '.close', function() {
						            amagarModalSuccess();
						            $('#cercar-alumne').click();
						         });
						      }
						});

						upd2.fail(function( jqXHR, textStatus, errorThrown ) {
						   rerrorFunction( jqXHR, textStatus, errorThrown,
						      "Hi ha hagut algun error a l'hora d'actualitzar reclamacions': " );
						});
					}
					else {
						if (id2 == '') {
							missatgeError += "<span>Hi ha hagut un error al carregar la finestra</span>";
							$('#dades-baixa-inscripcio #id-baixa').addClass('error');
						}
						if (motiu == '') {
							missatgeError += "<span>Cal indicar un motiu de baixa</span>";
							$('#dades-baixa-motiu #motiu-baixa').addClass('error');
						}

						if (missatgeError != '') {
							var htmlMsgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
							htmlMsgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
							htmlMsgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
							htmlMsgError += "<i class='material-icons'>close</i></button>";
							htmlMsgError += missatgeError + "</div>";

							$('#dades-baixa-motiu').append(htmlMsgError);
						}
					}
//
				});

	      }
	});

	upd.fail(function( jqXHR, textStatus, errorThrown ) {
	   rerrorFunction( jqXHR, textStatus, errorThrown,
	      "Hi ha hagut algun error a l'hora d'actualitzar reclamacions': " );
	});
}

//Mostra el modal de consulta la factura de la inscripció amb id id
function mostrarModalConsultaFactura(id) {
	var upd = $.ajax({
	   url: path + "alumnes/mostraModalConsultaFactura.php",
	   method: "GET",
	   data: {
	      idInsc : id
	   },
	   dataType: "html"
	});

	upd.done(function( res ) {
	      paginaFactura = 1;
	      numPaginesFactura = 1;
	       if ( res.toLowerCase().includes("error") || res.toLowerCase().includes("404") ) {
	         afegirHeaderModalError("Alerta!");
	         afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la finestra per consultar la factura");
	         amagarLoadingModal();
	         mostrarModalError();

	         $('#modalErrors').on('click', '.btn-danger', function() {
	            amagarModalError();
	         });
	         $('#modalErrors').on('click', '.close', function() {
	            amagarModalError();
	         });
	      }
	      else if ( res == '' ) {
	        afegirHeaderModalError("Alerta!");
	        afegirTextModalError("No hi ha cap factura relacionada!");
	        amagarLoadingModal();
	        mostrarModalError();

	        $('#modalErrors').on('click', '.btn-danger', function() {
	           amagarModalError();
	        });
	        $('#modalErrors').on('click', '.close', function() {
	           amagarModalError();
	        });
	     }
	      else {
	         $("#modalConsultaFactura .modal-body").html(res);
	         amagarLoadingModal();
	         bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsultaFactura')).show();

	         $('.download-factura').off();
	         $('.fletxa-left').off();
	         $('.fletxa-right').off();

	         var nclick = 0;

	         $('.download-factura').on('click', function() {
	            var id2 = $('#modalConsultaFactura #factura-relacionada-fact').html().trim();
	            var upd2 = $.ajax({
	               url: path + "alumnes/descarregaFactura.php",
	               method: "GET",
	               data: {
	                  id : id2
	               },
	               dataType: "html"
	            });
	            upd2.done(function( res ) {
	               amagarModalConsultaFactura();
	               amagarLoadingModal();
	               if (!resD.toLowerCase().includes("error")) {
	                  var link = document.createElement('a');
	                  link.setAttribute("id", "download-fact-" + nclick);
	                  link.href = path + "alumnes/" + resD;
	                  link.download = resD + '.pdf';
	                  link.click();
	                  $.ajax({
	                     url: path + "alumnes/eliminarArxiu.php?filename=" + resD,
	                     cache: false,
	                     type: "GET",
	                     success: function(data) {
	                        afegirHeaderModalSuccess("S'ha generat la factura correctament");
	                        afegirTextModalSuccess('');
	                        amagarLoadingModal();
	                        mostrarModalSuccess();
	                        nclick++;

	                          $('#modalSuccess').on('click', '.btn-danger', function() {
	                             amagarModalSuccess();
	                          });
	                          $('#modalSuccess').on('click', '.close', function() {
	                             amagarModalSuccess();
	                          });
	                     }
	                  });
	               } else {
	                    afegirHeaderModalError("Hi ha hagut un error al generar la descarrega!");
	                    amagarLoadingModal();
	                    mostrarModalError();

	                    $('#modalErrors').on('click', '.btn-danger', function() {
	                       amagarModalError();
	                       reloadUrl();
	                    });
	                    $('#modalErrors').on('click', '.close', function() {
	                       amagarModalError();
	                       reloadUrl();
	                    });
	               }
	            });

	            upd2.fail(function( jqXHR, textStatus, errorThrown ) {
	               rerrorFunction( jqXHR, textStatus, errorThrown,
	                  "Hi ha hagut algun error a l'hora de guardar la informació': " );
	            });
	         });


	         if ($('#factura-num-pagines')) {
	            numPaginesFactura = $('#factura-num-pagines').html();
	         }

	         $('.fletxa-left').on('click', function() {
	            console.log('fletxa-left' + paginaFactura);
	            if (paginaFactura > 1) {
	               $('#pagina-factura' + paginaFactura).fadeOut('fast', function() {
	                  paginaFactura--;
	                  $('#pagina-factura' + paginaFactura).fadeIn('fast', function() {
	                     $('#factura-pagina-actual').html(paginaFactura);
	                  });
	               });
	            }

	         });
	         $('.fletxa-right').on('click', function() {
	            console.log('fletxa-right ' + paginaFactura + " " + numPaginesFactura);
	            if (paginaFactura < numPaginesFactura) {
	               $('#pagina-factura' + paginaFactura).fadeOut('fast', function() {
	                  paginaFactura++;
	                  $('#pagina-factura' + paginaFactura).fadeIn('fast', function() {
	                     $('#factura-pagina-actual').html(paginaFactura);
	                  });
	               });
	            }

	         });

	         $("#modalConsultaFactura .modal-body").html(res);
	         bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsultaFactura')).show();

	         $('#modalConsultaFactura').on('click', '.btn-success', function() {
	            bootstrap.Modal.getInstance(document.getElementById('modalConsultaFactura')).hide();
	         });
	         $('#modalConsultaFactura').on('click', '.close', function() {
	            bootstrap.Modal.getInstance(document.getElementById('modalConsultaFactura')).hide();
	         });
	      }
	});

	upd.fail(function( jqXHR, textStatus, errorThrown ) {
	   rerrorFunction( jqXHR, textStatus, errorThrown,
	      "Hi ha hagut algun error a l'hora de guardar la informació': " );
	});
}

//Mostra el modal de consulta el certificat de la inscripció amb id id
function mostrarModalConsultaCertificat(id, tipus) {
	$('.modal-info').off();
	$('#modalConsultaCertificat').off();

	var request = $.ajax({
		url: path + "alumnes/mostraModalConsultaCertificat.php",
		method: "GET",
		data: {
			idInsc : id,
			tipus: tipus
		},
		dataType: "html"
	});

	request.done(function( res ) {
		if (!res.toLowerCase().includes("error")) {
			$("#modalConsultaCertificat .modal-body").html(res);
			amagarLoadingModal();
			bootstrap.Modal.getOrCreateInstance(document.getElementById('modalConsultaCertificat')).show();

			$('.boto-certificat').off();
			$('.download-certificat').off();

			$('#modalConsultaCertificat').on('click', '.boto-certificat', function() {
				var id = $(this).attr('id');
				$('#modalConsultaCertificat .boto-certificat.marcat').removeClass('marcat');
				$('#' + id).addClass('marcat');

				$('#modalConsultaCertificat #print-certificat').addClass('opacity-02');
				$('#modalConsultaCertificat .loading-wrapper').removeClass('hide');

				var tipus = id.substr(5, id.length);
				var idInsc = parseInt($('#id-insc-certificat').html());

				var requestPrevCertificat = $.ajax({
					url: path + "alumnes/mostrarCertificat.php",
					method: "GET",
					data: {
						idInsc : idInsc,
						tipus : tipus,
						download : "false"
					},
					dataType: "html"
				});

				requestPrevCertificat.done(function( msgPrevCertificat ) {
					if ( !msgPrevCertificat.toLowerCase().includes("error") ) {
						$('#print-certificat').html(msgPrevCertificat);
						$('#modalConsultaCertificat .loading-wrapper').addClass('hide');
						$('#modalConsultaCertificat #print-certificat').removeClass('opacity-02');
					}
					else {
						afegirHeaderModalError("Alerta");
						afegirTextModalError("Hi ha hagut algun error al previsualitzar el certificat");
						amagarLoadingModal();
						mostrarModalError();
						reloadUrl();

			         $('#modalErrors').on('click', '.btn-danger', function() {
			            amagarModalError();
			         });
			         $('#modalErrors').on('click', '.close', function() {
			            amagarModalError();
			         });
					}
				});

				requestPrevCertificat.fail(function( jqXHRPrevCertificat, textStatusPrevCertificat, errorThrownPrevCertificat ) {
					errorFunction( jqXHRPrevCertificat, textStatusPrevCertificat, errorThrownPrevCertificat,
						"Hi ha hagut algun error al previsualitzar el certificat: " );
				});
			});

			var nclick = 0;

			$('#modalConsultaCertificat').on('click', '.download-certificat', function() {
				if ( tePermisEdicio ) {
					var id = $('#modalConsultaCertificat .boto-certificat.marcat').attr('id');
					var tipus = id.substr(5, id.length);
					var idInsc = parseInt($('#id-insc-certificat').html());
					var download = "true";

					var requestDownCertificat = $.ajax({
						url: path + "alumnes/mostrarCertificat.php",
						method: "GET",
						data: {
							idInsc : idInsc,
							tipus : tipus,
							download : download,
						},
						dataType: "html"
					});

					requestDownCertificat.done(function( resC ) {
						amagarModalConsultaCertificat();
						amagarLoadingModal();

						if ( !resC.toLowerCase().includes("error") ) {
							var link = document.createElement('a');
							link.setAttribute("id", "download-cert-" + nclick);
							link.href = path + "alumnes/" + resC;
							link.download = resC + '.pdf';
							link.click();

							var requestRemoveCertificat = $.ajax({
								url: path + "alumnes/eliminarArxiu.php",
								method: "GET",
								data: {
									filename : resC
								},
								dataType: "html"
							});
							requestRemoveCertificat.done(function( msg ) {
								afegirHeaderModalSuccess("Generat!");
								afegirTextModalSuccess("S'ha generat el certificat correctament");
								mostrarModalSuccess();
								nclick++;
							});
						}
						else {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut algun error al generar el certificat");
							mostrarModalError();
							reloadUrl();
						}

					});

					requestDownCertificat.fail(function( jqXHRDownCertificat, textStatusDownCertificat, errorThrownDownCertificat ) {
						errorFunction( jqXHRDownCertificat, textStatusDownCertificat, errorThrownDownCertificat,
							"Hi ha hagut algun error al generar la factura: " );
					});

				}
				else {
					mostrarModalNoTensPermisos();
				}

			});
		}
		else if (res == '') {
			amagarLoadingModal();
			afegirHeaderModalError("Alerta");
			afegirTextModalError("No hi ha cap certificat relacionat!");
			mostrarModalError();
		}
		else {
			amagarLoadingModal();
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la finestra per consultar el certificat");
			mostrarModalError();
			reloadUrl();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error al mostrar la finestra per consultar del certificat: " );
	});
}

/* Comprova si valor està buit. Si està buit, retorna true, altrament retorna false */
function campBuit(valor) {
	var buit = false;
	if (valor == '') buit = true;
	return buit;
}

/* Si dni es buit o té 9 caracters, comprova si valor és un numero de 9 digits i
no conté caracters no permesos en un telefon.
Altrament, comprova si valor és un numero 9 a 13 digits i no conté caracters no
permesos en un telefon.
Si compleix la condició, retorna buit, altrament retorna l'error.
Si valor està buit, retorna buit */
function validTel(valor, dni) {
	var valid = "";
	if (valor.length != 0) {
		var stripped = valor.replace(/[\(\)\.\-\ ]/g, '');

		if ( (( dni=='' || dni.length==9) && !(stripped.length == 9)) ||
			  ( dni!='' && dni.length!=9 && !(stripped.length >= 9 && stripped.length <= 13)) ) {
			valid = "El TELÈFON té una llargada incorrecta";
		} else if (isNaN(stripped)) {
			valid = "El TELÈFON conté caràcters no permesos";
		}
	}
	return valid;
}

/* Comprova si valor és 0, 1, X, C o D. Si compleix la condició, retorna
true, altrament retorna false. Si valor està buit, retorna true */
function validInsc(valor) {
	var valid = false;
	var valorUpper = valor.toUpperCase();
	if (valorUpper == '0' || valorUpper == '1' || valorUpper == 'C' ||
		 valorUpper == 'D' || valorUpper == 'X') {
		valid = true;
	}
	return valid;
}

function amagarModalConsultaInformacio() {
	bootstrap.Modal.getInstance(document.getElementById('modalConsultaInformacio')).hide();
}

$('#modalConsultaInformacio').on('click', function() {
	amagarModalConsultaInformacio()
});

function amagarModalCanviCurs() {
	bootstrap.Modal.getInstance(document.getElementById('modalCanviCurs')).hide();
}
$('#modalCanviCurs').on('click', function() {
	amagarModalCanviCurs()
});

function amagarModalDonarBaixa() {
	bootstrap.Modal.getInstance(document.getElementById('modalDonarBaixa')).hide();
}
$('#modalDonarBaixa').on('click', function() {
	amagarModalDonarBaixa()
});

function amagarModalConsultaFactura() {
	bootstrap.Modal.getInstance(document.getElementById('modalConsultaFactura')).hide();
}
$('#modalConsultaFactura').on('click', function() {
	amagarModalConsultaFactura()
});

function amagarModalConsultaCertificat() {
	bootstrap.Modal.getInstance(document.getElementById('modalConsultaCertificat')).hide();
}
$('#modalConsultaCertificat').on('click', function() {
	amagarModalConsultaCertificat()
});

function mostrarModalObservacions() {
	bootstrap.Modal.getOrCreateInstance(document.getElementById('modalObservacions')).show();
}

function amagarModalObservacions() {
	bootstrap.Modal.getInstance(document.getElementById('modalObservacions')).hide();
}
$('#modalObservacions').on('click', function() {
	amagarModalObservacions()
});
