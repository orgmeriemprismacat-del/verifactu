let urlPagina = window.location.pathname.split('?')[0];
let path = "https://intranet.prisma.cat/ajax/";

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

		/* ### FUNCIONALITATS BLOQUEIG CURSOS ### */
		var textBotoBloqueig = "Bloqueja";
		var textBotoNoBloqueig = "No bloquegis";
		$("#bloqueig-cursos button.marcat").each(function() {
			$(this).html(textBotoBloqueig);
		});
		$("#bloqueig-cursos button.no_marcat").each(function() {
			$(this).html(textBotoNoBloqueig);
		});

		$("#bloqueig-cursos").on("click", ".marcat", function(e) {
			$(this).html(textBotoNoBloqueig);
			$(this).removeClass("marcat");
			$(this).addClass("no_marcat");
		});
		$("#bloqueig-cursos").on("click", ".no_marcat", function(e) {
			$(this).html(textBotoBloqueig);
			$(this).removeClass("no_marcat");
			$(this).addClass("marcat");
		});

		$('#modalActualitzarDataBloqueig').on('hide.bs.modal', function (e) {
			window.location.reload();
		})

		$("#bloqueig-cursos").on("click", "#confirmar-bloqueig-cursos", function(e) {
			if ( tePermisEdicio ) {
				var existeixAlgunCanvi = false;
				$('#bloqueig-cursos button.marcat').each(function() {
					var idButton = $(this).attr('id');
					var idCurs = idButton.split("-")[2];

					if ( idCurs != '' ) {
						existeixAlgunCanvi = true;
						var esPrimerCanviCert = true;

						var msg = "<p>S'ha actualitzat la data de bloqueig del curs ";
						msg += " amb <strong>"+idCurs+"</strong></p>";

						var updBloq = $.ajax({
							url: path + "inici/actualitzaDataBloqueig.php",
							method: "POST",
							data: {
								idCurs : idCurs
							},
							dataType: "html"
						});

						updBloq.done(function( msgBloq ) {
							if ( msgBloq.toLowerCase().includes("error") ) {
								afegirHeaderModalError("Alerta");
								afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idCurs+"</strong>");
								mostrarModalError();
							}
							else {
								if (esPrimerCanviCert) {
									$('#modalActualitzarDataBloqueig').modal('show');
									$('#modalActualitzarDataBloqueig .modal-body').html('');
								}
								esPrimerCanviCert = false;
								$('#modalActualitzarDataBloqueig .modal-body').append(msg);
							}
						});

						updBloq.fail(function( jqXHR, textStatus, errorThrown ) {
							rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error al actualitzar el registre <strong>"+idCurs+"</strong>: " );
						});
					}

				});
				if ( !existeixAlgunCanvi ) {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("No has marcat cap canvi");
					mostrarModalError();
				}
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
