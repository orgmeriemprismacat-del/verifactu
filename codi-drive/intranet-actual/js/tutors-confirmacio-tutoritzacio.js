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

	//Desplegable
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

	//Quan cliques ENTER, s'executa el botó de buscar
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
			mostrarCursosPerConfirmarTutoritzacio(anyCercat, mesCercat, 'codi', 1);
		}
	});

	$('#cercar-cursos').click();

});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function mostrarCursosPerConfirmarTutoritzacio(any, mes, orderBy, asc) {
	$('#resultats-cerca').off();
	$('#resultats-cerca .table-order').off();

	mostrarModalLoading();

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: path + "tutors/buscarCursosConfirmarTutoritzacio.php",
		method: "GET",
		data: {
			any : any,
			mes : mes,
			orderBy: orderBy,
			asc: asc
		},
		dataType: "html"
	});

	reqCursos.done(function( res ) {
		$('#resultats-cerca').html(res);
		$('#resultats-cerca').show();
		amagarLoadingModal();

		$('#resultats-cerca').on('click', '.confirma-tutor', function() {
			var idCurs = $(this).attr('id').split('-')[1];
			var aula = $(this).attr('id').split('-')[2];
			var codiCurs = $('#codiCurs-' + idCurs + '-' + aula).html().trim();
			// var idCuho = $('#idCuho-' + idCurs + '-' + aula).html().trim();
			// var dniTutor = $('#dniTutor-' + idCurs + '-' + aula).html().trim();
			// confirnaTutoritzacio(any, mes, codiCurs, aula, idCuho, dniTutor);
			confirmaTutoritzacio(any, mes, codiCurs, aula);
		});

		//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
		//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
		$('#resultats-cerca .table-order').on('click', '.sorting', function() {
				var id = $(this).attr('id').substr(3, $(this).attr('id').length);
				if ($(this).hasClass('asc'))
					mostrarCursosPerConfirmarTutoritzacio(any, mes, id, 0);
				else
					mostrarCursosPerConfirmarTutoritzacio(any, mes, id, 1);
			});
	});

	reqCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});
}

function confirmaTutoritzacio( any, mes, codiCurs, aula ) {
	var updConfCurs = $.ajax({
		url: path + "tutors/confirmarTutoritzacio.php",
		method: "POST",
		data: {
			any : any,
			mes : mes,
			codiCurs: codiCurs,
			aula: aula
		},
		dataType: "html"
	});

	updConfCurs.done(function( res ) {
		amagarLoadingModal();
		afegirHeaderModalSuccess("Tutor confirmat!");
		mostrarModalSuccess();
		var labelConf = "<label class='label d-flex justify-content-center align-items-center position-relative font-weight-bold px-2 py-2 lightGreen'>CONFIRMAT</label>";
		$('#confirma-' + any + codiCurs + mes + '-' + aula).parent().append(labelConf);
		$('#confirma-' + any + codiCurs + mes + '-' + aula).remove();
	});

	updConfCurs.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});
}
