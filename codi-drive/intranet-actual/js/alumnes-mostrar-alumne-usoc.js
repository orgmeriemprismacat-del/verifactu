(function (window, $) {
    'use strict';

    var endpoint = 'https://intranet.prisma.cat/ajax/alumnes/sifUsoc.php';
    var panelId = 'uc013-usoc-panel';

    document.addEventListener('shown.bs.modal', function (event) {
        if (!event.target || event.target.id !== 'modalConsultaInformacio') {
            return;
        }

        loadCurrentCase();
    });

    document.addEventListener('hidden.bs.modal', function (event) {
        if (event.target && event.target.id === 'modalConsultaInformacio') {
            $('#' + panelId).remove();
        }
    });

    function loadCurrentCase() {
        var identity = currentIdentity();
        if (!identity) {
            return;
        }

        request({
            action: 'view',
            id_insc: identity.idInsc,
            idpag: identity.idpag
        }).done(function (response) {
            if (!response || response.ok !== true || response.resolution === 'FEATURE_DISABLED') {
                removePanel();
                return;
            }

            renderCase(response.case || {});
        }).fail(function (xhr) {
            if (xhr.status === 409 || xhr.status === 404) {
                removePanel();
                return;
            }

            if (xhr.status === 403) {
                renderUnavailable('No tens autorització per consultar l’expedient USOC.');
                return;
            }

            renderUnavailable('No s’ha pogut consultar l’expedient USOC.');
        });
    }

    function currentIdentity() {
        var idInsc = textValue('#modalConsultaInformacio #id-insc');
        var idpag = textValue('#modalConsultaInformacio #idpag-insc');

        if (!/^\d+$/.test(idInsc) || parseInt(idInsc, 10) <= 0) {
            return null;
        }
        if (!/^\d+$/.test(idpag) || parseInt(idpag, 10) <= 0) {
            return null;
        }

        return {
            idInsc: parseInt(idInsc, 10),
            idpag: parseInt(idpag, 10)
        };
    }

    function renderCase(usocCase) {
        var panel = ensurePanel();
        panel.empty();

        panel.append(
            $('<div>').addClass('d-flex justify-content-between align-items-center w-100 mb-2')
                .append($('<p>').addClass('titol-apartat d-flex m-0').text('Finançament USOC · SIF'))
                .append(
                    $('<button>')
                        .attr('type', 'button')
                        .addClass('btn btn-sm btn-outline-secondary uc013-refresh')
                        .text('Actualitza')
                )
        );

        var status = String(usocCase.STATUS || '');
        var badge = $('<span>')
            .addClass('badge mb-3 ' + statusClass(status))
            .text(statusLabel(status));
        panel.append($('<div>').addClass('w-100').append(badge));

        panel.append(summary(usocCase));

        var entityUuid = String(usocCase.UUID_ENTITY_INVOICE || '').trim();

        if (entityUuid === '') {
            panel.append(
                $('<div>')
                    .addClass('alert alert-warning w-100')
                    .text('La factura de l’alumne existeix, però encara falta emetre la factura de la part USOC.')
            );
            panel.append(issueEntityInvoiceForm(usocCase));
        } else {
            panel.append(
                $('<div>')
                    .addClass('alert alert-info w-100')
                    .text('La factura de la part USOC ja existeix. Els cobraments s’han de registrar contra aquesta factura.')
            );
            panel.append(entityPaymentForm(usocCase));
        }

        var actions = $('<div>').addClass('d-flex flex-wrap gap-2 mt-3');
        actions.append(
            $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-outline-primary uc013-reconcile')
                .text('Reconciliar ara')
        );
        panel.append(actions);

        bindPanelEvents(usocCase);
    }

    function summary(usocCase) {
        var table = $('<table>').addClass('table table-sm table-bordered align-middle mb-3');
        var body = $('<tbody>');

        appendRow(body, 'ID inscripció', usocCase.ID_INSC);
        appendRow(body, 'IDPAG', usocCase.IDPAG);
        appendRow(body, 'Factura alumne', usocCase.UUID_STUDENT_INVOICE);
        appendRow(body, 'Import alumne', money(usocCase.STUDENT_AMOUNT));
        appendRow(body, 'Estat cobrament alumne', usocCase.STUDENT_PAYMENT_STATUS);
        appendRow(body, 'Factura USOC', usocCase.UUID_ENTITY_INVOICE || 'Pendent');
        appendRow(body, 'Import USOC', money(usocCase.ENTITY_AMOUNT));
        appendRow(body, 'Estat cobrament USOC', usocCase.ENTITY_PAYMENT_STATUS);

        table.append(body);
        return $('<div>').addClass('table-responsive w-100').append(table);
    }

    function issueEntityInvoiceForm(usocCase) {
        var wrapper = $('<div>').addClass('uc013-issue-form border rounded p-3 w-100');
        wrapper.append($('<h6>').text('Emetre factura a l’entitat USOC'));

        var fields = $('<div>').addClass('row g-2');
        fields.append(input('Raó social', 'uc013-billing-name', 'text', true));
        fields.append(input('NIF/CIF', 'uc013-billing-nif', 'text', true));
        fields.append(input('Adreça', 'uc013-billing-address', 'text', true));
        fields.append(input('Codi postal', 'uc013-billing-postal-code', 'text', true));
        fields.append(input('Població', 'uc013-billing-city', 'text', true));
        fields.append(input('Email factura', 'uc013-billing-email', 'email', false));
        wrapper.append(fields);

        wrapper.append(
            $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-primary mt-3 uc013-issue-entity')
                .text('Emetre factura USOC')
        );

        return wrapper;
    }

    function entityPaymentForm(usocCase) {
        var wrapper = $('<div>').addClass('uc013-payment-form border rounded p-3 w-100');
        wrapper.append($('<h6>').text('Registrar cobrament de l’entitat'));

        var fields = $('<div>').addClass('row g-2');
        fields.append(input('Import', 'uc013-payment-amount', 'number', true, {
            step: '0.01',
            min: '0.01'
        }));
        fields.append(input('Data moviment', 'uc013-payment-date', 'datetime-local', true));
        fields.append(input('Referència bancària', 'uc013-payment-reference', 'text', false));
        fields.append(input('Banc', 'uc013-payment-bank', 'text', false));

        var methodCol = $('<div>').addClass('col-12 col-md-6');
        methodCol.append($('<label>').addClass('form-label').attr('for', 'uc013-payment-method').text('Mètode'));
        methodCol.append(
            $('<select>')
                .attr('id', 'uc013-payment-method')
                .addClass('form-select')
                .append($('<option>').attr('value', 'TRANSFERENCIA').text('Transferència'))
                .append($('<option>').attr('value', 'MANUAL').text('Manual'))
        );
        fields.append(methodCol);

        wrapper.append(fields);
        wrapper.append(
            $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-success mt-3 uc013-register-payment')
                .text('Registrar cobrament i reconciliar')
        );

        return wrapper;
    }

    function bindPanelEvents(usocCase) {
        var panel = $('#' + panelId);

        panel.off('.uc013');

        panel.on('click.uc013', '.uc013-refresh', function () {
            loadCurrentCase();
        });

        panel.on('click.uc013', '.uc013-reconcile', function () {
            var identity = currentIdentity();
            if (!identity) {
                showError('No s’ha pogut identificar la inscripció USOC.');
                return;
            }

            mutate({
                action: 'reconcile',
                id_insc: identity.idInsc,
                idpag: identity.idpag
            }, 'Expedient USOC reconciliat.');
        });

        panel.on('click.uc013', '.uc013-issue-entity', function () {
            var identity = currentIdentity();
            if (!identity) {
                showError('No s’ha pogut identificar la inscripció USOC.');
                return;
            }

            var billing = {
                name: value('#uc013-billing-name'),
                nif: value('#uc013-billing-nif'),
                address: value('#uc013-billing-address'),
                postal_code: value('#uc013-billing-postal-code'),
                city: value('#uc013-billing-city'),
                email: value('#uc013-billing-email')
            };

            if (!billing.name || !billing.nif || !billing.address || !billing.postal_code || !billing.city) {
                showError('Cal completar les dades fiscals obligatòries de l’entitat.');
                return;
            }

            mutate({
                action: 'issue_entity_invoice',
                input: {
                    id_insc: identity.idInsc,
                    idpag: identity.idpag,
                    student_amount: String(usocCase.STUDENT_AMOUNT || ''),
                    amount: String(usocCase.ENTITY_AMOUNT || ''),
                    student_invoice_uuid: String(usocCase.UUID_STUDENT_INVOICE || ''),
                    billing: billing
                }
            }, 'Factura USOC emesa correctament.');
        });

        panel.on('click.uc013', '.uc013-register-payment', function () {
            var amount = value('#uc013-payment-amount');
            var movementDate = value('#uc013-payment-date');

            if (!amount || Number(amount) <= 0 || !movementDate) {
                showError('Cal indicar un import positiu i la data del moviment.');
                return;
            }

            var payment = {
                amount: amount,
                movement_date: movementDate.replace('T', ' '),
                method: value('#uc013-payment-method') || 'TRANSFERENCIA'
            };

            var reference = value('#uc013-payment-reference');
            var bank = value('#uc013-payment-bank');
            if (reference) payment.reference = reference;
            if (bank) payment.bank = bank;

            mutate({
                action: 'register_entity_payment',
                uuid_entity_invoice: String(usocCase.UUID_ENTITY_INVOICE || ''),
                payment: payment
            }, 'Cobrament USOC registrat i expedient reconciliat.');
        });
    }

    function mutate(payload, successMessage) {
        setBusy(true);

        request(payload).done(function (response) {
            if (!response || response.ok !== true) {
                showError((response && response.error) || 'No s’ha pogut completar l’operació USOC.');
                return;
            }

            showSuccess(successMessage);
            loadCurrentCase();
        }).fail(function (xhr) {
            var message = 'No s’ha pogut completar l’operació USOC.';
            if (xhr.responseJSON && xhr.responseJSON.error) {
                message = xhr.responseJSON.error;
            } else if (xhr.status === 403) {
                message = 'No tens permisos per modificar aquest expedient USOC.';
            }
            showError(message);
        }).always(function () {
            setBusy(false);
        });
    }

    function request(payload) {
        return $.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: JSON.stringify(payload)
        });
    }

    function ensurePanel() {
        var panel = $('#' + panelId);
        if (panel.length) {
            return panel;
        }

        panel = $('<div>')
            .attr('id', panelId)
            .addClass('d-flex flex-column justify-content-center align-items-center my-3 p-3 border-top w-100');

        var paymentSection = $('#modalConsultaInformacio #dades-pagament');
        if (paymentSection.length) {
            paymentSection.after(panel);
        }

        return panel;
    }

    function removePanel() {
        $('#' + panelId).remove();
    }

    function renderUnavailable(message) {
        var panel = ensurePanel();
        panel.empty().append(
            $('<div>').addClass('alert alert-danger w-100 mb-0').text(message)
        );
    }

    function input(label, id, type, required, attrs) {
        var col = $('<div>').addClass('col-12 col-md-6');
        var control = $('<input>')
            .attr('id', id)
            .attr('type', type)
            .addClass('form-control');

        if (required) control.attr('required', 'required');
        Object.keys(attrs || {}).forEach(function (key) {
            control.attr(key, attrs[key]);
        });

        col.append($('<label>').addClass('form-label').attr('for', id).text(label));
        col.append(control);
        return col;
    }

    function appendRow(body, label, rawValue) {
        var row = $('<tr>');
        row.append($('<th>').attr('scope', 'row').text(label));
        row.append($('<td>').addClass('text-break').text(display(rawValue)));
        body.append(row);
    }

    function statusClass(status) {
        if (status === 'FINANCING_RECONCILED') return 'bg-success';
        if (status === 'ENTITY_PARTIAL') return 'bg-warning text-dark';
        if (status === 'REVIEW_REQUIRED') return 'bg-danger';
        return 'bg-info text-dark';
    }

    function statusLabel(status) {
        var labels = {
            PENDING_ENTITY_INVOICE: 'Pendent de factura USOC',
            ENTITY_INVOICED: 'Factura USOC emesa · cobrament pendent',
            ENTITY_PARTIAL: 'Cobrament USOC parcial',
            FINANCING_RECONCILED: 'Finançament conciliat',
            REVIEW_REQUIRED: 'Revisió manual requerida'
        };
        return labels[status] || status || 'Estat desconegut';
    }

    function textValue(selector) {
        var element = $(selector).first();
        if (!element.length) return '';
        return String(element.is('input,select,textarea') ? element.val() : element.text()).trim();
    }

    function value(selector) {
        return String($(selector).val() || '').trim();
    }

    function display(rawValue) {
        if (rawValue === undefined || rawValue === null || rawValue === '') return '—';
        return String(rawValue);
    }

    function money(rawValue) {
        var number = Number(rawValue);
        return Number.isFinite(number) ? number.toFixed(2) + ' €' : display(rawValue);
    }

    function setBusy(busy) {
        $('#' + panelId + ' button, #' + panelId + ' input, #' + panelId + ' select').prop('disabled', busy);
        if (busy && typeof mostrarModalLoading === 'function') mostrarModalLoading();
        if (!busy && typeof amagarLoadingModal === 'function') amagarLoadingModal();
    }

    function showSuccess(message) {
        if (typeof afegirHeaderModalSuccess === 'function'
            && typeof afegirTextModalSuccess === 'function'
            && typeof mostrarModalSuccess === 'function') {
            afegirHeaderModalSuccess('Operació completada');
            afegirTextModalSuccess(message);
            mostrarModalSuccess();
        }
    }

    function showError(message) {
        setBusy(false);

        if (typeof afegirHeaderModalError === 'function'
            && typeof afegirTextModalError === 'function'
            && typeof mostrarModalError === 'function') {
            afegirHeaderModalError('Alerta');
            afegirTextModalError(message);
            mostrarModalError();
            return;
        }

        window.alert(message);
    }
})(window, jQuery);
