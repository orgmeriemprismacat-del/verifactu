# Configuració SIF

## Consulta interna de factures — UC-007

La consulta HTTP de factures és **fail-closed**. Sense aquestes variables no s'ha d'activar el pont de la intranet.

### SIF / pay.prisma.cat

- `SIF_INTERNAL_API_KEY_ID`: identificador de la clau HMAC.
- `SIF_INTERNAL_API_SECRET`: secret llarg aleatori compartit només entre els dos servidors PHP.
- `SIF_INTERNAL_API_MAX_SKEW`: desviació màxima de rellotge en segons; default 300.
- `SIF_INTERNAL_API_SIGNED_PATH`: path canònic que entra a la signatura; default `/api/factures/query.php`.
- `SIF_INVOICE_FULL_READ_ROLES`: rols interns, separats per comes, amb projecció completa.
- `SIF_INVOICE_MINIMAL_READ_ROLES`: rols interns, separats per comes, amb projecció mínima.
- `SIF_INVOICE_QUERY_MAX_RESULTS`: màxim de resultats per cerca; default 50, límit absolut 100.

**No hi ha rols per defecte:** si les llistes són buides, la consulta queda denegada.

### Intranet

- `SIF_INVOICE_QUERY_UI_ENABLED=1`: activa el mòdul JS UC-007 a `/alumnes/factura/`. Per defecte, absent/0, la pantalla continua 100% llegada.
- `SIF_INTERNAL_API_URL`: URL server-to-server de `sif/public/api/factures/query.php`.
- `SIF_INTERNAL_API_KEY_ID`: mateix key id.
- `SIF_INTERNAL_API_SECRET`: mateix secret.
- `SIF_INTERNAL_API_SIGNED_PATH`: mateix path canònic.
- `SIF_INTERNAL_DOCUMENT_API_URL`: URL server-to-server de `sif/public/api/documents/download.php`.
- `SIF_INTERNAL_DOCUMENT_SIGNED_PATH`: mateix path canònic de document; default `/api/documents/download.php`.
- `SIF_DOCUMENT_ROOT`: directori privat real dels documents; ha d'estar fora del webroot.
- `SIF_DOCUMENT_MAX_BYTES`: mida màxima que el servei accepta llegir/servir; default 20 MiB.

El secret no s'envia al navegador. `ajax/alumnes/sifFactures.php` refresca la sessió des de BD, extreu actor/rol al servidor i `SifInternalApiClient` crea la signatura HMAC.

## Rotació

Per rotar la clau sense exposar-la al repositori: actualitzar variables d'entorn als dos servidors dins la mateixa finestra de desplegament. No guardar secrets en PHP, Git, SQL de negoci, Trello ni documentació.
