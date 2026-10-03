# UC-007 · Revalidació exhaustiva — 2026-10-03

**Cas:** Consultar factura, estat i document.  
**Base revisada:** `main` vigent el 2026-10-03, contrastat amb la fitxa UC-007, l'auditoria del 2026-09-29, PHP/JS real, SIF i fronteres UC-080.  
**Criteri:** `DOCUMENTAT` ≠ `IMPLEMENTAT` ≠ `VERIFICAT`. Només es marca `VERIFICAT` quan hi ha evidència estàtica concreta o prova executable; les proves runtime/preproducció continuen separades.

## 1. Resultat executiu

| Àrea | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- |
| Fitxa funcional UC-007 | sí | n/a | revalidada | mantenir sincronitzada |
| Consulta SIF per UUID/cerca | sí | sí | estàtic + tests existents | execució CI/preproducció |
| Scope intern FULL/MINIMAL | sí | sí | estàtic | rols reals d'entorn |
| HMAC + anti-replay | sí | sí | codi/tests existents | prova contra entorn |
| UI `/alumnes/factura/` | sí | sí | estàtic | E2E navegador |
| AL-16/17/18 fitxa alumne | sí | sí al font | **deriva d'asset detectada i corregida en aquesta branca** | desplegament + E2E |
| Documents UC-080 | sí | parcial | estàtic | storage/hash/runtime |
| Fallback llegat | sí | parcial/enduit | estàtic | regressió amb dades reals |
| UML classes | existia integrat | sí documental | revalidat | — |
| UML seqüència | existia integrat | sí documental | revalidat | — |
| UML activitats F01-F07/AL16-18 | existia a l'auditoria | sí documental | revalidat | — |
| Fitxers UML separats 1:1 | **faltaven** | creats ara | revisats contra codi | manteniment |
| Matriu de traçabilitat UC-007 | parcial | creada ara | contrastada | evidència runtime |

**Conclusió:** no faltava el concepte funcional ni el nucli SIF, però sí faltava una paquetització documental 1:1 i hi havia una deriva crítica entre el JS font i el JS real carregat a la fitxa de l'alumne.

## 2. Troballes de la revalidació

### UC007-RV-01 · Asset executable de fitxa alumne obsolet — CORREGIT EN BRANCA

`alumnes-mostrar-alumne.php` carregava `alumnes-mostrar-alumne.min.js?ver=1.6`. Aquest artefacte encara contenia:
- descàrrega llegada per GET;
- referència a la variable inexistent `resD`;
- cleanup per GET;
- doble `.html(res)` després de registrar handlers de fletxes.

El font `alumnes-mostrar-alumne.js` ja contenia les correccions i la integració SIF, però **no era l'asset principal executat**. La branca fa servir el font canònic `?ver=1.7` i deixa d'incloure el segon mòdul SIF duplicat.

### UC007-RV-02 · Dues implementacions UC-007 a `/alumnes/factura/` — CORREGIT EN BRANCA

La pàgina carregava `alumnes-factura-sif.js` i després `alumnes-factura.js`. Tots dos definien lògica SIF; el primer no propagava `uuid` a criteris i esperava `HASH_FITXER` en metadata, mentre el read model SIF no exposa aquest camp. L'ordre de scripts feia que una implementació pogués sobreescriure l'altra.

La branca deixa una sola implementació executable: `alumnes-factura.js`.

### UC007-RV-03 · Deep links UUID — CORREGIT / COMPATIBLE

El format canònic és `#/uuid/<UUID>`. La implementació principal ja el resolia i el mapejava a `criteria.uuid_factura`. S'afegeix compatibilitat amb el format anterior `?uuid_factura=<UUID>` per no trencar enllaços existents.

### UC007-RV-04 · Paginació del fallback llegat — CORREGIT EN BRANCA

`mostrarModalConsultaFacturaLlegat()` registrava handlers sobre `.fletxa-left/.fletxa-right` i després substituïa una segona vegada el body del modal. Això recreava els nodes i perdia els handlers. S'elimina la segona substitució.

### UC007-RV-05 · Metadata documental — RECONCILIAT

`InvoiceReadRepository::findDocumentMetadata()` retorna `ID, UUID_FACTURA, TIPUS, ESTAT, CREATED_AT`, sense `PATH_FITXER` ni `HASH_FITXER`. Aquesta és la projecció de lectura UC-007. Els bytes i el hash físic es verifiquen dins UC-080 en `InvoiceDocumentAccessService`. El JS duplicat que intentava mostrar `HASH_FITXER` queda fora de l'execució.

### UC007-RV-06 · Estat CREATED de document — PARCIAL

La UI pot oferir intent de descàrrega per `CREATED|READY|ARCHIVED`. UC-080 torna a verificar storage i hash i pot retornar unavailable/integrity error. Per tant, `CREATED` continua sense equivaldre a “bytes acreditats”. És acceptable com a intent revalidat, però s'ha de provar en preproducció.

## 3. Inventari de superfícies

| Ref | Superfície | Responsabilitat | Estat |
| --- | --- | --- | --- |
| P-07-01 | `/alumnes/factura/` | cerca, llistat i detall | IMPLEMENTAT |
| P-07-02 | `/alumnes/mostrar-alumne/` | AL-16 navegació + AL-17 modal | IMPLEMENTAT al font canònic |
| A-07-01 | `ajax/alumnes/sifFactures.php` | pont sessió → API interna | IMPLEMENTAT |
| A-07-02 | `ajax/alumnes/sifDocument.php` | proxy document UC-080 | IMPLEMENTAT/PARCIAL rollout |
| S-07-01 | `POST /api/factures/query.php` | cerca/view SIF | IMPLEMENTAT |
| S-80-01 | `POST /api/documents/download.php` | bytes privats + audit | IMPLEMENTAT/PARCIAL entorn |
| L-07-* | wrappers llegats | fallback temporal | IMPLEMENTAT/PARCIAL |

## 4. Estat per subacció

| Ref | Acció | Documentat | Implementat | Verificat | Pendent |
| --- | --- | --- | --- | --- | --- |
| F01 | carregar pantalla/rol | sí | sí | estàtic | runtime rol revocat |
| F02 | cercar | sí | SIF + fallback | estàtic | dades reals/wildcards llegat |
| F03 | selector | sí | sí | estàtic | UX/E2E |
| F04 | llistat | sí | sí | estàtic | regressió llegat |
| F05 | detall read-only | sí | sí | estàtic | E2E |
| F06 | preview/document | sí | UC-080 per SIF | estàtic | storage |
| F07 | descàrrega | sí | UC-080 / fallback POST | estàtic | runtime |
| AL-16 | salt des del camp factura | sí | sí al JS canònic | estàtic | navegador |
| AL-17 | modal des d'inscripció | sí | sí | estàtic | múltiples factures |
| AL-18 | download/cleanup | sí | SIF immutable; fallback sense cleanup immediat | estàtic | regressió |

## 5. Frontera de seguretat

1. El navegador no signa HMAC ni decideix `invoice_scope`.
2. La intranet deriva actor/rol de sessió revalidada i signa server-to-server.
3. El SIF torna a resoldre rol → projecció FULL/MINIMAL.
4. El document es reautoritza per `document_id`; UUID o URL no són credencial.
5. El read model UC-007 no exposa path físic.
6. Les mutacions llegades poden bloquejar-se quan la relació ja està governada pel SIF.
7. Cap consulta UC-007 ha de crear factura, cobrament, rectificativa o document nou.

## 6. Mancances que continuen obertes

- Execució real de la suite i E2E amb MySQL/preproducció.
- Evidència de rols reals i revocació de sessió.
- Storage privat UC-080, documents absents, hash incorrecte i audit d'accés.
- Casos d'empresa/grup i visibilitat per recurs dels canals externs.
- Regressió de consultes llegades amb dades històriques complexes.
- Retirada definitiva del fallback i dels artefactes JS obsolets un cop acabada la migració.
- Verificació de `SIF_BLOCK_LEGACY_INVOICE_MUTATIONS=1` abans de producció.

## 7. Fitxers creats/reconciliats en aquesta auditoria

- `uc-007-inventari-codi-php-js-actual-final.md`
- `uc-007-classes-actual-final.md`
- `uc-007-sequencies-actual-final.md`
- `uc-007-activitats-pagines-actual-final.md`
- `uc-007-tracabilitat-estats-2026-10-03.md`
- `sif/tests/Integration/Uc007IntranetBoundaryTest.php`

## 8. Criteri de tancament

L'UC-007 queda **DOCUMENTAT + IMPLEMENTAT EN CODI, PERÒ NO VERIFICAT RUNTIME**. El tancament “IMPLEMENTAT I PROVAT” requereix evidència reproduïble de CI/preproducció i les proves de [03-proves-pendents-uc-007-implementacio.md](03-proves-pendents-uc-007-implementacio.md).
