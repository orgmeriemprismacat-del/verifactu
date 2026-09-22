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
	//mostrem el filtre de recordatoris
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
	$('#last-rec-claim').on('focus', '.form-control', function() {
		$(this).parent().removeClass('element-cercat-marcat');
	});

	$("#idFunciolast-rec-claim .select").click(function(e) {
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

	$("#idFunciolast-rec-claim .select").on("click", "li", function(e) {
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

	/* Si premo la tecla ENTER, es reprodueix l'event de clicar del cercar */
	$("#idFunciolast-rec-claim").keyup(function(evObject) {
		if (evObject.keyCode == 13) $('#cercar').click();
	});

	//si la url li indiquem dia i mes, filtrar automàticament

	//Quan clico el botó de cercar, reprodueix l'event de cercar
	$('#cercar').on('click', function() {
		cercarRecordatoris( any, mes, hores);
	})

	/* Consulta les baixes amb una ordenacio */

	activaFuncionsTaula();
	$("#baixes").on("click", "#upd-baixes", function(e) {
		confirmaReclamacio();
	});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

//Cerca les persones a les quals els hi hem de fer un recordatori de pagament de l'any any i del mes mes

function cercarRecordatoris(any, mes, hores) {
	//ENVIAR PETICIÓ CERCA
}

function activaOrderBy() {
	//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
	//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
	$('#baixes .table-order').on('click', '.sorting', function() {
		var id = $(this).attr('id').substr(3, $(this).attr('id').length);
		if ($(this).hasClass('asc'))
			vistaBaixesOrdenada(id, 0);
		else
			vistaBaixesOrdenada(id, 1);
	});
}

function activaButtonUpd() {
	$('#baixes').on('click', '.confirma-reclamacio', function() {
		var idInsc = $(this).attr('id').split('-')[1];
		confirmaReclamacio(idInsc);
	});
}

function activaMarcatge() {
	var textBoto = "SÍ <i class='material-icons mx-1' title='reclama'>sentiment_very_satisfied</i>";
	var textBotoNo = "NO <i class='material-icons mx-1' title='reclama'>sentiment_very_dissatisfied</i>";
	$('#baixes').on("click", ".marcat", function(e) {
		$(this).html(textBotoNo);
		$(this).addClass("no_marcat lightRed");
		$(this).removeClass("marcat lightGreen");
	});
	$('#baixes').on("click", ".no_marcat", function(e) {
		$(this).html(textBoto);
		$(this).addClass("marcat lightGreen");
		$(this).removeClass("no_marcat lightRed");
	});
}

function activaBotons() {
	$("#baixes").on("click", ".btn-action", function(e) {
		var id = $(this).attr('id').split('-');
		if (id[1] == "qual") {
		  if (id[0] == 'moodle')
			  link = "https://campus.prisma.cat/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];
		  else
			  link = "https://www.prisma.cat/campus/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];
	  }
	  else if (id[1] == "part") {
		  if (id[0] == 'moodle')
			  link = "https://campus.prisma.cat/report/outline/user.php?id=" + id[3] + "&course=" + id[2] + "&mode=complete";
		  else
			  link = "https://www.prisma.cat/campus/report/outline/user.php?id=" + id[3] + "&course=" + id[2] + "&mode=complete";
	  }
		window.open(link, '_blank');
	});
}

function activaBotoRecl() {
	$("#baixes").on("click", ".btn-reclamar", function(e) {
		mostrarModalLoading();
		var id = $(this).attr('id').split('-');
		var claim = $('#reclamar-'+id[1]).val();

		/* Guardar una reclamació */
		var req = $.ajax({
			url: path + "facturacio/saveMoneyClaim.php",
			method: "POST",
			data: {
				claim: claim,
				id: id[1]
			},
			dataType: "html"
		});

		req.done(function( res ) {
			amagarLoadingModal();
			afegirHeaderModalSuccess("Genial!");
			afegirTextModalSuccess("S'han actualitzat la reclamació sense problemes!");
			mostrarModalSuccess();
			$('#modalSuccess').on('click', '.btn-success', function() {
				amagarModalSuccess();
			});
			$('#modalSuccess').on('click', '.close', function() {
				amagarModalSuccess();
			});
		});

		req.fail(function( jqXHR, textStatus, errorThrown ) {
			rerrorFunction( jqXHR, textStatus, errorThrown,
				"Hi ha hagut algun error a l'hora d'actualitzar la reclamació: " );
		});
	});
}

function activaFuncionsTaula() {
	activaOrderBy();
	activaButtonUpd();
	activaMarcatge();
	activaBotons();
	activaBotoRecl();
}

function vistaBaixesOrdenada(orderBy, asc) {
	$('#baixes').off();
	$('#baixes .table-order').off();

	mostrarModalLoading();

	/* Consulta les baixes amb una ordenacio */
	var req = $.ajax({
		url: path + "facturacio/buscarRegistresBaixesSegonaSetmana.php",
		method: "GET",
		data: {
			orderBy: orderBy,
			asc: asc
		},
		dataType: "html"
	});

	req.done(function( res ) {
		amagarLoadingModal();
		$('#baixes .table').html(res);
		// $('#baixes').show();
		activaOrderBy();
		activaButtonUpd();
	});

	req.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de cercar les baixes: " );
	});
}

function confirmaRecordatori() {
	mostrarModalLoading();
	//Si no existeix algun element marcat
	if ( $('#baixes .marcat').length == 0 ) {
		afegirHeaderModalError("Alerta");
		afegirTextModalError("No has marcat cap reclamació");
		mostrarModalError();
	}
	else {
		$('#baixes .marcat').each(function(i,v) {
			var idButton = $(this).attr('id');
			var idInsc = idButton.split("-")[1];
			var motiuId = $('#motiu-'+idInsc).val();
			var msgsError = '';

			var upd = $.ajax({
				url: path + "facturacio/updDadesBaixesSegonaSetmana.php",
				method: "POST",
				data: {
					idInsc : idInsc,
					motiu : motiuId
				},
				dataType: "html"
			});

			upd.done(function( res ) {
				amagarLoadingModal();
				if ( res.toLowerCase().includes("error") ) {
					msgsError += "<p>Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idInsc+"</strong></p>";
				}

				if ($("#baixes .marcat").length-1 === i) {
					if ( msgsError != '' ) {
						afegirHeaderModalError("Alerta!");
						afegirTextModalError(msgsError);
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
						afegirTextModalSuccess("S'han enviat i actualitzat totes les baixes sense problemes!");
						mostrarModalSuccess();

						$('#modalSuccess').on('click', '.btn-success', function() {
							amagarModalSuccess();
						});
						$('#modalSuccess').on('click', '.close', function() {
							amagarModalSuccess();
						});

						$("#modalSuccess").on('hidden.bs.modal', function (e) {
							reloadUrl();
						})
					}
				}
			});

			upd.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut algun error a l'hora d'actualitzar les baixes': " );
			});

		});


	}
}
