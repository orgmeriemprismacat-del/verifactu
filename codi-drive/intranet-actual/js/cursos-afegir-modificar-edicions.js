var urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let resultatsPujada = [];
let tablePujada = "";
let dataResolucio = "";
let numTramit = "";

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

	$('#analitza-fitxer-add-ed').off();

	$( "#afegeix-edicions" ).keyup(function(evObject){
		if (evObject.keyCode == 13)
			$('#analitza-fitxer-add-ed').click();
	});
	$( "#afegeix-edicions" ).on('change', '#fitxer-add-edicions', function(e){
		var fileName = '', label = $('.fitxers label'), labelVal = label.innerHTML;

		if( this.files && this.files.length > 1 )
			fileName = ( this.getAttribute( 'data-multiple-caption' ) || '' ).replace( '{count}', this.files.length );
		else
			fileName = e.target.value.split( '\\' ).pop();

		if( fileName )
			$('.fitxers label span').html(fileName);
		else
			$('.fitxers label span').html(labelVal);


		$('.fitxers label').css('left', 'calc( 50% - ' + $('.fitxers label span').width()/2 + 'px)');
	});

	$( '#afegeix-edicions' ).on('submit', '#formAddEdicions', function() {
		console.log('submit');
		$('.alert.alert-danger').remove();

		dataResolucio = $('#dataResolucio').val().trim();
		numTramit = $('#numTramit').val().trim();

		if ( $('#fitxer-add-edicions')[0].files[0] && dataResolucio != '' && numTramit != ''
		 && dataEsValida(dataResolucio) == '' ) {
			var dades = new FormData();
			dades.append('fitxer-add-edicions',$('#fitxer-add-edicions')[0].files[0]);
			enviaFitxer($('#fitxer-add-edicions')[0].files[0], 'creacio', 1);
		}
		else {
			var errors = '';
			if ( !$('#fitxer-add-edicions')[0].files[0] ) {
				errors = "<span>No has seleccionat cap fitxer.</span>";
			}
			if ( dataResolucio == '' ) {
				errors += "<span>El camp <strong>DATA RESOLUCIÓ</strong> no pot estar buit.</span>";
			}
			else if ( dataEsValida(dataResolucio) != '' ) {
				errors += "<span>" + dataEsValida(dataResolucio) + ".</span>";
			}
			if ( numTramit == '' ) {
				errors += "<span>El camp <strong>NÚMERO DE TRÀMIT</strong> no pot estar buit.</span>";
			}

			var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
			msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
			msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
			msgError += "<i class='material-icons'>close</i></button>";
			msgError +=  errors + "</div>";

			$('#afegeix-edicions .card-body').append(msgError);
		}

		return false;

	});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function enviaFitxer( fitxer, orderby, asc ) {
	if ( tePermisEdicio ) {
		$('.table-order').off();
		$('#confirma-pujada-edicions').off();
		$('#prev-add-edicions .modal-body').addClass('opacity-06');

		console.log("1");

		var dades = new FormData();
		dades.append('fitxer-add-edicions', fitxer);
		dades.append('orderby', orderby);
		dades.append('asc', asc);

		var sendFile = $.ajax({
			url: path + "cursos/analitzarFitxerAddEdicions.php",
			method: "POST",
			contentType:false,
			data: dades,
			dataType: "json",
			processData:false
		});

		sendFile.done(function( resposta ) {

			if ( resposta.state == 1 ) {
				resultatsPujada = resposta.resultats;
				tablePujada = resposta.table;
				vectLlegenda = resposta.llegendaErrors;

				var llegenda = "", llegendaCol1 = "", llegendaCol2 = "", llegendaColX = "";

				for ( var i = 0; i < vectLlegenda.length; i++ ) {
					llegendaColX = "<div class='d-flex flex-sm-row flex-column w-100 align-items-center mb-2'>";
					llegendaColX += vectLlegenda[i]["label"];
					llegendaColX += "<p class='m-0'>"+vectLlegenda[i]["explicacio"]+"</p>";
					llegendaColX += "</div>";
					if ( i%2 == 0 ) llegendaCol1 += llegendaColX;
					else llegendaCol2 += llegendaColX;
				}

				if ( vectLlegenda.length > 0 ) {
					llegenda = "<div class='llegenda d-flex flex-md-row flex-column w-100'>";

					llegenda += "<div class='d-flex flex-column w-100'>";
					llegenda += llegendaCol1;
					llegenda += "</div>";

					if ( vectLlegenda.length > 2 ) {
						llegenda += "<div class='d-flex flex-column w-100'>";
						llegenda += llegendaCol2;
						llegenda += "</div>";
					}

					llegenda += "</div>";
				}

				$('#prev-add-edicions .modal-header p').html("Confirma els cursos que vols pujar!");
				$('#prev-add-edicions .modal-body').html(tablePujada);
				$('#prev-add-edicions .modal-body').removeClass('opacity-06');
				$('#prev-add-edicions .modal-footer .llegenda').remove();
				$('#prev-add-edicions .modal-footer').prepend(llegenda);
				$('#prev-add-edicions').modal('show');

				//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
				//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
				$('.table-order').on('click', '.sorting', function() {
					var id = $(this).attr('id').split('-')[1];
					if ( $(this).hasClass('asc') )
						enviaFitxer(fitxer, id, 0);
					else
						enviaFitxer(fitxer, id, 1);
				});
			}
			else if ( resposta.state == 2 ) {
				afegirHeaderModalError("Oops..!");
				afegirTextModalError(resposta.msg);
				mostrarModalError();
				$('#prev-add-edicions .modal-body').html(resposta.registresPagErrors);
				$('#prev-add-edicions').modal('show');

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
				// reloadUrl();
			}

			var textBotoPujada = "Puja";
			var textBotoNoPujada = "No pugis";
			$("#prev-add-edicions button.marcat").each(function() {
				$(this).html(textBotoPujada);
			});
			$("#prev-add-edicions button.no_marcat").each(function() {
				$(this).html(textBotoNoPujada);
			});

			$("#prev-add-edicions").on("click", ".marcat", function(e) {
				$(this).html(textBotoNoPujada);
				$(this).removeClass("marcat");
				$(this).addClass("no_marcat");
			});
			$("#prev-add-edicions").on("click", ".no_marcat", function(e) {
				$(this).html(textBotoPujada);
				$(this).removeClass("no_marcat");
				$(this).addClass("marcat");
			});


			$('#confirma-pujada-edicions').on('click', function() {

				var existeixAlgunCanvi = 0;
				var esPrimerInsert = 1;
				$('#modalInsertCurs .modal-body').html('');

				$('#prev-add-edicions button.marcat').each(function() {
					var idRes = $(this).attr('id').split('-')[3];

					var msg = "<p>S'ha creat el curs <strong>" + resultatsPujada[idRes]['nomCurs'] + "</strong>";
					msg += " amb codi GTAF <strong>" + resultatsPujada[idRes]['codiGtaf'] + "</strong>";
					msg += " del curs escolar <strong>" + resultatsPujada[idRes]['cursEscolar'] + "</strong></p>";

					existeixAlgunCanvi = 1;

					var insertCurs = $.ajax({
						url: path + "cursos/inserirCurs.php",
						method: "POST",
						data: {
							cursEscolar : resultatsPujada[idRes]['cursEscolar'],
							codiGtaf : resultatsPujada[idRes]['codiGtaf'],
							nomCurs : resultatsPujada[idRes]['nomCurs'],
							dataI : resultatsPujada[idRes]['dataI'],
							dataF : resultatsPujada[idRes]['dataF'],
							aula : resultatsPujada[idRes]['aula'],
							idAula : resultatsPujada[idRes]['idAula'],
							any : resultatsPujada[idRes]['any'],
							mes : resultatsPujada[idRes]['mes'],
							idCurs : resultatsPujada[idRes]['idCurs'],
							codiCurs : resultatsPujada[idRes]['codiCurs'],
							idPreu : resultatsPujada[idRes]['idPreu'],
							public : resultatsPujada[idRes]['public'],
							hores : resultatsPujada[idRes]['hores'],
							dataRes: dataResolucio
						},
						dataType: "html"
					});

					console.log("10");



					insertCurs.done(function( msgInsert ) {
						if ( msgInsert.toLowerCase().includes("error") ) {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora d'inserir el registre amb codi GTAF <strong>"+resultatsPujada[idRes]['codiGtaf']+"</strong> i curs escolar <strong>" + resultatsPujada[idRes]['cursEscolar'] + "</strong>");
							mostrarModalError();
						}
						else {
							if ( esPrimerInsert ) {
								$('#modalInsertCurs').modal('show');
								$('#modalInsertCurs .modal-body').html('');
							}
							esPrimerInsert = 0;
							$('#modalInsertCurs .modal-body').append(msg);
						}
					});

					insertCurs.fail(function( jqXHR, textStatus, errorThrown ) {
						rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error a l'inserir el registre amb codi GTAF <strong>"+resultatsPujada[idRes]['codiGtaf']+"</strong> i curs escolar <strong>" + resultatsPujada[idRes]['cursEscolar'] + "</strong> " );
					});

				});
				if ( !existeixAlgunCanvi ) {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("No has marcat cap canvi");
					mostrarModalError();
				}
				else {
					var insertNumTramit = $.ajax({
						url: path + "cursos/inserirNumTramit.php",
						method: "POST",
						data: {
							dataRes: dataResolucio,
							numTramit: numTramit
						},
						dataType: "html"
					});

					insertNumTramit.done(function( msgInsertTram ) {
						if ( msgInsertTram.toLowerCase().includes("error") ) {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora d'inserir el número de tràmit.");
							mostrarModalError();
						}
						else {
							$('#modalInsertCurs .modal-body').append("<p>S'ha generat correctament el tràmit.</p>");
						}
					});

					insertNumTramit.fail(function( jqXHR, textStatus, errorThrown ) {
						rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error a l'inserir el registre amb codi GTAF <strong>"+resultatsPujada[idRes]['codiGtaf']+"</strong> i curs escolar <strong>" + resultatsPujada[idRes]['cursEscolar'] + "</strong> " );
					});
				}


			});
		});
	}
	else {
	   mostrarModalNoTensPermisos();
	}
}

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

//Comprova si la data és correcta i envia una alerta en cas de no ser-ho
function dataCorrecte( valor ) {
	return esValid = dataEsValida(valor);
}
