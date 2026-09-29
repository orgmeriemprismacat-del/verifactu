(function (window, $) {
    'use strict';

    var endpoint = 'https://intranet.prisma.cat/ajax/alumnes/sifFactures.php';

    document.addEventListener('click', function (event) {
        var target = event.target && event.target.closest
            ? event.target.closest('.cns-factura')
            : null;

        if (!target) {
            return;
        }

        event.preventDefault();
        event.stopPropagation();
        event.stopImmediatePropagation();

        var parts = String(target.id || '').split('-');
        var idInsc = parts.length > 1 ? parts[1] : '';

        if (!/^\d+$/.test(idInsc)) {
            showError('No s\'ha pogut identificar la inscripció.');
            return;
        }

        if (typeof mostrarModalLoading === 'function') {
            mostrarModalLoading();
        }

        $.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            data: JSON.stringify({
                action: 'view_by_enrollment',
                id_insc: idInsc
            })
        }).done(function (response) {
            if (!response || response.ok !== true) {
                showError('No s\'ha pogut consultar la factura SIF.');
                return;
            }

            if (response.resolution === 'NO_SIF') {
                if (typeof mostrarModalConsultaFactura === 'function') {
                    mostrarModalConsultaFactura(idInsc);
                    return;
                }

                showError('No hi ha factura SIF i el flux llegat no està disponible.');
                return;
            }

            if (response.resolution === 'MULTIPLE') {
                renderSelection(response.results || []);
                showInvoiceModal();
                hideLoading();
                return;
            }

            renderInvoice(response);
            showInvoiceModal();
            hideLoading();
        }).fail(function (jqXHR) {
            if (jqXHR.status === 401 || jqXHR.status === 403) {
                showError('No tens autorització per consultar aquesta factura.');
                return;
            }

            showError('No s\'ha pogut consultar la factura SIF.');
        });
    }, true);

    function renderSelection(results) {
        var body = $('<div>').addClass('uc007-sif-selection');
        body.append(
            $('<div>')
                .addClass('alert alert-info')
                .text('Aquesta inscripció està relacionada amb més d’una factura SIF. Selecciona el document fiscal concret.')
        );

        var list = $('<div>').addClass('list-group');

        results.forEach(function (invoice) {
            var label = [
                invoice.num_visible || invoice.uuid_factura || 'Factura',
                invoice.tipus_factura || '',
                invoice.estat_factura || ''
            ].filter(Boolean).join(' · ');

            var button = $('<button>')
                .attr('type', 'button')
                .addClass('list-group-item list-group-item-action')
                .attr('data-uuid', invoice.uuid_factura || '')
                .text(label);

            list.append(button);
        });

        body.append(list);
        $('#modalConsultaFactura .modal-body').empty().append(body);

        $('#modalConsultaFactura .modal-body')
            .off('click.uc007sif')
            .on('click.uc007sif', '[data-uuid]', function () {
                var uuid = String($(this).attr('data-uuid') || '');
                if (uuid !== '') {
                    loadInvoice(uuid);
                }
            });

        hideLegacyDownload();
    }

    function loadInvoice(uuid) {
        if (typeof mostrarModalLoading === 'function') {
            mostrarModalLoading();
        }

        $.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            data: JSON.stringify({
                action: 'view',
                uuid_factura: uuid
            })
        }).done(function (response) {
            if (!response || response.ok !== true) {
                showError('No s\'ha pogut obrir la factura SIF.');
                return;
            }

            renderInvoice(response);
            showInvoiceModal();
            hideLoading();
        }).fail(function () {
            showError('No s\'ha pogut obrir la factura SIF.');
        });
    }

    function renderInvoice(view) {
        var invoice = view.invoice || {};
        var billing = invoice.billing || {};
        var totals = invoice.totals || {};
        var body = $('<div>').addClass('uc007-sif-view');

        body.append(
            $('<div>')
                .addClass('alert alert-info')
                .text('Factura SIF · consulta només lectura. Original i rectificatives es mantenen com a documents independents.')
        );

        body.append(section('Factura', [
            ['UUID', invoice.uuid_factura],
            ['Número visible', invoice.num_visible],
            ['Tipus', invoice.tipus_factura],
            ['Data emissió', invoice.data_emissio],
            ['Estat factura', invoice.estat_factura],
            ['Estat cobrament', invoice.estat_cobrament],
            ['Estat AEAT', invoice.estat_aeat]
        ]));

        if (billing.name || billing.nif) {
            body.append(section('Receptor fiscal', [
                ['Nom / raó', billing.name],
                ['NIF / CIF', billing.nif],
                ['Email', billing.email]
            ]));
        }

        if (totals.total !== undefined) {
            body.append(section('Imports', [
                ['Base', totals.import_base],
                ['Descompte', totals.discount],
                ['IVA', totals.iva_import],
                ['Total', totals.total]
            ]));
        }

        appendTable(body, 'Rectificacions', view.rectifications || [], [
            ['RECTIFICATIVA_NUM_VISIBLE', 'Rectificativa'],
            ['RECTIFICADA_NUM_VISIBLE', 'Original'],
            ['MOTIU', 'Motiu']
        ]);

        appendTable(body, 'Documents registrats', view.documents || [], [
            ['TIPUS', 'Tipus'],
            ['ESTAT', 'Estat'],
            ['HASH_FITXER', 'Hash']
        ]);

        $('#modalConsultaFactura .modal-body').empty().append(body);
        hideLegacyDownload();
    }

    function section(title, rows) {
        var wrapper = $('<div>').addClass('mb-4');
        wrapper.append($('<h6>').text(title));
        var dl = $('<dl>').addClass('row mb-0');

        rows.forEach(function (row) {
            var value = row[1];
            if (value === undefined || value === null || value === '') {
                return;
            }

            dl.append($('<dt>').addClass('col-sm-4').text(row[0]));
            dl.append($('<dd>').addClass('col-sm-8').text(String(value)));
        });

        wrapper.append(dl);
        return wrapper;
    }

    function appendTable(parent, title, rows, columns) {
        if (!rows || rows.length === 0) {
            return;
        }

        var wrapper = $('<div>').addClass('mb-4');
        wrapper.append($('<h6>').text(title));

        var table = $('<table>').addClass('table table-sm');
        var head = $('<tr>');
        columns.forEach(function (column) {
            head.append($('<th>').text(column[1]));
        });
        table.append($('<thead>').append(head));

        var tbody = $('<tbody>');
        rows.forEach(function (row) {
            var tr = $('<tr>');
            columns.forEach(function (column) {
                var value = row[column[0]];
                tr.append($('<td>').text(value === undefined || value === null ? '' : String(value)));
            });
            tbody.append(tr);
        });

        table.append(tbody);
        wrapper.append($('<div>').addClass('table-responsive').append(table));
        parent.append(wrapper);
    }

    function hideLegacyDownload() {
        $('#modalConsultaFactura .download-factura').hide();
        $('#modalConsultaFactura .fletxa-left').hide();
        $('#modalConsultaFactura .fletxa-right').hide();
    }

    function showInvoiceModal() {
        var element = document.getElementById('modalConsultaFactura');
        if (!element || typeof bootstrap === 'undefined') {
            return;
        }

        bootstrap.Modal.getOrCreateInstance(element).show();
    }

    function hideLoading() {
        if (typeof amagarLoadingModal === 'function') {
            amagarLoadingModal();
        }
    }

    function showError(message) {
        hideLoading();

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
