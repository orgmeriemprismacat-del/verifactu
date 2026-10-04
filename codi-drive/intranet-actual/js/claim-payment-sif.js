(function () {
	'use strict';

	const ENDPOINT = 'https://intranet.prisma.cat/ajax/facturacio/registerClaimPaymentSif.php';
	const BUTTON_CLASS = 'uc024-register-payment';
	const OVERLAY_ID = 'uc024-payment-overlay';

	function csrfToken() {
		const meta = document.querySelector('meta[name="csrf-token-claim-payment"]');
		return meta ? (meta.getAttribute('content') || '') : '';
	}

	function newRequestId() {
		if (window.crypto && typeof window.crypto.randomUUID === 'function') {
			return window.crypto.randomUUID();
		}
		return 'uc024-' + Date.now().toString(36) + '-' + Math.random().toString(36).slice(2, 14);
	}

	function localDateTimeValue() {
		const now = new Date();
		const offset = now.getTimezoneOffset();
		const local = new Date(now.getTime() - offset * 60000);
		return local.toISOString().slice(0, 16);
	}

	function createButton(row) {
		if (!row || row.querySelector('.' + BUTTON_CLASS)) return;

		const idInsc = parseInt(row.getAttribute('data-id-insc') || '', 10);
		if (!Number.isInteger(idInsc) || idInsc <= 0) return;

		const cells = row.querySelectorAll('td');
		if (!cells.length) return;

		const button = document.createElement('button');
		button.type = 'button';
		button.className = BUTTON_CLASS + ' btn btn-sm btn-outline-primary mt-2';
		button.textContent = 'Registrar cobrament';
		button.setAttribute('data-id-insc', String(idInsc));
		button.addEventListener('click', function (event) {
			event.preventDefault();
			event.stopPropagation();
			openDialog(idInsc);
		});

		cells[cells.length - 1].appendChild(button);
	}

	function decorateRows(root) {
		(root || document).querySelectorAll('tr[data-id-insc]').forEach(createButton);
	}

	function ensureOverlay() {
		let overlay = document.getElementById(OVERLAY_ID);
		if (overlay) return overlay;

		overlay = document.createElement('div');
		overlay.id = OVERLAY_ID;
		overlay.setAttribute('aria-hidden', 'true');
		overlay.innerHTML =
			'<div class="uc024-payment-backdrop"></div>' +
			'<section class="uc024-payment-panel" role="dialog" aria-modal="true" aria-labelledby="uc024-payment-title">' +
				'<div class="uc024-payment-header">' +
					'<h3 id="uc024-payment-title">Registrar cobrament al SIF</h3>' +
					'<button type="button" class="uc024-close" aria-label="Tancar">&times;</button>' +
				'</div>' +
				'<form id="uc024-payment-form" novalidate>' +
					'<input type="hidden" name="idInsc">' +
					'<input type="hidden" name="requestId">' +
					'<label>Tipus de referència externa' +
						'<select name="externalReceiptType">' +
							'<option value="BANK_REFERENCE">Referència bancària</option>' +
							'<option value="DS_ORDER">DS_ORDER Redsys</option>' +
							'<option value="PROVIDER_REF">Referència del proveïdor</option>' +
						'</select>' +
					'</label>' +
					'<label>Referència externa del cobrament' +
						'<input name="externalReceiptId" type="text" maxlength="120" required autocomplete="off" ' +
						'placeholder="Identificador únic del rebut o operació">' +
					'</label>' +
					'<label>Import cobrat (€)' +
						'<input name="amount" type="number" min="0.01" step="0.01" required inputmode="decimal">' +
					'</label>' +
					'<label>Data i hora del cobrament' +
						'<input name="movementDate" type="datetime-local" required>' +
					'</label>' +
					'<label>Mètode' +
						'<select name="method">' +
							'<option value="TRANSFERENCIA">Transferència</option>' +
							'<option value="MANUAL">Manual</option>' +
						'</select>' +
					'</label>' +
					'<label>Banc (opcional)' +
						'<input name="bank" type="text" maxlength="80" autocomplete="off">' +
					'</label>' +
					'<label>Observacions (opcional)' +
						'<textarea name="notes" maxlength="1000" rows="3"></textarea>' +
					'</label>' +
					'<div class="uc024-payment-warning">' +
						'Registra només un ingrés ja confirmat. El SIF resoldrà la factura vinculada a la inscripció.' +
					'</div>' +
					'<div class="uc024-payment-error" role="alert" hidden></div>' +
					'<div class="uc024-payment-actions">' +
						'<button type="button" class="uc024-cancel btn btn-secondary">Cancel·la</button>' +
						'<button type="submit" class="uc024-submit btn btn-primary">Registrar cobrament</button>' +
					'</div>' +
				'</form>' +
			'</section>';

		const style = document.createElement('style');
		style.textContent =
			'#' + OVERLAY_ID + '{position:fixed;inset:0;z-index:20000;display:none}' +
			'#' + OVERLAY_ID + '.is-open{display:block}' +
			'.uc024-payment-backdrop{position:absolute;inset:0;background:rgba(0,0,0,.48)}' +
			'.uc024-payment-panel{position:relative;background:#fff;max-width:560px;margin:5vh auto;padding:20px;border-radius:8px;max-height:90vh;overflow:auto}' +
			'.uc024-payment-header{display:flex;align-items:center;justify-content:space-between;gap:16px;margin-bottom:16px}' +
			'.uc024-payment-header h3{margin:0;font-size:1.25rem}' +
			'.uc024-close{border:0;background:transparent;font-size:2rem;line-height:1;cursor:pointer}' +
			'#uc024-payment-form{display:grid;gap:12px}' +
			'#uc024-payment-form label{display:grid;gap:5px;font-weight:600}' +
			'#uc024-payment-form input,#uc024-payment-form select,#uc024-payment-form textarea{width:100%;padding:8px;border:1px solid #bbb;border-radius:4px;font-weight:400}' +
			'.uc024-payment-warning{padding:10px;background:#fff3cd;border-radius:4px}' +
			'.uc024-payment-error{padding:10px;background:#f8d7da;border-radius:4px}' +
			'.uc024-payment-actions{display:flex;justify-content:flex-end;gap:8px;margin-top:4px}' +
			'.' + BUTTON_CLASS + '{white-space:nowrap}';
		document.head.appendChild(style);
		document.body.appendChild(overlay);

		overlay.querySelector('.uc024-close').addEventListener('click', closeDialog);
		overlay.querySelector('.uc024-cancel').addEventListener('click', closeDialog);
		overlay.querySelector('.uc024-payment-backdrop').addEventListener('click', closeDialog);
		overlay.querySelector('#uc024-payment-form').addEventListener('submit', submitPayment);

		return overlay;
	}

	function openDialog(idInsc) {
		const overlay = ensureOverlay();
		const form = overlay.querySelector('#uc024-payment-form');
		form.reset();
		form.elements.idInsc.value = String(idInsc);
		form.elements.requestId.value = newRequestId();
		form.elements.movementDate.value = localDateTimeValue();
		const error = overlay.querySelector('.uc024-payment-error');
		error.hidden = true;
		error.textContent = '';
		overlay.classList.add('is-open');
		overlay.setAttribute('aria-hidden', 'false');
		form.elements.externalReceiptId.focus();
	}

	function closeDialog() {
		const overlay = document.getElementById(OVERLAY_ID);
		if (!overlay) return;
		overlay.classList.remove('is-open');
		overlay.setAttribute('aria-hidden', 'true');
	}

	function errorText(payload, fallback) {
		if (payload && typeof payload.error === 'string' && payload.error.trim() !== '') {
			return payload.error.trim();
		}
		return fallback;
	}

	async function submitPayment(event) {
		event.preventDefault();

		const form = event.currentTarget;
		if (!form.reportValidity()) return;

		const token = csrfToken();
		const overlay = document.getElementById(OVERLAY_ID);
		const errorBox = overlay.querySelector('.uc024-payment-error');
		const submit = form.querySelector('.uc024-submit');

		if (!token) {
			errorBox.textContent = 'No hi ha token de seguretat disponible. Recarrega la pàgina.';
			errorBox.hidden = false;
			return;
		}

		const body = new URLSearchParams();
		[
			'idInsc',
			'requestId',
			'externalReceiptType',
			'externalReceiptId',
			'amount',
			'movementDate',
			'method',
			'bank',
			'notes'
		].forEach(function (name) {
			body.set(name, form.elements[name].value);
		});
		body.set('csrfToken', token);

		submit.disabled = true;
		errorBox.hidden = true;
		errorBox.textContent = '';

		try {
			const response = await fetch(ENDPOINT, {
				method: 'POST',
				credentials: 'same-origin',
				headers: {
					'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
					'X-Requested-With': 'XMLHttpRequest',
					'Accept': 'application/json'
				},
				body: body.toString()
			});
			let payload = null;
			try {
				payload = await response.json();
			} catch (parseError) {
				throw new Error('Resposta no vàlida del servidor.');
			}

			if (
				payload
				&& payload.payment_persisted === true
				&& payload.requires_reconciliation === true
				&& payload.payment
			) {
				const persistedUuid = payload.payment.uuid_payment
					? ' UUID: ' + payload.payment.uuid_payment
					: '';
				errorBox.textContent =
					errorText(
						payload,
						'El cobrament ja consta al SIF però falta completar la conciliació.'
					)
					+ persistedUuid
					+ ' No registris un altre cobrament ni canviïs la referència externa. '
					+ 'Reintenta aquest mateix rebut quan la incidència estigui resolta.';
				errorBox.hidden = false;
				return;
			}

			if (!response.ok || !payload || payload.ok !== true || !payload.payment) {
				throw new Error(errorText(payload, 'No s’ha pogut registrar el cobrament.'));
			}

			const payment = payload.payment;
			const reused = payment.idempotency_reused === true;
			const message =
				(reused ? 'El cobrament ja constava registrat.' : 'Cobrament registrat correctament.') +
				(payment.uuid_payment ? ' UUID: ' + payment.uuid_payment : '');

			closeDialog();
			window.alert(message);
		} catch (error) {
			errorBox.textContent = error && error.message
				? error.message
				: 'No s’ha pogut registrar el cobrament.';
			errorBox.hidden = false;
		} finally {
			submit.disabled = false;
		}
	}

	function start() {
		decorateRows(document);
		const main = document.querySelector('.mainpanel') || document.body;
		const observer = new MutationObserver(function (mutations) {
			mutations.forEach(function (mutation) {
				mutation.addedNodes.forEach(function (node) {
					if (node.nodeType === 1) decorateRows(node);
				});
			});
			decorateRows(main);
		});
		observer.observe(main, { childList: true, subtree: true });
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', start);
	} else {
		start();
	}
}());
