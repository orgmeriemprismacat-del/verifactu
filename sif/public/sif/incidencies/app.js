(() => {
    'use strict';

    const app = document.getElementById('incident-app');
    if (!app) return;

    const csrf = app.dataset.csrf || '';
    const canManage = app.dataset.canManage === '1';
    const endpoint = './actions.php';
    const rows = document.getElementById('incident-rows');
    const summaryBox = document.getElementById('summary');
    const detail = document.getElementById('detail');
    const alertBox = document.getElementById('alert');
    let currentIncidentId = null;

    const esc = value => {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    };

    const uid = () => {
        if (!window.crypto || typeof crypto.getRandomValues !== 'function') {
            throw new Error('Aquest navegador no disposa d’un generador criptogràfic segur.');
        }
        if (typeof crypto.randomUUID === 'function') {
            return crypto.randomUUID();
        }

        const bytes = new Uint8Array(16);
        crypto.getRandomValues(bytes);
        bytes[6] = (bytes[6] & 0x0f) | 0x40;
        bytes[8] = (bytes[8] & 0x3f) | 0x80;
        const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0')).join('');
        return [
            hex.slice(0, 8),
            hex.slice(8, 12),
            hex.slice(12, 16),
            hex.slice(16, 20),
            hex.slice(20)
        ].join('-');
    };

    const badge = (value, kind = 'status') => {
        const text = String(value || '—').toUpperCase();
        const cls = kind === 'severity'
            ? ({CRITICAL: 'critical', HIGH: 'high', MEDIUM: 'medium', LOW: 'low'}[text] || 'neutral')
            : ({OPEN: 'open', IN_PROGRESS: 'progress', RESOLVED: 'resolved', DISMISSED: 'dismissed'}[text] || 'neutral');
        return '<span class="badge ' + cls + '">' + esc(text) + '</span>';
    };

    async function call(payload) {
        const response = await fetch(endpoint, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': csrf
            },
            body: JSON.stringify(payload)
        });
        const json = await response.json().catch(() => ({ok: false, error: 'Resposta JSON no vàlida'}));
        if (!response.ok || json.ok === false) {
            const error = new Error(json.error || 'Operació SIF fallida');
            error.status = response.status;
            throw error;
        }
        return json;
    }

    function showAlert(message, type = 'error') {
        alertBox.textContent = message;
        alertBox.className = 'alert ' + type;
    }

    function hideAlert() {
        alertBox.textContent = '';
        alertBox.className = 'alert hidden';
    }

    async function loadSummary() {
        const response = await call({action: 'summary'});
        const data = response.summary || {};
        const statuses = data.by_status || {};
        const metrics = [
            ['Obertes', data.open_total || 0],
            ['Crítiques obertes', data.critical_open || 0],
            ['OPEN', statuses.OPEN || 0],
            ['IN_PROGRESS', statuses.IN_PROGRESS || 0],
            ['RESOLVED', statuses.RESOLVED || 0],
            ['DISMISSED', statuses.DISMISSED || 0]
        ];
        summaryBox.innerHTML = metrics.map(([label, value]) =>
            '<article class="metric"><span>' + esc(label) + '</span><strong>' + esc(value) + '</strong></article>'
        ).join('');
    }

    async function loadList() {
        const filters = {
            status: document.getElementById('filter-status').value || null,
            severity: document.getElementById('filter-severity').value || null,
            type: document.getElementById('filter-type').value.trim() || null
        };
        const response = await call({action: 'list', filters, limit: 100});
        const incidents = Array.isArray(response.incidents) ? response.incidents : [];
        rows.innerHTML = incidents.length ? incidents.map(item => {
            const resource = item.RESOURCE_TYPE
                ? esc(item.RESOURCE_TYPE) + '<br><code>' + esc(item.RESOURCE_ID || '—') + '</code>'
                : (item.UUID_FACTURA ? 'INVOICE<br><code>' + esc(item.UUID_FACTURA) + '</code>' : '—');
            return '<tr>' +
                '<td><strong>#' + esc(item.ID) + '</strong></td>' +
                '<td>' + badge(item.SEVERITY, 'severity') + '</td>' +
                '<td>' + badge(item.ESTAT) + '</td>' +
                '<td>' + esc(item.TIPUS_INCIDENCIA) + '</td>' +
                '<td>' + resource + '</td>' +
                '<td>' + esc(item.ASSIGNED_TO || '—') + '</td>' +
                '<td>' + esc(item.UPDATED_AT || item.CREATED_AT || '—') + '</td>' +
                '<td><button class="button small view" data-id="' + esc(item.ID) + '">Veure</button></td>' +
                '</tr>';
        }).join('') : '<tr><td colspan="8" class="empty-cell">No hi ha incidències amb aquests filtres.</td></tr>';
    }

    async function loadDetail(id) {
        const response = await call({action: 'view', incident_id: Number(id)});
        const incident = response.incident || {};
        const actions = Array.isArray(response.actions) ? response.actions : [];
        currentIncidentId = Number(id);

        document.getElementById('detail-id').textContent = '#' + currentIncidentId;
        document.getElementById('detail-meta').textContent =
            (incident.UUID_INCIDENT || '') + ' · ' + (incident.CORRELATION_ID || 'sense correlació');

        const fields = [
            ['Estat', badge(incident.ESTAT), true],
            ['Prioritat', badge(incident.SEVERITY, 'severity'), true],
            ['Tipus', incident.TIPUS_INCIDENCIA],
            ['Responsable', incident.ASSIGNED_TO || '—'],
            ['Origen', [incident.SOURCE_TYPE, incident.SOURCE_ID].filter(Boolean).join(' / ') || '—'],
            ['Recurs', [incident.RESOURCE_TYPE, incident.RESOURCE_ID].filter(Boolean).join(' / ') || incident.UUID_FACTURA || '—'],
            ['Creat', incident.CREATED_AT || '—'],
            ['Actualitzat', incident.UPDATED_AT || '—'],
            ['Detall', incident.DETAILS || '—'],
            ['Criteri tancament', incident.CLOSURE_CRITERIA || '—'],
            ['Resolució', incident.RESOLUTION_NOTES || '—']
        ];
        document.getElementById('detail-fields').innerHTML = fields.map(([label, value, html]) =>
            '<div><span class="label">' + esc(label) + '</span><div>' + (html ? value : esc(value)) + '</div></div>'
        ).join('');

        document.getElementById('timeline').innerHTML = actions.length ? actions.map(action => {
            let evidence = '—';
            if (action.EVIDENCE_JSON) {
                try { evidence = JSON.stringify(JSON.parse(action.EVIDENCE_JSON)); } catch (_) { evidence = action.EVIDENCE_JSON; }
            }
            return '<article class="timeline-item">' +
                '<div><strong>' + esc(action.ACTION_TYPE) + '</strong> ' + badge(action.NEW_STATUS) + '</div>' +
                '<div class="muted">' + esc(action.CREATED_AT) + ' · ' + esc(action.ACTOR_ID || 'sistema') + '</div>' +
                '<div>' + esc(action.DETAILS || '') + '</div>' +
                '<code>' + esc(evidence) + '</code>' +
                '</article>';
        }).join('') : '<p class="muted">Encara no hi ha accions registrades.</p>';

        if (canManage) {
            const assignee = document.querySelector('#assign-form [name="assignee_id"]');
            const severity = document.querySelector('#assign-form [name="severity"]');
            if (assignee) assignee.value = incident.ASSIGNED_TO || '';
            if (severity && incident.SEVERITY) severity.value = incident.SEVERITY;
        }

        detail.classList.remove('hidden');
        detail.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    function operationBase(action) {
        const operationId = uid();
        return {
            action,
            incident_id: currentIncidentId,
            correlation_id: operationId,
            idempotency_key: 'PANEL|' + action.toUpperCase() + '|' + currentIncidentId + '|' + operationId
        };
    }

    async function refreshAll() {
        hideAlert();
        try {
            await Promise.all([loadSummary(), loadList()]);
            if (currentIncidentId) await loadDetail(currentIncidentId);
        } catch (error) {
            showAlert(error.message);
        }
    }

    rows.addEventListener('click', event => {
        const button = event.target.closest('.view');
        if (button) loadDetail(button.dataset.id).catch(error => showAlert(error.message));
    });

    ['filter-status', 'filter-severity'].forEach(id => {
        document.getElementById(id).addEventListener('change', () => loadList().catch(error => showAlert(error.message)));
    });
    document.getElementById('filter-type').addEventListener('change', () => loadList().catch(error => showAlert(error.message)));
    document.getElementById('refresh').addEventListener('click', refreshAll);
    document.getElementById('close-detail').addEventListener('click', () => {
        detail.classList.add('hidden');
        currentIncidentId = null;
    });

    if (canManage) {
        document.getElementById('assign-form').addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentIncidentId) return;
            const data = new FormData(event.currentTarget);
            await call({...operationBase('assign'), assignee_id: data.get('assignee_id'), severity: data.get('severity'),
                reason_code: data.get('reason_code'), details: data.get('details') || null});
            showAlert('Assignació registrada.', 'success');
            await refreshAll();
        });

        document.getElementById('evidence-form').addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentIncidentId) return;
            const data = new FormData(event.currentTarget);
            await call({...operationBase('evidence'), reason_code: data.get('reason_code'),
                details: data.get('details') || null, evidence: {reference: String(data.get('evidence_ref') || '')}});
            showAlert('Evidència registrada.', 'success');
            event.currentTarget.reset();
            await refreshAll();
        });

        document.getElementById('close-form').addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentIncidentId) return;
            const data = new FormData(event.currentTarget);
            const action = String(data.get('close_action') || 'resolve');
            const evidenceRef = String(data.get('evidence_ref') || '').trim();
            if (action === 'resolve' && !evidenceRef) {
                showAlert('RESOLVED exigeix evidència de verificació.');
                return;
            }
            await call({...operationBase(action), reason_code: data.get('reason_code'),
                closure_criteria: data.get('closure_criteria'), resolution_notes: data.get('resolution_notes'),
                evidence: evidenceRef ? {reference: evidenceRef} : null});
            showAlert('Expedient tancat.', 'success');
            await refreshAll();
        });

        document.getElementById('reopen-form').addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentIncidentId) return;
            const data = new FormData(event.currentTarget);
            await call({...operationBase('reopen'), reason_code: data.get('reason_code'), details: data.get('details') || null});
            showAlert('Incidència reoberta.', 'success');
            await refreshAll();
        });
    }

    document.getElementById('logout').addEventListener('click', async () => {
        try { await call({action: 'logout'}); } catch (_) {}
        window.location.href = 'https://intranet.prisma.cat/sif-verifactu.php';
    });

    refreshAll();
})();
