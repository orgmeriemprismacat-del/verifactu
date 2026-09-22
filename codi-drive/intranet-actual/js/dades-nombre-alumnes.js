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

/* Mostrem el main */
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
			comprovarNombreAlumnes();
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function comprovarNombreAlumnes() {
	var any = $('#anys-dispo .element-selected').html().trim();
	var mes = $('#mesos-dispo .element-selected').html().trim();

	if (mes.toLowerCase().includes("tots"))
		mes = "tots";

	/* Consulta el numero d'alumnes */
	var requestMain = $.ajax({
		url: "https://intranet.prisma.cat/ajax/dades/comprovarNombreAlumnes.php",
		method: "GET",
		data: {
			any : any,
			mes : mes
		},
		dataType: "html"
	});
	requestMain.done(function( message ) {
		$('#resultats-cerca').html(message);
		$('#resultats-cerca').show();
	});

	requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error a la consulta del nombre d'alumnes: " );
	});

}
