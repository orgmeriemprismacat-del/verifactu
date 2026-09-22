var urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let hashUrl = null;
if ( window.location.hash.split('#')[1])
	hashUrl = window.location.hash.split('#')[1].split('/')[1];

/* Cada vegada que es faci una crida d'un ajax, s'executarà la funció mostrarModalLoading().
Cada vegada que finalitza la crida d'un ajax, s'executarà la funció amagarLoadingModal(). */
$(document).bind("ajaxSend", function(){
	mostrarModalLoading();
}).bind("ajaxComplete", function(){
	amagarLoadingModal();
});

/* Consulta el codi del main */
var requestMain = $.ajax({
	url:  path + "mostrarMain.php",
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
	$('#mostrar-curs').on('focus', '.form-control', function() {
		$(this).parent().removeClass('element-cercat-marcat');
	});

	//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
	$('#mostrar-curs').on('blur', '.form-control', function() {
		if ($(this).val().trim() == '')
			$(this).removeClass('element-cercat-marcat');
		else
			$(this).addClass('element-cercat-marcat');
	});

	//Canvio d'icona quan es clica un desplegable
	$("#mostrar-curs .select").click(function(e) {
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

	//Marco l'element del desplegable i l'afegeixo al camp
	$("#mostrar-curs .select").on("click", "li", function(e) {
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

	/* Si premo la tecla ENTER, es reprodueix l'event de clicar del cercar-curs*/
	$("#mostrar-curs").keyup(function(evObject) {
		if (evObject.keyCode == 13) $('#cercar-curs').click();
	});

	$('#cercar-curs').on('click', function() {
		let idCurs = $('#codiCurs').val().trim(),
			 any = $('#anys-dispo .element-selected').html().trim(),
			 mes = $('#mesos-dispo .element-selected').html().trim(),
			 curs = $('#cursos-dispo .element-selected').html().trim(),
			 cercaPer = "RESULTATS DE LA CERCA PER ",
			 elementsCercats = "";
		if ($('#amaga-cerca-avancada').css('display') == 'none') {
			any = mes = curs = "";
		}
		else {
			// Si algun desplegable té la opció marcada "qualsevol", el transformem a caracter buit
			if (any.toLowerCase().includes("qualsevol"))
				any = "";
			if (mes.toLowerCase().includes("qualsevol"))
				mes = "";
			if (curs.toLowerCase().includes("qualsevol"))
				curs = "";
		}

		if (
			( $('#amaga-cerca-avancada').css('display') == 'none' &&
				idCurs == '' )
			||
			(
				($('#amaga-cerca-avancada').css('display') != 'none') &&
				(any == '' && mes == '' && curs == '' && idCurs == '' )
			)
		) {
			afegirHeaderModalError("Omple un camp per poder fer la cerca");
			mostrarModalError();
		}
		else {

			if (idCurs != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "CODI CURS «" + idCurs + "»";
			}
			if (any != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "ANY «" + any + "»";
			}
			if (mes != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "MES «" + mes + "»";
			}
			if (curs != '') {
				if (elementsCercats != '') elementsCercats += " i ";
				elementsCercats += "CURS «" + curs + "»";
			}

			cercaPer += elementsCercats;

			/* XXXX */
			var request = $.ajax({
				url: path + "cursos/consultaCurs.php",
				method: "GET",
				data: {
					idCurs : idCurs,
					any : any,
					mes : mes,
					curs : curs
				},
				dataType: "html"
			});

			request.done(function( message ) {
				if ( message.includes("Error") || message.includes("error") ) { //Hi ha un error
					amagarLoadingModal();
					afegirHeaderModalError("Hi ha hagut un error al cercar del curs");
					mostrarModalError();
					reloadUrl();
				} else if (message.includes("No") && message.includes("resultats")) {
					amagarLoadingModal();
					afegirHeaderModalError("Alerta");
					afegirTextModalError("No s'han trobat resultats");
					mostrarModalError();
				} else if (message.split('|') > 2000) {
					amagarLoadingModal();
					afegirHeaderModalError("Alerta");
					afegirTextModalError("El volum de dades cercat és molt gran. Si us plau, afegeix algun filtre més per acotar el volum de dades");
					mostrarModalError();
				} else {
					var cursos = message.split('#')[1];
					if (cursos.split('|').length == 1) { // Només existeix una edició del curs
						// Cercar el curs: cursos[0]. cercaPer es el text que t'indica per l'element el qual has  cercat
						cercarUnCurs(cursos, cercaPer);
					} else { // Existeix més d'una edició del curs
						// Mostra la taula amb els cursos trobats a partir de la cerca realitzada.
						// cercaPer es el text que t'indica per l'element el qual has  cercat
						mostraLlistatCursos(cursos, 'id', 'asc', cercaPer);
					}
				}
			});

			request.fail(function( jqXHR, textStatus, errorThrown ) {
				errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
			});
		}
	});

	$('#mostra-cerca-avancada').on('click', function() {
		$('#mostra-cerca-avancada').fadeOut('fast', function() {
			$('#mostrar-curs .cerca-av').fadeIn('fast', function() {});
			$('#amaga-cerca-avancada').fadeIn('fast', function() {});
			$('#eliminar-filtres').fadeIn('fast', function() {});
		});
	});
	$('#amaga-cerca-avancada').on('click', function() {
		$('#amaga-cerca-avancada').fadeOut('fast', function() {
			$('#eliminar-filtres').fadeOut('fast', function() {});
			$('#mostrar-curs .cerca-av').fadeOut('fast', function() {});
			$('#mostra-cerca-avancada').fadeIn('fast', function() {});
		});
	});
	$('#eliminar-filtres').on('click', function() {
		$('#mostrar-curs .select .element-selected').html('');
		$('#mostrar-curs input').prev().removeClass('active');
		$('#mostrar-curs .select').prev().removeClass('active');
		$('#mostrar-curs input').val('');
		$('#mostrar-curs .element-cercat-marcat').removeClass('element-cercat-marcat');
	});

	if ( hashUrl ) {
		$('#mostrar-curs #codiCurs').val(hashUrl);
		$('#mostrar-curs #codiCurs').prev().addClass('active');
		$('#cercar-curs').click();
	}
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

/*
	Mostra la taula amb els cursos trobats a partir de la cerca realitzada ordenada
	per l'id de cursos si orderby es 'id', per any si orderby es 'any', per mes si
	orderby es 'mes' i per curs si orderby és 'curs' . S'odrenarà ascendentment
	si asc és 1 i en descendentment si és 0.
*/
function mostraLlistatCursos(cursos, orderby, asc, cercaPer) {
	$('#resultats-cerca').off();
	$('.table-order').off();

	var request = $.ajax({
		url: path + "cursos/mostraTaulaEdicionsCursosOrderBy.php",
		global: false,
		method: "GET",
		data: {
			cursos: cursos,
			orderby: orderby,
			asc: asc
		},
		dataType: "html"
	});
	request.done(function( msg ) {
		if ( !msg.toLowerCase().includes("error") ) {
			$('#resultats-cerca').html(msg);
			$('#resultats-cerca').show();

			//Quan sel·lecciono un registre de la taula, mostro la informació de l'usuari
			$('#resultats-cerca').on('click', '.seleccionar', function() {
				mostrarModalLoading();
				cercarUnCurs( $(this).attr('id'), cercaPer );
			});

			/*
				Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
				s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
			*/
			$('.table-order').on('click', '.sorting', function() {
				var idOrderBy = $(this).attr('id').substr(3, $(this).attr('id').length);
				if ( $(this).hasClass('asc') )
					mostraLlistatCursos(cursos, idOrderBy, 0, cercaPer);
				else
					mostraLlistatCursos(cursos, idOrderBy, 1, cercaPer);
			});
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la informació dels cursos");
			mostrarModalError();
		}
	});
	request.fail(function( jqXHR, textStatus, errorThrown ) {
		if ( jqXHR.status == 414 ) {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("El resultat de la cerca és molt gran. Si us plau, acota més la cerca.");
			mostrarModalError();
		}
		else
			errorFunction( jqXHR, textStatus, errorThrown,
				"Hi ha hagut un error a l'hora de mostrar la informació del curs: ");
	});
}

function cercarUnCurs(idCurs, cercaPer) {
	var request = $.ajax({
		url: path + "cursos/mostrarInfoEdicioCurs.php",
		global: false,
		method: "GET",
		data: {
			idCurs: idCurs,
			cercaPer: cercaPer
		},
		dataType: "html"
	});
	request.done(function( msg ) {
		amagarLoadingModal();

		if ( msg.toLowerCase() != '' && !msg.toLowerCase().includes("error") ) {
			$('#resultats-cerca').html(msg);
			$('#resultats-cerca').show();
		}
		else if ( msg.toLowerCase() == '' ) {
			afegirHeaderModalError("Aquest curs no existeix!");
			afegirTextModalError("");
			mostrarModalError();
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de buscar l'edició del curs");
			mostrarModalError();
			reloadUrl();
		}

		$("#resultats-cerca .card-body").off();

		//es desactiva qualsevol event que depengui de dades-edicio i dades-aula
		$('#dades-edicio').off();
		$('#dades-aula').off();

		function mostrarEdicioApartat( idContainer ) {
			$('#' + idContainer + " .apartat .form-group .form-control").each(function() {
				var id = $(this).attr('id');
				var text = $(this).html();
				var parent = $(this).parent();
				//Casos puntuals que no deixo editar
				if ( id != 'numAules-cercat' && id != 'nomTutor-cercat' ) {
					$(this).remove();
					parent.append("<input type='text' class='form-control edit' id='" + id + "' name='" + id + "' value=\"" + text + "\">");
				}

			});

			$('#' + idContainer + ' .editar-apartat').html("save");
			$('#' + idContainer + ' .editar-apartat').addClass("save-result");
			$('#' + idContainer + ' .editar-apartat').removeClass("editar-apartat");
			$('#' + idContainer + ' .titol-apartat').append("<i class='material-icons ml-2 cancelar-apartat'>cancel</i>");
		}

		function cancelarEdicioApartat( idContainer ) {
			$("#" + idContainer + " .apartat .form-group .form-control").each(function() {
				var id = $(this).attr('id');
				var text = $(this).val();
				var parent = $(this).parent();
				$(this).remove();
				parent.append("<div class='form-control no-edit' id='" + id + "'>" + text + "</div>");
			});

			$("#" + idContainer + " .save-result").html("edit");
			$("#" + idContainer + " .save-result").addClass("editar-apartat");
			$("#" + idContainer + " .save-result").removeClass("save-result");
			$("#" + idContainer + " .cancelar-apartat").remove();
		}

		/*Si es clica el botó d'.editar-apartat' a l'apartat'#dades-edicio',
		s'habilita l'edició en els inputs de l'apartat,
		s'amaga el botó d'edita i s'afageix el botó de guardar resultat i cancel·lar */
		$('#dades-edicio').on('click', '.editar-apartat', function() {
			if ( tePermisEdicio ) {
				mostrarEdicioApartat( 'resultats-cerca #dades-edicio' );
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});

		/*Si es clica el botó d'.save-result' a l'apartat'#dades-edicio',
		es guarden els canvis de l'edició del curs a la BD i
		s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
		$('#dades-edicio').on('click', '.save-result', function() {
			if ( tePermisEdicio ) {
				/*  ####################################### a comprovar #######################################  */
				console.log('save result');

				var idCurs = $('#id-cercat').val().trim();
				let nomCurs = $('#nomCurs-cercat').val().trim();
				let dataI = $('#dataI-cercat').val().trim();
				let dataF = $('#dataF-cercat').val().trim();
				let hores = $('#hores-cercat').val().trim();
				let cursEsc = $('#cursEsc-cercat').val().trim();
				let codiGtaf = $('#codiGtaf-cercat').val().trim();
				let codiFiss = $('#codiFiss-cercat').val().trim();
				let dataRes = $('#dataRes-cercat').val().trim();
				let dataQual = $('#dataQual-cercat').val().trim();
				let dataBloq = $('#dataBloq-cercat').val().trim();
				let valGtaf = $('#valGtaf-cercat').val().trim();
				let valFiss = $('#valFiss-cercat').val().trim();
				let obs = $('#obs-cercat').val().trim();

				$('#resultats-cerca #dades-edicio .apartat input').removeClass('error');

				if (!campBuit(nomCurs) && !campBuit(dataI) && !campBuit(dataF) &&
					!campBuit(hores) && !campBuit(cursEsc) && !campBuit(codiGtaf) &&
					!campBuit(dataRes) && validData(dataI) && validData(dataF) &&
					validData(dataRes) && validData(dataQual) && validData(dataBloq) && validData(valGtaf) && validData(valFiss)
				) {
					var request = $.ajax({
						url: path + "cursos/desarCanvisDadesEdicio.php",
						global: false,
						method: "POST",
						data: {
							idCurs: idCurs,
							nomCurs: nomCurs,
							dataI: dataI,
							dataF: dataF,
							hores: hores,
							cursEsc: cursEsc,
							codiGtaf: codiGtaf,
							codiFiss: codiFiss,
							dataRes: dataRes,
							dataQual: dataQual,
							dataBloq: dataBloq,
							valGtaf: valGtaf,
							valFiss: valFiss,
							obs: obs
						},
						dataType: "html"
					});
					request.done(function( msg ) {
						if ( !msg.toLowerCase().includes("error") ) {
							amagarLoadingModal();

							if (!msg.includes("Error") && !msg.includes("error")) {
								afegirHeaderModalSuccess("Els canvis s'han guardat correctament");
								mostrarModalSuccess();
								$("#resultats-cerca #dades-edicio .apartat .form-group .form-control").each(function() {
									var id = $(this).attr('id');
									var text = $(this).val();
									var parent = $(this).parent();
									$(this).remove();
									parent.append("<div class='form-control no-edit' id='" + id + "'>" + text + "</div>");
								});

								$('#resultats-cerca #dades-edicio .save-result').html("edit");
								$('#resultats-cerca #dades-edicio .save-result').addClass("editar-apartat");
								$('#resultats-cerca #dades-edicio .save-result').removeClass("save-result");
								$('#resultats-cerca #dades-edicio .cancelar-apartat').remove();

								$('#codiCurs').val(idCurs);
								$('#cercar-curs').click();
							}
							else {
								afegirHeaderModalError(msg);
								mostrarModalError();
								reloadUrl();
							}
						}
						else {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora de guardar les dades de l'edició");
							mostrarModalError();
						}
					});
					request.fail(function( jqXHR, textStatus, errorThrown ) {
						errorFunction( jqXHR, textStatus, errorThrown,
							"Hi ha hagut algun error a l'hora de guardar les dades de l'edició:");
					});
				}
				else {
					var errors = "";
					if ( campBuit(nomCurs) ) {
						errors += "<span>" + missatgeNoPotEstarBuit("NOM CURS") + "</span>";
						$('#nomCurs-cercat').addClass('error');
					}
					if ( campBuit(dataI) ) {
						errors += "<span>" + missatgeNoPotEstarBuit("DATA INICI") + "</span>";
						$('#dataI-cercat').addClass('error');
					}
					else if ( !validData(dataI) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA INICI") + "</span>";
						$('#dataI-cercat').addClass('error');
					}
					if ( campBuit(dataF) ) {
						errors += "<span>" + missatgeNoPotEstarBuit("DATA FI") + "</span>";
						$('#dataF-cercat').addClass('error');
					}
					else if ( !validData(dataF) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA FI") + "</span>";
						$('#dataF-cercat').addClass('error');
					}
					if (campBuit(hores)) {
						errors += "<span>" + missatgeNoPotEstarBuit("HORES") + "</span>";
						$('#hores-cercat').addClass('error');
					}
					if (campBuit(cursEsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("CURS ESCOLAR") + "</span>";
						$('#cursEsc-cercat').addClass('error');
					}
					if (campBuit(codiGtaf)) {
						errors += "<span>" + missatgeNoPotEstarBuit("CODI GTAF") + "</span>";
						$('#codiGtaf-cercat').addClass('error');
					}
					if (campBuit(codiFiss)) {
						errors += "<span>" + missatgeNoPotEstarBuit("CODI FISS") + "</span>";
						$('#codiFiss-cercat').addClass('error');
					}
					if ( campBuit(dataRes) ) {
						errors += "<span>" + missatgeNoPotEstarBuit("DATA RESOLUCIÓ") + "</span>";
						$('#dataRes-cercat').addClass('error');
					}
					else if ( !validData(dataRes) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA RESOLUCIÓ") + "</span>";
						$('#dataRes-cercat').addClass('error');
					}
					if ( !validData(dataQual) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA QUALIFICACIÓ") + "</span>";
						$('#dataQual-cercat').addClass('error');
					}
					if ( !validData(dataBloq) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA BLOQUEIG") + "</span>";
						$('#dataBloq-cercat').addClass('error');
					}
					if ( !validData(valGtaf) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA VALORACIÓ GTAF") + "</span>";
						$('#valGtaf-cercat').addClass('error');
					}
					if ( !validData(valFiss) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA VALORACIÓ FISS") + "</span>";
						$('#valFiss-cercat').addClass('error');
					}

					var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
					msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
					msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
					msgError += "<i class='material-icons'>close</i></button>";
					msgError += errors + "</div>";

					$('#resultats-cerca #dades-edicio').append(msgError);
				}
			}
			else {
			   mostrarModalNoTensPermisos();
			}

			/*  ####################################### fi a comprovar #######################################  */

		});

		/*Si es clica el botó d'.cancelar-apartat' a l'apartat'#dades-edicio',
		es deshabilita l'edició en els inputs de l'apartat,
		s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
		$('#dades-edicio').on('click', '.cancelar-apartat', function() {
			if ( tePermisEdicio ) {
				cancelarEdicioApartat( 'resultats-cerca #dades-edicio' );
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});

		/*Si es clica el botó d'.editar-apartat' a l'apartat'#dades-aula',
		s'habilita l'edició en els inputs de l'apartat,
		s'amaga el botó d'edita i s'afageix el botó de guardar resultat i cancel·lar */
		$('#dades-aula').on('click', '.editar-apartat', function() {
			if ( tePermisEdicio ) {
				var aula = $(this).attr('id').split('-')[3];

				mostrarEdicioApartat( 'resultats-cerca #dades-aula-'+aula );
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});

		/*Si es clica el botó d'.save-result' a l'apartat'#dades-aula',
		es guarden els canvis de l'edició del curs a la BD i
		s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
		$('#dades-aula').on('click', '.save-result', function() {
			if ( tePermisEdicio ) {
				var aula = $(this).attr('id').split('-')[3];

				var idCurs = $('#idCursAula-' + aula + '-cercat').val().trim();
				var idAula = $('#idAula-' + aula + '-cercat').val().trim();
				let dataRevisio = $('#dataRevisio-' + aula + '-cercat').val().trim();
				let dataInforme = $('#dataInforme-' + aula + '-cercat').val().trim();
				let obs = $('#obs-' + aula + '-cercat').val().trim();

				$('#resultats-cerca #dades-aula-'+aula+' .apartat input').removeClass('error');

				if (validData(dataRevisio) && validData(dataInforme) ) {
					var request = $.ajax({
						url: path + "cursos/desarCanvisDadesAulaEdicio.php",
						global: false,
						method: "POST",
						data: {
							idCurs: idCurs,
							aula: aula,
							dataRevisio: dataRevisio,
							dataInforme: dataInforme,
							obs: obs,
							idAula: idAula
						},
						dataType: "html"
					});
					request.done(function( msg ) {
						if ( !msg.toLowerCase().includes("error") ) {
							amagarLoadingModal();

							if (!msg.includes("Error") && !msg.includes("error")) {
								afegirHeaderModalSuccess("Els canvis s'han guardat correctament");
								mostrarModalSuccess();
								$("#resultats-cerca #dades-aula-"+aula+" .apartat .form-group .form-control").each(function() {
									var id = $(this).attr('id');
									var text = $(this).val();
									var parent = $(this).parent();
									$(this).remove();
									parent.append("<div class='form-control no-edit' id='" + id + "'>" + text + "</div>");
								});

								$('#resultats-cerca #dades-aula-'+aula+' .save-result').html("edit");
								$('#resultats-cerca #dades-aula-'+aula+' .save-result').addClass("editar-apartat");
								$('#resultats-cerca #dades-aula-'+aula+' .save-result').removeClass("save-result");
								$('#resultats-cerca #dades-aula-'+aula+' .cancelar-apartat').remove();

								$('#codiCurs').val(idCurs);
								$('#cercar-curs').click();

							}
							else {
								afegirHeaderModalError(msg);
								mostrarModalError();
								reloadUrl();
							}
						}
						else {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora de guardar les dades de l'edició");
							mostrarModalError();
						}
					});
					request.fail(function( jqXHR, textStatus, errorThrown ) {
						errorFunction( jqXHR, textStatus, errorThrown,
							"Hi ha hagut algun error a l'hora de guardar les dades de l'edició:");
					});
				}
				else {
					var errors = "";
					if ( !validData(dataRevisio) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA REVISIÓ") + "</span>";
						$('#dataRevisio-' + aula + '-cercat').addClass('error');
					}
					if ( !validData(dataInforme) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA INFORME") + "</span>";
						$('#dataInforme-' + aula + '-cercat').addClass('error');
					}
					if ( !validData(dataBloq) ) {
						errors += "<span>" + missatgeNoTeFormatData("DATA BLOQUEIG") + "</span>";
						$('#dataBloq-' + aula + '-cercat').addClass('error');
					}

					var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
					msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
					msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
					msgError += "<i class='material-icons'>close</i></button>";
					msgError += errors + "</div>";

					$('#resultats-cerca #dades-aula-'+aula+'').append(msgError);
				}
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});

		/*Si es clica el botó d'.cancelar-apartat' a l'apartat'#dades-aula',
		es deshabilita l'edició en els inputs de l'apartat,
		s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
		$('#dades-aula').on('click', '.cancelar-apartat', function() {
			if ( tePermisEdicio ) {
				var aula = $(this).prev().attr('id').split('-')[3];

				cancelarEdicioApartat( 'resultats-cerca #dades-aula-'+aula );
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});

		$("#resultats-cerca .card-body").on("click", ".alumnes", function() {
			//Necessito l'any, el mes, el curs i el grup
			var ids = $(this).attr('id').split('-');
			//Mostra la info dels alumnes en un modal
			mostrarModalInfoAlumnes(ids[1], ids[2], ids[3], ids[4]);
		});

		$("#dades-estat-inscripcio").on("click", ".urlCurs", function() {
			//Obtin el codi de la url
			var id = $(this).attr('id').split('-');

			var requestUrlWeb = $.ajax({
				url: path + "web/buscarUrlPagina.php",
				global: false,
				method: "GET",
				data: {
					codiCurs: id[1]
				},
				dataType: "html"
			});
			requestUrlWeb.done(function( msg ) {
				if ( !msg.includes("error") ) {
					if ( msg != '' ) {
						//Obro una finestra nova amb la info de la web
						var link2 = "https://www.prisma.cat" + msg;
						window.open(link2, '_blank');
					}
					else {
						afegirHeaderModalError("Alerta");
						afegirTextModalError("No s'ha trobat la url de la pàgina");
						mostrarModalError();
					}
				}
				else {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("Hi ha hagut un error a l'hora de buscar la url de la pàgina");
					mostrarModalError();
				}
			});
			requestUrlWeb.fail(function( jqXHR, textStatus, errorThrown ) {
				errorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut algun error a l'hora de buscar la url de la pàgina:");
			});


		});

		$("#dades-aula").on("click", ".urlCurs", function() {
			//Obtinc si el moodle és l'antic o el nou i el id del curs del moodle
			var id = $(this).attr('id').split('-');
			console.log

			//Obro una finestra nova amb l'enllaç al moodle
			var link1;
			if ( id[1] == 'antic' ) {
				link1 = "https://www.prisma.cat/campus/course/view.php?id=" + id[2];
			}
			else if ( id[1] == 'nou' ) {
				link1 = "https://campus.prisma.cat/course/view.php?id=" + id[2];
			}
			window.open(link1, '_blank');
		});

	});
	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de xxxx:");
	});
}

//Mostro el modal de la info dels alumnes amb idCurs id
function mostrarModalInfoAlumnes(year, course, month, group) {
	$('#modalConsultaAlumnes').off();

	var request = $.ajax({
		url: path + "cursos/mostrarModalConsultaAlumnes.php",
		global: false,
		method: "GET",
		data: {
			any: year,
			mes: month,
			curs: course,
			aula: group
		},
		dataType: "html"
	});
	request.done(function( msg ) {
		if ( !msg.toLowerCase().includes("error") ) {
			$("#modalConsultaAlumnes .modal-body").html(msg);
			amagarLoadingModal();
			$("#modalConsultaAlumnes").modal('show');
			$("#dades-aula-"+group).off();

			$("#modalConsultaAlumnes").on("click", '.cnsDadesAlumne', function(e) {
				var dni = $(this).attr('id').split('-')[2];
				var link = "https://intranet.prisma.cat/alumnes/mostrar-alumne/#/"+dni;
				window.open(link, '_blank');
			});
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la informació dels alumnes");
			mostrarModalError();
		}
	});
	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de mostrar la informació dels alumnes:");
	});
}

/* Comprova si valor està buit. Si està buit, retorna true, altrament retorna false */
function campBuit(valor) {
	var buit = false;
	if (valor == '') buit = true;
	return buit;
}
