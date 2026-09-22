let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";

/* Cada vegada que es faci una crida d'un ajax, s'executarà la funció mostrarModalLoading().
Cada vegada que finalitza la crida d'un ajax, s'executarà la funció amagarLoadingModal(). */
// $(document).bind("ajaxSend", function(){
// 	mostrarModalLoading();
// }).bind("ajaxComplete", function(){
// 	amagarLoadingModal();
// });

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: path + "mostrarMain.php",
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
	$('#perfil').on('focus', '.form-control', function() {
		$(this).parent().removeClass('element-cercat-marcat');
	});
	//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
	$('#perfil').on('blur', '.form-control', function() {
		if ($(this).val().trim() == '')
			$(this).removeClass('element-cercat-marcat');
		else
			$(this).addClass('element-cercat-marcat');
	});

	$('#perfil').on('click', '.change-pass', function() {
		/* Modal per canviar la contrassenya */
		var request = $.ajax({
			url: path + "mostrarModalCanviContrassenya.php",
			method: "GET",
			dataType: "html"
		});

		request.done(function( res ) {
			$('#modalChangePass .modal-body').html(res);
			$('#modalChangePass').modal('show');

			$( "#modalChangePass" ).keyup(function(evObject){
				if (evObject.keyCode == 13)
					$('#confirma-canvi-contrassenya').click();
			});

			$('#modalChangePass').on('click', '#confirma-canvi-contrassenya', function() {
				var passAct = $('#pass-act-profile').val().trim();
				var passNew = $('#pass-new-profile').val().trim();
				var passConfNew = $('#conf-pass-new-profile').val().trim();

				console.log("pass act" + passAct);
				console.log("pass new" + passNew);
				console.log("pass conf new" + passConfNew);

				if ( passAct != '' && passNew != '' && passConfNew != '' ) {
					var request = $.ajax({
						url: path + "comprovarContrassenyesRealitzarCanviContrassenya.php",
						method: "GET",
						data: {
							passAct : passAct,
							passNew : passNew,
							passConfNew: passConfNew
						},
						dataType: "html"
					});

					request.done(function( res ) {
						if ( res != '' ) {
							$('#modalChangePass .modal-body .errors').html(res);
						}
						else {
							mostrarModalLoading();
							var updActualitzaPassword = $.ajax({
								url: path + "updatePasswordPerfil.php",
								method: "POST",
								data: {
									passNew : passNew
								},
								dataType: "html"
							});

							request.done(function( resUpd ) {
								amagarLoadingModal();

								$('#modalChangePass').modal('hide');
								if ( !resUpd.includes("Error") ) {
									afegirHeaderModalSuccess("Actualitzat!");
									afegirTextModalSuccess(msgResposta);
									mostrarModalSuccess();
								}
								else {
									afegirHeaderModalError("Oops...!");
									afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar la contrassenya! ");
									mostrarModalError();
								}
							});

							request.fail(function( jqXHR, textStatus, errorThrown ) {
								rerrorFunction( jqXHR, textStatus, errorThrown,
									"Hi ha hagut algun error a l'hora de mostrar el modal per fer el canvi de contrassenya: " );
							});
						}
					});

					request.fail(function( jqXHR, textStatus, errorThrown ) {
						rerrorFunction( jqXHR, textStatus, errorThrown,
							"Hi ha hagut algun error a l'hora de mostrar el modal per fer el canvi de contrassenya: " );
					});
				}
				else {
					$('#modalChangePass .modal-body .errors').html("<div class='alert alert-danger alert-with-icon w-100 mb-2'><i class='material-icons' data-notify='icon'>notifications</i><button type='button' data-dismiss='alert' aria-label='Close' class='close'><i class='material-icons'>close</i></button><span>Has d'omplir tots els camps per poder actualitzar la contrassenya!</span></div>");
				}
			});
		});

		request.fail(function( jqXHR, textStatus, errorThrown ) {
			rerrorFunction( jqXHR, textStatus, errorThrown,
				"Hi ha hagut algun error a l'hora de mostrar el modal per fer el canvi de contrassenya: " );
		});

	});



});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
