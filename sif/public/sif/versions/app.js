(() => {
    'use strict';

    const root = document.getElementById('version-app');
    if (!root) return;

    const csrf = root.dataset.csrf || '';
    const canManage = root.dataset.canManage === '1';
    const alertBox = document.getElementById('alert');
    const rows = document.getElementById('version-rows');
    const detail = document.getElementById('detail');
    let currentVersion = null;

    const esc = value => String(value ?? '').replace(/[&<>"']/g, ch => ({
        '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'
    }[ch]));

    function uid() {
        if (window.crypto && typeof window.crypto.randomUUID === 'function') {
            return window.crypto.randomUUID();
        }
        return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, ch => {
            const r = Math.random() * 16 | 0;
            const v = ch === 'x' ? r : (r & 0x3 | 0x8);
            return v.toString(16);
        });
    }

    async function call(payload) {
        const response = await fetch('./actions.php', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {'Content-Type': 'application/json', 'X-CSRF-Token': csrf},
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

    function shortHash(value) {
        const text = String(value || '');
        return text.length > 16 ? text.slice(0, 12) + '…' : text || '—';
    }

    function operationId(form) {
        if (!form.dataset.operationId) form.dataset.operationId = uid();
        return form.dataset.operationId;
    }

    function clearOperationId(form) {
        delete form.dataset.operationId;
    }

    function operationBase(action, reasonCode, form) {
        const id = operationId(form);
        return {
            action,
            uuid_version: currentVersion,
            request_id: id,
            correlation_id: id,
            idempotency_key: 'UC010|' + action.toUpperCase() + '|' + id,
            reason_code: reasonCode
        };
    }

    function handleMutationError(form, error) {
        // A 4xx is a definitive rejection and can use a new operation id after
        // the user corrects the input. For network/5xx failures we retain the
        // id because the server may already have committed the mutation.
        if (Number(error.status || 0) >= 400 && Number(error.status || 0) < 500) {
            clearOperationId(form);
        }
        showAlert(error.message || 'Operació SIF fallida');
    }

    async function loadRuntime() {
        const response = await call({action: 'runtime'});
        const r = response.runtime || {};
        const manifest = r.manifest || {};
        const fields = [
            ['Entorn', r.environment || '—'],
            ['Evidència completa', r.complete ? 'SÍ' : 'NO'],
            ['Git revision', r.git_revision || '—'],
            ['Artifact hash', r.artifact_hash || '—'],
            ['Config hash', r.config_hash || '—'],
            ['Versió BD', r.database_version || '—'],
            ['Schema verificat', r.schema_verified ? 'SÍ' : 'NO'],
            ['Fitxers manifest', (manifest.verified_count || 0) + '/' + (manifest.file_count || 0)]
        ];
        document.getElementById('runtime').innerHTML = fields.map(([label, value]) =>
            '<div><span class="label">' + esc(label) + '</span><div><code>' + esc(value) + '</code></div></div>'
        ).join('');
    }

    async function loadList() {
        const response = await call({action: 'list', limit: 100});
        const versions = Array.isArray(response.versions) ? response.versions : [];
        rows.innerHTML = versions.length ? versions.map(v =>
            '<tr>' +
            '<td><strong>' + esc(v.VERSION_CODE) + '</strong><br><code>' + esc(v.UUID_VERSION) + '</code></td>' +
            '<td>' + esc(v.STATUS) + '</td>' +
            '<td><code>' + esc(shortHash(v.GIT_REVISION)) + '</code></td>' +
            '<td>' + esc(v.DATABASE_VERSION) + '</td>' +
            '<td>' + esc(v.ACTIVATED_AT || '—') + '</td>' +
            '<td><button class="button small view" data-uuid="' + esc(v.UUID_VERSION) + '">Veure</button></td>' +
            '</tr>'
        ).join('') : '<tr><td colspan="6" class="empty-cell">No hi ha versions registrades.</td></tr>';
    }

    async function loadDetail(uuid) {
        const response = await call({action: 'view', uuid_version: uuid});
        const v = response.version || {};
        const d = response.declaration || null;
        const activations = Array.isArray(response.activations) ? response.activations : [];
        currentVersion = String(v.UUID_VERSION || uuid);

        document.getElementById('detail-code').textContent = v.VERSION_CODE || '';
        document.getElementById('detail-uuid').textContent = currentVersion;
        const fields = [
            ['Estat', v.STATUS || '—'],
            ['Git revision', v.GIT_REVISION || '—'],
            ['Artifact hash', v.ARTIFACT_HASH || '—'],
            ['Config hash', v.CONFIG_HASH || '—'],
            ['Versió BD', v.DATABASE_VERSION || '—'],
            ['Creat per', v.CREATED_BY || '—'],
            ['Creat', v.CREATED_AT || '—'],
            ['Activat', v.ACTIVATED_AT || '—']
        ];
        document.getElementById('detail-fields').innerHTML = fields.map(([label, value]) =>
            '<div><span class="label">' + esc(label) + '</span><div><code>' + esc(value) + '</code></div></div>'
        ).join('');

        document.getElementById('declaration').innerHTML = d ? [
            ['UUID', d.UUID_DECLARATION],
            ['Versió', d.DECLARATION_VERSION],
            ['Hash document', d.DOCUMENT_HASH],
            ['Storage key', d.STORAGE_KEY],
            ['Aprovat per', d.APPROVED_BY],
            ['Aprovat', d.APPROVED_AT],
            ['Estat', d.STATUS]
        ].map(([label, value]) =>
            '<div><span class="label">' + esc(label) + '</span><div><code>' + esc(value || '—') + '</code></div></div>'
        ).join('') : '<p class="muted">No hi ha cap declaració APPROVED vinculada.</p>';

        document.getElementById('activations').innerHTML = activations.length ? activations.map(a =>
            '<article class="timeline-item"><div><strong>' + esc(a.STATUS) + '</strong> · ' +
            esc(a.CREATED_AT) + '</div><div class="muted">' +
            esc(a.ENVIRONMENT) + ' · ' + esc(a.ACTOR_ID) + ' / ' + esc(a.ACTOR_ROLE) +
            '</div><code>' + esc(a.UUID_ACTIVATION) + '</code></article>'
        ).join('') : '<p class="muted">Encara no hi ha activacions registrades.</p>';

        const preflight = document.getElementById('preflight-result');
        if (preflight) preflight.textContent = '';
        detail.classList.remove('hidden');
        detail.scrollIntoView({behavior: 'smooth', block: 'start'});
    }

    async function refreshAll() {
        hideAlert();
        try {
            await Promise.all([loadRuntime(), loadList()]);
            if (currentVersion) await loadDetail(currentVersion);
        } catch (error) {
            showAlert(error.message);
        }
    }

    rows.addEventListener('click', event => {
        const button = event.target.closest('.view');
        if (button) loadDetail(button.dataset.uuid).catch(error => showAlert(error.message));
    });

    document.getElementById('refresh').addEventListener('click', refreshAll);
    document.getElementById('close-detail').addEventListener('click', () => {
        detail.classList.add('hidden');
        currentVersion = null;
    });

    if (canManage) {
        document.getElementById('register-form').addEventListener('submit', async event => {
            event.preventDefault();
            const form = event.currentTarget;
            const data = new FormData(form);
            const id = operationId(form);
            try {
                const response = await call({
                    action: 'register_current',
                    version_code: String(data.get('version_code') || '').trim(),
                    reason_code: String(data.get('reason_code') || '').trim(),
                    request_id: id,
                    correlation_id: id,
                    idempotency_key: 'UC010|REGISTER|' + id
                });
                clearOperationId(form);
                showAlert(response.reused ? 'Candidata reutilitzada.' : 'Candidata registrada des del runtime verificat.', 'success');
                await refreshAll();
            } catch (error) {
                handleMutationError(form, error);
            }
        });

        document.getElementById('declaration-form').addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentVersion) return;
            const form = event.currentTarget;
            const data = new FormData(form);
            const payload = operationBase('attach_declaration', String(data.get('reason_code') || 'DECLARATION_APPROVAL'), form);
            payload.declaration_version = String(data.get('declaration_version') || '').trim();
            payload.storage_key = String(data.get('storage_key') || '').trim();
            try {
                const response = await call(payload);
                clearOperationId(form);
                showAlert(response.reused ? 'Declaració ja vinculada.' : 'Declaració verificada i vinculada.', 'success');
                await loadDetail(currentVersion);
            } catch (error) {
                handleMutationError(form, error);
            }
        });

        document.getElementById('preflight-form').addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentVersion) return;
            const data = new FormData(event.currentTarget);
            const response = await call({
                action: 'preflight',
                uuid_version: currentVersion,
                backup_evidence_uuid: String(data.get('backup_evidence_uuid') || '').trim()
            });
            const p = response.preflight || {};
            const output = document.getElementById('preflight-result');
            output.textContent = p.ok ? 'GO tècnic: totes les comprovacions són correctes.' :
                'NO-GO: ' + (Array.isArray(p.failed) ? p.failed.join(', ') : 'gate incomplet');
            showAlert(p.ok ? 'Preflight UC-010 superat.' : 'Preflight UC-010 no superat.', p.ok ? 'success' : 'error');
        });

        document.getElementById('activate-form').addEventListener('submit', async event => {
            event.preventDefault();
            if (!currentVersion) return;
            if (!window.confirm('Confirmes que vols registrar com a activa aquesta versió ja desplegada i verificada?')) return;
            const form = event.currentTarget;
            const data = new FormData(form);
            const payload = operationBase('activate', String(data.get('reason_code') || 'APPROVED_RELEASE'), form);
            payload.backup_evidence_uuid = String(data.get('backup_evidence_uuid') || '').trim();
            try {
                const response = await call(payload);
                clearOperationId(form);
                showAlert(response.reused ? 'Activació reutilitzada idempotentment.' : 'Activació registrada.', 'success');
                await refreshAll();
            } catch (error) {
                handleMutationError(form, error);
            }
        });
    }

    document.getElementById('logout').addEventListener('click', async () => {
        try { await call({action: 'logout'}); } catch (_) {}
        window.location.href = 'https://intranet.prisma.cat/sif-verifactu.php';
    });

    refreshAll();
})();
