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
	url: path + "mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

requestMain.done(function( message ) {
	$('.mainpanel').html(message);


	/* TODO FUNCTION CLICK CANVIAR D'ESTAT AVÍS
		Mentre es cliqui sobre el botó d'Avís es canvii entre estat SÍ i NO
	*/
	$("#inscripcions").on("click", ".descompte-valid", function(e) {
		if ( $(this).hasClass("lightGreen") ) {
			$(this).addClass('lightRed');
			$(this).removeClass('lightGreen');
			$(this).html("NO <i class='material-icons mx-1' title='Descompte aplicable'>sentiment_very_dissatisfied</i>");
		}
		else if ( $(this).hasClass("lightRed") ) {
			$(this).addClass('lightGreen');
			$(this).removeClass('lightRed');
			$(this).html("SÍ <i class='material-icons mx-1' title='Descompte aplicable'>sentiment_satisfied_alt</i>");
		}
	});
	/* TODO FUNCTION CLICK CANVIAR D'ESTAT AVÍS
		Mentre es cliqui sobre el botó d'Avís es canvii entre estat SÍ i NO
	*/
	$("#inscripcions_recent_titulat").on("click", ".resguard-valid", function(e) {
		if ( $(this).hasClass("lightGreen") ) {
			$(this).addClass('lightRed');
			$(this).removeClass('lightGreen');
			$(this).html("NO <i class='material-icons mx-1' title='Professor novell'>sentiment_very_dissatisfied</i>");
		}
		else if ( $(this).hasClass("lightRed") ) {
			$(this).addClass('lightGreen');
			$(this).removeClass('lightRed');
			$(this).html("SÍ <i class='material-icons mx-1' title='Professor no novell'>sentiment_satisfied_alt</i>");
		}
	});

	/* TODO FUNCTION CLICK SEND
		Quan es clica sobre el botó de enviar correus, s'enviarà un correu d'avís als alumnes marcats com avís SÍ
	*/
	$("#inscripcions").on("click", ".validat", function(e) {
		var idValidat = $(this).attr('id').split("-");
		var idInsc = idValidat[1];

		var valid = 1;
		if ( $('#validar-descompte-' + idInsc).hasClass('lightRed') ) valid = 0;

		var request = $.ajax({
			url: path + "alumnes/sendMsgValidatCurosDescomptes.php",
			global: false,
			method: "GET",
			data: {
				idInsc: idInsc,
				verificat: valid
			},
			dataType: "html"
		});
		request.done(function( msg ) {
			if ( !msg.toLowerCase().includes("error") ) {
				afegirHeaderModalSuccess("Missatge enviat!");
				afegirTextModalSuccess(msg);
				mostrarModalSuccess();
			}
			else {
				afegirHeaderModalError("Alerta!");
				afegirTextModalError("<p>Hi ha hagut un error a l'hora d'enviar el missatge de validació</p>" + msg);
				mostrarModalError();
			}
		});
		request.fail(function( jqXHR, textStatus, errorThrown ) {
			errorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
		});
	});
	/* TODO FUNCTION CLICK SEND
		Quan es clica sobre el botó de enviar correus, s'enviarà un correu d'avís als alumnes marcats com avís SÍ
	*/
	$("#inscripcions_recent_titulat").on("click", ".validatResguard", function(e) {
		var idValidat = $(this).attr('id').split("-");
		var idInsc = idValidat[1];

		var valid = 1;
		if ( $('#validar-resguard-' + idInsc).hasClass('lightRed') ) valid = 0;

		var request = $.ajax({
			url: path + "alumnes/sendMsgValidatProfessorNovell.php",
			global: false,
			method: "GET",
			data: {
				idInsc: idInsc,
				verificat: valid
			},
			dataType: "html"
		});
		request.done(function( msg ) {
			if ( !msg.toLowerCase().includes("error") ) {
				afegirHeaderModalSuccess("Canvi aplicat!");
				afegirTextModalSuccess(msg);
				mostrarModalSuccess();
			}
			else {
				afegirHeaderModalError("Alerta!");
				afegirTextModalError("<p>Hi ha hagut un error a l'hora d'enviar el missatge de validació</p>" + msg);
				mostrarModalError();
			}
		});
		request.fail(function( jqXHR, textStatus, errorThrown ) {
			errorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
		});
	});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
