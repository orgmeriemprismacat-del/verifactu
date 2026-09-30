# UC-008 · Desplegament del panell d'incidències

**Estat:** codi implementat; configuració productiva i desplegament encara s'han de verificar.

## 1. Superfícies

- Panell oficial SIF: `https://pay.prisma.cat/sif/incidencies/`
- API interna: `https://pay.prisma.cat/api/incidents/manage.php`
- Resum intranet: `https://intranet.prisma.cat/sif-verifactu.php`
- Pont read-only intranet: `ajax/sif/sifIncidents.php`
- Handoff signat intranet → SIF: `ajax/sif/sifPanelLaunch.php`

La intranet no resol incidències. El botó «Obrir incidències SIF» genera un POST HMAC curt i d'un sol ús. El SIF valida signatura, timestamp i replay, crea una sessió pròpia i aplica CSRF a totes les accions del panell.

## 2. Variables SIF obligatòries

```text
SIF_INCIDENT_READ_ROLES=AUDITOR_FISCAL,AEAT_READONLY,SIF_ADMIN,RESPONSABLE_TECNICA
SIF_INCIDENT_MANAGE_ROLES=SIF_ADMIN,RESPONSABLE_TECNICA
SIF_INCIDENT_QUERY_MAX_RESULTS=100

SIF_PANEL_LAUNCH_KEY_ID=<id dedicat>
SIF_PANEL_LAUNCH_SECRET=<secret aleatori llarg>
SIF_PANEL_INCIDENTS_PATH=/sif/incidencies/
SIF_PANEL_LAUNCH_MAX_SKEW=120
SIF_PANEL_SESSION_NAME=SIFPANELSESSID

SIF_INTERNAL_INCIDENT_SIGNED_PATH=/api/incidents/manage.php
```

Els rols reals s'han d'ajustar als rols existents de PrisMa. Si les llistes de rols queden buides, el servei falla tancat.

## 3. Variables intranet obligatòries

```text
SIF_INTERNAL_INCIDENTS_URL=https://pay.prisma.cat/api/incidents/manage.php
SIF_INTERNAL_INCIDENT_SIGNED_PATH=/api/incidents/manage.php
SIF_INTERNAL_API_KEY_ID=<id API interna>
SIF_INTERNAL_API_SECRET=<secret API interna>

SIF_PANEL_INCIDENTS_URL=https://pay.prisma.cat/sif/incidencies/
SIF_PANEL_INCIDENTS_PATH=/sif/incidencies/
SIF_PANEL_LAUNCH_KEY_ID=<mateix id de launch configurat al SIF>
SIF_PANEL_LAUNCH_SECRET=<mateix secret de launch configurat al SIF>
```

`SifPanelLaunchToken` admet temporalment fallback a `SIF_INTERNAL_API_KEY_ID/SECRET`, però en producció és preferible un secret específic del handoff del panell.

## 4. Requisits web

- HTTPS obligatori a `pay.prisma.cat`.
- `/sif/incidencies/` ha d'apuntar a `sif/public/sif/incidencies/index.php`.
- `/sif/incidencies/actions.php`, `app.js` i `style.css` han de quedar accessibles sota el mateix origen.
- La cookie del panell és `HttpOnly`, `SameSite=Strict` i `Secure` quan la petició és HTTPS.
- No exposar cap secret HMAC a JavaScript.
- No convertir l'API interna en una API browser directa.

## 5. Menú intranet

El menú lateral actual es genera des de BD, no des d'un catàleg PHP versionat. Cal afegir a la configuració de menú de la intranet una entrada visible només per rols autoritzats:

```text
Facturació
  VERI*FACTU -> /sif-verifactu.php
```

Aquesta alta de menú és una operació de configuració de BD i no s'ha inventat dins del repositori. Com a accés versionat, `sif-registres-aeat.php` ja inclou un enllaç a `sif-verifactu.php`.

## 6. Preflight executable abans de navegador

Executar al servidor/preproducció, amb les variables reals carregades al procés:

```bash
php sif/scripts/preflight-incidents-panel.php
```

El resultat ha de retornar `"ok": true`. El script és **read-only** respecte del domini: no emet factures, no registra pagaments i no resol incidències. Comprova:

- entorn `test` o `preproduction`;
- extensions PHP necessàries;
- rols de lectura i gestió;
- que tots els rols gestors tinguin també lectura;
- secrets HMAC de com a mínim 32 bytes/caràcters;
- paths signats exactes;
- skew del handoff entre 30 i 300 segons;
- fitxers del panell/API;
- esquema i taules `errors_verifactu`, `sif_incident_action` i `internal_api_request`.

Després executar el control global:

```bash
php sif/scripts/go-no-go-preproduction.php
```

El go/no-go global incorpora també la presència/configuració bàsica del circuit UC-008. Un `NO-GO` bloqueja la validació E2E fins resoldre els checks fallits. Cap dels dos scripts autoritza per si mateix el pas a producció.

## 6. Proves de desplegament

1. Usuari sense rol de lectura → 403 al resum i al panell.
2. Auditor → pot llistar/veure, no pot assignar ni tancar.
3. Responsable tècnica → pot assignar, afegir evidència, resoldre, descartar i reobrir.
4. Reutilitzar el mateix handoff → 409 per replay.
5. Handoff caducat → 401.
6. Signatura modificada → 401.
7. POST d'acció sense CSRF → 403.
8. Obrir directament `/sif/incidencies/` sense sessió → no mostra dades i remet a la intranet.
9. `RESOLVED` sense evidència → 422.
10. La intranet intenta una mutació a `sifIncidents.php` → 403.
11. Validar que `SIF_PANEL_LAUNCH_SECRET` no apareix en HTML, JS, logs o respostes.
12. Verificar que la sessió SIF es destrueix amb «Sortir».

## 7. Evidència necessària per tancar

- URL productiva accessible per un rol autoritzat.
- Captura/resposta del resum intranet.
- Captura del llistat i detall SIF.
- Prova auditor read-only.
- Prova responsable amb assignació + evidència + resolució.
- Log de replay bloquejat.
- Resultat GitHub Actions de la suite SIF.
- Resultat dels checks de la còpia de la intranet.
