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

		/* ### FUNCIONALITATS VALORA CURSOS ENSENYAMENT ### */
		var textBotoValora = "Valora";
		var textBotoNoValora = "No Valoris";
		var textBotoGeneraFitxer = "L'he generat";
		var textBotoGeneratFitxer = "Generat";
		var textBotoNoEsPotGeneraFitxer = "Anul·lar GTAF";

		$("#valora-cursos-ense button.marcat").each(function() {
			$(this).html(textBotoValora);
		});
		$("#valora-cursos-ense button.no_marcat").each(function() {
			$(this).html(textBotoNoValora);
		});

		$("#valora-cursos-ense").on("click", ".marcat", function(e) {
			$(this).html(textBotoNoValora);
			$(this).removeClass("marcat");
			$(this).addClass("no_marcat");
		});
		$("#valora-cursos-ense").on("click", ".no_marcat", function(e) {
			$(this).html(textBotoValora);
			$(this).removeClass("no_marcat");
			$(this).addClass("marcat");
		});

		$("#valora-cursos-ense button.generat").each(function() {
			$(this).html(textBotoGeneratFitxer);
		});
		$("#valora-cursos-ense button.no_generat").each(function() {
			$(this).html(textBotoGeneraFitxer);
		});
		$("#valora-cursos-ense .no_es_pot_generar").each(function() {
			$(this).html(textBotoNoEsPotGeneraFitxer);
		});

		$("#valora-cursos-ense").on("click", ".no_generat", function(e) {
			if ( tePermisEdicio ) {
				var idButton = $(this).attr('id');
				var idCurs = idButton.split("-")[3];
				$('#modalActualitzarFitxerValoracio .modal-body').html('');

				var msg = "<p>S'ha registrat que has realitzat el fitxer de valoracions del curs ";
				msg += " <strong>"+idCurs+"</strong></p>";

				var anyUsuariValoraEnse = $('#valora-cursos-ense #any-'+idCurs).html().trim();
				var mesUsuariValoraEnse = $('#valora-cursos-ense #mes-'+idCurs).html().trim();

				var updValor = $.ajax({
					url: path + "inici/heGeneratFitxerValroacionsEnse.php",
					method: "GET",
					data: {
						any : anyUsuariValoraEnse,
						mes : mesUsuariValoraEnse,
						idCurs : idCurs
					},
					dataType: "html"
				});

				updValor.done(function( msgValor ) {
					if ( msgValor.toLowerCase().includes("error") ) {
						afegirHeaderModalError("Alerta");
						afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idCurs+"</strong>");
						mostrarModalError();
					}
					else {
						$('#modalActualitzarFitxerValoracio').modal('show');
						$('#modalActualitzarFitxerValoracio .modal-body').html(msg);

						$("#"+idButton).html(textBotoGeneratFitxer);
						$("#"+idButton).removeClass("no_generat");
						$("#"+idButton).addClass("generat");
						$("#"+idButton).removeAttr("id");
					}
				});

				updValor.fail(function( jqXHR, textStatus, errorThrown ) {
					rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error al actualitzar el registre <strong>"+idCurs+"</strong>: " );
				});
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});

		$('#modalActualitzarDataValoracio').on('hide.bs.modal', function (e) {
			window.location.reload();
		})

		$("#valora-cursos-ense").on("click", "#confirmar-valora-cursos-ense", function(e) {
			if ( tePermisEdicio ) {
				var existeixAlgunCanvi = false;
				$('#modalActualitzarDataValoracio .modal-body').html('');
				$('#valora-cursos-ense button.marcat').each(function() {
					var idButton = $(this).attr('id');
					var idCurs = idButton.split("-")[3];

					if ( idCurs != '' ) {
						existeixAlgunCanvi = true;
						var esPrimerCanviCert = true;

						var msg = "<p>S'ha actualitzat la data de valoració del curs ";
						msg += " <strong>"+idCurs+"</strong></p>";

						var updValor = $.ajax({
							url: path + "inici/actualitzaDataValoracio.php",
							method: "POST",
							data: {
								idCurs : idCurs
							},
							dataType: "html"
						});

						updValor.done(function( msgValor ) {
							if ( msgValor.toLowerCase().includes("error") ) {
								afegirHeaderModalError("Alerta");
								afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idCurs+"</strong>");
								mostrarModalError();
							}
							else {
								if (esPrimerCanviCert) {
									$('#modalActualitzarDataValoracio').modal('show');
								}
								esPrimerCanviCert = false;
								$('#modalActualitzarDataValoracio .modal-body').append(msg);
							}
						});

						updValor.fail(function( jqXHR, textStatus, errorThrown ) {
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
