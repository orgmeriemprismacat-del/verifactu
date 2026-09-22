var urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";
let hashUrl = null;
if ( window.location.hash.split('#')[1])
	hashUrl = window.location.hash.split('#')[1].split('/')[1];

let any = "",
	mes = "";

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain_v5.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

requestMain.done(function( message ) {
	$('.mainpanel').html(message);
	activaFuncions();
});
requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function activaFuncions() {
	//quan es clica a qualsevol lloc fora del select, amago el desplegable
	$(window).click(function() {
		//amago el desplegable
		$('.select .select-list').hide();
		//retorno el triangle com esta per defecte
		var triangle = $('.select').find("i");
		triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
	});

	//quan estas focus en el camp, elimino el marcatge de l'input
	$('#publicacions').on('focus', '.form-control', function() {
		$(this).parent().removeClass('element-cercat-marcat');
	});

	//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
	$('#publicacions').on('blur', '.form-control', function() {
		if ($(this).val().trim() == '')
			$(this).removeClass('element-cercat-marcat');
		else
			$(this).addClass('element-cercat-marcat');
	});

	$("#publicacions .select").click(function(e) {
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

	$("#publicacions .select").on("click", "li", function(e) {
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
		//marco el select de la cerca quan s'ha escrit alguna cosa en el camp
		if ($(this).parent().prev().html() == '' || $(this).parent().prev().html().toLowerCase().includes("qualsevol"))
			$(this).parent().parent().removeClass('element-cercat-marcat');
		else
			$(this).parent().parent().addClass('element-cercat-marcat');
	});

	/* Si premo la tecla ENTER, es reprodueix l'event de clicar del cercar-alumne*/
	$("#publicacions").keyup(function(evObject) {
		if (evObject.keyCode == 13) $('#cercar-dies-publicar').click();
	});

	$('#cercar-dies-publicar').on('click', function() {
		any = $('#anys-dispo .element-selected').html().trim();
		mes = $('#mesos-dispo .element-selected').html().trim();

		if ( any == '' || mes == '' ) {
			afegirHeaderModalError("Omple els camps per poder fer la cerca");
			mostrarModalError();
		}
		else {
			mostrarModalLoading();
			showMeContainer(any, mes);
		}
	});
}

/* ########################        ACTIVA BOTONS        ######################## */

function activeButtonviewPostMdl() {
	$('#cnt-posts').on('click', '.urlPost', function() {
		var idUrlCurs = $(this).attr('id');
		viewPost(idUrlCurs);
	});
}

// Activa el botó d'enviar
function activeButtonSend() {
	$('#cnt-posts').on('click', '#send-posts', function() {
		sendposts();
	});
}

// Activa el botó de marcatge
function activaMarcatge() {
	var textBoto = "SÍ <i class='material-icons mx-1' title='envia'>sentiment_very_satisfied</i>";
	var textBotoNo = "NO <i class='material-icons mx-1' title='envia'>sentiment_very_dissatisfied</i>";
	$('#cnt-posts').on("click", ".marcat", function(e) {
		$(this).html(textBotoNo);
		$(this).addClass("no_marcat lightRed");
		$(this).removeClass("marcat lightGreen");
	});
	$('#cnt-posts').on("click", ".no_marcat", function(e) {
		$(this).html(textBoto);
		$(this).addClass("marcat lightGreen");
		$(this).removeClass("no_marcat lightRed");
	});
}

/* ########################        VIEW COURSES        ######################## */

function showMeContainer(any, mes) {
	$('#resultats-cerca .card-body').off();
	$('.table-order').off();
	mostrarModalLoading();

	var req = $.ajax({
	   url: path + "developer/viewContainer_Xarxes_Mensual.php",
	   method: "GET",
	   data: {
	      any : any,
	      mes : mes
	   },
	   dataType: "html"
	});

	req.done(function( info ) {
		$('#resultats-cerca').html(info);
		amagarLoadingModal();
		$('#resultats-cerca').show();
		activaMarcatge();
		activeButtonviewPostMdl();
		activeButtonSend();
	});

	req.fail(function( jqXHR, textStatus, errorThrown ) {
	   rerrorFunction( jqXHR, textStatus, errorThrown,
	      "Hi ha hagut algun error a l'hora de mostrar taula de cursos: " );
	});
}

/* ########################       FUNCIONALITATS       ######################## */

function viewPost(idUrlCurs) {
	var id = idUrlCurs.split('-');
	//Obro una finestra nova amb l'enllaç al moodle
	var link1 = "https://campus.prisma.cat/mod/forum/discuss.php?d=" + id[1];
	window.open(link1, '_blank');
}

/* ########################          ENVIAMENT          ######################## */

// Envia comunicats marcats
function sendposts() {
	mostrarModalLoading();
	//Si no existeix algun element marcat
	if ( $('#cnt-posts .marcat').length == 0 ) {
		afegirHeaderModalError("Alerta");
		afegirTextModalError("No has marcat res");
		mostrarModalError();
	}
	else {
		$('#cnt-posts .marcat').each(function(i,v) {
			var idButton = $(this).attr('id').split("-")[1];
			var tipusPost =  $(this).attr('id').split("-")[2];
			var descPost = $('#descPost-' + idButton).val();
			if ( descPost != '' )
				descPost = descPost.replaceAll('\n', "</p><p style='text-align: left;'>");
			var msgsError = '';

			var upd = $.ajax({
				url: path + "developer/insertPublicacio_Xarxes_Mensual.php",
				method: "POST",
				data: {
					nomPost : $('#nomPost-' + idButton).html(),
					tipusPost : tipusPost,
					descPost : descPost,
					any : parseInt( $('#any-' + idButton).html() ),
					mes : $('#mes-' + idButton).html(),
					dia : parseInt( $('#day-' + idButton).html() )
				},
				dataType: "html"
			});

			upd.done(function( res ) {
				if ( res.toLowerCase().includes("error") ) {
					msgsError += "<p>Hi ha hagut un error a l'hora d'inserir el registre</p>";
				}

				if ($("#cnt-posts .marcat").length-1 === i) {
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
						afegirTextModalSuccess("S'han registrat totes les publicacions!");
						amagarLoadingModal();
						mostrarModalSuccess();

						$('#modalSuccess').on('click', '.btn-success', function() {
							amagarModalSuccess();ç
							$('#cercar-dies-publicar').click();
						});
						$('#modalSuccess').on('click', '.close', function() {
							amagarModalSuccess();
							$('#cercar-dies-publicar').click();
						});
					}
				}
			});

			upd.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown,
					"Hi ha hagut algun error a l'hora d'inserir el comunicat'': " );
			});

		});
	}
}
