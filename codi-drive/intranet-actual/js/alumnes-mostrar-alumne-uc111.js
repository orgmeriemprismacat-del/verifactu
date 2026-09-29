(function ($) {
	'use strict';

	function normalizeRequestDni(data) {
		if (!data) return '';
		if (typeof data === 'object' && data.dni) return String(data.dni);
		if (typeof data !== 'string') return '';
		try {
			return new URLSearchParams(data).get('dni') || '';
		} catch (e) {
			return '';
		}
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value)
			.replace(/&/g, '&amp;')
			.replace(/</g, '&lt;')
			.replace(/>/g, '&gt;')
			.replace(/"/g, '&quot;')
			.replace(/'/g, '&#039;');
	}

	function formatEuro(value) {
		var number = Number(value);
		if (!Number.isFinite(number)) return escapeHtml(String(value || '0.00')) + ' €';
		return number.toLocaleString('ca-ES', { minimumFractionDigits: 2, maximumFractionDigits: 2 }) + ' €';
	}

	function formatDate(value) {
		if (!value) return '—';
		var normalized = String(value).replace(' ', 'T');
		var date = new Date(normalized);
		if (isNaN(date.getTime())) return escapeHtml(String(value));
		return date.toLocaleString('ca-ES');
	}

	function entitlementLabel(status) {
		var labels = {
			GRANTED: 'Concedit',
			ACTIVE: 'Actiu',
			CODE_PREPARED: 'Codi preparat',
			DELIVERED: 'Enviat',
			PARTIALLY_RESERVED: 'Reserva en curs',
			PARTIALLY_USED: 'Parcialment utilitzat',
			EXHAUSTED: 'Esgotat',
			EXPIRED: 'Caducat',
			CANCELLED: 'Cancel·lat'
		};
		return labels[status] || status || 'Desconegut';
	}

	function applicationLabel(status) {
		var labels = {
			RESERVED: 'Reservat',
			APPLIED: 'Aplicat',
			RELEASED: 'Alliberat',
			REVERSED: 'Revertit'
		};
		return labels[status] || status || '—';
	}

	function amountCell(label, value) {
		return "<div class='col-6 col-md-3 mb-2'><div class='small text-muted'>" +
			escapeHtml(label) + "</div><div class='font-weight-bold'>" + escapeHtml(formatEuro(value)) + "</div></div>";
	}

	function renderRight(right, index) {
		var origin = right.origin || {};
		var html = "<div class='border rounded p-3" + (index > 0 ? " mt-3" : "") + "'>";
		html += "<div class='d-flex justify-content-between align-items-start flex-wrap'>";
		html += "<div><h5 class='mb-1'>Dret promocional docent novell</h5>";
		html += "<div class='text-muted small'>Origen: " +
			escapeHtml((origin.product_code || 'JASOM') + ' ' + (origin.product_edition || '')) +
			" · Inscripció #" + escapeHtml(origin.enrollment_id || '') + "</div></div>";
		html += "<span class='badge badge-secondary'>" + escapeHtml(entitlementLabel(right.display_status)) + "</span></div>";

		html += "<div class='row mt-3'>" +
			amountCell('Concedit', right.original_amount) +
			amountCell('Utilitzat', right.applied_amount) +
			amountCell('Reservat', right.reserved_amount) +
			amountCell('Disponible', right.available_amount) +
			"</div>";

		html += "<div class='small mt-2'><strong>Concessió:</strong> " + formatDate(right.issued_at) +
			" · <strong>Caducitat:</strong> " + formatDate(right.expires_at) +
			" · <strong>Lliurament:</strong> " + escapeHtml(right.delivery_status || 'pendent') + "</div>";

		html += "<div class='mt-3'><strong>Historial d’ús</strong>";
		if (!right.applications || right.applications.length === 0) {
			html += "<div class='text-muted mt-1'>Encara no s'ha utilitzat aquest dret.</div>";
		} else {
			html += "<div class='table-responsive mt-2'><table class='table table-sm'><thead><tr>" +
				"<th>Estat</th><th>Curs destí</th><th>Import</th><th>Factura</th><th>Data</th>" +
				"</tr></thead><tbody>";
			right.applications.forEach(function (app) {
				html += "<tr><td>" + escapeHtml(applicationLabel(app.status)) + "</td>" +
					"<td>" + escapeHtml((app.destination_product_code || '') + ' ' + (app.destination_product_edition || '')) +
					" <span class='text-muted'>#" + escapeHtml(app.destination_enrollment_id || '') + "</span></td>" +
					"<td>" + escapeHtml(formatEuro(app.amount)) + "</td>" +
					"<td>" + escapeHtml(app.invoice_number || '—') + "</td>" +
					"<td>" + formatDate(app.applied_at || app.reserved_at) + "</td></tr>";
			});
			html += "</tbody></table></div>";
		}
		html += "</div></div>";
		return html;
	}

	function loadPromotion(dniUser) {
		if (!dniUser || !$('#resultats-cerca').is(':visible')) return;

		$('#promocio-docent-novell').remove();
		$('#resultats-cerca').append(
			"<section id='promocio-docent-novell' class='card mt-4 mb-4'>" +
			"<div class='card-header'><strong>Promoció docent novell</strong></div>" +
			"<div class='card-body'><div class='text-muted'>Carregant informació del SIF...</div></div></section>"
		);

		$.ajax({
			url: '/ajax/alumnes/mostrarPromocioDocentNovell.php',
			method: 'GET',
			data: { dni: dniUser },
			dataType: 'json',
			global: false
		}).done(function (res) {
			if (!res || res.ok !== true) {
				$('#promocio-docent-novell .card-body').html(
					"<div class='alert alert-warning mb-0'>No s'ha pogut carregar la informació promocional del SIF.</div>"
				);
				return;
			}
			if (!res.rights || res.rights.length === 0) {
				$('#promocio-docent-novell .card-body').html(
					"<div class='text-muted'>Aquesta persona no té cap dret de promoció docent novell registrat.</div>"
				);
				return;
			}
			var html = '';
			res.rights.forEach(function (right, index) { html += renderRight(right, index); });
			$('#promocio-docent-novell .card-body').html(html);
		}).fail(function () {
			$('#promocio-docent-novell .card-body').html(
				"<div class='alert alert-warning mb-0'>El SIF no està disponible per a aquesta consulta.</div>"
			);
		});
	}

	$(document).on('ajaxComplete.uc111', function (_event, _xhr, settings) {
		var url = String((settings && settings.url) || '');
		if (url.indexOf('alumnes/mostrarInformacioUsuari.php') === -1) return;
		var dniUser = normalizeRequestDni(settings.data);
		if (dniUser.trim() !== '') loadPromotion(dniUser.trim());
	});
})(jQuery);
