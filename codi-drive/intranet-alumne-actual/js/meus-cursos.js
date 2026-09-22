var solicitaAccesAO = new Missatge(
	'Sol·licitud enviada!',
	"En 24/48 hores laborables podràs accedir a l'aula oberta."
)
var textCopiatOK = new Missatge(
	'',
	"Text copiat al porta-retalls."
)
var textCopiatWrong = new Missatge(
	'',
	"Text copiat al porta-retalls."
)
var textCopiatError = new Missatge(
	'',
	"Text copiat al porta-retalls."
)

let msgAp = [];
msgAp['solicitaAccesAO'] = solicitaAccesAO;
msgAp['textCopiatOK'] = textCopiatOK;
msgAp['textCopiatWrong'] = textCopiatWrong;
msgAp['textCopiatError'] = textCopiatError;

$(document).bind("ajaxSend", function(){
	mostrarModalLoading();
}).bind("ajaxComplete", function(){
	amagarLoadingModal();
});

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://campus.prisma.cat/intranet-alumnes/ajax/mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel').html(message);

		//Quan cliques .logout redirigim al campus my
		$('.breadcrump').on('click', '.logout', function() {
			mostrarModalLoadingInfo( 'Redireccionant...' );
			window.location.href = pathCampusMy;
		});

		// aplicarAccionsCursosPendents();
		// aplicarAccionsCursosAcabats();

		aplicarFuncioPay();
		aplicarFuncioMostrarModalDadesCurs();
		aplicarFuncioMostrarModalCompartirCurs();
		aplicarFuncioAulaOberta();

});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function aplicarFuncioPay() {
	$('#content-page').on('click', '.payInsc', function() {
		var idPagThis = $(this).attr('id').split('-')[1];
		var tipusInsc = $(this).attr('id').split('-')[2];

		//Request
		var request = $.ajax({
			url: path + "cursos/obtenirUrlPagament.php",
			method: "GET",
			data: {
				tipusInsc : tipusInsc,
				idPag : idPagThis
			},
			dataType: "html"
		});

		request.done(function( msg ) {
			if ( !msg.includes("error") ) {
				var link2 = "https://www.prisma.cat" + msg;
				window.open(link2, '_blank');
			}
			else {
				mostrarModalErrorMsgGeneral();
			}
		});

		request.fail(function( jqXHR, textStatus, errorThrown ) {
			mostrarModalErrorMsgGeneral();
		});

	});
}

function aplicarFuncioMostrarModalDadesCurs() {
	$('#content-page').on('click', '.info-curs', function() {
		var idThis = $(this).attr('id').split('-')[1];
		//Request
		var request = $.ajax({
			url: path + "cursos/mostrarInformacioDadesCurs.php",
			method: "GET",
			data: { id : idThis },
			dataType: "html"
		});

		request.done(function( msg ) {
			if ( !msg.includes("error") ) {
				$('#modalInfoDadesCurs .modal-body').html( msg );
				$('#modalInfoDadesCurs').modal('show');
			}
			else {
				mostrarModalErrorMsgGeneral();
			}
		});

		request.fail(function( jqXHR, textStatus, errorThrown ) {
			mostrarModalErrorMsgGeneral();
		});

	});
}

function aplicarFuncioMostrarModalCompartirCurs() {
	$('#content-page').on('click', '.compartir-curs', function() {
		var idThis = $(this).attr('id').split('-')[1];

		//Request
		var request = $.ajax({
			url: path + "cursos/mostrarInformacioCompartirCurs.php",
			method: "GET",
			data: { id : idThis },
			dataType: "html"
		});

		request.done(function( msg ) {
			if ( !msg.includes("error") ) {
				$('#modalInfoCompartirCurs .modal-body').html( msg );
				$('#modalInfoCompartirCurs').modal('show');
				aplicarFuncioCopiarUrl();
			}
			else {
				mostrarModalErrorMsgGeneral();
			}
		});

		request.fail(function( jqXHR, textStatus, errorThrown ) {
			mostrarModalErrorMsgGeneral();
		});

	});
}

function aplicarFuncioCopiarUrl() {
	$('.cnt-copy').on('click', '.copy', function() {
		console.log('x');
		var textCopiar = $('.cnt-copy > div').html().trim();

		var aux = document.createElement("input");
		aux.setAttribute("value", textCopiar);
		// S'afageix el nou camp a la pàgina
		document.body.appendChild(aux);
		// Se sel·lecciona el contingut del camp
		aux.select();
		try {
			var res = document.execCommand('copy'); //Intento copiar en el porta-retalls
			console.log(res);
			if (res) exit();
			else fracas();
		}
		catch(ex) {
			excepcio();
		}

		// Elimina el campo de la página
		document.body.removeChild(aux);

		function exit() {
			var errors = 'Text copiat al porta-retalls';

			var msgError = "<div class='alert alert-success alert-with-icon w-100 mb-2'>";
			msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
			msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
			msgError += "<i class='material-icons'>close</i></button>";
			msgError += msgAp['textCopiatOK'].getMsg() + "</div>";

			$('#modalInfoCompartirCurs .apartat').append(msgError);

			setTimeout(eliminaAlerta, 3000);
		}

		function fracas() {
			var errors = 'Ha fallat al porta-retalls';
			var msgError = "<div class='alert alert-warning alert-with-icon w-100 mb-2'>";
			msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
			msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
			msgError += "<i class='material-icons'>close</i></button>";
			msgError += msgAp['textCopiatWrong'].getMsg() + "</div>";

			$('#modalInfoCompartirCurs .apartat').append(msgError);
			setTimeout(eliminaAlerta, 3000);
		}

		function excepcio() {
			var errors = "S'ha produit un error al porta-retalls";
			var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
			msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
			msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
			msgError += "<i class='material-icons'>close</i></button>";
			msgError += msgAp['textCopiatError'].getMsg() + "</div>";

			$('#modalInfoCompartirCurs .apartat').append(msgError);
			setTimeout(eliminaAlerta, 3000);
		}

		function eliminaAlerta() {
			$('#modalInfoCompartirCurs .alert').fadeOut( 'slow', function() {
				$('#modalInfoCompartirCurs .alert').remove();
			});
		}
	});
}

function aplicarFuncioAulaOberta() {
	$('#cursosAcabats').on('click', '.accesAO', function() {
		var idInsc = $(this).attr('id').split('-')[1];

		if ( $(this).hasClass('acces') ) {
			goToAO(idInsc);
		}
		else if ( $(this).hasClass('demana-access') ) {
			mostrarModalAcceptaAccessAO(idInsc);
		}
	});
}

/* Buscar la id de l'aula oberta curresponent a la inscripció idInsc */
function goToAO( idInc ) {
	/*
		Busco els curs que correspon a la inscripció idInc
		Busco l'aula oberta que correspon al curs anterior en el campus nou.
		Si existeix, retorna la id del campus nou
	*/
	var request = $.ajax({
		url: path + "cursos/obtenirIdAulaOberta.php",
		method: "GET",
		data: { idInc : idInc },
		dataType: "html"
	});

	request.done(function( msg ) {
		if ( msg != 'no_existeix' ) {
			var link = "https://campus.prisma.cat/course/view.php?id=" + msg;
			console.log(link);
			window.open(link, '_blank');
		}
		else {
			/*
				Busco els curs que correspon a la inscripció idInc
				Busco l'aula oberta que correspon al curs anterior en el campus antic.
				Si existeix, retorna la id del campus antic
			*/
			var request = $.ajax({
				url: path + "cursos/obtenirIdAulaObertaCampusAntic.php",
				method: "GET",
				data: { idInc : idInc },
				dataType: "html"
			});

			request.done(function( msg2 ) {
				if ( msg2 != 'no_existeix' ) {
					var link = "https://www.prisma.cat/campus/course/view.php?id=" + msg2;
					console.log(link);
					window.open(link, '_blank');
				}
				else {
					mostrarModalErrorMsgGeneral();
				}
			});

			request.fail(function( jqXHR, textStatus, errorThrown ) {
				mostrarModalErrorMsgGeneral();
			});
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		mostrarModalErrorMsgGeneral();
	});
}

/*
	Busco els curs que correspon a la inscripció idInc
	Busco l'aula oberta que correspon al curs anterior en el campus nou.
	Si existeix, retorna la id del campus nou
*/
function obtenirIdAulaObertaCampusNou( idInc ) {
	console.log('obtenirIdAulaObertaCampusNou' + idInc);

	var request = $.ajax({
		url: path + "cursos/obtenirIdAulaOberta.php",
		method: "GET",
		data: { idInc : idInc },
		dataType: "html"
	});

	request.done(function( msg ) {
		console.log(msg);
		return msg;
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		return "error";
	});
}

/*
	Busco els curs que correspon a la inscripció idInc
	Busco l'aula oberta que correspon al curs anterior en el campus antic.
	Si existeix, retorna la id del campus antic
*/
function obtenirIdAulaObertaCampusAntic( idInc ) {

	var request = $.ajax({
		url: path + "cursos/obtenirIdAulaObertaCampusAntic.php",
		method: "GET",
		data: { idInc : idInc },
		dataType: "html"
	});

	request.done(function( msg ) {
		return msg;
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		return "error";
	});
}

function mostrarModalAcceptaAccessAO( idInc ) {
	var request = $.ajax({
		url: path + "cursos/mostrarInformacioSolicitudAulaOberta.php",
		method: "GET",
		data: { id : idInc },
		dataType: "html"
	});

	request.done(function( msg ) {
		if ( !msg.includes("error") ) {
			$('#modaConfirmaSolicitudAO .modal-body').html(msg);
			$('#modaConfirmaSolicitudAO').modal('show');

			$('#modaConfirmaSolicitudAO').on('click', '.consentiment', function() {
				if ( $(this).hasClass('success') ) {
					$(this).addClass('active');
					$('.consentiment.wrong').removeClass('active');
					$('.solicito-access-wrong').addClass('hide');
					$('.solicito-access-ok').removeClass('hide');
					$('.solicito-access-ok').addClass('show');
				}
				else {
					$(this).addClass('active');
					$('.consentiment.success').removeClass('active');
					$('.solicito-access-ok').addClass('hide');
					$('.solicito-access-wrong').removeClass('hide');
					$('.solicito-access-wrong').addClass('show');
				}
			});

			$('#modaConfirmaSolicitudAO').on('click', '.solicito-access-ao', function() {
				var idInsc1 = $(this).attr('id').split('-')[1];
				solicitaAccesAulaOberta( idInsc1 );
			});
		}
		else {
			mostrarModalErrorMsgGeneral();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		mostrarModalErrorMsgGeneral();
	});
}

/* Solicita l'accés a l'aula oberta de la inscripció idInsc */
function solicitaAccesAulaOberta( idInc ) {
	$('#modaConfirmaSolicitudAO').modal('hide');
	var request = $.ajax({
		url: path + "cursos/solicitaAccesAulaOberta.php",
		method: "GET",
		data: { id : idInc },
		dataType: "html"
	});

	request.done(function( msg ) {
		if ( !msg.includes("error") ) {
			afegirHeaderModalSuccess( msgAp['solicitaAccesAO'].getHeader() );
			afegirTextModalSuccess( msgAp['solicitaAccesAO'].getMsg() );
			mostrarModalSuccess();
		}
		else {
			mostrarModalErrorMsgGeneral();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		mostrarModalErrorMsgGeneral();
	});
}
