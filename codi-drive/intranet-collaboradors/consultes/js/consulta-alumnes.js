let mdlUsername = $('#mdl-user-username').html().trim();
/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://campus.prisma.cat/intranet-collaboradors/consultes/ajax/mostrarMain.php",
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
		$('#cursos').on('blur', '.form-control', function() {
			if ($(this).val().trim() == '')
				$(this).removeClass('element-cercat-marcat');
			else
				$(this).addClass('element-cercat-marcat');
		});

		$("#cursos .select").click(function(e) {
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
		$("#cursos .select").on("click", "li", function(e) {
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

		$( "#cursos" ).keyup(function(evObject){
			if (evObject.keyCode == 13)
				$('#cercar-cursos').click();
		});

		$('#cursos').on('click', '#cercar-cursos', function() {
			anyCercat = $('#anys-dispo .element-selected').html().trim();
			mesCercat = $('#mesos-dispo .element-selected').html().trim();

			if ( anyCercat == '' && mesCercat == '' ) {
				afegirHeaderModalError("Oops...!");
				afegirTextModalError("Has d'omplir els camps de l'ANY i el MES per poder fer la cerca! ");
				mostrarModalError();
			}
			else {
				mostrarCursos(anyCercat, mesCercat);
			}
		});

		$('#cercar-cursos').click();

		//Quan cliques .logout redirigim al campus my
		$('.breadcrump').on('click', '.logout', function() {
			mostrarModalLoadingInfo( 'Redireccionant...' );
			window.location.href = pathCampusMy;
		});

		var heightBread = $('.breadcrump').parent().outerHeight();
		$('#content-page').css('min-height', 'calc( 100% - ' + heightBread + 'px )');

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

function mostrarCursos(any, mes) {
	$('#resultats-cerca').off();
	$('.table-order').off();

	mostrarModalLoading();

	var mdlUsername = $('#mdl-user-username').html().trim();

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: "https://campus.prisma.cat/intranet-collaboradors/cobraments/ajax/buscarAlumnesCurs.php",
		method: "GET",
		data: {
			any : any,
			mes : mes,
			username : mdlUsername
		},
		dataType: "html"
	});

	reqCursos.done(function( res ) {
			$('#resultats-cerca').html(res);
			$('#resultats-cerca').show();
			amagarLoadingModal();

			//Quan es clica per ordenar, si la columna clicada estÃ  ordenada ascendentment,
			//s'odrenarÃ  descententment, sino s'ordenarÃ  per la columna marcada asc.
			$('.table-order').on('click', '.sorting', function() {
				var id = $(this).attr('id').substr(3, $(this).attr('id').length);
				if ($(this).hasClass('asc'))
					mostrarCursos(any, mes);
				else
					mostrarCursos(any, mes);
			});

			$("#resultats-cerca").on("click", ".urlCurs", function() {
				//Obtinc si el moodle Ã©s l'antic o el nou i el id del curs del moodle
				var id = $(this).attr('id').split('-');

				//Obro una finestra nova amb l'enllaÃ§ al moodle
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

	reqCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});
}
