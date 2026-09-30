(() => {
    'use strict';

    const app = document.getElementById('sif-verifactu-app');
    if (!app) return;

    const csrf = document.querySelector('.mainpanel')?.dataset.csrf || '';
    const queryEndpoint = 'https://intranet.prisma.cat/ajax/sif/sifIncidents.php';
    const launchEndpoint = 'https://intranet.prisma.cat/ajax/sif/sifPanelLaunch.php';
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

    async function load() {
        hideAlert();
        const [sum, list] = await Promise.all([
            post(queryEndpoint, {action:'summary'}),
            post(queryEndpoint, {action:'list', filters:{}, limit:8})
        ]);

        const data = sum.summary || {};
        const statuses = data.by_status || {};
        const metrics = [
            ['Incidències obertes', data.open_total || 0],
            ['Crítiques obertes', data.critical_open || 0],
            ['En investigació', statuses.IN_PROGRESS || 0],
            ['Resoltes', statuses.RESOLVED || 0],
            ['Descartades', statuses.DISMISSED || 0],
            ['Total', data.total || 0]
        ];
        summary.innerHTML = metrics.map(([label,value]) =>
            '<div class="col-6 col-md-4 col-xl-2"><div class="card sif-verifactu-metric shadow-sm h-100"><div class="card-body py-2">' +
            '<div class="small text-muted">' + esc(label) + '</div><div class="value">' + esc(value) + '</div></div></div></div>'
        ).join('');

        const incidents = Array.isArray(list.incidents) ? list.incidents : [];
        rows.innerHTML = incidents.length ? incidents.map(item =>
            '<tr><td>#' + esc(item.ID) + '</td><td>' + badge(item.SEVERITY,true) + '</td><td>' + badge(item.ESTAT) +
            '</td><td>' + esc(item.TIPUS_INCIDENCIA) + '</td><td>' + esc(item.ASSIGNED_TO || '—') +
            '</td><td>' + esc(item.UPDATED_AT || item.CREATED_AT || '—') + '</td></tr>'
        ).join('') : '<tr><td colspan="6" class="text-center text-muted py-4">No hi ha incidències.</td></tr>';
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
        load().catch(error => showAlert(error.message));
    });
    document.getElementById('sif-open-incidents').addEventListener('click', () => {
        launchPanel().catch(error => showAlert(error.message));
    });

    load().catch(error => showAlert(error.message));
})();
