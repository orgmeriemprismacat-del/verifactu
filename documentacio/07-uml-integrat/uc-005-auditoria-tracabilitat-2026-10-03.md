# UC-005 · Auditoria detallada i traçabilitat — 2026-10-03

## 1. Resum executiu

UC-005 **no estava sense codi**: existeix un nucli SIF executable per crear una factura sèrie R, relacionar-la amb l'original i marcar l'original com `RECTIFIED`. També existeixen scripts de preview/process i proves. El que faltava era reconciliar-lo amb la pantalla real, separar ACTUAL/FINAL i documentar els buits.

## 2. Matriu documentat / implementat / verificat / pendent

| Capacitat | Documentat | Implementat | Verificat estàtic | Pendent |
| --- | --- | --- | --- | --- |
| Localitzar original per UUID | Sí | Sí | Sí | executar prova |
| Localitzar per número visible | Sí | Sí | Sí | executar prova |
| Mode DIFERENCIES | Sí | Sí | Sí | fiscalitat completa |
| Mode SUBSTITUCIO | Sí | **Sí al backend SIF** | Sí | validació fiscal/AEAT específica |
| Clau idempotent | Sí | Sí | Sí | evidència MySQL |
| Idempotència payload | Transversal | Sí via InvoiceService | Sí | prova executada |
| Sèrie R i numeració SIF | Sí | Sí | Sí | evidència MySQL |
| Registre fiscal + cadena + cua | Sí | Sí | Sí | worker/AEAT transversal |
| Relació `fact_rels RECTIFIES` | Sí | Sí | Sí | executar prova |
| `factura_rectificacio` | Sí | Sí | Sí | atomicitat |
| Estat original RECTIFIED | Sí | Sí | Sí | política per variants |
| Alias `motiu/mode_rectificacio` | Implícit | **Corregit en aquesta branca** | Sí | executar nova prova |
| Receptor nou | Necessitat documentada | **Sí en SUBSTITUCIO** | Sí | UI + criteri fiscal/AEAT |
| IVA/règim heretat/correcte | Necessitat documentada | **Fail-closed + mapper AEAT simple server-side** | Sí estàtic | CI + casos multi-desglossament/recàrrec/canvi perfil |
| Import zero per canvi receptor/concepte | Necessitat possible | No | builder el rebutja | decidir regla |
| Classificador fiscal UC-74 | Sí | **Consum d'evidència persistida + guard; productor/classificador genèric no** | Sí | implementar UC-74 executable |
| Pantalla UC-005 SIF | Sí FINAL | **Sí com a consumidor read-only de decisió** | Sí | productor UC-74 + E2E/preproducció |
| Auth/CSRF command UC-005 | Sí FINAL | **HMAC/replay/rol + sessió/same-origin/CSRF** | Sí | evidència E2E/preproducció |
| Atomicitat total | Sí FINAL | **Implementada en aquesta branca per UC-005** | Revisada estàticament | executar prova de rollback/concurrència |
| Lock original/concurrència | Sí FINAL | **FOR UPDATE + revalidació** | Sí | concurrència E2E |
| Audit event específic | Sí | **sif_audit_event + operational_event** | Sí | evidència CI/preprod |
| Prova fallada entre invoice/link/state | Sí necessària | **Escrita** | Sí | executar CI/MySQL |

## 3. Troballes

### UC005-F01 — TANCAT EN CODI / PENDENT EXECUCIÓ · atomicitat del nucli UC-005

La branca d'auditoria afegeix un hook `beforeCommit` opcional a `InvoiceService`. `ManualRectificationService` l'utilitza per bloquejar l'original amb `FOR UPDATE`, revalidar el snapshot, inserir `factura_rectificacio` i marcar l'original `RECTIFIED` **abans del COMMIT de la mateixa transacció**. També s'ha afegit una prova d'injecció de fallada que exigeix rollback de `factura`, línies, registre, cua i relacions.

**Evidència disponible:** la suite específica UC-005 va passar 34/34 incloent rollback transaccional. **Pendent:** concurrència específica sobre l'original i revalidació posterior al merge amb `main`.

### UC005-F02 — TANCAT PARCIALMENT / AEAT SIMPLE IMPLEMENTAT · fiscalitat fail-closed

`ManualRectificationPayloadBuilder` ja no força EXEMPT/0 a qualsevol cas. Per originals exempts preserva règim, tipus, quota i causa d'exempció. Per originals subjectes a IVA, `amount` sol es considera ambigu i es rebutja; cal un bloc `fiscal` explícit amb base, règim, percentatge, quota i total coherent.

`AeatRectificationMapper` ja deriva els camps rectificatius des del snapshot AEAT original quan hi ha un únic desglossament autoritatiu i el perfil fiscal és compatible. **Pendent:** revalidació CI, múltiples desglossaments, recàrrec d'equivalència, canvi de perfil/tipus fiscal, ISP/no-subjecció i evidència real de preproducció.

### UC005-F03 — TANCAT AL BACKEND / CRITERI AEAT PENDENT · substitució de receptor

`SUBSTITUCIO` admet ara un bloc `billing` corregit i el congela només a la nova factura R. La factura original continua immutable. `DIFERENCIES` rebutja canvis de receptor.

**Pendent:** productor/classificador fiscal UC-74, perfils AEAT complexos i evidència E2E/preproducció. Receptor substitutiu, mapper compatible i integració UI consumidora ja existeixen.

### UC005-F04 — P1 · import zero prohibit

La validació rebutja imports amb valor absolut < 0,005. Cal decidir documentalment com representar correccions que no alteren total però sí receptor/concepte, sense inventar una figura fiscal.

### UC005-F05 — CONSUM D'EVIDÈNCIA IMPLEMENTAT / CLASSIFICADOR PENDENT · UC-74 abans de mutar

El botó llegat “anul·lar” no pot mapar-se directament a UC-005. A més del `FiscalCorrectionDecisionGuard`, l'endpoint exigeix `classification_event_uuid` i el resol contra `sif_audit_event`. Només s'accepta un event `FISCAL_CORRECTION_CLASSIFIED` amb `RESULT=SUCCEEDED`, `RESOURCE_TYPE=FACTURA`, la mateixa factura, reason code coherent i `correction_fingerprint` igual al payload actual; la classificació inline del request deixa de ser font de veritat.

Això **no equival a tenir UC-74 implementat**: encara falta el productor/classificador genèric que crea aquesta decisió persistent segons regles fiscals aprovades.

### UC005-F06 — TANCAT EN BRANCA · alias incoherents

El builder acceptava `motiu`/`mode_rectificacio`, però la persistència de `factura_rectificacio` llegia `reason`/`mode`. S'ha normalitzat l'entrada a `ManualRectificationService` i afegit test.

### UC005-F07 — documentació antiga de GET desactualitzada

Els endpoints `guardarDadesFactura_Factures.php` i `anularFactura_Factures.php` revisats actualment exigeixen POST, sessió, same-origin/permís i guard de mutació. La documentació que els descriu com GET s'ha de considerar històrica.

### UC005-F08 — PARCIAL IMPLEMENTAT / CASOS COMPLEXOS PENDENTS · mapping AEAT rectificativa

El backend ja recupera el snapshot `factura_registres.PAYLOAD_JSON.aeat`, valida emissor i identitat, pren R1-R5 de la decisió UC-74 persistida, genera S/I, `FacturasRectificadas`, `ImporteRectificacion` per S i un `Desglose` derivat del perfil original. El mapper rebutja explícitament escenaris que encara no pot demostrar. Encara no es pot declarar flux productiu fins superar CI, preproducció i els perfils fiscals pendents.

### UC005-F09 — IMPLEMENTAT COM A CONSUMIDOR · proxy/panell intranet segur

Existeixen `POST /api/factures/rectify.php`, el proxy `sifRectificarFactura.php`, `SifRectificationAccess` i el panell de `/alumnes/factura/`. La vista completa projecta la darrera decisió `FISCAL_CORRECTION_CLASSIFIED`; si porta `correction` executable, la UI la mostra read-only i només ofereix preview/confirm. Sense event o snapshot executable, l'emissió queda bloquejada.

## 4. Criteris de tancament

UC-005 només es pot marcar tancat quan:

1. la pantalla real crea una comanda SIF, no un UPDATE fiscal llegat;
2. UC-74 decideix la figura fiscal abans de la mutació;
3. el payload rectificatiu reconstrueix fiscalitat i dades corregides de manera autoritativa;
4. emissió R + relació + estat/auditoria tenen atomicitat o recuperació provada;
5. hi ha idempotència i lock de l'original;
6. hi ha proves nominal, reintent, payload conflictiu, concurrència i fallada intermèdia;
7. hi ha evidència MySQL `sif_test*`/preproducció;
8. consulta posterior mostra original, R, motiu, mode, estat AEAT i document.

## 5. Estat final d'aquesta passada

**DOCUMENTAT:** sí, paquet estructural complet.  
**IMPLEMENTAT:** nucli rectificatiu, atomicitat, aliases, fiscalitat fail-closed, SUBSTITUCIO amb receptor, command intern signat, preview/confirm, guard UC-74, auditoria i suite UC-005 aïllada.  
**VERIFICAT:** revisió estàtica, suite específica UC-005 verda 34/34 abans del reforç de decisió persistida, incloent atomicitat, fiscalitat local, command, permisos i protocol AEAT.  
**PENDENT:** revalidació CI, productor/classificador UC-74 executable, perfils AEAT complexos, concurrència E2E, document E2E i preproducció. El consumidor UI/proxy preview/confirm ja està implementat.

### UC005-F10 — P1 TRANSVERSAL · no hi ha productor/worker de documents fiscals

La branca disposa de `DocumentRepository`, `PrivateDocumentStore`, `InvoiceDocumentAccessService`, descàrrega signada i auditoria `fiscal_document_access`. La migració crea `document_job`, però en el codi SIF revisat **no s'ha localitzat cap productor de `document_job` ni cap worker/generador que construeixi PDF/QR/XML després d'emetre la factura R**. `DocumentRepository::registerDocument()` només registra bytes ja generats. Per tant UC-005 no pot acreditar encara `R -> document immutable -> consulta/descàrrega`; és un bloqueig transversal del subsistema documental, no una raó per regenerar documents des de dades vives.
