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
						missatgeConsultaOK += "<p>En breu rebràs un missatge de confirmació en del correu ";
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

let pagina = 1,
	hemPassatPerLaPagina1 = false;
	hemPassatPerLaPagina4 = false;
	codiCurs = '', //ens indica el curs que estem fent el descompte;
	edicio = '0',
	any = 0,
	dates = '',
	conegut = '',
	tipusInsc = '',
	nomCentreContacte = '',
	nomPersonaContacte = '',
	cognomsPersonaContacte = '',
	dniPersonaContacte = '',
	telefonPersonaContacte = '',
	emailPersonaContacte = '',
	adrecaPersonaContacte = '',
	cpPersonaContacte = '',
	poblacioPersonaContacte = '',
	codiPostal = '',
	numAlumnesGrup = 0,
	dadesGrup = [],
	screenWidth = parseInt($(this).width());

/* ############################### FUNCIONALITATS ############################### */

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
	edicioNoReconeguda();
	edicioSensePerfil();
}

function mostrarConegut(id) {

	$('#conegut .element-selected').html(conegut);
	$('#comHasConegut_altres').html('');

	if (id == 'conegut-altres') {
		var nomCamp = 'Com ens has conegut?';
		var idInput = 'c_altres';
		var idError = 'conegut_altres_erroni';
		var input = "<div class='form-group field-wrap position-relative w-100'>";
		input += "<label class='position-absolute mb-0 active'><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
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

		$('.form-group').on('focus', '.form-control', function() {
			$(this).prev().addClass('active');
		});
		$('.form-group').on('blur', '.form-control', function() {
			if ($(this).val().trim() == '')
				$(this).prev().removeClass('active');
		});
	}
}

/* Mostrem la pàgina que toca */
function mostrarPagina() {
	removeEvents();
	console.log('pàgina: ' + pagina);

	if (pagina == 1) { //mostra la pàgina inicial del descompte per grup
		var requestPagina1 = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/mostrar_pagina_descompte_grup.php",
			method: "GET",
			data: {
				dispositiu: dispositiu
			},
			dataType: "html"
		});

		requestPagina1.done(function(page) {
			hemPassatPerLaPagina1 = true;
			$("#contingut").html(page);

			$('#cnt-cursos').on('click', '.curs-descompte-grup-overlay', function() {
				realitzaCurs( $(this).parent().attr('id').split('-')[2] );
			});
			$('#cnt-cursos').on('click', '.curs-descompte-grup-titol', function() {
				realitzaCurs( $(this).parent().attr('id').split('-')[2] );
			});
		});

		requestPagina1.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Page 1: ");
		});
	} else if (pagina == 2) { //mostra el formulari d'inscripció per a grups

		var requestPagina2 = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/mostrar_formulari_inscripcions_grup.php",
			method: "GET",
			data: {
				codi: codiCurs,
				tipusInsc: tipusInsc,
				nomCentre: nomCentreContacte,
				nom: nomPersonaContacte,
				cognoms: cognomsPersonaContacte,
				dni: dniPersonaContacte,
				telefon: telefonPersonaContacte,
				email: emailPersonaContacte,
				adreca: adrecaPersonaContacte,
				cp: cpPersonaContacte,
				poble: poblacioPersonaContacte,
			},
			dataType: "html"
		});

		requestPagina2.done(function(page) {
			$(".cntPage").html(page);

			if (hemPassatPerLaPagina1) {
				$(".cntPage").fadeIn(800);
				// });
				$('html, body').animate({
					scrollTop: 0
				}, 'slow');
			}

			//Quan es clica un element amb la classe .tipusInsc, es marca com a tipusInsc l'id de l'objecte
			$('.cntPage').on('click', '.tipusInsc', function() {
				tipusInsc = $(this).attr('id');
				$('.tipusInsc').removeClass('marcat');
				$(this).addClass('marcat');
			});

			//Quan es clica l'element amb id «enrere», anem a la pàgina anterior
			$('.cntPage').on('click', '#enrereForm', function() {
				pagina--;
				mostrarPagina();
			});

			//Quan es clica l'element amb id continua, comprovem que hi ha un tipus d'inscripció marcada
			//Si no hi ha cap error, anem a la següent pàgina;
			//Si hi ha algun error, mostrem un modal amb l'error.
			$('.cntPage').on('click', '#continuaForm', function() {
				if (tipusInsc != '') {
					$(".cntPage").fadeOut(800, function() {
						pagina++;
						mostrarPagina();
					});
				} else {
					var missatgeError = "<p>Cal indicar si la inscripció és d'un grup o un centre escolar.</p>";
					mostrarModalError(missatgeError);
				}
			});
		});

		requestPagina2.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Page 2: ");
		});
	} else if (pagina == 3) { //mostra el formulari de les dades de contacte
		var requestPagina3 = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/mostrar_formulari_dades_contacte.php",
			method: "GET",
			data: {
				codi: codiCurs,
				tipusInsc: tipusInsc,
				dispositiu: dispositiu,
				nomCentre: nomCentreContacte,
				nom: nomPersonaContacte,
				cognoms: cognomsPersonaContacte,
				dni: dniPersonaContacte,
				telefon: telefonPersonaContacte,
				email: emailPersonaContacte,
				adreca: adrecaPersonaContacte,
				cp: cpPersonaContacte,
				poblacio: poblacioPersonaContacte
			},
			dataType: "html"
		});

		requestPagina3.done(function(page) {
			$(".cntPage").html(page);
			$(".cntPage").fadeIn(800);
			$('html, body').animate({
				scrollTop: 0
			}, 'slow');

			//Afegeix i elimina la classe "active" del input que s'ha clicat a sobre seu
			$('.form-group').on('focus', '.form-control', function() {
				$(this).prev().addClass('active');
			});
			$('.form-group').on('blur', '.form-control', function() {
				if ($(this).val().trim() == '')
					$(this).prev().removeClass('active');
			});

			if (tipusInsc != 'centre-escolar') mostrarDocumentacio('doc-dni');

			$(".form-group .select").click(function(e) {
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

			$('.cntPage').on('change', '#nom_centre', function() {
				validarNomCentre()
			});
			$('.cntPage').on('blur', '#nom_centre', function() {
				validarNomCentre()
			});
			$('.cntPage').on('focus', '#nom_centre', function() {
				eliminarError('nom_centre_erroni')
			});

			$('.cntPage').on('change', '#nom', function() {
				validarNom()
			});
			$('.cntPage').on('blur', '#nom', function() {
				validarNom()
			});
			$('.cntPage').on('focus', '#nom', function() {
				eliminarError('nom_cognom_erroni')
			});

			$('.cntPage').on('change', '#cog', function() {
				validarCognoms()
			});
			$('.cntPage').on('blur', '#cog', function() {
				validarCognoms()
			});
			$('.cntPage').on('focus', '#cog', function() {
				eliminarError('cognom_erroni')
			});

			$('.cntPage').on('change', '#nif', function() {
				if (tipusInsc == 'centre-escolar')
					validarCif();
			});
			$('.cntPage').on('blur', '#nif', function() {
				if (tipusInsc == 'centre-escolar')
					validarCif();
			});
			$('.cntPage').on('focus', '#nif', function() {
				if (tipusInsc == 'centre-escolar')
					eliminarError('dni_erroni')
			});

			$(".form-group #doc").on("click", "li", function(e) {
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
				if (tipusInsc != 'centre-escolar') mostrarDocumentacio(id);
			});

			$('.cntPage').on('change', '#telf', function() {
				validarTel()
			});
			$('.cntPage').on('blur', '#telf', function() {
				validarTel()
			});
			$('.cntPage').on('focus', '#telf', function() {
				eliminarError('telf_erroni')
			});

			$('.cntPage').on('change', '#email', function() {
				validarEmail(0)
			});
			$('.cntPage').on('blur', '#email', function() {
				validarEmail(0)
			});
			$('.cntPage').on('focus', '#email', function() {
				eliminarError('correu_erroni')
			});

			$('.cntPage').on('change', '#email_conf', function() {
				validarEmailConf(0)
			});
			$('.cntPage').on('blur', '#email_conf', function() {
				validarEmailConf(0)
			});
			$('.cntPage').on('focus', '#email_conf', function() {
				eliminarError('correu_conf_erroni')
			});

			$('.cntPage').on('change', '#adreca', function() {
				validarAdreca()
			});
			$('.cntPage').on('blur', '#adreca', function() {
				validarAdreca()
			});
			$('.cntPage').on('focus', '#adreca', function() {
				eliminarError('adreca_erroni')
			});

			$('.cntPage').on('change', '#cp', function() {
				validarCP()
			});
			$('.cntPage').on('blur', '#cp', function() {
				mostrarLlistatPoblacions();
				validarCP()
			});
			$('.cntPage').on('focus', '#cp', function() {
				eliminarError('cp_erroni')
			});

			$('.cntPage').on('change', '#poble', function() {
				validarPoble()
			});
			$('.cntPage').on('blur', '#poble', function() {
				validarPoble()
			});
			$('.cntPage').on('focus', '#poble', function() {
				eliminarError('poble_erroni')
			});

			//Quan es clica l'element amb id «enrere», anem a la pàgina anterior
			$('.cntPage').on('click', '#enrereForm', function() {
				pagina--;
				mostrarPagina();
			});

			//Quan es clica l'element amb id continua, comprovem que no hi hagi cap camp buit
			//Si no hi ha cap error, anem a la següent pàgina;
			//Si hi ha algun error, mostrem un modal amb l'error.
			$('.cntPage').on('click', '#continuaForm', function() {
				var comprovacio = '',
					validDoc = '',
					validNomCentre = '',
					validNom = validarNom(),
					validCognom = validarCognoms(),
					validTel = validarTel(),
					validEmailNoBuit = validarEmail(1),
					validEmailConfNoBuit = validarEmailConf(1),
					validAdreca = validarAdreca(),
					validCP = validarCP(),
					validPoble = validarPoble();

				if (tipusInsc != 'centre-escolar') {
					if ($("#doc .element-selected").html().trim() == 'NIF/NIE')
						validDoc = validarNif();
					else
						validDoc = validarPass();
				} else {
					validNomCentre = validarNomCentre();
					if (validNomCentre.length != 0) comprovacio += "<li>Nom del centre</li>";
					validDoc = validarCif();
				}

				if (validNom.length != 0) comprovacio += "<li>Nom</li>";
				if (validCognom.length != 0) comprovacio += "<li>Cognoms</li>";
				if (tipusInsc != 'centre-escolar') {
					if ($("#doc .element-selected").html().trim() == 'NIF/NIE') {
						if (validDoc.length != 0) comprovacio += "<li>Dni sense lletra</li>";
					} else {
						if (validDoc.length != 0) comprovacio += "<li>Número de passaport</li>";
					}
				} else {
					if (validDoc.length != 0) comprovacio += "<li>CIF</li>";
				}
				if (validTel.length != 0) comprovacio += "<li>Telèfon</li>";
				if (validEmailNoBuit.length != 0 || validEmailConfNoBuit.length != 0) comprovacio += "<li>Correu electrònic</li>";
				if (validAdreca.length != 0) comprovacio += "<li>Adreça postal</li>";
				if (validCP.length != 0) comprovacio += "<li>Codi postal</li>";
				if (validPoble.length != 0) comprovacio += "<li>Població</li>";

				if (comprovacio == '') {
					$(".cntPage").fadeOut(800, function() {
						if (tipusInsc == 'centre-escolar')
							nomCentreContacte = $('#nom_centre').val().trim();
						nomPersonaContacte = $('#nom').val().trim();
						cognomsPersonaContacte = $('#cog').val().trim();
						if (tipusInsc != 'centre-escolar') {
							if ($("#doc .element-selected").html().trim() == 'NIF/NIE')
								dniPersonaContacte = $('#nif').val().trim();
							else
								dniPersonaContacte = $('#pass').val().trim();
						} else {
							dniPersonaContacte = $('#nif').val().trim();
						}
						telefonPersonaContacte = $('#telf').val().trim();
						emailPersonaContacte = $('#email').val().trim();
						adrecaPersonaContacte = $('#adreca').val().trim();
						cpPersonaContacte = $('#cp').val().trim();
						poblacioPersonaContacte = $('#poble').val().trim();

						pagina++;
						mostrarPagina();
					});
				} else {
					var missatgeError = "<p>Els camps següents són incorrectes:</p>";
					missatgeError += "<ul class='errors'>" + comprovacio + "</ul>";
					mostrarModalError(missatgeError);
				}
			});
		});

		requestPagina3.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Page 3: ");
		});
	} else if (pagina == 4) { //mostra les dades del curs i les dades del grup
		if (!hemPassatPerLaPagina4) {
			codiPostal = '';
			hemPassatPerLaPagina4 = true;
		}

		var requestPagina4 = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/mostra_dades_curs_i_grup.php",
			method: "GET",
			data: {
				codi: codiCurs,
				numAlumnes: numAlumnesGrup,
				nomCentre: nomCentreContacte,
				nom: nomPersonaContacte,
				cognoms: cognomsPersonaContacte,
				dni: dniPersonaContacte,
				telefon: telefonPersonaContacte,
				email: emailPersonaContacte,
				adreca: adrecaPersonaContacte,
				cp: cpPersonaContacte,
				poblacio: poblacioPersonaContacte
			},
			dataType: "html"
		});

		requestPagina4.done(function(page) {
			$(".cntPage").html(page);
			$(".cntPage").fadeIn(800);
			$('html, body').animate({
				scrollTop: 0
			}, 'slow');

			mostrarDates(0);

			comHasConegut();

			//Afegeix i elimina la classe "active" del input que s'ha clicat a sobre seu
			$('.form-group').on('focus', '.form-control', function() {
				$(this).prev().addClass('active');
			});
			$('.form-group').on('blur', '.form-control', function() {
				if ($(this).val().trim() == '')
					$(this).prev().removeClass('active');
			});

			$('.form-dades').on('focus', '.form-control', function() {
				$(this).prev().addClass('active');
			});
			$('.form-dades').on('blur', '.form-control', function() {
				if ($(this).val().trim() == '')
					$(this).prev().removeClass('active');
			});

			mostrarDocumentacio('doc-dni');

			$(".form-group .select").click(function(e) {
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

			$('.cntPage').on('change', '#nom', function() {
				validarNom()
			});
			$('.cntPage').on('blur', '#nom', function() {
				validarNom()
			});
			$('.cntPage').on('focus', '#nom', function() {
				eliminarError('nom_cognom_erroni')
			});

			$('.cntPage').on('change', '#cog', function() {
				validarCognoms()
			});
			$('.cntPage').on('blur', '#cog', function() {
				validarCognoms()
			});
			$('.cntPage').on('focus', '#cog', function() {
				eliminarError('cognom_erroni')
			});

			$(".form-group #doc").on("click", "li", function(e) {
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

			$('.cntPage').on('change', '#telf', function() {
				validarTel()
			});
			$('.cntPage').on('blur', '#telf', function() {
				validarTel()
			});
			$('.cntPage').on('focus', '#telf', function() {
				eliminarError('telf_erroni')
			});

			$('.cntPage').on('change', '#email', function() {
				validarEmail(0)
			});
			$('.cntPage').on('blur', '#email', function() {
				validarEmail(0)
			});
			$('.cntPage').on('focus', '#email', function() {
				eliminarError('correu_erroni')
			});

			$('.cntPage').on('change', '#email_conf', function() {
				validarEmailConf(0)
			});
			$('.cntPage').on('blur', '#email_conf', function() {
				validarEmailConf(0)
			});
			$('.cntPage').on('focus', '#email_conf', function() {
				eliminarError('correu_conf_erroni')
			});

			$('.cntPage').on('change', '#adreca', function() {
				validarAdreca()
			});
			$('.cntPage').on('blur', '#adreca', function() {
				validarAdreca()
			});
			$('.cntPage').on('focus', '#adreca', function() {
				eliminarError('adreca_erroni')
			});

			$('.cntPage').on('blur', '#cp', function() {
				mostrarLlistatPoblacions();
			});
			$('.cntPage').on('focus', '#cp', function() {
				eliminarError('cp_erroni')
			});

			$('.cntPage').on('change', '#poble', function() {
				validarPoble()
			});
			$('.cntPage').on('blur', '#poble', function() {
				validarPoble()
			});
			$('.cntPage').on('focus', '#poble', function() {
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
				mostrarDates(1);
			});

			//Quan es clica l'element amb id afegeix-alumne, mostrem un modal afegirAlumne
			$('.cntPage').on('click', '#afegeix-alumne', function() {
				mostrarModalAfegirAlumne();
			});
			//Quan es clica l'element amb id afegeix-alumne, mostrem un modal afegirAlumne
			$('.cntPage').on('click', '#form_afegir_alumne', function() {
				codiPostal = '';
				//cal afegir la info a les dades del grups
				var comprovacio = '',
					validDoc = '',
					validNom = validarNom(),
					validCognom = validarCognoms(),
					validTel = validarTel(),
					validEmailNoBuit = validarEmail(1),
					validEmailConfNoBuit = validarEmailConf(1),
					validAdreca = validarAdreca(),
					validPoble = validarPoble(),
					validPerfil = validarPerfils(),
					validTitulacio = validarTitulacio();

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
				if (validEmailNoBuit.length != 0 || validEmailConfNoBuit.length != 0) comprovacio += "<li>Correu electrònic</li>";
				if (validAdreca.length != 0) comprovacio += "<li>Adreça postal</li>";
				if (validPoble.length != 0) comprovacio += "<li>Població</li>";
				if (validPerfil.length != 0) comprovacio += "<li>Estic treballant a</li>";
				if (validTitulacio.length != 0) comprovacio += "<li>Tinc la titulació de</li>";

				if (comprovacio != "") {
					var missatgeError = "<p>Els camps següents són incorrectes:</p><ul class='errors'>" + comprovacio + "</ul>";
					mostrarModalError(missatgeError);
				} else {
					var nomAlumne = $("#nom").val().trim(),
						cogAlumne = $("#cog").val().trim(),
						dniAlumne = '',
						telAlumne = $("#telf").val().trim(),
						emailAlumne = $("#email").val().trim(),
						adrecaAlumne = $("#adreca").val().trim(),
						cpAlumne = $("#cp").val().trim(),
						pobleAlumne = $("#poble").val().trim(),
						perfilAlumne = $("#perfil .element-selected").html().trim(),
						perfilAlumneAfegit = '',
						perfilAlumneTotal = '',
						titulacioAlumneAfegit = '',
						titulacioAlumne = $("#titulacio .element-selected").html().trim(),
						titulacioAlumneTotal = '',
						tbTitulacioAlumne = $("#titol_de").val().trim(),
						comentarisAlumne = $("#comentaris").val().trim();

					if ($("#doc .element-selected").html().trim() == 'NIF/NIE')
						dniAlumne = $('#nif').val().trim();
					else
						dniAlumne = $('#pass').val().trim();

					if (perfilAlumne == $('#perfil #perfil-altres a').html().trim())
						perfilAlumneAfegit += ": " + $("#perfil_de").val().trim();

					perfilAlumneTotal = perfilAlumne + perfilAlumneAfegit;

					if (titulacioAlumne == $('#titulacio #titulacio-altres a').html().trim())
						titulacioAlumneAfegit += ', ' + $("#titol_altres").val().trim();
					else if (titulacioAlumne == $('#titulacio #titulacio-edSecundaria a').html().trim())
						titulacioAlumneAfegit += ', ' + $("#titol_secundaria").val().trim();
					else if (titulacioAlumne == $('#titulacio #titulacio-estudiant a').html().trim())
						titulacioAlumneAfegit += ', ' + $("#titol_estudiant").val().trim();

					if (tbTitulacioAlumne != '')
						titulacioAlumneAfegit += ', També tinc la titulació de: ' + tbTitulacioAlumne;

					titulacioAlumneTotal = titulacioAlumne + titulacioAlumneAfegit;
					//afegir la info de l'alumne en el grup
					dadesGrup[numAlumnesGrup] = [
						nomAlumne,
						cogAlumne,
						dniAlumne,
						telAlumne,
						emailAlumne,
						adrecaAlumne,
						cpAlumne,
						pobleAlumne,
						perfilAlumneTotal,
						titulacioAlumneTotal,
						comentarisAlumne
					];

					var afegirAlumne = $.ajax({
						async: !0,
						url: "https://www.prisma.cat/ajax/afegirAlumne_desompteGrup.php",
						method: "GET",
						data: {
							nom: nomAlumne,
							cog: cogAlumne,
							dni: dniAlumne,
							tel: telAlumne,
							email: emailAlumne,
							adreca: adrecaAlumne,
							cp: cpAlumne,
							poble: pobleAlumne,
							perfil: perfilAlumneTotal,
							titulacio: titulacioAlumneTotal,
							comentaris: comentarisAlumne
						},
						dataType: "html"
					});

					afegirAlumne.done(function(page) {
						//amagar el modal
						amagarModalAfegirAlumne();
						$('.modal-backdrop').remove();
						$('body').removeClass('modal-open');
						$('body').css('padding-right', '0');
						//augmentar el total d'alumnes
						numAlumnesGrup++;
						//mostra les dades del curs i grup
						mostrarPagina();
					});

					afegirAlumne.fail(function(jqXHR, textStatus, errorThrown) {
						errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'afegir l'alumne: ");
					});
				}
			});

			//Quan es clica l'element amb id «enrere», anem a la pàgina anterior
			$('.cntPage').on('click', '#enrereForm', function() {
				pagina--;
				mostrarPagina();
			});

			//Quan es clica l'element amb id continua, comprovem que com a minim hi hagi tres alumne
			//Si no hi ha cap error, anem a la següent pàgina;
			//Si hi ha algun error, mostrem un modal amb l'error.
			$('.cntPage').on('click', '#continuaForm', function() {
				var validDates = validarDates();
				var validConegut = validarConegut();

				if (validDates.length != 0) {
					var missatgeError = "<p>Cal escollir unes dates del curs</p>";
					mostrarModalError(missatgeError);
				} else if (validConegut.length != 0) {
					var missatgeError = "<p>Cal indicar com heu conegut al curs</p>";
					mostrarModalError(missatgeError);
				} else if (dadesGrup.length > 2) {
					dates = $("#dates .element-selected").html().trim();
					conegut = $("#comConegut .element-selected").html().trim();
					$(".cntPage").fadeOut(800, function() {
						pagina++;
						mostrarPagina();
					});
				} else {
					var missatgeError = "<p>Cal introduir com a mínim tres alumnes</p>";
					mostrarModalError(missatgeError);
				}

			});
		});

		requestPagina4.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Page 4: ");
		});
	} else if (pagina == 5) { //mostra el resum de les dades de contacte, del curs i del grup
		var requestPagina5 = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/mostrar_resum_dades_descompte_grup.php",
			method: "GET",
			data: {
				codi: codiCurs,
				numAlumnes: numAlumnesGrup,
				tipusInsc: tipusInsc,
				dates: dates,
				conegut: conegut,
				any: any,
				edicio: edicio
			},
			dataType: "html"
		});

		requestPagina5.done(function(page) {
			$(".cntPage").html(page);
			$(".cntPage").fadeIn(800);
			$('html, body').animate({
				scrollTop: 0
			}, 'slow');

			$('.form-group').on('focus', '.form-control', function() {
				$(this).prev().addClass('active');
			});
			$('.form-group').on('blur', '.form-control', function() {
				if ($(this).val().trim() == '')
					$(this).prev().removeClass('active');
			});

			enviamentPubli(emailPersonaContacte);

			//Quan es clica l'element amb id «enrere», anem a la pàgina anterior
			$('.cntPage').on('click', '#enrereForm', function() {
				pagina--;
				mostrarPagina();
			});

			//Quan es clica l'element amb id continua, comprovem que com a minim hi hagi tres alumne
			//Si no hi ha cap error, anem a la següent pàgina;
			//Si hi ha algun error, mostrem un modal amb l'error.
			$('.cntPage').on('click', '#continuaForm', function() {
				$("#modalLoading").modal('show');
				enviarDades();
			});

		});

		requestPagina5.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Page 5: ");
		});
	} else {
		//
	}
}

if ( urlPagina.split('/')[3] ) {
	splitUrl = urlPagina.split('/');
	tipusInsc = splitUrl[3];
	pagina = 3;
	urlPagina = "/"+splitUrl[1]+"/"+splitUrl[2];

	var requestCodiCurs = $.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/obtenirCodiCurs.php",
		method: "GET",
		data: {
			url: urlPagina
		},
		dataType: "html"
	});
	requestCodiCurs.done(function(codi_curs) {
		codiCurs = codi_curs;
		var requestPagina1 = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/mostrar_pagina_descompte_grup.php",
			method: "GET",
			dataType: "html"
		});

		requestPagina1.done(function(page) {
			$("#contingut").html(page);
			$(".cntPage").html();
			mostrarPagina();
		});

		requestPagina1.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Page 2: ");
		});
	});

	requestCodiCurs.fail(function(jqXHR, textStatus, errorThrown) {
		errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request del codi del curs: ");
	});
}
else {
	if (urlPagina.split('/')[2]) {
		pagina = 2; //ens indica que estem a la pàgina del descompte d'un curs seleccionat
		//crida ajax per obtenir el codi del curs
		codiCurs = '';


		var requestCodiCurs = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/obtenirCodiCurs.php",
			method: "GET",
			data: {
				url: urlPagina
			},
			dataType: "html"
		});

		requestCodiCurs.done(function(codi_curs) {
			codiCurs = codi_curs;

			var requestPagina1 = $.ajax({
				async: !0,
				url: "https://www.prisma.cat/ajax/mostrar_pagina_descompte_grup.php",
				method: "GET",
				dataType: "html"
			});

			requestPagina1.done(function(page) {
				$("#contingut").html(page);
				$(".cntPage").html();
				mostrarPagina();
			});

			requestPagina1.fail(function(jqXHR, textStatus, errorThrown) {
				errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Page 2: ");
			});

		});

		requestCodiCurs.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request del codi del curs: ");
		});
	} else {
		mostrarPagina();
	}
}

//Mostra els cursos de X hores
function mostraCursos(hores) {
	$('#cnt-hores button').removeClass('marcat');
	$('#hores-' + hores).addClass('marcat');
	$("#modalCercantCursos").modal('show');

	var request = $.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/mosrar_cursos_descompte_grup.php",
		method: "GET",
		data: {
			hores: hores,
			dispositiu: dispositiu
		},
		dataType: "html"
	});

	request.done(function(cntCursos) {
		$("#cnt-cursos").html(cntCursos);
		$("#cnt-hores").addClass('mb-4 border-bottom');
		$("#modalCercantCursos").modal('hide');
	});

	request.fail(function(jqXHR, textStatus, errorThrown) {
		errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request mostrar cursos per hores: ");
	});
}

//Mostra un tastet del curs "codi"
function mostrarTaset(codi) {
	if (!$('#veure_tastet_' + codi)[0]) {
		var request = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/mostrar_tastet.php",
			method: "GET",
			data: {
				codi: codi,
				dispositiu: dispositiu
			},
			dataType: "html"
		});

		request.done(function(tastet) {
			$("#contingut").append(tastet);

			//Mostrar el video de la presentacio del curs quan cliques la caratula
			var video = $('#veure_tastet_' + codi + " .video-responsive");
			video.on("click", function() {
				var obj = $(this);
				$(this).find('iframe')[0].src = $(this).find('iframe')[0].src.replace("autoplay=0", "autoplay=1");
				setTimeout(function() {
					obj.find('button').hide();
					obj.find('img').hide();
				}, 500);
			});

			//Pausar el video quan surts del modal de veure un tastet
			$('#veure_tastet_' + codi).on('hide.bs.modal', function(e) {
				if ($(this).find('iframe')[0]) {
					var src = $(this).find('iframe')[0].src;
					$(this).find('iframe')[0].src = src;
					$(this).find('iframe')[0].src = src.replace("autoplay=1", "autoplay=0");
					$(this).find('img').show();
					$(this).find('button').show();
				}
			});

			$('#veure_tastet_' + codi).modal('show');
		});

		request.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora de mostrar el tastet: ");
		});
	} else {
		$('#veure_tastet_' + codi).modal('show');
	}

}

//Desapareix el contingut anterior i mostra la pàgina del formulari de la targeta regal
function realitzaCurs(curs) {
	$(".cntPage").fadeOut(800, function() {
		pagina++;
		codiCurs = curs;
		mostrarPagina();
	});
}
function mostraInfoCurs(link) {
	window.open(link, '_blank');
}
function enviarDades() {
	var comentaris = $("#comentaris").val().trim(),
		mailing = 'registred';

	if ($("#valmail").val() == '1') {
		if ($("#radio_mailing_yes").is(":checked"))
			mailing = "yes";
		else
			mailing = "no";
	}

	if (($("#valmail").val() == '1' && (($("#radio_mailing_yes").is(":checked")) || ($("#radio_mailing_no").is(":checked")))) ||
		$("#valmail").val() == '0') {
		var requestDades = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/enviaDades_DescompteGrup.php",
			method: "GET",
			data: {
				comentaris: comentaris,
				mailing: mailing,
				tipusInsc: tipusInsc
			},
			dataType: "html"
		});

		requestDades.done(function(page) {
			$('#modalLoading').modal('hide');
			var preuDescompte = $('.preu_descompte').html();
			if (!page.toLowerCase().includes("error")) {
				var urlConf = "https://www.prisma.cat/descompte-curs-grup/confirmacio/";
				urlConf +=  page.trim();
				//redireccioUrl a /confirmacio/url-amigable/idInsc on la idIsc està codificada amb hash
				window.location.replace(urlConf);
			} else {
				mostrarModalError(page);
				//enviar un missatge a suport
			}
		});

		requestDades.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora d'enviar les dades: ");
		});
	}
}

//Mostra el modal d'afegir alumne
function mostrarModalAfegirAlumne() {
	$("#modalAfegirAlumne").modal('show');
	$('#modalAfegirAlumne input').val('');
}

//Amaga el modal d'afegir alumne
function amagarModalAfegirAlumne() {
	$("#modalAfegirAlumne").modal('hide');
}



/* S'elimina tots els events que poden donar conflicte amb altres pàgines */
function removeEvents() {
	console.log('remove events');
	$('.form-group').off();
	$('.cntPage').off();
	$(".form-group .select").off();
	$(".form-group #doc").off();
	$(".form-group .cnt-poble").off();
	$(".form-dades #perfil").off();
	$(".form-dades #titulacio").off();
	$("#input_doc").off();
	$(".form-dades").off();
}

function mostrarDocumentacio(id) {
	var nomCamp = 'DNI amb lletra';
	var idInput = 'nif';
	var idError = 'dni_erroni';
	var input = "<div class='form-group field-wrap position-relative w-100'>";
	input += "<label class='position-absolute mb-0'>";
	input += "<span class='camp'>" + nomCamp + "</span>";
	input += "<span class='req'>*</span>";
	input += "</label>";
	input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "'>";
	input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
	$('#input_doc').html(input);

	$('#input_doc').off();

	$('#input_doc').on('focus', '#nif', function() {
		$(this).prev().addClass('active');
	});
	$('#input_doc').on('blur', '#nif', function() {
		if ($(this).val().trim() == '')
			$(this).prev().removeClass('active');
	});

	$('.cntPage').on('change', '#' + idInput, function() {
		validarNif();
		documentacio = $('#nif').val().trim();
	});
	$('.cntPage').on('blur', '#' + idInput, function() {
		validarNif();
		documentacio = $('#nif').val().trim();
	});
	$('.cntPage').on('focus', '#' + idInput, function() {
		eliminarError(idError)
	});

	if (id == 'doc-passaport') {
		var nomCamp = 'Número de passaport';
		var idInput = 'pass';
		var idError = 'dni_erroni';
		var input = "<div class='form-group field-wrap position-relative w-100'>";
		input += "<label class='position-absolute mb-0'><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
		input += "<input type='text' class='form-control' id='" + idInput + "' name='" + idInput + "'>";
		input += "<span id='" + idError + "' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
		$('#input_doc').html(input);

		$('#input_doc').on('focus', '#pass', function() {
			$(this).prev().addClass('active');
		});
		$('#input_doc').on('blur', '#pass', function() {
			if ($(this).val().trim() == '')
				$(this).prev().removeClass('active');
		});

		$('#' + idInput).focus();

		$('.cntPage').on('change', '#' + idInput, function() {
			validarPass();
			documentacio = $('#pass').val().trim();
		});
		$('.cntPage').on('blur', '#' + idInput, function() {
			validarPass();
			documentacio = $('#pass').val().trim();
		});
		$('.cntPage').on('focus', '#' + idInput, function() {
			eliminarError(idError)
		});
	}
}

function mostrarLlistatPoblacions() {
	if (validarCP().length == 0) {
		var cp = $("#cp").val().trim();
		$("#llistat_poblacions").show();

		if (codiPostal != cp) {
			var searchPoble = $.ajax({
				async: !0,
				url: "https://www.prisma.cat/ajax/buscarPoblacio.php",
				method: "GET",
				data: {
					cp: cp
				},
				dataType: "html"
			});

			searchPoble.done(function(llistat) {
				$("#llistat_poblacions").html(llistat);
				codiPostal = cp;
				$("#llistat_poblacions").show();

				$(".cnt-poble").on("click", "li", function(e) {
					var texto = $(this).text(),
						element = $(this).parent().prev(),
						lista = $(this).closest("ul"),
						triangle = $(this).parent().next(),
						id = $(this).attr('id');
					e.preventDefault();
					e.stopPropagation();
					lista.hide();
					var valorPoble = $('#' + id + " a").html().trim();
					$('#poble').val(valorPoble);
					$('.cnt-poble label').addClass('active');
				});
			});

			searchPoble.fail(function(jqXHR, textStatus, errorThrown) {
				errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora de buscar els pobles corresponents al CP: ");
			});
		}
	}
}

function mostrarPerfil(id) {
	var dataExp = "<p class='mb-3'>Els nostres cursos compten com Formació Permanent del Professorat sempre que es realitzin posteriorment a la data d’expedició del títol d’accés a la docència (Magisteri o CAP / Màster en Educació Secundària).</p>";
	$('#perfils-altres').html('');
	//Si se selecciona Altres, apareix el input: Estic treballant a....
	if (id == 'perfil-altres') {
		var input = "<div class='form-group field-wrap position-relative w-100'>";
		input += "<label class='position-absolute mb-0'><span class='camp'>Estic treballant a...</span><span class='req'>*</span></label>";
		input += "<input type='text' class='form-control' id='perfil_de' name='perfil_de' maxlength='150'>";
		input += "<span id='perfil_altres_erroni' class='d-flex justify-content-center align-items-center px-2 position-absolute text-center text-white'></span></div>";
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
		var input = "<div class='form-group field-wrap position-relative w-100'>";
		input += "<label class='position-absolute mb-0'><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
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
		var input = "<div class='form-group field-wrap position-relative w-100'>";
		input += "<label class='position-absolute mb-0'><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
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
		var input = "<div class='form-group field-wrap position-relative w-100'>";
		input += "<label class='position-absolute mb-0'><span class='camp'>" + nomCamp + "</span><span class='req'>*</span></label>";
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

//Comprovem si existeix el correu mail a la BD de mailing. Si no existeix, afegim el correu amb nom "nom", cognoms "cog" i correu "mail"
function enviamentPubli(mail) {
	var enviament = $.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/enviamentPubli.php",
		method: "GET",
		data: {
			mail: mail
		},
		dataType: "html"
	});

	enviament.done(function(page) {
		$("#txtHint_mailing").html(page);
	});

	enviament.fail(function(jqXHR, textStatus, errorThrown) {
		errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request del correu: ");
	});
}

/* ############################### VALIDACIONS ############################### */
function validarNomCentre() {
	var error = validacioCampBuit("nom_centre", "nom_centre_erroni");
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

function validarCif() {
	var nif = $('#nif').val().trim();
	var error = "";

	var error = validacioCampBuit("nif", "dni_erroni");;

	if (nif.length != 0) {
		var stripped = nif.replace(/[\(\)\.\-\ ]/g, '');
		var length = nif.length;
		var valor = nif.slice(1,nif.length);
		if (!(stripped.length == 9)) {
			error = "Llargada incorrecta";
			mostrarError("dni_erroni", error);
		} else {
			var firstPos = nif.charAt(0);
			if (firstPos == 'K' || firstPos == 'P' || firstPos == 'Q' || firstPos == 'S') {
				//Comprovo si el cif es tot numeros menys l'ultim digit i a la ultima posicio hi ha una lletra
				var correcte = true;
				var x = 1;
				while (x < length - 2 && correcte) {
					v1 = valor.slice(x, x+1);
					v2 = parseInt(v1);
					if (isNaN(v2)) correcte = false;
					x++;
				}
				if (!correcte) {
					error = "CIF erroni";
					mostrarError("dni_erroni", error);
				}
				else {
					var lletra = nif.charAt(8);
					if (!(esAlphabetic(lletra))) {
						error = "CIF erroni";
						mostrarError("dni_erroni", error);
					}
				}
			}
			else if (firstPos == 'A' || firstPos == 'B' || firstPos == 'E' || firstPos == 'H') {
				//Comprovo si el cif es tot numeros
				var correcte = true;
				var x = 1;
				while (x < length - 1 && correcte) {
					v1 = valor.slice(x, x+1);
					v2 = parseInt(v1);
					if (isNaN(v2)) correcte = false;
					x++;
				}

				if (!correcte) {
					error = "CIF erroni";
					mostrarError("dni_erroni", error);
				}
			}
			else if (firstPos == 'C' || firstPos == 'D' || firstPos == 'F' || firstPos == 'G' || firstPos == 'L' || firstPos == 'M' || firstPos == 'N') {
				//Si el cif comença per C, D, F, G, L, M, N els seguents digits han de ser o tots numeros o tots numeros menys l'últim digit que ha de ser lletra

				//Comprovo si el cif es tot numeros menys l'ultim digit i a la ultima posicio hi ha una lletra
				var correcte = true;
				var x = 1;
				while (x < length - 2 && correcte) {
					v1 = valor.slice(x, x+1);
					v2 = parseInt(v1);
					if (isNaN(v2)) correcte = false;
					x++;
				}

				if (!correcte) {
					error = "CIF erroni";
					mostrarError("dni_erroni", error);

					//Comprovo si el cif es tot numeros
					var correcte = true;
					var x = 1;
					while (x < length - 1 && correcte) {
						v1 = valor.slice(x, x+1);
						v2 = parseInt(v1);
						if (isNaN(v2)) correcte = false;
						x++;
					}

					if (!correcte) {
						error = "CIF erroni";
						mostrarError("dni_erroni", error);
					}
				} else {
					var lletra = nif.charAt(8);
				}


			}
			else if (firstPos == 'R' || firstPos == 'J' || firstPos == 'U') {

			}
			else {
				error = "CIF erroni";
				mostrarError("dni_erroni", error);
			}
		}
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
		if (error.length == 0 && $('#email_conf').val().trim() != '' && inici == 0) correuAdvert();
	}
	return error;
}

//Validar la confirmació del correu electrònic.
//Apareix el modal d'avis del correu electrònic si inici és 0 i no hi ha cap error
function validarEmailConf(inici) {
	var error = validacioCampBuit("email_conf", "correu_conf_erroni");
	if (error.length == 0) {
		error = validarCorreu();
		if (error.length == 0 && $('#email').val().trim() != '' && inici == 0) correuAdvert();
	}
	return error;
}

function validarCorreu() {
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
			}
		}
	}
	return error;
}

function correuAdvert() {
	var email = $('#email').val().trim();
	var correu = email.split("@")[1];

	if (!$('#modalCorreuValid').hasClass('show')) {
		var searchEmailNoValid = $.ajax({
			async: !0,
			url: "https://www.prisma.cat/ajax/buscarCorreusNoValid.php",
			method: "GET",
			dataType: "html"
		});

		searchEmailNoValid.done(function(esCorreuValid) {
			if (esCorreuValid != '') { //(hotmail.es,hotmail.com,yahoo.es|Hotmail,Yahoo!)
				var vectEl = esCorreuValid.split(","),
					vectTermAdvert = vectEl[0].split("|"),
					vectNomTermAdvert = vectEl[1].split("|"),
					correuActValid = true,
					vc = 0;
				while (vc < vectTermAdvert.length && correuActValid) {
					if (vectTermAdvert[vc].toLowerCase() == correu.toLowerCase())
						correuActValid = false;
					else
						vc++;
				}

				if (!correuActValid) {
					var terminacions = '';
					for (var vc = 0; vc < vectNomTermAdvert.length; vc++) {
						if (vc > 0) {
							if (vc == vectNomTermAdvert.length - 1)
								terminacions += ' i ';
							else
								terminacions += ', ';
						}
						terminacions += "<span><em>" + vectNomTermAdvert[vc] + "</em></span> ";
					}
					var msgInscModal = "<p>Et recomanem que posis un altre correu ";
					msgInscModal += "electrònic si el tens, atès que els comptes ";
					msgInscModal += "de " + terminacions + " poden no rebre els ";
					msgInscModal += "missatges del Campus i de Secretaria.</p>";
					msgInscModal += "<p class='mb-0'>Gràcies.</p>";
					$("#modalCorreuValidBody").html(msgInscModal);
					$('#modalCorreuValid').modal('show');
				}
			}
		});

		searchEmailNoValid.fail(function(jqXHR, textStatus, errorThrown) {
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora de buscar els correu no vàlids: ");
		});
	}
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
		} else if (isNaN(parseInt(stripped))) {
			error = "Caràcters no permesos";
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

	if ($("#perfil .element-selected").html().trim() == 'Estic treballant a') {
		error = "Camp obligatori";
		mostrarError("perfil_erroni", error);
	} else {
		eliminarError("perfil_erroni");
		if (perfil == $('#perfil-altres a').html().trim())
			error = validacioCampBuit("perfil_de", "perfil_altres_erroni");
	}
	return error;
}

function validarTitulacio() {
	var titulacio = $('#titulacio .element-selected').html().trim();
	var error = '';

	if ($("#titulacio .element-selected").html().trim() == 'Tinc la titulació de') {
		error = "Camp obligatori";
		mostrarError("titulacio_erroni", error);
	} else {
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

function validarDates() {
	var error = '';

	if ($("#dates .element-selected").html().trim() == "Durant quines dates voleu realitzar el curs? Tria l'edició") {
		error = "Camp obligatori";
		mostrarError("dates_erroni", error);
	} else {
		eliminarError("dates_erroni");
	}
	return error;
}

function validarConegut() {
	var com_conegut = $('#comConegut .element-selected').html().trim();
	var error = '';

	if ($("#comConegut .element-selected").html().trim() == 'Com heu conegut aquest curs? Tria una opció') {
		error = "Camp obligatori";
		mostrarError("conegut_erroni", error);
	} else {
		eliminarError("conegut_erroni");
		if (com_conegut == $('#comConegut #conegut-altres	').html().trim())
			error = validacioCampBuit("c_altres", "conegut_altres_erroni");
	}
	return error;
}

//Comprovar si l'edicio sel·leccionada és una edició reconeguda
//S ino és una edició reconeguda, apreixerà una frase indicant que l'edicio no està reconeguda.
function edicioNoReconeguda() {
	$('#missInformatiuEdicioRec').html('');
	$('#missInformatiuEdicioRec').removeClass('mb-3');

	var requestEdicioRec = $.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/edicioNoReconeguda.php",
		method: "GET",
		data: {
			edicio: edicio,
			any: any,
			curs: codiCurs
		},
		dataType: "html"
	});

	requestEdicioRec.done(function(data) {
		$('#missInformatiuEdicioRec').html(data);
		if (data != '') $('#missInformatiuEdicioRec').addClass('mb-3');
	});

	requestEdicioRec.fail(function(jqXHR, textStatus, errorThrown) {
		errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora de buscar si és una edició reconeguda: ");
	});
}

function edicioSensePerfil() {
	var numEd = $('#dates .select-list li').length;
	$('#missInformatiuEdicioPerf').html('');
	$('#missInformatiuEdicioPerf').removeClass('mb-3');

	var requestEdicioRec = $.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/cursTePerfil.php",
		method: "GET",
		data: {
			curs: codiCurs
		},
		dataType: "html"
	});

	requestEdicioRec.done(function(cursTeAlgunPerfil) {
		if (cursTeAlgunPerfil == 'true') {
			var requestPerfil = $.ajax({
				async: !0,
				url: "https://www.prisma.cat/ajax/edicioTePerfil.php",
				method: "GET",
				data: {
					edicio: edicio,
					any: any,
					curs: codiCurs
				},
				dataType: "html"
			});

			requestPerfil.done(function(edicioTeAlgunPerfil) {
				if ((cursTeAlgunPerfil != edicioTeAlgunPerfil) && (edicio != '0')) {
					// serà 0 quan no hi hagi cap edició seleccionada
					var miss = "L'edició seleccionada encara està pendent de confirmar l'acreditació del perfil professional. ";
					miss += "Per a més informació, visiteu la pàgina de <a href='https://www.prisma.cat/perfils-professionals' ";
					miss += "role='button' title='Cursos de PrisMa amb perfils professionals'>perfils professionals</a>.";

					$('#missInformatiuEdicioPerf').html(miss);
					$('#missInformatiuEdicioPerf').addClass('mb-3');
				}
			});

			requestPerfil.fail(function(jqXHR, textStatus, errorThrown) {
				errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora d'obtenir si l'edició disposa d'algun perfil: ");
			});
		}
	});

	requestEdicioRec.fail(function(jqXHR, textStatus, errorThrown) {
		errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora d'obtenir si el curs disposa de perfil: ");
	});
}

//Afegim opcions de «m'han conegut»: 1. Me l'han recomenat. 2. Ho he vist a les Xarxes socials (Facebook, Instagram, Twiter...). 3. Altres...
//Si s'ha registrat al butlletí electrònic, afegim una opció: 3. He rebut el butlletí electrònic
function comHasConegut() {
	$('#listComConegut').hide();

	var requestComHasConegutPrisma = $.ajax({
		async: !0,
		url: "https://www.prisma.cat/ajax/comHasConegutPrisma.php",
		method: "GET",
		data: {
			val: emailPersonaContacte
		},
		dataType: "html"
	});

	requestComHasConegutPrisma.done(function(page) {
		if (!page.toLowerCase().includes("error")) {
			$("#listComConegut").html(page);

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
				mostrarConegut(id);
				validarConegut();
			});

			$('.form-dades').on('change', '#com_conegut', function() {
				mostrarConegut();
				validarConegut()
			});
		} else {
			mostrarModalError(page);
		}
	});

	requestComHasConegutPrisma.fail(function(jqXHR, textStatus, errorThrown) {
		errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request com has conegut el curs: ");
	});

}

//Si el input amb id "id" està buit, mostra l'error "Camp obligatori" a l'id id_error
function validacioCampBuit(id, id_error) {
	var error = "";

	if ($("#" + id).val().trim().length == 0) {
		error = "Camp obligatori";
		mostrarError(id_error, error);
	} else {
		eliminarError(id_error);
	}
	return error;
}

//Mostra l'error error a l'id id_error
function mostrarError(id_error, error) {
	$("#" + id_error).html(error);
	$("#" + id_error).addClass("erroni");
}

//Elimina l'error de l'id id_error
function eliminarError(id_error) {
	$("#" + id_error).html("");
	$("#" + id_error).removeClass("erroni");
}

function mostrarModalError(missatgeError) {
	$('#modalErrorsBody').html(missatgeError);
	$('#modalErrors').modal('show');
}

/* ############################### GENERAL ############################### */
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
	} else {
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
