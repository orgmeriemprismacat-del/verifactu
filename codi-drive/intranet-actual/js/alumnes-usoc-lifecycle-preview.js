(function (window, $) {
    'use strict';

    var allowLegacyCancellationClick = false;

    function csrfToken() {
        var meta = document.querySelector('meta[name="csrf-token-alumnes-lifecycle"]');
        return meta ? String(meta.getAttribute('content') || '') : '';
    }

    function cancellationIdentity() {
        var id = String($('#dades-baixa-inscripcio #id-baixa').text() || '').trim();
        var reason = String($('#dades-baixa-motiu #motiu-baixa').val() || '').trim();

        if (!/^\d+$/.test(id) || Number(id) <= 0 || reason === '') {
            return null;
        }

        return {
            id_insc: Number(id),
            operation: 'cancellation'
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
            + 'La baixa no es pot executar amb el flux legacy perquè cal decidir per separat '
            + 'l’efecte sobre la factura i els diners de l’alumne i de l’entitat.'
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

                if (guard.allowed !== true) {
                    showBlock(blockedMessage(guard));
                    return;
                }

                if (typeof amagarLoadingModal === 'function') {
                    amagarLoadingModal();
                }

                allowLegacyCancellationClick = true;
                $(button).trigger('click');
            })
            .fail(function (xhr) {
                var message = xhr && xhr.responseJSON && xhr.responseJSON.error
                    ? xhr.responseJSON.error
                    : 'No s’ha pogut validar l’estat fiscal abans de tramitar la baixa.';
                showBlock(message);
            });
    }, true);
})(window, jQuery);
