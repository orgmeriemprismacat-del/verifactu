(() => {
    'use strict';

    const app = document.getElementById('sif-verifactu-app');
    if (!app) return;

    const csrf = document.querySelector('.mainpanel')?.dataset.csrf || '';
    const queryEndpoint = 'https://intranet.prisma.cat/ajax/sif/sifIncidents.php';
    const launchEndpoint = 'https://intranet.prisma.cat/ajax/sif/sifPanelLaunch.php';
    const cacheKey = 'sif-verifactu:last-valid-summary:v1';
    const alertBox = document.getElementById('sif-verifactu-alert');
    const summary = document.getElementById('sif-verifactu-summary');
    const rows = document.getElementById('sif-verifactu-incidents');

    const esc = value => {
        const div = document.createElement('div');
        div.textContent = value == null ? '' : String(value);
        return div.innerHTML;
    };

    const badge = (value, severity = false) => {
        const text = String(value || '—').toUpperCase();
        const cls = severity
            ? ({CRITICAL:'text-bg-danger',HIGH:'text-bg-warning',MEDIUM:'text-bg-primary',LOW:'text-bg-success'}[text] || 'text-bg-secondary')
            : ({OPEN:'text-bg-danger',IN_PROGRESS:'text-bg-warning',RESOLVED:'text-bg-success',DISMISSED:'text-bg-secondary'}[text] || 'text-bg-secondary');
        return '<span class="badge ' + cls + '">' + esc(text) + '</span>';
    };

    async function post(url, payload) {
        const response = await fetch(url, {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({...payload, csrf_token: csrf})
        });
        const json = await response.json().catch(() => ({ok:false,error:'Resposta JSON no vàlida'}));
        if (!response.ok || json.ok === false) throw new Error(json.error || 'Operació SIF fallida');
        return json;
    }

    function showAlert(message, type='danger') {
        alertBox.className = 'alert alert-' + type;
        alertBox.textContent = message;
        alertBox.classList.remove('d-none');
    }

    function hideAlert() {
        alertBox.classList.add('d-none');
        alertBox.textContent = '';
    }

    function metricsFrom(data) {
        const statuses = data.by_status || {};
        return [
            ['Incidències obertes', data.open_total ?? '—'],
            ['Crítiques obertes', data.critical_open ?? '—'],
            ['En investigació', statuses.IN_PROGRESS ?? '—'],
            ['Resoltes', statuses.RESOLVED ?? '—'],
            ['Descartades', statuses.DISMISSED ?? '—'],
            ['Total', data.total ?? '—']
        ];
    }

    function renderSummary(data, stale = false, cachedAt = null) {
        summary.innerHTML = metricsFrom(data).map(([label,value]) =>
            '<div class="col-6 col-md-4 col-xl-2"><div class="card sif-verifactu-metric shadow-sm h-100' +
            (stale ? ' border-warning' : '') + '"><div class="card-body py-2">' +
            '<div class="small text-muted">' + esc(label) + '</div><div class="value">' + esc(value) + '</div>' +
            (stale ? '<div class="small text-warning-emphasis">darrera dada validada</div>' : '') +
            '</div></div></div>'
        ).join('');

        if (stale && cachedAt) {
            showAlert(
                'SIF indisponible. Es mostra només l’últim resum validat d’aquesta sessió (' +
                new Date(cachedAt).toLocaleString('ca-ES') +
                '). El llistat d’incidències no es considera actual.',
                'warning'
            );
        }
    }

    function saveValidatedSummary(data) {
        const safe = {
            total: Number(data.total || 0),
            open_total: Number(data.open_total || 0),
            critical_open: Number(data.critical_open || 0),
            by_status: {
                OPEN: Number(data.by_status?.OPEN || 0),
                IN_PROGRESS: Number(data.by_status?.IN_PROGRESS || 0),
                RESOLVED: Number(data.by_status?.RESOLVED || 0),
                DISMISSED: Number(data.by_status?.DISMISSED || 0)
            },
            validated_at: new Date().toISOString()
        };
        sessionStorage.setItem(cacheKey, JSON.stringify(safe));
    }

    function readValidatedSummary() {
        try {
            const cached = JSON.parse(sessionStorage.getItem(cacheKey) || 'null');
            if (!cached || typeof cached !== 'object' || !cached.validated_at) return null;
            return cached;
        } catch (_) {
            sessionStorage.removeItem(cacheKey);
            return null;
        }
    }

    function renderUnavailableWithoutCache(errorMessage) {
        renderSummary({}, false, null);
        rows.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">SIF indisponible. No es pot afirmar que hi hagi 0 incidències.</td></tr>';
        showAlert('SIF indisponible: ' + errorMessage + '. No hi ha cap resum validat en aquesta sessió.', 'warning');
    }

    function renderIncidents(incidents) {
        rows.innerHTML = incidents.length ? incidents.map(item =>
            '<tr><td>#' + esc(item.ID) + '</td><td>' + badge(item.SEVERITY,true) + '</td><td>' + badge(item.ESTAT) +
            '</td><td>' + esc(item.TIPUS_INCIDENCIA) + '</td><td>' + esc(item.ASSIGNED_TO || '—') +
            '</td><td>' + esc(item.UPDATED_AT || item.CREATED_AT || '—') + '</td></tr>'
        ).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No hi ha incidències.</td></tr>';
    }

    async function load() {
        hideAlert();

        let summaryResponse;
        try {
            summaryResponse = await post(queryEndpoint, {action:'summary'});
            const data = summaryResponse.summary || {};
            renderSummary(data);
            saveValidatedSummary(data);
        } catch (error) {
            const cached = readValidatedSummary();
            if (cached) {
                renderSummary(cached, true, cached.validated_at);
                rows.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Llistat no disponible mentre el SIF és inaccessible.</td></tr>';
            } else {
                renderUnavailableWithoutCache(error.message);
            }
            return;
        }

        try {
            const list = await post(queryEndpoint, {action:'list', filters:{}, limit:8});
            renderIncidents(Array.isArray(list.incidents) ? list.incidents : []);
        } catch (error) {
            rows.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Llistat temporalment indisponible. El resum anterior sí que ha estat validat.</td></tr>';
            showAlert('El resum SIF és actual, però no s’ha pogut carregar el llistat: ' + error.message, 'warning');
        }
    }

    async function launchPanel() {
        hideAlert();
        const launch = await post(launchEndpoint, {});
        if (!launch.url || !launch.fields) throw new Error('Resposta d\'accés al panell incompleta');

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = launch.url;
        form.style.display = 'none';
        Object.entries(launch.fields).forEach(([name,value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = name;
            input.value = String(value);
            form.appendChild(input);
        });
        document.body.appendChild(form);
        form.submit();
    }

    document.getElementById('sif-verifactu-refresh').addEventListener('click', () => {
        load().catch(error => renderUnavailableWithoutCache(error.message));
    });
    document.getElementById('sif-open-incidents').addEventListener('click', () => {
        launchPanel().catch(error => showAlert(error.message));
    });

    load().catch(error => renderUnavailableWithoutCache(error.message));
})();
