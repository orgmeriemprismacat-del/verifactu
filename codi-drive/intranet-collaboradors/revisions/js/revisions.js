let path = "https://campus.prisma.cat/intranet-collaboradors/revisions/";
let revisat,
	codeRev = '',
	codeErrorsGen = '',
	codeErrorsLectures = '',
	codeErrorsMediateca = '',
	codeErrorsBiblio = '',
	codeErrorsAltres = '';
let numApartatsLectures = 3; //indico el numero de l'apartat de lectures;
let numApartatBiblio = 4; //indico el numero de l'apartat de biblio;
let numApartatMediateca = 5; //indico el numero de l'apartat de mediateca;
let num_apartats = "", user, shortname;

function mostrarRevisio() {
   num_apartats = $('#num_apartats').val().trim();
   var hores = $('#hores').val().trim();
   shortname = $('#shortname').val().trim();
   var course = $('#course').val().trim();
   user = $('#user').val().trim();

   var showTable = $.ajax({
		url: path + "ajax/mostrarTaula.php",
		method: "GET",
		data: {
			hores: hores,
			shortname: shortname,
			course: course,
			usuari: user,
			num_apartats: num_apartats
		},
		dataType: "html"
	});
	showTable.done(function(msg) {
		var revisio = msg;
		revisio += modalError();
		revisio += modalConfirmacio();
		$('#revisio').html(revisio);

		if ( msg.toLowerCase().includes("revisió està enviada") ) {
			$('#revisio').removeClass('justify-content-center');
		}

		/* ############################ ACCIONS DE FORMS ############################ */
		$('body').on('focus', '.form-control', function() {
			$(this).prev().addClass('active');
			$(this).parent().removeClass('element-cercat-marcat');
		});
		$('body').on('blur', '.form-control', function() {
			if ($(this).val().trim() == '')
				$(this).prev().removeClass('active');

			//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
			if ($(this).val().trim() == '' || $(this).val().trim() == 'Cap') {
				$(this).removeClass('element-cercat-marcat');
			}
			else
				$(this).addClass('element-cercat-marcat');
		});

		$('body').on('focus', '.addError', function() {
			if ( $(this).hasClass('mediateca') || $(this).hasClass('bibliografia') ) {
				var cntInc = "<div class='d-flex flex-column flex-md-row justify-content-center align-items-center'>";
				cntInc = "<div class='form-group field-wrap position-relative mb-0 p-1 w-100'>";
				cntInc = "<label class=''>Incidència</label>";
				cntInc = "<textarea type='text' class='form-control incidencia' id='inc" + j + "_incidencia'></textarea>";
				cntInc = "</div>";
				cntInc = "<div class='form-group field-wrap position-relative mb-0 p-1 w-100'>";
				cntInc = "<label class=''>Proposta</label>";
				cntInc = "<textarea type='text' class='form-control solucio' id='inc" + j + "_solucio'></textarea>";
				cntInc = "</div>";
				cntInc = "</div>";
			}
			else {
				var cntInc = "<div class='d-flex flex-column flex-md-row justify-content-center align-items-center'>";
				cntInc = "<div class='form-group field-wrap position-relative mb-0 p-1 w-100'>";
				cntInc = "<label class=''>Incidència</label>";
				cntInc = "<textarea type='text' class='form-control incidencia' id='inc" + j + "_incidencia'></textarea>";
				cntInc = "</div>";
				cntInc = "</div>";
			}

			$(this).prepend(cntInc);
		});

		$(".btn-group").on('click', '.btn-switch', function() {
			var idBtn = $(this).parent().attr('id');
			$('#'+idBtn+" .btn-switch").removeClass('active');
			$(this).addClass('active');
		})
		$(".cnt_revisio").on('click', '.addInc', function() {
			var idBoto = $(this).attr('id');
			var idElement = idBoto.split('-')[1];

			var cntElemntsInc = $(".cnt-inputs-incidencia-" + idElement).length;

			var inputIncidencia = obtInput( 'mb-1', '', 'Incidència', 'incidencia', 'incidencia-'+idElement+"-"+cntElemntsInc, '');
			var inputProposta = "";
			if ( idElement == numApartatsLectures || idElement == numApartatBiblio || idElement == numApartatMediateca)
				inputProposta = obtInput( 'mb-1', '', 'Proposta', 'proposta', 'proposta-'+idElement+"-"+cntElemntsInc, '');
			var botoRemove = "<i class='fa-solid fa-circle-minus removeInputs'></i>";

			var cntInputs = "<div id='cnt-inputs-incidencia-" + idElement + "-" + cntElemntsInc + "' class='d-flex flex-column flex-sm-row justify-content-center align-items-center cnt-inputs-incidencia-" + idElement + "'>";
			cntInputs += inputIncidencia + inputProposta + botoRemove + "</div>";

			$(this).parent().prepend(cntInputs);
		});
		$(".cnt_revisio").on('click', '.removeInputs', function() {
			$(this).parent().remove();
		});

	});

	showTable.fail(function(XMLHttpRequest, textStatus, errorThrown) {
		mostrarModalError(errorThrown);
	});

	$('body').on('click', '#desaNoEnviar', function() {
		console.log('desaNoEnviar');
		codeRev = generarCodificacioRevisio();
		codeErrorsGen = generarCodificacioErrors(1, 2);
		codeErrorsLectures = generarCodificacioErrors(numApartatsLectures, numApartatsLectures);
		codeErrorsBiblio = generarCodificacioErrors(numApartatBiblio, numApartatBiblio);
		codeErrorsMediateca = generarCodificacioErrors(numApartatMediateca, numApartatMediateca);
		codeErrorsAltres = generarCodificacioErrors(6, num_apartats);
		console.log(codeRev);
		console.log(codeErrorsGen);
		console.log(codeErrorsLectures);
		console.log(codeErrorsMediateca);
		console.log(codeErrorsBiblio);
		console.log(codeErrorsAltres);
		//insert/update codeRev a revisat
		//insert/update cada apartat a cada camp INC_GENERAL, INC_LECTURES, INC_MEDIATECA, INC_BIBLIO, INC_ALTRES
		saveResults(0);
	});
	$('body').on('click', '#desaEnviar', function() {
		console.log('desaEnvia');
		var revisat = 1, errors = '';
		var omplertIncidencies = 1, errorsIncidencia = '';
		var omplertPropostes = 1, errorsPropostes = '';
      for ( i=1;  i<=num_apartats;  i++) {
			// Comprovem que han marcat el camp REVISAT
			if ( $('#rev'+i + " .no-revisat").hasClass('active') ) {
				if ( errors != '') errors += ", ";
				errors += "<strong>" + $('#element-'+i).html() + "</strong>";
				if ( revisat ) revisat = 0;
			}
			if ( $('.cnt-inputs-incidencia-'+i + " .incidencia")[0] && $('.cnt-inputs-incidencia-'+i + " .incidencia").val().trim() == '' ) {
				if ( errorsIncidencia != '') errorsIncidencia += ", ";
				errorsIncidencia += "<strong>" + $('#element-'+i).html() + "</strong>";
				if ( omplertIncidencies ) omplertIncidencies = 0;
			}
			if ( $('.cnt-inputs-incidencia-'+i + " .proposta")[0] && $('.cnt-inputs-incidencia-'+i + " .proposta").val().trim() == '' ) {
				if ( errorsPropostes != '') errorsPropostes += ", ";
				errorsPropostes += "<strong>" + $('#element-'+i).html() + "</strong>";
				if ( omplertPropostes ) omplertPropostes = 0;
			}
		}

		if ( !revisat || !omplertIncidencies || !omplertPropostes ) {
			var msgError = "";
			if ( !revisat )
				msgError += "<p>Falta marcar com a revisat els apartats " + errors + ".</p>";
			if ( !omplertIncidencies )
				msgError += "<p>Falta omplir els camps de les incidències dels apartats " + errorsIncidencia + ".</p>";
			if ( !omplertPropostes )
				msgError += "<p>Falta omplir els camps de les propostes d'incidències dels apartats " + errorsPropostes + ".</p>";

			msgError += "<p>Gràcies!</p>";
			$('#modalErrors .modal-title').html("Alerta!");
			$('#modalErrors .modal-body').html(msgError);
			$('#modalErrors').modal('show');
		}
		else {
			codeRev = generarCodificacioRevisio();
			codeErrorsGen = generarCodificacioErrors(1, 2);
			codeErrorsLectures = generarCodificacioErrors(numApartatsLectures, numApartatsLectures);
			codeErrorsBiblio = generarCodificacioErrors(numApartatBiblio, numApartatBiblio);
			codeErrorsMediateca = generarCodificacioErrors(numApartatMediateca, numApartatMediateca);
			codeErrorsAltres = generarCodificacioErrors(6, num_apartats);
			console.log(codeRev);
			console.log(codeErrorsGen);
			console.log(codeErrorsLectures);
			console.log(codeErrorsMediateca);
			console.log(codeErrorsBiblio);
			console.log(codeErrorsAltres);
			//insert/update codeRev a revisat
			//insert/update cada apartat a cada camp INC_GENERAL, INC_LECTURES, INC_MEDIATECA, INC_BIBLIO, INC_ALTRES
			saveResults(1);
		}
   });
}

function obtInput( classForm, classLabel, nameLabel, classInput, idInput, valorInput) {
	var input = "<div class='form-group field-wrap position-relative mb-0 p-1 w-100 " + classForm + "'>";
   input += "<label class='" + classLabel + "'>" + nameLabel + "</label>";
   input += "<textarea type='text' class='form-control " + classInput + "'";
   input += "id='" + idInput + "' name='" + idInput + "'  >" + valorInput + "</textarea>";
	input += "</div>";

	return input;
}

function generarCodificacioRevisio() {
	var code = '';
	for ( i=1; i<=num_apartats;  i++ ) {
		code += "[REV]";
		if ( $("#rev"+i + " .no-revisat").hasClass('active') )
			code += "0";
		else
			code += "1";
	}
	return code;
}

function generarCodificacioErrors(inici, fi) {
	var code = '';
	for ( i=inici; i<=fi;  i++ ) {
		code += "[REV]";
		if ( $( ".cnt-inputs-incidencia-" + i ).length == 0 ) {
			code += "Cap";
		}
		else {
			$( ".cnt-inputs-incidencia-" + i ).each(function( index ) {
				code += "[INC_XX]";
				code += $( this ).find('.incidencia').val();

				if ( i == numApartatsLectures || i == numApartatBiblio || i == numApartatMediateca ) {
					code += "_####_";
					code += $( this ).find('.proposta').val();
				}
			});
		}
	}
	return code;
}

function modalConfirmacio() {
	var modal = "<div class='modal fade in' id='modalConfirmacio' tabindex='-1' role='dialog' aria-labelledby='modalConfirmacio' style='display: none;' aria-hidden='true'>";
	modal += "<div class='modal-dialog modal-dialog-centered modal-notify modal-success' role='document'>";
	modal += "<div class='modal-content w-100 border-0 p-4 ps2'>";
	modal += "<div class='modal-header border-0 d-flex flex-column justify-content-center align-items-center position-relative p-0'>";
	modal += "<p class='modal-title font-weight-bold text-center mt-2 mb-3'></p>";
	modal += "</div>";
	modal += "<div class='modal-body pt-2 text-center'></div>";
	modal += "<div class='modal-footer border-0 d-flex align-items-center justify-content-center p-0'>";
	modal += "<a type='button' class='btn boto-verd text-white' aria-label='Close' data-dismiss='modal'>Tanca</a>";
	modal += "</div>";
	modal += "</div>";
	modal += "</div></div>";
	return modal;
}

function modalError() {
	var modal = "<div class='modal fade in' id='modalErrors' tabindex='-1' role='dialog' aria-labelledby='modalErrorsTitle' style='display: none;' aria-hidden='true'>";
	modal += "<div class='modal-dialog modal-dialog-centered modal-notify modal-danger' role='document'>";
	modal += "<div class='modal-content w-100 border-0 p-4 mh-100 ps2'>";
	modal += "<div class='modal-header border-0 d-flex flex-column justify-content-center align-items-center position-relative p-0'>";
	modal += "<p class='modal-title font-weight-bold text-center text-danger'></p>";
	modal += "<button type='button' class='close' data-dismiss='modal'>×</button>";
	modal += "</div>";
	modal += "<div class='modal-body ps2 pt-2 text-center' id='modalErrorsBody'></div>";
	modal += "<div class='modal-footer border-0 d-flex align-items-center justify-content-center p-0'>";
	modal += "<a type='button' class='btn btn-danger text-white' aria-label='Close' data-dismiss='modal'>Tanca</a>";
	modal += "</div>";
	modal += "</div>";
	modal += "</div></div>";
	return modal;
}

//Guarda els resultats a la base de dades i si send == 1 i existeix alguna incidencia, envia també un missatge informant que s'ha revisat
function saveResults(send) {
	var campUpd = "revisat";

	var saveRevisio = $.ajax({
		url: path + "ajax/saveApartat.php",
		method: "GET",
		data: {
			shortname: shortname,
			codificacio: codeRev,
			campUpd: campUpd
		},
		dataType: "html"
	});
	saveRevisio.done(function(msgRevisio) {
		console.log('ok');
		if ( !msgRevisio.toLowerCase().includes("MySQL error") ) {
			var saveGen = $.ajax({
				url: path + "ajax/saveApartat.php",
				method: "GET",
				data: {
					shortname: shortname,
					codificacio: codeErrorsGen,
					campUpd: "INC_GENERAL"
				},
				dataType: "html"
			});
			saveGen.done(function(msgGen) {
				console.log('ok');
				if ( !msgGen.toLowerCase().includes("MySQL error") ) {
					var saveLectures = $.ajax({
						url: path + "ajax/saveApartat.php",
						method: "GET",
						data: {
							shortname: shortname,
							codificacio: codeErrorsLectures,
							campUpd: "INC_LECTURES"
						},
						dataType: "html"
					});
					saveLectures.done(function(msgLectures) {
						console.log('ok');
						if ( !msgLectures.toLowerCase().includes("MySQL error") ) {
							var saveMediateca = $.ajax({
								url: path + "ajax/saveApartat.php",
								method: "GET",
								data: {
									shortname: shortname,
									codificacio: codeErrorsMediateca,
									campUpd: "INC_MEDIATECA"
								},
								dataType: "html"
							});
							saveMediateca.done(function(msgMediateca) {
								console.log('ok');
								if ( !msgMediateca.toLowerCase().includes("MySQL error") ) {
									var saveBiblio = $.ajax({
										url: path + "ajax/saveApartat.php",
										method: "GET",
										data: {
											shortname: shortname,
											codificacio: codeErrorsBiblio,
											campUpd: "INC_BIBLIO"
										},
										dataType: "html"
									});
									saveBiblio.done(function(msgBiblio) {
										console.log('ok');
										if ( !msgBiblio.toLowerCase().includes("MySQL error") ) {
											var saveAltres = $.ajax({
												url: path + "ajax/saveApartat.php",
												method: "GET",
												data: {
													shortname: shortname,
													codificacio: codeErrorsAltres,
													campUpd: "INC_ALTRES"
												},
												dataType: "html"
											});
											saveAltres.done(function(msgAltres) {
												console.log('ok');
												if ( !msgAltres.toLowerCase().includes("MySQL error") ) {
													var msgConf = "";
													if ( send == 1 ) {
														$('#modalConfirmacio .modal-title').html("Enregistrat i enviat!");
														msgConf = "<p>La revisió s'ha enviat correctament.</p><p>Per a qualsevol modificació, consulta amb <strong>secretaria@prisma.cat</strong></p>";
														var existeixIncidencia = 0;
														if ( $("textarea")[0] ) existeixIncidencia = 1;
														var sendIncidencia = $.ajax({
															url: path + "ajax/sendRevisio.php",
															method: "GET",
															data: {
																shortname: shortname,
																user: user,
																incidencies: existeixIncidencia
															},
															dataType: "html"
														});
														sendIncidencia.done(function(msgIncidencia) {

														});
														sendIncidencia.fail(function(XMLHttpRequest, textStatus, errorThrown) {
															mostrarModalError(errorThrown);
														});
													}
													else {
														$('#modalConfirmacio .modal-title').html("Enregistrat!");
														msgConf = "<p>La revisió s'ha desat correctament.</p>";
													}
													$('#modalConfirmacio .modal-body').html(msgConf);
													$('#modalConfirmacio').modal('show');

													$("#modalConfirmacio").on('hidden.bs.modal', function() {
														location.reload();
													})
												}
												else {
													$('#modalErrors .modal-title').html("Hi ha hagut un error al guardar la revisió.");
													$('#modalErrors .modal-body').html("");
													$('#modalErrors').modal('show');
												}

											});
											saveAltres.fail(function(XMLHttpRequest, textStatus, errorThrown) {
												mostrarModalError(errorThrown);
											});
										}
										else {
											$('#modalErrors .modal-title').html("Hi ha hagut un error al guardar la revisió.");
											$('#modalErrors .modal-body').html("");
											$('#modalErrors').modal('show');
										}

									});
									saveBiblio.fail(function(XMLHttpRequest, textStatus, errorThrown) {
										mostrarModalError(errorThrown);
									});
								}
								else {
									$('#modalErrors .modal-title').html("Hi ha hagut un error al guardar la revisió.");
									$('#modalErrors .modal-body').html("");
									$('#modalErrors').modal('show');
								}

							});
							saveMediateca.fail(function(XMLHttpRequest, textStatus, errorThrown) {
								mostrarModalError(errorThrown);
							});
						}
						else {
							$('#modalErrors .modal-title').html("Hi ha hagut un error al guardar la revisió.");
							$('#modalErrors .modal-body').html("");
							$('#modalErrors').modal('show');
						}

					});
					saveLectures.fail(function(XMLHttpRequest, textStatus, errorThrown) {
						mostrarModalError(errorThrown);
					});
				}
				else {
					$('#modalErrors .modal-title').html("Hi ha hagut un error al guardar la revisió.");
					$('#modalErrors .modal-body').html("");
					$('#modalErrors').modal('show');
				}

			});
			saveGen.fail(function(XMLHttpRequest, textStatus, errorThrown) {
				mostrarModalError(errorThrown);
			});
		}
		else {
			$('#modalErrors .modal-title').html("Hi ha hagut un error al guardar la revisió.");
			$('#modalErrors .modal-body').html("");
			$('#modalErrors').modal('show');
		}

	});
	saveRevisio.fail(function(XMLHttpRequest, textStatus, errorThrown) {
		mostrarModalError(errorThrown);
	});
}
