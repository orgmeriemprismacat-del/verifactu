let typeCons = '';
let reasonCons = '';
let courseCons = '';
let deviceCons = '';
let systemCons = '';
let browserCons = '';
let marcatUrl = '';

if ( urlPagina.split('/')[1] == 'alumnes' && urlPagina.split('/')[2] == 'contacte' && urlPagina.split('/')[3] != '') {
	marcatUrl = urlPagina.split('/')[3];
	urlPagina = "/" + urlPagina.split('/')[1] + "/" + urlPagina.split('/')[2];
	if ( marcatUrl == 'codi-verificacio') {
		typeCons = 'tipus-consulta-secretaria-consultes-generals';
		reasonCons = 'motiu-consulta-demanar-el-codi-de-verificacio';
	}
	if ( marcatUrl == 'consultes-generals') typeCons = 'tipus-consulta-secretaria-consultes-generals';
	if ( marcatUrl == 'queixes') typeCons = 'tipus-consulta-secretaria-atencio-de-queixes';
	if ( marcatUrl == 'suggeriments') typeCons = 'tipus-consulta-secretaria-suggeriments';
	if ( marcatUrl == 'incidencies') typeCons = 'tipus-consulta-suport-informatic-incidencies-tecniques';
	console.log(marcatUrl);
}

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: path + "mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

$(document).bind("ajaxSend", function(){
	mostrarModalLoading();
}).bind("ajaxComplete", function(){
	amagarLoadingModal();
});

/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel').html(message);

		//Quan cliques .logout redirigim al campus my
		$('.breadcrump').on('click', '.logout', function() {
			mostrarModalLoadingInfo( 'Redireccionant...' );
			window.location.href = pathCampusMy;
		});

		activarSelects();

		chooseTypeCons();

		if ( marcatUrl != '' ) {
			var textMarcatUrl = $("#"+typeCons).text();
			$('#tipus-consulta').prev().addClass('active');
			$('#tipus-consulta .element-selected').html( textMarcatUrl );
			if ( marcatUrl == 'codi-verificacio' ) {
				var textMarcatUrl2 = $("#"+reasonCons).text();
				$('#motiu-consulta').prev().addClass('active');
				$('#motiu-consulta .element-selected').html( textMarcatUrl2 );
				activarSelectMotiuConsulta();
			}
			restart();
		}

		sendCons();
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

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

function chooseTypeCons() {
	$('.tipus-consulta').on('click', 'li', function(e) {
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

		typeCons = id;
		console.log( $(this).html());
		restart();
	});
}

function restart() {
	$('#cnt-motiu').addClass('hide');
	$('#cnt-courses').addClass('hide');
	$('#cnt-devices').addClass('hide');
	$('#cnt-systems').addClass('hide');
	$('#cnt-browsers').addClass('hide');

	if ( typeCons == 'tipus-consulta-secretaria-consultes-generals' ) {  //Consulta general
		$('#cnt-motiu').removeClass('hide');
		$('#cnt-courses').removeClass('hide');
		activarSelectMotiuConsulta();
		activarSelectCourseConsulta();
	}
	else if ( typeCons == 'tipus-consulta-suport-informatic-incidencies-tecniques' ) { //Suport informàtic
		$('#cnt-courses').removeClass('hide');
		$('#cnt-devices').removeClass('hide');
		$('#cnt-systems').removeClass('hide');
		$('#cnt-browsers').removeClass('hide');
		activarSelectMotiuConsulta();
		activarSelectCourseConsulta();
		chooseDevice();
		chooseSystem();
		chooseBrowser();
	}
}

function activarSelectMotiuConsulta() {
	$('.motiu-consulta').on('click', 'li', function(e) {
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
		reasonCons = id;
		if ( reasonCons == 'motiu-consulta-altres' )
			$('#motiu-consulta-text-altres').parent().removeClass('hide');
		else
			$('#motiu-consulta-text-altres').parent().addClass('hide');
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
		var nom = $('#nom').val().trim();
		var cognoms = $('#cog').val().trim();
		var email = $('#email').val().trim();
		var tel = $('#tel').val().trim();
		var tipusConsulta = $('#tipus-consulta .element-selected').html().trim();
		var assumpte = $('#assumpte').val().trim();
		var missatge = $('#missatge').val().trim();
		var motiuConsulta = '';
		var motiuConsultaAltres = '';
		var cursConsulta = '';
		if ( typeCons == 'tipus-consulta-secretaria-consultes-generals' ) { //consultes generals
			motiuConsulta = $('#motiu-consulta .element-selected').html().trim();
			if ( reasonCons == 'motiu-consulta-altres' ) {
				motiuConsultaAltres = $('#motiu-consulta-text-altres').val().trim();
			}
			cursConsulta = $('#curs-consulta .element-selected').html().trim();
		}
		else if (  typeCons == 'tipus-consulta-suport-informatic-incidencies-tecniques' ) {
			cursConsulta = $('#curs-consulta .element-selected').html().trim();
		}
		//Comprovacions de les dades que no siguin erronies
		errors = '';
		if ( campBuit(nom) ) {
		  errors += "<span>" + missatgeNoPotEstarBuit("NOM") + "</span>";
		  $('#nom').addClass('error');
		}
		if ( campBuit(cognoms) ) {
		  errors += "<span>" + missatgeNoPotEstarBuit("COGNOMS") + "</span>";
		  $('#cog').addClass('error');
		}
		if ( campBuit(email) ) {
		  errors += "<span>" + missatgeNoPotEstarBuit("<em>E-MAIL</em>") + "</span>";
		  $('#email').addClass('error');
		}
		else if ( !campBuit(email) && correuAdvert() ) {
			errors += "<span>" + missatgeInscritNoValid("<em>E-MAIL</em>") + "</span>";
 		  $('#email').addClass('error');
		}
		if ( campBuit(tel) ) {
		  errors += "<span>" + missatgeNoPotEstarBuit("TELÈFON") + "</span>";
		  $('#tel').addClass('error');
		}
		if ( campBuit(tipusConsulta) ) {
		  errors += "<span>" + missatgeNoPotEstarBuit("TIPUS DE CONSULTA") + "</span>";
		  $('#tipus-consulta').addClass('error');
		}
		else {
			if ( typeCons == 'tipus-consulta-secretaria-consultes-generals' ) { //consultes generals
				if ( campBuit(motiuConsulta) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("MOTIU DE CONSULTA") + "</span>";
				  $('#motiu-consulta').addClass('error');
				}
				else {
					if ( reasonCons == 'motiu-consulta-altres' && campBuit(motiuConsultaAltres) ) {
					  errors += "<span>" + missatgeNoPotEstarBuit("MOTIU DE CONSULTA") + "</span>";
					  $('#motiu-consulta-altres').addClass('error');
					}
				}

				if ( campBuit(cursConsulta) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("CURS RELACIONAT") + "</span>";
				  $('#curs-consulta').addClass('error');
				}
			}
			else if ( typeCons == 'tipus-consulta-suport-informatic-incidencies-tecniques' ) { //incidència tècniques
				if ( campBuit(cursConsulta) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("CURS RELACIONAT") + "</span>";
				  $('#curs-consulta').addClass('error');
				}
				if ( !$('#cnt-devices .device.active')[0] ) {
					errors += "<span>" + missatgeNoPotEstarBuit("DISPOSITIU") + "</span>";
				}
				if ( !$('#cnt-systems .device.active')[0] ) {
					errors += "<span>" + missatgeNoPotEstarBuit("SISTEMA OPERATIU") + "</span>";
				}
				if ( !$('#cnt-browsers .device.active')[0] ) {
					errors += "<span>" + missatgeNoPotEstarBuit("NAVEGADOR") + "</span>";
				}
			}
		}
		if ( campBuit(assumpte) ) {
		  errors += "<span>" + missatgeNoPotEstarBuit("ASSUMPTE") + "</span>";
		  $('#assumpte').addClass('error');
		}
		if ( campBuit(missatge) ) {
		  errors += "<span>" + missatgeNoPotEstarBuit("MISSATGE") + "</span>";
		  $('#missatge').addClass('error');
		}

		if ( errors == '' )
			enviarMsgDades( nom, cognoms, email, tel, typeCons, tipusConsulta, motiuConsulta, motiuConsultaAltres, courseCons, deviceCons, systemCons, browserCons, assumpte, missatge );
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

function correuAdvert(inici) {
	var email = $('#email').val().trim();
	var correu = email.split("@")[1];
	var error = '';

	esCorreuValid = "hotmail.com|hotmail.es|hotmail.co.uk|outlook.com|outlook.es|live.com|live.es|msn.com|YAHOO.COM|YAHOO.ES|YAHOO.FR,Yahoo!|Hotmail|Outlook|Live|Msn";

	if ( esCorreuValid != '') { //(hotmail.es,hotmail.com,yahoo.es|Hotmail,Yahoo!)
		var vectEl = esCorreuValid.split(","),
			 vectTermAdvert = vectEl[0].split("|"),
			 vectNomTermAdvert = vectEl[1].split("|"),
			 correuActValid = true,
			 vc=0;
		 while (vc<vectTermAdvert.length && correuActValid) {
				if (vectTermAdvert[vc].toLowerCase() == correu.toLowerCase())
				correuActValid=false;
				else
				vc++;
			}

		if (!correuActValid) {
			var terminacions = '';
			for (var vc=0; vc<vectNomTermAdvert.length; vc++) {
				if (vc>0) {
					if (vc==vectNomTermAdvert.length-1)
						terminacions += ' i ';
					else
						terminacions += ', ';
				}
				terminacions += "<span><em>"+vectNomTermAdvert[vc]+"</em></span>";
			}
			var msgInscModal = "Per assegurar-nos que rebràs tota la informació del teu curs sense cap inconvenient, et demanem que <span class='font-weight-bold'>no utilitzis un correu dels comptes " + terminacions + "</span>.";
			if ( !$('#modalCorreuValid').hasClass('show') && !inici) {
				$("#modalCorreuValidBody").html(msgInscModal);
				$('#modalCorreuValid').modal('show');
			}
			error = "Correu electrònic no vàlid";
			mostrarError("correu_erroni", error);
		}
	}
	return error;
}

function enviarMsgDades ( nom, cognoms, email, tel, typeCons, tipusConsulta,
	motiuConsulta, motiuConsultaAltres, courseCons, deviceCons, systemCons, browserCons,
	assumpte, missatge ) {
	console.log('enviar msg');

	var request = $.ajax({
		url: path + "dades/enviarMsgConsulta.php",
		method: "GET",
		data: {
			nom: nom,
			cognoms: cognoms,
			email: email,
			telefon: tel,
			typeCons: typeCons,
			motiuConsulta: motiuConsulta,
			motiuConsultaAltres: motiuConsultaAltres,
			courseCons: courseCons,
			deviceCons: deviceCons,
			systemCons: systemCons,
			browserCons: browserCons,
			assumpte: assumpte,
			missatge: missatge
		},
		dataType: "html"
	});

	request.done(function( message ) {
		if ( !message.includes("Error") && !message.includes("error") && !message.toLowerCase().includes("no canvi")  ) { //Hi ha un error
			//Resposta s'afegeix a $('#dades .card-body')
			afegirHeaderModalSuccess("S'ha enviat la consulta correctament!");
			afegirTextModalSuccess( "En 24/48 hores laborables rebràs la resposta a la teva consulta.</p> <p>Gràcies." );
			mostrarModalSuccess();
			$("#modalSuccess").on('hidden.bs.modal', function (e) {
				reloadUrl();
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
