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
			mostrarCursosPerAssignarTutor(anyCercat, mesCercat, 'codi', 1);
			mostrarCursosPropostaTutoritzacio(anyCercat, mesCercat, 'codi', 1);
		}
	});

	$('#cercar-cursos').click();

});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function mostrarCursosPerAssignarTutor(any, mes, orderBy, asc) {
	$('#resultats-cerca-assignar-tutors').off();
	$('#resultats-cerca-assignar-tutors .table-order').off();

	mostrarModalLoading();

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: path + "tutors/buscarCursosAssignarTutors.php",
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
		if ( res == '' )
			$('#resultats-cerca-assignar-tutors').addClass('d-none');
		else
			$('#resultats-cerca-assignar-tutors').removeClass('d-none');

		$('#resultats-cerca-assignar-tutors').html(res);
		$('#resultats-cerca-assignar-tutors').show();
		amagarLoadingModal();

		$('#resultats-cerca-assignar-tutors').on('click', '.set-tutor', function() {
			if ( idCuho == 17 || idCuho == 13 ) {
				afegirHeaderModalError("Oops...!");
				afegirTextModalError("Has de seleccionar un tutor/a per poder fer l'assignació! ");
				mostrarModalError();
			}
			else {
				var idCurs = $(this).attr('id').split('-')[2];
				var aula = $(this).attr('id').split('-')[3];
				var idCuho = $('#idCuho-' + idCurs + '-' + aula).html().trim();
				var codiCurs = $('#codiCurs-' + idCurs + '-' + aula).html().trim();
				/* assignar el tutor */
				assignarTutor(codiCurs, idCurs, aula, idCuho, any, mes);
			}

		});
		$('#resultats-cerca-assignar-tutors').on('click', '.add-tutor', function() {
			var idCurs = $(this).attr('id').split('-')[2];
			var aula = $(this).attr('id').split('-')[3];
			var idCuho = $('#idCuho-' + idCurs + '-' + aula).html().trim();
			var codiCurs = $('#codiCurs-' + idCurs + '-' + aula).html().trim();
			/* mostrar un modal per escollir un tutor */
			mostrarModalTriaTutor(codiCurs, idCurs, aula, idCuho, any, mes);
		});

		//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
		//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
		$('#resultats-cerca-assignar-tutors .table-order').on('click', '.sorting', function() {
				var id = $(this).attr('id').substr(3, $(this).attr('id').length);
				if ($(this).hasClass('asc'))
					mostrarCursosPerAssignarTutor(any, mes, id, 0);
				else
					mostrarCursosPerAssignarTutor(any, mes, id, 1);
			});
	});

	reqCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});
}
function mostrarCursosPropostaTutoritzacio(any, mes, orderBy, asc) {
	$('#resultats-cerca-proposta-tutoritzacio').off();
	$('#resultats-cerca-proposta-tutoritzacio .table-order').off();

	mostrarModalLoading();

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: path + "tutors/buscarCursosPropostaTutoritzacio.php",
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
			$('#resultats-cerca-proposta-tutoritzacio').html(res);
			$('#resultats-cerca-proposta-tutoritzacio').show();
			amagarLoadingModal();

			$('#resultats-cerca-proposta-tutoritzacio').on('click', '.canvi-tutor', function() {
				var idCurs = $(this).attr('id').split('-')[2];
				var aula = $(this).attr('id').split('-')[3];
				var codiCurs = $('#codiCurs-' + idCurs + '-' + aula).html().trim();
				var idCuho = $('#idCuho-' + idCurs + '-' + aula).html().trim();
				/* mostrar un modal per escollir un tutor */
				mostrarModalEscollirTutor(codiCurs, idCurs, aula, idCuho, any, mes);
			});

			$('#resultats-cerca-proposta-tutoritzacio').on('click', '.avisar-tutor', function() {
				if ( ( $(this).hasClass('lightRed') ) && $(this).html() == 'NO' ) {
					$(this).removeClass('lightRed');
					$(this).addClass('lightGreen');
					$(this).html('SÍ');
				}
				else if ( ( $(this).hasClass('lightGreen') ) && $(this).html() == 'SÍ' ) {
					$(this).removeClass('lightGreen');
					$(this).addClass('lightRed');
					$(this).html('NO');
				}
			});

			//Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
			//s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.
			$('#resultats-cerca-proposta-tutoritzacio .table-order').on('click', '.sorting', function() {
				var id = $(this).attr('id').substr(3, $(this).attr('id').length);
				if ($(this).hasClass('asc'))
					mostrarCursosPropostaTutoritzacio(any, mes, id, 0);
				else
					mostrarCursosPropostaTutoritzacio(any, mes, id, 1);
			});

			$('#resultats-cerca-proposta-tutoritzacio').on('click', '#guardar-canvis-envia', function() {
				var msgResposta = "";
				//
				$("#resultats-cerca-proposta-tutoritzacio label.avisar-tutor.lightGreen").each(function() {
					var idCurs = $(this).attr('id').split('-')[2];
					var aula = $(this).attr('id').split('-')[3];
					var idCuho = $('#idCuho-' + idCurs + '-' + aula).html().trim();
					var codiCurs = $('#codiCurs-' + idCurs + '-' + aula).html().trim();
					/* CHANGED Canviar GET per POST */
					var sendMsg = $.ajax({
						url: path + "tutors/enviarMsgPropostaTutoritzacio.php",
						method: "GET",
						data: {
							any: any,
							mes: mes,
							codiCurs : codiCurs,
							idCurs : idCurs,
							aula : aula,
							idCuho: idCuho
						},
						dataType: "html"
					});

					sendMsg.done(function( res ) {
						msgResposta +=  res;
					});

					sendMsg.fail(function( jqXHR, textStatus, errorThrown ) {
						rerrorFunction( jqXHR, textStatus, errorThrown,
							"Hi ha hagut algun error a l'hora d'enviar missatges de propsta de tutorització': " );
					});
				});

				afegirHeaderModalSuccess("Missatges enviats!");
				afegirTextModalSuccess(msgResposta);
				mostrarModalSuccess();
				// reloadUrl();
			});

			$('#resultats-cerca-proposta-tutoritzacio').on('click', '.infoCurs', function() {
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

/* Mostrar un modal per triar un tutor quan no hi ha cap assignat */
function mostrarModalTriaTutor(codiCurs, idCurs, aula, idCuho, any, mes) {
	console.log("Modal per escollir el tutor: " + idCurs + " " + aula + " " + idCuho);

	/* Mostrar el modal per escollir un tutor/a */
	mostrarModalLoading();
	var req = $.ajax({
		url: path + "tutors/mostrarModalTriaTutor.php",
		method: "GET",
		data: {
			any : any,
			mes : mes,
			codiCurs : codiCurs,
			idCurs : idCurs,
			aula : aula,
			idCuho: idCuho
		},
		dataType: "html"
	});

	req.done(function( res ) {
		$('#modalEscollirTutor .modal-body').html(res);
		$('#modalEscollirTutor').modal('show');

		$('#modalEscollirTutor').on('click', '.tutor-selecciona', function() {
			var dni = $(this).attr('id').split('-')[1];
			var idCuhoDni = $('#modalEscollirTutor #idCuho-' + dni).html().trim();
			var nomCognoms = $('#modalEscollirTutor #tutor-' + dni + " .tutor-name .name").html().trim();

			$('#tutor-' + idCurs + "-" + aula).html(nomCognoms);
			$('#idCuho-' + idCurs + "-" + aula).html(idCuhoDni);
			$('#modalEscollirTutor').modal('hide');
			assignarTutor(codiCurs, idCurs, aula, idCuhoDni, any, mes);

		});
	});

	req.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de mostrar el modal per escollir un tutor/a: " );
	});
}

/* Assignar un tutor */
function assignarTutor(codiCurs, idCurs, aula, idCuho, any, mes) {
	console.log("Assignar el tutor: " + idCurs + " " + aula + " " + idCuho);

	mostrarModalLoading();
	var req = $.ajax({
		url: path + "tutors/assignarTutorCurs.php",
		method: "GET",
		data: {
			any : any,
			mes : mes,
			codiCurs : codiCurs,
			idCurs : idCurs,
			aula : aula,
			idCuho: idCuho
		},
		dataType: "html"
	});

	req.done(function( res ) {
		afegirHeaderModalSuccess("Tutor assignat!");
		afegirTextModalSuccess(res);
		mostrarModalSuccess();
		reloadUrl();
	});

	req.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora d'assignar el tutor/a: " );
	});
}

/* Mostrar un modal per canviar un tutor  */
function mostrarModalEscollirTutor(codiCurs, idCurs, aula, idCuho, any, mes) {
	console.log("Modal per escollir el tutor: " + idCurs + " " + aula + " " + idCuho);

	/* Mostrar el modal per escollir un tutor/a */
	mostrarModalLoading();
	var req = $.ajax({
		url: path + "tutors/mostrarModalEscollirTutor.php",
		method: "GET",
		data: {
			any : any,
			mes : mes,
			codiCurs : codiCurs,
			idCurs : idCurs,
			aula : aula,
			idCuho: idCuho
		},
		dataType: "html"
	});

	req.done(function( res ) {
		$('#modalEscollirTutor .modal-body').html(res);
		$('#modalEscollirTutor').modal('show');

		$('#modalEscollirTutor').on('click', '.tutor-selecciona', function() {
			var dni = $(this).attr('id').split('-')[1];
			var idCuhoDni = $('#modalEscollirTutor #idCuho-' + dni).html().trim();
			var mailPersDni = $('#modalEscollirTutor #mailPers-' + dni).html().trim();
			var mailPrisDni = $('#modalEscollirTutor #mailPris-' + dni).html().trim();
			var nomCognoms = $('#modalEscollirTutor #tutor-' + dni + " .tutor-name .name").html().trim();

			$('#tutor-' + idCurs + "-" + aula).html(nomCognoms);
			$('#idCuho-' + idCurs + "-" + aula).html(idCuhoDni);
			$('#mailTut-' + idCurs + "-" + aula).html(mailPrisDni + "-" + mailPersDni);
			$('#modalEscollirTutor').modal('hide');
			assignarTutor(codiCurs, idCurs, aula, idCuhoDni, any, mes);
		});
	});

	req.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de mostrar el modal per escollir un tutor/a: " );
	});
}
