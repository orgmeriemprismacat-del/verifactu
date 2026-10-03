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
| IVA/règim heretat/correcte | Necessitat documentada | **Fail-closed + bloc fiscal explícit** | Sí | mapping AEAT/XSD específic |
| Import zero per canvi receptor/concepte | Necessitat possible | No | builder el rebutja | decidir regla |
| Classificador fiscal UC-74 | Sí | **Guard integrat; classificador genèric no** | Sí | implementar UC-74 executable |
| Pantalla UC-005 SIF | Sí FINAL | No | Sí | implementar adaptador/proxy intranet |
| Auth/CSRF command UC-005 | Sí FINAL | **HMAC/replay/rol backend sí** | Sí | sessió+CSRF al proxy intranet |
| Atomicitat total | Sí FINAL | **Implementada en aquesta branca per UC-005** | Revisada estàticament | executar prova de rollback/concurrència |
| Lock original/concurrència | Sí FINAL | **FOR UPDATE + revalidació** | Sí | concurrència E2E |
| Audit event específic | Sí | **sif_audit_event + operational_event** | Sí | evidència CI/preprod |
| Prova fallada entre invoice/link/state | Sí necessària | **Escrita** | Sí | executar CI/MySQL |

## 3. Troballes

### UC005-F01 — TANCAT EN CODI / PENDENT EXECUCIÓ · atomicitat del nucli UC-005

La branca d'auditoria afegeix un hook `beforeCommit` opcional a `InvoiceService`. `ManualRectificationService` l'utilitza per bloquejar l'original amb `FOR UPDATE`, revalidar el snapshot, inserir `factura_rectificacio` i marcar l'original `RECTIFIED` **abans del COMMIT de la mateixa transacció**. També s'ha afegit una prova d'injecció de fallada que exigeix rollback de `factura`, línies, registre, cua i relacions.

**Pendent per tancar evidència:** execució CI/MySQL verda de la nova prova i prova de concurrència específica sobre l'original.

### UC005-F02 — TANCAT PARCIALMENT / AEAT PENDENT · fiscalitat local fail-closed

`ManualRectificationPayloadBuilder` ja no força EXEMPT/0 a qualsevol cas. Per originals exempts preserva règim, tipus, quota i causa d'exempció. Per originals subjectes a IVA, `amount` sol es considera ambigu i es rebutja; cal un bloc `fiscal` explícit amb base, règim, percentatge, quota i total coherent.

**Pendent:** camps AEAT específics de rectificativa, validació XSD/protocol i criteris fiscals addicionals (ISP, recàrrec, no-subjecció, etc.).

### UC005-F03 — TANCAT AL BACKEND / CRITERI AEAT PENDENT · substitució de receptor

`SUBSTITUCIO` admet ara un bloc `billing` corregit i el congela només a la nova factura R. La factura original continua immutable. `DIFERENCIES` rebutja canvis de receptor.

**Pendent:** decisió/classificació fiscal real UC-74, mapping AEAT específic i integració UI.

### UC005-F04 — P1 · import zero prohibit

La validació rebutja imports amb valor absolut < 0,005. Cal decidir documentalment com representar correccions que no alteren total però sí receptor/concepte, sense inventar una figura fiscal.

### UC005-F05 — GUARD IMPLEMENTAT / CLASSIFICADOR PENDENT · UC-74 abans de mutar

El botó llegat “anul·lar” no pot mapar-se directament a UC-005. El nou `FiscalCorrectionDecisionGuard` impedeix confirmar UC-005 si la decisió no declara `source_uc=UC-74`, `decision=RECTIFICATION`, versió de política, reason code i mode coherent.

Això **no equival a tenir UC-74 implementat**: el classificador genèric continua en `[DISSENY/BLOQUEJANT]`.

### UC005-F06 — TANCAT EN BRANCA · alias incoherents

El builder acceptava `motiu`/`mode_rectificacio`, però la persistència de `factura_rectificacio` llegia `reason`/`mode`. S'ha normalitzat l'entrada a `ManualRectificationService` i afegit test.

### UC005-F07 — documentació antiga de GET desactualitzada

Els endpoints `guardarDadesFactura_Factures.php` i `anularFactura_Factures.php` revisats actualment exigeixen POST, sessió, same-origin/permís i guard de mutació. La documentació que els descriu com GET s'ha de considerar històrica.

### UC005-F08 — P0/P1 · mapping AEAT específic de rectificativa pendent

El payload local SIF ja conserva una fiscalitat més segura, però UC-005 no construeix encara de manera acreditada els camps AEAT específics de factura rectificativa ni prova el registre contra XSD/protocol. Per tant no es pot declarar el flux productiu VERI*FACTU.

### UC005-F09 — P1 · adaptador intranet pendent

Existeix `POST /api/factures/rectify.php` com endpoint intern signat amb HMAC, replay guard i rol específic. La pantalla `/alumnes/factura/` encara no disposa del proxy servidor amb sessió/permís/CSRF ni del modal preview/confirm que consumeixi el command.

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
**VERIFICAT:** revisió estàtica i cobertura de proves escrita; la suite global prèvia va donar 918 passats i 6 errors aliens al UC-005.  
**PENDENT:** resultat verd de la suite UC-005 aïllada, classificador UC-74 executable, mapping AEAT/XSD de rectificatives, proxy/UI intranet, concurrència E2E i preproducció.
