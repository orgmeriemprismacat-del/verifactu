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
				"Hi ha hagut algun error a l'hora d'actualitzar els registres: " );
		});
	});

	$('#calendari').on("click", ".add-url ", function(e) {
		mostrarModalLoading();
		var id = $(this).attr('id').split("-")[1];
		var url = $('#url-' + id).val();
		var msgsError = '';

		var upd = $.ajax({
			url: path + "developer/addUrl.php",
			method: "POST",
			data: {
				id : id,
				url : url
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

	$('#modalAddElement').on('click', '.prio', function(e) {
		if ( $(this).hasClass('marcat') ) {
			$(this).removeClass('marcat');
			$(this).addClass('opacity-50');
		}
		else {
			$(this).addClass('marcat');
			$(this).removeClass('opacity-50');
		}
	});

	$('#modalAddElement').on('click', '.type', function(e) {
		if ( $(this).hasClass('marcat') ) {
			$(this).removeClass('marcat');
			$(this).addClass('opacity-50');
		}
		else {
			$(this).addClass('marcat');
			$(this).removeClass('opacity-50');
		}
	});

	$('#modalAddElement').on("click", ".btn-add", function(e) {
		mostrarModalLoading();
		var titol = $('#modalAddElement #titol').val();
		var descripcio = $('#modalAddElement #desc').val();
		var motiuPrio = $('#modalAddElement #motiuprio').val();
		var dataEstimada = $('#modalAddElement #data').val();

		var tipus = '';
		$('#modalAddElement .type.marcat').each(function( index ) {
			if ( tipus != '' ) tipus += '|';
			tipus += $(this).attr('id').split('-')[1];
		});

		var prio = '';
		$('#modalAddElement .prio.marcat').each(function( index ) {
			if ( prio != '' ) prio += '|';
			prio += $(this).attr('id').split('-')[1];
		});

		var upd = $.ajax({
			url: path + "developer/addElement.php",
			method: "POST",
			data: {
				titol : titol,
				descripcio : descripcio,
				motiuPrio : motiuPrio,
				dataEstimada : dataEstimada,
				tipus : tipus,
				prio : prio
			},
			dataType: "html"
		});

		upd.done(function( res ) {
			if ( res.toLowerCase().includes("error") ) {
				msgsError += "<p>Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+id+"</strong></p>";
			}
			if ( res != '' ) {
				bootstrap.Modal.getInstance(document.getElementById('modalAddElement')).hide();
				afegirHeaderModalError("Alerta!");
				afegirTextModalError(res);
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
				bootstrap.Modal.getInstance(document.getElementById('modalAddElement')).hide();
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
