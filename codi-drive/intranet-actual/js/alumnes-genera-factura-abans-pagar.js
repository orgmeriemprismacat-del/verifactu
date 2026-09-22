let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let idsInsc = [], entitatMarcada='', preuTotal=0, concepte1='', concepte2='';
let cursos = [], edicions = [];

/* Cada vegada que es faci una crida d'un ajax, s'executarà la funció mostrarModalLoading().
Cada vegada que finalitza la crida d'un ajax, s'executarà la funció amagarLoadingModal(). */
$(document).bind("ajaxSend", function(){
	mostrarModalLoading();
}).bind("ajaxComplete", function(){
	amagarLoadingModal();
});

var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain.php?url="+urlPagina,
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

	/* Si premo la tecla ENTER, es reprodueix l'event de clicar del cercar-alumne*/
	$( "#genera-factura-pas-1" ).keyup(function(evObject){
		if (evObject.keyCode == 13)
			$('#cercar-alumne').click();
	});

	/* Busco l'alumne o els diferents registres que poden coincidir amb la cerca */
	$('#cercar-alumne').on('click', function() {
		dni = $('#dni').val().trim();
		if ( dni == ''	) {
			afegirHeaderModalError("Oops...!");
			afegirTextModalError("Omple un camp per poder fer la cerca");
			mostrarModalError();
		}
		else {
			var request = $.ajax({
				url: path + "alumnes/mostrarInformacioInscripcio_generaFactura.php",
				method: "GET",
				data: { dni : dni },
				dataType: "html"
			});

			request.done(function( res ) {
				$('#resultats-cerca').off();
				$('#insc-rel-fact-rel').off();

				if ( !res.toLowerCase().includes("error") ) {
					$('#resultats-cerca').html(res);
					$('#resultats-cerca').show();
				}
				else {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la informació de la inscripció");
					mostrarModalError();
					reloadUrl();
				}

				/* Si es clica sobré el botó .add-inscripcio, s'afegeix en el
				bloc «INSCRIPCIONS RELACIONADES AMB LA FACTURA A GENERAR» la
				inscripció amb la qual està relacionada */
				$('#resultats-cerca').on('click', '.add-inscripcio', function() {
					var id = $(this).attr('id');
					var idInsc = $(this).attr('id').split('-')[2];
					mostrarModalLoading();
					// var trInsc = $('#'+id).parent().parent().html();
					/* Afegim tots elments td del tr de la inscripció marcada excepte l'últim  */
					var elementTractat = $('#'+id).parent();
					var tds = "";
					while ( elementTractat.prev().length > 0 ) {
						elementTractat = elementTractat.prev();
						var tdtemp = "<td>";
						if (elementTractat.attr('id')) {
							tdtemp = "<td ";
							if (
								elementTractat.attr('id').split('-')[0] == 'titol' ||
								elementTractat.attr('id').split('-')[0] == 'hores'
							)
								tdtemp += "class='hide' ";
							tdtemp += "id='"+elementTractat.attr('id')+"'>";
						}
						tds = tdtemp + elementTractat.html()+"</td>"+tds;
					}
					var trInsc = "<tr>"+tds;
					trInsc += "<td><i id='remove-insc-"+idInsc+"' class='material-icons remove-inscripcio'>remove_circle</i></td>";
					trInsc += "</tr>";

					/* Si existeix el table, vol dir que hi ha alguna inscripció
					marcada, per tant simplement afegirem trInsc al final de tota la taula.
					Si no existeix el table, vol dir que encara no s'havia afegit
					cap inscripció, per tant cal crear la taula. */
					if ( $('#insc-rel-fact-rel').html().includes("table") ) {
						$('#insc-rel-fact-rel table tbody').append(trInsc);
					}
					else {
						var theadInsc = $('#'+id).parent().parent().parent().prev().html();
						var table = "<table class='table table-hover table-striped table-hover text-center'>";
						table += "<thead>"+theadInsc+"</thead>";
						table += "<tbody>";
						table += trInsc;
						table += "</tbody></table>";
						$('#insc-rel-fact-rel').html(table);
					}

					/* Elimino la classe perquè no es pugui afegir més d'una vegada
					el registre a inscripcions relacionades*/
					$(this).addClass('no-disponible');
					$(this).removeClass('add-inscripcio');
					amagarLoadingModal();
					afegirHeaderModalSuccess("Afegit!");
					afegirTextModalSuccess("La inscripció s'ha afegit correctament");
					mostrarModalSuccess();
					setTimeout(function(){
						amagarModalSuccess();
					}, 1000);
				});

				/* Si es clica sobré el botó .remove-inscripcio, s'elimina
				la inscripció amb la qual està relacionada del bloc
				«INSCRIPCIONS RELACIONADES AMB LA FACTURA A GENERAR» */
				$('#insc-rel-fact-rel').on('click', '.remove-inscripcio', function() {
					mostrarModalLoading();
					var id = $(this).attr('id');
					var idInsc = $(this).attr('id').split('-')[2];
					$('#'+id).parent().parent().remove();
					if ( !$('#insc-rel-fact-rel table tbody').html().includes('tr') ) {
						var textBuit = "<p>No hi ha inscripcions relacionades amb la factura a generar.</p>";
						$('#insc-rel-fact-rel').html(textBuit);
					}
					if ( $('#add-insc-'+idInsc) ) {
						$('#add-insc-'+idInsc).addClass('add-inscripcio');
						$('#add-insc-'+idInsc).removeClass('no-disponible');
					}
					amagarLoadingModal();
				});
			});

			request.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error  a l'hora de fer la consulta: " );
			});
		}
	});

	/* Quan cliquem el boto continue-pas-2, amaguem el bloc #genera-factura-pas-1
	i mostrem el bloc #genera-factura-pas-2, creem la variable idsInsc
	amb totes les ids de les inscripcions marcades, calculem el preu
	total que cal pagar (la suma dels apagar de les inscripcions),
	calculem el nom del concepte 1*/
	$('#genera-factura-pas-1').on('click', '#continue-pas-2', function() {
		if ( tePermisEdicio ) {
			if ( $('#insc-rel-fact-rel').html().includes('table') ) {
				var id = $(this).attr('id').split('-')[1];
				cursos = [];
				edicions = [];
				preuTotal = 0;
				mostrarModalLoading();

				/* idsInsc = totes les ids de les inscripcions marcades*/
				$('#insc-rel-fact-rel table tbody tr td').each(function() {
					var idTdChild = $(this).children().attr('id');
					//Sabem que existeix un registre, perquè existeix el botó per eliminar
					if ( idTdChild && idTdChild.split('-')[0]=='remove' && idTdChild.split('-')[1]=='insc') {
						var idInsc = idTdChild.split('-')[2];
						idsInsc.push( idInsc );

						//Busquem el valor del apagar d'aquest registre i incrementem el preu total
						preuTotal += parseFloat( $('#apagar-'+idInsc).html() );

						/*busco si el curs d'aquest registre ja existeix a l'array cursos.
						Si no hi és, afegeixo a l'última posició una array amb el codi curs,
						el titol del curs i un array amb el nom i cognom del registre */
						var i=0, trobat=false;
						while ( i<cursos.length && !trobat)  {
							if ( cursos[i][0].toLowerCase() == $('#curs-'+idInsc).html().toLowerCase() )
								trobat = true;
							else
								i++;
						}
						if ( !trobat ) {
							cursos.push([$('#curs-'+idInsc).html(), $('#titol-'+idInsc).html(), [$('#nom-'+idInsc).html()+" "+$('#cognoms-'+idInsc).html()], $('#hores-'+idInsc).html() ]);
						}
						else {
							cursos[i][2].push( $('#nom-'+idInsc).html()+" "+$('#cognoms-'+idInsc).html() );
						}

						/*busco si l'edició d'aquest registre ja existeix a l'array edicions.
						Si no hi és afegeixo un array amb l'any i el mes del registre.*/
						$('#any-'+idInsc).html();
						$('#mes-'+idInsc).html();
						i=0; trobat=false;
						while ( i<edicions.length && !trobat)  {
							if ( parseInt(edicions[i][0]) == parseInt($('#any-'+idInsc).html())
							&& parseInt(edicions[i][1]) == parseInt($('#mes-'+idInsc).html()) )
								trobat = true;
							else
								i++;
						}
						if ( !trobat ) {
							edicions.push( [$('#any-'+idInsc).html(),$('#mes-'+idInsc).html()] );
						}
					}
				});

				if ( cursos.length > 1 || edicions.length > 1 ) {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("No pots fer una factura de diferents cursos o de diferents edicions.");
					amagarLoadingModal();
					mostrarModalError();
				}
				else {
					/* calculem el preu total que cal pagar */
					$('#genera-factura-pas-2 #preu').html(preuTotal+" €");

					/* calculem el concepte 1 i el concepte 2 de la factura  */
					/* Busquem tots els cursos i les persones que pertanyen aquell curs,
					de manera que podem crear el concepte 1 = "Curs DOL, realitzat per:
					Meriem Abjil Bajja"*/
					concepte1 = "";
					for ( var i=0; i<cursos.length; i++ ) {
						if ( i>0 ) concepte1 += ". ";
						concepte1 += "Curs "+cursos[i][1]+", realitzat per: ";
						for ( var j=0; j<cursos[i][2].length; j++ ) {
							if ( j>0) {
								if ( j==cursos[i][2].length-1 )
									concepte1 += " i ";
								else
									concepte1 += ", ";
							}
							concepte1 += cursos[i][2][j];
						}
					}
					/* Busquem totes les edicions, de manera que podem crear el
					concepte 3 = "Convocatòria abril 2021"*/
					concepte2 = "";
					for ( var i=0; i<edicions.length; i++ ) {
						if ( i>0 ) concepte2 += ". ";
						var textEdicio = edicions[i][1];
						var textAny = edicions[i][0];
						var requestMes = $.ajax({
							url: path + "alumnes/calcularTextData.php",
							method: "GET",
							data: {
								date : "2021-"+edicions[i][1]+"-07 00:00:00"
							},
							dataType: "html"
						});

						requestMes.done(function( mes ) {
							if ( !mes.toLowerCase().includes("error") ) {
								textEdicio = mes;
							}
							concepte2 += "Convocatòria "+textEdicio+" "+textAny;
						});

						requestMes.fail(function( jqXHRDadesFact, textStatusDadesFact, errorThrownDadesFact ) {
							errorFunction( jqXHRDadesFact, textStatusDadesFact, errorThrownDadesFact,
								"Hi ha hagut algun error al calcular el text del mes: " );
							concepte2 += "Convocatòria "+textEdicio+" "+textAny;
						});

					}
					$('#genera-factura-pas-2 #concepte1').val(concepte1);
					$('#genera-factura-pas-2 #concepte1').prev().addClass('active');

					$('#genera-factura-pas-1').fadeOut('fast', function() {
						amagarLoadingModal();
						$('#genera-factura-pas-2').fadeIn('fast', function() {
							//
						});
					});
				}
			}
			else {
				afegirHeaderModalError("Alerta");
				afegirTextModalError("No s'ha seleccionat cap inscripció");
				mostrarModalError();
			}
		}
		else {
		   mostrarModalNoTensPermisos();
		}
	});

	/* Quan cliquem el boto continue-pas-3, amaguem el bloc #genera-factura-pas-2
	i mostrem el bloc #genera-factura-pas-3*/
	$('#genera-factura-pas-2').on('click', '#continue-pas-3', function() {
		var con1 = $('#concepte1').val().trim();
		var preuV = $('#preu').html().trim();
		if ( entitatMarcada == '' || con1 == '' || preuV == ''	) {
			afegirHeaderModalError("Oops...!");
			afegirTextModalError("Els camps <strong>Entitat</strong>, <strong>Concepte1</strong> i <strong>Preu</strong> no poden estar buits");
			mostrarModalError();
		}
		else {
			var observacions = $('#observacions').val().trim();
			var request = $.ajax({
				url: path + "alumnes/generaFacturaElectronica_Factures.php",
				method: "POST",
				data: {
					empresa : entitatMarcada,
					concepte1 : con1,
					concepte2 : concepte2,
					preu : preuTotal,
					cursos:  JSON.stringify(cursos),
					edicions:  JSON.stringify(edicions),
					inscripcions:  JSON.stringify(idsInsc),
					observacions:  observacions
				},
				dataType: "html"
			});

			request.done(function( msg ) {
				if ( !msg.toLowerCase().includes("error") ) {
					afegirHeaderModalSuccess("Factura creada!");
					afegirTextModalSuccess(msg);
					mostrarModalSuccess();
					var factura = $('#modalSuccess #factura-creada').html();
					var requestDadesFactura = $.ajax({
						url: path + "alumnes/mostraDadesFacturaElectronica_Factures.php",
						method: "POST",
						data: {
							factura : factura
						},
						dataType: "html"
					});
					var requestInscrFactura = $.ajax({
						url: path + "alumnes/mostraInscripcionsFacturaElectronica_Factures.php",
						method: "POST",
						data: {
							factura : factura
						},
						dataType: "html"
					});

					requestDadesFactura.done(function( msgDadesFactura ) {
						if ( !msg.toLowerCase().includes("error") ) {
							$('#genera-factura-pas-3 .card-body').html(msgDadesFactura);
							$('#genera-factura-pas-2').fadeOut('fast', function() {
								$('#genera-factura-pas-3').fadeIn('fast');
							});
							$('#genera-factura-pas-3').on('click', '.cns-factura', function() {
								var id = $(this).attr('id').split('-')[2];
								$('.modal-info').off();

								var requestPrevFactura = $.ajax({
									url: path + "alumnes/mostraPrevFactura_Factures.php",
									method: "GET",
									data: {
										factura : id
									},
									dataType: "html"
								});
								requestPrevFactura.done(function( msgPrevFactura ) {
									if ( !msgPrevFactura.toLowerCase().includes("error") ) {
										$("#modalConsultaFactura .modal-body").html(msgPrevFactura);
										$("#modalConsultaFactura").modal('show');

										$('.download-factura').off();
										$('.fletxa-left').off();
										$('.fletxa-right').off();

										var nclick = 0;

										$('.download-factura').on('click', function() {
											var id = $('#modalConsultaFactura #factura-relacionada-fact').html().trim();
											var requestDownFactura = $.ajax({
												url: path + "alumnes/descarregaFactura.php",
												method: "GET",
												data: {
													id : id
												},
												dataType: "html"
											});
											requestDownFactura.done(function( msgDownFactura ) {
												$("#modalConsultaFactura").modal('hide');
												if ( !msgDownFactura.toLowerCase().includes("error") ) {
													var link = document.createElement('a');
													link.setAttribute("id", "download-fact-" + nclick);
													link.href = path + "alumnes/" + msgDownFactura;
													link.download = msgDownFactura;
													link.click();
													var requestRemoveFactura = $.ajax({
														url: path + "alumnes/eliminarArxiu.php",
														method: "GET",
														data: {
															filename : msgDownFactura
														},
														dataType: "html"
													});
													requestRemoveFactura.done(function( msg ) {
														afegirHeaderModalSuccess("Generada!");
														afegirTextModalSuccess("S'ha generat la factura correctament");
														mostrarModalSuccess();
														nclick++;
													});
												}
												else {
													afegirHeaderModalError("Alerta");
													afegirTextModalError("Hi ha hagut algun error al descarregar la factura");
													mostrarModalError();
													reloadUrl();
												}
											});
											requestDownFactura.fail(function( jqXHRDownFactura, textStatusDownFactura, errorThrownDownFactura ) {
												errorFunction( jqXHRDownFactura, textStatusDownFactura, errorThrownDownFactura,
													"Hi ha hagut algun error al descarregar la factura: " );
											});
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
										afegirTextModalError("Hi ha hagut algun error al previsualitzar la factura");
										mostrarModalError();
										reloadUrl();
									}

								});

								requestPrevFactura.fail(function( jqXHRPrevFactura, textStatusPrevFactura, errorThrownPrevFactura ) {
									errorFunction( jqXHRPrevFactura, textStatusPrevFactura, errorThrownPrevFactura,
										"Hi ha hagut algun error al previsualitzar la factura: " );
								});
							});
						}
						else {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut algun error al mostrar les dades de la factura");
							mostrarModalError();
							reloadUrl();
						}
					});

					requestDadesFactura.fail(function( jqXHRDadesFact, textStatusDadesFact, errorThrownDadesFact ) {
						errorFunction( jqXHRDadesFact, textStatusDadesFact, errorThrownDadesFact,
							"Hi ha hagut algun error al mostrar les dades de la factura: " );
					});

					requestInscrFactura.done(function( msgInscrFactura ) {
						if ( !msg.toLowerCase().includes("error") ) {
							if ( msgInscrFactura == '' ) {
								msgInscrFactura = "<p>No hi ha inscripcions relacionades amb la factura</p>";
							}
							$('#genera-factura-pas-3 .card-footer').append(msgInscrFactura);
							$('#genera-factura-pas-2').fadeOut('fast', function() {
								$('#genera-factura-pas-3 .card-footer').fadeIn('fast');
							});
						}
						else {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut algun error al mostrar les inscripcions relacionades amb la factura");
							mostrarModalError();
							reloadUrl();
						}

					});

					requestInscrFactura.fail(function( jqXHRInscFact, textStatusInscFact, errorThrownInscrsFact ) {
						errorFunction( jqXHRInscFact, textStatusInscFact, errorThrownInscrsFact,
							"Hi ha hagut algun error al mostrar les inscripcions relacionades amb la factura: " );
					});
				}
				else {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("Hi ha hagut un error a l'hora de generar la factura");
					mostrarModalError();
					// reloadUrl();
				}

			});

			request.fail(function( jqXHR, textStatus, errorThrown ) {
				errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error al generar la factura: " );
			});
		}
	});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
