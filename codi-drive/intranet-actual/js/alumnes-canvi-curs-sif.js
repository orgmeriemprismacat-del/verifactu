(function ($) {
    'use strict';

    var state = {
        standardTargetAmount: null,
        preview: null,
        previewFingerprint: null,
        allowLegacyClick: false
    };

    function normalizedMoney(value) {
        var text = String(value == null ? '' : value).trim().replace(',', '.');
        if (!/^\d+(?:\.\d{1,2})?$/.test(text)) {
            return null;
        }
        return Number.parseFloat(text).toFixed(2);
    }

    function currentValue(selector) {
        var element = $(selector);
        if (!element.length) {
            return '';
        }
        return element.is('input,textarea,select') ? element.val() : element.text();
    }

    function ensureEnhancement() {
        var price = $('#apagar-nou-registre');
        if (!price.length) {
            return;
        }

        if (state.standardTargetAmount === null) {
            state.standardTargetAmount = normalizedMoney(price.val());
        }

        if ($('#uc071-sif-preview').length) {
            syncManualPriceUi();
            return;
        }

        var host = price.closest('.form-group');
        if (!host.length) {
            host = price.parent();
        }

        var block = $('<div>', {
            id: 'uc071-sif-preview',
            'class': 'mt-3 border rounded p-3 bg-light'
        });

        block.append(
            $('<div>', {'class': 'small text-muted mb-1'}).text('Preu calculat del curs'),
            $('<div>', {'class': 'mb-2'}).append(
                $('<strong>', {id: 'uc071-preu-estandard'}).text(state.standardTargetAmount || '—'),
                document.createTextNode(' €')
            ),
            $('<div>', {id: 'uc071-preu-manual-wrap', 'class': 'd-none mb-3'}).append(
                $('<label>', {'for': 'uc071-motiu-preu-manual', 'class': 'form-label fw-bold'})
                    .text('Motiu de modificació manual del preu'),
                $('<textarea>', {
                    id: 'uc071-motiu-preu-manual',
                    'class': 'form-control',
                    rows: 2,
                    maxlength: 500,
                    placeholder: 'Indica per què el preu final no és el preu calculat.'
                })
            ),
            $('<div>', {id: 'uc071-preview-status', 'class': 'small text-muted'})
                .text('La classificació fiscal i econòmica es calcularà abans de confirmar.')
        );

        host.after(block);

        $('#uc071-motiu-preu-manual').on('input', invalidatePreview);
        price.on('input', function () {
            syncManualPriceUi();
            invalidatePreview();
        });

        var paid = $('#pagat-nou-registre');
        paid.prop('readonly', true)
            .attr('aria-readonly', 'true')
            .attr('title', 'El pagat es rellegeix del SIF o del llegat servidor i no es pot editar com a preu.');
        if (!$('#uc071-pagat-help').length) {
            paid.after(
                $('<div>', {
                    id: 'uc071-pagat-help',
                    'class': 'form-text'
                }).text('Import ja pagat: dada econòmica de lectura. No es modifica des del canvi de curs.')
            );
        }

        $('#despeses-registre').on('input change', invalidatePreview);
        $('#motiu-canvi').on('input change', invalidatePreview);

        syncManualPriceUi();
    }

    function setStandardTargetAmount(value) {
        var normalized = normalizedMoney(value);
        if (normalized === null) {
            return;
        }

        state.standardTargetAmount = normalized;
        state.preview = null;
        state.previewFingerprint = null;

        ensureEnhancement();
        $('#uc071-preu-estandard').text(normalized);
        syncManualPriceUi();
    }

    function syncManualPriceUi() {
        var effective = normalizedMoney($('#apagar-nou-registre').val());
        var standard = state.standardTargetAmount;
        var manual = effective !== null && standard !== null && effective !== standard;

        $('#uc071-preu-manual-wrap').toggleClass('d-none', !manual);
        $('#apagar-nou-registre').attr('data-uc071-pricing-mode', manual ? 'MANUAL' : 'STANDARD');

        if (!manual) {
            $('#uc071-motiu-preu-manual').val('');
        }
    }

    function invalidatePreview() {
        state.preview = null;
        state.previewFingerprint = null;
        $('#uc071-preview-status')
            .removeClass('text-danger text-success')
            .addClass('text-muted')
            .text('Cal recalcular la previsualització abans de confirmar.');
    }

    function payload() {
        ensureEnhancement();

        var original = normalizedMoney(currentValue('#apagar-canvi-curs-actual'));
        var standard = state.standardTargetAmount || normalizedMoney($('#apagar-nou-registre').val());
        var proposed = normalizedMoney($('#apagar-nou-registre').val());
        var paid = normalizedMoney($('#pagat-nou-registre').val());
        var fee = normalizedMoney($('#despeses-registre').val());

        return {
            source_enrollment_id: String(currentValue('#id-canvi-curs-actual')).trim(),
            source_course: String(currentValue('#curs-canvi-curs-actual')).trim(),
            target_course: String(currentValue('#dades-canvi-curs .element-selected')).trim(),
            original_amount: original,
            standard_target_amount: standard,
            proposed_target_amount: proposed,
            paid_amount: paid,
            management_fee: fee,
            manual_price_reason: String($('#uc071-motiu-preu-manual').val() || '').trim()
        };
    }

    function fingerprint(data) {
        return JSON.stringify(data);
    }

    function validateBeforePreview(data) {
        if (!/^\d+$/.test(data.source_enrollment_id) || Number(data.source_enrollment_id) <= 0) {
            return 'No s\'ha pogut identificar la inscripció origen.';
        }
        if (!data.source_course || !data.target_course) {
            return 'Cal escollir correctament el curs origen i el curs de destinació.';
        }
        if (data.original_amount === null || data.standard_target_amount === null
            || data.proposed_target_amount === null || data.paid_amount === null
            || data.management_fee === null) {
            return 'Hi ha imports amb un format no vàlid.';
        }
        if (data.proposed_target_amount !== data.standard_target_amount && !data.manual_price_reason) {
            return 'Has modificat el preu calculat. Cal indicar el motiu del preu manual.';
        }
        return null;
    }

    function fiscalLabel(code) {
        switch (code) {
            case 'NONE':
                return 'Sense rectificació';
            case 'RECTIFY_DIFFERENCE':
                return 'Rectificar per diferència';
            case 'RECTIFY_AND_REISSUE':
                return 'Rectificar factura anterior i emetre una factura nova';
            case 'REVIEW_REQUIRED':
                return 'Revisió fiscal necessària';
            default:
                return code || 'Pendent';
        }
    }

    function economicLabel(impact) {
        switch (impact.economic_decision) {
            case 'AMOUNT_DUE':
                return 'Queden ' + impact.amount_due + ' € pendents de cobrar';
            case 'EXCESS_TO_RESOLVE':
                return 'Hi ha ' + impact.excess_amount + ' € a favor del pagador: cal decidir retorn, saldo o altra resolució';
            case 'NONE':
                return 'No hi ha diferència econòmica pendent';
            default:
                return impact.economic_decision || 'Pendent';
        }
    }

    function priceRelationLabel(code) {
        if (code === 'HIGHER') {
            return 'El preu final és superior';
        }
        if (code === 'LOWER') {
            return 'El preu final és inferior';
        }
        return 'El preu final és el mateix';
    }

    function renderPreview(result) {
        var impact = result.impact || {};
        var status = $('#uc071-preview-status');
        status.empty().removeClass('text-muted text-danger').addClass('text-success');

        var list = $('<div>', {'class': 'uc071-preview-result'});
        list.append(
            $('<div>', {'class': 'fw-bold mb-2'}).text('Previsualització del canvi'),
            $('<div>').text('Preu origen: ' + (impact.original_amount || '—') + ' €'),
            $('<div>').text('Preu calculat destí: ' + (impact.standard_target_amount || '—') + ' €'),
            $('<div>').text('Preu final destí: ' + (impact.effective_target_amount || '—') + ' €'
                + (impact.pricing_mode === 'MANUAL' ? ' · preu manual justificat' : '')),
            $('<div>').text('Despeses: ' + (impact.management_fee || '0.00') + ' €'),
            $('<div>').text('Total previst: ' + (impact.target_total || '—') + ' €'),
            $('<div>').text('Situació de preu: ' + priceRelationLabel(impact.price_relation)),
            $('<div>', {'class': 'mt-2 fw-bold'}).text('Tractament fiscal proposat: ' + fiscalLabel(impact.fiscal_decision)),
            $('<div>').text('Impacte econòmic: ' + economicLabel(impact))
        );

        if (result.invoice && result.invoice.num_visible) {
            list.append($('<div>').text('Factura SIF relacionada: ' + result.invoice.num_visible));
        }
        if (result.invoice_resolution === 'MULTIPLE') {
            list.append(
                $('<div>', {'class': 'text-danger fw-bold mt-2'})
                    .text('Hi ha més d’una factura SIF relacionada. No es permet confirmar automàticament.')
            );
        }

        list.append(
            $('<div>', {'class': 'small text-muted mt-2'})
                .text('Aquesta previsualització no registra cap cobrament, devolució ni rectificativa.')
        );

        status.append(list);

        if (impact.paid_amount && normalizedMoney(impact.paid_amount) !== null) {
            $('#pagat-nou-registre').val(impact.paid_amount);

            var targetTotal = normalizedMoney(impact.target_total);
            var paidAmount = normalizedMoney(impact.paid_amount);
            if (targetTotal !== null && paidAmount !== null) {
                $('#pendent-nou-registre').val(
                    (Number.parseFloat(targetTotal) - Number.parseFloat(paidAmount)).toFixed(2)
                );
            }
        }
    }

    function renderError(message) {
        $('#uc071-preview-status')
            .empty()
            .removeClass('text-muted text-success')
            .addClass('text-danger')
            .text(message);
    }

    function requestPreview(data) {
        return $.ajax({
            url: path + 'alumnes/sifCanviCursPreview.php',
            method: 'POST',
            data: JSON.stringify(data),
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            global: false,
            headers: {
                'X-CSRF-Token': $('meta[name="csrf-token-alumnes-lifecycle"]').attr('content') || ''
            }
        }).then(function (result) {
            if (!result || result.ok !== true) {
                return $.Deferred().reject({responseJSON: result}).promise();
            }

            renderPreview(result);
            state.preview = result;
            state.previewFingerprint = fingerprint(payload());
            return result;
        });
    }

    function appendPreviewToConfirmation(result) {
        var body = $('#modalConfirmacioCanvi .modal-body');
        if (!body.length || !result || !result.impact) {
            return;
        }

        body.find('#uc071-confirmacio-sif').remove();
        var impact = result.impact;
        var box = $('<div>', {
            id: 'uc071-confirmacio-sif',
            'class': 'alert alert-info mt-3'
        });

        box.append(
            $('<div>', {'class': 'fw-bold'}).text('Impacte fiscal i econòmic'),
            $('<div>').text('Preu: ' + priceRelationLabel(impact.price_relation)),
            $('<div>').text('Fiscal: ' + fiscalLabel(impact.fiscal_decision)),
            $('<div>').text('Econòmic: ' + economicLabel(impact))
        );

        if (impact.pricing_mode === 'MANUAL') {
            box.append(
                $('<div>').text('Preu manual: ' + impact.effective_target_amount + ' €'),
                $('<div>').text('Motiu: ' + (impact.manual_price_reason || '—'))
            );
        }

        body.append(box);
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('#modalCanviCurs .save-result');
        if (!button) {
            return;
        }

        if (state.allowLegacyClick) {
            state.allowLegacyClick = false;
            return;
        }

        var data = payload();
        var error = validateBeforePreview(data);

        event.preventDefault();
        event.stopImmediatePropagation();

        if (error) {
            renderError(error);
            return;
        }

        var currentFingerprint = fingerprint(data);
        var ready = state.preview
            && state.previewFingerprint === currentFingerprint
            && state.preview.can_confirm_legacy_change === true;

        var proceed = function (result) {
            if (!result.can_confirm_legacy_change) {
                renderError('El canvi necessita revisió abans de poder-se confirmar.');
                return;
            }

            state.allowLegacyClick = true;
            $(button).trigger('click');
            window.setTimeout(function () {
                appendPreviewToConfirmation(result);
            }, 0);
        };

        if (ready) {
            proceed(state.preview);
            return;
        }

        $('#uc071-preview-status')
            .removeClass('text-danger text-success')
            .addClass('text-muted')
            .text('Calculant impacte fiscal i econòmic…');

        requestPreview(data)
            .done(proceed)
            .fail(function (xhr) {
                var response = xhr && xhr.responseJSON ? xhr.responseJSON : null;
                renderError(response && response.error
                    ? response.error
                    : 'No s\'ha pogut validar el canvi amb el SIF. No s\'executarà el canvi.');
            });
    }, true);

    $.ajaxPrefilter(function (options, originalOptions) {
        if (!options.url || options.url.indexOf('realitzarCanviCurs_CanviCurs.php') === -1) {
            return;
        }

        var data = payload();
        var impact = state.preview && state.preview.impact ? state.preview.impact : {};

        var extra = {
            csrfToken: $('meta[name="csrf-token-alumnes-lifecycle"]').attr('content') || '',
            pendent: currentValue('#pendent-nou-registre'),
            sif_source_course: data.source_course,
            sif_original_amount: data.original_amount,
            sif_standard_target_amount: data.standard_target_amount,
            sif_manual_price_reason: data.manual_price_reason,
            sif_expected_fiscal_decision: impact.fiscal_decision || '',
            sif_expected_economic_decision: impact.economic_decision || ''
        };

        options.type = 'POST';
        options.method = 'POST';

        if (typeof options.data === 'string') {
            var suffix = $.param(extra);
            options.data = options.data
                ? options.data + '&' + suffix
                : suffix;
        } else {
            options.data = $.extend({}, originalOptions.data || options.data || {}, extra);
        }
    });

    $(document).ajaxComplete(function (_event, _xhr, settings) {
        if (settings && settings.url && settings.url.indexOf('mostrarModalCanviCurs.php') !== -1) {
            window.setTimeout(function () {
                state.standardTargetAmount = normalizedMoney($('#apagar-nou-registre').val());
                state.preview = null;
                state.previewFingerprint = null;
                ensureEnhancement();
            }, 0);
        }

        if (settings && settings.url && settings.url.indexOf('buscarPreuAPagar_modalCanviCurs.php') !== -1) {
            window.setTimeout(function () {
                setStandardTargetAmount($('#apagar-nou-registre').val());
            }, 0);
        }
    });
})(jQuery);
