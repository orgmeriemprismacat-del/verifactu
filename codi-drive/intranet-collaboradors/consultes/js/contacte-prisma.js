/* Consulta el codi del main */
let mdlUsername = $('#mdl-user-username').html().trim();
let prioritatCons = '';
let typeCons = '';
let courseCons = '';
let deviceCons = '';
let systemCons = '';
let browserCons = '';
let marcatUrl = '';

var requestMain = $.ajax({
	url: "https://campus.prisma.cat/intranet-collaboradors/consultes/ajax/mostrarMain.php",
	method: "GET",
	data: {
		url : urlPagina,
		mdlUsername : mdlUsername
	},
	dataType: "html"
});

$(document).bind("ajaxSend", function(){
	mostrarModalLoading();
}).bind("ajaxComplete", function(){
	amagarLoadingModal();
});

/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel > #head-title').html(message);

		//Quan cliques .logout redirigim al campus my
		$('.breadcrump').on('click', '.logout', function() {
			mostrarModalLoadingInfo( 'Redireccionant...' );
			window.location.href = pathCampusMy;
		});

		choosePrio();
		chooseType()

		activarSelectCourseConsulta();

		activarSelects();

		restart();

		sendCons();

		var heightBread = $('.breadcrump').parent().outerHeight();
		$('#content-page').css('min-height', 'calc( 100% - ' + heightBread + 'px )');

});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

$(window).resize( resizeHeightBreadrcump );

/* Ajusta estil. Quan l'amplada de la pantala >= 991, el sidebar es
manté visible. Quan l'amplada de la pantalla < 991, el sidebar s'oculta */
function resizeHeightBreadrcump() {
		var heightBread = $('.breadcrump').parent().outerHeight();
		$('#content-page').css('min-height', 'calc( 100% - ' + heightBread + 'px )');
}

function restart() {
	$('#adds').addClass('hide');

	if ( typeCons == 'dept-info' ) { //Suport informàtic
		$('#adds').removeClass('hide');
		chooseDevice();
		chooseSystem();
		chooseBrowser();
	}
}

function activarSelects() {
	//quan es clica a qualsevol lloc fora del select, amago el desplegable
	$(window).click(function() {
		//amago el desplegable
		$('.select .select-list').hide();
		//retorno el triangle com esta per defecte
		var triangle = $('.select').find("i");
		triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
	});

	$(".select").click(function(e) {
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
}

function activarSelectCourseConsulta() {
	$('.curs-consulta').on('click', 'li', function(e) {
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
		console.log('id' + id);
		courseCons = id;
	});
}

function choosePrio() {
	$('#cnt-prioritat').on('click', '.prioritat', function(e) {
		$('#cnt-prioritat .prioritat').removeClass('active');
		$(this).addClass('active');
		prioritatCons = $(this).attr('id');
	});
}

function chooseType() {
	$('#cnt-tipusConsulta').on('click', '.people', function(e) {
		$('#cnt-tipusConsulta .people').removeClass('people-escollit');
		$(this).addClass('people-escollit');
		typeCons = $(this).attr('id');
		restart();
	});
	$('#cnt-tipusConsulta').on('click', '.people-selecciona', function(e) {
		$('#cnt-tipusConsulta .people').removeClass('people-escollit');
		$('#cnt-tipusConsulta .people').addClass('people-escollit');
		typeCons = $(this).attr('id');
		restart();
	});
}

function chooseDevice() {
	$('#cnt-devices').on('click', '.device', function(e) {
		$('#cnt-devices .device').removeClass('active');
		$(this).addClass('active');
		deviceCons = $(this).attr('id');
	});
}

function chooseSystem() {
	$('#cnt-systems').on('click', '.device', function(e) {
		$('#cnt-systems .device').removeClass('active');
		$(this).addClass('active');
		systemCons = $(this).attr('id');
	});
}

function chooseBrowser() {
	$('#cnt-browsers').on('click', '.device', function(e) {
		$('#cnt-browsers .device').removeClass('active');
		$(this).addClass('active');
		browserCons = $(this).attr('id');
	});
}

function sendCons() {
	$('#contacte').on('click', '#envia-consulta', function(e) {
		$('.alert').remove();
		$('.error').removeClass('error');

		//Camps obligatoris
		var prioritatConsulta = '';
		if ( $('.prioritat.active p')[0] ) prioritatConsulta = $('.prioritat.active p').html();
		var tipusConsulta = '';
		if ( $('.people-escollit .dept')[0] ) tipusConsulta = $('.people-escollit .dept').html().trim();
		var cursConsulta = '';
		if ( $('#curs-consulta')[0] ) cursConsulta = $('#curs-consulta .element-selected').html().trim();
		var url = $('#url').val().trim();
		var missatge = $('#missatge').val().trim();


		//Comprovacions de les dades que no siguin erronies
		errors = '';
		if ( !$('.prioritat.active p')[0] ) {
			errors += "<span>" + missatgeNoPotNoEstarSenseSeleccionar("PRIORITAT DE CONSULTA") + "</span>";
			$('#cnt-prioritat > p').addClass('error');
		}
		if ( !$('.people-escollit .dept')[0] ) {
		  errors += "<span>" + missatgeNoPotNoEstarSenseSeleccionar("TIPUS DE CONSULTA") + "</span>";
		  $('#cnt-tipusConsulta > p').addClass('error');
		}
		else {
			if ( typeCons == 'dept-info' ) { //incidència tècniques
				if ( !$('#cnt-devices .device.active')[0] ) {
					errors += "<span>" + missatgeNoPotNoEstarSenseSeleccionar("DISPOSITIU") + "</span>";
					$('#cnt-devices > p').addClass('error');
				}
				if ( !$('#cnt-systems .device.active')[0] ) {
					errors += "<span>" + missatgeNoPotNoEstarSenseSeleccionar("SISTEMA OPERATIU") + "</span>";
					$('#cnt-systems > p').addClass('error');
				}
				if ( !$('#cnt-browsers .device.active')[0] ) {
					errors += "<span>" + missatgeNoPotNoEstarSenseSeleccionar("NAVEGADOR") + "</span>";
					$('#cnt-browsers > p').addClass('error');
				}
			}
		}
		if ( campBuit(missatge) ) {
		  errors += "<span>" + missatgeNoPotEstarBuit("MISSATGE") + "</span>";
		  $('#missatge').addClass('error');
		}

		if ( errors == '' )
			enviarMsgDades( prioritatConsulta, tipusConsulta, cursConsulta, deviceCons, systemCons, browserCons, url, missatge );
		else {
			console.log(errors);
			var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
			msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
			msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
			msgError += "<i class='material-icons'>close</i></button>";
			msgError += errors + "</div>";

			$('#contacte .card-body').append(msgError);
		}

	});
}

function enviarMsgDades( prioritatConsulta, tipusConsulta, cursConsulta, deviceCons, systemCons, browserCons, url, missatge ) {
	var request = $.ajax({
		url: "https://campus.prisma.cat/intranet-collaboradors/consultes/ajax/enviarMsgConsulta.php",
		method: "POST",
		data: {
			mdlUsername: mdlUsername,
			priorCons: prioritatConsulta,
			typeCons: typeCons,
			courseCons: cursConsulta,
			deviceCons: deviceCons,
			systemCons: systemCons,
			browserCons: browserCons,
			urlCons: url,
			missatge: missatge
		},
		dataType: "html"
	});

	request.done(function( message ) {
		if ( message.includes("OK") ) { //Hi ha un error
			//Resposta s'afegeix a $('#dades .card-body')
			afegirHeaderModalSuccess("S'ha enviat la consulta/incidència correctament!");
			afegirTextModalSuccess( "En breu rebràs la resposta a la teva consulta.</p><p>Gràcies." );
			mostrarModalSuccess();
			$("#modalSuccess").on('hidden.bs.modal', function (e) {
				// reloadUrl();
			});
		}
		else {
			mostrarModalErrorMsgGeneral();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
	});
}
