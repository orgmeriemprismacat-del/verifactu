let mdlUsername = $('#mdl-user-username').html().trim();

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://campus.prisma.cat/intranet-collaboradors/cobraments/ajax/mostrarMain.php",
	method: "GET",
	data: {
		url : urlPagina,
		mdlUsername : mdlUsername
	},
	dataType: "html"
});

/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel > #head-title').html(message);

		//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
		//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
		$('#resultats-cerca .table-order').on('click', '.sorting', function() {
			var id = $(this).attr('id').substr(3, $(this).attr('id').length);
			if ($(this).hasClass('asc'))
				mostrarCursos('tots', id, 0);
			else
				mostrarCursos('tots', id, 1);
		});

		//quan es clica a qualsevol lloc fora del select, amago el desplegable
		$(window).click(function() {
			//amago el desplegable
			$('.select .select-list').hide();
			//retorno el triangle com esta per defecte
			var triangle = $('.select').find("i");
			triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
		});

		//quan estas focus en el camp, elimino el marcatge de l'input
		$('#cursos').on('focus', '.form-control', function() {
			$(this).parent().removeClass('element-cercat-marcat');
		});

		//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
		$('#cobraments').on('blur', '.form-control', function() {
			if ($(this).val().trim() == '')
				$(this).removeClass('element-cercat-marcat');
			else
				$(this).addClass('element-cercat-marcat');
		});

		$("#cobraments .select").click(function(e) {
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

		$("#cobraments .select").on("click", "li", function(e) {
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


		//Quan cliques .logout redirigim al campus my
		$('.breadcrump').on('click', '.logout', function() {
			mostrarModalLoadingInfo( 'Redireccionant...' );
			window.location.href = pathCampusMy;
		});

		var heightBread = $('.breadcrump').parent().outerHeight();
		$('#content-page').css('min-height', 'calc( 100% - ' + heightBread + 'px )');

		/* Si premo la tecla ENTER, es reprodueix l'event de clicar del cercar-factura*/
		$("#cobraments").keyup(function(evObject) {
			if (evObject.keyCode == 13) $('#cercar-factura').click();
		});

		$('#cobraments').on('click', '#cercar-cobraments', function() {
			anyCercat = $('#anys-dispo .element-selected').html().trim();
			mostrarCursos(anyCercat, 'any', 1);
		});

});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

$(window).resize( resizeHeightBreadrcump );

/* Ajusta estil. Quan l'amplada de la pantala >= 991, el sidebar es
manté visible. Quan l'amplada de la pantalla < 991, el sidebar s'oculta */
function resizeHeightBreadrcump() {
		var heightBread = $('.breadcrump').parent().outerHeight();
		$('#content-page').css('min-height', 'calc( 100% - ' + heightBread + 'px )');
}

function mostrarCursos(any, orderBy, asc) {
	mostrarModalLoading();
	$('#resultats-cerca').off();
	$('.table-order').off();

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: "https://campus.prisma.cat/intranet-collaboradors/cobraments/ajax/buscarCursosConsultaCobraments.php",
		method: "GET",
		data: {
			any : any,
			orderBy: orderBy,
			asc: asc,
			mdlUsername : mdlUsername
		},
		dataType: "html"
	});

	reqCursos.done(function( res ) {
			$('#resultats-cerca').html(res);
			$('#resultats-cerca').show();
			amagarLoadingModal();
			//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
			//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
			$('#resultats-cerca .table-order').on('click', '.sorting', function() {
				var id = $(this).attr('id').substr(3, $(this).attr('id').length);
				if ($(this).hasClass('asc'))
					mostrarCursos(any, id, 0);
				else
					mostrarCursos(any, id, 1);
			});
	});

	reqCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});

}
