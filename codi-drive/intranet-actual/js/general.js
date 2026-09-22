/* ############################     RESPONSIVE     ############################ */

/* Quan es carrega la pàgina, s'ajusta l'estil per la mida de la pantalla. */
windowResize();

/* Quan es redimensiona la pàgina, s'ajusta l'estil per la mida de la pantalla. */
$(window).resize(windowResize);

/* Ajusta estil. Quan l'amplada de la pantala >= 991, el sidebar es
manté visible. Quan l'amplada de la pantalla < 991, el sidebar s'oculta */
function windowResize() {
	if ($(window).width() < 991) {
		// $(".sidebar").css('width', '0');
		$(".sidebar").removeClass('active');
		$(".mainpanel").removeClass('active');
	}
}

let rolsPageEdicio;
let rolsUsuari;
let tePermisEdicio = false;

/* ############################ ACCIONS GENERALS ############################ */

setTimeout(function() {
	window.location.href = "https://intranet.prisma.cat/";
}, 7200000);

let urlPaginaG = window.location.pathname.split('?')[0];
let pathG = "https://intranet.prisma.cat";
let nTooltipGlobal = 0;

/* ############################ ACCIONS DE FORMS ############################ */
$('body').on('focus', '.form-control', function() {
	$(this).prev().addClass('active');
});
$('body').on('blur', '.form-control', function() {
	if ($(this).val().trim() == '')
		$(this).prev().removeClass('active');
});

/* ############################    REQUEST MENÚ    ############################ */
/* Es busca l'estructura del menu lateral*/
var requestMenu = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarSideBar.php",
	method: "GET",
	dataType: "html"
});

/* Es mostra el menu lateral*/
requestMenu.done(function( message ) {
	/* Message conté l'estructura del menú lateral */
	$('.sidebar').html(message);

	/* Consultem el bloc de l'usuari del menú lateral */
	var requestBlocUser = $.ajax({
		url: "https://intranet.prisma.cat/ajax/mostrarSideBarUser.php",
		method: "GET",
		dataType: "html"
	});

	/* Mostrem el bloc de l'usuari del menú lateral */
	requestBlocUser.done(function( blocUsuari ) {
		$('.user').html(blocUsuari);

		$('.sidebar').on('click', '.logout', function() {
			window.location.href = "https://intranet.prisma.cat/";
		});

		$('.sidebar').on('click', '.photo', function() {
			window.location.href = "https://intranet.prisma.cat/perfil/mostra-perfil/";
		});
		$('.sidebar').on('click', '.user-info', function() {
			window.location.href = "https://intranet.prisma.cat/perfil/mostra-perfil/";
		});

		$('body').on('click', '.btn-menu', function() {
			var menuExt;
			if ($('.sidebar').hasClass('active') ) {
		      $('.sidebar').removeClass('active');
		      $('.mainpanel').removeClass('active');
				//Fer upd de l'estat del menu_ext a 0
				menuExt = 0;
			}
			else {
		      $('.sidebar').addClass('active');
		      $('.mainpanel').addClass('active');
				//Fer upd de l'estat del menu_ext a 1
				menuExt = 1;
			}

			var updActualitzaPassword = $.ajax({
				url: "https://intranet.prisma.cat/ajax/actualitzarDesplegableMenu.php",
				method: "POST",
				data: {
					menuExt : menuExt
				},
				dataType: "html"
			});

			updActualitzaPassword.done(function( resUpd ) {
				var requestMenuApartats = $.ajax({
					url: "https://intranet.prisma.cat/ajax/mostrarSideBarMenu.php",
					method: "GET",
					data: { url : urlPaginaG },
					dataType: "html"
				});

				/* Mostrem els apartats del menú lateral */
				requestMenuApartats.done(function( apartats ) {
					$('.sidebar > .sidebar-wrapper > .nav').html(apartats);
					if ($(".active").parent().parent().hasClass("collapse")) {
						$(".active").parent().parent().addClass('show');
					}

					$( ".sidebar li.nav-item" ).hover(function() {
						var thisItem,
							textLi = '',
							nTooltip = $(this).attr("data-nitem");

						if ( $(this).find("p")[0] ) {
							//Primer nivell de llista
							textLi = $(this).find("p").html().trim();
						}
						else {
							//Últim nivell de llista
							textLi = $(this).find(".sidebar-normal").html().trim();
						}

						if ( $(this).find("i")[0] ) {
							thisItem = $(this).find("i");
						}
						else {
							thisItem = $(this).find(".sidebar-mini");
						}
						// var topLi = thisItem.offset().top,
						// 	leftLi = thisItem.offset().left,
						// 	widthLi = thisItem.width(),
						// 	heightLi = thisItem.height(),
						// 	paddingYLi = parseFloat(thisItem.css('padding-top') + thisItem.css('padding-bottom')),
						// 	paddingXLi = parseFloat(thisItem.css('padding-left') + thisItem.css('padding-right')),
						// 	topTool = 0,
						// 	leftTool = 0,
						// 	cssTool = 0;
					   // if ( !$('.sidebar').hasClass('active') )  {
						// 	topTool = parseFloat(topLi) + parseFloat(paddingYLi/2);
						// 	leftTool = parseFloat(leftLi) + parseFloat(widthLi) + 10;
						// 	cssTool = "top: " + topTool + "px; ";
						// 	cssTool += "left: " + leftTool + "px; ";
						// 	$('.contingut').append( "<span id='tooltipPrisma" + nTooltip + '-' + nTooltipGlobal + "' class='tooltip-prisma px-3 py-1 text-white' style='" + cssTool + "'>" + textLi + "</span>" );
						// 	$('#tooltipPrisma' + nTooltip + '-' + nTooltipGlobal).fadeIn('fast');
						// 	nTooltipGlobal++;
						// }
					}, function() {
						if ( !$('.sidebar').hasClass('active') )  {
							var nTooltip = $(this).attr("data-nitem");
							$('.tooltip-prisma').each( function() {
								if ( parseInt($(this).attr('id').split('-')[1]) <  nTooltipGlobal ) {
									$('#' + $(this).attr('id')).fadeOut('fast', function(){
										$(this).remove();
									});
								}
							});

						}
					});
				});

				requestMenuApartats.fail(function( jqXHR, textStatus, errorThrown ) {
					errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request SideBar Menu: " );
				});
			});
		});
	});

	requestBlocUser.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request SideBar User: " );
	});

	/* Consultem els apartats del menú lateral */
	var requestMenuApartats = $.ajax({
		url: "https://intranet.prisma.cat/ajax/mostrarSideBarMenu.php",
		method: "GET",
		data: { url : urlPaginaG },
		dataType: "html"
	});

	/* Mostrem els apartats del menú lateral */
	requestMenuApartats.done(function( apartats ) {
		$('.sidebar > .sidebar-wrapper > .nav').html(apartats);
		if ($(".active").parent().parent().hasClass("collapse")) {
			$(".active").parent().parent().addClass('show');
		}

		$( ".sidebar li.nav-item" ).hover(function() {
			var thisItem,
				textLi = '',
				nTooltip = $(this).attr("data-nitem");

			if ( $(this).find("p")[0] ) {
				//Primer nivell de llista
				textLi = $(this).find("p").html().trim();
			}
			else {
				//Últim nivell de llista
				textLi = $(this).find(".sidebar-normal").html().trim();
			}

			if ( $(this).find("i")[0] ) {
				thisItem = $(this).find("i");
			}
			else {
				thisItem = $(this).find(".sidebar-mini");
			}
			var topLi = thisItem.offset().top,
				leftLi = thisItem.offset().left,
				widthLi = thisItem.width(),
				heightLi = thisItem.height(),
				paddingYLi = parseFloat(thisItem.css('padding-top') + thisItem.css('padding-bottom')),
				paddingXLi = parseFloat(thisItem.css('padding-left') + thisItem.css('padding-right')),
				topTool = 0,
				leftTool = 0,
				cssTool = 0;
		   if ( !$('.sidebar').hasClass('active') )  {
				topTool = parseFloat(topLi) + parseFloat(paddingYLi/2);
				leftTool = parseFloat(leftLi) + parseFloat(widthLi) + 10;
				cssTool = "top: " + topTool + "px; ";
				cssTool += "left: " + leftTool + "px; ";
				$('.contingut').append( "<span id='tooltipPrisma" + nTooltip + '-' + nTooltipGlobal + "' class='tooltip-prisma px-3 py-1 text-white' style='" + cssTool + "'>" + textLi + "</span>" );
				$('#tooltipPrisma' + nTooltip + '-' + nTooltipGlobal).fadeIn('fast');
				nTooltipGlobal++;
			}
		}, function() {
			if ( !$('.sidebar').hasClass('active') )  {
				var nTooltip = $(this).attr("data-nitem");
				$('.tooltip-prisma').each( function() {
					if ( parseInt($(this).attr('id').split('-')[1]) <  nTooltipGlobal ) {
						$('#' + $(this).attr('id')).fadeOut('fast', function(){
							$(this).remove();
						});
					}
				});

			}
		});
	});

	requestMenuApartats.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request SideBar Menu: " );
	});

	var reqMenuExt = $.ajax({
		url: "https://intranet.prisma.cat/ajax/consultaMenuLateralDesplegat.php",
		method: "GET",
		dataType: "html"
	});

	reqMenuExt.done(function( res ) {
		if ( res == "1" ) {
			$('.sidebar').addClass('active');
			$('.mainpanel').addClass('active');
		}
		else {
			$('.sidebar').removeClass('active');
			$('.mainpanel').removeClass('active');
		}
	});

	var reqRolsEdicio = $.ajax({
		url: "https://intranet.prisma.cat/ajax/consultaRolsEdicio.php",
		method: "GET",
		data: { url : window.location.pathname.split('?')[0] },
		dataType: "html"
	});

	reqRolsEdicio.done(function( res ) {
		rolsPageEdicio = res;

		var reqRolsEdicio = $.ajax({
			url: "https://intranet.prisma.cat/ajax/consultaRolsUsuari.php",
			method: "GET",
			dataType: "html"
		});

		reqRolsEdicio.done(function( res ) {
			rolsUsuari = res;

			var arrRolsUsuari = rolsUsuari.split('|');
			var arrRolsPageEd = rolsPageEdicio.split('|');
			var cntRolsPage = 0, cntRolsUsuari = 0;

			while (!tePermisEdicio && cntRolsUsuari < arrRolsUsuari.length) {
				cntRolsPage = 0;
				while (!tePermisEdicio && cntRolsPage < arrRolsPageEd.length) {
					if ( arrRolsPageEd[cntRolsPage] == arrRolsUsuari[cntRolsUsuari] ) tePermisEdicio = true;
					cntRolsPage++;
				}
				cntRolsUsuari++;
			}
		});

	});



});

/* Es mostra l'error del request*/
requestMenu.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request SideBar: " );
});

/* ############################ FUNCIONS DE MODALS  ############################ */

function mostrarModalLoading() {
	$("#modalLoading").modal('show');
}
function amagarLoadingModal() {
	$("#modalLoading").modal('hide');
}

function mostrarModalError() {
	$("#modalErrors").modal('show');
}
function afegirTextModalError(text) {
	$("#modalErrors .modal-body").html(text);
}
function afegirHeaderModalError(text) {
	$("#modalErrors .modal-header .modal-title").html(text);
}
function amagarModalError() {
	$("#modalErrors").modal('hide');
}

function mostrarModalSuccess() {
	$("#modalSuccess").modal('show');
}
function afegirTextModalSuccess(text) {
	$("#modalSuccess .modal-body").html(text);
}
function afegirHeaderModalSuccess(text) {
	$("#modalSuccess .modal-header .modal-title").html(text);
}
function amagarModalSuccess() {
	$("#modalSuccess").modal('hide');
}

function mostrarModalNoTensPermisos() {
	$('.modal').modal('hide');
	afegirHeaderModalError("Oops!");
	afegirTextModalError("No tens permisos per realitzar aquesta acció");
	mostrarModalError();
}

/* ############################ FUNCIONS GENERALS ############################ */

$('body').on('click', '.loading-wrapper', function() {
	amagarLoadingModal()
});

$('#modalErrors').on('click', function() {
	amagarModalError()
});

$('#modalSuccess').on('click', function() {
	amagarModalSuccess()
});

//Després de 3 segons, recarrego la pàgina
function reloadUrl() {
	setTimeout(function() {
		window.location.reload();
	}, 2000);
}

/* ############################ FUNCIONS D'ERROR  ############################ */

function errorFunction( jqXHR, textStatus, errorThrown, msg ) {
	afegirHeaderModalError("Oops...!");
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
	afegirTextModalError(msgError);
	mostrarModalError();
}

function rerrorFunction( jqXHR, textStatus, errorThrown, msg ) {
	errorFunction( jqXHR, textStatus, errorThrown, msg );
	reloadUrl();
}

/* ############################ MSG INFORMATIUS ############################ */

function missatgeNoPotEstarBuit(valor) {
	var mostrar = "El camp " + valor + " no pot estar buit.";
	return mostrar;
}

function missatgeInscritNoValid(valor) {
	var mostrar = "El camp " + valor + " no és valid.";
	return mostrar;
}

function missatgeNoEsNumero(valor) {
	var mostrar = "El camp " + valor + " ha de ser un número.";
	return mostrar;
}

function missatgeNoTeFormatData(valor) {
	var mostrar = "El camp " + valor + " no té el format de la data correcta (DD/MM/AAAA o DD/MM/AAAA HH:MM:SS).";
	return mostrar;
}

/* ############################ FUNCIONS DE VALIDACIÓ ############################ */

/* Comprova si valor és un numero. Si compleix la condició, retorna
true, altrament retorna false. Si valor està buit, retorna true  */
function validNumero(valor) {
	var valid = false;
	if (valor != "") {
		if (parseFloat(valor) >= 0) {
			valid = true;
		}
	}
	else
		valid = true;

	return valid;
}

/* Comprova si valor és una data. Si valor té el format AAAA-MM-DD HH:MM:SS o AAAA-MM-DD.
Si compleix la condició, retorna true, altrament retorna false. Si valor està buit, retorna true  */
function validData(valor) {
	var valid = false;
	if (valor.length > 0) {
		//separem per l'espai per si el format de les dates té la hora
		var vectDataHora = valor.split(' ');
		if (vectDataHora.length > 0) {
			var vectData = vectDataHora[0].split("/");
			if (vectData.length > 0 && vectData.length == 3 && vectData[0].length == 2
				&& vectData[1].length == 2 && vectData[2].length == 4)
				valid = true;

			if (vectDataHora.length > 1) {
				var vectHora = vectDataHora[1].split(":");
				if (vectHora.length > 0 && (vectData.length == 2 || vectData.length == 3) &&
				vectHora[0].length == 2 && vectHora[1].length == 2 && vectHora[2].length == 2)
					valid = true;
				else
					valid = false;
			}
		}
	} else
		valid = true;
	return valid;
}
