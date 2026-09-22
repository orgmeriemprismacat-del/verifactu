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


		/* ### FUNCIONALITATS PUJADA GTAF ### */
		$('#modalActualitzarCertificat').on('hide.bs.modal', function (e) {
			window.location.reload();
		})
		$("#pujar-gtaf .select").on("click", "li", function(e) {
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
			if ($(this).parent().prev().html() == '' || $(this).parent().prev().html().toLowerCase().includes("no assignat"))
				$(this).parent().parent().removeClass('element-cercat-marcat');
			else
				$(this).parent().parent().addClass('element-cercat-marcat');

			$(this).parent().parent().prev().remove();
		});

		$("#pujar-gtaf").on("click", "#confirmar-pujada-gtaf", function(e) {
			var existeixAlgunCanvi = false;
			var esPrimerCanviCert = true;
			$('#modalActualitzarCertificat .modal-body').html('');
			$('#pujar-gtaf .select .element-selected').each(function() {
				var textCertGTAF = $(this).html().trim();
				var idCertGTAF = $(this).parent().attr('id');
				var numGTAF = idCertGTAF.split("-")[2];

				if ( textCertGTAF != '' ) {
					existeixAlgunCanvi = true;

					var msg = "<p>S'ha actualitzat el certificat del registre de l'alumne ";
					msg += "<strong>"+$('#nom-'+numGTAF).html().trim();
					msg += " "+$('#cognom-'+numGTAF).html().trim()+"</strong>";
					msg += " de l'edició "+$('#any-'+numGTAF).html().trim();
					msg += $('#curs-'+numGTAF).html().trim()+$('#mes-'+numGTAF).html().trim();
					msg += " amb <strong>"+textCertGTAF+"</strong></p>";



					var updCert = $.ajax({
						url: path + "inici/actualitzaCertificatInscripcions.php",
						method: "POST",
						data: {
							id : numGTAF,
							certificat : textCertGTAF
						},
						dataType: "html"
					});

					updCert.done(function( msgUpd ) {
						if ( msgUpd.toLowerCase().includes("error") ) {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+numGTAF+"</strong>");
							mostrarModalError();
						}
						else {
							if (esPrimerCanviCert) {
								$('#modalActualitzarCertificat').modal('show');
							}
							esPrimerCanviCert = false;
							$('#modalActualitzarCertificat .modal-body').append(msg);
						}
					});

					updCert.fail(function( jqXHR, textStatus, errorThrown ) {
						rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error al actualitzar el registre: " );
					});
				}

			});
			if ( !existeixAlgunCanvi ) {
				afegirHeaderModalError("Alerta");
				afegirTextModalError("No has marcat cap canvi");
				mostrarModalError();
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
		});

		$('#modalActualitzarDataValoracio').on('hide.bs.modal', function (e) {
			window.location.reload();
		})

		$("#valora-cursos-ense").on("click", "#confirmar-valora-cursos-ense", function(e) {
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
		});

		/* ### FUNCIONALITATS VALORA CURSOS FISS ### */
		var textBotoValora = "Valora";
		var textBotoNoValora = "No Valoris";
		var textBotoGeneraFitxer = "L'he generat";
		var textBotoGeneratFitxer = "Generat";
		var textBotoNoEsPotGeneraFitxer = "Anul·lar FISS";

		$("#valora-cursos-fiss button.marcat").each(function() {
			$(this).html(textBotoValora);
		});
		$("#valora-cursos-fiss button.no_marcat").each(function() {
			$(this).html(textBotoNoValora);
		});

		$("#valora-cursos-fiss").on("click", ".marcat", function(e) {
			$(this).html(textBotoNoValora);
			$(this).removeClass("marcat");
			$(this).addClass("no_marcat");
		});
		$("#valora-cursos-fiss").on("click", ".no_marcat", function(e) {
			$(this).html(textBotoValora);
			$(this).removeClass("no_marcat");
			$(this).addClass("marcat");
		});

		$("#valora-cursos-fiss button.generat").each(function() {
			$(this).html(textBotoGeneratFitxer);
		});
		$("#valora-cursos-fiss button.no_generat").each(function() {
			$(this).html(textBotoGeneraFitxer);
		});
		$("#valora-cursos-fiss .no_es_pot_generar").each(function() {
			$(this).html(textBotoNoEsPotGeneraFitxer);
		});

		$("#valora-cursos-fiss").on("click", ".no_generat", function(e) {
			var idButton = $(this).attr('id');
			var idCurs = idButton.split("-")[3];
			$('#modalActualitzarFitxerValoracioFISS .modal-body').html('');

			var msg = "<p>S'ha registrat que has realitzat el fitxer de valoracions del curs ";
			msg += " <strong>"+idCurs+"</strong></p>";

			var anyUsuariValora = $('#valora-cursos-fiss #any-'+idCurs).html().trim();
			var mesUsuariValora = $('#valora-cursos-fiss #mes-'+idCurs).html().trim();

			var updValor = $.ajax({
				url: path + "inici/heGeneratFitxerValoracionsFISS.php",
				method: "GET",
				data: {
					any : anyUsuariValora,
					mes : mesUsuariValora,
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
					$('#modalActualitzarFitxerValoracioFISS').modal('show');
					$('#modalActualitzarFitxerValoracioFISS .modal-body').html(msg);

					$("#"+idButton).html(textBotoGeneratFitxer);
					$("#"+idButton).removeClass("no_generat");
					$("#"+idButton).addClass("generat");
					$("#"+idButton).removeAttr("id");
				}
			});

			updValor.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error al actualitzar el registre <strong>"+idCurs+"</strong>: " );
			});
		});

		$('#modalActualitzarDataValoracioFISS').on('hide.bs.modal', function (e) {
			window.location.reload();
		})

		$("#valora-cursos-fiss").on("click", "#confirmar-valora-cursos-fiss", function(e) {
			var existeixAlgunCanvi = false;
			$('#modalActualitzarDataValoracioFISS .modal-body').html('');
			$('#valora-cursos-fiss button.marcat').each(function() {
				var idButton = $(this).attr('id');
				var idCurs = idButton.split("-")[3];

				if ( idCurs != '' ) {
					existeixAlgunCanvi = true;
					var esPrimerCanviCert = true;

					var msg = "<p>S'ha actualitzat la data de valoració del FISS del curs ";
					msg += " <strong>"+idCurs+"</strong></p>";

					var updValor = $.ajax({
						url: path + "inici/actualitzaDataValoracioFISS.php",
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
								$('#modalActualitzarDataValoracioFISS').modal('show');
							}
							esPrimerCanviCert = false;
							$('#modalActualitzarDataValoracioFISS .modal-body').append(msg);
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
		});

		/* ### FUNCIONALITATS PUJAR AULES OBERTES ### */
		var textBotoPujar = "Pujar";
		var textBotoNoPujar = "No Pujar";
		$("#pujar-ao button.marcat").each(function() {
			$(this).html(textBotoPujar);
		});
		$("#pujar-ao button.no_marcat").each(function() {
			$(this).html(textBotoNoPujar);
		});

		$("#pujar-ao").on("click", ".marcat", function(e) {
			$(this).html(textBotoNoPujar);
			$(this).removeClass("marcat");
			$(this).addClass("no_marcat");
		});
		$("#pujar-ao").on("click", ".no_marcat", function(e) {
			$(this).html(textBotoPujar);
			$(this).removeClass("no_marcat");
			$(this).addClass("marcat");
		});

		$('#modalActualitzarPerenne').on('hide.bs.modal', function (e) {
			window.location.reload();
		})

		var fitxerPujada = "";
		$("#pujar-ao").on("click", "#confirmar-pujada-ao", function(e) {
			var existeixAlgunCanvi = false;
			$('#modalActualitzarPerenne .modal-body').html('');

			var createAO = $.ajax({
				url: path + "inici/crearFitxerAO.php",
				method: "POST",
				dataType: "html"
			});

			createAO.done(function( msgAO ) {
				if ( msgAO.toLowerCase().includes("error") ) {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("Hi ha hagut un error a l'hora de crear el fitxer");
					mostrarModalError();
				}
				else {
					fitxerPujada = msgAO;

					$('#pujar-ao button.marcat').each(function(i,v) {
						var idButton = $(this).attr('id');
						var idCurs = idButton.split("-")[2];
						var usuari = idButton.split("-")[3];

						if ( idCurs != '' ) {
							existeixAlgunCanvi = true;
							var esPrimerCanviCert = true;

							var msg = "<p>S'ha actualitzat el perenne de l'usuari <strong>"+usuari+"</strong> del curs ";
							msg += " <strong>"+idCurs+"</strong></p>";

							var anyUsuariAO = $('#pujar-ao #any-'+idCurs+'-'+usuari).html().trim();
							var mesUsuariAO = $('#pujar-ao #mes-'+idCurs+'-'+usuari).html().trim();
							var cursUsuariAO = $('#pujar-ao #curs-'+idCurs+'-'+usuari).html().trim();
							var nomUsuariAO = $('#pujar-ao #nom-'+idCurs+'-'+usuari).html().trim();
							var cognomsUsuariAO = $('#pujar-ao #cognoms-'+idCurs+'-'+usuari).html().trim();
							var emailUsuariAO = $('#pujar-ao #email-'+idCurs+'-'+usuari).html().trim();
							var poblacioUsuariAO = $('#pujar-ao #poblacio-'+idCurs+'-'+usuari).html().trim();

							var updAO = $.ajax({
								url: path + "inici/pujarAulesObertes.php",
								method: "POST",
								data: {
									any : anyUsuariAO,
									mes : mesUsuariAO,
									curs : cursUsuariAO,
									usuari : usuari,
									fitxer: fitxerPujada,
									nom: nomUsuariAO,
									cognoms: cognomsUsuariAO,
									email: emailUsuariAO,
									poblacio: poblacioUsuariAO
								},
								dataType: "html"
							});

							updAO.done(function( msgAO ) {
								if ( msgAO.toLowerCase().includes("error") ) {
									afegirHeaderModalError("Alerta");
									afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idCurs+"</strong> de l'usuari <strong>"+usuari+"</strong>");
									mostrarModalError();
								}
								else {
									if (esPrimerCanviCert) {
										$('#modalActualitzarPerenne').modal('show');
									}
									esPrimerCanviCert = false;
									$('#modalActualitzarPerenne .modal-body').append(msg);
								}
								if ($("#pujar-ao button.marcat").length === i+1) {
									var msgF = "<a href='https://intranet.prisma.catfitxers/"+fitxerPujada+"' target='_blank'>Fitxer pujada aules obertes</a>";
									$('#modalActualitzarPerenne .modal-body').append(msgF);
								}
							});

							updAO.fail(function( jqXHR, textStatus, errorThrown ) {
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
			});

			createAO.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error a l'hora de crear el fitxer: " );
			});


		});

		/* ### FUNCIONALITATS PUJADA CURSOS - CREAR FITXER PUJADA ### */
		var idCnt = "#pujada-inscr";
		var textBotoPujar = "Pujar";
		var textBotoNoPujar = "No Pujar";
		$(idCnt+" button.marcat").each(function() {
			$(this).html(textBotoPujar);
		});
		$(idCnt+" button.no_marcat").each(function() {
			$(this).html(textBotoNoPujar);
		});

		$(idCnt+"").on("click", ".marcat", function(e) {
			$(this).html(textBotoNoPujar);
			$(this).removeClass("marcat");
			$(this).addClass("no_marcat");
		});
		$(idCnt+"").on("click", ".no_marcat", function(e) {
			$(this).html(textBotoPujar);
			$(this).removeClass("no_marcat");
			$(this).addClass("marcat");
		});

		$('#modalActualitzarPujadaInscripcions').on('hide.bs.modal', function (e) {
			window.location.reload();
		})

		$(idCnt + " .select").on("click", "li", function(e) {
			var texto = $(this).find("a").html(),
				element = $(this).parent().prev(),
				lista = $(this).closest("ul"),
				triangle = $(this).parent().next(),
				id = $(this).attr('id');
			e.preventDefault();
			e.stopPropagation();
			element.html(texto);
			lista.hide();
			triangle.removeClass("fa-angle-up").addClass("fa-angle-down");
			$(this).parent().parent().prev().addClass('active');
			//marco el select de la cerca quan s'ha escrit alguna cosa en el camp
			if ($(this).parent().prev().html() == '' || $(this).parent().prev().html().toLowerCase().includes("no assignat"))
				$(this).parent().parent().removeClass('element-cercat-marcat');
			else
				$(this).parent().parent().addClass('element-cercat-marcat');

			$(this).parent().parent().prev().remove();
		});

		var fitxerPujada = "";
		$(idCnt+"").on("click", "#confirmar-pujada-inscr", function(e) {
			var existeixAlgunCanvi = false;
			$('#modalActualitzarPujadaInscripcions .modal-body').html('');

			var createInscr = $.ajax({
				url: path + "inici/crearFitxerPujadaInscripcions.php",
				method: "POST",
				dataType: "html"
			});

			createInscr.done(function( msgInsc ) {
				if ( msgInsc.toLowerCase().includes("error") ) {
					afegirHeaderModalError("Alerta");
					afegirTextModalError("Hi ha hagut un error a l'hora de crear el fitxer");
					mostrarModalError();
				}
				else {
					fitxerPujada = msgInsc;

					$(idCnt+' button.marcat').each(function(i,v) {
						var idButton = $(this).attr('id');
						var idCurs = idButton.split("-")[2];
						var usuariInscrit = idButton.split("-")[3];

						if ( idCurs != '' ) {
							existeixAlgunCanvi = true;
							var esPrimerCanviCert = true;

							var msg = "<p>S'ha actualitzat el INSC CURS de l'usuari <strong>"+usuariInscrit+"</strong> del curs ";

							var anyUsuariInscrit = $(idCnt+' #any-'+idCurs+'-'+usuariInscrit).html().trim();
							var mesUsuariInscrit = $(idCnt+' #mes-'+idCurs+'-'+usuariInscrit).html().trim();
							var cursUsuariInscrit = $(idCnt+' #curs-'+idCurs+'-'+usuariInscrit).html().trim();
							var aulaUsuariInscrit = $(idCnt+' #aula-'+idCurs+'-'+usuariInscrit + " .aula").html().trim();
							if ($(idCnt+' #aula-'+idCurs+'-'+usuariInscrit + ' .element-selected .aula')[0]) {
								aulaUsuariInscrit = $(idCnt+' #aula-'+idCurs+'-'+usuariInscrit + ' .element-selected .aula').html().trim();
							}
							var nomUsuariInscrit = $(idCnt+' #nom-'+idCurs+'-'+usuariInscrit).html().trim();
							var cognomsUsuariInscrit = $(idCnt+' #cognoms-'+idCurs+'-'+usuariInscrit).html().trim();
							var emailUsuariInscrit = $(idCnt+' #email-'+idCurs+'-'+usuariInscrit).html().trim();
							var poblacioUsuariInscrit = $(idCnt+' #poblacio-'+idCurs+'-'+usuariInscrit).html().trim();

							msg += " <strong>"+anyUsuariInscrit+cursUsuariInscrit+mesUsuariInscrit+aulaUsuariInscrit+"</strong></p>";

							var updInscr = $.ajax({
								url: path + "inici/pujarInscripcions.php",
								method: "POST",
								data: {
									any : anyUsuariInscrit,
									mes : mesUsuariInscrit,
									curs : cursUsuariInscrit,
									aula : aulaUsuariInscrit,
									usuari : usuariInscrit,
									fitxer: fitxerPujada,
									nom: nomUsuariInscrit,
									cognoms: cognomsUsuariInscrit,
									email: emailUsuariInscrit,
									poblacio: poblacioUsuariInscrit
								},
								dataType: "html"
							});

							updInscr.done(function( msgInscr ) {
								if ( msgInscr.toLowerCase().includes("error") ) {
									afegirHeaderModalError("Alerta");
									afegirTextModalError("Hi ha hagut un error a l'hora d'actualitzar el registre <strong>"+idCurs+"</strong> de l'usuari <strong>"+usuariInscrit+"</strong>");
									mostrarModalError();
								}
								else {
									if (esPrimerCanviCert) {
										$('#modalActualitzarPujadaInscripcions').modal('show');
									}
									esPrimerCanviCert = false;
									$('#modalActualitzarPujadaInscripcions .modal-body').append(msg);
								}
								if ($(idCnt+" button.marcat").length === i+1) {
									var msgF = "<a href='https://intranet.prisma.catfitxers/"+fitxerPujada+"' target='_blank'>Fitxer pujada inscripcions</a>";
									$('#modalActualitzarPujadaInscripcions .modal-body').append(msgF);
								}
							});

							updInscr.fail(function( jqXHR, textStatus, errorThrown ) {
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
			});

			createInscr.fail(function( jqXHR, textStatus, errorThrown ) {
				rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error a l'hora de crear el fitxer: " );
			});


		});

		/* ### FUNCIONALITATS PUJADA CURSOS - EDITAR ALUMNE ### */
		$(idCnt).on('click', '.edit-inscr', function() {
			var idInscripcio = $(this).attr('id').split('-')[2];
			var idCurs = $(this).attr('id').split('-')[3];
			var idUser = $(this).attr('id').split('-')[4];
			mostrarInformacioAlumne( idInscripcio, idCurs, idUser );
		});
		$(idCnt).on('click', '.search', function() {
			var idInscripcio = $(this).attr('id').split('-')[2];
			var idCurs = $(this).attr('id').split('-')[3];
			var idUser = $(this).attr('id').split('-')[4];
			var link = "https://intranet.prisma.cat/alumnes/mostrar-alumne/#/" + idUser;
			window.open(link, '_blank');
		});

		$('#content-page').on('click', '.pujada-gtaf', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.pujada-inscripcions', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.valorar-cursos-fiss', function() {
			var idApartat = $(this).attr('id').split('-')[3];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.valorar-cursos-ense', function() {
			var idApartat = $(this).attr('id').split('-')[3];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.pujada-aules-obertes', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.curs-superat', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.bloquejar-cursos', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.cns-imatges-cursos-nous', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.duplicats', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.valid-desc', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.canvi-pregresp', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
		$('#content-page').on('click', '.duplicats-idpag', function() {
			var idApartat = $(this).attr('id').split('-')[2];
			obreApartat(idApartat);
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

//Mostrar la informació de la inscripció $idInscripcio dintre del modal "modalEditaDadesInscripcions"
function mostrarInformacioAlumne( idInscripcio, idCurs, idUser ) {
	console.log("mostrarInfoAlumne "+idInscripcio+" "+idCurs+" "+idUser);
	//Consulta AJAX per consultar la informació necessària per el modal i afegir-la en el modal "modalEditaDadesInscripcions"
	var info = $.ajax({
		url: path + "inici/mostrarModalEditaInscripcio.php",
		method: "GET",
		data: {
			idInsc : idInscripcio
		},
		dataType: "html"
	});

	info.done(function( res ) {
		if ( res.toLowerCase().includes("error") ) {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar el modal d'edició");
			mostrarModalError();
		}
		else {
			$("#modalEditaDadesInscripcions .modal-body").html(res);
			$("#modalEditaDadesInscripcions").modal('show');

			$('.copy')

			//afegir accions en cas de clicar el botó de copiar
			$('#modalEditaDadesInscripcions').on('click', '.copy', function() {
				element_id = $(this).prev().attr('id');
				var aux = document.createElement("div");
 			  aux.setAttribute("contentEditable", true);
 			  aux.innerHTML = document.getElementById(element_id).innerHTML;
 			  aux.setAttribute("onfocus", "document.execCommand('selectAll',false,null)");
 			  document.body.appendChild(aux);
 			  aux.focus();
 			  document.execCommand("copy");
 			  document.body.removeChild(aux);
			});

			//afegir accions per desar resultats
			$('#dades-inscripcio-pujada-alumnes').off();

			/*Si es clica el botó d'.save-result',
			es guarden els resultats a la BD a la ultima inscripció de la BD amb dni dni,
			s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
			$('#dades-inscripcio-pujada-alumnes').on('click', '.save-result', function() {
				var idinsc = $('#id-insc').html().trim();
				let nominsc = $('#nom-insc').val().trim();
				let coginsc = $('#cognoms-insc').val().trim();
				let dniinsc = $('#dni-insc').val().trim();
				let emailinsc = $('#email-insc').val().trim();
				let telinsc = $('#telefon-insc').val().trim();
				let adrecainsc = $('#adreca-insc').val().trim();
				let cpinsc = $('#codipostal-insc').val().trim();
				let poblacioinsc = $('#poblacio-insc').val().trim();

				$('#modalEditaDadesInscripcions .apartat input').removeClass('error');
				$('#modalEditaDadesInscripcions .alert-danger').remove();

				if (!campBuit(nominsc) && !campBuit(coginsc) && !campBuit(dniinsc) &&
					!campBuit(emailinsc) && !campBuit(telinsc) && !campBuit(adrecainsc) &&
					!campBuit(cpinsc) && !campBuit(poblacioinsc) && validTel(telinsc, dniinsc).length == 0
				) {
					$('#modalEditaDadesInscripcions .apartat').addClass('opacity-02');
					$('#modalEditaDadesInscripcions .loading-wrapper').removeClass('hide');

					var requestSavePers = $.ajax({
						url: path + "inici/actualitzaDadesPersonals.php",
						method: "GET",
						data: {
							idinsc : idinsc,
							nom : nominsc,
							cog : coginsc,
							email : emailinsc,
							tel : telinsc,
							adreca : adrecainsc,
							cp : cpinsc,
							poblacio : poblacioinsc
						},
						dataType: "html"
					});

					requestSavePers.done(function( res ) {
						if (!res.includes("Error") && !res.includes("error")) {
							$('#modalEditaDadesInscripcions .loading-wrapper').addClass('hide');
							var msgOK = "<div class='alert alert-success alert-with-icon w-100 mb-2'>";
							msgOK += "<i class='material-icons' data-notify='icon'>notifications</i>";
							msgOK += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
							msgOK += "<i class='material-icons'>close</i></button>";
							msgOK += "<span>El canvi s'ha guardat correctament</span></div>";
							$('#modalEditaDadesInscripcions .result-success').html(msgOK);
							$('#modalEditaDadesInscripcions .result-success').removeClass('hide');

							$('#pujada-inscr #nom-'+idCurs+'-'+idUser).html(nominsc);
							$('#pujada-inscr #cognoms-'+idCurs+'-'+idUser).html(coginsc);
							$('#pujada-inscr #email-'+idCurs+'-'+idUser).html(emailinsc);
							$('#pujada-inscr #poblacio-'+idCurs+'-'+idUser).html(poblacioinsc);

							console.log("saveResult "+idCurs+" "+idUser);

						}
						else {
							$('#modalEditaDadesInscripcions .loading-wrapper').addClass('hide');
							var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
							msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
							msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
							msgError += "<i class='material-icons'>close</i></button>";
							msgError += "<span>Hi ha hagut un error amb el registre</span></div>";
							$('#modalEditaDadesInscripcions .result-success').html(msgError);
							$('#modalEditaDadesInscripcions .result-success').addClass('danger');
							$('#modalEditaDadesInscripcions .result-success').removeClass('hide');
						}
					});

					requestSavePers.fail(function( jqXHR, textStatus, errorThrown ) {
						errorFunction( jqXHR, textStatus, errorThrown,
						"Hi ha hagut un error en guardar les dades d'inscripció: " );
					});
				}
				else {
					var errors = "";
					if (campBuit(nominsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("NOM") + "</span>";
						$('#nom-insc').addClass('error');
					}
					if (campBuit(coginsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("COGNOMS") + "</span>";
						$('#cognoms-insc').addClass('error');
					}
					if (campBuit(dniinsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("DNI") + "</span>";
						$('#dni-insc').addClass('error');
					}
					if (campBuit(emailinsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("E-MAIL") + "</span>";
						$('#email-insc').addClass('error');
					}
					if (campBuit(telinsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("TELÈFON") + "</span>";
						$('#telefon-insc').addClass('error');
					} else if (!validTel(telinsc, dniinsc).length == 0) {
						errors += "<span>" + validTel(telinsc, dniinsc) + "</span>";
						$('#telefon-insc').addClass('error');
					}
					if (campBuit(adrecainsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("ADREÇA") + "</span>";
						$('#adreca-insc').addClass('error');
					}
					if (campBuit(cpinsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("CODI POSTAL") + "</span>";
						$('#codipostal-insc').addClass('error');
					}
					if (campBuit(poblacioinsc)) {
						errors += "<span>" + missatgeNoPotEstarBuit("POBLACIÓ") + "</span>";
						$('#poblacio-insc').addClass('error');
					}

					var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
					msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
					msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
					msgError += "<i class='material-icons'>close</i></button>";
					msgError += errors + "</div>";

					$('#modalConsultaInformacio #dades-inscripcio-pujada-alumnes').append(msgError);
				}
			});
		}
	});

	info.fail(function( jqXHR, textStatus, errorThrown ) {
		rerrorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut algun error a l'hora de mostrar el modal d'edició: " );
	});
}

/* Comprova si valor està buit. Si està buit, retorna true, altrament retorna false */
function campBuit(valor) {
	var buit = false;
	if (valor == '') buit = true;
	return buit;
}

/* Si dni es buit o té 9 caracters, comprova si valor és un numero de 9 digits i
no conté caracters no permesos en un telefon.
Altrament, comprova si valor és un numero 9 a 13 digits i no conté caracters no
permesos en un telefon.
Si compleix la condició, retorna buit, altrament retorna l'error.
Si valor està buit, retorna buit */
function validTel(valor, dni) {
	var valid = "";
	if (valor.length != 0) {
		var stripped = valor.replace(/[\(\)\.\-\ ]/g, '');

		if ( (( dni=='' || dni.length==9) && !(stripped.length == 9)) ||
			  ( dni!='' && dni.length!=9 && !(stripped.length >= 9 && stripped.length <= 13)) ) {
			valid = "El TELÈFON té una llargada incorrecta";
		} else if (isNaN(stripped)) {
			valid = "El TELÈFON conté caràcters no permesos";
		}
	}
	return valid;
}

function obreApartat(idApartat) {
	var link = "https://intranet.prisma.cat";
	var reqUrl = $.ajax({
		url: path + "inici/buscarUrlIdApartatFuncio.php",
		method: "GET",
		data: {
			idApartat : idApartat
		},
		dataType: "html"
	});

	reqUrl.done(function( res ) {
		if (!res.includes("Error") && !res.includes("error")) {
			link = link + res;
			window.open(link, '_blank');
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de consultar la url de l'apartat");
			mostrarModalError();
		}
	});

	reqUrl.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
		"Hi ha hagut un error a l'hora de consultar la url de l'apartat: " );
	});
}
