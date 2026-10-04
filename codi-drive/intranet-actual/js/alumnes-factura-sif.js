(function (window, $) {
    'use strict';

    var endpoint = 'https://intranet.prisma.cat/ajax/alumnes/sifFactures.php';
    var documentEndpoint = 'https://intranet.prisma.cat/ajax/alumnes/sifDocument.php';
    var rectificationEndpoint = 'https://intranet.prisma.cat/ajax/alumnes/sifRectificarFactura.php';
    var rectificationConfig = window.sifUc005RectificationConfig || { enabled: false, csrf: '' };

    window.uc005SifRectificationPreview = function (request) {
        return rectificationRequest('preview', request || {});
    };

    window.uc005SifRectificationConfirm = function (request) {
        return rectificationRequest('confirm', request || {});
    };

    function rectificationRequest(action, request) {
        if (rectificationConfig.enabled !== true || !/^[a-f0-9]{64}$/i.test(rectificationConfig.csrf || '')) {
            return $.Deferred().reject({
                status: 403,
                responseJSON: { error: 'El flux UC-005 no està habilitat en aquesta pantalla.' }
            }).promise();
        }

        var payload = {
            action: action,
            uuid_factura: String(request.uuid_factura || ''),
            correction: request.correction || {},
            classification_event_uuid: String(request.classification_event_uuid || '')
        };

        if (action === 'confirm') {
            payload.expected_fingerprint = String(request.expected_fingerprint || '');
        }

        return $.ajax({
            url: rectificationEndpoint,
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            headers: {
                'X-CSRF-Token': rectificationConfig.csrf,
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: JSON.stringify(payload)
        });
    }

    window.uc007SifSearch = function (input) {
        var criteria = {};

        if (input.dni) criteria.participant_document = input.dni;
        if (input.email) criteria.participant_email = input.email;
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
                'La factura SIF és immutable. No s’edita directament; una correcció fiscal només es pot executar amb una decisió UC-74 aprovada i el flux UC-005 preview/confirm.'
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

        appendDocumentsTable(body, view.documents || []);
        appendRectificationPanel(body, view);

        $('#modalConsultaInformacio .modal-body').empty().append(body);
        $('#modalConsultaInformacio .editar-apartat').remove();
    }

    function appendRectificationPanel(parent, view) {
        if (rectificationConfig.enabled !== true) {
            return;
        }

        var invoice = view.invoice || {};
        var decision = view.fiscal_correction_decision || null;
        var wrapper = $('<div>').addClass('mb-4 uc005-rectification-panel');
        wrapper.append($('<h6>').text('Rectificació fiscal · UC-005'));

        if (!decision) {
            wrapper.append(
                $('<div>').addClass('alert alert-warning mb-0').text(
                    'Pendent de classificació fiscal UC-74. No es pot previsualitzar ni emetre cap rectificativa.'
                )
            );
            parent.append(wrapper);
            return;
        }

        var classification = decision.classification || {};
        wrapper.append(section('Decisió fiscal aprovada', [
            ['Via', classification.decision],
            ['Tipus fiscal', classification.invoice_type],
            ['Mode', classification.rectification_mode],
            ['Motiu fiscal', classification.reason_code || decision.reason_code],
            ['Política', classification.policy_version],
            ['Event UC-74', decision.event_uuid]
        ]));

        if (decision.eligible_for_uc005 !== true) {
            wrapper.append(
                $('<div>').addClass('alert alert-secondary mb-0').text(
                    'La decisió fiscal vigent no deriva a UC-005. No s’emet cap factura rectificativa des d’aquesta pantalla.'
                )
            );
            parent.append(wrapper);
            return;
        }

        if (decision.executed === true) {
            var execution = decision.execution || {};
            var executedMessage = 'Aquesta decisió UC-74 ja s’ha executat.';
            if (execution.uuid_factura_rectificativa) {
                executedMessage += ' Rectificativa: ' + String(execution.uuid_factura_rectificativa) + '.';
            }
            wrapper.append(
                $('<div>').addClass('alert alert-success mb-0').text(executedMessage)
            );
            parent.append(wrapper);
            return;
        }

        if (decision.ready_for_uc005_ui !== true || !decision.correction) {
            wrapper.append(
                $('<div>').addClass('alert alert-warning mb-0').text(
                    'La decisió UC-74 no conté el snapshot executable de la correcció. Cal reclassificar el cas abans de continuar.'
                )
            );
            parent.append(wrapper);
            return;
        }

        var correction = decision.correction;
        var fiscal = correction.fiscal || {};
        var correctedBilling = correction.billing || {};

        wrapper.append(section('Correcció aprovada', [
            ['Motiu operatiu', correction.reason],
            ['Mode', correction.mode],
            ['Import', correction.amount],
            ['Concepte', correction.concept],
            ['Detall', correction.detail],
            ['Referència', correction.reference]
        ]));

        if (Object.keys(fiscal).length > 0) {
            wrapper.append(section('Snapshot fiscal aprovat', [
                ['Base', fiscal.import_base],
                ['Base imposable', fiscal.taxable_base],
                ['Règim IVA', fiscal.iva_regim],
                ['IVA %', fiscal.iva_pct],
                ['IVA', fiscal.iva_import],
                ['Total', fiscal.total],
                ['Causa exempció', fiscal.exemption_reason]
            ]));
        }

        if (Object.keys(correctedBilling).length > 0) {
            wrapper.append(section('Receptor fiscal corregit', [
                ['Nom / raó', correctedBilling.name],
                ['NIF / CIF', correctedBilling.nif],
                ['Adreça', correctedBilling.address],
                ['CP', correctedBilling.cp],
                ['Població', correctedBilling.city],
                ['Província', correctedBilling.province],
                ['País', correctedBilling.country],
                ['Email', correctedBilling.email]
            ]));
        }

        var actions = $('<div>').addClass('d-flex align-items-center flex-wrap');
        var previewButton = $('<button>')
            .attr('type', 'button')
            .addClass('btn btn-warning btn-sm mr-2 uc005-preview-rectification')
            .text('Previsualitzar rectificativa');
        var resultBox = $('<div>').addClass('mt-3 uc005-rectification-result');

        previewButton.on('click', function () {
            previewButton.prop('disabled', true);

            window.uc005SifRectificationPreview({
                uuid_factura: invoice.uuid_factura,
                correction: correction,
                classification_event_uuid: decision.event_uuid
            }).done(function (response) {
                if (!response || response.ok !== true) {
                    resultBox.empty().append(
                        $('<div>').addClass('alert alert-danger').text(
                            (response && response.error) || 'No s’ha pogut previsualitzar la rectificativa.'
                        )
                    );
                    return;
                }

                var preview = $('<div>').addClass('border rounded p-3');
                preview.append($('<strong>').text('Preview validat pel SIF'));
                preview.append(section('Resultat previst', [
                    ['Factura original', response.num_visible_rectificada],
                    ['Tipus', (response.classification || {}).invoice_type],
                    ['Mode', (response.classification || {}).rectification_mode],
                    ['Total rectificativa', (response.totals || {}).total],
                    ['Fingerprint', response.fingerprint]
                ]));

                var confirmButton = $('<button>')
                    .attr('type', 'button')
                    .addClass('btn btn-danger btn-sm uc005-confirm-rectification')
                    .text('Confirmar i emetre rectificativa');

                confirmButton.on('click', function () {
                    if (!window.confirm(
                        'Confirmes l’emissió de la factura rectificativa? La factura original no es modificarà.'
                    )) {
                        return;
                    }

                    confirmButton.prop('disabled', true);
                    window.uc005SifRectificationConfirm({
                        uuid_factura: invoice.uuid_factura,
                        correction: correction,
                        classification_event_uuid: decision.event_uuid,
                        expected_fingerprint: response.fingerprint
                    }).done(function (confirmed) {
                        if (!confirmed || confirmed.ok !== true) {
                            resultBox.empty().append(
                                $('<div>').addClass('alert alert-danger').text(
                                    (confirmed && confirmed.error) || 'No s’ha pogut emetre la rectificativa.'
                                )
                            );
                            return;
                        }

                        var success = $('<div>').addClass('alert alert-success');
                        success.append(
                            $('<div>').text(
                                'Rectificativa emesa: ' + String(confirmed.num_visible || confirmed.uuid_factura || '')
                            )
                        );

                        var refreshButton = $('<button>')
                            .attr('type', 'button')
                            .addClass('btn btn-sm btn-outline-success mt-2')
                            .text('Actualitzar factura original')
                            .on('click', function () {
                                viewSifInvoice(String(invoice.uuid_factura || ''));
                            });

                        success.append(refreshButton);
                        resultBox.empty().append(success);
                    }).fail(function (jqXHR) {
                        confirmButton.prop('disabled', false);
                        resultBox.empty().append(
                            $('<div>').addClass('alert alert-danger').text(
                                rectificationError(jqXHR, 'No s’ha pogut emetre la rectificativa.')
                            )
                        );
                    });
                });

                preview.append(confirmButton);
                resultBox.empty().append(preview);
            }).fail(function (jqXHR) {
                resultBox.empty().append(
                    $('<div>').addClass('alert alert-danger').text(
                        rectificationError(jqXHR, 'No s’ha pogut previsualitzar la rectificativa.')
                    )
                );
            }).always(function () {
                previewButton.prop('disabled', false);
            });
        });

        actions.append(previewButton);
        wrapper.append(actions).append(resultBox);
        parent.append(wrapper);
    }

    function rectificationError(jqXHR, fallback) {
        if (jqXHR && jqXHR.responseJSON && jqXHR.responseJSON.error) {
            return String(jqXHR.responseJSON.error);
        }

        if (jqXHR && jqXHR.status === 403) {
            return 'No tens autorització per executar aquesta rectificació.';
        }
        if (jqXHR && jqXHR.status === 409) {
            return 'La factura o la decisió fiscal han canviat. Cal tornar a classificar/previsualitzar.';
        }

        return fallback;
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

    function appendDocumentsTable(parent, rows) {
        if (!rows || rows.length === 0) {
            return;
        }

        var wrapper = $('<div>').addClass('mb-4');
        wrapper.append($('<h6>').text('Documents registrats'));

        var table = $('<table>').addClass('table table-sm');
        table.append(
            $('<thead>').append(
                $('<tr>')
                    .append($('<th>').text('Tipus'))
                    .append($('<th>').text('Estat'))
                    .append($('<th>').text('Hash'))
                    .append($('<th>').text('Acció'))
            )
        );

        var tbody = $('<tbody>');
        rows.forEach(function (row) {
            var id = parseInt(row.ID, 10);
            var hash = String(row.HASH_FITXER || '');
            var button = $('<button>')
                .attr('type', 'button')
                .addClass('btn btn-sm btn-outline-primary')
                .text('Descarregar');

            if (!Number.isInteger(id) || id <= 0) {
                button.prop('disabled', true);
            } else {
                button.on('click', function () {
                    downloadSifDocument(id);
                });
            }

            tbody.append(
                $('<tr>')
                    .append($('<td>').text(row.TIPUS || ''))
                    .append($('<td>').text(row.ESTAT || ''))
                    .append($('<td>').text(hash.length > 20 ? hash.substring(0, 20) + '…' : hash))
                    .append($('<td>').append(button))
            );
        });

        table.append(tbody);
        wrapper.append($('<div>').addClass('table-responsive').append(table));
        parent.append(wrapper);
    }

    function downloadSifDocument(documentId) {
        mostrarModalLoading();

        fetch(documentEndpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json; charset=utf-8'
            },
            body: JSON.stringify({
                document_id: documentId
            })
        }).then(function (response) {
            if (!response.ok) {
                var message = 'No s\'ha pogut descarregar el document.';
                if (response.status === 403) message = 'No tens autorització per descarregar aquest document.';
                if (response.status === 409) message = 'El document no supera la comprovació d\'integritat.';
                if (response.status === 503) message = 'El document encara no està disponible.';
                throw new Error(message);
            }

            var disposition = response.headers.get('Content-Disposition') || '';
            var match = disposition.match(/filename="?([^";]+)"?/i);
            var filename = match ? match[1] : 'factura-document-' + documentId;

            return response.blob().then(function (blob) {
                return {
                    blob: blob,
                    filename: filename
                };
            });
        }).then(function (download) {
            var url = window.URL.createObjectURL(download.blob);
            var link = document.createElement('a');
            link.href = url;
            link.download = download.filename;
            document.body.appendChild(link);
            link.click();
            link.remove();
            window.URL.revokeObjectURL(url);
            amagarLoadingModal();
        }).catch(function (error) {
            showSifError(error.message || 'No s\'ha pogut descarregar el document.');
        });
    }

    function showSifError(message) {
        amagarLoadingModal();
        afegirHeaderModalError('Alerta');
        afegirTextModalError(message);
        mostrarModalError();
    }
    var deepLinkUuid = new URLSearchParams(window.location.search).get('uuid_factura') || '';
    if (/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/i.test(deepLinkUuid)) {
        viewSifInvoice(deepLinkUuid);
    }
})(window, jQuery);
