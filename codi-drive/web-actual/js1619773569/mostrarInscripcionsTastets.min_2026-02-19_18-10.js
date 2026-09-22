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
let urlPart2 = vectUrlPagina[3];
urlPagina = "/" + vectUrlPagina[2] + "/" + vectUrlPagina[3];
tipus = 0;
if (vectUrlPagina[1] == 'inscripcions') tipus = 0;
else if (vectUrlPagina[1] == 'regala') tipus = 2;
else if (vectUrlPagina[1] == 'bescanvia') tipus = 3;

var codiCurs = '',
	tipusCurs = '',
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
		url: path + "ajax/mostrar_inscripcio_tastets.php",
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

		$('.form-dades').on('change', '#poble', function() {
			validarPoble()
		});
		$('.form-dades').on('blur', '#poble', function() {
			validarPoble()
		});
		$('.form-dades').on('focus', '#poble', function() {
			eliminarError('poble_erroni')
		});

		comHasConegut();

		$(".form-dades #form_enviar_dades").click(function(e) {
			var comprovacio = '',
				validDoc = '',
				validNom = validarNom(),
				validCognom = validarCognoms(),
				validEmailNoBuit = validarEmail(1),
				validEmailConfNoBuit = validarEmailConf(1),
				validPoble = validarPoble(),
				validConegut = validarConegut();

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
			if (validEmailNoBuit.length != 0 || validEmailConfNoBuit.length != 0) comprovacio += "<li>Correu electrònic</li>";
			if (validPoble.length != 0) comprovacio += "<li>Població</li>";
			if (validConegut.length != 0) comprovacio += "<li>Com has conegut el curs</li>";

			if (comprovacio != "") {
				var missatgeError = "<p>Els camps següents són incorrectes:</p><ul class='errors'>" + comprovacio + "</ul>";
				mostrarModalError(missatgeError);
			} else {
				$("#modalLoading").modal('show');
				comprovaSiHaRealitzatElTastet();
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
		url: path + "ajax/obtenirCodiTastet.php",
		data: {
			url: urlPagina
		},
		method: "GET",
		dataType: "html"
	});

	request.done(function(codi) {
		codiCurs = codi;
	});

	request.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de cercar el curs: ");
	});
}

//Afegim opcions de «m'han conegut»: 1. Me l'han recomenat. 2. Ho he vist a les Xarxes socials (Facebook, Instagram, Twiter...). 3. Altres...
//Si s'ha registrat al butlletí electrònic, afegim una opció: 3. He rebut el butlletí electrònic
function comHasConegut() {
	var email = "1";
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
		documentacio = $('#nif').val().trim();
	});
	$('.form-dades').on('blur', '#' + idInput, function() {
		validarNif();
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
			documentacio = $('#pass').val().trim();
		});
		$('.form-dades').on('blur', '#' + idInput, function() {
			validarPass();
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

//Validar el correu electrònic.
//Apareix el modal d'avis del correu electrònic si inici és 0 i no hi ha cap error
function validarEmail(inici) {
	var error = validacioCampBuit("email", "correu_erroni");
	if (error.length == 0) {
		error = validarCorreu();
		if (error.length == 0 && $('#email_conf').val().trim()!='' && inici==0) correuAdvert();
	}
	return error;
}

//Validar la confirmació del correu electrònic.
//Apareix el modal d'avis del correu electrònic si inici és 0 i no hi ha cap error
function validarEmailConf(inici) {
	var error = validacioCampBuit("email_conf", "correu_conf_erroni");
	if (error.length == 0) {
		error = validarCorreu(inici);
		if (error.length == 0 && $('#email').val().trim()!='' && inici==0) correuAdvert();
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
			}
		}
	}
	return error;
}

function correuAdvert() {
	var email = $('#email').val().trim();
	var correu = email.split("@")[1];

	if ( !$('#modalCorreuValid').hasClass('show') ) {
		$.ajax({
			url: path + "ajax/buscarCorreusNoValid.php",
			cache: false,
			type: "GET",
			success: function(esCorreuValid) {
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
							terminacions += "<span><em>"+vectNomTermAdvert[vc]+"</em></span> ";
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
			}
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

/*
   Comprova si ha realitzat el curs xxx.
   Si no l'ha realitzat, comprova si ha realitzat el curs del qual deriva. Si l'ha realitzat
	mostra el modal amb la frase "Has realitzat el curs XXX a l'edició XXX de l'any XXX" i pregunta si vol continuar la inscripcio
	Si l'ha realitzat,
	mostra el modal amb la frase "Has realitzat el curs XXX a l'edició XXX de l'any XXX" i pregunta si vol continuar la inscripcio
*/
function comprovaSiHaRealitzatElTastet() {
	var vectorHaRealitzatElCurs=[],
		 titolHaRealitzatElCurs='',
		 anyHaRealitzatElCurs=0,
		 edicioHaRealitzatElCurs='';
	//Consulta ajax per comprovar si el usuari XXX ha realitzat el curs xxx (retornar l'edicio|any en que va fer-lo)

	var request = $.ajax({
		url: path + "ajax/buscarSiHaRealitzatElTastet.php",
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
			dataHaRealitzatElCurs = vectorHaRealitzatElCurs[1];

			//Si ha realitzat el curs, mostra el modal per continuar la inscripcio
			var msgInscModal = "<p>Ja t'has inscrit en el tastet <span>"+titolHaRealitzatElCurs+"</span> ";
			msgInscModal += "<span>"+dataHaRealitzatElCurs+".</p>";
			$("#modalInscripcioDuplicadaBody").html(msgInscModal);
			$("#modalLoading").modal('hide');
			$("#modalInscripcioDuplicada").modal('show');

		}
		else {
			if ( !haRealitzatElCurs.toLowerCase().includes("error") ) {
					enviarInscripcio();
			}
		}


	});

	request.fail(function(jqXHRMailing, textStatusMailing, errorThrownMailing) {
		errorFunction(jqXHRMailing, textStatusMailing, errorThrownMailing, "Hi ha hagut un error a l'hora de buscar si ha realitzat el curs ");
	});
}

function enviarInscripcio() {
	//enviem inscripció
	//si no hi ha cap error, carrego la pàgina del pagament
	//si hi ha algun error, mostra el modal d'error

	var titolCurs = $('.nom-curs').html().trim(),
		 nom = $("#nom").val().trim(),
		 cog = $("#cog").val().trim(),
		 email = $("#email").val().trim(),
		 poblacio = $("#poble").val().trim(),
		 conegut =  $("#comConegut .element-selected").html().trim(),
		 comentaris =  $("#comentaris").val().trim(),
		 mailing = 'yes';

		 if ( conegut == "Altres (indica'ns com)" )
 			conegut = "Altres: " + $("#c_altres").val().trim();

		var sendInscr = $.ajax({
			url: path + "ajax/enviarInscripcioTastet.php",
			data: {
				nom: nom,
				cog: cog,
				dni: documentacio,
				email: email,
				poblacio: poblacio,
				conegut: conegut,
				comentaris: comentaris,
				mailing: mailing,
				codiCurs: codiCurs
			},
			method: "GET",
			dataType: "html"
		});

		sendInscr.done(function( msg ) {
			if ( !msg.toLowerCase().includes("error") && msg != '' ) {
				//buscar la part amigable de la url actual
				//retorna la url de /confirmacio/url-amigable/idInsc
				var urlConf = "https://www.prisma.cat/tastets/confirmacio/";
				urlConf += urlPart2 + "/" + msg.trim();
				// window.location.replace(urlConf);
				window.location.replace(urlConf);
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
