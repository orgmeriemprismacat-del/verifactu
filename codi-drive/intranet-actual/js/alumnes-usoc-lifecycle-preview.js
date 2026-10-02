(function (window, $) {
    'use strict';

    var allowLegacyCancellationClick = false;
    var cancellationContext = null;
    var executionModalId = 'uc013-usoc-cancellation-execution';

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token-alumnes-lifecycle"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function isValidatedUsocCourseChange() {
        return String($('#tipusDesc-registre').text() || '').trim() === '4'
            && String($('#validDesc-registre').text() || '').trim() === '1';
    }

    function courseChangeIdentity() {
        if (!isValidatedUsocCourseChange()) {
            return null;
        }

        var id = String($('#dades-curs-canvi #id-canvi-curs-actual').text() || '').trim();
        var year = String($('#dades-canvi #dades-canvi-any .element-selected').text() || '').trim();
        var month = String($('#dades-canvi #dades-canvi-mes .element-selected').text() || '').trim();
        var course = String($('#dades-canvi #dades-canvi-curs .element-selected').text() || '').trim();
        var changeNumber = typeof numeroCanvi !== 'undefined' ? Number(numeroCanvi) : -1;

        if (
            !/^\d+$/.test(id)
            || Number(id) <= 0
            || !/^20\d{2}$/.test(year)
            || !month
            || month.toLowerCase().indexOf('triar') !== -1
            || !course
            || !Number.isInteger(changeNumber)
            || changeNumber < 0
            || changeNumber > 4
        ) {
            return null;
        }

        return {
            id_insc: Number(id),
            change_number: changeNumber,
            target: {
                year: year,
                month: month,
                course: course
            }
        };
    }

    function requestCourseChangePreview(payload) {
        return $.ajax({
            url: path + 'alumnes/sifUsocCourseChangePreview.php',
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            global: false,
            headers: {
                'X-CSRF-Token': csrfToken()
            },
            data: JSON.stringify(payload)
        });
    }

    function payerPreviewRow(label, payer) {
        payer = payer || {};
        return '<div class="card mb-2"><div class="card-body py-2">'
            + '<strong>' + escapeHtml(label) + '</strong>'
            + '<div class="row small mt-1">'
            + '<div class="col-md-3">Origen net: <strong>' + escapeHtml(payer.source_net_paid || '0.00') + ' €</strong></div>'
            + '<div class="col-md-3">Destí: <strong>' + escapeHtml(payer.target_obligation || '0.00') + ' €</strong></div>'
            + '<div class="col-md-2">Compensable: <strong>' + escapeHtml(payer.compensate_amount || '0.00') + ' €</strong></div>'
            + '<div class="col-md-2">Pendent: <strong>' + escapeHtml(payer.amount_due || '0.00') + ' €</strong></div>'
            + '<div class="col-md-2">Excés: <strong>' + escapeHtml(payer.excess_amount || '0.00') + ' €</strong></div>'
            + '</div></div></div>';
    }

    function renderCourseChangePreview(response) {
        var pricing = response && response.pricing ? response.pricing : {};
        var preview = response && response.preview ? response.preview : {};
        var target = preview.target || {};
        var fund = preview.fund_plan || {};
        var payers = fund.payers || {};

        var host = $('#uc071-preview-status');
        if (!host.length) {
            host = $('#apagar-nou-registre').closest('.form-group');
            if (!host.length) {
                host = $('#apagar-nou-registre').parent();
            }

            var block = $('<div>', {
                id: 'uc013-course-change-preview',
                'class': 'mt-3'
            });
            host.after(block);
            host = block;
        }

        host.empty()
            .removeClass('text-muted text-danger text-success')
            .addClass('text-success')
            .append(
                $('<div>', {'class': 'alert alert-warning'})
                    .text('Preview USOC calculat al servidor. Encara no s’executarà el canvi: '
                        + 'falta l’executor fiscal/econòmic COURSE_CHANGE.'),
                $('<div>', {'class': 'fw-bold mb-2'}).text('Canvi de curs USOC · dos pagadors'),
                $('<div>').text(
                    'Curs destí: '
                    + String((pricing.target && pricing.target.title) || (pricing.target && pricing.target.course) || '—')
                ),
                $('<div>').text('Preu estàndard destí: ' + String(target.target_standard_course_amount || '—') + ' €'),
                $('<div>').text('Part alumne curs: ' + String(target.target_student_course_amount || '—') + ' €'),
                $('<div>').text('Part entitat USOC: ' + String(target.target_entity_course_amount || '—') + ' €'),
                $('<div>').text('Despeses alumne: ' + String(target.management_fee || '0.00') + ' €'),
                $('<div>', {'class': 'mt-2'}).html(
                    payerPreviewRow('Alumne', payers.student)
                    + payerPreviewRow('Entitat USOC', payers.entity)
                ),
                $('<div>', {'class': 'small text-muted mt-2'})
                    .text('No s’ha emès cap rectificativa, factura, cobrament, refund ni compensació.')
            );
    }

    function showCourseChangePreviewError(message) {
        var host = $('#uc071-preview-status');
        if (!host.length) {
            host = $('#apagar-nou-registre').parent();
        }
        host.empty()
            .removeClass('text-muted text-success')
            .addClass('text-danger')
            .text(message);
    }

    function cancellationIdentity() {
        var id = String($('#dades-baixa-inscripcio #id-baixa').text() || '').trim();
        var reason = String($('#dades-baixa-motiu #motiu-baixa').val() || '').trim();

        if (!/^\d+$/.test(id) || Number(id) <= 0 || reason === '') {
            return null;
        }

        return {
            id_insc: Number(id),
            operation: 'cancellation',
            legacy_reason: reason
        };
    }

    function requestPreview(payload) {
        return $.ajax({
            url: path + 'alumnes/sifUsocLifecyclePreview.php',
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            global: false,
            headers: {
                'X-CSRF-Token': csrfToken()
            },
            data: JSON.stringify(payload)
        });
    }

    function requestExecution(payload) {
        return $.ajax({
            url: path + 'alumnes/sifUsoc.php',
            method: 'POST',
            contentType: 'application/json; charset=utf-8',
            dataType: 'json',
            global: false,
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            },
            data: JSON.stringify(payload)
        });
    }

    function payerSummary(guard) {
        var snapshot = guard && guard.payer_snapshot ? guard.payer_snapshot : null;
        if (!snapshot) {
            return '';
        }

        var pieces = [];
        if (snapshot.student) {
            pieces.push(
                'Alumne: ' + (snapshot.student.net_paid || '0.00')
                + ' € nets sobre ' + (snapshot.student.total || '—') + ' €'
            );
        }
        if (snapshot.entity) {
            pieces.push(
                'USOC: ' + (snapshot.entity.net_paid || '0.00')
                + ' € nets sobre ' + (snapshot.entity.total || '—') + ' €'
            );
        }

        return pieces.length ? ' ' + pieces.join(' · ') + '.' : '';
    }

    function blockedMessage(guard) {
        var reason = String((guard && guard.reason) || '');
        if (reason === 'USOC_FISCAL_EVIDENCE_WITHOUT_CASE_REQUIRES_REVIEW') {
            return 'Aquesta inscripció té evidència fiscal USOC sense un expedient coherent. '
                + 'Cal reconciliar-la abans de tramitar la baixa.';
        }

        return 'Aquesta inscripció USOC té dues parts econòmiques separades. '
            + 'La baixa no es pot executar amb el flux legacy fins que el SIF '
            + 'registri la decisió fiscal/econòmica de cada pagador.'
            + payerSummary(guard);
    }

    function showBlock(message) {
        if (typeof amagarLoadingModal === 'function') {
            amagarLoadingModal();
        }

        if (typeof afegirHeaderModalError === 'function'
            && typeof afegirTextModalError === 'function'
            && typeof mostrarModalError === 'function') {
            afegirHeaderModalError('Baixa bloquejada pel SIF');
            afegirTextModalError(message);
            mostrarModalError();
            return;
        }

        window.alert(message);
    }

    function requestId(idInsc) {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return 'uc013-cancel-' + idInsc + '-' + window.crypto.randomUUID();
        }

        return 'uc013-cancel-' + idInsc + '-' + Date.now()
            + '-' + Math.random().toString(16).slice(2);
    }

    function localTimestamp() {
        var d = new Date();
        function two(value) {
            return String(value).padStart(2, '0');
        }
        return d.getFullYear() + '-' + two(d.getMonth() + 1) + '-' + two(d.getDate())
            + ' ' + two(d.getHours()) + ':' + two(d.getMinutes()) + ':' + two(d.getSeconds());
    }

    function findPayerAction(plan, role) {
        var actions = plan && Array.isArray(plan.actions) ? plan.actions : [];
        for (var i = 0; i < actions.length; i++) {
            if (String(actions[i].payer_role || '') === role) {
                return actions[i];
            }
        }
        return null;
    }

    function ensureExecutionModal() {
        var existing = document.getElementById(executionModalId);
        if (existing) {
            return existing;
        }

        var modal = document.createElement('div');
        modal.id = executionModalId;
        modal.className = 'modal fade';
        modal.tabIndex = -1;
        modal.setAttribute('aria-hidden', 'true');
        modal.innerHTML =
            '<div class="modal-dialog modal-xl modal-dialog-scrollable">'
            + '<div class="modal-content">'
            + '<div class="modal-header">'
            + '<h5 class="modal-title">Baixa USOC · decisió fiscal i econòmica</h5>'
            + '<button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Tanca"></button>'
            + '</div>'
            + '<div class="modal-body">'
            + '<div class="alert alert-warning">'
            + 'La matrícula no es donarà de baixa al legacy fins que aquesta comanda '
            + 'quedi COMPLETED al SIF. Alumne i entitat es tracten per separat.'
            + '</div>'
            + '<div id="uc013-cancel-plan"></div>'
            + '<div id="uc013-cancel-error" class="alert alert-danger d-none"></div>'
            + '</div>'
            + '<div class="modal-footer">'
            + '<button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Torna</button>'
            + '<button type="button" class="btn btn-danger" id="uc013-cancel-confirm">'
            + 'Registrar decisió i continuar baixa'
            + '</button>'
            + '</div>'
            + '</div></div>';

        document.body.appendChild(modal);

        modal.addEventListener('hidden.bs.modal', function () {
            if (cancellationContext && cancellationContext.completed !== true) {
                var legacyModal = document.getElementById('modalDonarBaixa');
                if (legacyModal && window.bootstrap && bootstrap.Modal) {
                    bootstrap.Modal.getOrCreateInstance(legacyModal).show();
                }
            }
        });

        $('#uc013-cancel-confirm').on('click', function () {
            submitCancellationExecution();
        });

        return modal;
    }

    function payerCard(role, label, action, legacyReason) {
        if (!action) {
            return '<div class="alert alert-danger">Falta el pla del pagador ' + escapeHtml(label) + '.</div>';
        }

        var invoiceUuid = String(action.invoice_uuid || '');
        var total = String(action.invoice_total || action.total || '0.00');
        var netPaid = String(action.net_paid || '0.00');
        var maxRefundable = String(action.max_refundable || '0.00');
        var prefix = 'uc013-' + role;

        if (!invoiceUuid) {
            return '<div class="card mb-3" data-payer="' + role + '">'
                + '<div class="card-header"><strong>' + escapeHtml(label) + '</strong></div>'
                + '<div class="card-body">'
                + '<p class="mb-1">Factura: <strong>no emesa</strong></p>'
                + '<p class="mb-0">No es crearà rectificativa ni refund per aquest pagador.</p>'
                + '<input type="hidden" id="' + prefix + '-no-invoice" value="1">'
                + '</div></div>';
        }

        var hasFunds = Number(maxRefundable) > 0;
        return '<div class="card mb-3" data-payer="' + role + '">'
            + '<div class="card-header"><strong>' + escapeHtml(label) + '</strong></div>'
            + '<div class="card-body">'
            + '<div class="row mb-3">'
            + '<div class="col-md-6"><strong>Factura</strong><div class="text-break">' + escapeHtml(invoiceUuid) + '</div></div>'
            + '<div class="col-md-2"><strong>Total</strong><div>' + escapeHtml(total) + ' €</div></div>'
            + '<div class="col-md-2"><strong>Pagat net</strong><div>' + escapeHtml(netPaid) + ' €</div></div>'
            + '<div class="col-md-2"><strong>Màxim retornable</strong><div>' + escapeHtml(maxRefundable) + ' €</div></div>'
            + '</div>'

            + '<div class="border rounded p-3 mb-3">'
            + '<h6>Decisió fiscal</h6>'
            + '<div class="row g-2">'
            + '<div class="col-md-4"><label class="form-label">Acció</label>'
            + '<select class="form-select uc013-fiscal-action" id="' + prefix + '-fiscal-action">'
            + '<option value="DEFER_FISCAL" selected>Diferir revisió fiscal</option>'
            + '<option value="RECTIFY">Emetre rectificativa</option>'
            + '<option value="NO_FISCAL_EFFECT">Sense efecte fiscal</option>'
            + '</select></div>'
            + '<div class="col-md-8"><label class="form-label">Motiu/justificació</label>'
            + '<input class="form-control" id="' + prefix + '-fiscal-reason" value="' + escapeAttr(legacyReason) + '"></div>'
            + '</div>'
            + '<div class="row g-2 mt-2 uc013-rect-fields d-none">'
            + '<div class="col-md-3"><label class="form-label">Import rectificativa</label>'
            + '<input type="number" step="0.01" max="-0.01" class="form-control" id="' + prefix + '-rect-amount" value="-' + escapeAttr(total) + '"></div>'
            + '<div class="col-md-3"><label class="form-label">Mode</label>'
            + '<select class="form-select" id="' + prefix + '-rect-mode">'
            + '<option value="SUBSTITUCIO" selected>Substitució total</option>'
            + '<option value="DIFERENCIES">Diferències</option>'
            + '</select></div>'
            + '<div class="col-md-6"><label class="form-label">Motiu rectificativa</label>'
            + '<input class="form-control" id="' + prefix + '-rect-reason" value="ANULACIO_TOTAL"></div>'
            + '</div></div>'

            + '<div class="border rounded p-3">'
            + '<h6>Decisió econòmica</h6>'
            + '<div class="row g-2">'
            + '<div class="col-md-4"><label class="form-label">Acció</label>'
            + '<select class="form-select uc013-economic-action" id="' + prefix + '-economic-action">'
            + (hasFunds
                ? '<option value="DEFER_REFUND" selected>Diferir devolució</option>'
                    + '<option value="REFUND">Registrar refund ja executat</option>'
                    + '<option value="NO_REFUND">No retornar</option>'
                : '<option value="NO_REFUND" selected>Sense fons retornables</option>')
            + '</select></div>'
            + '<div class="col-md-8"><label class="form-label">Motiu/seguiment</label>'
            + '<input class="form-control" id="' + prefix + '-economic-reason" value="'
            + (hasFunds ? '' : 'No hi ha fons reals retornables') + '"></div>'
            + '</div>'
            + '<div class="row g-2 mt-2 uc013-refund-fields d-none">'
            + '<div class="col-md-3"><label class="form-label">Import refund</label>'
            + '<input type="number" step="0.01" min="0.01" max="' + escapeAttr(maxRefundable)
            + '" class="form-control" id="' + prefix + '-refund-amount" value="' + escapeAttr(maxRefundable) + '"></div>'
            + '<div class="col-md-3"><label class="form-label">Data moviment</label>'
            + '<input type="datetime-local" class="form-control" id="' + prefix + '-refund-date"></div>'
            + '<div class="col-md-3"><label class="form-label">Referència bancària</label>'
            + '<input class="form-control" id="' + prefix + '-refund-reference"></div>'
            + '<div class="col-md-3"><label class="form-label">Banc</label>'
            + '<input class="form-control" id="' + prefix + '-refund-bank"></div>'
            + '</div></div>'
            + '</div></div>';
    }

    function renderExecutionPlan(plan, identity) {
        var student = findPayerAction(plan, 'student');
        var entity = findPayerAction(plan, 'entity');

        $('#uc013-cancel-plan').html(
            '<p><strong>Inscripció:</strong> ' + escapeHtml(String(identity.id_insc))
            + ' · <strong>IDPAG:</strong> ' + escapeHtml(String(plan.idpag || '—')) + '</p>'
            + payerCard('student', 'Alumne', student, identity.legacy_reason)
            + payerCard('entity', 'Entitat USOC', entity, identity.legacy_reason)
        );

        $('.uc013-fiscal-action').off('change.uc013').on('change.uc013', function () {
            var card = $(this).closest('[data-payer]');
            card.find('.uc013-rect-fields').toggleClass('d-none', $(this).val() !== 'RECTIFY');
        });

        $('.uc013-economic-action').off('change.uc013').on('change.uc013', function () {
            var card = $(this).closest('[data-payer]');
            card.find('.uc013-refund-fields').toggleClass('d-none', $(this).val() !== 'REFUND');
        });
    }

    function decisionFor(role, action) {
        var prefix = '#uc013-' + role;
        if (!action || !action.invoice_uuid) {
            return {
                fiscal_action: 'NO_FISCAL_EFFECT',
                fiscal_reason: 'Factura no emesa per aquest pagador',
                economic_action: 'NO_REFUND',
                economic_reason: 'Sense factura ni fons retornables'
            };
        }

        var fiscalAction = String($(prefix + '-fiscal-action').val() || '');
        var economicAction = String($(prefix + '-economic-action').val() || '');

        var result = {
            fiscal_action: fiscalAction,
            fiscal_reason: String($(prefix + '-fiscal-reason').val() || '').trim(),
            economic_action: economicAction,
            economic_reason: String($(prefix + '-economic-reason').val() || '').trim()
        };

        if (fiscalAction === 'RECTIFY') {
            result.rectification_amount = String($(prefix + '-rect-amount').val() || '').trim();
            result.rectification_mode = String($(prefix + '-rect-mode').val() || '').trim();
            result.rectification_reason = String($(prefix + '-rect-reason').val() || '').trim();
        }

        if (economicAction === 'REFUND') {
            result.refund_amount = String($(prefix + '-refund-amount').val() || '').trim();
            result.refund_movement_date = String($(prefix + '-refund-date').val() || '').trim().replace('T', ' ');
            result.refund_reference = String($(prefix + '-refund-reference').val() || '').trim();
            result.refund_bank = String($(prefix + '-refund-bank').val() || '').trim();
        }

        return result;
    }

    function showExecutionModal(plan, identity, button) {
        var modal = ensureExecutionModal();
        cancellationContext = {
            identity: identity,
            button: button,
            plan: plan,
            requestId: requestId(identity.id_insc),
            executionPayload: null,
            completed: false
        };

        renderExecutionPlan(plan, identity);
        $('#uc013-cancel-error').addClass('d-none').text('');

        var legacyModal = document.getElementById('modalDonarBaixa');
        if (legacyModal && window.bootstrap && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(legacyModal).hide();
        }
        bootstrap.Modal.getOrCreateInstance(modal).show();
    }

    function submitCancellationExecution() {
        if (!cancellationContext) {
            return;
        }

        var plan = cancellationContext.plan;
        var identity = cancellationContext.identity;
        var student = findPayerAction(plan, 'student');
        var entity = findPayerAction(plan, 'entity');

        if (cancellationContext.executionPayload === null) {
            cancellationContext.executionPayload = {
                action: 'execute_cancellation',
                request_id: cancellationContext.requestId,
                id_insc: identity.id_insc,
                idpag: Number(plan.idpag || 0),
                input: {
                    reason_code: 'BAIXA_INSCRIPCIO_USOC',
                    effective_at: localTimestamp(),
                    student: decisionFor('student', student),
                    entity: decisionFor('entity', entity)
                }
            };

            $('#' + executionModalId)
                .find('input, select')
                .prop('disabled', true);
        }

        var payload = cancellationContext.executionPayload;

        $('#uc013-cancel-confirm').prop('disabled', true);
        $('#uc013-cancel-error').addClass('d-none').text('');

        requestExecution(payload)
            .done(function (response) {
                if (!response || response.ok !== true || !response.execution) {
                    $('#uc013-cancel-error')
                        .removeClass('d-none')
                        .text((response && response.error) || 'No s’ha pogut registrar la baixa USOC.');
                    return;
                }

                cancellationContext.completed = true;
                bootstrap.Modal.getOrCreateInstance(
                    document.getElementById(executionModalId)
                ).hide();

                allowLegacyCancellationClick = true;
                $(cancellationContext.button).trigger('click');
            })
            .fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'No s’ha pogut registrar la decisió fiscal/econòmica USOC.';
                $('#uc013-cancel-error').removeClass('d-none').text(message);
            })
            .always(function () {
                $('#uc013-cancel-confirm').prop('disabled', false);
            });
    }

    function escapeHtml(value) {
        return $('<div>').text(String(value || '')).html();
    }

    function escapeAttr(value) {
        return escapeHtml(value).replace(/"/g, '&quot;');
    }

    document.addEventListener('click', function (event) {
        var button = event.target.closest('#modalCanviCurs .save-result');
        if (!button || !isValidatedUsocCourseChange()) {
            return;
        }

        var identity = courseChangeIdentity();

        event.preventDefault();
        event.stopImmediatePropagation();

        if (!identity) {
            showCourseChangePreviewError(
                'Cal completar correctament curs destí i número de canvi abans de calcular el preview USOC.'
            );
            return;
        }

        if (typeof mostrarModalLoading === 'function') {
            mostrarModalLoading();
        }

        requestCourseChangePreview(identity)
            .done(function (response) {
                if (typeof amagarLoadingModal === 'function') {
                    amagarLoadingModal();
                }

                if (!response || response.ok !== true || !response.preview) {
                    showCourseChangePreviewError(
                        'No s’ha pogut calcular el preview USOC del canvi de curs.'
                    );
                    return;
                }

                renderCourseChangePreview(response);
            })
            .fail(function (xhr) {
                if (typeof amagarLoadingModal === 'function') {
                    amagarLoadingModal();
                }

                var message = xhr && xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'No s’ha pogut calcular el preview USOC del canvi de curs.';
                showCourseChangePreviewError(message);
            });
    }, true);

    document.addEventListener('click', function (event) {
        var button = event.target.closest('#modalDonarBaixa .confirma-baixa');
        if (!button) {
            return;
        }

        if (allowLegacyCancellationClick) {
            allowLegacyCancellationClick = false;
            return;
        }

        var identity = cancellationIdentity();
        if (!identity) {
            return;
        }

        event.preventDefault();
        event.stopImmediatePropagation();

        if (typeof mostrarModalLoading === 'function') {
            mostrarModalLoading();
        }

        requestPreview(identity)
            .done(function (response) {
                var guard = response && response.guard ? response.guard : null;
                if (!response || response.ok !== true || !guard) {
                    showBlock('No s’ha pogut validar l’estat fiscal abans de tramitar la baixa.');
                    return;
                }

                if (guard.allowed === true) {
                    if (typeof amagarLoadingModal === 'function') {
                        amagarLoadingModal();
                    }
                    allowLegacyCancellationClick = true;
                    $(button).trigger('click');
                    return;
                }

                if (
                    String(guard.reason || '') === 'USOC_FINANCING_CASE_REQUIRES_ORCHESTRATION'
                    && response.plan
                    && response.plan.requires_usoc_orchestration === true
                ) {
                    if (typeof amagarLoadingModal === 'function') {
                        amagarLoadingModal();
                    }
                    showExecutionModal(response.plan, identity, button);
                    return;
                }

                showBlock(blockedMessage(guard));
            })
            .fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'No s’ha pogut validar l’estat fiscal abans de tramitar la baixa.';
                showBlock(message);
            });
    }, true);
})(window, jQuery);
