var marginTop, path = 'https://www.prisma.cat/';

function mostrarHeaderFooter() {
	var requestHeader = $.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/mostrar_header_2.php",
		method: "GET",
		dataType: "html"
	});

	var requestFooter = $.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/mostrar_footer_2.php",
		method: "GET",
		dataType: "html"
	});

	requestHeader.done(function(pagina) {
		$("header").html(pagina);
		$('.closebtn').css('display', 'none');
		if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent))
			$('.navbar-nav > li').addClass('mobile');
		else
			$('.navbar-nav > li').addClass('computer')

		$('.prisma-header').on('focus', '.form-control', function() {
			$(this).next().next().addClass('active');
		});
		$('.prisma-header').on('blur', '.form-control', function() {
			if ($(this).val() == '')
				$(this).next().next().removeClass('active');
		});
	});

	requestHeader.fail(function(jqXHR, textStatus, errorThrown) {
		errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Header: ");
	});

	requestFooter.done(function(pagina) {
		$("footer").html(pagina);

		adjustStyle();

		$(window).resize(function() {
			adjustStyle();
		});

		$('.form-footer').on('focus', '.form-control', function() {
			$('.form-footer label').hide();
		});
		$('.form-footer').on('blur', '.form-control', function() {
			if ($('#adreca-electronica').val() == '')
				$('.form-footer label').show();
		});

		function mostrarErrorButlleti(missatgeError) {
			$('#modalErrorBody').html(missatgeError);
			$('#modalError').modal('show')
		}

		function mostrarSuccessButlleti(missatgeSuccess) {
			$('#modalOkBody').html(missatgeSuccess);
			$('#modalOK').modal('show');
		}

		function validacioCorreuButlleti() {
			var email = $('#adreca-electronica').val();
			var error = "";
			if (email.length != 0) {
				var tfld_email = $.trim(email);
				var emailFilter = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
				var illegalChars = /[\(\)\<\>\,\;\:\\\"\[\]]/;
				if (!emailFilter.test(tfld_email)) {
					error = "El correu electr\u00F2nic no és v\u00E0lid";
				} else if (email.match(illegalChars)) {
					error = "El correu electr\u00F2nic té car\u0E0cters no permesos";
				}
			} else
				error = "Cal omplir el camp";
			return error;
		}

		$('.form-footer').on('click', '#news', function() {
			if (validacioCorreuButlleti() != '') {
				var missatgeError = "<p>Cal omplir el camp de l'adreça electrònica.</p>";
				mostrarErrorButlleti(missatgeError);
			} else {
				var correu = $('#adreca-electronica').val();
				var comprovaSpam = $("#butlletiSpam").val();

				var requestMailing = $.ajax({
					url: "https://www.prisma.cat/ajax/mailingNou.php",
					data: {
						correu: correu,
						comprova: comprovaSpam
					},
					method: "GET",
					dataType: "html"
				});

				requestMailing.done(function(message) {
					if (message == "ok") {
						var missatgeConsultaOK = "<p>T'has subscrit correctament al nostre butlletí electrònic.</p>";
						missatgeConsultaOK += "<p>En breu rebràs un missatge de confirmació en el correu ";
						missatgeConsultaOK += "<strong><span class='correu_consulta'>" + correu + "</span></strong>.</p>";
						missatgeConsultaOK += "<p>Si el missatge no arriba en 15 minuts, revisa la carpeta del correu brossa. ";
						missatgeConsultaOK += "<p>Gr&agrave;cies per confiar en PrisMa!</p>";
						mostrarSuccessButlleti(missatgeConsultaOK)
					} else {
						mostrarErrorButlleti(message)
					}
				});

				requestMailing.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
					errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error en el request del Mailing: ");
				});
			}
		});

		function checkAcceptCookies() {
			if (localStorage.getItem("acceptCookies") == 'true') {
				$('#cntCookies').hide();
			}
		}
		checkAcceptCookies();
	});

	requestFooter.fail(function(jqXHR, textStatus, errorThrown) {
		errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Footer: ");
	});
}

function acceptCookies() {
	localStorage.setItem("acceptCookies", "true");
	$('#cntCookies').slideUp();
	consentGrantedAdStorage();
	consentGrantedAdUserData();
	consentGrantedAdPersonalization();
	consentGrantedAnalyticsStorage();
}

mostrarHeaderFooter();

var urlPagina = window.location.pathname.split('?')[0];
if (urlPagina.substr(-1) == "/") urlPagina = urlPagina.substr(0, urlPagina.length - 1);
var dispositiu;
if (/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)) {
	dispositiu = "mobil";
	$('#boto-tancar').css('display', 'none');
	$('#top-menu').remove()
} else {
	dispositiu = "ordinador"
}

vectUrlPagina = urlPagina.split('/');
let urlPart2 = vectUrlPagina[2];
urlPagina = "/" + vectUrlPagina[1] + "/" + urlPart2;
if (vectUrlPagina[1] == 'inscripcions') tipus = 0;
else if (vectUrlPagina[1] == 'regala') tipus = 2;
else if (vectUrlPagina[1] == 'bescanvia') tipus = 3;

urlPagina = '/inscripcions/us-aplicacio-tic-educacio-recursos-estrategies-aula';
tipus = 0;

var codiCurs = '',
	documentacio = '',
	preuCurs = 0,
	preuInscripcio = 0,
	idPreu = 0,
	hores = 0,
	edicio = '0',
	any = 0,
	checkCarnet = 0,
	checkUSOC = 0,
	checkDiscapacitat = 0,
	checkFamNum = 0,
	checkFamMono = 0,
	codiPostal = '',
	tipusPreuAplicat = 0,
	promocionsTrobades = [],
	promocioATrobadaplicada = '',
	promocioAplicada = '';

if (typeof vectUrlPagina[3] !== "undefined") edicio = vectUrlPagina[3];
if (typeof vectUrlPagina[4] !== "undefined") any = vectUrlPagina[4];

function mostrarInscripcio() {
	var requestPage  = $.ajax({
		async: !0,
		url: path + "ajax/mostrar_inscripcio_prova_usoc.php",
		method: "GET",
		data: {
			url : urlPagina,
			tipus : tipus,
			dispositiu : dispositiu
		},
		dataType: "html"
	});

	requestPage.done(function( cursos ) {
		$("#cnt-inscripcio").html(cursos);

		buscarCodiCurs();

		//efecte focus inputs
		$('.form-dades').on('focus', '.form-control', function() {
			$(this).prev().addClass('active');
		});
		$('.form-dades').on('blur', '.form-control', function() {
			if ($(this).val().trim() == '')
				$(this).prev().removeClass('active');
		});

		$(".form-dades .select").click(function(e) {
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

		$('.form-dades').on('change', '#nom', function() {
			validarNom()
		});
		$('.form-dades').on('blur', '#nom', function() {
			validarNom()
		});
		$('.form-dades').on('focus', '#nom', function() {
			eliminarError('nom_cognom_erroni')
		});

		$('.form-dades').on('change', '#cog', function() {
			validarCognoms()
		});
		$('.form-dades').on('blur', '#cog', function() {
			validarCognoms()
		});
		$('.form-dades').on('focus', '#cog', function() {
			eliminarError('cognom_erroni')
		});

		mostrarDocumentacio('doc-dni');
		$(".form-dades #doc").on("click", "li", function(e) {
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
			mostrarDocumentacio(id);
		});

		$('.form-dades').on('change', '#telf', function() {
			validarTel()
		});
		$('.form-dades').on('blur', '#telf', function() {
			validarTel()
		});
		$('.form-dades').on('focus', '#telf', function() {
			eliminarError('telf_erroni')
		});

		$('.form-dades').on('change', '#email', function() {
			validarEmail(0)
		});
		$('.form-dades').on('blur', '#email', function() {
			validarEmail(0)
		});
		$('.form-dades').on('focus', '#email', function() {
			eliminarError('correu_erroni')
		});

		$('.form-dades').on('change', '#email_conf', function() {
			validarEmailConf(0)
		});
		$('.form-dades').on('blur', '#email_conf', function() {
			validarEmailConf(0)
		});
		$('.form-dades').on('focus', '#email_conf', function() {
			eliminarError('correu_conf_erroni')
		});

		$('.form-dades').on('change', '#adreca', function() {
			validarAdreca()
		});
		$('.form-dades').on('blur', '#adreca', function() {
			validarAdreca()
		});
		$('.form-dades').on('focus', '#adreca', function() {
			eliminarError('adreca_erroni')
		});

		$('.form-dades').on('change', '#cp', function() {
			validarCP()
		});
		$('.form-dades').on('blur', '#cp', function() {
			mostrarLlistatPoblacions();
			validarCP()
		});
		$('.form-dades').on('focus', '#cp', function() {
			eliminarError('cp_erroni')
		});

		$('.form-dades').on('change', '#poble', function() {
			validarPoble()
		});
		$('.form-dades').on('blur', '#poble', function() {
			validarPoble()
		});
		$('.form-dades').on('focus', '#poble', function() {
			eliminarError('poble_erroni')
		});

		$(".form-dades #perfil").on("click", "li", function(e) {
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
			mostrarPerfil(id);
			validarPerfils();
		});
		$(".form-dades #titulacio").on("click", "li", function(e) {
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
			console.log('titulacio' + id);
			mostrarTitulacions(id);
			validarTitulacio();
		});

		$('.form-dades').on('click', ':checkbox', function() {
			$('.checksNoAccum').attr('disabled', false);
			$('#cnt_input_carnet').addClass('d-none');
			$('.desabilitat').removeClass('desabilitat');
			checkCarnet = 0; checkUSOC = 0; checkDiscapacitat = 0; checkDiscapacitat = 0; checkFamMono = 0;
			if (
				(
					$(this).attr('id') == 'carnetjove' ||
					$(this).attr('id') == 'usoc' ||
					$(this).attr('id') == 'discapacitat' ||
					$(this).attr('id') == 'familia_numerosa' ||
					$(this).attr('id') == 'familia_mono'
				)
			)
			{
				if ( this.checked ) {
					$('.checksNoAccum').attr('disabled', true);
					$('.checksNoAccum').next().addClass('desabilitat');
					$(this).attr('disabled', false);
					$(this).next().removeClass('desabilitat');
					if ($(this).attr('id') == 'carnetjove')
						checkCarnet = 1;
					else if ($(this).attr('id') == 'usoc')
						checkUSOC = 1;
					else if ($(this).attr('id') == 'discapacitat') {
						checkDiscapacitat = 1;
						$('#cnt_input_carnet').removeClass('d-none');
					}
					else if ($(this).attr('id') == 'familia_numerosa') {
						checkFamNum = 1;
						$('#cnt_input_carnet').removeClass('d-none');
					}
					else if ($(this).attr('id') == 'familia_mono') {
						checkFamMono = 1;
						$('#cnt_input_carnet').removeClass('d-none');
					}
				}
			}
			calcularPreu(1);
		});

		$(".form-dades #dates").on("click", "li", function(e) {
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
			var arrayDates = id.split('-');
			any = arrayDates[1];
			edicio = arrayDates[2];
			// mostrarDates(id);
			mostrarDates(1);
		});

		enviament_publi('0');

		$('.form-dades').on('change', '#promo', function() {
			$('#text-promo-info').html('');
			eliminarError('promo_erroni')
			if ( $(this).val() != '' )
				calcularPreu(2);
		});
		$('.form-dades').on('blur', '#promo', function() {
			$('#text-promo-info').html('');
			eliminarError('promo_erroni')
			if ( $(this).val() != '' )
				calcularPreu(2);
		});
		$('.form-dades').on('focus', '#promo', function() {
			$('#text-promo-info').html('');
			eliminarError('promo_erroni')
		});

		$(".form-dades #form_enviar_dades").click(function(e) {
			var comprovacio = '',
				validDoc = '',
				validNom = validarNom(),
				validCognom = validarCognoms(),
				validTel = validarTel(),
				validEmailNoBuit = validarEmail(1),
				validEmailConfNoBuit = validarEmailConf(1),
				validEmailCorreu = correuAdvert(1),
				validAdreca = validarAdreca(),
				validCP = validarCP(),
				validPoble = validarPoble(),
				validPerfil = validarPerfils(),
				validTitulacio = validarTitulacio(),
				validDates = validarDates(),
				validConegut = validarConegut(),
				validMailing = validarMailing();
				validFileCarnet = validarFileCarnet();

			if ($("#doc .element-selected").html().trim() == 'NIF/NIE')
				validDoc = validarNif();
			else
				validDoc = validarPass();


			if (validNom.length != 0) comprovacio += "<li>Nom</li>";
			if (validCognom.length != 0) comprovacio += "<li>Cognoms</li>";
			if ($("#doc .element-selected").html().trim() == 'NIF/NIE') {
				if (validDoc.length != 0) comprovacio += "<li>Dni sense lletra</li>";
			} else {
				if (validDoc.length != 0) comprovacio += "<li>Número de passaport</li>";
			}
			if (validTel.length != 0) comprovacio += "<li>Telèfon</li>";
			if (validEmailNoBuit.length != 0 || validEmailConfNoBuit.length != 0 || validEmailCorreu.length != 0 ) comprovacio += "<li>Correu electrònic</li>";
			if (validAdreca.length != 0) comprovacio += "<li>Adreça postal</li>";
			if (validCP.length != 0) comprovacio += "<li>Codi postal</li>";
			if (validPoble.length != 0) comprovacio += "<li>Població</li>";
			if (validPerfil.length != 0) comprovacio += "<li>Estic treballant a</li>";
			if (validTitulacio.length != 0) comprovacio += "<li>Tinc la titulació de</li>";
			if (validDates.length != 0) comprovacio += "<li>Les dates del curs</li>";
			if (validConegut.length != 0) comprovacio += "<li>Com has conegut el curs</li>";
			if (validMailing.length != 0) comprovacio += "<li>Newsletter</li>";
			if (validFileCarnet.length != 0) comprovacio += "<li>Fitxer carnet</li>";

			if (comprovacio != "") {
				var missatgeError = "<p>Els camps següents són incorrectes:</p><ul class='errors'>" + comprovacio + "</ul>";
				if ( preuInscripcio <= 0 ) missatgeError += "<p>Hi ha hagut un error al calcular el preu. Torna a carregar la pàgina per solucionar l'error.</p>";
				mostrarModalError(missatgeError);
			} else {
				$("#modalLoading").modal('show');
				comprovaSiHaRealitzatElCurs();
			}
		});
	});

	requestPage.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request de la pàgina: " );
	});
}
mostrarInscripcio();

var screenWitdh = parseInt($(this).width());

function buscarCodiCurs() {
	var request = $.ajax({
		url: path + "ajax/obtenirCodiCurs.php",
		data: {
			url: urlPagina
		},
		method: "GET",
		dataType: "html"
	});

	request.done(function(codi_curs) {
		codiCurs = codi_curs;
		buscarIdPreu();
		mostrarDates(0);
		mostraCodiPromo();
	});

	request.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de cercar el curs: ");
	});
}

function buscarIdPreu() {
	var request = $.ajax({
		url: path + "ajax/obtenirIdPreu.php",
		data: {
			codi: codiCurs
		},
		method: "GET",
		dataType: "html"
	});

	request.done(function(idPreuHores) {
		vectorPreuHores = idPreuHores.split("|");
		idPreu = parseInt(vectorPreuHores[0]);
		hores = parseInt(vectorPreuHores[1]);
		buscarPreuCar();
	});

	request.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de cercar el preu: ");
	});
}

function buscarPreuCar() {
	var request = $.ajax({
		url: path + "ajax/obtenirPreuCar.php",
		data: {
			idPreu: idPreu
		},
		method: "GET",
		dataType: "html"
	});

	request.done(function(preu) {
		preuCurs = parseFloat(preu);
		preuInscripcio = parseFloat(preu);
		mostrarPreu();
	});

	request.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de cercar el preu: ");
	});
}

//Comprovem si existeix el correu mail a la BD de mailing. Si no existeix, afegim el correu amb nom "nom", cognoms "cog" i correu "mail"
function enviament_publi(mail) {
	var request = $.ajax({
		url: path + "ajax/enviamentPubli.php",
		data: {
			mail: mail
		},
		method: "GET",
		dataType: "html"
	});

	request.done(function(message) {
		$("#txtHint_mailing").html(message);
		comHasConegut();
	});

	request.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de comprovar si existeix el mail: ");
	});
}

//Afegim opcions de «m'han conegut»: 1. Me l'han recomenat. 2. Ho he vist a les Xarxes socials (Facebook, Instagram, Twiter...). 3. Altres...
//Si s'ha registrat al butlletí electrònic, afegim una opció: 3. He rebut el butlletí electrònic
function comHasConegut() {
	var email = $("#valmail").val().trim();
	$('#listComConegut').hide();
	var request = $.ajax({
		url: path + "ajax/comHasConegutPrisma.php",
		data: {
			val: email
		},
		method: "GET",
		dataType: "html"
	});

	request.done(function(message) {
			$("#listComConegut").html(message);

			$(".form-dades #comConegut").on("click", "li", function(e) {
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
				console.log('conegut' + id);
				mostrarConegut(id);
				validarConegut();
			});

			$('.form-dades').on('change', '#com_conegut', function() {
				mostrarConegut();
				validarConegut()
			});
	});

	request.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de subscriure's al butlletí: ");
	});
}

function mostrarPreu() {
	$('#preu').html("<p>Preu: <span class='preu'><strong>" + preuCurs + " euros</strong></span></p>");
}

function mostrarDocumentacio(id) {
	var nomCamp = 'DNI amb lletra';
	var idInput = 'nif';
	var idError = 'dni_erroni';
	var input = "<div class='form-group field-wrap position-relative'>";
	input += "<label><span class='fons'></span><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
	input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "' maxlength='150'>";
	input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
	$('#input_doc').html(input);

	$('.form-dades').on('change', '#' + idInput, function() {
		validarNif();
		calcularPreu(1);
		documentacio = $('#nif').val().trim();
	});
	$('.form-dades').on('blur', '#' + idInput, function() {
		validarNif();
		calcularPreu(1);
		documentacio = $('#nif').val().trim();
	});
	$('.form-dades').on('focus', '#' + idInput, function() {
		eliminarError(idError)
	});

	if (id == 'doc-passaport') {
		var nomCamp = 'Número de passaport';
		var idInput = 'pass';
		var idError = 'dni_erroni';
		var input = "<div class='form-group field-wrap position-relative'>";
		input += "<label><span class='fons'></span><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
		input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "' maxlength='150'>";
		input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
		$('#input_doc').html(input);

		$('#' + idInput).focus();

		$('.form-dades').on('change', '#' + idInput, function() {
			validarPass();
			calcularPreu(1);
			documentacio = $('#pass').val().trim();
		});
		$('.form-dades').on('blur', '#' + idInput, function() {
			validarPass();
			calcularPreu(1);
			documentacio = $('#pass').val().trim();
		});
		$('.form-dades').on('focus', '#' + idInput, function() {
			eliminarError(idError)
		});
	}
}

function mostrarLlistatPoblacions() {
	if (validarCP().length == 0) {
		var cp = $("#cp").val().trim();
		$("#llistat_poblacions").show();

		if (codiPostal != cp) {
			$.ajax({
				url: path + "ajax/buscarPoblacio.php?cp=" + cp,
				cache: false,
				type: "GET",
				success: function(llistat) {
					$("#llistat_poblacions").html(llistat);
					codiPostal = cp;
					$("#llistat_poblacions").show();

					$(".form-dades #poble_box").on("click", "li", function(e) {
						var texto = $(this).text(),
							element = $(this).parent().prev(),
							lista = $(this).closest("ul"),
							triangle = $(this).parent().next(),
							id = $(this).attr('id');
						e.preventDefault();
						e.stopPropagation();
						// element.text(texto);
						lista.hide();
						console.log('poble_box' + id);
						var valorPoble = $('#' + id + " a").html().trim();
						console.log('valorPoble' + valorPoble);
						$('#poble').val(valorPoble);
						$('#poble_box label').addClass('active');
					});
				}
			});
		}
	}
}

function mostrarPerfil(id) {
	var dataExp = "<p class='mb-3'>Els nostres cursos compten com Formació Permanent del Professorat sempre que es realitzin posteriorment a la data d’expedició del títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).</p>";
	$('#perfils-altres').html('');
	//Si se selecciona Altres, apareix el input: Estic treballant a....
	if (id == 'perfil-altres') {
		var input = "<div class='form-group field-wrap position-relative'>";
		input += "<label><span class='fons'></span><span class='camp'>Estic treballant a...</span><span class='req'>*</span></label>";
		input += "<input type='text' class='form-control' id='perfil_de' name='perfil_de' maxlength='150'>";
		input += "<span id='perfil_altres_erroni'></span></div>";
		$('#perfils-altres').html(dataExp + input);

		$('.form-dades').on('change', '#perfil_de', function() {
			validarPerfils()
		});
		$('.form-dades').on('blur', '#perfil_de', function() {
			validarPerfils()
		});
		$('.form-dades').on('focus', '#perfil_de', function() {
			eliminarError("perfil_altres_erroni")
		});

		$('#perfil_de').focus();
	} else if (id == 'perfil-consultaPrivada' || id == 'perfil-noEsticTreballant') {
		$('#perfils-altres').html(dataExp);
	}
}

function mostrarTitulacions(id) {
	$('#titulacions-altres').html('');
	$('#titulacions-secundaria').html('');
	$('#titulacions-estudiant').html('');

	//Si se selecciona Altres, apareix el input: Tinc la titulació de....
	if (id == 'titulacio-altres') {
		var nomCamp = 'Tinc la titulació de';
		var idInput = 'titol_altres';
		var idError = 'titulacio_altres_erroni';
		var input = "<div class='form-group field-wrap position-relative'>";
		input += "<label><span class='fons'></span><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
		input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "' maxlength='150'>";
		input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
		$('#titulacions-altres').html(input);

		$('#' + idInput).focus();

		$('.form-dades').on('change', '#' + idInput, function() {
			validarTitulacio()
		});
		$('.form-dades').on('blur', '#' + idInput, function() {
			validarTitulacio()
		});
		$('.form-dades').on('focus', '#' + idInput, function() {
			eliminarError(idError)
		});
	}
	//Si se selecciona Ed. Secundària, apareix el input: Tinc l'especialitat de
	else if (id == 'titulacio-edSecundaria') {
		var nomCamp = 'La meva especialtat és';
		var idInput = 'titol_secundaria';
		var idError = 'titulacio_secundaria_erroni';
		var input = "<div class='form-group field-wrap position-relative'>";
		input += "<label><span class='fons'></span><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
		input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "' maxlength='150'>";
		input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
		$('#titulacions-secundaria').html(input);

		$('#' + idInput).focus();

		$('.form-dades').on('change', '#' + idInput, function() {
			validarTitulacio()
		});
		$('.form-dades').on('blur', '#' + idInput, function() {
			validarTitulacio()
		});
		$('.form-dades').on('focus', '#' + idInput, function() {
			eliminarError(idError)
		});
	}
	//Si se selecciona Estudiant, apareix el input: Sóc estudiant de
	else if (id == 'titulacio-estudiant') {
		var nomCamp = 'Sóc estudiant de';
		var idInput = 'titol_estudiant';
		var idError = 'titulacio_estudiant_erroni';
		var input = "<div class='form-group field-wrap position-relative'>";
		input += "<label><span class='fons'></span><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
		input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "' maxlength='150'>";
		input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
		$('#titulacions-estudiant').html(input);

		$('#' + idInput).focus();

		$('.form-dades').on('change', '#' + idInput, function() {
			validarTitulacio()
		});
		$('.form-dades').on('blur', '#' + idInput, function() {
			validarTitulacio()
		});
		$('.form-dades').on('focus', '#' + idInput, function() {
			eliminarError(idError)
		});
	}
}

//Si inici és 0, vol dir que és la primera vegada que es crida la funció, per tant, mostra la data
//de l'edició indicada a la url si n'hi ha.
//També calcula el preu de l'edició
function mostrarDates(inici) {
	if (edicio != '0' && any != 0 && inici == 0) {
		var text = $('#dates-' + any + '-' + edicio + ' a').html().trim();
		if (typeof text !== "undefined")
			$('#dates .element-selected').html(text)
		else {
			edicio = '0';
			any = 0;
		}
	}
	calcularPreu(0);
	edicioNoReconeguda();
	edicioSensePerfil();
}

function mostrarConegut(id) {
	$('#comHasConegut_altres').html('');

	if (id == 'conegut-altres') {
		var nomCamp = 'Com ens has conegut?';
		var idInput = 'c_altres';
		var idError = 'conegut_altres_erroni';
		var input = "<div class='form-group field-wrap position-relative'>";
		input += "<label><span class='fons'></span><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
		input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "' maxlength='150'>";
		input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
		$('#comHasConegut_altres').html(input);

		$('#' + idInput).focus();

		$('.form-dades').on('change', '#' + idInput, function() {
			validarConegut()
		});
		$('.form-dades').on('blur', '#' + idInput, function() {
			validarConegut()
		});
		$('.form-dades').on('focus', '#' + idInput, function() {
			eliminarError(idError)
		});
	}
}

//Si el curs actual té algun descompte associat i actiu de les trobades, apareix l'input
function mostraCodiPromo() {
	var exCodeProm = $.ajax({
		url: path + "ajax/obtenirCodisPromo.php",
		data: {
			codi: codiCurs
		},
		method: "GET",
		dataType: "html"
	});

	exCodeProm.done(function( msg ) {
		if ( !msg.toLowerCase().includes("error") && msg!='' ) {
			//Com a mínim hi ha un codi de promoció d'aquest curs actiu
			promocionsTrobades = msg.split(","); //Si existeix més d'un codi promocional actiu, se separan per ","

			var nomCamp = 'Codi de descompte';
			var idInput = 'codi-promo';
			var idError = 'codi_promo_erroni';
			var input = "<div class='form-group field-wrap position-relative'>";
			input += "<label><span class='fons'></span><span class='camp'>" + nomCamp + "</span></label>";
			input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "' maxlength='150'>";
			input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";

			var msgRecInsc = "<p>Si has rebut una targeta regal d'un curs, pots anar a la pàgina <a href='https://www.prisma.cat/bescanvia-regal' target='_self' title='Bescamvoa im regal que t'hagin fet de PrisMa'>bescanvia la targeta regal</a> per bescanviar-lo</p>";

			$('#cnt-codis-promocionals').html( input + msgRecInsc );

			$('.form-dades').on('change', '#' + idInput, function() {
				calcularPreu(2);
			});
			$('.form-dades').on('blur', '#' + idInput, function() {
				calcularPreu(2);
			});
			$('.form-dades').on('focus', '#' + idInput, function() {
				eliminarError(idError)
			});

		}
	});

	exCodeProm.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de buscar el codi de promoció ");
	});
}

//Busco el preu i miro si té algun descompte.
//Si desdeOnEstaConsultat és 0, vol dir que esta calculant preu quan sel·lecciona una edicio
//Si desdeOnEstaConsultat és 1, vol dir que esta calculant preu quan introdueixes el dni o el passaport
//Si desdeOnEstaConsultat és 2, vol dir que esta calculant preu quan introdueixes el codi de promoció
function calcularPreu( desdeOnEstaConsultat ) {
	//Si existeixen codis de promocio del curs actiu
	if ( promocionsTrobades.length > 0 ) {
		var codiPromoEntrat = $('#codi-promo').val().trim();

		if ( codiPromoEntrat == '' ) {
			calcularPreuSenseCodiPromo( desdeOnEstaConsultat );
		}
		else  {
			var trobat = false, i=0; promocioATrobadaplicada='';
			while ( i < promocionsTrobades.length && !trobat ) {
				var promo = promocionsTrobades[i].split('|');
				if ( codiPromoEntrat == promo[0] ) {
					trobat = true;
					promocioATrobadaplicada = promo[0] + '|' + promo[1] + '|' + promo[2];
				}
				else
					i++;
			}

			if ( !trobat ) {
				calcularPreuSenseCodiPromo( 2 );
				mostrarError("codi_promo_erroni", 'Codi de descompte erroni');
			}
			else if ( trobat && (edicio == '' || edicio != promo[2]) ) {
				calcularPreuSenseCodiPromo( 2 );
				mostrarError("codi_promo_erroni", 'No és vàlid per a aquesta edició');
				console.log(edicio != promo[2]);
			}
			else {
				/* Calcular el preu d'inscripció a partir del preu del curs i el
				percentatge del descompte
				*/

				var msgInsc = "se t'ha aplicat el codi de descompte " + promo[0];
				var msgModal = "<p>Se t'ha aplicat el " + promo[1] + "% de descompte en el preu del curs.</p>";

				preuInscripcio = parseFloat( preuCurs ) - ( (parseFloat( preuCurs )*parseFloat( promo[1] )) / 100 );

				var partEnteraInsc = parseInt(preuInscripcio);
				var partDecimalInsc = preuInscripcio - partEnteraInsc;
				var numberFinalInsc = preuInscripcio;

				if (partDecimalInsc > 0)
					numberFinalInsc = preuInscripcio.toFixed(2);

				missPreu = "<span class='preuTatxat'>" + preuCurs + " euros</span> ";
				missPreu += "<span class='preu'>" + numberFinalInsc + " euros</span> ";
				missPreu += "<span class='textMsgPreu'>(" + msgInsc + ")</span> ";

				mostraPreu(missPreu, msgModal, true);

			}
		}
	}
	else {
		if ( $('#promo').val() != '' )
		{
			var promo = $('#promo').val().trim();
			//Buscar les dades del codi promocional
			var requestDadesPromo  = $.ajax({
				async: !0,
				url: path + "ajax/obtenirDadesPromo.php",
				method: "GET",
				data: {
					promo : promo,
				},
				dataType: "html"
			});

			requestDadesPromo.done(function( dades_promo ) {
				dadesPromo = dades_promo;
				//Validacions pertinents
				if ( validacionsCodiPromocional(dadesPromo) )
					aplicarPreuCodiPromocions(dadesPromo);
			});

			requestDadesPromo.fail(function( jqXHR, textStatus, errorThrown ) {
				errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error: " );
			});
		}
		else
			calcularPreuSenseCodiPromo( desdeOnEstaConsultat );
	}

}

function validacionsCodiPromocional(dadesPromo) {
	console.log(dadesPromo);
	// Si promoExisteix == 1 es que el codi existeix, altrament no existeix
	var promoExisteix = dadesPromo.split('|')[0];
	if ( promoExisteix == "1" ) {
		// Si promoActiu == 1 es que el codi està actiu
		// Si promoActiu == 0 es que el codi no està actiu
		var promoActiu = dadesPromo.split('|')[1];

		// Si promoUsed == 0 es que el codi no ha estat utilitzat
		// Si promoUsed == 1 es que el codi ja ha estat utilitzat
		var promoUsed = dadesPromo.split('|')[2];

		// Si promoDni està buit es que el codi no ha d'estar vinculat a un DNI
		// Si promoDni no està buit es que el codi esta vinculat a un DNI i aquest serà promoDNI
		var promoDni = dadesPromo.split('|')[3];

		// Obtenim l'edició on es pot aplicar el codi promocional. Si és una en concret o a totes les edicions.
		// Si promoEdicio == 'TOTS', el codi promociona es pot aplicar a totes les edicions.
		// Altrament, el codi promocional només es pot aplicar a l'edicio $promoEdicio
		var promoEdicio = dadesPromo.split('|')[4];

		// Obtenim el codi del curs on es pot aplicar el codi promocional. Si és una en concret, a un nombre d'hores o a totes les edicions.
		// Si promoCurs == 'TOTS', el codi promocional es pot aplicar a totes els cursos.
		// Si promoCurs == a un nombre d'hores, llavors el codi promocional només es pot aplicar al cursos amb el mateix nombre d'hores
		// Altrament, el codi promocional només es pot aplicar al curs $promoCurs
		var promoCurs = dadesPromo.split('|')[5];
	}

	//Variable per controlar si la promo es variable
	var promoValida = 1;
	var msgCurtErroni = '';
	var msgAplicatPreu = '';
	var msgPromo = '';
	var msgPromoModal = '';

	if ( promoExisteix != "1" ) {  // Si promoExisteix != 1 es que el codi no existeix
		promoValida = 0;
		msgAplicatPreu = '';
		msgPromo = "<p class='text-danger font-weight-bold'>El codi promocional introduït no correspon a amb cap codi promocional actiu.<p>";
		msgPromoModal = '';
		msgCurtErroni = 'Codi promocional erroni';
	}
	else if ( promoActiu != "1" ) {  // Si promoActiu == 1 es que el codi no està actiu
		promoValida = 0;
		msgAplicatPreu = '';
		msgPromoModal = '';
		if ( promoActiu == "0" ) {  // Si promoActiu == 1 es que el codi no està actiu
		  msgPromo = "<p class='text-danger font-weight-bold'>El codi promocional està caducat!<p>";
		  msgCurtErroni = 'Codi promocional caducat';
	  }
		if ( promoActiu == "2" ) {  // Si promoActiu == 1 es que el codi no està actiu
		  msgPromo = "<p class='text-danger font-weight-bold'>El codi promocional encara no està actiu!<p>";
		  msgCurtErroni = 'Codi promocional no actiu';
	  }
	}
	else if ( promoUsed == "1" ) { // Si promoUsed == 1 es que el codi ja ha estat utilitzat
	   promoValida = 0;
	   msgAplicatPreu = '';
	   msgPromo = "<p class='text-danger font-weight-bold'>El codi promocional ja ha estat utilitzat!<p>";
	   msgPromoModal = '';
	   msgCurtErroni = 'Codi promocional utiltizat';
	}
	else {
		console.log('promoDNI' + promoDni);
		if ( promoDni != null && promoDni != '' ) {
			//el codi esta vinculat a un DNI i aquest serà promoDNI
			var dni_formulari = documentacio;
			console.log('dni_formulari' + dni_formulari);
			//Comprovar si el dni introduit a la inscripció correspon al del codi promocional
			if ( dni_formulari == '' ) {
				//No s'ha introduit el dni en el formulari
				promoValida = 0;
				msgPromo = "<p class='font-weight-bold'>Cal que introdueixs el DNI/NIE per verificar si tens disponible el codi promocional.</p>";
				msgAplicatPreu = 'no hem pogut verificar que el DNI/NIE estigui associat al codi promocional';
			}
			else if ( dni_formulari.toUpperCase() != promoDni.toUpperCase() ) {
				promoValida = 0;
				msgCurtErroni = 'No és vàlid per a aquest DNI/NIE';
				msgAplicatPreu = 'no hem pogut verificar que el DNI/NIE estigui associat al codi promocional';
				msgPromo = "<p class='font-weight-bold'>El codi promocional introduït no està associat al DNI/NIE introduït.</p>";
			}
		}
		if ( promoValida ) {
			//Comprovem si el codi promocional és per una única edició o per totes
			if ( promoEdicio != 'TOTS' ) {
				// Si promoEdicio != 'TOTS', el codi promocional només es pot aplicar a l'edicio $promoEdicio
				var edicio_formulari = edicio;
				if ( edicio_formulari == '0' ) {
					//No s'ha introduit el dni en el formulari
					promoValida = 0;
					msgPromo = "<p class='font-weight-bold'>Cal que triïs l'edició associada al codi promocional.</p>";
				}
				else if ( edicio_formulari != promoEdicio ) {
					promoValida = 0;
					msgCurtErroni = 'No és vàlid per a aquesta edició';
					msgPromo = "<p class='font-weight-bold'>El codi promocional introduït no correspon a cap descompte associat a l'edició marcada amb aquest curs.</p>";
				}
			}

		}
		if ( promoValida ) {
			//Comprovem si el codi promocional és per un únic curs, per un nombre d'hores o per tots els cursos
			if ( promoCurs != 'TOTS' ) {
				if ( promoCurs != '30' && promoCurs != '40' && promoCurs != '60' && promoCurs != '100' ) {
					var codiCurs_formulari = codiCurs;
					if ( codiCurs_formulari != promoCurs ) {
						promoValida = 0;
						msgCurtErroni = 'No és vàlid per a aquest curs';
						msgPromo = "<p class='font-weight-bold'>El codi promocional introduït no correspon a cap descompte associat amb aquest curs.</p>";
					}
				}
				else {
					var hores_formulari = hores;
					if ( hores_formulari != promoCurs ) {
						promoValida = 0;
						msgCurtErroni = 'No és vàlid per a aquest curs';
						msgPromo = "<p class='font-weight-bold'>El codi promocional introduït no correspon a cap descompte associat amb aquest curs.</p>";
					}
				}
			}
		}
	}


	if ( !promoValida ) {
		calcularPreuSenseCodiPromo( 2 );
		if ( msgCurtErroni != '' )
			mostrarError("promo_erroni", msgCurtErroni);
		if ( msgAplicatPreu != '' )
			$('#textMsgPreu').html(msgAplicatPreu);
		if ( msgPromo != '' )
			$('#text-promo-info').html(msgPromo);
		if ( msgPromoModal != '' ) {
			$("#modalBodyInfoPreu").html( msgPromoModal );
			$("#modalInfoPreu").modal('show');
		}
	}
	return promoValida;
}

function aplicarPreuCodiPromocions(dadesPromo) {
	//Obtenim el valor del percentatge de descompte que se li aplicarà amb el codi promocional.
	var promoDesc = dadesPromo.split('|')[6];
	//Obtenim el codi promocional.
	var promo = $('#promo').val().trim();

	var msgInsc = "se t'ha aplicat el descompte del codi promocional " + promo;
	var msgModal = "<p>Se t'ha aplicat el " + promoDesc + "% de descompte en el preu del curs corresponent al codi promocional " + promo + ".</p>";

	preuInscripcio = parseFloat( preuCurs ) - ( (parseFloat( preuCurs ) * parseFloat( promoDesc )) / 100 );
	promocioAplicada = promo + '|' + promoDesc;

	var partEnteraInsc = parseInt(preuInscripcio);
	var partDecimalInsc = preuInscripcio - partEnteraInsc;
	var numberFinalInsc = preuInscripcio;

	if (partDecimalInsc > 0)
		numberFinalInsc = preuInscripcio.toFixed(2);

	missPreu = "<span class='preuTatxat'>" + preuCurs + " euros</span> ";
	missPreu += "<span class='preu'>" + numberFinalInsc + " euros</span> ";
	missPreu += "<span class='textMsgPreu'>(" + msgInsc + ")</span> ";

	mostraPreu(missPreu, msgModal, true);
	// $('#carnetjove').attr("disabled", true);
	$('#text-promo-info').html('');
}


/* Busco el preu de la persona que es vol inscriure. Comprovo si la persona té algun descompte.
Si és així li aplico el descompte i, si desdeOnEstaConsultat == 1 li notifico mitjançant un modal i modificant
el text del preu.
Si no és així li aplico el preu original.*/
function calcularPreuSenseCodiPromo( desdeOnEstaConsultat ) {
	if ($('#dni_erroni').html().trim().length == 0 &&
		(
			($('#doc .element-selected').html().trim() == 'NIF/NIE' && $('#nif').val().trim() != '') ||
			($('#doc .element-selected').html().trim() != 'NIF/NIE' && $('#pass').val().trim() != '')
		)
	)
	{
		if ( desdeOnEstaConsultat == 1 ) {
			$("#modalLoading").modal('show');
		}

		var doc;
		if ($('#doc .element-selected').html().trim() == 'NIF/NIE' && $('#nif').val().trim() != '')
			doc = $('#nif').val().trim();
		else
			doc = $('#pass').val().trim();

		/*calculem el preu que se li ha d'aplicar a l'alumne. retorna tipus.

		Si el tipus és 0, vol dir que no se li pot aplicar cap descompte, preuInscripcio=preuCurs
		Si no ho és, busquem el MSG_INSC i el preu de l'últim descompte on TIPUS = tipus i ID_PREU = id_preu i estigui actiu que podem aplicar
		*/

		var searchPrice  = $.ajax({
			async: !0,
			url: path + "ajax/calcularPreu_prova_usoc.php",
			method: "GET",
			data: {
				codi : codiCurs,
				hores : hores,
				idPreu : idPreu,
				edicio : edicio,
				doc : doc,
				checkCarnet : checkCarnet,
				checkUSOC : checkUSOC,
				checkDiscapacitat : checkDiscapacitat,
				checkFamNum : checkFamNum,
				checkFamMono : checkFamMono
			},
			dataType: "html"
		});
		searchPrice.done(function( msg ) {
			var missPreu, classInsc = '',
				msgInsc = '',
				msgInscModal;
			vectorTipusMsgPreu = msg.split("|");
			tipusPreuAplicat = parseInt(vectorTipusMsgPreu[0]);
			missPreu = parseFloat(vectorTipusMsgPreu[1]);
			msgInsc = vectorTipusMsgPreu[2];
			msgInscModal = vectorTipusMsgPreu[3];
			if (msgInscModal == '0')
				msgInscModal = "No tens cap descompte disponible.";

			if (tipusPreuAplicat == 0) preuInscripcio = preuCurs;
			else preuInscripcio = parseFloat(vectorTipusMsgPreu[1]);

			if (preuCurs == preuInscripcio) {
				missPreu = "<span class='preu'>" + preuCurs + " euros</span> ";
				if (tipusPreuAplicat == 2) {
					classInsc = 'carnetWrong';
					missPreu += "<span class='textMsgPreu " + classInsc + "'>(" + msgInsc + ")</span> ";
				} else {

				}
				$("#carnetjove").removeAttr("disabled");
				$("#text_carnet_jove").removeClass("desabilitat");
				checkCarnet = 0;
			}
			else {
				if (tipusPreuAplicat == 1) {
					classInsc = 'textPreuPrisma';
					// $("#carnetjove").attr("disabled", true);
					// $("#text_carnet_jove").addClass("desabilitat");
					// $('#carnetjove').prop('checked', false);
					checkCarnet = 0;
				} else if (tipusPreuAplicat == 2) {
					classInsc = 'carnetOk';
					$("#carnetjove").removeAttr("disabled");
					$("#text_carnet_jove").removeClass("desabilitat");
					checkCarnet = 1;
				} else if (tipusPreuAplicat > 10 && tipusPreuAplicat<100) {
					classInsc = '';
					$("#carnetjove").attr("disabled", true);
					$("#text_carnet_jove").addClass("desabilitat");
					$('#carnetjove').prop('checked', false);
					checkCarnet = 0;
				} else if (tipusPreuAplicat == 0) {
					classInsc = '';
					$("#carnetjove").removeAttr("disabled");
					$("#text_carnet_jove").removeClass("desabilitat");
					checkCarnet = 1;
				}

				var partEnteraInsc = parseInt(preuInscripcio);
				var partDecimalInsc = preuInscripcio - partEnteraInsc;
				var numberFinalInsc = preuInscripcio;

				if (partDecimalInsc > 0)
					numberFinalInsc = preuInscripcio.toFixed(2);

				missPreu = "<span class='preuTatxat'>" + preuCurs + " euros</span> ";
				missPreu += "<span class='preu'>" + numberFinalInsc + " euros</span> ";
				missPreu += "<span class='textMsgPreu " + classInsc + "'>(" + msgInsc + ")</span> ";
			}

			if ( desdeOnEstaConsultat == 1 || desdeOnEstaConsultat == 2 ) {
				if ( preuInscripcio <= 0 ) {
					msgModal = "<p>Hi ha hagut un error al calcular el preu. Si us plau, torna a carregar la pàgina per solucionar el problema.</p>";
				}
				msgModal = "<p>" + msgInscModal + "</p>";
				if ( desdeOnEstaConsultat == 2 && $('#codi-promo')[0] && $('#codi-promo').val().trim() != '' ) {
					msgModal += "<p>El codi de descompte introduït no correspon a cap descompte associat a l'edició marcada amb aquest curs.</p>";
					msgModal += "<p>Si has rebut una targeta regal d'un curs, pots anar a la pàgina de <a href='https://www.prisma.cat/bescanvia-regal' target='_self'>bescanvia la teva targeta regal</a> per bescanviar-lo</p>";
				}
				if ( vectorTipusMsgPreu[3] != 0 ) {
					if ( desdeOnEstaConsultat == 2 && $('#codi-promo')[0] && $('#codi-promo').val().trim() == '' )
						mostraPreu(missPreu, msgModal, false);
					else
						mostraPreu(missPreu, msgModal, true);
				}
				else {
					mostraPreu(missPreu, msgModal, false);
				}
			}
			else {
				if (parseInt($('#preu .preu').html().trim()) != preuInscripcio) {
					$("#modalLoading").modal('show');
					setTimeout(function() {
						if ( preuInscripcio <= 0 ) {
							msgModal = "<p>Hi ha hagut un error al calcular el preu. Si us plau, torna a carregar la pàgina per solucionar el problema.</p>";
						}
						msgModal = "<p>" + msgInscModal + "</p>";
						if (vectorTipusMsgPreu[3]!=0) {
							mostraPreu(missPreu, msgModal, true);
						}
						else {
							mostraPreu(missPreu, msgModal, false);
						}
					}, 3000);
				}
			}
		});

		searchPrice.fail(function( jqXHR, textStatus, errorThrown ) {
			errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el càlcul del preu: " );
		});
	}
}

function mostraPreu( msgPreu, msgModal, showModal ) {
	$('#preu').html("<p>Preu: " + msgPreu + "</p>");
	$("#modalLoading").modal('hide');
	$("#modalBodyInfoPreu").html( msgModal );
	if ( showModal ) {
		$("#modalInfoPreu").modal('show');
	}
}

//Comprovar si l'edicio sel·leccionada és una edició reconeguda
//S ino és una edició reconeguda, apreixerà una frase indicant que l'edicio no està reconeguda.
function edicioNoReconeguda() {
	$('#missInformatiuEdicioRec').html('');
	$('#missInformatiuEdicioRec').removeClass('mb-3');
	$.ajax({
		url: path + "ajax/edicioNoReconeguda.php?edicio=" + edicio + "&any=" + any + "&curs=" + codiCurs,
		cache: false,
		type: "GET",
		success: function(data) {
			$('#missInformatiuEdicioRec').html(data);
			if (data!='') $('#missInformatiuEdicioRec').addClass('mb-3');
		}
	});
}

function edicioSensePerfil() {
	var numEd = $('#dates .select-list li').length;
	$('#missInformatiuEdicioPerf').html('');
	$('#missInformatiuEdicioPerf').removeClass('mb-3');
	$.ajax({
		url: path + "ajax/cursTePerfil.php?curs=" + codiCurs,
		cache: false,
		type: "GET",
		success: function(cursTeAlgunPerfil) {
			if (cursTeAlgunPerfil == 'true') {
				$.ajax({
					url: path + "ajax/edicioTePerfil.php?edicio=" + edicio + "&any=" + any + "&curs=" + codiCurs,
					cache: false,
					type: "GET",
					success: function(edicioTeAlgunPerfil) {
						if ((cursTeAlgunPerfil != edicioTeAlgunPerfil) && (edicio != '0')) { // serà 0 quan no hi hagi cap edició seleccionada
							var miss = "L'edició seleccionada encara està pendent de confirmar l'acreditació del perfil professional. ";
							miss += "Per a més informació, visiteu la pàgina de <a href='https://www.prisma.cat/perfils-professionals' ";
							miss += "role='button' title='Cursos de PrisMa amb perfils professionals'>perfils professionals</a>.";

							$('#missInformatiuEdicioPerf').html(miss);
							$('#missInformatiuEdicioPerf').addClass('mb-3');
						}
					}
				});
			}
		}
	});
}

function validarDates() {
	var error = '';

	if ($("#dates .element-selected").html().trim() == "Durant quines dates vols realitzar el curs? Tria l'edició" || any == 0 || edicio == 0) {
		error = "Camp obligatori";
		mostrarError("dates_erroni", error);
	} else {
		eliminarError("dates_erroni");
	}
	return error;
}

function validarNom() {
	var error = validacioCampBuit("nom", "nom_cognom_erroni");
	if (error.length == 0) error = validarNomDiferentCognom();
	return error;
}

function validarCognoms() {
	var error = validacioCampBuit("cog", "cognom_erroni");
	if (error.length == 0) error = validarNomDiferentCognom();
	return error;
}

function validarNomDiferentCognom() {
	var nom = $('#nom').val().trim();
	var cog = $('#cog').val().trim();
	var error = "";

	if (nom.length != 0 && cog.length != 0) {
		if (cog == nom) {
			error = "El nom i els cognoms no poden coincidir";
			mostrarError("nom_cognom_erroni", error);
		} else
			eliminarError("nom_cognom_erroni");
	}
	return error;
}

function validarNif() {
	var nif = $('#nif').val().trim();
	var error = "";

	var error = validacioCampBuit("nif", "dni_erroni");;

	if (nif.length != 0) {
		var stripped = nif.replace(/[\(\)\.\-\ ]/g, '');

		if (!(stripped.length == 9)) {
			error = "Llargada incorrecta";
			mostrarError("dni_erroni", error);
		} else {
			var primera_posicio = nif.charAt(0);
			if (primera_posicio == "x" || primera_posicio == "y" || primera_posicio == "X" || primera_posicio == "Y") {
				numeros = nif.split(primera_posicio);
				if (!esNumeric(numeros[1])) {
					error = "NIF/NIE erroni";
					mostrarError("dni_erroni", error);
				}
			} else if (!(esNumeric(nif))) {
				error = "NIF/NIE erroni";
				mostrarError("dni_erroni", error);
			}
		}
	}
	if (error.length == 0) {
		var lletra = nif.charAt(8);
		console.log("comprovar lletra " + lletra);
		if (!(esAlphabetic(lletra))) {
			error = "NIF/NIE erroni";
			mostrarError("dni_erroni", error);
		} else {
			var lockup = 'TRWAGMYFPDXBNJZSQVHLCKE';
			dni = nif.substring(0, 8);

			primer = dni.charAt(0);
			resta = dni.substring(1);

			if (primer.toUpperCase() == "X")
				dni = 0 + resta;
			else if (primer.toUpperCase() == "Y")
				dni = 1 + resta;
			else if (primer.toUpperCase() == "Z")
				dni = 2 + resta;

			correcta = lockup.charAt(dni % 23);

			if (lletra.toUpperCase() != correcta) {
				error = "La lletra no correspon";
				mostrarError("dni_erroni", error);
			} else
				eliminarError("dni_erroni");
		}
	}
	return error;
}

function validarTel() {
	var telf = $('#telf').val().trim();
	var error = "";

	error = validacioCampBuit("telf", "telf_erroni");;

	if (telf.length != 0) {
		var stripped = telf.replace(/[\(\)\.\-\ ]/g, '');

		if (!(stripped.length == 9)) {
			error = "Llargada incorrecta";
			mostrarError("telf_erroni", error);
		} else if (isNaN(stripped)) {
			error = "Caràcters no permesos";
			mostrarError("telf_erroni", error);
		} else {
			eliminarError("telf_erroni");
		}

	}
	return error;
}

//Validar el correu electrònic.
//Apareix el modal d'avis del correu electrònic si inici és 0 i no hi ha cap error
function validarEmail(inici) {
	var error = validacioCampBuit("email", "correu_erroni");
	if (error.length == 0) {
		error = validarCorreu();
		if (error.length == 0 && $('#email_conf').val().trim()!='') correuAdvert(inici);
	}
	return error;
}

//Validar la confirmació del correu electrònic.
//Apareix el modal d'avis del correu electrònic si inici és 0 i no hi ha cap error
function validarEmailConf(inici) {
	var error = validacioCampBuit("email_conf", "correu_conf_erroni");
	if (error.length == 0) {
		error = validarCorreu(inici);
		if (error.length == 0 && $('#email').val().trim()!='' && inici==0) correuAdvert(inici);
	}
	return error;
}

function validarCorreu(inici) {
	var email = $('#email').val().trim();
	var email_conf = $('#email_conf').val().trim();
	var error = "";

	if (email.length != 0 && email_conf.length != 0) {
		var tfld_email = $.trim(email);
		var tfld_email_conf = $.trim(email_conf);
		var emailFilter = /^([\w-\.]+@([\w-]+\.)+[\w-]{2,4})?$/;
		var illegalChars = /[\(\)\<\>\,\;\:\\\"\[\]]/;

		if (!emailFilter.test(tfld_email)) {
			error = "Correu electrònic no vàlid";
			mostrarError("correu_erroni", error);
		} else if (email.match(illegalChars)) {
			error = "Caràcters no permesos";
			mostrarError("correu_erroni", error);
		}

		if (!emailFilter.test(tfld_email_conf)) {
			error = "Correu electrònic no vàlid";
			mostrarError("correu_conf_erroni", error);
		} else if (email_conf.match(illegalChars)) {
			error = "Caràcters no permesos";
			mostrarError("correu_conf_erroni", error);
		}

		if (error == "") {
			if (email != email_conf) {
				error = "No coincideixen els correus electrònics";
				mostrarError("correu_erroni", error);
				mostrarError("correu_conf_erroni", error);
			} else {
				eliminarError("correu_erroni");
				if ( inici == 0 )
					enviament_publi(email_conf);
			}
		}
	}
	return error;
}

// function correuAdvert() {
// 	var email = $('#email').val().trim();
// 	var correu = email.split("@")[1];
//
// 	if ( !$('#modalCorreuValid').hasClass('show') ) {
// 		$.ajax({
// 			url: path + "ajax/buscarCorreusNoValid.php",
// 			cache: false,
// 			type: "GET",
// 			success: function(esCorreuValid) {
// 				if ( esCorreuValid != '') { //(hotmail.es,hotmail.com,yahoo.es|Hotmail,Yahoo!)
// 					var vectEl = esCorreuValid.split(","),
// 						 vectTermAdvert = vectEl[0].split("|"),
// 						 vectNomTermAdvert = vectEl[1].split("|"),
// 						 correuActValid = true,
// 						 vc=0;
// 					 while (vc<vectTermAdvert.length && correuActValid) {
//  						if (vectTermAdvert[vc].toLowerCase() == correu.toLowerCase())
// 							correuActValid=false;
//  						else
// 							vc++;
//  					}
//
// 					if (!correuActValid) {
// 						var terminacions = '';
// 						for (var vc=0; vc<vectNomTermAdvert.length; vc++) {
// 							if (vc>0) {
// 								if (vc==vectNomTermAdvert.length-1)
// 									terminacions += ' i ';
// 								else
// 									terminacions += ', ';
// 							}
// 							terminacions += "<span><em>"+vectNomTermAdvert[vc]+"</em></span> ";
// 						}
// 						var msgInscModal = "<p>Et recomanem que posis un altre correu ";
// 						msgInscModal += "electrònic si el tens, atès que els comptes ";
// 						msgInscModal += "de " + terminacions + " poden no rebre els ";
// 						msgInscModal += "missatges del Campus i de Secretaria.</p>";
// 						msgInscModal += "<p class='mb-0'>Gràcies.</p>";
// 						$("#modalCorreuValidBody").html(msgInscModal);
// 						$('#modalCorreuValid').modal('show');
// 					}
// 				}
// 			}
// 		});
// 	}
// }

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
			mostrarError("correu_conf_erroni", error);
		}
	}
	return error;
}

function haveArroba(valor) {
	var arroba = "@";

	for (i = 0; i < valor.length; i++) {
		if (arroba.indexOf(valor.charAt(i), 0) != -1) {
			return 1;
		}
	}
	return 0;
}

function validarAdreca() {
	var adreca = $('#adreca').val().trim();
	var error = "";

	var error = validacioCampBuit("adreca", "adreca_erroni");

	if (adreca.length != 0) {
		if (haveArroba(adreca)) {
			error = "Adre\u00E7a postal incorrecta.";
			mostrarError("adreca_erroni", error);
		} else {
			eliminarError("adreca_erroni");
		}
	}
	return error;
}

function validarCP() {
	var cp = $('#cp').val().trim();
	var error = "";

	var error = validacioCampBuit("cp", "cp_erroni");

	if (cp.length != 0) {
		var stripped = cp.replace(/[\(\)\.\-\ ]/g, '');

		if (!(stripped.length == 5)) {
			error = "Llargada incorrecta";
			mostrarError("cp_erroni", error);
		} else {
			eliminarError("cp_erroni");
		}

	}
	return error;
}

function haveNumbers(valor) {
	var numeros = "0123456789";
	for (i = 0; i < valor.length; i++) {
		if (numeros.indexOf(valor.charAt(i), 0) != -1) {
			return true;
		}
	}
	return false;
}

function validarPoble() {
	var poble = $('#poble').val().trim();

	var error = validacioCampBuit("poble", "poble_erroni");

	if (poble.length != 0) {
		if ((haveNumbers(poble))) {
			error = "Poblacic&oacute incorrecta";
			mostrarError("poble_erroni", error);
		} else {
			eliminarError("poble_erroni");
		}
	}
	return error;
}

function esAlphabetic(valor) {
	var re = /^[a-zA-Z]+$/;
	if (re.test(valor)) return true
	else return false
}

function esNumeric(valor) {
	var log = valor.length;
	var sw = "S";
	for (x = 0; x < log - 1; x++) {
		v1 = valor.substr(x, 1);
		v2 = parseInt(v1);
		if (isNaN(v2)) sw = "N";
	}

	if (sw == "S") return true
	else return false
}

function validarPass() {
	return validacioCampBuit("pass", "dni_erroni");
}

function validarPerfils() {
	var perfil = $('#perfil .element-selected').html().trim();
	var error = '';

	// if ($("#perfil .element-selected").html().trim() == 'Estic treballant a') {
	if ($("#perfil .element-selected").html().includes('Estic treballant a')) {
		error = "Camp obligatori";
		mostrarError("perfil_erroni", error);
	}
	else {
		eliminarError("perfil_erroni");
		if (perfil == $('#perfil-altres a').html().trim())
			error = validacioCampBuit("perfil_de", "perfil_altres_erroni");
	}
	return error;
}

function validarTitulacio() {
	var titulacio = $('#titulacio .element-selected').html().trim();
	var error = '';

	// if ($("#titulacio .element-selected").html().trim() == 'Tinc la titulació de') {
	if ($("#titulacio .element-selected").html().includes('Tinc la titulació de') ) {

		error = "Camp obligatori";
		mostrarError("titulacio_erroni", error);
	}
	else {
		eliminarError("titulacio_erroni");
		if (titulacio == $('#titulacio-altres a').html().trim())
			error = validacioCampBuit("titol_altres", "titulacio_altres_erroni");
		else if (titulacio == $('#titulacio-edSecundaria a').html().trim())
			error = validacioCampBuit("titol_secundaria", "titulacio_secundaria_erroni");
		else if (titulacio == $('#titulacio-estudiant a').html().trim())
			error = validacioCampBuit("titol_estudiant", "titulacio_estudiant_erroni");
	}
	return error;
}

function validarConegut() {
	var com_conegut = $('#comConegut .element-selected').html().trim();
	var error = '';

	//if ($("#comConegut .element-selected").html().trim() == 'Com has conegut aquest curs? Tria una opció<span class="req ml-1">*</span>') {
	if ($("#comConegut .element-selected").html().includes('Com has conegut aquest curs? Tria una opció') ) {
		error = "Camp obligatori";
		mostrarError("conegut_erroni", error);
	} else {
		eliminarError("conegut_erroni");
		if (com_conegut == $('#comConegut #conegut-altres').html().trim())
			error = validacioCampBuit("c_altres", "conegut_altres_erroni");
	}
	return error;
}

function validarMailing() {
	var valmail = $('#valmail').val().trim();
	var error = '';

	if (valmail == '1') {
		if (($("#radio_mailing_yes").is(':checked')) || ($("#radio_mailing_no").is(':checked'))) {
			$('#mailing_erroni').html("");
			$('#mailing_erroni').removeClass('mailing_erroni');
		} else {
			error = "Has de marcar alguna opció";
			$('#mailing_erroni').html("<i class='fas fa-times-circle img-preu'></i> " + error);
			$('#mailing_erroni').addClass('mailing_erroni');
		}
	} else {
		$('#mailing_erroni').html("");
		$('#mailing_erroni').removeClass('mailing_erroni');
	}

	return error;
}

/*
   Comprova si ha realitzat el curs xxx.
   Si no l'ha realitzat, comprova si ha realitzat el curs del qual deriva. Si l'ha realitzat
	mostra el modal amb la frase "Has realitzat el curs XXX a l'edició XXX de l'any XXX" i pregunta si vol continuar la inscripcio
	Si l'ha realitzat,
	mostra el modal amb la frase "Has realitzat el curs XXX a l'edició XXX de l'any XXX" i pregunta si vol continuar la inscripcio
*/
function comprovaSiHaRealitzatElCurs() {
	var vectorHaRealitzatElCurs=[],
		 titolHaRealitzatElCurs='',
		 anyHaRealitzatElCurs=0,
		 edicioHaRealitzatElCurs='';
	//Consulta ajax per comprovar si el usuari XXX ha realitzat el curs xxx (retornar l'edicio|any en que va fer-lo)

	var request = $.ajax({
		url: path + "ajax/buscarSiHaRealitzatElCurs.php",
		data: {
			doc: documentacio,
			curs: codiCurs
		},
		method: "GET",
		dataType: "html"
	});

	request.done(function( haRealitzatElCurs ) {
		if ( !haRealitzatElCurs.toLowerCase().includes("error") &&  haRealitzatElCurs != '' ) {
			vectorHaRealitzatElCurs = haRealitzatElCurs.split("|");
			titolHaRealitzatElCurs = vectorHaRealitzatElCurs[0];
			anyHaRealitzatElCurs = vectorHaRealitzatElCurs[1];
			edicioHaRealitzatElCurs = vectorHaRealitzatElCurs[2];

			//Si ha realitzat el curs, mostra el modal per continuar la inscripcio
			var msgInscModal = "<p>Ja has realitzat el curs <span>"+titolHaRealitzatElCurs+"</span> ";
			msgInscModal += "en l'edició <span>"+edicioHaRealitzatElCurs+"</span> ";
			msgInscModal += "de l'any <span>"+anyHaRealitzatElCurs+"</span>.</p>";
			msgInscModal += "<p class='mb-0'>Què vols fer?</p>";
			$("#modalInscripcioDuplicadaBody").html(msgInscModal);
			$("#modalLoading").modal('hide');
			$("#modalInscripcioDuplicada").modal('show');

		}
		else if ( !haRealitzatElCurs.toLowerCase().includes("error") &&  haRealitzatElCurs != '' ) {
			//Busco el codi curs del qual deriva el curs actual.
			var reqCode = $.ajax({
				url: path + "ajax/buscarCodiCursDeriva.php",
				data: {
					curs: codiCurs
				},
				method: "GET",
				dataType: "html"
			});

			reqCode.done(function( cursDerivat ) {
				if ( !cursDerivat.toLowerCase().includes("error")  && cursDerivat != '') {
					//Si el curs deriva d'algun altre curs, comprovar si ha realitzat el curs del qual deriva (retornar l'edicio|any en que va fer-lo)

					var haFetCurs = $.ajax({
						url: path + "ajax/buscarSiHaRealitzatElCurs.php",
						data: {
							doc: documentacio,
							curs: cursDerivat
						},
						method: "GET",
						dataType: "html"
				});

					haFetCurs.done(function( haRealitzatElCursDeriva ) {
						if ( !haRealitzatElCursDeriva.toLowerCase().includes("error") && msg!='' ) {
							if (haRealitzatElCursDeriva!='') {
								vectorHaRealitzatElCurs = haRealitzatElCursDeriva.split("|");
								titolHaRealitzatElCurs = vectorHaRealitzatElCurs[0];
								anyHaRealitzatElCurs = vectorHaRealitzatElCurs[1];
								edicioHaRealitzatElCurs = vectorHaRealitzatElCurs[2];

								var msgInscModal = "<p>Aquest curs és part del curs <span>"+titolHaRealitzatElCurs+"</span> ";
								msgInscModal += "que ja vas realitzar en l'edició <span>"+edicioHaRealitzatElCurs+"</span> ";
								msgInscModal += "de l'any <span>"+anyHaRealitzatElCurs+"</span>.</p>";
								msgInscModal += "<p class='mb-0'>Què vols fer?</p>";

								$("#modalInscripcioDuplicadaBody").html(msgInscModal);
								$("#modalLoading").modal('hide');
								$("#modalInscripcioDuplicada").modal('show');
							}
							else {
								enviarInscripcio();
							}
						}
					});

					haFetCurs.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
						errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de comprovar si ha realitzat el curs ");
					});

				}
				else if ( !cursDerivat.toLowerCase().includes("error") && cursDerivat == '' ) {
					enviarInscripcio();
				}
			});

			reqCode.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
				errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de buscar el curs per el qual deriva ");
			});
		}

		if ( !haRealitzatElCurs.toLowerCase().includes("error") ) {
			$("#confirmInsc").click(function(e){
				enviarInscripcio();
			});
		}

	});

	request.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de buscar si ha realitzat el curs ");
	});


	$.ajax({
		url: path + "ajax/buscarSiHaRealitzatElCurs.php?doc=" + documentacio + "&curs=" + codiCurs,
		cache: false,
		type: "GET",
		success: function(haRealitzatElCurs) {
			if (haRealitzatElCurs!='') {
				vectorHaRealitzatElCurs = haRealitzatElCurs.split("|");
				titolHaRealitzatElCurs = vectorHaRealitzatElCurs[0];
				anyHaRealitzatElCurs = vectorHaRealitzatElCurs[1];
				edicioHaRealitzatElCurs = vectorHaRealitzatElCurs[2];

				//Si ha realitzat el curs, mostra el modal per continuar la inscripcio
				var msgInscModal = "<p>Ja has realitzat el curs <span>"+titolHaRealitzatElCurs+"</span> ";
				msgInscModal += "en l'edició <span>"+edicioHaRealitzatElCurs+"</span> ";
				msgInscModal += "de l'any <span>"+anyHaRealitzatElCurs+"</span>.</p>";
				msgInscModal += "<p class='mb-0'>Què vols fer?</p>";
				$("#modalInscripcioDuplicadaBody").html(msgInscModal);
				$("#modalLoading").modal('hide');
				$("#modalInscripcioDuplicada").modal('show');
			}
			else {
				//Busco el codi curs del qual deriva el curs actual.
				$.ajax({
					url: path + "ajax/buscarCodiCursDeriva.php?curs=" + codiCurs,
					cache: false,
					type: "GET",
					success: function(cursDerivat) {
						//Si el curs deriva d'algun altre curs, comprovar si ha realitzat el curs del qual deriva (retornar l'edicio|any en que va fer-lo)
						if (cursDerivat!='') {
							$.ajax({
								url: path + "ajax/buscarSiHaRealitzatElCurs.php?doc=" + documentacio + "&curs=" + cursDerivat,
								cache: false,
								type: "GET",
								success: function(haRealitzatElCursDeriva) {
									if (haRealitzatElCursDeriva!='') {
										vectorHaRealitzatElCurs = haRealitzatElCursDeriva.split("|");
										titolHaRealitzatElCurs = vectorHaRealitzatElCurs[0];
										anyHaRealitzatElCurs = vectorHaRealitzatElCurs[1];
										edicioHaRealitzatElCurs = vectorHaRealitzatElCurs[2];

										var msgInscModal = "<p>Aquest curs és part del curs <span>"+titolHaRealitzatElCurs+"</span> ";
										msgInscModal += "que ja vas realitzar en l'edició <span>"+edicioHaRealitzatElCurs+"</span> ";
										msgInscModal += "de l'any <span>"+anyHaRealitzatElCurs+"</span>.</p>";
										msgInscModal += "<p class='mb-0'>Què vols fer?</p>";

										$("#modalInscripcioDuplicadaBody").html(msgInscModal);
										$("#modalLoading").modal('hide');
										$("#modalInscripcioDuplicada").modal('show');
									}
									else {
										enviarInscripcio();
									}
								}
							});
						}
						else {
							enviarInscripcio();
						}
					}
				});
			}

			$("#confirmInsc").click(function(e){
				enviarInscripcio();
			});
		}
	});

}

function validarFileCarnet() {
	var error = '';
	if ( checkDiscapacitat == 1 || checkFamNum == 1 || checkFamMono == 1 ) {
		if ( !$('#carnet')[0].files[0] ) {
			error = "Has d'adjuntar un fitxer";
			mostrarError("carnet_erroni", error);
		}
		else
			eliminarError("carnet_erroni");
	}
	else
		eliminarError("carnet_erroni");
	return error;
}

function enviarInscripcio() {
	//enviem inscripció
	//si no hi ha cap error, carrego la pàgina del pagament
	//si hi ha algun error, mostra el modal d'error

	var titolCurs = $('.nom-curs').html().trim(),
		 nom = $("#nom").val().trim(),
		 cog = $("#cog").val().trim(),
		 telf = $("#telf").val().trim(),
		 email = $("#email").val().trim(),
		 adreca = $("#adreca").val().trim(),
		 codiPostal = $("#cp").val().trim(),
		 poblacio = $("#poble").val().trim(),
		 perfil = $("#perfil .element-selected").html().trim(),
		 perfilAltres = '',
		 titulacio = $("#titulacio .element-selected").html().trim(),
		 titulacioAltres = '',
		 titulacioEdSecundaria = '',
		 titulacioEstudiant = '',
		 tbTitulacio = $("#titol_de").val().trim(),
		 pagFrac = 'no',
		 dates =  $("#dates .element-selected").html().trim(),
		 conegut =  $("#comConegut .element-selected").html().trim(),
		 comentaris =  $("#comentaris").val().trim(),
		 mailing = 'registred';

	if ( perfil == $('#perfil #perfil-altres a').html().trim() )
		perfilAltres = $("#perfil_de").val().trim();

	if ( titulacio == $('#titulacio #titulacio-altres a').html().trim() )
		titulacioAltres = $("#titol_altres").val().trim();
	else if ( titulacio == $('#titulacio #titulacio-edSecundaria a').html().trim() )
		titulacioEdSecundaria = $("#titol_secundaria").val().trim();
	else if ( titulacio == $('#titulacio #titulacio-estudiant a').html().trim() )
		titulacioEstudiant = $("#titol_estudiant").val().trim();

	if ( conegut == "Altres (indica'ns com)" )
		conegut = "Altres: " + $("#c_altres").val().trim();

	if ( $('#pagament_fraccionat').is(":checked") )
		pagFrac = 'yes';

	if ( $("#valmail").val() == '1' ) {
		if  ($("#radio_mailing_yes").is(":checked"))
			mailing = "yes";
		else
			mailing = "no";
	}


	var requestInscrDupl = $.ajax({
		url: path + "ajax/inscripcioDuplicada.php",
		data: {
			dni: documentacio,
			any: any,
			edicio: edicio,
			codiCurs: codiCurs
		},
		method: "GET",
		dataType: "html"
	});

	requestInscrDupl.done(function( msgInscDupl ) {
		if ( !msgInscDupl.toLowerCase().includes("error") && msgInscDupl == '' ) {
			var sendInscr = $.ajax({
				url: path + "ajax/enviarInscripcio_prova_usoc.php",
				data: {
					nom: nom,
					cog: cog,
					dni: documentacio,
					telf: telf,
					email: email,
					adreca: adreca,
					codiPostal: codiPostal,
					poblacio: poblacio,
					perfil: perfil,
					perfilAltres: perfilAltres,
					titulacio: titulacio,
					titulacioAltres: titulacioAltres,
					titulacioSecundaria: titulacioEdSecundaria,
					titulacioEstudiant: titulacioEstudiant,
					tbTitulacio: tbTitulacio,
					any: any,
					edicio: edicio,
					pagFrac: pagFrac,
					dates: dates,
					conegut: conegut,
					comentaris: comentaris,
					mailing: mailing,
					tipusDescompte: tipusPreuAplicat,
					preuCar: preuCurs,
					preuDescompte: preuInscripcio,
					promocioATrobadaplicada: promocioATrobadaplicada,
					promocioAplicada: promocioAplicada,
					codiCurs: codiCurs,
					titolCurs: titolCurs,
				},
				method: "GET",
				dataType: "html"
			});

			sendInscr.done(function( msg ) {
				if ( !msg.toLowerCase().includes("error") && msg != '' ) {
					//buscar la part amigable de la url actual
					//retorna la url de /confirmacio/url-amigable/idInsc
					var urlConf = "https://www.prisma.cat/confirmacio/";
					urlConf += urlPart2 + "/" + msg.trim();
					// window.location.replace(urlConf);

					var formData = new FormData();
					var files = $('#carnet')[0].files[0];
					formData.append('file',files);
					formData.append('codiCurs',codiCurs);
					formData.append('edicio',edicio);
					formData.append('any',any);
					formData.append('documentacio',documentacio);
					$.ajax({
						url: 'ajax/enviarImatgeCarnetInscripcio.php',
						type: 'post',
						data: formData,
						contentType: false,
						processData: false,
						success: function(response) {
							// window.location.replace(urlConf);
						}
					});
				}
				else {
					$('#modalLoading').modal('hide');
					$('#modalInscripcioDuplicada').modal('hide');
					mostrarModalError(msg);
				}
			});

			sendInscr.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
				errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de xx ");
			});
		}
		else if ( !msgInscDupl.toLowerCase().includes("error") && msgInscDupl != '' ) {
			var msgInscDupl = "<p>Ja t'has inscrit en el curs <span class='font-weight-bold'>";
			 msgInscDupl += titolCurs+"</span> a l'edició que dura des <span class='font-weight-bold'>";
			 msgInscDupl += dates.toLowerCase()+".</p>"
			$('#modalLoading').modal('hide');
			$('#modalInscripcioDuplicada').modal('hide');
			mostrarModalError(msgInscDupl);
		}
	});

	requestInscrDupl.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de buscar si la inscripció és duplicada ");
	});
}

function validacioCampBuit(id, id_error) {
	//console.log("validació del camp "+id);
	var error = "";

	if ($("#" + id).val().trim().length == 0) {
		error = "Camp obligatori";
		mostrarError(id_error, error);
	} else {
		eliminarError(id_error);
	}
	return error;
}

function mostrarError(id_error, error) {
	$("#" + id_error).html(error);
	$("#" + id_error).addClass("erroni text-center text-white");
}

function eliminarError(id_error) {
	$("#" + id_error).html("");
	$("#" + id_error).removeClass("erroni");
}

function mostrarModalError(missatgeError) {
	$('#modalErrorsBody').html(missatgeError);
	$('#modalErrors').modal('show');
}

function errorFunction( jqXHR, textStatus, errorThrown, msg ) {
	var msgError = msg;
	if (jqXHR.status === 0)
		msgError += "<strong>Verifica la connexió</strong>";
	else if (jqXHR.status === 404)
		msgError += "<strong>Page Not Found</strong>";
	else if (jqXHR.status === 414)
		msgError += "<strong>Request URI Too Long [414]</strong>";
	else if (jqXHR.status === 500)
		msgError += "<strong>Internal Server Error [500]</strong>";
	else if (textStatus === 'parsererror')
		msgError += "<strong>Requested JSON parse failed</strong>";
	else if (textStatus === 'timeout')
		msgError += "<strong>Time out error</strong>";
	else if (textStatus === 'abort')
		msgError += "<strong>Ajax request aborted</strong>";
	else if (jqXHR.status === 0)
		msgError += "<strong>"+jqXHR.responseText+"</strong>";
	mostrarModalError(msgError);
}

function adjustStyle() {
	screenWidth = parseInt($(this).width());

	if (screenWidth < 575) {
		$('.prisma-footer .panel').css('display', 'none');
		$('.accordion-footer').removeClass('active');
		$('.accordion-footer').click(function() {
			this.classList.toggle("active");
			var panel = this.nextElementSibling;
			if (panel.style.display === "block") {
				panel.style.display = "none";
				panel.style.maxHeight = null
			} else {
				panel.style.display = "block";
				panel.style.maxHeight = panel.scrollHeight + "px"
			}
		})
	}
	else {
		$('.accordion-footer').removeClass('active');
		$('.prisma-footer .panel').css('display', 'block')
	}
}

function afegirCSS() {
	$('body').append("<link rel='stylesheet' href='https://fonts.googleapis.com/css?family=Roboto:100,100i,300,400,400i,500,500i,700,700i|Nunito+Sans&display=swap' />");
	$('body').append("<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/font-awesome-prisma.min.css?ver=1.0' />");
	$('body').append("<link rel='shortcut icon' type='image/x-icon' href='https://www.prisma.cat/favicon.ico'/>");
	$('body').append("<link rel='stylesheet' href='https://www.prisma.cat/css1619773569/404.min.css?ver=2.0' />")
}

afegirCSS();

function openNav() {
	$(".navbarPrisma").css('width', '270px');
	$('.closebtn').css('display', 'block');
	$('.navBarPrisma').addClass('w-100');
	$("#pagina").addClass('sideNavBarPrismaObert');
	$("#pagina > .container").addClass('sideNavBarContainerPrismaObert');
	$('body').css('overflow-y', 'hidden')
}

function closeNav() {
	$(".navbarPrisma").css('width', '0');
	$('.closebtn').css('display', 'none');
	$('.navBarPrisma').removeClass('w-100');
	$("#pagina").removeClass('sideNavBarPrismaObert');
	$("#pagina > .container").removeClass('sideNavBarContainerPrismaObert');
	$('body').css('overflow-y', 'auto')
}
window.onscroll = function() {
	noPerdreHeader()
};

function noPerdreHeader() {
	if (document.body.scrollTop > 0 || document.documentElement.scrollTop > 0) {
		$('#top-menu').css('display', 'none');
		$('#nav-header').css('position', 'fixed');
		$('#nav-header').css('top', '0')
	} else {
		if (!(/Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent)))
			$('#top-menu').css('display', 'block');
		$('#nav-header').css('position', 'inherit')
	}
}
