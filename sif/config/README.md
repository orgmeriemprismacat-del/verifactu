# Configuració SIF

## Abast

Aquest README és el catàleg de configuració versionada del SIF. Les **variables i contractes** es documenten aquí, però els **valors secrets o específics d'entorn no es guarden al repositori**.

Famílies que s'han de mantenir documentades a mesura que creix el projecte:

- connexions BD;
- HMAC i APIs internes;
- Redsys;
- AEAT/certificat;
- documents;
- rols i permisos;
- feature flags;
- cues/workers;
- integracions legacy;
- configuració específica de UC.

Una variable documentada no acredita que estigui configurada en preproducció o producció.

## Consulta interna de factures — UC-007

La consulta HTTP de factures és **fail-closed**. Sense aquestes variables no s'ha d'activar el pont de la intranet.

### SIF / pay.prisma.cat

- `SIF_INTERNAL_API_KEY_ID`: identificador de la clau HMAC.
- `SIF_INTERNAL_API_SECRET`: secret llarg aleatori compartit només entre els dos servidors PHP.
- `SIF_INTERNAL_API_MAX_SKEW`: desviació màxima de rellotge en segons; default 300.
- `SIF_INTERNAL_API_SIGNED_PATH`: path canònic que entra a la signatura; default `/api/factures/query.php`.
- `SIF_INTERNAL_DOCUMENT_SIGNED_PATH`: path canònic de la descàrrega; default `/api/documents/download.php`.
- `SIF_DOCUMENT_ROOT`: directori privat real dels documents; ha d'estar fora del webroot.
- `SIF_DOCUMENT_MAX_BYTES`: mida màxima servida; default 20 MiB.
- `SIF_INVOICE_FULL_READ_ROLES`: rols interns, separats per comes, amb projecció completa.
- `SIF_INVOICE_MINIMAL_READ_ROLES`: rols interns, separats per comes, amb projecció mínima.
- `SIF_INVOICE_QUERY_MAX_RESULTS`: màxim de resultats per cerca; default 50, límit absolut 100.

**No hi ha rols per defecte:** si les llistes són buides, la consulta queda denegada.

### Intranet

- `SIF_UC007_QUERY_ENABLED=1`: activa el pont UC-007 de consulta SIF a la intranet. Per defecte, absent/0, `sifFactures.php` respon `FEATURE_DISABLED` i la pantalla conserva el fallback llegat.
- `SIF_INTERNAL_API_URL`: URL server-to-server de `sif/public/api/factures/query.php`.
- `SIF_INTERNAL_API_KEY_ID`: mateix key id.
- `SIF_INTERNAL_API_SECRET`: mateix secret.
- `SIF_INTERNAL_API_SIGNED_PATH`: mateix path canònic.
- `SIF_UC080_DOCUMENT_ENABLED=1`: activa el proxy segur de descàrrega documental. Per defecte queda desactivat.
- `SIF_INTERNAL_DOCUMENT_API_URL`: URL server-to-server de `sif/public/api/documents/download.php`.
- `SIF_INTERNAL_DOCUMENT_SIGNED_PATH`: mateix path canònic de document; default `/api/documents/download.php`.

El secret no s'envia al navegador. `ajax/alumnes/sifFactures.php` refresca la sessió des de BD, extreu actor/rol al servidor i `SifInternalApiClient` crea la signatura HMAC.

- `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1`: quan UC-007 està operatiu, impedeix que F05/F06/F07 i AL-17 tornin al generador/edició llegada per una factura que ja existeix al SIF. Si el SIF no es pot consultar, el guard falla tancat amb 503.
- `INTRANET_ALLOWED_ORIGINS`: orígens permesos, separats per `;` o `,`, per a operacions llegades sensibles. Default actual: `https://intranet.prisma.cat`.

## Rotació

Per rotar la clau sense exposar-la al repositori: actualitzar variables d'entorn als dos servidors dins la mateixa finestra de desplegament. No guardar secrets en PHP, Git, SQL de negoci, Trello ni documentació.


## Ordre recomanat d'activació

1. Configurar secrets HMAC i rols al SIF, mantenint `SIF_UC007_QUERY_ENABLED=0` a la intranet.
2. Verificar connectivitat server-to-server i migració `internal_api_request` en preproducció.
3. Activar `SIF_UC007_QUERY_ENABLED=1` i validar consulta read-only.
4. Activar `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1` per evitar regeneració/edició llegada de factures SIF.
5. Configurar `SIF_DOCUMENT_ROOT` privat i, només quan els bytes/hash siguin correctes, activar `SIF_UC080_DOCUMENT_ENABLED=1`.
6. Mantenir secrets i paths físics fora del repositori i fora del navegador.


## Canvi de curs — UC-071

La previsualització del canvi de curs és server-to-server, signada amb HMAC i queda desactivada fins que els flags s'activen explícitament.

### SIF / pay.prisma.cat

- `SIF_COURSE_CHANGE_PREVIEW_ROLES`: rols autoritzats. Si no s'informa, reutilitza `SIF_INVOICE_FULL_READ_ROLES`; ambdues buides impliquen denegació.
- `SIF_INTERNAL_COURSE_CHANGE_SIGNED_PATH`: path canònic HMAC; default `/api/course-changes/preview.php`.

### Intranet

- `SIF_COURSE_CHANGE_UI_ENABLED=1`: activa el mòdul JS UC-071.
- `SIF_COURSE_CHANGE_API_URL`: URL HTTPS server-to-server del preview SIF.
- `SIF_INTERNAL_COURSE_CHANGE_SIGNED_PATH`: mateix path canònic configurat al SIF.
- `SIF_COURSE_CHANGE_PREVIEW_ENFORCED=1`: abans d'executar el canvi llegat, recalcula el preu estàndard al servidor, rellegeix origen/pagat i torna a validar la decisió al SIF.

El preview no emet rectificatives, no registra cobraments i no executa devolucions. La confirmació final continua sotmesa a POST, CSRF, same-origin, permisos i guards de lifecycle de l'endpoint actual.


## Redsys PACK — UC-015

El checkout PACK és fail-closed i no reutilitza imports, titular, correu ni endpoint Redsys aportats pel navegador com a dades autoritatives.

### Web / ecommerce

- `SIF_REDSYS_INTENT_API_URL`: endpoint HTTPS server-to-server de creació d'intencions Redsys.
- `SIF_INTERNAL_REDSYS_INTENT_SIGNED_PATH`: path canònic HMAC de la creació d'intenció.
- `SIF_INTERNAL_API_KEY_ID` / `SIF_INTERNAL_API_SECRET`: credencial HMAC compartida.
- `SIF_REDSYS_INTENT_ACTOR_ID`: actor tècnic del checkout.
- `SIF_REDSYS_INTENT_ACTOR_ROLES`: rols signats; no hi ha fallback permissiu.
- `SIF_REDSYS_CALLBACK_URL`: MerchantURL HTTPS del callback SIF.
- `SIF_REDSYS_PAYMENT_URL`: URL del formulari Redsys. Només s'accepten:
  - producció: `https://sis.redsys.es/sis/realizarPago`;
  - preproducció/sandbox: `https://sis-t.redsys.es:25443/sis/realizarPago`.
- `REDSYS_MERCHANT_CODE`: FUC.
- `REDSYS_TERMINAL`: terminal.
- `SIF_REDSYS_MERCHANT_KEY`: secret Redsys; mai al repositori.

`preflight-redsys-pack.php` comprova URL de callback, API d'intenció, endpoint de pagament Redsys, rols, secrets, connectivitat i taules necessàries. L'endpoint de pagament només és vàlid si coincideix exactament amb una de les dues URLs admeses.

### Evidència

- `php sif/scripts/verify-redsys-pack-preproduction.php <DS_ORDER>`: orquestració de preflight/preview i execució controlada.
- `php sif/scripts/verify-redsys-pack-evidence.php <DS_ORDER>`: verificació read-only de la cadena persistent completa UC-015 després de l'execució.
- En producció el verificador d'evidència queda bloquejat per defecte; només es pot habilitar explícitament amb `SIF_UC015_EVIDENCE_ALLOW_PRODUCTION=1`.
- La sortida d'evidència no inclou PII, signatures ni snapshots comercials complets.
