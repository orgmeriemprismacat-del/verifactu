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
	$('#content-page').on('focus', '.form-control', function() {
		$(this).parent().removeClass('element-cercat-marcat');
	});

	//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
	$('#content-page').on('blur', '.form-control', function() {
		if ($(this).val().trim() == '')
			$(this).removeClass('element-cercat-marcat');
		else
			$(this).addClass('element-cercat-marcat');
	});

	$( "#genera-entitat" ).keyup(function(evObject){
		if (evObject.keyCode == 13)
			$('#afegeix-entitat').click();
	});

	$('#afegeix-entitat').on('click', function() {
		if ( tePermisEdicio ) {
			var cif = $('#cif-insert').val().trim();
			var rao = $('#rao-insert').val().trim();
			var adreca = $('#adreca-insert').val().trim();
			var cp = $('#cp-insert').val().trim();
			var poblacio = $('#poblacio-insert').val().trim();
			var nomResp = $('#nom-resp-insert').val().trim();
			var cognomResp = $('#cog-resp-insert').val().trim();
			var correuResp = $('#correu-resp-insert').val().trim();

			if ( cif == '' || rao == '' || adreca == '' || cp == '' || poblacio == '' ||
			nomResp == '' || cognomResp == '' || correuResp == ''	) {
				afegirHeaderModalError("Oops...!");
				afegirTextModalError("Has d'omplir tots els camps per afegir l'entitat");
				mostrarModalError();
			}
			else {
				var request = $.ajax({
					url: path + "alumnes/generaEntitats.php",
					method: "POST",
					data: {
						cif : cif,
						rao : rao,
						adreca : adreca,
						cp : cp,
						poblacio : poblacio,
						nomResp : nomResp,
						cognomResp : cognomResp,
						correuResp : correuResp
					},
					dataType: "html"
				});

				request.done(function( res ) {
					if ( !res.toLowerCase().includes("error") ) {
						afegirHeaderModalSuccess("Creada!");
						afegirTextModalSuccess("L'entitat s'ha creat correctament");
						mostrarModalSuccess();
						reloadUrl();
					}
					else {
						afegirHeaderModalError("Alerta!");
						afegirTextModalError("Hi ha hagut un error a l'hora de crear l'entitat");
						mostrarModalError();
						reloadUrl();
					}
				});

				request.fail(function( jqXHR, textStatus, errorThrown ) {
					rerrorFunction( jqXHR, textStatus, errorThrown,
						"Hi ha hagut algun error a l'hora de crear l'entitat: " );
				});
			}
		}
		else {
		   mostrarModalNoTensPermisos();
		}
	});

	$('#resultats-cerca').on('click', '.edita-entitat', function() {
		if ( tePermisEdicio ) {
			var idEntitat = $(this).attr('id').split('-')[1];
			var idResponsable = $(this).attr('id').split('-')[2];
			mostrarModalLoading();
			mostrarModalEditaEntitat(idEntitat, idResponsable);
			amagarLoadingModal();
		}
		else {
		   mostrarModalNoTensPermisos();
		}
	});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function mostrarModalEditaEntitat(idEntitat, idResponsable) {
	$('.modal-info').off();
	var request = $.ajax({
		url: path + "alumnes/mostrarModalEditaEntitat_Entitats.php",
		method: "GET",
		data: {
			idEntitat : idEntitat,
			idResponsable : idResponsable
		},
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			$("#modalEditaEntitat .modal-body").html(res);
			$("#modalEditaEntitat").modal('show');
			/*Si es clica el botó d'.save-result', es mostra el modal */
			$('#modalEditaEntitat').on('click', '.save-edicio', function() {
				var rao = $('#rao-upd').val();
				var cif = $('#cif-upd').val();
				var adreca = $('#adreca-upd').val();
				var cp = $('#cp-upd').val();
				var poblacio = $('#poblacio-upd').val();
				var nomResp = $('#nom-resp-upd').val().trim();
				var cognomResp = $('#cog-resp-upd').val().trim();
				var correuResp = $('#correu-resp-upd').val().trim();

				var requestActualitzar = $.ajax({
					url: path + "alumnes/actualitzaEditaEntitat.php",
					method: "GET",
					data: {
						idEntitat : idEntitat,
						rao : rao,
						cif : cif,
						adreca : adreca,
						cp : cp,
						poblacio : poblacio,
						nomResp : nomResp,
						cognomResp : cognomResp,
						correuResp : correuResp
					},
					dataType: "html"
				});

				requestActualitzar.done(function( res ) {
					$('#modalEditaEntitat').hide();
					if ( !res.toLowerCase().includes("error") ) {
						afegirHeaderModalSuccess("Actualitzada!");
						afegirTextModalSuccess("L'entitat s'ha actualitzat correctament");
						amagarLoadingModal();
						mostrarModalSuccess();
						reloadUrl();
					}
					else {
						afegirHeaderModalError("Alerta!");
						afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar l'entitat");
						amagarLoadingModal();
						mostrarModalError();
						reloadUrl();
					}
				});

				requestActualitzar.fail(function( jqXHR, textStatus, errorThrown ) {
					rerrorFunction( jqXHR, textStatus, errorThrown,
						"Hi ha hagut algun error a l'hora d'actualitzar l'entitat: " );
				});
			});

			/*Si es clica el botó d'.cancelar-apartat', s'amaga el modal */
			$('#modalEditaEntitat').on('click', '.cancelar-edicio', function() {
				$("#modalEditaEntitat").modal('hide');
			});
		}
		else {
			afegirHeaderModalError("Alerta!");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar el modal per editar l'entitat");
			mostrarModalError();
			reloadUrl();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de mostrar el modal per editar l'entitat: " );
	});
}
