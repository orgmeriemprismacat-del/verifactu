let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain_v5.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

requestMain.done(function( message ) {
	/* mostrem totes les persones que tenen un pagament pendent dels cursos
	que fa com a mínim tres dies que han acabat i que com a màxim fàci una setmana */
	$('.mainpanel').html(message);

	activaFuncionsTaula();
	$("#recordatori").on("click", "#upd-baixes", function(e) {
		confirmaReclamacio();
	});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function activaOrderBy() {
	//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
	//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
	$('#recordatori .table-order').on('click', '.sorting', function() {
		var id = $(this).attr('id').substr(3, $(this).attr('id').length);
		if ($(this).hasClass('asc'))
			vistaRecordatoriOrdenada(id, 0);
		else
			vistaRecordatoriOrdenada(id, 1);
	});
}

function activaButtonUpd() {
	$('#recordatori').on('click', '#confirma-reclamacio', function() {
		var idInsc = $(this).attr('id').split('-')[1];
		confirmaRecordatori(idInsc);
	});
}

function activaMarcatge() {
	var textBoto = "SÍ <i class='material-icons mx-1' title='reclama'>sentiment_very_satisfied</i>";
	var textBotoNo = "NO <i class='material-icons mx-1' title='reclama'>sentiment_very_dissatisfied</i>";
	$('#recordatori').on("click", ".marcat", function(e) {
		$(this).html(textBotoNo);
		$(this).addClass("no_marcat lightRed");
		$(this).removeClass("marcat lightGreen");
	});
	$('#recordatori').on("click", ".no_marcat", function(e) {
		$(this).html(textBoto);
		$(this).addClass("marcat lightGreen");
		$(this).removeClass("no_marcat lightRed");
	});
}

function activaFuncionsTaula() {
	activaOrderBy();
	activaButtonUpd();
	activaMarcatge();
}

function vistaRecordatoriOrdenada(orderBy, asc) {
	$('#recordatori').off();
	$('#recordatori .table-order').off();

	mostrarModalLoading();

	/* Consulta les baixes amb una ordenacio */
	var req = $.ajax({
		url: path + "facturacio/buscarRegistresRecordatorisPagament.php",
		method: "GET",
		data: {
			orderBy: orderBy,
			asc: asc
		},
		dataType: "html"
	});

	req.done(function( res ) {
		$('#recordatori .table').html(res);
		amagarLoadingModal();
		activaFuncionsTaula();
	});

	req.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de cercar les baixes: " );
	});
}

function confirmaRecordatori() {
	mostrarModalLoading();
	//Si no existeix algun element marcat
	if ( $('#recordatori .marcat').length == 0 ) {
		afegirHeaderModalError("Alerta");
		afegirTextModalError("No has marcat cap reclamació");
		mostrarModalError();
	}
	else {
		$('#recordatori .marcat').each(function(i,v) {
			var idButton = $(this).attr('id');
			var idInsc = idButton.split("-")[1];
			var msgsError = '';

			var upd = $.ajax({
				url: path + "facturacio/updDadesRecordatoriPagament.php",
				method: "POST",
				data: {
					idInsc : idInsc
				},
				dataType: "html"
			});

			upd.done(function( res ) {
				if ( res.toLowerCase().includes("error") ) {
					msgsError += "<p>Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idInsc+"</strong></p>";
				}

				if ($("#recordatori .marcat").length-1 === i) {
					if ( msgsError != '' ) {
						afegirHeaderModalError("Alerta!");
						afegirTextModalError(msgsError);
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
						afegirHeaderModalSuccess("Genial!");
						afegirTextModalSuccess("S'han enviat i actualitzat totes les reclamacions sense problemes!");
						amagarLoadingModal();
						mostrarModalSuccess();

						$('#modalSuccess').on('click', '.btn-success', function() {
							amagarModalSuccess();
							reloadUrl();
						});
						$('#modalSuccess').on('click', '.close', function() {
							amagarModalSuccess();
							reloadUrl();
						});
					}
				}
			});

			upd.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut algun error a l'hora d'actualitzar les reclamacions': " );
			});

		});


	}
}
