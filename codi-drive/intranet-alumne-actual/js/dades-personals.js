/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://campus.prisma.cat/intranet-alumnes/ajax/mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

/* Mostrem el main */
requestMain.done(function( message ) {
		$('.mainpanel').html(message);

		// activarSelects();

		//Quan cliques .logout redirigim al campus my
		$('.breadcrump').on('click', '.logout', function() {
			mostrarModalLoadingInfo( 'Redireccionant...' );
			window.location.href = pathCampusMy;
		});

		/*
			Quan es botó amb la classe edita-info,
			es canvien tots els camps amb dades per camps editables
		*/
		$('#dades').on('click', '.edita-info', function() {
			//consulta ajax per substituir les dades.
			mostrarDadesEditables();
		});
});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});

function activarSelects() {
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
}

function mostrarDadesEditables() {
	var request = $.ajax({
		url: path + "dades/mostrarDadesEditables.php",
		method: "GET",
		dataType: "html"
	});

	request.done(function( result ) {
		if ( !result.includes("Error") && !result.includes("error") ) { //Hi ha un error
			//Resposta s'afegeix a $('#dades .card-body')
			$('#dades .card-body').html( result );
			$('#dades .card-body').addClass( 'edit' );
			//afegir on click d'enviar de msg
			$('.cnt-send-info').on('click', '.send-info', function() {
				//consulta ajax per substituir les dades.
				var nom, cognoms, dni, email, tel, adreca, cp, poble, perfil, titulacio;
				idInsc = $('.send-info').attr('id').split('-')[2];
				nom = $('#nom').val().trim();
				cognoms = $('#cog').val().trim();
				dni = $('#dni').val().trim();
				email = $('#email').val().trim();
				tel = $('#tel').val().trim();
				adreca = $('#adreca').val().trim();
				cp = $('#cp').val().trim();
				poble = $('#poble').val().trim();
				perfil = $('.perfil .element-selected').html().trim();
				perfilAltres = '';
				titulacio = $('.titulacio .element-selected').html().trim();
				titulacioAltres =  '';
				titulacioEdSecundaria =  '';
				titulacioEstudiant =  '';
				titulacioVaries =  $('#varies-titulacio').val().trim();
				comentari = $('#comentari').val().trim();

				if ( !$('.perfil-altres').hasClass('hide') )
					perfilAltres =  $('#perfil-altres').val().trim();
				if ( !$('.titulacio-altres').hasClass('hide') )
					titulacioAltres =  $('#titulacio-altres').val().trim();
				if ( !$('.titulacio-ed-secundaria').hasClass('hide') )
					titulacioEdSecundaria = $('#titulacio-ed-secundaria').val().trim();
				if ( !$('.titulacio-estudiant').hasClass('estudiant') )
					titulacioEstudiant =  $('#titulacio-estudiant').val().trim();

				//elimino errors anteriors
				$('.alert').remove();
				$('.edit .error').removeClass('error');

				//Comprovacions de les dades que no siguin erronies
				errors = '';
				if ( campBuit(nom) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("NOM") + "</span>";
				  $('#nom').addClass('error');
				}
				if ( campBuit(cognoms) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("COGNOMS") + "</span>";
				  $('#cog').addClass('error');
				}
				if ( campBuit(dni) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("DNI") + "</span>";
				  $('#dni').addClass('error');
				}
				if ( campBuit(email) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("<em>E-MAIL</em>") + "</span>";
				  $('#email').addClass('error');
				}
				if ( campBuit(tel) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("TELÈFON") + "</span>";
				  $('#tel').addClass('error');
				}
				else if (!validTel(tel, dni).length == 0) {
					errors += "<span>" + validTel(tel, dni) + "</span>";
					$('#tel').addClass('error');
				}
				if ( campBuit(adreca) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("ADREÇA") + "</span>";
				  $('#adreca').addClass('error');
				}
				if ( campBuit(cp) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("CODI POSTAL") + "</span>";
				  $('#cp').addClass('error');
				}
				if ( campBuit(poble) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("POBLACIÓ") + "</span>";
				  $('#poble').addClass('error');
				}
				if ( campBuit(perfil) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("TREBALLA A...") + "</span>";
				  $('#perfil').addClass('error');
				}
				else if ( !$('.perfil-altres').hasClass('hide') && campBuit(perfilAltres) ) {
					errors += "<span>" + missatgeNoPotEstarBuit("TREBALLA A...") + "</span>";
				  $('#perfil-altres').addClass('error');
				}
				if ( campBuit(titulacio) ) {
				  errors += "<span>" + missatgeNoPotEstarBuit("ESTIC TREBALLANT A..") + "</span>";
				  $('#titulacio').addClass('error');
				}
				else if ( !$('.titulacio-altres').hasClass('hide') && campBuit(titulacioAltres) ) {
					errors += "<span>" + missatgeNoPotEstarBuit("TINC LA TITULACIÓ DE...") + "</span>";
				  $('#titulacio-altres').addClass('error');
				}
				else if ( !$('.titulacio-ed-secundaria').hasClass('hide') && campBuit(titulacioEdSecundaria) ) {
					errors += "<span>" + missatgeNoPotEstarBuit("LA MEVA ESPECIALITAT ÉS...") + "</span>";
				  $('#titulacio-ed-secundaria').addClass('error');
				}
				else if ( !$('.titulacio-estudiant').hasClass('hide') && campBuit(titulacioEstudiant) ) {
					errors += "<span>" + missatgeNoPotEstarBuit("SÓC ESTUDIANT DE...") + "</span>";
				  $('#titulacio-estudiant').addClass('error');
				}

				if ( errors == '' )
					enviarMsgSolicitantModificacioDades( idInsc, nom, cognoms, dni, email, tel,
						adreca, cp, poble, perfil, perfilAltres, titulacio, titulacioAltres,
						titulacioEdSecundaria, titulacioEstudiant, titulacioVaries, comentari);
				else {
					console.log(errors);
					var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
					msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
					msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
					msgError += "<i class='material-icons'>close</i></button>";
					msgError += errors + "</div>";

					$('#dades').append(msgError);
				}
			});
			activarSelects();
			//s'afegeixen totes les alertes i afegits de text dels selects
			$('.perfil').on('click', 'li', function(e) {
				var id = $(this).attr('id');
				var idInsc = $('.send-info').attr('id').split('-')[2];
				if ( $(this).html() != $('.perfil .element-selected').html() ) {
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

					if ( id == 'perfil-' + idInsc + '-altres' ) {
						$('.perfil-altres').removeClass('hide');
					}
					else {
						$('.perfil-altres').addClass('hide');
					}
					console.log( $(this).html());
				}
				else {
					console.log('equal');
				}

			});
			$('.titulacio').on('click', 'li', function(e) {
				var id = $(this).attr('id');
				var idInsc = $('.send-info').attr('id').split('-')[2];
				if ( $(this).html() != $('.titulacio .element-selected').html() ) {
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

					if ( id == 'titulacio-' + idInsc + '-altres' ) {
						$('.titulacio-altres').removeClass('hide');
						$('.titulacio-ed-secundaria').addClass('hide');
						$('.titulacio-estudiant').addClass('hide');
					}
					else if ( id == 'titulacio-' + idInsc + 'prof-ed-secundaria' ) {
						$('.titulacio-altres').addClass('hide');
						$('.titulacio-estudiant').addClass('hide');
						$('.titulacio-ed-secundaria').removeClass('hide');
					}
					else if ( id == 'titulacio-' + idInsc + '-encara-no-tinc-cap-titulacio-soc-estudiant-de-' ) {
						$('.titulacio-altres').addClass('hide');
						$('.titulacio-ed-secundaria').addClass('hide');
						$('.titulacio-estudiant').removeClass('hide');
					}
					else {
						$('.titulacio-altres').addClass('hide');
						$('.titulacio-ed-secundaria').addClass('hide');
						$('.titulacio-estudiant').addClass('hide');
					}

					console.log( $(this).html());
					$('.titulacio-tinc-varies').removeClass('hide');
				}
				else {
					console.log('equal');
				}

			});
		}
		else {
			mostrarModalErrorMsgGeneral();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
	});

}

function enviarMsgSolicitantModificacioDades( idInsc, nom, cognoms, dni, email, tel,
	adreca, cp, poble, perfil, perfilAltres, titulacio, titulacioAltres,
	titulacioEdSecundaria, titulacioEstudiant, titulacioVaries, comentari ) {
		var cnjPerfil = perfil;
		var cnjTitulacio = titulacio;
		if ( perfilAltres != '' ) {
			cnjPerfil = cnjPerfil + ': ' + perfilAltres;
		}
		if ( titulacioAltres != '' ) {
			cnjTitulacio = cnjTitulacio + ', ' + titulacioAltres;
		}
		if ( titulacioEdSecundaria != '' ) {
			cnjTitulacio = cnjTitulacio + ', ' + titulacioEdSecundaria;
		}
		if ( titulacioEstudiant != '' ) {
			cnjTitulacio = cnjTitulacio + ', ' + titulacioEstudiant;
		}
		console.log(titulacioVaries);
		if ( titulacioVaries != '' ) {
			cnjTitulacio = cnjTitulacio + ', També tinc la titulació de: ' + titulacioVaries;
		}
	var request = $.ajax({
		url: path + "dades/enviarMsgPeticioActualtizacio.php",
		method: "GET",
		data: {
			idInsc: idInsc,
			nom: nom,
			cognoms: cognoms,
			dni: dni,
			email: email,
			telefon: tel,
			adreca: adreca,
			cp: cp,
			poble: poble,
			perfil: cnjPerfil,
			titulacio: cnjTitulacio,
			comentari: comentari
		},
		dataType: "html"
	});

	request.done(function( message ) {
		var missatgeInformatiuModificacions = "<p>En 24/48h laborables realitzarem el canvi i podràs veure les modificacions a la teva <a href='https://campus.prisma.cat/alumnes/dades/' target='_blank'><strong>Intranet</strong></a>.</p>";
		missatgeInformatiuModificacions += "<p>Per a qualsevol consulta, no dubtis a posar-te en <a href='https://campus.prisma.cat/alumnes/contacte/'><strong>contacte</strong></a> amb nosaltres.</p>";

		afegirHeaderModalSuccess("S'ha enviat la petició correctament!");
		afegirTextModalSuccess(missatgeInformatiuModificacions);
		mostrarModalSuccess();

		if ( !message.includes("Error") && !message.includes("error") && !message.toLowerCase().includes("no canvi")  ) { //Hi ha un error
			//Resposta s'afegeix a $('#dades .card-body')
			var request = $.ajax({
				url: path + "dades/mostrarDades.php",
				method: "GET",
				dataType: "html"
			});

			request.done(function( result ) {
				$('#dades .card-body').html( result );
			});

			request.fail(function( jqXHR, textStatus, errorThrown ) {
				errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
			});
			//s'afegeixen totes les alertes i afegits de text dels selects
			//afegir on click d'enviar de msg
		}
		else if ( message.toLowerCase().includes("no canvi") ) {
			afegirHeaderModalError("Oops!");
			afegirTextModalError( "No has realitzat cap canvi." );
			mostrarModalError();
		}
		else {
			mostrarModalErrorMsgGeneral();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
	});

}
