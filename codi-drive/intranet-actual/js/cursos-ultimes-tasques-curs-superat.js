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

		/* ### FUNCIONALITATS QUALIFICA CURS SUPERAT ### */
		var textBotoQualifica = "Qualifica";
		var textBotoNoQualifica = "No Qualifica";
		$("#cursos-superat button.marcat").each(function() {
			$(this).html(textBotoQualifica);
		});
		$("#cursos-superat button.no_marcat").each(function() {
			$(this).html(textBotoNoQualifica);
		});

		$("#cursos-superat").on("click", ".marcat", function(e) {
			$(this).html(textBotoNoQualifica);
			$(this).removeClass("marcat");
			$(this).addClass("no_marcat");
		});
		$("#cursos-superat").on("click", ".no_marcat", function(e) {
			$(this).html(textBotoQualifica);
			$(this).removeClass("no_marcat");
			$(this).addClass("marcat");
		});

		$('#modalActualitzarCursSuperat').on('hide.bs.modal', function (e) {
			window.location.reload();
		})

		$("#cursos-superat").on("click", "#confirmar-curs-superat", function(e) {
			if ( tePermisEdicio ) {
				var existeixAlgunCanvi = false;
				$('#modalActualitzarCursSuperat .modal-body').html('');
				$('#cursos-superat button.marcat').each(function() {
					var idButton = $(this).attr('id');
					var idCurs = idButton.split("-")[2];

					if ( idCurs != '' ) {
						existeixAlgunCanvi = true;
						var esPrimerCanviCert = true;

						var msg = "<p>S'ha actualitzat la data de qualifiació del curs ";
						msg += " <strong>"+idCurs+"</strong></p>";

						var updQual = $.ajax({
							url: path + "inici/actualitzaCursSuperat.php",
							method: "POST",
							data: {
								idCurs : idCurs
							},
							dataType: "html"
						});

						updQual.done(function( msgQual ) {
							if ( msgQual.toLowerCase().includes("error") ) {
								afegirHeaderModalError("Alerta");
								afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idCurs+"</strong>");
								mostrarModalError();
							}
							else {
								if (esPrimerCanviCert) {
									$('#modalActualitzarCursSuperat').modal('show');
								}
								esPrimerCanviCert = false;
								$('#modalActualitzarCursSuperat .modal-body').append(msg);
							}
						});

						updQual.fail(function( jqXHR, textStatus, errorThrown ) {
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
