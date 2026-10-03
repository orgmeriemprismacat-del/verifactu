(function (global) {
    'use strict';

    const endpoint = '/ajax/facturacio/sifDebtClaim.php';

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token-debt-claim"]');
        const value = meta ? String(meta.getAttribute('content') || '').trim() : '';
        if (value === '') {
            throw new Error('Falta el token CSRF de morositat');
        }
        return value;
    }

    function newOperationId() {
        if (global.crypto && typeof global.crypto.randomUUID === 'function') {
            return global.crypto.randomUUID();
        }
        const bytes = new Uint8Array(16);
        global.crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, function (b) {
            return b.toString(16).padStart(2, '0');
        }).join('');
        return [
            hex.slice(0, 8),
            hex.slice(8, 12),
            hex.slice(12, 16),
            hex.slice(16, 20),
            hex.slice(20)
        ].join('-');
    }

    async function request(payload) {
        const params = new URLSearchParams();
        Object.keys(payload).forEach(function (key) {
            const value = payload[key];
            if (value !== undefined && value !== null && String(value) !== '') {
                params.set(key, String(value));
            }
        });
        params.set('csrfToken', csrfToken());

        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            redirect: 'error',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            },
            body: params.toString()
        });

        let data;
        try {
            data = await response.json();
        } catch (error) {
            throw new Error('Resposta no vàlida del pont SIF de morositat');
        }

        data._http_status = response.status;
        if (!response.ok || data.ok === false) {
            const error = new Error(data.error || 'Operació SIF de morositat fallida');
            error.status = response.status;
            error.response = data;
            throw error;
        }

        return data;
    }

    function selector(invoice) {
        const uuid = String(invoice && invoice.uuid_factura || '').trim();
        const numVisible = String(invoice && invoice.num_visible || '').trim();
        if ((uuid === '') === (numVisible === '')) {
            throw new Error('Cal indicar exactament una factura SIF');
        }
        return uuid !== '' ? {uuid_factura: uuid} : {num_visible: numVisible};
    }

    global.SifDebtClaimBridge = Object.freeze({
        newOperationId: newOperationId,

        preview: function (invoice) {
            return request(Object.assign({action: 'preview'}, selector(invoice)));
        },

        recordNotice: function (invoice, stage, options) {
            options = options || {};
            const operationId = String(options.operation_id || '').trim() || newOperationId();
            return request(Object.assign({
                action: 'record_notice',
                stage: String(stage || '').trim(),
                operation_id: operationId,
                reason_code: String(options.reason_code || 'DEBT_DUE').trim(),
                notes: String(options.notes || '').trim()
            }, selector(invoice)));
        },

        reconcileAfterPayment: function (invoice, options) {
            options = options || {};
            const operationId = String(options.operation_id || '').trim() || newOperationId();
            return request(Object.assign({
                action: 'reconcile_after_payment',
                operation_id: operationId,
                reason_code: String(options.reason_code || 'PAYMENT_RECONCILIATION').trim(),
                uuid_payment: String(options.uuid_payment || '').trim()
            }, selector(invoice)));
        }
    });
})(window);
