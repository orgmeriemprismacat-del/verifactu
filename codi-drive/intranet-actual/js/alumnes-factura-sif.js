(function (window, $) {
    'use strict';

    var endpoint = 'https://intranet.prisma.cat/ajax/alumnes/sifFactures.php';

    window.uc007SifSearch = function (input) {
        var criteria = {};

        if (input.dni) criteria.participant_document = input.dni;
        if (input.email) criteria.billing_email = input.email;
        if (input.factRel) criteria.factura_relacionada = input.factRel;
        if (input.factNum) criteria.num_visible = input.factNum;

        $.ajax({
            url: endpoint,
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            data: JSON.stringify({
                action: 'search',
                criteria: criteria,
                limit: 50
            })
        }).done(function (response) {
            if (!response || response.ok !== true) {
                showSifError('No s\'ha pogut consultar el SIF.');
                return;
            }

            if ((response.count || 0) === 0) {
                legacySearch(input);
                return;
            }

            renderSifResults(response.results || [], input.cercaPer || '');
            amagarLoadingModal();
        }).fail(function (jqXHR) {
            var message = 'No s\'ha pogut consultar el SIF.';
            if (jqXHR.status === 401 || jqXHR.status === 403) {
                message = 'No tens autorització per consultar aquestes factures al SIF.';
            } else if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                message = jqXHR.responseJSON.error;
            }

            showSifError(message);
        });

        return true;
    };

    function legacySearch(input) {
        $.ajax({
            url: 'https://intranet.prisma.cat/ajax/alumnes/consultaUsuarisFacturaRelacionada.php',
            method: 'GET',
            data: {
                dni: input.dni || '',
                email: input.email || '',
                factRel: input.factRel || '',
                factNum: input.factNum || ''
            },
            dataType: 'html'
        }).done(function (dnies) {
            var lower = String(dnies).toLowerCase();

            if (lower.includes('error')) {
                showSifError('Hi ha hagut un error a l\'hora de fer la consulta llegada.');
                return;
            }

            if (String(dnies).includes('No') && String(dnies).includes('resultats')) {
                showSifError('No s\'han trobat resultats.');
                return;
            }

            var parts = String(dnies).split('#');
            var candidates = (parts[1] || '')
                .split('|')
                .map(function (value) { return value.trim(); })
                .filter(function (value) { return value !== ''; });

            if (candidates.length === 0) {
                showSifError('No s\'han trobat resultats.');
                return;
            }

            if (candidates.length > 2000) {
                showSifError('El volum de dades és massa gran. Afegeix algun filtre més.');
                return;
            }

            if (candidates.length === 1) {
                cercarUSuari(candidates[0]);
                return;
            }

            mostraLlistatUsuaris(candidates.join('|'), 'cog', 'asc');
        }).fail(function (jqXHR, textStatus, errorThrown) {
            amagarLoadingModal();
            if (typeof errorFunction === 'function') {
                errorFunction(
                    jqXHR,
                    textStatus,
                    errorThrown,
                    'Hi ha hagut un error a la consulta llegada: '
                );
                return;
            }

            showSifError('Hi ha hagut un error a la consulta llegada.');
        });
    }

    function renderSifResults(results, title) {
        var container = $('<div>').addClass('uc007-sif-results');
        var heading = $('<h5>').addClass('mb-3').text(title || 'FACTURES SIF');
        var badge = $('<span>')
            .addClass('badge badge-success ml-2')
            .text('SIF');
        heading.append(badge);
        container.append(heading);

        var responsive = $('<div>').addClass('table-responsive');
        var table = $('<table>').addClass('table table-sm table-hover');
        var thead = $('<thead>').append(
            $('<tr>')
                .append($('<th>').text('Factura'))
                .append($('<th>').text('Tipus'))
                .append($('<th>').text('Data'))
                .append($('<th>').text('Receptor'))
                .append($('<th>').text('Total'))
                .append($('<th>').text('Factura'))
                .append($('<th>').text('Cobrament'))
                .append($('<th>').text('AEAT'))
                .append($('<th>').text('Acció'))
        );
        var tbody = $('<tbody>');

        results.forEach(function (invoice) {
            var billing = invoice.billing || {};
            var totals = invoice.totals || {};
            var button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-sm btn-outline-primary sif-cns-informacio')
                .attr('data-uuid', invoice.uuid_factura || '')
                .text('Informació');

            tbody.append(
                $('<tr>')
                    .append($('<td>').text(invoice.num_visible || ''))
                    .append($('<td>').text(invoice.tipus_factura || invoice.tipus_serie || ''))
                    .append($('<td>').text(invoice.data_emissio || ''))
                    .append($('<td>').text(billing.name || '—'))
                    .append($('<td>').text(totals.total || '—'))
                    .append($('<td>').text(invoice.estat_factura || '—'))
                    .append($('<td>').text(invoice.estat_cobrament || '—'))
                    .append($('<td>').text(invoice.estat_aeat || '—'))
                    .append($('<td>').append(button))
            );
        });

        table.append(thead).append(tbody);
        responsive.append(table);
        container.append(responsive);

        $('#resultats-cerca').off('.uc007sif');
        $('#resultats-cerca').empty().append(container).show();
        $('#resultats-cerca').on('click.uc007sif', '.sif-cns-informacio', function () {
            var uuid = String($(this).attr('data-uuid') || '');
            if (uuid !== '') {
                viewSifInvoice(uuid);
            }
        });
    }

    function viewSifInvoice(uuid) {
        mostrarModalLoading();

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
                showSifError('No s\'ha pogut obrir la factura SIF.');
                return;
            }

            renderSifInvoiceModal(response);
            amagarLoadingModal();
            $('#modalConsultaInformacio').modal('show');
        }).fail(function (jqXHR) {
            var message = 'No s\'ha pogut obrir la factura SIF.';
            if (jqXHR.status === 401 || jqXHR.status === 403) {
                message = 'No tens autorització per veure aquesta factura SIF.';
            } else if (jqXHR.responseJSON && jqXHR.responseJSON.error) {
                message = jqXHR.responseJSON.error;
            }

            showSifError(message);
        });
    }

    function renderSifInvoiceModal(view) {
        var invoice = view.invoice || {};
        var billing = invoice.billing || {};
        var totals = invoice.totals || {};
        var body = $('<div>').addClass('uc007-sif-view');

        body.append(
            $('<div>').addClass('alert alert-info').text(
                'Consulta SIF en mode només lectura. Les correccions, anul·lacions i documents es gestionen en fluxos separats.'
            )
        );

        body.append(section('Factura', [
            ['UUID', invoice.uuid_factura],
            ['Número visible', invoice.num_visible],
            ['Tipus', invoice.tipus_factura],
            ['Data emissió', invoice.data_emissio],
            ['Estat factura', invoice.estat_factura],
            ['Estat cobrament', invoice.estat_cobrament],
            ['Estat AEAT', invoice.estat_aeat],
            ['E_FACT', invoice.e_fact]
        ]));

        if (billing.name || billing.nif) {
            body.append(section('Receptor fiscal', [
                ['Nom / raó', billing.name],
                ['NIF / CIF', billing.nif],
                ['Adreça', billing.address],
                ['CP', billing.cp],
                ['Població', billing.city],
                ['Email', billing.email]
            ]));
        }

        if (totals.total !== undefined) {
            body.append(section('Imports', [
                ['Base', totals.import_base],
                ['Descompte', totals.discount],
                ['Base imposable', totals.taxable_base],
                ['Règim IVA', totals.iva_regim],
                ['IVA %', totals.iva_pct],
                ['IVA', totals.iva_import],
                ['Total', totals.total]
            ]));
        }

        appendRowsTable(body, 'Línies', view.lines || [], [
            ['CONCEPTE', 'Concepte'],
            ['DETALL', 'Detall'],
            ['QUANTITAT', 'Quantitat'],
            ['TOTAL', 'Total']
        ]);

        appendRowsTable(body, 'Moviments atribuïts', view.payments || [], [
            ['TIPUS_MOVIMENT', 'Moviment'],
            ['METODE', 'Mètode'],
            ['IMPORT_ASSIGNAT', 'Assignat'],
            ['ESTAT', 'Estat']
        ]);

        appendRowsTable(body, 'Rectificacions', view.rectifications || [], [
            ['RECTIFICATIVA_NUM_VISIBLE', 'Rectificativa'],
            ['RECTIFICADA_NUM_VISIBLE', 'Original'],
            ['MOTIU', 'Motiu'],
            ['MODE_RECTIFICACIO', 'Mode']
        ]);

        appendRowsTable(body, 'Documents registrats', view.documents || [], [
            ['TIPUS', 'Tipus'],
            ['ESTAT', 'Estat'],
            ['HASH_FITXER', 'Hash']
        ]);

        $('#modalConsultaInformacio .modal-body').empty().append(body);
        $('#modalConsultaInformacio .editar-apartat').remove();
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

    function appendRowsTable(parent, title, rows, columns) {
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

        var body = $('<tbody>');
        rows.forEach(function (row) {
            var tr = $('<tr>');
            columns.forEach(function (column) {
                var value = row[column[0]];
                tr.append($('<td>').text(value === null || value === undefined ? '' : String(value)));
            });
            body.append(tr);
        });

        table.append(body);
        wrapper.append($('<div>').addClass('table-responsive').append(table));
        parent.append(wrapper);
    }

    function showSifError(message) {
        amagarLoadingModal();
        afegirHeaderModalError('Alerta');
        afegirTextModalError(message);
        mostrarModalError();
    }
})(window, jQuery);
