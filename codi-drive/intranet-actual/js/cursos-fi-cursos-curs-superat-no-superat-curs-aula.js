let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";

/* Cada vegada que es faci una crida d'un ajax, s'executarà la funció mostrarModalLoading().
Cada vegada que finalitza la crida d'un ajax, s'executarà la funció amagarLoadingModal(). */
$(document).bind("ajaxSend", function(){
	mostrarModalLoading();
}).bind("ajaxComplete", function(){
	amagarLoadingModal();
});

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel').html(message);

		//quan es clica a qualsevol lloc fora del select, amago el desplegable
		$(window).click(function() {
			//amago el desplegable
			$('.select .select-list').hide();
			//retorno el triangle com esta per defecte
			var triangle = $('.select').find("i");
			triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
		});

		//quan estas focus en el camp, elimino el marcatge de l'input
		$('#cursos').on('focus', '.form-control', function() {
			$(this).parent().removeClass('element-cercat-marcat');
		});

		//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
		$('#cursos').on('blur', '.form-control', function() {
			if ($(this).val().trim() == '')
				$(this).removeClass('element-cercat-marcat');
			else
				$(this).addClass('element-cercat-marcat');
		});

		$("#cursos .select").click(function(e) {
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

		$("#cursos .select").on("click", "li", function(e) {
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

		$( "#cursos" ).keyup(function(evObject){
			if (evObject.keyCode == 13)
				$('#cercar-cursos').click();
		});

		$("#cursos").on("click", ".tipusMissatge", function(e) {
			if ( !$(this).hasClass("marcat") ) {
				$('.tipusMissatge').removeClass('marcat');
				$(this).addClass('marcat');
			}
		});

		$('#cursos').on('click', '#cercar-cursos', function() {
			anyCercat = $('#anys-dispo .element-selected').html().trim();
			mesCercat = $('#mesos-dispo .element-selected').html().trim();
			horaCercada = $('#hores-dispo .element-selected').html().trim();
			tipusMissatgeCerca = $('.tipusMissatge.marcat');

			if ( anyCercat == '' || mesCercat == '' || horaCercada == '' || horaCercada == 'Qualsevol' || tipusMissatgeCerca.length <= 0 ) {
				afegirHeaderModalError("Oops...!");
				afegirTextModalError("Has d'omplir els camps de l'ANY, el MES, les HORES i el tipus de missatge a enviar per poder fer la cerca! ");
				mostrarModalError();
			}
			else {
				if ( tipusMissatgeCerca.hasClass('approve') )
					mostrarAlumnesCursSuperatCursAula(anyCercat, mesCercat, horaCercada, '', '');
				if ( tipusMissatgeCerca.hasClass('dontApprove') )
					mostrarAlumnesCursNoSuperat(anyCercat, mesCercat, horaCercada);
			}
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function mostrarAlumnesCursSuperat(any, mes, hores) {
	$('#resultats-cerca').off();

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: path + "cursos/buscarAlumnesCursSuperat.php",
		method: "GET",
		data: {
			any : any,
			mes : mes,
			hores : hores,
		},
		dataType: "html"
	});

	reqCursos.done(function( res ) {
			$('#resultats-cerca').html(res);
			$('#resultats-cerca').show();
			amagarLoadingModal();

			//Es necessita perquè el tooltip funcioni
			$(function() {
				$('[data-toggle="tooltip"]').tooltip();
			})

			$("#resultats-cerca").on("click", ".avisar-alumne", function(e) {
				if ( $(this).hasClass("lightGreen") ) {
					$(this).addClass('lightRed');
					$(this).removeClass('lightGreen');
					$(this).html('NO ENVIAR ALUMNE/A');
				}
				else if ( $(this).hasClass("lightRed") ) {
					$(this).addClass('lightGreen');
					$(this).removeClass('lightRed');
					$(this).html('ENVIAR ALUMNE/A');
				}
			});
			$("#resultats-cerca").on("click", ".avisar-tutor", function(e) {
				if ( $(this).hasClass("lightGreen") ) {
					$(this).addClass('lightRed');
					$(this).removeClass('lightGreen');
					$(this).html('NO ENVIAR TUTOR/A');
				}
				else if ( $(this).hasClass("lightRed") ) {
					$(this).addClass('lightGreen');
					$(this).removeClass('lightRed');
					$(this).html('ENVIAR TUTOR/A');
				}
			});

			$('.cnt-curs-superat').on('click', '.btn-action', function() {
				var id = $(this).attr('id').split('_');
				var link;

				if (id[1] == "qual") {
					if (id[0] == 'moodle')
						link = "https://campus.prisma.cat/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];
					else
						link = "https://www.prisma.cat/campus/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];

					console.log(link);
				}
				else if (id[1] == "part") {
					if (id[0] == 'moodle')
						link = "https://campus.prisma.cat/report/outline/user.php?id=" + id[2] + "&course=" + id[3] + "&mode=complete";
					else
						link = "https://www.prisma.cat/campus/report/outline/user.php?id=" + id[2] + "&course=" + id[3] + "&mode=complete";

					console.log(link);
				}
				window.open(link, '_blank');
			});

			/* Quan es canvia la data de pagament, es comprova si la data Ã©s correcte */
			$("#resultats-cerca").on("change", ".dataDispo", function(e) {
				dataCorrecte( $(this).val() );
			});

			$('#resultats-cerca').on('click', '.not-aprove', function() {
				if ( tePermisEdicio ) {
					var id = $(this).attr('id').split('_')[3];
					var element = $(this);
					mostrarModalLoading();

					var updNoSuperat = $.ajax({
						url: path + "cursos/marcaInscripcioNoSuperat.php",
						method: "GET",
						data: { id : id },
						dataType: "html"
					});
					updNoSuperat.done(function( res ) {
						if (!res.includes("Error") && !res.includes("error")) {
							amagarLoadingModal();
							afegirHeaderModalSuccess("S'ha registrat correctament com a no superat");
							mostrarModalSuccess();
							$("#modalSuccess").on('hidden.bs.modal', function (e) {
								$('#cercar-cursos').click();
							})
						} else {
							amagarLoadingModal();
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora de marcar el curs no superat");
							mostrarModalError();
							reloadUrl();
						}
					});
					updNoSuperat.fail(function( jqXHR, textStatus, errorThrown ) {
						errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora de marcar el curs no superat: " );
					});
				}
				else {
				   mostrarModalNoTensPermisos();
				}
			});


			$("#resultats-cerca").on("click", "#enviarCorreusCursSuperat", function(e) {
				var nMsgAlumnesEnviar = $("#resultats-cerca .avisar-alumne.lightGreen").length;
				var nMsgTutorsEnviar = $("#resultats-cerca .avisar-tutor.lightGreen").length;
				var nMsgAlumneActual = 1;
				var nMsgTutorActual = 1;
				var nMsgAlumneSendOK = 0;
				var nMsgTutorSendOK = 0;
				var dataDispo = $('.dataDispo').val().trim();

				if ( nMsgAlumnesEnviar <= 0 && nMsgTutorsEnviar <= 0 ) {
					afegirHeaderModalError("Oops...!");
					afegirTextModalError("Has de marcar com a mínim a un missatge a enviar! ");
					mostrarModalError();
				}
				else if ( dataDispo == '' ) {
					afegirHeaderModalError("Oops...!");
					afegirTextModalError("Has d'introduir la data en la qual estarà disponible el certificat!");
					mostrarModalError();
				}
				else {
					mostrarModalLoading();
					$("#resultats-cerca .avisar-alumne.lightGreen").each(function() {
						var idInsc = $(this).attr('id').split('-')[2];
						var idsError = "";
						var request = $.ajax({
							url: path + "alumnes/sendMsgAvisAlumneCursSuperat.php",
							global: false,
							method: "GET",
							data: {
								idInsc: idInsc,
								data: dataDispo
							},
							dataType: "html"
						});
						request.done(function( msg ) {
							amagarLoadingModal();
							if ( !msg.toLowerCase().includes("error") ) {
								nMsgAlumneSendOK++;
							}
							else {
								idsError = "<li>"+idInsc+"</li>";
							}

							if ( nMsgAlumneActual == nMsgAlumnesEnviar ) {
								if ( nMsgAlumneSendOK == nMsgAlumnesEnviar ) {
									afegirHeaderModalSuccess("Missatges enviats als alumnes i als tutors!");
									afegirTextModalSuccess("");
									mostrarModalSuccess();
									$("#modalSuccess").on('hidden.bs.modal', function (e) {
										$('#cercar-cursos').click();
									})
								}
								else {
									afegirHeaderModalError("Alerta!");
									afegirTextModalError("<p>Hi ha hagut un error a l'hora d'enviar el missatge d'avís!</p><p>Els IDs que han donat problemes són: </p><ul>"+idsError+"</ul>");
									mostrarModalError();
								}
							}
							nMsgAlumneActual++;
						});
						request.fail(function( jqXHR, textStatus, errorThrown ) {
							errorFunction( jqXHR, textStatus, errorThrown,
									"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
						});
					});
					$("#resultats-cerca .avisar-tutor.lightGreen").each(function() {
						var idInsc = $(this).attr('id').split('-')[2];
						var idsError = "";
						var request = $.ajax({
							url: path + "alumnes/sendMsgAvisTutorCursSuperat.php",
							global: false,
							method: "GET",
							data: {
								idInsc: idInsc
							},
							dataType: "html"
						});
						request.done(function( msg ) {
							if ( !msg.toLowerCase().includes("error") ) {
								nMsgTutorSendOK++;
							}
							else {
								idsError = "<li>"+idInsc+"</li>";
							}

							if ( nMsgTutorActual == nMsgTutorsEnviar ) {
								if ( nMsgTutorSendOK == nMsgTutorsEnviar ) {
									afegirHeaderModalSuccess("Missatges enviats als alumnes i als tutors!");
									afegirTextModalSuccess("");
									mostrarModalSuccess();
									$("#modalSuccess").on('hidden.bs.modal', function (e) {
										$('#cercar-cursos').click();
									})
								}
								else {
									afegirHeaderModalError("Alerta!");
									afegirTextModalError("<p>Hi ha hagut un error a l'hora d'enviar el missatge d'avís!</p><p>Els IDs que han donat problemes són: </p><ul>"+idsError+"</ul>");
									mostrarModalError();
								}
							}
							nMsgAlumneActual++;
						});
						request.fail(function( jqXHR, textStatus, errorThrown ) {
							errorFunction( jqXHR, textStatus, errorThrown,
									"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
						});
					});
				}
			});

	});

	reqCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});
}

function mostrarAlumnesCursSuperatCursAula(any, mes, hores, curs, aula) {
	$('#resultats-cerca').off();

	if ( curs == '' ) curs = "CANVA";
	if ( aula == '' ) aula = "B";

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: path + "cursos/buscarAlumnesCursSuperatCursAula.php",
		method: "GET",
		data: {
			any : any,
			mes : mes,
			hores : hores,
			curs : curs,
			aula : aula,
		},
		dataType: "html"
	});

	reqCursos.done(function( res ) {
			$('#resultats-cerca').html(res);
			$('#resultats-cerca').show();
			amagarLoadingModal();

			//Es necessita perquè el tooltip funcioni
			$(function() {
				$('[data-toggle="tooltip"]').tooltip();
			})

			$("#resultats-cerca").on("click", ".avisar-alumne", function(e) {
				if ( $(this).hasClass("lightGreen") ) {
					$(this).addClass('lightRed');
					$(this).removeClass('lightGreen');
					$(this).html('NO ENVIAR ALUMNE/A');
				}
				else if ( $(this).hasClass("lightRed") ) {
					$(this).addClass('lightGreen');
					$(this).removeClass('lightRed');
					$(this).html('ENVIAR ALUMNE/A');
				}
			});
			$("#resultats-cerca").on("click", ".avisar-tutor", function(e) {
				if ( $(this).hasClass("lightGreen") ) {
					$(this).addClass('lightRed');
					$(this).removeClass('lightGreen');
					$(this).html('NO ENVIAR TUTOR/A');
				}
				else if ( $(this).hasClass("lightRed") ) {
					$(this).addClass('lightGreen');
					$(this).removeClass('lightRed');
					$(this).html('ENVIAR TUTOR/A');
				}
			});

			$('.cnt-curs-superat').on('click', '.btn-action', function() {
				var id = $(this).attr('id').split('_');
				var link;

				if (id[1] == "qual") {
					if (id[0] == 'moodle')
						link = "https://campus.prisma.cat/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];
					else
						link = "https://www.prisma.cat/campus/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];

					console.log(link);
				}
				else if (id[1] == "part") {
					if (id[0] == 'moodle')
						link = "https://campus.prisma.cat/report/outline/user.php?id=" + id[2] + "&course=" + id[3] + "&mode=complete";
					else
						link = "https://www.prisma.cat/campus/report/outline/user.php?id=" + id[2] + "&course=" + id[3] + "&mode=complete";

					console.log(link);
				}
				window.open(link, '_blank');
			});

			/* Quan es canvia la data de pagament, es comprova si la data Ã©s correcte */
			$("#resultats-cerca").on("change", ".dataDispo", function(e) {
				dataCorrecte( $(this).val() );
			});

			$('#resultats-cerca').on('click', '.not-aprove', function() {
				if ( tePermisEdicio ) {
					var id = $(this).attr('id').split('_')[3];
					var element = $(this);
					mostrarModalLoading();

					var updNoSuperat = $.ajax({
						url: path + "cursos/marcaInscripcioNoSuperat.php",
						method: "GET",
						data: { id : id },
						dataType: "html"
					});
					updNoSuperat.done(function( res ) {
						if (!res.includes("Error") && !res.includes("error")) {
							amagarLoadingModal();
							afegirHeaderModalSuccess("S'ha registrat correctament com a no superat");
							mostrarModalSuccess();
							$("#modalSuccess").on('hidden.bs.modal', function (e) {
								$('#cercar-cursos').click();
							})
						} else {
							amagarLoadingModal();
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora de marcar el curs no superat");
							mostrarModalError();
							reloadUrl();
						}
					});
					updNoSuperat.fail(function( jqXHR, textStatus, errorThrown ) {
						errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora de marcar el curs no superat: " );
					});
				}
				else {
				   mostrarModalNoTensPermisos();
				}
			});


			$("#resultats-cerca").on("click", "#enviarCorreusCursSuperat", function(e) {
				var nMsgAlumnesEnviar = $("#resultats-cerca .avisar-alumne.lightGreen").length;
				var nMsgTutorsEnviar = $("#resultats-cerca .avisar-tutor.lightGreen").length;
				var nMsgAlumneActual = 1;
				var nMsgTutorActual = 1;
				var nMsgAlumneSendOK = 0;
				var nMsgTutorSendOK = 0;
				var dataDispo = $('.dataDispo').val().trim();

				if ( nMsgAlumnesEnviar <= 0 && nMsgTutorsEnviar <= 0 ) {
					afegirHeaderModalError("Oops...!");
					afegirTextModalError("Has de marcar com a mínim a un missatge a enviar! ");
					mostrarModalError();
				}
				else if ( dataDispo == '' ) {
					afegirHeaderModalError("Oops...!");
					afegirTextModalError("Has d'introduir la data en la qual estarà disponible el certificat!");
					mostrarModalError();
				}
				else {
					mostrarModalLoading();
					$("#resultats-cerca .avisar-alumne.lightGreen").each(function() {
						var idInsc = $(this).attr('id').split('-')[2];
						var idsError = "";
						var request = $.ajax({
							url: path + "alumnes/sendMsgAvisAlumneCursSuperat.php",
							global: false,
							method: "GET",
							data: {
								idInsc: idInsc,
								data: dataDispo
							},
							dataType: "html"
						});
						request.done(function( msg ) {
							amagarLoadingModal();
							if ( !msg.toLowerCase().includes("error") ) {
								nMsgAlumneSendOK++;
							}
							else {
								idsError = "<li>"+idInsc+"</li>";
							}

							if ( nMsgAlumneActual == nMsgAlumnesEnviar ) {
								if ( nMsgAlumneSendOK == nMsgAlumnesEnviar ) {
									afegirHeaderModalSuccess("Missatges enviats als alumnes i als tutors!");
									afegirTextModalSuccess("");
									mostrarModalSuccess();
									$("#modalSuccess").on('hidden.bs.modal', function (e) {
										$('#cercar-cursos').click();
									})
								}
								else {
									afegirHeaderModalError("Alerta!");
									afegirTextModalError("<p>Hi ha hagut un error a l'hora d'enviar el missatge d'avís!</p><p>Els IDs que han donat problemes són: </p><ul>"+idsError+"</ul>");
									mostrarModalError();
								}
							}
							nMsgAlumneActual++;
						});
						request.fail(function( jqXHR, textStatus, errorThrown ) {
							errorFunction( jqXHR, textStatus, errorThrown,
									"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
						});
					});
					$("#resultats-cerca .avisar-tutor.lightGreen").each(function() {
						var idInsc = $(this).attr('id').split('-')[2];
						var idsError = "";
						var request = $.ajax({
							url: path + "alumnes/sendMsgAvisTutorCursSuperat.php",
							global: false,
							method: "GET",
							data: {
								idInsc: idInsc
							},
							dataType: "html"
						});
						request.done(function( msg ) {
							if ( !msg.toLowerCase().includes("error") ) {
								nMsgTutorSendOK++;
							}
							else {
								idsError = "<li>"+idInsc+"</li>";
							}

							if ( nMsgTutorActual == nMsgTutorsEnviar ) {
								if ( nMsgTutorSendOK == nMsgTutorsEnviar ) {
									afegirHeaderModalSuccess("Missatges enviats als alumnes i als tutors!");
									afegirTextModalSuccess("");
									mostrarModalSuccess();
									$("#modalSuccess").on('hidden.bs.modal', function (e) {
										$('#cercar-cursos').click();
									})
								}
								else {
									afegirHeaderModalError("Alerta!");
									afegirTextModalError("<p>Hi ha hagut un error a l'hora d'enviar el missatge d'avís!</p><p>Els IDs que han donat problemes són: </p><ul>"+idsError+"</ul>");
									mostrarModalError();
								}
							}
							nMsgAlumneActual++;
						});
						request.fail(function( jqXHR, textStatus, errorThrown ) {
							errorFunction( jqXHR, textStatus, errorThrown,
									"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
						});
					});
				}
			});

	});

	reqCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});
}

function mostrarAlumnesCursNoSuperat(any, mes, hores) {
	$('#resultats-cerca').off();

	/* Consulta cursos a partir d'un any i un mes */
	var reqCursos = $.ajax({
		url: path + "cursos/buscarAlumnesCursNoSuperat.php",
		method: "GET",
		data: {
			any : any,
			mes : mes,
			hores : hores,
		},
		dataType: "html"
	});

	reqCursos.done(function( res ) {
			$('#resultats-cerca').html(res);
			$('#resultats-cerca').show();
			amagarLoadingModal();

			//Es necessita perquè el tooltip funcioni
			$(function() {
				$('[data-toggle="tooltip"]').tooltip();
			})
			$("#resultats-cerca").on("click", ".avisar-tutor", function(e) {
				if ( $(this).hasClass("lightGreen") ) {
					$(this).addClass('lightRed');
					$(this).removeClass('lightGreen');
					$(this).html('NO ENVIAR TUTOR/A');
				}
				else if ( $(this).hasClass("lightRed") ) {
					$(this).addClass('lightGreen');
					$(this).removeClass('lightRed');
					$(this).html('ENVIAR TUTOR/A');
				}
			});

			$('.cnt-curs-superat').on('click', '.btn-action', function() {
				var id = $(this).attr('id').split('_');
				var link;

				if (id[1] == "qual") {
					if (id[0] == 'moodle')
						link = "https://campus.prisma.cat/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];
					else
						link = "https://www.prisma.cat/campus/course/user.php?mode=grade&id=" + id[2] + "&user=" + id[3];

					console.log(link);
				}
				else if (id[1] == "part") {
					if (id[0] == 'moodle')
						link = "https://campus.prisma.cat/report/outline/user.php?id=" + id[2] + "&course=" + id[3] + "&mode=complete";
					else
						link = "https://www.prisma.cat/campus/report/outline/user.php?id=" + id[2] + "&course=" + id[3] + "&mode=complete";

					console.log(link);
				}
				window.open(link, '_blank');
			});

			/* Quan es canvia la data de pagament, es comprova si la data Ã©s correcte */
			$("#resultats-cerca").on("change", ".dataDispo", function(e) {
				dataCorrecte( $(this).val() );
			});

			$('#resultats-cerca').on('click', '.aprove', function() {
				if ( tePermisEdicio ) {
					var id = $(this).attr('id').split('_')[2];
					var element = $(this);
					mostrarModalLoading();

					var updNoSuperat = $.ajax({
						url: path + "cursos/marcaInscripcioSuperat.php",
						method: "GET",
						data: { id : id },
						dataType: "html"
					});
					updNoSuperat.done(function( res ) {
						if (!res.includes("Error") && !res.includes("error")) {
							amagarLoadingModal();
							afegirHeaderModalSuccess("S'ha registrat correctament com a superat");
							mostrarModalSuccess();
							$("#modalSuccess").on('hidden.bs.modal', function (e) {
								$('#cercar-cursos').click();
							})
						} else {
							amagarLoadingModal();
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora de marcar el curs superat");
							mostrarModalError();
							reloadUrl();
						}
					});
					updNoSuperat.fail(function( jqXHR, textStatus, errorThrown ) {
						errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error a l'hora de marcar el curs superat: " );
					});
				}
				else {
				   mostrarModalNoTensPermisos();
				}
			});

			$("#resultats-cerca").on("click", "#enviarCorreusCursNoSuperat", function(e) {
				var nMsgTutorsEnviar = $("#resultats-cerca .avisar-tutor.lightGreen").length;
				var nMsgAlumneActual = 1;
				var nMsgTutorActual = 1;
				var nMsgAlumneSendOK = 0;
				var nMsgTutorSendOK = 0;
				var dataDispo = $('.dataDispo').val().trim();

				if ( nMsgTutorsEnviar <= 0 ) {
					afegirHeaderModalError("Oops...!");
					afegirTextModalError("Has de marcar com a mínim a un missatge a enviar! ");
					mostrarModalError();
				}
				else if ( dataDispo == '' ) {
					afegirHeaderModalError("Oops...!");
					afegirTextModalError("Has d'introduir la data en la qual estarà disponible el certificat!");
					mostrarModalError();
				}
				else {
					mostrarModalLoading();
					$("#resultats-cerca .avisar-tutor.lightGreen").each(function() {
						var idInsc = $(this).attr('id').split('-')[2];
						var idsError = "";
						var request = $.ajax({
							url: path + "alumnes/sendMsgAvisTutorCursNoSuperat.php",
							global: false,
							method: "GET",
							data: {
								idInsc: idInsc,
								data: dataDispo
							},
							dataType: "html"
						});
						request.done(function( msg ) {
							amagarLoadingModal();
							if ( !msg.toLowerCase().includes("error") ) {
								nMsgTutorSendOK++;
							}
							else {
								idsError = "<li>"+idInsc+"</li>";
							}

							if ( nMsgTutorActual == nMsgTutorsEnviar ) {
								if ( nMsgTutorSendOK == nMsgTutorsEnviar ) {
									afegirHeaderModalSuccess("Missatges enviats als tutors!");
									afegirTextModalSuccess("");
									mostrarModalSuccess();
								}
								else {
									afegirHeaderModalError("Alerta!");
									afegirTextModalError("<p>Hi ha hagut un error a l'hora d'enviar el missatge d'avís!</p><p>Els IDs que han donat problemes són: </p><ul>"+idsError+"</ul>");
									mostrarModalError();
								}
							}
							nMsgAlumneActual++;
						});
						request.fail(function( jqXHR, textStatus, errorThrown ) {
							errorFunction( jqXHR, textStatus, errorThrown,
									"Hi ha hagut un error a l'hora d'enviar el missatge d'avís': ");
						});
					});
				}
			});

	});

	reqCursos.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error a l'hora de buscar els cursos: " );
	});
}

//Comprova si la data Ã©s correcta i envia una alerta en cas de no ser-ho
function dataCorrecte( valor ) {
	var esValid = dataEsValida(valor);

	if ( esValid == "" ) {
		/* comprovem la diferencia en dies que hi ha entre la data actual i la
		data valor. Si la data valor Ã©s posterior a l'actual no Ã©s valid.
		Si la data valor Ã©s mÃ©s anterior de 5 dies tambÃ© no Ã©s vÃ lid. Altrament Ã©s vÃ lid*/
		var dateNow = new Date();

		var dd = dateNow.getDate();
		var mm = dateNow.getMonth()+1;
		var yyyy = dateNow.getFullYear();

		if ( dd < 10 ) dd = '0' + dd;

		if( mm < 10 ) mm = '0' + mm;

		dateNow = yyyy + '-' + mm + '-' + dd;

		if ( diffDates(valor, dateNow) > 0 ) {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("La data de pagament és anterior a avui");
			mostrarModalError();
		}
	}
	else {
		afegirHeaderModalError("Alerta");
		afegirTextModalError(esValid);
		mostrarModalError();
	}
}

/* Retorna el text buit si valor tÃ© el format d'una data dd/mm/yyyy i
Ã©s una data correcte. Altrament retorna l'error */
function dataEsValida( valor ) {
	// revisar el patrÃ³
	if(!/^\d{4}\-\d{1,2}\-\d{1,2}$/.test(valor))
	return "Revisa el format de la data de pagament";

	// convertir els nombres a enters
	var parts = valor.split("-");
	var day = parseInt(parts[2], 10);
	var month = parseInt(parts[1], 10);
	var year = parseInt(parts[0], 10);

	// Revisar els rangs d'any i mes
	if( (year < 1000) || (year > 3000) || (month == 0) || (month > 12) )
	return "Revisa els rangs d'any i mes de la data de pagament";

	var monthLength = [ 31, 28, 31, 30, 31, 30, 31, 31, 30, 31, 30, 31 ];

	// Ajustar els anys bisiestos
	if(year % 400 == 0 || (year % 100 != 0 && year % 4 == 0))
	monthLength[1] = 29;

	// Revisar el rang dels dies
	if (! ( day > 0 && day <= monthLength[month - 1] ) )
		return "Revisa el rang dels dies de la data de pagament";
	else
		return "";
}

// Calcula la diferÃ¨ncia entre dies de la data date1 i la data date 2
function diffDates(date1, date2) {
 	var partsDate1 = date1.split('-');
	var partsDate2 = date2.split('-');
	var partsDateUTC1 = Date.UTC(partsDate1[0], partsDate1[1]-1, partsDate1[2]);
	var partsDateUTC2 = Date.UTC(partsDate2[0], partsDate2[1]-1, partsDate2[2]);
	var diff = partsDateUTC2 - partsDateUTC1;
	var days = Math.floor(diff / (1000 * 60 * 60 * 24));

	return days;
}
