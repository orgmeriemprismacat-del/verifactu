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
- Storage privat UC-080 amb configuració real d'entorn i rols reals. Els casos bytes correctes, fitxer absent, hash incorrecte i audit ALLOWED/DENIED/FAILED ja tenen prova d'integració amb storage temporal.
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
- `sif/tests/Integration/InvoiceDocumentAccessServiceTest.php`

## 8. Criteri de tancament

L'UC-007 queda **DOCUMENTAT + IMPLEMENTAT EN CODI, PERÒ NO VERIFICAT RUNTIME**. El tancament “IMPLEMENTAT I PROVAT” requereix evidència reproduïble de CI/preproducció i les proves de [03-proves-pendents-uc-007-implementacio.md](03-proves-pendents-uc-007-implementacio.md).

## 9. Evidència CI obtinguda en aquesta revalidació

El primer run del PR #135 ha passat totes les proves directament relacionades amb UC-007 que ja existien: frontera intranet, query read-only, política FULL/MINIMAL, HMAC/anti-replay i CLI query. També passen les proves de registre immutable de document. El workflow global queda en `failure` per sis tests PACK/Redsys no modificats per aquesta branca. El commit base `main` ja tenia workflows SIF en `failure`; no s’afirma que fossin exactament les mateixes sis assertions perquè els logs complets del baseline no s’han reextret de forma fiable. Per tant, no hi ha evidència que aquestes fallades globals siguin una regressió causada per l’UC-007.

Després d'aquesta evidència s'ha afegit una prova específica d'UC-080 (`InvoiceDocumentAccessServiceTest`) per cobrir bytes verificats, scope MINIMAL denegat, hash mismatch i fitxer absent amb auditoria.


## 10. Segona passada de cobertura — 2026-10-03

S'han tancat tres mancances addicionals de prova/configuració:

1. S'ha retirat de `alumnes-factura.php` i `alumnes-mostrar-alumne.php` el bloc buit `SIF_INVOICE_QUERY_UI_ENABLED`. Aquest flag ja no governava cap asset; el rollout real queda en `SIF_UC007_QUERY_ENABLED` i `SIF_UC080_DOCUMENT_ENABLED`.
2. `InvoiceQueryServiceTest` cobreix ara `SOURCE_TYPE=INSCRIPCIO + source_ids`, múltiples factures relacionades amb una mateixa inscripció, combinació AND amb `NUM_VISIBLE` i errors de criteris incomplets/desconeguts.
3. `InternalInvoiceScopeResolverTest` cobreix resolució FULL, MINIMAL i denegació fail-closed de rols no autoritzats/actor buit.
4. `InvoiceDocumentAccessServiceTest` cobreix també intent de sortir de `SIF_DOCUMENT_ROOT` → 403 + `PATH_OUTSIDE_STORAGE`.

Aquestes proves continuen sent proves automatitzades sobre BD/storage de test; no substitueixen l'evidència de preproducció amb configuració real.


## 11. F07-01 tancat en codi — descàrrega llegada sense mutació de `GENERAT`

La relectura directa del blob complet de `codi-drive/intranet-actual/Intranet.php` ha permès verificar el cos real de `generaFactura($factura, $descarrega)`. Abans d'aquesta correcció, quan `$descarrega=true`, el mètode:
1. reconstruïa el PDF temporal;
2. escrivia el fitxer amb Dompdf;
3. si `GENERAT` era buit, executava `updGeneratFactura`.

Això era una mutació de negoci causada per una operació de consulta/descàrrega i violava l'invariant UC-007 de zero mutació.

**Correcció aplicada a la branca:** s'ha eliminat l'UPDATE de `GENERAT` del camí `generaFactura(..., true)`. La descàrrega llegada pot continuar reconstruint un PDF temporal mentre existeixi el fallback, però ja no modifica aquest estat de negoci.

**Protecció de regressió:** `Uc007IntranetBoundaryTest::testLegacyPdfReconstructionDoesNotMutateGeneratedBusinessState` aïlla el mètode `generaFactura()`, confirma que continua generant el fitxer temporal i falla si reapareix `updGeneratFactura`.

A partir d'aquesta correcció és coherent tractar la descàrrega com a **lectura**: el JS ja no exigeix `tePermisEdicio` per descarregar, mentre el backend continua revalidant sessió, `ROLS_VISUALITZAR`, origen/XHR i el guard de convivència SIF.


### 11.1. Semàntica llegada de `GENERAT` i còpia

Al generador llegat, `GENERAT` també es consulta per imprimir l'etiqueta «ÉS CÒPIA». Després de separar la lectura de la mutació, una descàrrega UC-007 ja no converteix per si mateixa una factura en “generada” ni en “còpia”. Els valors històrics ja existents es continuen llegint, però no es creen des del cas d'ús de consulta.

Aquesta és una diferència deliberada respecte del comportament antic: la traça de consulta/descàrrega FINAL no s'ha de codificar alterant la factura, sinó en un registre d'accés (`fiscal_document_access`) quan el document és SIF/UC-080. Si algun flux de negoci llegat necessita marcar explícitament una emissió/generació, s'ha de modelar fora de l'UC-007.


## 12. Reconciliació de troballes de l'auditoria 2026-09-29

| Ref | Estat 2026-10-03 | Evidència / decisió |
| --- | --- | --- |
| AUTH-01 | CORREGIT | rol de pàgina separat del breadcrumb |
| AUTH-02 | PARCIAL / DISSENY | el helper de rol no autoritza recurs; SIF sí aplica policy per factura, fallback només rol de pàgina |
| AUTH-03 | CORREGIT PARCIAL | context llegat refrescat + SIF resource policy |
| AUTH-04 | IMPLEMENTAT / RUNTIME PENDENT | `replaceRols()`; falta prova real de revocació |
| F02-01 | **OBERT LLEGAT** | `$existeixCerca` no s'inicialitza dins `buscarUsuaris_Factures()`; convé corregir al blob gran quan l'escriptura sigui estable |
| F02-02 | CORREGIT | escape de `=`, `%`, `_` + SQL `LIKE ... ESCAPE '='` |
| F02-03 | MITIGAT | protocol `#|...` continua ad hoc, però el JS actual filtra entrades buides |
| F02-04 | CORREGIT | control de volum usa longitud |
| F02-05 | CORREGIT | abort de request anterior + generation guard |
| F04-01 | CORREGIT | `buscarTotesFactId` usa `LEFT JOIN` |
| F04-02 | CORREGIT | estat d'inscripció agregat amb `MAX(CASE...)` |
| F04-03 | CORREGIT | ordenació PHP per any, sèrie i seqüència numèrica |
| F05-01 | **OBERT LLEGAT** | `buscarInfoFactInsc2` agrupa per `IDPAG` però conserva camps no agregats; depèn del mode SQL/semàntica llegada |
| F05-02 | CORREGIT | DNI i observacions tenen IDs HTML diferents |
| F05-03 | **OBERT MENOR** | `E_FACT` es recupera però encara no es mostra al modal llegat |
| F05-04 | CORREGIT AL WRAPPER | `guardarDadesFactura_Factures.php` recupera la relació actual i rebutja canvi de `FACTURA_RELACIONADA` |
| F05-05 | MITIGAT | el wrapper valida existència abans d'UPDATE; `affectedRows=0` pot ser legítim si no hi ha canvi |
| F06-01 | **OBERT LLEGAT** | preview parteix d'ID però reconstrueix per `FACTURA_RELACIONADA` |
| F06-02 | **OBERT LLEGAT** | original i R poden aparèixer com pàgines d'una reconstrucció; el camí SIF els separa |
| F07-01 | **CORREGIT EN BRANCA** | eliminat `updGeneratFactura` de `generaFactura(..., true)` |
| F07-02 | CORREGIT | una sola generació per clic |
| F07-03 | CORREGIT | prefix de filename derivat de la sèrie visible |
| F07-04 | MITIGAT | `buscarInfoFactura` té ordre estable; el model per relació continua sent limitació llegada |
| F07-05 | CORREGIT | `file_put_contents` es comprova |
| PDF-01 | OBERT LLEGAT | emissor/text fiscal hardcoded; no forma part del FINAL SIF |
| PDF-02 | OBERT LLEGAT | logo remot/reconstrucció viva; no és document immutable |
| PDF-03 | OBERT LLEGAT | no acredita QR/UUID/hash/AEAT; UC-080 és el camí FINAL |
| AL17-01 | CORREGIT | eliminada la segona substitució de `modal-body` |
| AL18-01 | CORREGIT | font canònic carregat; ja no usa `resD` indefinit |
| AL18-02 | CORREGIT PER FACTURA | endpoint de cleanup endurit; el document SIF no hi passa |
| AL18-03 | CORREGIT | factura llegada no fa cleanup immediat; TTL CLI |

### 12.1. Lectura de tancament

Les mancances que continuen obertes són **del fallback llegat** (F02-01, F05-01/F05-03, F06-01/F06-02 i PDF-01..03). No bloquegen el model FINAL UC-007/080, però sí bloquegen afirmar que el circuit llegat és equivalent o completament sanejat. La retirada del fallback continua sent el criteri final.


## 11. Troballa F07-L · `GENERAT` mutava en descarregar factura llegada — CORREGIT

La inspecció directa del blob complet `Intranet.php` ha permès acreditar una mutació que les lectures parcials anteriors no mostraven:

- `generaFactura($factura, true)` genera el PDF temporal;
- si `GENERAT` era nul/buit, executava `updGeneratFactura`;
- per tant, la descàrrega llegada no era estrictament read-only.

A la branca:
- `generaFactura()` passa a admetre `$marcaGenerada = true` per compatibilitat amb altres fluxos llegats;
- `descarregaFactura.php`, que és la frontera de consulta UC-007, crida `generaFactura((int) $id, true, false)`;
- l'UPDATE de `GENERAT` només s'executa si `$marcaGenerada` és `true`;
- `Uc007IntranetBoundaryTest` fixa aquesta semàntica.

Això preserva el comportament històric fora d'UC-007 i fa que **consultar/descarregar des d'UC-007 no modifiqui l'estat de la factura llegada**. La creació del PDF temporal continua sent una operació tècnica de sortida, no una mutació fiscal/econòmica de BD.
