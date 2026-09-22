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
			if ( tePermisEdicio ) {
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
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});
