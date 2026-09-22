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

		$('#resultats-cerca').on('click', '.btn-action', function() {
			var idForumPost = $(this).attr('id').split('_')[2];
			link = "https://campus.prisma.cat/mod/forum/discuss.php?d=" + idForumPost;
			window.open(link, '_blank');
		});

		$('#resultats-cerca').on('click', '.changeAct ', function() {
			if ( $(this).hasClass('lightGreen') ) {
				$(this).addClass('lightRed');
				$(this).removeClass('lightGreen');
				$(this).html('NO CANVIAR');
			}
			else {
				$(this).addClass('lightGreen');
				$(this).removeClass('lightRed');
				$(this).html('CANVIAR');
			}
		});


		$('#resultats-cerca').on('click', '#canviarForumsPreg', function() {
			canviarForumsPreg();
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});


function canviarForumsPreg() {
	mostrarModalLoading();
	var nElements = $("#resultats-cerca .changeAct.lightGreen").length;
	var nElementActual = 1;
	var nElementSendOK = 0;

	if ( nElements <= 0 ) {
		afegirHeaderModalError("Oops...!");
		afegirTextModalError("Has de marcar com a mínim a una pregunta! ");
		mostrarModalError();
	}
	else {
		$("#resultats-cerca .changeAct.lightGreen").each(function() {
			var idCurs = $(this).attr('id').split('-')[2];
			var idForum = $(this).attr('id').split('-')[1];
			var idsError = "";
			var msgMostrar = "";
			var request = $.ajax({
				url: path + "cursos/updateCanvisForumsPreguntesRespostes.php",
				global: false,
				method: "GET",
				data: {
					idCurs: idCurs,
					idForum: idForum
				},
				dataType: "html"
			});
			request.done(function( msg ) {
				if ( !msg.toLowerCase().includes("error") ) {
					nElementSendOK++;
				}
				else {
					msgMostrar += msg;
				}

				if ( nElementActual == nElements ) {
					amagarLoadingModal();
					if ( nElementSendOK == nElements ) {
						afegirHeaderModalSuccess("Canvis aplicats!");
						afegirTextModalSuccess( );
						mostrarModalSuccess();
					}
					else {
						afegirHeaderModalError("Alerta!");
						afegirTextModalError("<p>Hi ha hagut un error a l'hora de canviar algun fòrum</p><p>Els que han donat problemes són: </p><ul>"+msgMostrar+"</ul>");
						mostrarModalError();
					}
				}
				nElementActual++;
			});
			request.fail(function( jqXHR, textStatus, errorThrown ) {
				errorFunction( jqXHR, textStatus, errorThrown,
						"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
			});
		});
	}
}
