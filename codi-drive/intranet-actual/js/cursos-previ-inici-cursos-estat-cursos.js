let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let estatsCursos = [];
let anyCercat = 0;
let mesCercat = '';

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

	// mostrarModalLoading();
	// /* Canviar GET per POST */
	// var cursosDontStarted = $.ajax({
	// 	url: path + "cursos/buscarCursosNoHanComencat.php",
	// 	method: "GET",
	// 	dataType: "html"
	// });
	//
	// cursosDontStarted.done(function( res ) {
	// 	resultat = res.split('|');
	// 	anyCercat = resultat[0];
	// 	mesCercat = resultat[1];
	//
	// 	$('#anys-dispo .element-selected').html(anyCercat);
	// 	$('#anys-dispo').prev().addClass('active');
	// 	$('#mesos-dispo .element-selected').html(mesCercat);
	// 	$('#mesos-dispo').prev().addClass('active');
	//
	//
	// 	$('#cercar-cursos').click();
	// });
	//
	// cursosDontStarted.fail(function( jqXHR, textStatus, errorThrown ) {
	// 	rerrorFunction( jqXHR, textStatus, errorThrown,
	// 		"Hi ha hagut algun error a l'hora de buscar la edició a cercar': " );
	// });

	$('#cursos').on('click', '#cercar-cursos', function() {
		anyCercat = $('#anys-dispo .element-selected').html().trim();
		mesCercat = $('#mesos-dispo .element-selected').html().trim();

		if ( anyCercat == '' && mesCercat == '' ) {
			afegirHeaderModalError("Oops...!");
			afegirTextModalError("Has d'omplir els camps de l'ANY i el MES per poder fer la cerca! ");
			mostrarModalError();
		}
		else {
			mostrarCursos(anyCercat, mesCercat, 'codi', 1);
		}
	});

	$('#cercar-cursos').click();
	

});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function mostrarCursos(any, mes, orderBy, asc) {
	$('#resultats-cerca').off();
	$('.table-order').off();

	mostrarModalLoading();

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: path + "cursos/buscarCursos.php",
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

			$("#resultats-cerca label.tipus").each(function() {
				var id = $(this).attr('id').split('-');
				var text = $(this).html().trim();
				estatsCursos[ id[1] + id[2] + id[3] ] = text;
			});

			$('#resultats-cerca').on('click', '.label', function() {
				var id = $(this).attr('id').split('-');
				if ( ( $(this).hasClass('lightGreen') || $(this).hasClass('lightGreen4') ) && $(this).html() == 'ACTIU' ) {
					if ( estatsCursos[ id[1] + id[2] + id[3] ] == "PENDENT" )
						$(this).addClass('lightOrange');
					else
						$(this).addClass('lightOrange2');

					$(this).removeClass('lightGreen');
					$(this).removeClass('lightGreen4');
					$(this).html('PENDENT');
				}
				else if ( ( $(this).hasClass('lightOrange') || $(this).hasClass('lightOrange2') ) && $(this).html() == 'PENDENT' ) {
					if ( estatsCursos[ id[1] + id[2] + id[3] ] == "ANUL·LAT" )
						$(this).addClass('lightRed');
					else
						$(this).addClass('lightRed3');

					$(this).removeClass('lightOrange');
					$(this).removeClass('lightOrange2');
					$(this).html('ANUL·LAT');
				}
				else if ( ( $(this).hasClass('lightRed') || $(this).hasClass('lightRed3') ) && $(this).html() == 'ANUL·LAT' ) {
					if ( estatsCursos[ id[1] + id[2] + id[3] ] == "ACTIU" )
						$(this).addClass('lightGreen');
					else
						$(this).addClass('lightGreen4');

					$(this).removeClass('lightRed');
					$(this).removeClass('lightRed3');
					$(this).html('ACTIU');
				}
			});

			//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
			//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
			$('.table-order').on('click', '.sorting', function() {
				var id = $(this).attr('id').substr(3, $(this).attr('id').length);
				if ($(this).hasClass('asc'))
					mostrarCursos(any, mes, id, 0);
				else
					mostrarCursos(any, mes, id, 1);
			});

			$('#resultats-cerca').on('click', '#guardar-canvis-envia', function() {
				if ( tePermisEdicio ) {
					var msgResposta = "";

					$("#resultats-cerca label.tipus").each(function() {
						var id = $(this).attr('id').split('-');
						var text = $(this).html().trim();

						if ( text != estatsCursos[ id[1] + id[2] + id[3] ] ) {
							console.log('text:' + text);
							console.log('id:' + id[1] + id[2] + id[3]);

							/* CHANGED Canviar GET per POST */
							var updCurs = $.ajax({
								url: path + "cursos/desarCanvisEstatEnviarMsg.php",
								method: "GET",
								data: {
									any : id[1],
									mes : id[2],
									curs: id[3],
									estatAnt: estatsCursos[ id[1] + id[2] + id[3] ],
									estat: text
								},
								dataType: "html"
							});

							updCurs.done(function( res ) {
								msgResposta +=  res;
							});

							updCurs.fail(function( jqXHR, textStatus, errorThrown ) {
								rerrorFunction( jqXHR, textStatus, errorThrown,
									"Hi ha hagut algun error a l'hora d'actualitzar el curs': " );
							});
						}
					});

					afegirHeaderModalSuccess("Actualitzat!");
					afegirTextModalSuccess(mostrarModalSuccess);
					mostrarModalSuccess();
					reloadUrl();
				}
				else {
				   mostrarModalNoTensPermisos();
				}
			});

			$('#resultats-cerca').on('click', '.infoCurs', function() {
				var codiCurs = $(this).attr('id').split('-')[2];
				var link = "https://intranet.prisma.cat/curs/mostrar-curs/#/"+anyCercat+codiCurs+mesCercat;
				window.open(link, '_blank');
			});
	});

	reqCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});
}
