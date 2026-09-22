let urlPagina = window.location.pathname.split('?')[0];
let veureUnaFactura = "";
let path = "https://intranet.prisma.cat/ajax/";

let hashUrl = null;
let tipusCerca = null;

if ( window.location.hash.split('#')[1]) {
	tipusCerca = window.location.hash.split('#')[1].split('/')[1];
	hashUrl = window.location.hash.split('#')[1].split('/')[2];
}

/* Cada vegada que es faci una crida d'un ajax, s'executarà la funció mostrarModalLoading().
Cada vegada que finalitza la crida d'un ajax, s'executarà la funció amagarLoadingModal(). */
// $(document).bind("ajaxSend", function(){
// 	mostrarModalLoading();
// }).bind("ajaxComplete", function(){
// 	amagarLoadingModal();
// });

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

requestMain.done(function( message ) {

	$('.mainpanel').html(message);

	//quan es clica a qualsevol lloc fora del select, amago el desplegable
	$(window).click(function() {
		//amago el desplegable
		$('.select .select-list').hide();
		//retorno el triangle com esta per defecte
		var triangle = $('.select').find("i");
		triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
	});

	//quan estas focus en el camp, elimino el marcatge de l'input
	$('#content-page').on('focus', '.form-control', function() {
		$(this).parent().removeClass('element-cercat-marcat');
	});

	//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
	$('#content-page').on('blur', '.form-control', function() {
		if ($(this).val().trim() == '')
			$(this).removeClass('element-cercat-marcat');
		else
			$(this).addClass('element-cercat-marcat');
	});

	$("#content-page .select").click(function(e) {
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
	$("#content-page .select").on("click", "li", function(e) {
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
		if ( $(this).parent().parent().attr('id') == 'entitat-dispo')
			entitatMarcada = texto;
	});

	let dni = "",
		email = "",
		factRel = "",
		factNum = "",
		elementsCercats = "";

	/* Si premo la tecla ENTER, es reprodueix l'event de clicar del cercar-factura*/
	$("#mostrar-factura").keyup(function(evObject) {
		if (evObject.keyCode == 13) $('#cercar-factura').click();
	});

	/* Busco l'alumne o els diferents registres que poden coincidir amb la cerca */
	$('#cercar-factura').on('click', function() {
		mostrarModalLoading()
		dni = $('#dni').val().trim();
		email = $('#email').val().trim();
		factRel = $('#fact-rel').val().trim();
		factNum = $('#fact-num').val().trim();
		cercaPer = "RESULTATS DE LA CERCA PER ";
		elementsCercats = "";

		if ( dni == '' && email == '' && factRel == '' && factNum == '' ) {
			afegirHeaderModalError("Oops...!");
			afegirTextModalError("Omple un camp per poder fer la cerca");
			mostrarModalError();
		}
		else {
			if (dni != '')
				elementsCercats += "DNI «" + dni + "»";
			if (email != '') {
				if (elementsCercats != '')
					elementsCercats += " i ";
				elementsCercats += "E-MAIL «" + email + "»";
			}
			if (factRel != '') {
				if (elementsCercats != '')
					elementsCercats += " i ";
				elementsCercats += "FACTURA RELACIONADA «" + factRel + "»";
			}
			if (factNum != '') {
				if (elementsCercats != '')
					elementsCercats += " i ";
				elementsCercats += "NÚM. FACTURA «" + factNum + "»";
			}

			cercaPer += elementsCercats;

			/* Cerca els usuaris amb les factures que el dni = dni o el email = email
			correspongui amb la inscripció relacionada amb la factura o la factura
			relacionada = factRel o el número de la factura = factNum */
			var request = $.ajax({
				url: path + "alumnes/consultaUsuarisFacturaRelacionada.php",
				method: "GET",
				data: {
					dni : dni ,
					email : email ,
					factRel : factRel ,
					factNum : factNum
				},
				dataType: "html"
			});

			request.done(function( dnies ) {
				let vectDnies = dnies.split('#');
				if ( dnies.toLowerCase().includes("error") ) {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("Hi ha hagut un error a l'hora de fer la consulta d'usuaris");
					mostrarModalError();
					reloadUrl();
				}
				else if ( dnies.includes("No") && dnies.includes("resultats") ) {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("No s'han trobat resultats");
					mostrarModalError();
				}
				else if ( dnies.split('|') > 2000 ) {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("El volum de dades cercat és molt gran. Si us plau, afegeix algun filtre més per acotar el volum de dades");
					mostrarModalError();
				}
				else {
					let vectDnies2 = vectDnies[1].split('|');
					if ( vectDnies2.length == 1 ) {
						//Hi ha un sol usuari amb la cerca realitzada
						cercarUSuari(vectDnies2[0]);
					}
					else {
						//Hi ha més d'un sol usuari amb la cerca realitzada
						//Mostra la taula amb els usuaris trobats a partir de la cerca realitzada
						mostraLlistatUsuaris(vectDnies[1], 'cog', 'asc')
					}
				}
			});

			request.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error a l'hora de fer la consulta d'usuaris: " );
			});
		}
	});

	if ( hashUrl != '' && hashUrl != null ) {
		var inputCerca = 'dni';

		if ( tipusCerca == 'dni' ) {
			inputCerca = 'dni';
		}
		if ( tipusCerca == 'factRel' ) {
			inputCerca = 'fact-rel';
		}
		if ( tipusCerca == 'factNum' ) {
			inputCerca = 'fact-num';
		}
		$('#mostrar-factura #'+inputCerca).val(hashUrl);
		$('#mostrar-factura #'+inputCerca).prev().addClass('active');

		$('#cercar-factura').click();
	}


});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

/* Mostra la taula amb els usuaris trobats a partir de la cerca realitzada
ordenada per cognoms si orderby es cog, per nom si ordery es nom i per dni si
orderby es dni i en ascendentment si asc és 1 i en descendentment si és 0. */
function mostraLlistatUsuaris(dnies, orderby, asc) {

	$('#resultats-cerca').off();
	$('.table-order').off();

	var request = $.ajax({
		url: path + "alumnes/mostrarTaulaUsuaris.php",
		method: "GET",
		data: {
			dnies : dnies,
			orderBy : orderby,
			asc : asc
		},
		dataType: "html"
	});

	//Mostra la taula amb els usuaris trobats a partir de la cerca realitzada
	request.done(function( res ) {
		$('#resultats-cerca').html(res);
		$('#resultats-cerca').show();
		amagarLoadingModal();

		//Quan sel·lecciono un registre de la taula, mostro la informació de l'usuari
		$('#resultats-cerca').on('click', '.seleccionar', function() {
			mostrarModalLoading();
			cercarUSuari($(this).attr('id'));
		});
		/* Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
		s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.*/
		$('.table-order').on('click', '.sorting', function() {
			mostrarModalLoading();
			var id = $(this).attr('id').substr(3, $(this).attr('id').length);
			if ($(this).hasClass('asc'))
				mostraLlistatUsuaris(dnies, id, 0);
			else
				mostraLlistatUsuaris(dnies, id, 1);
		});
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar la taula amb els usuaris: " );
	});
}

/* Mostrar informació de l' usuari a partir del dni de l'usuari cercat */
function cercarUSuari(dniUser) {
	var request = $.ajax({
		url: path + "alumnes/mostrarTotesFacturesUsuari_Factures.php",
		method: "GET",
		data: {
			dni : dniUser,
			cercaPer : cercaPer
		},
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			$('#resultats-cerca').html(res);
			$('#resultats-cerca').show();
			amagarLoadingModal();
		}
		else {
			amagarLoadingModal();
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Aquest alumne no té DNI!");
			mostrarModalError();
			reloadUrl();
		}

		//es desactiva qualsevol event que depengui de l'apartat
		$('.regCursos').off();

		/*Si es clica el botó d'.cns-informacio' als apartats '.regCursos',
		es mostra el modal amb la informació de la factura amb una id
		de factura igual a l'id del botó */
		$('.regCursos').on('click', '.cns-informacio', function() {
			var id = $(this).attr('id').split('-')[1];
			mostrarModalConsultaInformacio(id);
		});
		/*Si es clica el botó d'.anula-factura' als apartats '.regCursos',
		es mostra el modal amb la informació de la factura amb una id
		de factura igual a l'id del botó */
		$('.regCursos').on('click', '.anula-factura', function() {
			if ( tePermisEdicio ) {
				var id = $(this).attr('id').split('-')[1];
				mostrarModalAnulaFactura(id);
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});
		/*Si es clica el botó d'.prev-factura' als apartats '.regCursos',
		es mostra el modal amb la informació de la factura amb una id
		de factura igual a l'id del botó */
		$('.regCursos').on('click', '.prev-factura', function() {
			var id = $(this).attr('id').split('-')[1];
			mostrarModalPrevisualitzaFactura(id);
		});
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar la informació de la factura d'un usuari: " );
	});
}

function mostrarModalConsultaInformacio( id ) {
	$('.modal-info').off();
	var request = $.ajax({
		url: path + "alumnes/mostraModalConsultaInformacio_Factures.php",
		method: "GET",
		data: { id : id },
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			$("#modalConsultaInformacio .modal-body").html(res);
			$("#modalConsultaInformacio").modal('show');

			/*Si es clica el botó d'.editar-apartat', s'habilita l'edició en els
			inputs de l'apartat, s'amaga el botó d'edita i s'afageix el botó de
			guardar resultat i cancel·lar */
			$('#modalConsultaInformacio #dades-factura').on('click', '.editar-apartat', function() {
				if ( tePermisEdicio ) {
					editarApartat('#modalConsultaInformacio #dades-factura');
				}
				else {
				   mostrarModalNoTensPermisos();
				}
			});
			/*Si es clica el botó d'.cancelar-apartat' a l'apartat #dades-factura',
			es deshabilita l'edició en els inputs de l'apartat, s'amaga el botó de
			guardar resultat i cancelar i s'afageix el botó d'edició */
			$('#modalConsultaInformacio #dades-factura').on('click', '.cancelar-apartat', function() {
				if ( tePermisEdicio ) {
					cancelEditarApartat('#modalConsultaInformacio #dades-factura');
				}
				else {
				   mostrarModalNoTensPermisos();
				}
			});
			/*Si es clica el botó d'.save-result' a l'apartat #dades-factura',
			es guarden els resultats a la BD a, s'amaga el botó de
			guardar resultat i cancelar i s'afageix el botó d'edició */
			$('#modalConsultaInformacio #dades-factura').on('click', '.save-result', function() {
				if ( tePermisEdicio ) {
					let idFact = $('#id-cns-fact').html().trim();
					let facturaFact = $('#factura-cns-fact').html().trim();
					let raoFact = $('#rao-cns-fact').val().trim();
					let cifFact = $('#cif-cns-fact').val().trim();
					let cpFact = $('#codipostal-cns-fact').val().trim();
					let poblacioFact = $('#poblacio-cns-fact').val().trim();
					let adrecaFact = $('#adreca-cns-fact').val().trim();
					let concepte1Fact = $('#concepte1-cns-fact').val().trim();
					let concepte2Fact = $('#concepte2-cns-fact').val().trim();
					let obsFact = $('#obs-cns-fact').val().trim();

					if ( !campBuit(raoFact) && !campBuit(cifFact) && !campBuit(concepte1Fact) ) {
						$('#modalConsultaInformacio #dades-factura .apartat').addClass('opacity-02');
						$('#modalConsultaInformacio #dades-factura .loading-wrapper').removeClass('hide');

						var requestSavePag = $.ajax({
							url: path + "alumnes/guardarDadesFactura_Factures.php",
							global: false,
							method: "GET",
							data: {
								id: idFact,
								factura: facturaFact,
								rao: raoFact,
								cif: cifFact,
								cp: cpFact,
								poblacio: poblacioFact,
								adreca: adrecaFact,
								concepte1: concepte1Fact,
								concepte2: concepte2Fact,
								obs: obsFact
							},
							dataType: "html"
						});

						requestSavePag.done(function(res) {
							if ( !res.includes("Error") && !res.includes("error") ) {
								$('#modalConsultaInformacio #dades-factura .loading-wrapper').addClass('hide');
								var msgOK = "<div class='alert alert-success alert-with-icon w-100 mb-2'>";
								msgOK += "<i class='material-icons' data-notify='icon'>notifications</i>";
								msgOK += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
								msgOK += "<i class='material-icons'>close</i></button>";
								msgOK += "<span>Els canvis s'han guardat correctament</span></div>";
								$('#modalConsultaInformacio #dades-factura .result-success').html(msgOK);
								$('#modalConsultaInformacio #dades-factura .result-success').removeClass('hide');
							}
							else {
								var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
								msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
								msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
								msgError += "<i class='material-icons'>close</i></button>";
								msgError += "<span>Hi ha hagut un error amb el registre</span></div>";
								$('#modalConsultaInformacio #dades-factura .result-success').html(msgError);
								$('#modalConsultaInformacio #dades-factura .result-success').addClass('danger');
								$('#modalConsultaInformacio #dades-factura .result-success').removeClass('hide');
							}
							setTimeout(function() {
								$('#modalConsultaInformacio #dades-factura .result-success').fadeOut('slow', function() {
									$('#modalConsultaInformacio #dades-factura .result-success').addClass('hide');
									$('#modalConsultaInformacio #dades-factura .apartat').removeClass('opacity-02');
									$("#modalConsultaInformacio #dades-factura .apartat .form-group .form-control.editables").each(function() {
										var id = $(this).attr('id');
										var text = $(this).val();
										var parent = $(this).parent();
										$(this).remove();
										parent.append("<div class='form-control no-edit editables' id='" + id + "'>" + text + "</div>");
									});
									$('#modalConsultaInformacio #dades-factura .save-result').html("edit");
									$('#modalConsultaInformacio #dades-factura .save-result').addClass("editar-apartat");
									$('#modalConsultaInformacio #dades-factura .save-result').removeClass("save-result");
									$('#modalConsultaInformacio #dades-factura .cancelar-apartat').remove();
								});
							}, 1500);
						});

						requestSavePag.fail(function(jqXHR, textStatus, errorThrown) {
							$("#modalConsultaInformacio").modal('hide');
							errorFunction(jqXHR, textStatus, errorThrown,
								"Hi ha hagut un error a l'hora de guardar les dades de la factura: ");
						});
					}
					else {
						var missatgeError="";
						if (raoFact == '') {
							missatgeError += "<span>" + missatgeNoPotEstarBuit("RAÓ") + "</span>";
							$('#modalConsultaInformacio #dades-factura #rao-cns-fact').addClass('error');
						}
						if (cifFact == '') {
							missatgeError += "<span>" + missatgeNoPotEstarBuit("CIF") + "</span>";
							$('#modalConsultaInformacio #dades-factura #cif-cns-fact').addClass('error');
						}
						if (concepte1Fact == '') {
							missatgeError += "<span>" + missatgeNoPotEstarBuit("CONCEPTE1") + "</span>";
							$('#modalConsultaInformacio #dades-factura #concepte1-cns-fact').addClass('error');
						}
						if (missatgeError != '') {
							var htmlMsgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
							htmlMsgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
							htmlMsgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
							htmlMsgError += "<i class='material-icons'>close</i></button>";
							htmlMsgError += missatgeError + "</div>";

							$('#modalConsultaInformacio #dades-factura').append(htmlMsgError);
						}
					}
				}
				else {
				   mostrarModalNoTensPermisos();
				}


			});
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la informació de la factura");
			mostrarModalError();
			reloadUrl();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar el modal de consulta la informació de la factura: " );
	});
}

/*Si es clica el botó d'.editar-apartat' a l'apartat idApartat,
s'habilita l'edició en els inputs de l'apartat,
s'amaga el botó d'edita i s'afageix el botó de guardar resultat i cancel·lar */
function editarApartat(idApartat) {
	$(idApartat + " .apartat .form-group .form-control.editables").each(function() {
		var id = $(this).attr('id');
		var text = $(this).html();
		var parent = $(this).parent();
		$(this).remove();
		parent.append("<input type='text' class='form-control edit editables' id='" + id + "' name='" + id + "' value=\"" + text + "\">");
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
	$(idApartat + " .apartat .form-group .form-control.editables").each(function() {
		var id = $(this).attr('id');
		var text = $(this).val();
		var parent = $(this).parent();
		$(this).remove();
		parent.append("<div class='form-control no-edit editables' id='" + id + "'>" + text + "</div>");
	});
	$(idApartat + ' .save-result').html("edit");
	$(idApartat + ' .save-result').addClass("editar-apartat");
	$(idApartat + ' .save-result').removeClass("save-result");
	$(idApartat + ' .cancelar-apartat').remove();
}

/* Comprova si valor està buit. Si està buit, retorna true, altrament retorna false */
function campBuit(valor) {
	var buit = false;
	if (valor == '') buit = true;
	return buit;
}

function mostrarModalAnulaFactura( id ) {
	$('.modal-info').off();
	var request = $.ajax({
		url: path + "alumnes/mostrarModalAnulaFactura_Factures.php",
		method: "GET",
		data: { id : id },
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			$("#modalAnulaFactura .modal-body").html(res);
			$("#modalAnulaFactura").modal('show');

			$('#modalAnulaFactura').on('click', '.confirma-baixa', function() {
				console.log('confirma baixa');
				let idAnul = $('#id-anula-fact').html().trim();
				let tornarAnul = $('#import-anula-fact').val().trim();
				let dataAnul = $('#data-pag-anula-fact').val().trim();
				let obsAnul = $('#obs-anula-fact').val().trim();
				console.log('idAnul ' + idAnul);
				console.log('tornarAnul ' + tornarAnul);
				console.log('dataAnul ' + dataAnul);
				console.log('obsAnul ' + obsAnul);

				if ( !campBuit(tornarAnul) && !campBuit(dataAnul) && validNumero(tornarAnul)
				&& validData(dataAnul) ) {
					$("#modalAnulaFactura").modal('hide');
					var requestAnulFact = $.ajax({
						url: path + "alumnes/anularFactura_Factures.php",
						method: "GET",
						data: {
							id : idAnul,
							tornar : tornarAnul,
							dataAnulacio : dataAnul,
							obs : obsAnul
						},
						dataType: "html"
					});

					requestAnulFact.done(function( msg ) {
						if ( !msg.toLowerCase().includes("error") ) {
							$('#cercar-factura').click();
							afegirHeaderModalSuccess("Factura anul·lada!");
							afegirTextModalSuccess(msg);
							mostrarModalSuccess();
						}
						else {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora d'anul·lar la factura");
							mostrarModalError();
							reloadUrl();
						}
					});

					requestAnulFact.fail(function( jqXHRAnulFact, textStatusAnulFact, errorThrownAnulFact ) {
						errorFunction( jqXHRAnulFact, textStatusAnulFact, errorThrownAnulFact,
							"Hi ha hagut algun error a l'hora d'anul·lar la factura: " );
					});
				}
				else {
					var missatgeError="";
					if (tornarAnul == '') {
						missatgeError += "<span>" + missatgeNoPotEstarBuit("A TORNAR") + "</span>";
						$('#modalAnulaFactura #dades-factura #import-anula-fact').addClass('error');
					}
					else if ( !validNumero(tornarAnul) ) {
						missatgeError += "<span>" + missatgeNoEsNumero("A TORNAR") + "</span>";
						$('#modalAnulaFactura #dades-factura #import-anula-fact').addClass('error');
					}

					if (dataAnul == '') {
						missatgeError += "<span>" + missatgeNoPotEstarBuit("DATA DEVOLUCIÓ") + "</span>";
						$('#modalAnulaFactura #dades-factura #data-pag-anula-fact').addClass('error');
					}
					else if ( !validData(dataAnul) ) {
						missatgeError += "<span>" + missatgeNoTeFormatData("DATA DEVOLUCIÓ") + "</span>";
						$('#modalAnulaFactura #dades-factura #data-pag-anula-fact').addClass('error');
					}
					if (missatgeError != '') {
						var htmlMsgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
						htmlMsgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
						htmlMsgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
						htmlMsgError += "<i class='material-icons'>close</i></button>";
						htmlMsgError += missatgeError + "</div>";

						$('#modalConsultaInformacio #dades-factura').append(htmlMsgError);
					}
					console.log(missatgeError);
				}

			});
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar el modal d'anul·lació de la factura");
			mostrarModalError();
			reloadUrl();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar el modal d'anul·lació d'una factura: " );
	});
}
function mostrarModalPrevisualitzaFactura( id ) {
	$('.modal-info').off();
	let paginaFactura, numPaginesFactura;
	var request = $.ajax({
		url: path + "alumnes/mostraModalPrevFactura_Factures.php",
		method: "GET",
		data: { id : id },
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			paginaFactura = 1;
			numPaginesFactura = 1;
			$("#modalPrevisualizaFactura .modal-body").html(res);
			$("#modalPrevisualizaFactura").modal('show');

			$('.download-factura').off();
			$('.fletxa-left').off();
			$('.fletxa-right').off();

			var nclick = 0;

			$('.download-factura').on('click', function() {
				if ( tePermisEdicio ) {
					$("#modalPrevisualizaFactura").modal('hide');
					var idFact = $('#modalPrevisualizaFactura #factura-relacionada-fact').html().trim();
					var requestDown = $.ajax({
						url: path + "alumnes/descarregaFactura.php",
						method: "GET",
						data: { id : idFact },
						dataType: "html"
					});

					requestDown.done(function( resD ) {

						if (!resD.toLowerCase().includes("error")) {
							var link = document.createElement('a');
							link.setAttribute("id", "download-fact-" + nclick);
							link.href = path + "alumnes/" + resD;
							link.download = resD + '.pdf';
							link.click();
							var requestDown = $.ajax({
								url: path + "alumnes/descarregaFactura.php",
								method: "GET",
								data: { id : idFact },
								dataType: "html"
							});

							requestDown.done(function( resD ) {
								afegirHeaderModalSuccess("Generat!");
								afegirTextModalSuccess("S'ha generat la factura correctament");
								mostrarModalSuccess();
								nclick++;
							});

							requestDown.fail(function( jqXHRRem, textStatusRem, errorThrownRem ) {
								errorFunction( jqXHRRem, textStatusRem, errorThrownRem,
									"Hi ha hagut algun error a l'hora d'eliminar la factura de servidor: " );
							});
						}
						else {
							afegirHeaderModalError("Hi ha hagut un error al generar la descarrega");
							afegirTextModalError('');
							mostrarModalError();
							reloadUrl();
						}
					});

					requestDown.fail(function( jqXHRDown, textStatusDown, errorThrownDown ) {
						errorFunction( jqXHRDown, textStatusDown, errorThrownDown,
							"Hi ha hagut algun error a l'hora de descarregar la factura: " );
					});
				}
				else {
				   mostrarModalNoTensPermisos();
				}

			});

			if ($('#factura-num-pagines')) {
				numPaginesFactura = $('#factura-num-pagines').html();
			}

			$('.fletxa-left').on('click', function() {
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
				if (paginaFactura < numPaginesFactura) {
					$('#pagina-factura' + paginaFactura).fadeOut('fast', function() {
						paginaFactura++;
						$('#pagina-factura' + paginaFactura).fadeIn('fast', function() {
							$('#factura-pagina-actual').html(paginaFactura);
						});
					});
				}

			});
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la previsualització de la factura");
			mostrarModalError();
			reloadUrl();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar el modal de previsualitzar la factura: " );
	});
}
