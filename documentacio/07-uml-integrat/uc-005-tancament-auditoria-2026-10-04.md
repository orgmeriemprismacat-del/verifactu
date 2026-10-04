# UC-005 · Tancament de l'auditoria i gates de producció

**Data de reconciliació:** 2026-10-04  
**Branca:** `audit/uc-005-2026-10-03`  
**PR:** #131  
**Estat de l'auditoria:** **TANCADA A NIVELL DOCUMENTAL I DE RECONCILIACIÓ DE CODI**  
**Estat de posada en producció:** **NO-GO FINS SUPERAR ELS GATES PENDENTS**

## 1. Conclusió

UC-005 ja no és un cas d'ús només dissenyat. La branca conté un nucli executable per emetre/reutilitzar una factura rectificativa, vincular-la a l'original i marcar l'original com `RECTIFIED` dins la mateixa transacció; també conté command `preview/confirm`, autorització interna signada, consumidor intranet segur, consum d'una decisió UC-74 persistent, auditoria i un mapper AEAT fail-closed per al perfil simple acreditat.

Això **no** converteix UC-005 en productiu per si sol. El productor/classificador UC-74, els perfils AEAT no coberts, el pipeline documental UC-55 i l'evidència executable actual/preproducció continuen fora del gate.

## 2. Estat per capa

| Capa | Estat | Evidència / límit |
| --- | --- | --- |
| Fitxa funcional | **RECONCILIADA** | `../06-fitxes-funcionals/uc-005.md` |
| Inventari PHP/JS | **RECONCILIAT** | `uc-005-inventari-artefactes.md` |
| Cas d'ús ACTUAL/FINAL | **RECONCILIAT** | `uc-005-cas-us-actual-final.md` |
| Classes ACTUAL/FINAL | **RECONCILIAT** | `uc-005-classes-actual-final.md` |
| Seqüències ACTUAL/FINAL | **RECONCILIAT** | `uc-005-sequencies-actual-final.md` |
| Activitats/pantalles | **RECONCILIADES** | `uc-005-activitats-pagines-actual-final.md` |
| Atomicitat R + relació + estat | **IMPLEMENTADA** | fase `beforeCommit`, lock i rollback |
| Idempotència | **IMPLEMENTADA** | mateixa correcció reutilitza R; payload divergent falla |
| Fiscalitat local | **FAIL-CLOSED IMPLEMENTADA** | IVA ambigu exigeix snapshot fiscal explícit |
| SUBSTITUCIO receptor | **IMPLEMENTADA** | billing corregit només en mode permès |
| UC-74 consumidor | **IMPLEMENTAT** | event persistent + fingerprint exacte |
| UC-74 productor/classificador | **PENDENT / BLOQUEJANT** | no hi ha criteri fiscal aprovat executable que es pugui inventar |
| Endpoint intern | **IMPLEMENTAT** | POST HMAC, replay guard, rol explícit |
| Proxy intranet | **IMPLEMENTAT** | sessió, permís, same-origin, CSRF i HMAC |
| Panell intranet UC-005 | **IMPLEMENTAT COM A CONSUMIDOR** | només decisió/correction aprovades; sense selector R1-R5 |
| Decisió ja consumida | **IMPLEMENTAT** | read model la marca executada i la UI no torna a oferir confirmació |
| Mapper AEAT simple | **IMPLEMENTAT FAIL-CLOSED** | un únic desglossament compatible |
| AEAT complex | **PENDENT** | múltiples desglossaments, recàrrec, ISP/no-subjecció, canvi de perfil |
| Correcció total zero | **PENDENT DECISIÓ FISCAL** | builder la bloqueja |
| Concurrència multiprocés | **PROVA IMPLEMENTADA** | execució CI actual pendent |
| PDF/QR/XML | **PENDENT TRANSVERSAL UC-55** | accés/custòdia parcial existeixen; generador/worker no acreditat |
| Preproducció | **PENDENT** | no hi ha evidència de desplegament/cicle complet |

## 3. Garanties implementades

1. **Immutabilitat:** cap flux UC-005 modifica imports, receptor o línies de la factura original.
2. **Atomicitat:** nova R, `factura_rectificacio`, canvi d'estat de l'original i auditoria terminal comparteixen el commit fiscal.
3. **Idempotència:** la mateixa petició lògica no consumeix un segon número R.
4. **Concurrència defensiva:** l'original es rellegeix sota `FOR UPDATE` i es compara amb el snapshot anterior al commit.
5. **Preview/confirm:** un fingerprint canònic evita confirmar un payload diferent del previsualitzat.
6. **Decisió fiscal externa:** UC-005 no classifica pel text d'un botó ni per dades enviades pel navegador.
7. **Vinculació UC-74:** `classification_event_uuid` ha de pertànyer a la mateixa factura i el seu `correction_fingerprint` ha de coincidir amb la correcció exacta.
8. **Tipus R1-R5:** el tipus fiscal prové de la decisió UC-74, no del caller.
9. **AEAT server-side:** identitat d'emissor/SIF i snapshot original provenen del servidor.
10. **UI no destructiva:** la factura SIF continua read-only; el panell només inicia preview/confirm.
11. **Decisió consumida:** una decisió que ja té `RECTIFICATION_CONFIRM SUCCEEDED/REUSED` queda mostrada com executada.
12. **Fail closed:** qualsevol cas fiscal que el mapper no pot demostrar es rebutja en lloc de fabricar un XML.

## 4. Evidència de proves

### Executada

Existeix evidència prèvia d'una execució específica UC-005 amb **34 proves passades i 0 fallades**, que incloïa el rollback transaccional i els controls existents en aquell tall.

### Afegida després d'aquella execució

La branca ha incorporat posteriorment proves per:

- decisió UC-74 persistida i fingerprint de correcció;
- mapper AEAT des del snapshot original;
- canvi del snapshot AEAT entre preview i confirm;
- contracte del proxy intranet;
- read model de decisió UC-74;
- decisió ja executada;
- concurrència multiprocés sobre la mateixa factura.

Aquestes proves formen part de `sif/tests/run-uc005-tests.php`, però **la nova execució completa no es pot declarar verda mentre GitHub Actions continuï en cua**.

## 5. Gate de concurrència

`ManualRectificationConcurrencyTest` utilitza dos processos PHP i dues connexions:

1. el procés A entra a la transacció UC-005 i manté el lock abans del commit;
2. el procés B intenta la mateixa correcció mentre A encara està obert;
3. A confirma;
4. B només pot reutilitzar la mateixa R o rebre conflicte per snapshot obsolet;
5. la BD ha de conservar exactament una factura sèrie R i una relació `factura_rectificacio`;
6. un reintent posterior ha de retornar la mateixa R amb `idempotency_reused=true`.

**Gate:** no marcar concurrència com verificada fins executar aquesta prova en MySQL `sif_test*` i repetir l'escenari crític en preproducció.

## 6. Dependències que UC-005 no ha de duplicar

### UC-74 — Classificar una correcció fiscal

UC-005 ja consumeix la decisió, però **no ha d'inventar el classificador**. Fins que UC-74 no disposi de regles aprovades, una decisió dubtosa ha de romandre pendent de revisió humana.

### UC-55 — Generar/reintentar/custodiar documents

La branca disposa de:

- `factura_documents`;
- `DocumentRepository`;
- `PrivateDocumentStore`;
- descàrrega signada;
- validació SHA-256;
- auditoria `fiscal_document_access`;
- esquema `document_job`.

No s'ha localitzat un productor/worker que generi automàticament PDF/QR/XML a partir del snapshot immutable i completi el job. UC-005 no ha de crear un generador propi: ha de consumir el pipeline transversal UC-55 quan estigui implementat.

## 7. NO-GO productiu si falla qualsevol d'aquests punts

- no existeix una decisió UC-74 aprovada i vinculada a la correcció exacta;
- el perfil fiscal requereix un cas AEAT que el mapper actual rebutja;
- la suite UC-005 actual no ha passat;
- la prova de concurrència no ha passat;
- el certificat/configuració AEAT de l'entorn no està validat;
- el cicle document immutable PDF/QR/XML no està disponible quan sigui exigible;
- la preproducció no demostra preview → confirm → R → cua AEAT → document → consulta;
- existeix una incidència fiscal/documental bloquejant.

## 8. Criteri per declarar UC-005 productiu

UC-005 podrà passar de **NO-GO** a **GO** només quan es conservi evidència de:

1. productor UC-74 validat per les casuístiques aprovades;
2. suite UC-005 actual completament verda;
3. concurrència multiprocés verda;
4. perfil o perfils AEAT que realment s'activaran validats contra protocol/XSD;
5. pipeline UC-55 disponible o política operativa que bloquegi el flux fins que el document requerit existeixi;
6. prova preproducció completa amb permisos reals, feature flags i configuració de l'entorn;
7. evidència de consulta posterior de l'original i la R, amb auditoria i document/estat correcte.

## 9. Veredicte

**Auditoria UC-005: TANCADA.**  
**Implementació core UC-005: AVANÇADA I FAIL-CLOSED.**  
**Producció UC-005: NO-GO fins tancar dependències i evidència.**

No s'ha de reobrir l'auditoria estructural per aquests pendents; s'han de tancar els gates UC-74, UC-55, AEAT/preproducció i proves, i després registrar una revalidació final.
