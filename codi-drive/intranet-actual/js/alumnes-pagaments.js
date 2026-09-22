let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";

/* Cada vegada que es faci una crida d'un ajax, s'executarà la funció mostrarModalLoading().
Cada vegada que finalitza la crida d'un ajax, s'executarà la funció amagarLoadingModal(). */
$(document).bind("ajaxSend", function(){
	mostrarModalLoading();
}).bind("ajaxComplete", function(){
	amagarLoadingModal();
});

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

requestMain.done(function( message ) {
	$('.mainpanel').html(message);

	$('#pagaments').on('click', '.tipusInsc', function() {
		$('.tipusInsc').removeClass('marcat');
		$(this).addClass('marcat');
	});

	$( "#pagaments" ).keyup(function(evObject){
		if (evObject.keyCode == 13)
			$('#cercar-pagament').click();
	});

	$('#pagaments').on('click', '#cercar-pagament', function() {
		var dni = $('#dni').val().trim();
		var codiRegal = $('#codi-regal').val().trim();
		var numFact = $('#num-fact').val().trim();
		var tipusInsc = "";
		if ( $('.tipusInsc.marcat').attr('id') == "tipus-individual" ) {
			tipusInsc = "I";
		}
		else if ( $('.tipusInsc.marcat').attr('id') == "tipus-grup" ) {
			tipusInsc = "G";
		}

		if ( dni == '' && codiRegal == '' && numFact == '' ) {
			afegirHeaderModalError("Oops...!");
			afegirTextModalError("Has d'omplir els camps del DNI, el Codi regal o el número de factura per buscar el pagament");
			mostrarModalError();
		}
		else if (
			( dni != '' && ( codiRegal != '' || numFact != '' ) ) ||
			( codiRegal != '' && ( dni != '' || numFact != '' ) ) ||
			( numFact != '' && ( codiRegal != '' || dni != '' ) )
		) {
			afegirHeaderModalError("Oops...!");
			afegirTextModalError("Per efectuar la cerca, només pots emplenar un camp. Si us plau, tria entre buscar per DNI, per codi regal o per Núm. factura.");
			mostrarModalError();
		}
		else {
			var reqPagament = $.ajax({
				url: path + "alumnes/buscarInfomacioPagament.php",
				method: "GET",
				data: {
					dni : dni,
					codiRegal : codiRegal,
					numFact : numFact,
					tipusInsc : tipusInsc
				},
				dataType: "html"
			});

			reqPagament.done(function( res ) {
				if ( !res.toLowerCase().includes("error") ) {
					$('#resultats-cerca').html(res);
					$('#resultats-cerca').show();
					$('#resultats-cerca').off();

					//quan es clica a qualsevol lloc fora del select, amago el desplegable
					$(window).click(function() {
						//amago el desplegable
						$('.select .select-list').hide();
						//retorno el triangle com esta per defecte
						var triangle = $('.select').find("i");
						triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
					});

					//quan estas focus en el camp, elimino el marcatge de l'input
					$('#resultats-cerca').on('focus', '.form-control', function() {
						$(this).parent().removeClass('element-cercat-marcat');
					});

					//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
					$('#resultats-cerca').on('blur', '.form-control', function() {
						if ($(this).val().trim() == '')
							$(this).removeClass('element-cercat-marcat');
						else
							$(this).addClass('element-cercat-marcat');
					});

					$("#resultats-cerca .select").on("click", function(e) {
						console.log('1');
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
					$("#resultats-cerca .select").on("click", "li", function(e) {
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
					});

					$("#resultats-cerca").on("click", ".tipus", function() {
						var idTipus = $(this).attr('id').split('-')[1];
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
							if ( !msg.toLowerCase().includes("error") ) {
								$('#infoPag .modal-body').html(msg);
								$('#infoPag').modal('show');

								$("#infoPag").on("click", '.cnsDadesAlumne', function(e) {
									var dni = $(this).attr('id').split('-')[2];
									var link = "https://intranet.prisma.cat/alumnes/mostrar-alumne/#/"+dni;
									window.open(link, '_blank');
								});

							}
							else {
								afegirHeaderModalError("Alerta");
								afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la informació del pagament");
								mostrarModalError();
							}
						});
						getModal.fail(function( jqXHR, textStatus, errorThrown ) {
							errorFunction( jqXHR, textStatus, errorThrown,
								"Hi ha hagut algun error a l'hora de mostrar la informació del pagament:");
						});
					});

					/* Quan es canvia la data de pagament, es comprova si la data és correcte */
					$("#resultats-cerca").on("change", ".dataPag", function(e) {
						dataCorrecte( $(this).val() );
					});

					/* Quan es modifica el pagament, s'incremente el valor del pagat amb el valor del pagament */
					$("#resultats-cerca").on("change", ".pagament", function(e) {
						var idInputPag = $(this).parent().parent().attr('id').split('-')[1];
						suma(idInputPag, $(this).val());
					});

					/* Confirmo el pagament */
					$("#resultats-cerca").on("click", ".upd-inscripcio", function(e) {
						var idTipus = $(this).attr('id').split('-')[2];

						var tipusInsc = $('#tipus-'+idTipus).html();

						var pagInsc = $('#pagament-'+idTipus).val();
						if ( $('#pagament-'+idTipus+' input')[0] ) {
							pagInsc = $('#pagament-'+idTipus+' input').val();
						}
						else {
							pagInsc = $('#pagament-'+idTipus).html();
						}
						pagInsc = pagInsc.replace(',', '.');

						var esValidPagament = "";
						if( pagInsc != "") {
							if ( pagInsc == 0) esValidPagament = "Cal introduïr un import diferent de 0!";
							else {
								if( isNaN(pagInsc) ) esValidPagament = "Cal introduir un nombre";
							}
						}
						else {
							esValidPagament = "Cal introduïr un import!";
						}

						var dataPagInsc = $('#dataPag-'+idTipus).val();

						var esValidData = dataEsValida( dataPagInsc );

						var bancInsc = $('#banc-'+idTipus+" .element-selected").html().trim();

						var esValidBanc = "";
						if ( bancInsc == "" || bancInsc == "Triar" ) {
							esValidBanc = "Cal triar un banc!";
						}

						var obsInsc = $('#pagObs-'+idTipus).html().trim();

						var efactPagament = $('#efact-'+idTipus).html().trim();
						var numeroFact = $('#numFact-'+idTipus).html().trim();

						if ( esValidPagament == '' && esValidData == '' && esValidBanc == '' ) {

							if ( efactPagament == 1 ) {
								console.log('previsualitzacio');
								mostrarModalConfirmacioPagament(numeroFact, idTipus, tipusInsc, pagInsc, dataPagInsc, bancInsc, obsInsc);
							}
							else {
								console.log('enviar pagament');
								mostrarModalLoading();
								aplicarPagament(idTipus, tipusInsc, pagInsc, dataPagInsc, bancInsc, obsInsc, numeroFact, 0);
							}
						}
						else {
							var msgError = esValidPagament;

							if ( msgError != '' && esValidData != '' ) msgError += "<br>" + esValidData;
							else if ( msgError == '' && esValidData != '' ) msgError = esValidData;

							if ( msgError != '' && esValidBanc != '' ) msgError += "<br>" + esValidBanc;
							else if ( msgError == '' && esValidBanc != '' ) msgError = esValidBanc;

							afegirHeaderModalError("Alerta!");
							afegirTextModalError(msgError);
							mostrarModalError();
						}
					});
				}
				else {
					afegirHeaderModalError("Alerta!");
					afegirTextModalError("Hi ha hagut un error a l'hora de buscar el pagament");
					mostrarModalError();
					reloadUrl();
				}
			});

			reqPagament.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut algun error a l'hora de buscar el pagament: " );
			});
		}
	});

	$('#analisis-tpv').off();

	$('#analisis-tpv').on('submit', '#formTPV', function() {
		console.log('submit');
		$('.alert.alert-danger').remove();

		if ( $('#fitxer-tpv')[0].files[0] ) {
			var dades = new FormData();
			dades.append('fitxer-tpv',$('#fitxer-tpv')[0].files[0]);

			$("#pagaments-tpv ul").off();

			var sendFile = $.ajax({
				url: path + "alumnes/analitzarFitxerTPV.php",
				method: "POST",
				contentType:false,
				data: dades,
				dataType: "json",
				processData:false
			});

			sendFile.done(function( resposta ) {
				$('#pagaments-tpv').hide();
				if ( resposta.state == 1 ) {
					afegirHeaderModalSuccess("Genial!");
					afegirTextModalSuccess(resposta.msg);
					mostrarModalSuccess();
				}
				else if ( resposta.state == 2 ) {
					afegirHeaderModalError("Oops..!");
					afegirTextModalError(resposta.msg);
					mostrarModalError();
					$('#pagaments-tpv').html(resposta.registresPagErrors);
					$('#pagaments-tpv').show();

					$("#pagaments-tpv ul").on("click", '.consultaPagament', function(e) {
						var tipus = $(this).attr('id').split('-')[0];
						var dni = $(this).attr('id').split('-')[1];
						if ( tipus == 'cnsAlumne')
							var link = "https://intranet.prisma.cat/alumnes/mostrar-alumne/#/" + dni;
						else if ( tipus == 'cnsFactura' )
							var link = "https://intranet.prisma.cat/alumnes/factura/#/" + dni;
						window.open(link, '_blank');
					});

				}
				else if ( resposta.state == 0 ) {
					afegirHeaderModalError("Alerta!");
					afegirTextModalError(resposta.msg);
					mostrarModalError();
				}
				else {
					afegirHeaderModalError("Alerta!");
					afegirTextModalError(resposta);
					mostrarModalError();
					reloadUrl();
				}
			});

		}
		else {
			var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
			msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
			msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
			msgError += "<i class='material-icons'>close</i></button>";
			msgError += "No has seleccionat cap fitxer" + "</div>";

			$('#analisis-tpv .card-body').append(msgError);
		}

		return false;

	});

	/* Retorna el text buit si valor té el format d'una data dd/mm/yyyy i
	és una data correcte. Altrament retorna l'error */
	function dataEsValida( valor ) {
		// revisar el patró
		if(!/^\d{4}\-\d{1,2}\-\d{1,2}$/.test(valor))
		return "Revisa el format de la data de pagament";

		// convertir els nombres a enters
		var parts = valor.split("-");
		var day = parseInt(parts[2], 10);
		var month = parseInt(parts[1], 10);
		var year = parseInt(parts[0], 10);

		// Revisar els rangs d'any i mes
		if( (year < 1000) || (year > 3000) || (month == 0) || (month > 12) )
		return "Revisa els rangs d'any i mes de la data de pagament";

		var monthLength = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];

		// Ajustar els anys bisiestos
		if(year % 400 == 0 || (year % 100 != 0 && year % 4 == 0))
		monthLength[1] = 29;

		// Revisar el rang dels dies
		if (! ( day > 0 && day <= monthLength[month - 1] ) )
			return "Revisa el rang dels dies de la data de pagament";
		else
			return "";
	}

	// Calcula la diferència entre dies de la data date1 i la data date 2
	function diffDates(date1, date2) {
	 	var partsDate1 = date1.split('-');
		var partsDate2 = date2.split('-');
		var partsDateUTC1 = Date.UTC(partsDate1[0], partsDate1[1]-1, partsDate1[2]);
		var partsDateUTC2 = Date.UTC(partsDate2[0], partsDate2[1]-1, partsDate2[2]);
		var diff = partsDateUTC2 - partsDateUTC1;
		var days = Math.floor(diff / (1000 * 60 * 60 * 24));

		return days;
	}

	//Comprova si la data és correcta i envia una alerta en cas de no ser-ho
	function dataCorrecte( valor ) {
		var esValid = dataEsValida(valor);

		if ( esValid == "" ) {
			/* comprovem la diferencia en dies que hi ha entre la data actual i la
			data valor. Si la data valor és posterior a l'actual no és valid.
			Si la data valor és més anterior de 5 dies també no és vàlid. Altrament és vàlid*/
			var dateNow = new Date();

			var dd = dateNow.getDate();
			var mm = dateNow.getMonth()+1;
			var yyyy = dateNow.getFullYear();

			if ( dd < 10 ) dd = '0' + dd;

			if( mm < 10 ) mm = '0' + mm;

			dateNow = yyyy + '-' + mm + '-' + dd;

			if ( diffDates(valor, dateNow) >= 5 ) {
				afegirHeaderModalError("Alerta");
				afegirTextModalError("Fa més de 5 dies de la data de pagament");
				mostrarModalError();
			}
			if ( diffDates(valor, dateNow) < 0 ) {
				afegirHeaderModalError("Alerta");
				afegirTextModalError("La data de pagament és posterior a avui");
				mostrarModalError();
			}
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError(esValid);
			mostrarModalError();
		}
	}

	//Es suma el valor valor en el registre que té com a id "pagat-"+idInsc.
	function suma ( idInsc, valor ) {
		$('#pagat-'+idInsc).removeClass('danger');
		$('#apagar-'+idInsc).removeClass('danger');

		var pagamentInsc = parseFloat( valor.replace(",", ".") );
		var pagatInsc = parseFloat( $('#pagat-inici-'+idInsc).html().trim().replace(",", ".") );

		var pagatTotal;
		if ( pagamentInsc != '' && pagamentInsc > 0 ) {
			pagatTotal = parseFloat(pagamentInsc + pagatInsc).toFixed(2);
		}
		else {
			pagatTotal = parseFloat(0).toFixed(2);
		}
		$('#pagat-'+idInsc).html(pagatTotal + " €");

		if ( pagatTotal > parseFloat($('#apagar-'+idInsc).html().trim()) ) {
			$('#pagat-'+idInsc).addClass('danger');
			$('#apagar-'+idInsc).addClass('danger');
		}
	}

	//Previsualització
	function mostrarModalConfirmacioPagament(numFact, idTipus, tipusInsc, pagInsc, dataPagInsc, bancInsc, obsInsc) {
		var getModal = $.ajax({
			url: path + "alumnes/mostrarModalConfPag.php",
			global: false,
			method: "GET",
			data: {
				numFact: numFact
			},
			dataType: "html"
		});
		getModal.done(function( msg ) {
			if ( !msg.toLowerCase().includes("error") ) {
				$('#confPag .modal-dialog').addClass('modal-success');
				$('#confPag .modal-dialog').removeClass('modal-info');
				$('#confPag .modal-body').html(msg);
				$("#confPag .modal-content > .modal-footer").addClass('hide');
				$('#confPag').modal('show');

				$("#confPag .modal-body").on("click", "#torna-pagament", function(e) {
					$('#confPag').modal('hide');
				});
				$("#confPag .modal-body").on("click", "#confirmar-pagament", function(e) {
					console.log('enviar pagament');
					$('#confPag').modal('hide');

					mostrarModalLoading();

					aplicarPagament(idTipus, tipusInsc, pagInsc, dataPagInsc, bancInsc, obsInsc, numFact, 1);
				});
			}
			else {
				afegirHeaderModalError("Alerta");
				afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la confirmació del pagament");
				mostrarModalError();
			}
		});
		getModal.fail(function( jqXHR, textStatus, errorThrown ) {
			errorFunction( jqXHR, textStatus, errorThrown,
				"Hi ha hagut algun error a l'hora de mostrar la confirmació del pagament:");
		});
	}

	//S'envia el pagament
	function aplicarPagament(idTipus, tipusInsc, pagInsc, dataPagInsc, bancInsc, obsInsc, numFact, efact) {
		var sendPay = $.ajax({
			url: path + "alumnes/efectuarPagament.php",
			global: false,
			method: "GET",
			data: {
				id: idTipus,
				numFact: numFact,
				tipus: tipusInsc,
				pagament: pagInsc,
				dataPag: dataPagInsc,
				banc: bancInsc,
				obs: obsInsc,
				efact: efact
			},
			dataType: "html"
		});
		sendPay.done(function( msg ) {
			if ( !msg.toLowerCase().includes("error") ) {
				amagarLoadingModal();
				afegirHeaderModalSuccess("Pagament efectuat!");
				afegirTextModalSuccess(msg);
				mostrarModalSuccess();
				// reloadUrl();
				$("#modalSuccess").on('hidden.bs.modal', function (e) {
					reloadUrl();
				})
			}
			else {
				afegirHeaderModalError("Alerta");
				afegirTextModalError("Hi ha hagut un error a l'hora d'efectuar el pagament");
				mostrarModalError();
				reloadUrl();
			}
		});
		sendPay.fail(function( jqXHR, textStatus, errorThrown ) {
			errorFunction( jqXHR, textStatus, errorThrown,
				"Hi ha hagut algun error a l'hora d'efectuar el pagament: " );
		});
	}
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
