let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain_v5.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

requestMain.done(function( message ) {
	$('.mainpanel').html(message);
	$('#calendari').on("click", ".check ", function(e) {
		mostrarModalLoading();
		var id = $(this).attr('id').split("-")[1];
		var msgsError = '';

		var upd = $.ajax({
			url: path + "developer/checkCalendar.php",
			method: "POST",
			data: {
				id : id
			},
			dataType: "html"
		});

		upd.done(function( res ) {
			if ( res.toLowerCase().includes("error") ) {
				msgsError += "<p>Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+id+"</strong></p>";
			}
			if ( msgsError != '' ) {
				afegirHeaderModalError("Alerta!");
				afegirTextModalError(msgsError);
				amagarLoadingModal();
				mostrarModalError();

				$('#modalErrors').on('click', '.btn-danger', function() {
					amagarModalError();
				});
				$('#modalErrors').on('click', '.close', function() {
					amagarModalError();
				});
			}
			else {
				afegirHeaderModalSuccess("Genial!");
				afegirTextModalSuccess("S'ha actualitzat perfectament!");
				amagarLoadingModal();
				mostrarModalSuccess();

				$('#modalSuccess').on('click', '.btn-success', function() {
					amagarModalSuccess();
					reloadUrl();
				});
				$('#modalSuccess').on('click', '.close', function() {
					amagarModalSuccess();
					reloadUrl();
				});
			}
		});

		upd.fail(function( jqXHR, textStatus, errorThrown ) {
			rerrorFunction( jqXHR, textStatus, errorThrown,
				"Hi ha hagut algun error a l'hora d'actualitzar les reclamacions': " );
		});
	});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
