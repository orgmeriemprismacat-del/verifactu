let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let estatsCursos = [];

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

	// $('#cursos').on('click', '.tipusInsc', function() {
	// 	$('.tipusInsc').removeClass('marcat');
	// 	$(this).addClass('marcat');
	// });

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

	mostrarModalLoading();

	/* TODO FUNCTION CLICK CANVIAR D'ESTAT AVÍS
		Mentre es cliqui sobre el botó d'Avís es canvii entre estat SÍ i NO
	*/
	$("#cursos").on("click", ".avisar-alumne", function(e) {
		if ( $(this).hasClass("lightGreen") ) {
			$(this).addClass('lightRed');
			$(this).removeClass('lightGreen');
			$(this).html('NO');
		}
		else if ( $(this).hasClass("lightRed") ) {
			$(this).addClass('lightGreen');
			$(this).removeClass('lightRed');
			$(this).html('SÍ');
		}
	});

	/* TODO FUNCTION CLICK SEND
		Quan es clica sobre el botó de enviar correus, s'enviarà un correu d'avís als alumnes marcats com avís SÍ
	*/
	$("#cursos").on("click", ".sendAvis", function(e) {
		var dataMaxim = $('#dateLastStart').val().trim();
		var hourDataMaxim = $('#hourLastStart .element-selected').html().trim();

		if ( dataMaxim != '' && hourDataMaxim != '' ) {

			var idSendAvis = $(this).attr('id').split("-");

			var codiCursActual = idSendAvis[1];
			var $anyCurs = idSendAvis[2];
			var $mesCurs = idSendAvis[3];

			var msgComu = '';
			var nElements = $("#cntCurs-" + codiCursActual + "-" + $anyCurs + "-" + $mesCurs + " .avisar-alumne.lightGreen").length;
			var nElementActual = 1;

			$("#cntCurs-" + codiCursActual + "-" + $anyCurs + "-" + $mesCurs + " .avisar-alumne").each(function() {
				if ( $(this).hasClass('lightGreen') ) { //Indica que sí
					var idInsc = $(this).attr('id').split('-')[1];

					var request = $.ajax({
						url: path + "alumnes/sendMsgAvisCursosPendentsTots.php",
						global: false,
						method: "GET",
						data: {
							idInsc: idInsc,
							dataMaxim: dataMaxim,
							hourDataMaxim: hourDataMaxim
						},
						dataType: "html"
					});
					request.done(function( msg ) {
						msgComu += "<p>" + msg + "</p>";

						if ( nElementActual == nElements ) {
							if ( !msgComu.toLowerCase().includes("error") ) {
								afegirHeaderModalSuccess("Missatges enviats!");
								afegirTextModalSuccess(msgComu);
								mostrarModalSuccess();
							}
							else {
								afegirHeaderModalError("Alerta!");
								afegirTextModalError("<p>Hi ha hagut un error a l'hora d'enviar el missatge d'avís!</p>"+msgComu);
								mostrarModalError();
							}
						}

						nElementActual++;
					});
					request.fail(function( jqXHR, textStatus, errorThrown ) {
						errorFunction( jqXHR, textStatus, errorThrown,
								"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
					});

				}
			});
		}
		else {
			var campError = '';
			if ( dataMaxim == '' ) {
				campError += "del últim dia";
			}
			if ( hourDataMaxim == '' ) {
				if ( campError != '' ) campError += ' i ';
				campError += "de la hora màxima";
			}
			afegirHeaderModalError("Oops...!");
			afegirTextModalError("Has d'omplir el camp " + campError + " per poder enviar els correus.");
			mostrarModalError();

		}
	});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
