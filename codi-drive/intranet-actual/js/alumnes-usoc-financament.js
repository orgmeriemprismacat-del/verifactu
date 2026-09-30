(function () {
    'use strict';

    const endpoint = 'https://intranet.prisma.cat/ajax/alumnes/usocFinancament.php';

    function csrfToken() {
        const meta = document.querySelector('meta[name="csrf-token-usoc-financament"]');
        return meta ? meta.getAttribute('content') : '';
    }

    function field(id) {
        return ($('#' + id).val() || '').toString().trim();
    }

    function money(value) {
        const parsed = Number(value);
        return Number.isFinite(parsed) ? parsed.toFixed(2) : '—';
    }

    function showAlert(type, message) {
        $('#usoc-alert')
            .removeClass('d-none alert-success alert-danger alert-warning alert-info')
            .addClass('alert-' + type)
            .text(message);
    }

    function hideAlert() {
        $('#usoc-alert').addClass('d-none').text('');
    }

    function statusBadge(status) {
        const value = (status || 'Sense carregar').toString();
        const badge = $('#usoc-status-badge');
        badge.removeClass('badge-secondary badge-warning badge-info badge-success badge-danger');
        if (value === 'FINANCING_RECONCILED') badge.addClass('badge-success');
        else if (value === 'REVIEW_REQUIRED') badge.addClass('badge-danger');
        else if (value === 'ENTITY_PARTIAL') badge.addClass('badge-warning');
        else if (value === 'ENTITY_INVOICED' || value === 'PENDING_ENTITY_INVOICE') badge.addClass('badge-info');
        else badge.addClass('badge-secondary');
        badge.text(value);
    }

    function renderCase(data) {
        if (!data) return;
        $('#usoc-student-invoice').text(data.UUID_STUDENT_INVOICE || '—');
        $('#usoc-student-amount').text(data.STUDENT_AMOUNT ? money(data.STUDENT_AMOUNT) + ' €' : '—');
        $('#usoc-student-status').text(data.STUDENT_PAYMENT_STATUS || '—');
        $('#usoc-entity-invoice').text(data.UUID_ENTITY_INVOICE || '—');
        $('#usoc-entity-amount').text(data.ENTITY_AMOUNT ? money(data.ENTITY_AMOUNT) + ' €' : '—');
        $('#usoc-entity-status').text(data.ENTITY_PAYMENT_STATUS || '—');
        $('#usoc-case-status').text(data.STATUS || '—');
        $('#usoc-correlation').text(data.CORRELATION_ID || '—');
        statusBadge(data.STATUS || '');

        if (data.UUID_STUDENT_INVOICE) $('#usoc-student-uuid').val(data.UUID_STUDENT_INVOICE);
        if (data.STUDENT_AMOUNT) $('#usoc-student-input-amount').val(money(data.STUDENT_AMOUNT));
        if (data.ENTITY_AMOUNT) $('#usoc-entity-input-amount').val(money(data.ENTITY_AMOUNT));
        if (data.UUID_ENTITY_INVOICE) $('#usoc-payment-uuid').val(data.UUID_ENTITY_INVOICE);
        if (data.ID_INSC) $('#usoc-id-insc').val(data.ID_INSC);
        if (data.IDPAG) $('#usoc-idpag').val(data.IDPAG);
    }

    function post(action, payload) {
        hideAlert();
        const data = Object.assign({}, payload || {}, {
            action: action,
            csrfToken: csrfToken()
        });

        return $.ajax({
            url: endpoint,
            method: 'POST',
            data: data,
            dataType: 'json'
        });
    }

    function identity() {
        return {
            id_insc: field('usoc-id-insc'),
            idpag: field('usoc-idpag')
        };
    }

    $('#usoc-consultar').on('click', function () {
        post('view', identity())
            .done(function (response) {
                if (response.ok && response.case) {
                    renderCase(response.case);
                    showAlert('success', 'Expedient USOC carregat.');
                } else {
                    showAlert('danger', response.error || 'No s’ha pogut carregar l’expedient.');
                }
            })
            .fail(function (xhr) {
                showAlert('danger', xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error consultant l’expedient USOC.');
            });
    });

    $('#usoc-reconciliar').on('click', function () {
        post('reconcile', identity())
            .done(function (response) {
                if (response.ok && response.case) {
                    renderCase(response.case);
                    showAlert('success', 'Expedient reconciliat.');
                } else {
                    showAlert('danger', response.error || 'No s’ha pogut reconciliar.');
                }
            })
            .fail(function (xhr) {
                showAlert('danger', xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error reconciliant l’expedient.');
            });
    });

    $('#usoc-emetre-entitat').on('click', function () {
        const id = identity();
        const payload = {
            id_insc: id.id_insc,
            idpag: id.idpag,
            student_invoice_uuid: field('usoc-student-uuid'),
            student_amount: field('usoc-student-input-amount'),
            amount: field('usoc-entity-input-amount'),
            billing_name: field('usoc-billing-name'),
            billing_nif: field('usoc-billing-nif'),
            billing_email: field('usoc-billing-email'),
            billing_address: field('usoc-billing-address'),
            billing_cp: field('usoc-billing-cp'),
            billing_city: field('usoc-billing-city'),
            billing_province: field('usoc-billing-province'),
            billing_country: field('usoc-billing-country') || 'ES'
        };

        post('issue_entity_invoice', payload)
            .done(function (response) {
                if (response.ok && response.invoice) {
                    if (response.invoice.usoc_case) renderCase(response.invoice.usoc_case);
                    if (response.invoice.uuid_factura) $('#usoc-payment-uuid').val(response.invoice.uuid_factura);
                    showAlert('success', 'Factura USOC emesa correctament.');
                } else {
                    showAlert('danger', response.error || 'No s’ha pogut emetre la factura USOC.');
                }
            })
            .fail(function (xhr) {
                showAlert('danger', xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error emetent la factura USOC.');
            });
    });

    $('#usoc-registrar-cobrament').on('click', function () {
        post('register_entity_payment', {
            uuid_entity_invoice: field('usoc-payment-uuid'),
            amount: field('usoc-payment-amount'),
            movement_date: field('usoc-payment-date'),
            reference: field('usoc-payment-reference'),
            method: field('usoc-payment-method'),
            bank: field('usoc-payment-bank'),
            notes: field('usoc-payment-notes')
        })
            .done(function (response) {
                if (response.ok && response.payment) {
                    if (response.payment.usoc_case) renderCase(response.payment.usoc_case);
                    showAlert(
                        response.payment.reconciliation_pending ? 'warning' : 'success',
                        response.payment.reconciliation_pending
                            ? 'Cobrament registrat, però la conciliació queda pendent.'
                            : 'Cobrament registrat i expedient reconciliat.'
                    );
                } else {
                    showAlert('danger', response.error || 'No s’ha pogut registrar el cobrament.');
                }
            })
            .fail(function (xhr) {
                showAlert('danger', xhr.responseJSON && xhr.responseJSON.error ? xhr.responseJSON.error : 'Error registrant el cobrament USOC.');
            });
    });
})();