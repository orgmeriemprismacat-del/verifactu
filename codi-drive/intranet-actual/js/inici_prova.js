let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain_v5.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel').html(message);
		$('.card').each(function() {
			var id = $(this).attr('id');
			var idFuncio = id.split('_')[1];
			var idApartatFuncio = id.split('_')[2];

			var req = $.ajax({
				url: path + "inici/mostrarFuncionalitat.php",
				method: "GET",
				data: {
					idFuncio : idFuncio,
					idApartatFuncio : idApartatFuncio
				},
				dataType: "html"
			});
			req.done(function( res ) {
				$('#'+id+' .card-body > div').html(res);
				console.log('#'+id+' .card-body > div');
				console.log(res);
				aplicarFuncio( idFuncio );
			});
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function aplicarFuncio( idFuncio ) {
	if ( idFuncio == 2 ) {
		$('#content-page').on('click', '.bloquejar-cursos', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 3 ) {
		$('#content-page').on('click', '.curs-superat', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 5 ) {
		$('#content-page').on('click', '.pujada-gtaf', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 6 ) {
		$('#content-page').on('click', '.pujada-aules-obertes', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 7 ) {
		$('#content-page').on('click', '.valorar-cursos-ense', function() {
			var idApartat = $(this).attr('id').split('-')[3];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 8 ) {
		$('#content-page').on('click', '.valorar-cursos-fiss', function() {
			var idApartat = $(this).attr('id').split('-')[3];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 9 ) {
		$('#content-page').on('click', '.pujada-inscripcions', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 10 ) {
		$('#content-page').on('click', '.cns-imatges-cursos-nous', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 11 ) {
		//slider
	}
	else if ( idFuncio == 13 ) {
		$('#content-page').on('click', '.duplicats-idpag', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 14 ) {
		$('#content-page').on('click', '.duplicats', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 15 ) {
		$('#content-page').on('click', '.valid-desc', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 16 ) {
		$('#content-page').on('click', '.canvi-pregresp', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
	}
	else if ( idFuncio == 17 ) {
		//reclamacio
	}
	else if ( idFuncio == 18 ) {
		//segones baixes
	}
	else if ( idFuncio == 19 ) {
		//last claim
	}
}

function obreApartat(idApartat) {
	var link = "https://intranet.prisma.cat";
	var reqUrl = $.ajax({
		url: path + "inici/buscarUrlIdApartatFuncio.php",
		method: "GET",
		data: {
			idApartat : idApartat
		},
		dataType: "html"
	});

	reqUrl.done(function( res ) {
		if (!res.includes("Error") && !res.includes("error")) {
			link = link + res;
			window.open(link, '_blank');
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de consultar la url de l'apartat");
			mostrarModalError();
		}
	});

	reqUrl.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
		"Hi ha hagut un error a l'hora de consultar la url de l'apartat: " );
	});
}
