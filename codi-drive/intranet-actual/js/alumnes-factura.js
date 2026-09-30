let urlPagina = window.location.pathname.split('?')[0];
let veureUnaFactura = "";
let path = "https://intranet.prisma.cat/ajax/";
let uc007SearchGeneration = 0;
let uc007SifSearchRequest = null;
let uc007LegacySearchRequest = null;

let hashUrl = null;
let tipusCerca = null;

if ( window.location.hash.split('#')[1]) {
	tipusCerca = window.location.hash.split('#')[1].split('/')[1];
	hashUrl = window.location.hash.split('#')[1].split('/')[2];
}

/* Cada vegada que es faci una crida d'un ajax, s'executarà la funció mostrarModalLoading().
Cada vegada que finalitza la crida d'un ajax, s'executarà la funció amagarLoadingModal(). */
// $(document).bind("ajaxSend", function(){
// 	mostrarModalLoading();
// }).bind("ajaxComplete", function(){
// 	amagarLoadingModal();
// });

/* Consulta el codi del main */
var requestMain = $.ajax({
	url: "https://intranet.prisma.cat/ajax/mostrarMain.php",
	method: "GET",
	data: { url : urlPagina },
	dataType: "html"
});

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
	$('#content-page').on('focus', '.form-control', function() {
		$(this).parent().removeClass('element-cercat-marcat');
	});

	//marco el input de la cerca quan s'ha escrit alguna cosa en el camp
	$('#content-page').on('blur', '.form-control', function() {
		if ($(this).val().trim() == '')
			$(this).removeClass('element-cercat-marcat');
		else
			$(this).addClass('element-cercat-marcat');
	});

	$("#content-page .select").click(function(e) {
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
	$("#content-page .select").on("click", "li", function(e) {
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
		if ( $(this).parent().parent().attr('id') == 'entitat-dispo')
			entitatMarcada = texto;
	});

	let dni = "",
		email = "",
		factRel = "",
		factNum = "",
		elementsCercats = "";

	/* Si premo la tecla ENTER, es reprodueix l'event de clicar del cercar-factura*/
	$("#mostrar-factura").keyup(function(evObject) {
		if (evObject.keyCode == 13) $('#cercar-factura').click();
	});

	/* Busco l'alumne o els diferents registres que poden coincidir amb la cerca */
	$('#cercar-factura').on('click', function() {
		mostrarModalLoading()
		dni = $('#dni').val().trim();
		email = $('#email').val().trim();
		factRel = $('#fact-rel').val().trim();
		factNum = $('#fact-num').val().trim();
		cercaPer = "RESULTATS DE LA CERCA PER ";
		elementsCercats = "";

		if ( dni == '' && email == '' && factRel == '' && factNum == '' ) {
			afegirHeaderModalError("Oops...!");
			afegirTextModalError("Omple un camp per poder fer la cerca");
			mostrarModalError();
		}
		else {
			if (dni != '')
				elementsCercats += "DNI «" + dni + "»";
			if (email != '') {
				if (elementsCercats != '')
					elementsCercats += " i ";
				elementsCercats += "E-MAIL «" + email + "»";
			}
			if (factRel != '') {
				if (elementsCercats != '')
					elementsCercats += " i ";
				elementsCercats += "FACTURA RELACIONADA «" + factRel + "»";
			}
			if (factNum != '') {
				if (elementsCercats != '')
					elementsCercats += " i ";
				elementsCercats += "NÚM. FACTURA «" + factNum + "»";
			}

			cercaPer += elementsCercats;

			/* UC-007: primer consulta SIF read-only; si no hi ha coincidències,
			es conserva el circuit llegat com a fallback temporal de migració. */
			if (typeof window.uc007SifSearch === 'function') {
				window.uc007SifSearch({
					dni: dni,
					email: email,
					factRel: factRel,
					factNum: factNum,
					cercaPer: cercaPer
				});
				return;
			}

			/* Cerca els usuaris amb les factures que el dni = dni o el email = email
			correspongui amb la inscripció relacionada amb la factura o la factura
			relacionada = factRel o el número de la factura = factNum */
			cercarFacturesLlegat(dni, email, factRel, factNum);

		}
	});

	if ( hashUrl != '' && hashUrl != null ) {
		var inputCerca = 'dni';

		if ( tipusCerca == 'dni' ) {
			inputCerca = 'dni';
		}
		if ( tipusCerca == 'factRel' ) {
			inputCerca = 'fact-rel';
		}
		if ( tipusCerca == 'factNum' ) {
			inputCerca = 'fact-num';
		}
		if ( tipusCerca == 'uuid' ) {
			mostrarModalLoading();
			window.uc007SifSearch({
				uuid: decodeURIComponent(hashUrl),
				dni: '',
				email: '',
				factRel: '',
				factNum: '',
				cercaPer: 'FACTURA SIF'
			});
		}
		else {
			$('#mostrar-factura #'+inputCerca).val(hashUrl);
			$('#mostrar-factura #'+inputCerca).prev().addClass('active');
			$('#cercar-factura').click();
		}
	}


});

requestMain.fail(function( jqXHR, textStatus, errorThrown ) {
	errorFunction( jqXHR, textStatus, errorThrown, "Hi ha hagut un error en el request Main: " );
});


/* UC-007 · Consulta SIF read-only amb fallback llegat */
window.uc007SifSearch = function(params) {
	var generation = ++uc007SearchGeneration;

	if (uc007SifSearchRequest && uc007SifSearchRequest.readyState !== 4)
		uc007SifSearchRequest.abort();
	if (uc007LegacySearchRequest && uc007LegacySearchRequest.readyState !== 4)
		uc007LegacySearchRequest.abort();

	var criteria = {};

	if (params.uuid)
		criteria.uuid_factura = params.uuid;
	if (params.factNum != '')
		criteria.num_visible = params.factNum;
	if (params.email != '')
		criteria.participant_email = params.email;
	if (params.factRel != '')
		criteria.factura_relacionada = params.factRel;
	if (params.dni != '')
		criteria.participant_document = params.dni;

	/* Si la combinació no es pot representar fidelment al SIF, mantenim el llegat. */
	if ($.isEmptyObject(criteria)) {
		cercarFacturesLlegat(params.dni, params.email, params.factRel, params.factNum, generation);
		return;
	}

	var request = $.ajax({
		url: path + "alumnes/sifFactures.php",
		method: "POST",
		contentType: "application/json; charset=utf-8",
		data: JSON.stringify({
			action: "search",
			criteria: criteria,
			limit: 50
		}),
		dataType: "json"
	});
	uc007SifSearchRequest = request;

	request.done(function(res) {
		if (generation !== uc007SearchGeneration)
			return;

		if (!res || res.ok !== true) {
			uc007MostrarError((res && res.error) ? res.error : "Resposta SIF no vàlida");
			return;
		}

		if (res.resolution === "FEATURE_DISABLED") {
			if (params.uuid) {
				uc007MostrarError("La consulta SIF està desactivada en aquest entorn");
				return;
			}
			cercarFacturesLlegat(params.dni, params.email, params.factRel, params.factNum, generation);
			return;
		}

		if (!Array.isArray(res.results) || res.results.length === 0) {
			if (params.uuid) {
				amagarLoadingModal();
				uc007MostrarError("No s'ha trobat la factura SIF sol·licitada");
				return;
			}
			cercarFacturesLlegat(params.dni, params.email, params.factRel, params.factNum, generation);
			return;
		}

		uc007RenderResultatsSif(res.results, params.cercaPer);
	});

	request.fail(function(jqXHR, textStatus) {
		if (generation !== uc007SearchGeneration || textStatus === "abort")
			return;

		amagarLoadingModal();
		var message = "No s'ha pogut consultar el SIF";
		if (jqXHR.responseJSON && jqXHR.responseJSON.error)
			message = jqXHR.responseJSON.error;
		uc007MostrarError(message);
	});
};

function cercarFacturesLlegat(dni, email, factRel, factNum, generation) {
	if (!generation)
		generation = ++uc007SearchGeneration;

	if (uc007LegacySearchRequest && uc007LegacySearchRequest.readyState !== 4)
		uc007LegacySearchRequest.abort();

	var request = $.ajax({
		url: path + "alumnes/consultaUsuarisFacturaRelacionada.php",
		method: "GET",
		data: {
			dni : dni,
			email : email,
			factRel : factRel,
			factNum : factNum
		},
		dataType: "html"
	});
	uc007LegacySearchRequest = request;

	request.done(function(dnies) {
		if (generation !== uc007SearchGeneration)
			return;

		let vectDnies = dnies.split('#');
		if (dnies.toLowerCase().includes("error")) {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de fer la consulta d'usuaris");
			mostrarModalError();
			reloadUrl();
		}
		else if (dnies.includes("No") && dnies.includes("resultats")) {
			amagarLoadingModal();
			afegirHeaderModalError("Alerta");
			afegirTextModalError("No s'han trobat resultats");
			mostrarModalError();
		}
		else if (dnies.split('|').length > 2000) {
			amagarLoadingModal();
			afegirHeaderModalError("Alerta");
			afegirTextModalError("El volum de dades cercat és molt gran. Si us plau, afegeix algun filtre més per acotar el volum de dades");
			mostrarModalError();
		}
		else if (vectDnies.length < 2 || vectDnies[1].trim() === '') {
			amagarLoadingModal();
			afegirHeaderModalError("Alerta");
			afegirTextModalError("No s'han trobat resultats");
			mostrarModalError();
		}
		else {
			let vectDnies2 = vectDnies[1].split('|').filter(function(value) {
				return value.trim() !== '';
			});
			if (vectDnies2.length === 1)
				cercarUSuari(vectDnies2[0]);
			else
				mostraLlistatUsuaris(vectDnies2.join('|'), 'cog', 'asc');
		}
	});

	request.fail(function(jqXHR, textStatus, errorThrown) {
		if (generation !== uc007SearchGeneration || textStatus === "abort")
			return;

		amagarLoadingModal();
		if (typeof errorFunction === 'function')
			errorFunction(jqXHR, textStatus, errorThrown, "Hi ha hagut algun error a l'hora de fer la consulta d'usuaris: ");
	});
}

function uc007RenderResultatsSif(resultats, titol) {
	var html = '<div class="apartat uc007-sif-results">';
	html += '<div class="apartat-header"><strong>' + uc007EscapeHtml(titol) + '</strong>';
	html += ' <span class="label label-info">SIF · només lectura</span></div>';
	html += '<div class="table-responsive"><table class="table table-hover">';
	html += '<thead><tr><th>Factura</th><th>Data</th><th>Receptor</th><th>Total</th><th>Factura</th><th>Cobrament</th><th>AEAT</th><th></th></tr></thead><tbody>';

	resultats.forEach(function(invoice) {
		var billing = invoice.billing || {};
		var totals = invoice.totals || {};
		html += '<tr>';
		html += '<td>' + uc007EscapeHtml(invoice.num_visible || '') + '</td>';
		html += '<td>' + uc007EscapeHtml(invoice.data_emissio || '') + '</td>';
		html += '<td>' + uc007EscapeHtml(billing.name || '') + '</td>';
		html += '<td>' + uc007EscapeHtml(totals.total || '') + '</td>';
		html += '<td>' + uc007EscapeHtml(invoice.estat_factura || '') + '</td>';
		html += '<td>' + uc007EscapeHtml(invoice.estat_cobrament || '') + '</td>';
		html += '<td>' + uc007EscapeHtml(invoice.estat_aeat || '');
		if (invoice.estat_aeat_divergent === true) {
			html += ' <span class="label label-warning" title="L’estat de factura i l’últim registre fiscal no coincideixen">revisar</span>';
		}
		html += '</td>';
		html += '<td><button type="button" class="btn btn-default btn-xs uc007-sif-info" data-uuid="' +
			uc007EscapeHtml(invoice.uuid_factura || '') + '">Informació</button></td>';
		html += '</tr>';
	});

	html += '</tbody></table></div></div>';
	$('#resultats-cerca').html(html).show();
	amagarLoadingModal();

	$('#resultats-cerca').off('click.uc007Sif').on('click.uc007Sif', '.uc007-sif-info', function() {
		var uuid = $(this).attr('data-uuid');
		uc007MostrarFacturaSif(uuid);
	});
}

function uc007MostrarFacturaSif(uuid) {
	mostrarModalLoading();

	var request = $.ajax({
		url: path + "alumnes/sifFactures.php",
		method: "POST",
		contentType: "application/json; charset=utf-8",
		data: JSON.stringify({
			action: "view",
			uuid_factura: uuid
		}),
		dataType: "json"
	});

	request.done(function(res) {
		amagarLoadingModal();
		if (!res || res.ok !== true) {
			uc007MostrarError((res && res.error) ? res.error : "Resposta SIF no vàlida");
			return;
		}

		$("#modalConsultaInformacio .modal-body").html(uc007RenderFacturaSif(res));
		$("#modalConsultaInformacio")
			.off('click.uc007Document')
			.on('click.uc007Document', '.uc007-sif-document-download', function() {
				uc007DescarregarDocumentSif(parseInt($(this).attr('data-document-id'), 10));
			});
		$("#modalConsultaInformacio").modal('show');
	});

	request.fail(function(jqXHR) {
		amagarLoadingModal();
		var message = "No s'ha pogut consultar la factura SIF";
		if (jqXHR.responseJSON && jqXHR.responseJSON.error)
			message = jqXHR.responseJSON.error;
		uc007MostrarError(message);
	});
}

function uc007RenderFacturaSif(res) {
	var invoice = res.invoice || {};
	var billing = invoice.billing || {};
	var totals = invoice.totals || {};
	var html = '<div class="uc007-sif-readonly">';
	html += '<p><span class="label label-info">SIF · només lectura</span></p>';
	html += '<h4>' + uc007EscapeHtml(invoice.num_visible || '') + '</h4>';
	html += '<dl class="dl-horizontal">';
	html += uc007Dl('UUID', invoice.uuid_factura);
	html += uc007Dl('Tipus', invoice.tipus_factura);
	html += uc007Dl('Data emissió', invoice.data_emissio);
	html += uc007Dl('Estat factura', invoice.estat_factura);
	html += uc007Dl('Estat cobrament', invoice.estat_cobrament);
	html += uc007Dl('Estat AEAT factura', invoice.estat_aeat_factura || invoice.estat_aeat);
	if (invoice.estat_aeat_registre)
		html += uc007Dl('Estat AEAT últim registre', invoice.estat_aeat_registre);
	if (invoice.estat_aeat_divergent === true)
		html += '<dt>AEAT</dt><dd><span class="label label-warning">Divergència a revisar</span></dd>';
	html += uc007Dl('E_FACT', invoice.e_fact);
	html += uc007Dl('Receptor', billing.name);
	html += uc007Dl('NIF/CIF', billing.nif);
	html += uc007Dl('Email', billing.email);
	html += uc007Dl('Total', totals.total);
	html += '</dl>';

	if (res.fiscal_record) {
		html += '<h5>Registre fiscal</h5><dl class="dl-horizontal">';
		html += uc007Dl('Ordre fiscal', res.fiscal_record.FISCAL_ORDER);
		html += uc007Dl('Tipus registre', res.fiscal_record.TIPUS_REGISTRE);
		html += uc007Dl('Estat AEAT registre', res.fiscal_record.ESTAT_AEAT);
		html += uc007Dl('Creat', res.fiscal_record.DATE_CREATED);
		html += uc007Dl('Enviat', res.fiscal_record.DATE_SENT);
		html += '</dl>';
	}

	if (Array.isArray(res.lines) && res.lines.length > 0) {
		html += '<h5>Línies</h5><div class="table-responsive"><table class="table table-condensed">';
		html += '<thead><tr><th>#</th><th>Concepte</th><th>Detall</th><th>Quantitat</th><th>Total</th></tr></thead><tbody>';
		res.lines.forEach(function(line) {
			html += '<tr>';
			html += '<td>' + uc007EscapeHtml(line.ORDRE || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(line.CONCEPTE || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(line.DETALL || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(line.QUANTITAT || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(line.TOTAL || '') + '</td>';
			html += '</tr>';
		});
		html += '</tbody></table></div>';
	}

	if (Array.isArray(res.payments) && res.payments.length > 0) {
		html += '<h5>Moviments econòmics</h5><div class="table-responsive"><table class="table table-condensed">';
		html += '<thead><tr><th>Data</th><th>Tipus</th><th>Mètode</th><th>Import</th><th>Assignat</th><th>Estat</th></tr></thead><tbody>';
		res.payments.forEach(function(payment) {
			html += '<tr>';
			html += '<td>' + uc007EscapeHtml(payment.DATA_MOVIMENT || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(payment.TIPUS_MOVIMENT || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(payment.METODE || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(payment.IMPORT || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(payment.IMPORT_ASSIGNAT || '') + '</td>';
			html += '<td>' + uc007EscapeHtml(payment.ESTAT || '') + '</td>';
			html += '</tr>';
		});
		html += '</tbody></table></div>';
	}

	if (Array.isArray(res.rectifications) && res.rectifications.length > 0) {
		html += '<h5>Rectificatives</h5><ul>';
		res.rectifications.forEach(function(rect) {
			html += '<li>' + uc007EscapeHtml(rect.RECTIFICATIVA_NUM_VISIBLE || rect.UUID_FACTURA_RECTIFICATIVA || '') +
				' → ' + uc007EscapeHtml(rect.RECTIFICADA_NUM_VISIBLE || rect.UUID_FACTURA_RECTIFICADA || '') + '</li>';
		});
		html += '</ul>';
	}

	if (Array.isArray(res.documents) && res.documents.length > 0) {
		html += '<h5>Documents</h5><ul>';
		res.documents.forEach(function(doc) {
			var documentId = parseInt(doc.ID, 10);
			html += '<li>' + uc007EscapeHtml(doc.TIPUS || '') + ' · ' +
				uc007EscapeHtml(doc.ESTAT || '');
			if (!isNaN(documentId) && documentId > 0) {
				html += ' <button type="button" class="btn btn-default btn-xs uc007-sif-document-download" ' +
					'data-document-id="' + documentId + '">Descarregar</button>';
			}
			html += '</li>';
		});
		html += '</ul>';
	}

	html += '</div>';
	return html;
}

function uc007DescarregarDocumentSif(documentId) {
	if (!documentId || documentId <= 0) {
		uc007MostrarError("Identificador de document no vàlid");
		return;
	}

	if (typeof fetch !== 'function') {
		uc007MostrarError("El navegador no permet la descàrrega segura del document");
		return;
	}

	mostrarModalLoading();

	fetch(path + "alumnes/sifDocument.php", {
		method: "POST",
		credentials: "same-origin",
		headers: {
			"Content-Type": "application/json; charset=utf-8",
			"X-Requested-With": "XMLHttpRequest"
		},
		body: JSON.stringify({ document_id: documentId })
	})
	.then(function(response) {
		if (!response.ok) {
			return response.json().catch(function() {
				return { error: "No s'ha pogut descarregar el document" };
			}).then(function(payload) {
				throw new Error(payload.error || "No s'ha pogut descarregar el document");
			});
		}

		var disposition = response.headers.get("Content-Disposition") || "";
		var match = disposition.match(/filename="?([^";]+)"?/i);
		var filename = match ? match[1] : ("factura-document-" + documentId);
		return response.blob().then(function(blob) {
			return { blob: blob, filename: filename };
		});
	})
	.then(function(result) {
		var objectUrl = URL.createObjectURL(result.blob);
		var link = document.createElement("a");
		link.href = objectUrl;
		link.download = result.filename;
		document.body.appendChild(link);
		link.click();
		link.remove();
		setTimeout(function() {
			URL.revokeObjectURL(objectUrl);
		}, 1000);
		amagarLoadingModal();
	})
	.catch(function(error) {
		amagarLoadingModal();
		uc007MostrarError(error.message || "No s'ha pogut descarregar el document");
	});
}

function uc007Dl(label, value) {
	if (value === null || typeof value === 'undefined' || value === '')
		return '';
	return '<dt>' + uc007EscapeHtml(label) + '</dt><dd>' + uc007EscapeHtml(value) + '</dd>';
}

function uc007EscapeHtml(value) {
	return $('<div/>').text(String(value === null || typeof value === 'undefined' ? '' : value)).html();
}

function uc007MostrarError(message) {
	amagarLoadingModal();
	afegirHeaderModalError("Alerta SIF");
	afegirTextModalError(uc007EscapeHtml(message));
	mostrarModalError();
}

/* Mostra la taula amb els usuaris trobats a partir de la cerca realitzada
ordenada per cognoms si orderby es cog, per nom si ordery es nom i per dni si
orderby es dni i en ascendentment si asc és 1 i en descendentment si és 0. */
function mostraLlistatUsuaris(dnies, orderby, asc) {

	$('#resultats-cerca').off();
	$('.table-order').off();

	var request = $.ajax({
		url: path + "alumnes/mostrarTaulaUsuaris2.php",
		method: "GET",
		data: {
			dnies : dnies,
			orderBy : orderby,
			asc : asc
		},
		dataType: "html"
	});

	//Mostra la taula amb els usuaris trobats a partir de la cerca realitzada
	request.done(function( res ) {
		$('#resultats-cerca').html(res);
		$('#resultats-cerca').show();
		amagarLoadingModal();

		//Quan sel·lecciono un registre de la taula, mostro la informació de l'usuari
		$('#resultats-cerca').on('click', '.seleccionar', function() {
			mostrarModalLoading();
			cercarUSuari($(this).attr('id'));
		});
		/* Quan es clica per ordenar, si la columna clicada està ordenada ascendentment,
		s'odrenarà descententment, sino s'ordenarà per la columna marcada asc.*/
		$('.table-order').on('click', '.sorting', function() {
			mostrarModalLoading();
			var id = $(this).attr('id').substr(3, $(this).attr('id').length);
			if ($(this).hasClass('asc'))
				mostraLlistatUsuaris(dnies, id, 0);
			else
				mostraLlistatUsuaris(dnies, id, 1);
		});
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar la taula amb els usuaris: " );
	});
}

/* Mostrar informació de l' usuari a partir del dni de l'usuari cercat */
function cercarUSuari(dniUser) {
	var request = $.ajax({
		url: path + "alumnes/mostrarTotesFacturesUsuari_Factures.php",
		method: "GET",
		data: {
			dni : dniUser,
			cercaPer : cercaPer
		},
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			$('#resultats-cerca').html(res);
			$('#resultats-cerca').show();
			amagarLoadingModal();
		}
		else {
			amagarLoadingModal();
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Aquest alumne no té DNI!");
			mostrarModalError();
			reloadUrl();
		}

		//es desactiva qualsevol event que depengui de l'apartat
		$('.regCursos').off();

		/*Si es clica el botó d'.cns-informacio' als apartats '.regCursos',
		es mostra el modal amb la informació de la factura amb una id
		de factura igual a l'id del botó */
		$('.regCursos').on('click', '.cns-informacio', function() {
			var id = $(this).attr('id').split('-')[1];
			mostrarModalConsultaInformacio(id);
		});
		/*Si es clica el botó d'.anula-factura' als apartats '.regCursos',
		es mostra el modal amb la informació de la factura amb una id
		de factura igual a l'id del botó */
		$('.regCursos').on('click', '.anula-factura', function() {
			if ( tePermisEdicio ) {
				var id = $(this).attr('id').split('-')[1];
				mostrarModalAnulaFactura(id);
			}
			else {
			   mostrarModalNoTensPermisos();
			}
		});
		/*Si es clica el botó d'.prev-factura' als apartats '.regCursos',
		es mostra el modal amb la informació de la factura amb una id
		de factura igual a l'id del botó */
		$('.regCursos').on('click', '.prev-factura', function() {
			var id = $(this).attr('id').split('-')[1];
			mostrarModalPrevisualitzaFactura(id);
		});
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar la informació de la factura d'un usuari: " );
	});
}

function mostrarModalConsultaInformacio( id ) {
	$('.modal-info').off();
	var request = $.ajax({
		url: path + "alumnes/mostraModalConsultaInformacio_Factures.php",
		method: "GET",
		data: { id : id },
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			$("#modalConsultaInformacio .modal-body").html(res);
			$("#modalConsultaInformacio").modal('show');

			/*Si es clica el botó d'.editar-apartat', s'habilita l'edició en els
			inputs de l'apartat, s'amaga el botó d'edita i s'afageix el botó de
			guardar resultat i cancel·lar */
			$('#modalConsultaInformacio #dades-factura').on('click', '.editar-apartat', function() {
				if ( tePermisEdicio ) {
					editarApartat('#modalConsultaInformacio #dades-factura');
				}
				else {
				   mostrarModalNoTensPermisos();
				}
			});
			/*Si es clica el botó d'.cancelar-apartat' a l'apartat #dades-factura',
			es deshabilita l'edició en els inputs de l'apartat, s'amaga el botó de
			guardar resultat i cancelar i s'afageix el botó d'edició */
			$('#modalConsultaInformacio #dades-factura').on('click', '.cancelar-apartat', function() {
				if ( tePermisEdicio ) {
					cancelEditarApartat('#modalConsultaInformacio #dades-factura');
				}
				else {
				   mostrarModalNoTensPermisos();
				}
			});
			/*Si es clica el botó d'.save-result' a l'apartat #dades-factura',
			es guarden els resultats a la BD a, s'amaga el botó de
			guardar resultat i cancelar i s'afageix el botó d'edició */
			$('#modalConsultaInformacio #dades-factura').on('click', '.save-result', function() {
				if ( tePermisEdicio ) {
					let idFact = $('#id-cns-fact').html().trim();
					let facturaFact = $('#factura-cns-fact').html().trim();
					let raoFact = $('#rao-cns-fact').val().trim();
					let cifFact = $('#cif-cns-fact').val().trim();
					let cpFact = $('#codipostal-cns-fact').val().trim();
					let poblacioFact = $('#poblacio-cns-fact').val().trim();
					let adrecaFact = $('#adreca-cns-fact').val().trim();
					let concepte1Fact = $('#concepte1-cns-fact').val().trim();
					let concepte2Fact = $('#concepte2-cns-fact').val().trim();
					let obsFact = $('#obs-cns-fact').val().trim();

					if ( !campBuit(raoFact) && !campBuit(cifFact) && !campBuit(concepte1Fact) ) {
						$('#modalConsultaInformacio #dades-factura .apartat').addClass('opacity-02');
						$('#modalConsultaInformacio #dades-factura .loading-wrapper').removeClass('hide');

						var requestSavePag = $.ajax({
							url: path + "alumnes/guardarDadesFactura_Factures.php",
							global: false,
							method: "POST",
							data: {
								id: idFact,
								factura: facturaFact,
								rao: raoFact,
								cif: cifFact,
								cp: cpFact,
								poblacio: poblacioFact,
								adreca: adrecaFact,
								concepte1: concepte1Fact,
								concepte2: concepte2Fact,
								obs: obsFact
							},
							dataType: "html"
						});

						requestSavePag.done(function(res) {
							if ( !res.includes("Error") && !res.includes("error") ) {
								$('#modalConsultaInformacio #dades-factura .loading-wrapper').addClass('hide');
								var msgOK = "<div class='alert alert-success alert-with-icon w-100 mb-2'>";
								msgOK += "<i class='material-icons' data-notify='icon'>notifications</i>";
								msgOK += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
								msgOK += "<i class='material-icons'>close</i></button>";
								msgOK += "<span>Els canvis s'han guardat correctament</span></div>";
								$('#modalConsultaInformacio #dades-factura .result-success').html(msgOK);
								$('#modalConsultaInformacio #dades-factura .result-success').removeClass('hide');
							}
							else {
								var msgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
								msgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
								msgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
								msgError += "<i class='material-icons'>close</i></button>";
								msgError += "<span>Hi ha hagut un error amb el registre</span></div>";
								$('#modalConsultaInformacio #dades-factura .result-success').html(msgError);
								$('#modalConsultaInformacio #dades-factura .result-success').addClass('danger');
								$('#modalConsultaInformacio #dades-factura .result-success').removeClass('hide');
							}
							setTimeout(function() {
								$('#modalConsultaInformacio #dades-factura .result-success').fadeOut('slow', function() {
									$('#modalConsultaInformacio #dades-factura .result-success').addClass('hide');
									$('#modalConsultaInformacio #dades-factura .apartat').removeClass('opacity-02');
									$("#modalConsultaInformacio #dades-factura .apartat .form-group .form-control.editables").each(function() {
										var id = $(this).attr('id');
										var text = $(this).val();
										var parent = $(this).parent();
										$(this).remove();
										parent.append("<div class='form-control no-edit editables' id='" + id + "'>" + text + "</div>");
									});
									$('#modalConsultaInformacio #dades-factura .save-result').html("edit");
									$('#modalConsultaInformacio #dades-factura .save-result').addClass("editar-apartat");
									$('#modalConsultaInformacio #dades-factura .save-result').removeClass("save-result");
									$('#modalConsultaInformacio #dades-factura .cancelar-apartat').remove();
								});
							}, 1500);
						});

						requestSavePag.fail(function(jqXHR, textStatus, errorThrown) {
							$("#modalConsultaInformacio").modal('hide');
							errorFunction(jqXHR, textStatus, errorThrown,
								"Hi ha hagut un error a l'hora de guardar les dades de la factura: ");
						});
					}
					else {
						var missatgeError="";
						if (raoFact == '') {
							missatgeError += "<span>" + missatgeNoPotEstarBuit("RAÓ") + "</span>";
							$('#modalConsultaInformacio #dades-factura #rao-cns-fact').addClass('error');
						}
						if (cifFact == '') {
							missatgeError += "<span>" + missatgeNoPotEstarBuit("CIF") + "</span>";
							$('#modalConsultaInformacio #dades-factura #cif-cns-fact').addClass('error');
						}
						if (concepte1Fact == '') {
							missatgeError += "<span>" + missatgeNoPotEstarBuit("CONCEPTE1") + "</span>";
							$('#modalConsultaInformacio #dades-factura #concepte1-cns-fact').addClass('error');
						}
						if (missatgeError != '') {
							var htmlMsgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
							htmlMsgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
							htmlMsgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
							htmlMsgError += "<i class='material-icons'>close</i></button>";
							htmlMsgError += missatgeError + "</div>";

							$('#modalConsultaInformacio #dades-factura').append(htmlMsgError);
						}
					}
				}
				else {
				   mostrarModalNoTensPermisos();
				}


			});
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la informació de la factura");
			mostrarModalError();
			reloadUrl();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar el modal de consulta la informació de la factura: " );
	});
}

/*Si es clica el botó d'.editar-apartat' a l'apartat idApartat,
s'habilita l'edició en els inputs de l'apartat,
s'amaga el botó d'edita i s'afageix el botó de guardar resultat i cancel·lar */
function editarApartat(idApartat) {
	$(idApartat + " .apartat .form-group .form-control.editables").each(function() {
		var id = $(this).attr('id');
		var text = $(this).html();
		var parent = $(this).parent();
		$(this).remove();
		parent.append("<input type='text' class='form-control edit editables' id='" + id + "' name='" + id + "' value=\"" + text + "\">");
	});
	$(idApartat + ' .editar-apartat').html("save");
	$(idApartat + ' .editar-apartat').addClass("save-result");
	$(idApartat + ' .editar-apartat').removeClass("editar-apartat");
	$(idApartat + ' .titol-apartat').append("<i class='material-icons ml-2 cancelar-apartat'>cancel</i>");
}

/*Si es clica el botó d'.cancelar-apartat' a l'apartat idApartat,
es deshabilita l'edició en els inputs de l'apartat,
s'amaga el botó de guardar resultat i cancelar i s'afageix el botó d'edició */
function cancelEditarApartat(idApartat) {
	$(idApartat + " .apartat .form-group .form-control.editables").each(function() {
		var id = $(this).attr('id');
		var text = $(this).val();
		var parent = $(this).parent();
		$(this).remove();
		parent.append("<div class='form-control no-edit editables' id='" + id + "'>" + text + "</div>");
	});
	$(idApartat + ' .save-result').html("edit");
	$(idApartat + ' .save-result').addClass("editar-apartat");
	$(idApartat + ' .save-result').removeClass("save-result");
	$(idApartat + ' .cancelar-apartat').remove();
}

/* Comprova si valor està buit. Si està buit, retorna true, altrament retorna false */
function campBuit(valor) {
	var buit = false;
	if (valor == '') buit = true;
	return buit;
}

function mostrarModalAnulaFactura( id ) {
	$('.modal-info').off();
	var request = $.ajax({
		url: path + "alumnes/mostrarModalAnulaFactura_Factures.php",
		method: "GET",
		data: { id : id },
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			$("#modalAnulaFactura .modal-body").html(res);
			$("#modalAnulaFactura").modal('show');

			$('#modalAnulaFactura').on('click', '.confirma-baixa', function() {
				console.log('confirma baixa');
				let idAnul = $('#id-anula-fact').html().trim();
				let tornarAnul = $('#import-anula-fact').val().trim();
				let dataAnul = $('#data-pag-anula-fact').val().trim();
				let obsAnul = $('#obs-anula-fact').val().trim();
				console.log('idAnul ' + idAnul);
				console.log('tornarAnul ' + tornarAnul);
				console.log('dataAnul ' + dataAnul);
				console.log('obsAnul ' + obsAnul);

				if ( !campBuit(tornarAnul) && !campBuit(dataAnul) && validNumero(tornarAnul)
				&& validData(dataAnul) ) {
					$("#modalAnulaFactura").modal('hide');
					var requestAnulFact = $.ajax({
						url: path + "alumnes/anularFactura_Factures.php",
						method: "POST",
						data: {
							id : idAnul,
							tornar : tornarAnul,
							dataAnulacio : dataAnul,
							obs : obsAnul
						},
						dataType: "html"
					});

					requestAnulFact.done(function( msg ) {
						if ( !msg.toLowerCase().includes("error") ) {
							$('#cercar-factura').click();
							afegirHeaderModalSuccess("Factura anul·lada!");
							afegirTextModalSuccess(msg);
							mostrarModalSuccess();
						}
						else {
							afegirHeaderModalError("Alerta");
							afegirTextModalError("Hi ha hagut un error a l'hora d'anul·lar la factura");
							mostrarModalError();
							reloadUrl();
						}
					});

					requestAnulFact.fail(function( jqXHRAnulFact, textStatusAnulFact, errorThrownAnulFact ) {
						errorFunction( jqXHRAnulFact, textStatusAnulFact, errorThrownAnulFact,
							"Hi ha hagut algun error a l'hora d'anul·lar la factura: " );
					});
				}
				else {
					var missatgeError="";
					if (tornarAnul == '') {
						missatgeError += "<span>" + missatgeNoPotEstarBuit("A TORNAR") + "</span>";
						$('#modalAnulaFactura #dades-factura #import-anula-fact').addClass('error');
					}
					else if ( !validNumero(tornarAnul) ) {
						missatgeError += "<span>" + missatgeNoEsNumero("A TORNAR") + "</span>";
						$('#modalAnulaFactura #dades-factura #import-anula-fact').addClass('error');
					}

					if (dataAnul == '') {
						missatgeError += "<span>" + missatgeNoPotEstarBuit("DATA DEVOLUCIÓ") + "</span>";
						$('#modalAnulaFactura #dades-factura #data-pag-anula-fact').addClass('error');
					}
					else if ( !validData(dataAnul) ) {
						missatgeError += "<span>" + missatgeNoTeFormatData("DATA DEVOLUCIÓ") + "</span>";
						$('#modalAnulaFactura #dades-factura #data-pag-anula-fact').addClass('error');
					}
					if (missatgeError != '') {
						var htmlMsgError = "<div class='alert alert-danger alert-with-icon w-100 mb-2'>";
						htmlMsgError += "<i class='material-icons' data-notify='icon'>notifications</i>";
						htmlMsgError += "<button type='button' data-dismiss='alert' aria-label='Close' class='close'>";
						htmlMsgError += "<i class='material-icons'>close</i></button>";
						htmlMsgError += missatgeError + "</div>";

						$('#modalConsultaInformacio #dades-factura').append(htmlMsgError);
					}
					console.log(missatgeError);
				}

			});
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar el modal d'anul·lació de la factura");
			mostrarModalError();
			reloadUrl();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar el modal d'anul·lació d'una factura: " );
	});
}
function mostrarModalPrevisualitzaFactura( id ) {
	$('.modal-info').off();
	let paginaFactura, numPaginesFactura;
	var request = $.ajax({
		url: path + "alumnes/mostraModalPrevFactura_Factures.php",
		method: "GET",
		data: { id : id },
		dataType: "html"
	});

	request.done(function( res ) {
		if ( !res.toLowerCase().includes("error") ) {
			paginaFactura = 1;
			numPaginesFactura = 1;
			$("#modalPrevisualizaFactura .modal-body").html(res);
			$("#modalPrevisualizaFactura").modal('show');

			$('.download-factura').off();
			$('.fletxa-left').off();
			$('.fletxa-right').off();

			var nclick = 0;

			$('.download-factura').on('click', function() {
				if ( tePermisEdicio ) {
					$("#modalPrevisualizaFactura").modal('hide');
					var idFact = $('#modalPrevisualizaFactura #factura-relacionada-fact').html().trim();
					var requestDown = $.ajax({
						url: path + "alumnes/descarregaFactura.php",
						method: "POST",
						data: { id : idFact },
						dataType: "html"
					});

					requestDown.done(function( resD ) {

						resD = $.trim(resD);
						if (resD !== '' && !resD.toLowerCase().includes("error")) {
							var link = document.createElement('a');
							link.setAttribute("id", "download-fact-" + nclick);
							link.href = path + "alumnes/" + encodeURIComponent(resD);
							link.download = resD;
							document.body.appendChild(link);
							link.click();
							link.remove();

							afegirHeaderModalSuccess("Descarregada");
							afegirTextModalSuccess("S'ha iniciat la descàrrega de la factura");
							mostrarModalSuccess();
							nclick++;
						}
						else {
							afegirHeaderModalError("Hi ha hagut un error al generar la descarrega");
							afegirTextModalError('');
							mostrarModalError();
							reloadUrl();
						}
					});

					requestDown.fail(function( jqXHRDown, textStatusDown, errorThrownDown ) {
						errorFunction( jqXHRDown, textStatusDown, errorThrownDown,
							"Hi ha hagut algun error a l'hora de descarregar la factura: " );
					});
				}
				else {
				   mostrarModalNoTensPermisos();
				}

			});

			if ($('#factura-num-pagines')) {
				numPaginesFactura = $('#factura-num-pagines').html();
			}

			$('.fletxa-left').on('click', function() {
				if (paginaFactura > 1) {
					$('#pagina-factura' + paginaFactura).fadeOut('fast', function() {
						paginaFactura--;
						$('#pagina-factura' + paginaFactura).fadeIn('fast', function() {
							$('#factura-pagina-actual').html(paginaFactura);
						});
					});
				}

			});
			$('.fletxa-right').on('click', function() {
				if (paginaFactura < numPaginesFactura) {
					$('#pagina-factura' + paginaFactura).fadeOut('fast', function() {
						paginaFactura++;
						$('#pagina-factura' + paginaFactura).fadeIn('fast', function() {
							$('#factura-pagina-actual').html(paginaFactura);
						});
					});
				}

			});
		}
		else {
			afegirHeaderModalError("Alerta");
			afegirTextModalError("Hi ha hagut un error a l'hora de mostrar la previsualització de la factura");
			mostrarModalError();
			reloadUrl();
		}
	});

	request.fail(function( jqXHR, textStatus, errorThrown ) {
		errorFunction( jqXHR, textStatus, errorThrown,
			"Hi ha hagut algun error al mostrar el modal de previsualitzar la factura: " );
	});
}
