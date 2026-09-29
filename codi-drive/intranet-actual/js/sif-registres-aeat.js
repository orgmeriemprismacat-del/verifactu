(() => {
    'use strict';

    const endpoint = 'https://intranet.prisma.cat/ajax/sif/sifAeat.php';
    const app = document.getElementById('sif-aeat-app');
    if (!app) return;

    const csrf = document.querySelector('.mainpanel')?.dataset.csrf || '';
    const queueBody = document.getElementById('sif-aeat-queue');
    const summary = document.getElementById('sif-aeat-summary');
    const detailCard = document.getElementById('sif-aeat-detail');
    const alertBox = document.getElementById('sif-aeat-alert');
    let currentQueueId = null;

    const esc = (value) => {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    };

    const badge = (status) => {
        const value = String(status || '—').toUpperCase();
        const cls = {
            SENT: 'text-bg-success',
            ACCEPTED: 'text-bg-success',
            ACCEPTED_WITH_ERRORS: 'text-bg-warning',
            REJECTED: 'text-bg-danger',
            REVIEW: 'text-bg-warning',
            DEAD_LETTER: 'text-bg-danger',
            RETRY: 'text-bg-info',
            PROCESSING: 'text-bg-primary',
            PENDING: 'text-bg-secondary',
            UNCERTAIN: 'text-bg-warning',
            FAILED: 'text-bg-danger',
            RESOLVED: 'text-bg-success',
            OPEN: 'text-bg-danger'
        }[value] || 'text-bg-secondary';
        return '<span class="badge ' + cls + '">' + esc(value) + '</span>';
    };

    async function call(payload) {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        });
        const json = await response.json().catch(() => ({ok: false, error: 'Resposta JSON no vàlida'}));
        if (!response.ok || json.ok === false) {
            const error = new Error(json.error || 'Operació AEAT fallida');
            error.status = response.status;
            throw error;
        }
        return json;
    }

    function showAlert(message, type = 'danger') {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function hideAlert() {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    }

    async function loadSummary() {
        const response = await call({action: 'summary'});
        const data = response.data || {};
        const counts = data.queue?.counts || {};
        const metrics = [
            ['PENDING', counts.PENDING || 0],
            ['PROCESSING', counts.PROCESSING || 0],
            ['RETRY', counts.RETRY || 0],
            ['REVIEW', counts.REVIEW || 0],
            ['DEAD_LETTER', counts.DEAD_LETTER || 0],
            ['SENT', counts.SENT || 0],
            ['Incidències obertes', data.open_incidents || 0]
        ];
        summary.innerHTML = metrics.map(([label, value]) =>
            '<div class="col-6 col-md-4 col-xl">' +
            '<div class="card sif-metric shadow-sm h-100"><div class="card-body py-2">' +
            '<div class="small text-muted">' + esc(label) + '</div>' +
            '<div class="value">' + esc(value) + '</div>' +
            '</div></div></div>'
        ).join('');
    }

    async function loadQueue() {
        const status = document.getElementById('sif-aeat-status').value;
        const response = await call({action: 'list', status: status || null, limit: 100});
        const rows = Array.isArray(response.data) ? response.data : [];
        queueBody.innerHTML = rows.length ? rows.map(row => {
            const error = row.AEAT_CSV || row.AEAT_ERROR_CODE || row.AEAT_ERROR_MESSAGE || '—';
            const date = row.SENT_AT || row.NEXT_RETRY_AT || row.LOCKED_AT || row.CREATED_AT || '—';
            return '<tr>' +
                '<td>' + esc(row.ID) + '</td>' +
                '<td><strong>' + esc(row.NUM_VISIBLE || row.UUID_FACTURA) + '</strong><br><small class="text-muted">' +
                    esc(row.UUID_FACTURA) + '</small></td>' +
                '<td>' + badge(row.STATUS) + '</td>' +
                '<td>' + esc(row.ATTEMPTS) + '</td>' +
                '<td>' + esc(error) + '</td>' +
                '<td>' + esc(date) + '</td>' +
                '<td class="text-end"><button class="btn btn-sm btn-outline-primary sif-aeat-detail-btn" data-id="' +
                    esc(row.ID) + '">Veure</button></td>' +
                '</tr>';
        }).join('') : '<tr><td colspan="7" class="text-center text-muted py-4">No hi ha registres.</td></tr>';
    }

    async function loadDetail(queueId) {
        hideAlert();
        const response = await call({action: 'detail', queue_id: Number(queueId)});
        const data = response.data || {};
        const queue = data.queue || {};
        const record = data.record || {};
        currentQueueId = Number(queueId);

        document.getElementById('sif-aeat-detail-summary').innerHTML = [
            ['Queue', queue.ID],
            ['Factura', queue.NUM_VISIBLE || queue.UUID_FACTURA],
            ['Estat cua', badge(queue.STATUS), true],
            ['Ordre fiscal', queue.FISCAL_ORDER],
            ['Estat AEAT', badge(record.ESTAT_AEAT), true],
            ['Hash registre', record.HASH_FACT]
        ].map(([label, value, html]) =>
            '<div class="col-md-4"><div class="small text-muted">' + esc(label) + '</div><div>' +
            (html ? value : '<code>' + esc(value || '—') + '</code>') + '</div></div>'
        ).join('');

        const attempts = Array.isArray(data.attempts) ? data.attempts : [];
        const latestAttemptNo = attempts.reduce((max, attempt) => Math.max(max, Number(attempt.ATTEMPT_NO) || 0), 0);
        document.getElementById('sif-aeat-attempts').innerHTML = attempts.length ? attempts.map(attempt => {
            const terminal = ['ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED'].includes(String(attempt.STATUS));
            const isLatest = Number(attempt.ATTEMPT_NO) === latestAttemptNo;
            const reconcile = String(queue.STATUS) === 'REVIEW' && terminal && isLatest
                ? '<button class="btn btn-sm btn-warning sif-aeat-reconcile" data-attempt="' +
                    esc(attempt.UUID_ATTEMPT) + '">Conciliar sense reenviar</button>'
                : '—';
            return '<tr>' +
                '<td><code>' + esc(attempt.UUID_ATTEMPT) + '</code></td>' +
                '<td>' + esc(attempt.ATTEMPT_NO) + '</td>' +
                '<td>' + badge(attempt.STATUS) + '</td>' +
                '<td>' + esc(attempt.RESPONSE_CSV || '—') + '</td>' +
                '<td>' + esc(attempt.STARTED_AT || '—') + '</td>' +
                '<td>' + esc(attempt.FINISHED_AT || '—') + '</td>' +
                '<td class="text-end">' + reconcile + '</td>' +
                '</tr>';
        }).join('') : '<tr><td colspan="7" class="text-muted">No hi ha intents registrats.</td></tr>';

        const incidents = Array.isArray(data.incidents) ? data.incidents : [];
        document.getElementById('sif-aeat-incidents').innerHTML = incidents.length ? incidents.map(incident =>
            '<div class="incident" data-state="' + esc(incident.ESTAT) + '">' +
            '<div class="d-flex gap-2 align-items-center">' + badge(incident.ESTAT) +
            '<strong>' + esc(incident.TIPUS_INCIDENCIA) + '</strong></div>' +
            '<div class="small mt-1">' + esc(incident.DETAILS) + '</div>' +
            '<div class="small text-muted">' + esc(incident.CREATED_AT) + '</div></div>'
        ).join('') : '<p class="text-muted mb-0">No hi ha incidències AEAT per aquesta factura.</p>';

        detailCard.classList.remove('d-none');
        detailCard.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    async function reconcile(attemptUuid) {
        if (!currentQueueId) return;
        if (!window.confirm(
            'Aquesta acció NO tornarà a enviar el registre. ' +
            'Persistirà el resultat remot ja guardat per aquest intent. Vols continuar?'
        )) return;

        hideAlert();
        const response = await call({
            action: 'reconcile',
            queue_id: currentQueueId,
            attempt_uuid: attemptUuid,
            csrf_token: csrf
        });
        showAlert(
            'Reconciliació completada sense reenviament. Estat AEAT: ' + (response.aeat_status || '—'),
            'success'
        );
        await Promise.all([loadSummary(), loadQueue()]);
        await loadDetail(currentQueueId);
    }

    async function showPreflight() {
        hideAlert();
        const response = await call({action: 'preflight'});
        const data = response.data || {};
        const checks = data.checks || {};
        const rows = Object.entries(checks).map(([key, value]) =>
            '<tr><td><code>' + esc(key) + '</code></td><td>' +
            (value === true ? badge('ACCEPTED') : badge('REJECTED')) + '</td></tr>'
        ).join('');
        document.getElementById('sif-aeat-preflight-body').innerHTML =
            '<div class="mb-3">' + (data.ready === true
                ? '<div class="alert alert-success">Preflight preparat.</div>'
                : '<div class="alert alert-warning">Preflight no preparat. No s\'ha de forçar cap enviament.</div>') +
            '</div><div class="table-responsive"><table class="table table-sm"><tbody>' + rows + '</tbody></table></div>';
        bootstrap.Modal.getOrCreateInstance(document.getElementById('sif-aeat-preflight-modal')).show();
    }

    async function refreshAll() {
        hideAlert();
        try {
            await Promise.all([loadSummary(), loadQueue()]);
        } catch (error) {
            showAlert(error.message || 'No s’ha pogut carregar el panell AEAT');
        }
    }

    queueBody.addEventListener('click', event => {
        const button = event.target.closest('.sif-aeat-detail-btn');
        if (button) loadDetail(button.dataset.id).catch(error => showAlert(error.message));
    });

    document.getElementById('sif-aeat-attempts').addEventListener('click', event => {
        const button = event.target.closest('.sif-aeat-reconcile');
        if (button) reconcile(button.dataset.attempt).catch(error => showAlert(error.message));
    });

    document.getElementById('sif-aeat-status').addEventListener('change', () => {
        loadQueue().catch(error => showAlert(error.message));
    });
    document.getElementById('sif-aeat-refresh').addEventListener('click', refreshAll);
    document.getElementById('sif-aeat-preflight').addEventListener('click', () => {
        showPreflight().catch(error => showAlert(error.message));
    });
    document.getElementById('sif-aeat-close-detail').addEventListener('click', () => {
        detailCard.classList.add('d-none');
        currentQueueId = null;
    });

    refreshAll();
})();
