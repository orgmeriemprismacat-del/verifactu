# UC-005 · Auditoria detallada i traçabilitat — 2026-10-03

## 1. Resum executiu

UC-005 **no estava sense codi**: existeix un nucli SIF executable per crear una factura sèrie R, relacionar-la amb l'original i marcar l'original com `RECTIFIED`. També existeixen scripts de preview/process i proves. El que faltava era reconciliar-lo amb la pantalla real, separar ACTUAL/FINAL i documentar els buits.

## 2. Matriu documentat / implementat / verificat / pendent

| Capacitat | Documentat | Implementat | Verificat estàtic | Pendent |
| --- | --- | --- | --- | --- |
| Localitzar original per UUID | Sí | Sí | Sí | executar prova |
| Localitzar per número visible | Sí | Sí | Sí | executar prova |
| Mode DIFERENCIES | Sí | Sí | Sí | fiscalitat completa |
| Mode SUBSTITUCIO | Sí | Parcial | Sí | dades corregides completes |
| Clau idempotent | Sí | Sí | Sí | evidència MySQL |
| Idempotència payload | Transversal | Sí via InvoiceService | Sí | prova executada |
| Sèrie R i numeració SIF | Sí | Sí | Sí | evidència MySQL |
| Registre fiscal + cadena + cua | Sí | Sí | Sí | worker/AEAT transversal |
| Relació `fact_rels RECTIFIES` | Sí | Sí | Sí | executar prova |
| `factura_rectificacio` | Sí | Sí | Sí | atomicitat |
| Estat original RECTIFIED | Sí | Sí | Sí | política per variants |
| Alias `motiu/mode_rectificacio` | Implícit | **Corregit en aquesta branca** | Sí | executar nova prova |
| Receptor nou | Necessitat documentada | No | Buit confirmat | implementar |
| IVA/règim heretat/correcte | Necessitat documentada | No general | Builder fixa EXEMPT 0 | implementar |
| Import zero per canvi receptor/concepte | Necessitat possible | No | builder el rebutja | decidir regla |
| Classificador fiscal UC-74 | Sí | No integrat | Sí | integrar |
| Pantalla UC-005 SIF | Sí FINAL | No | Sí | implementar |
| Auth/CSRF command UC-005 | Sí FINAL | No específic | Sí | implementar |
| Atomicitat total | Sí FINAL | No | **Risc confirmat** | P0 |
| Lock original/concurrència | Sí FINAL | No en servei UC-005 | Sí | P0/P1 |
| Audit event específic | Sí | No observat | Sí | implementar |
| Prova fallada entre invoice/link/state | Sí necessària | No | Absència confirmada | implementar |

## 3. Troballes

### UC005-F01 — P0 · operació no atòmica

`InvoiceService::issueInvoice()` confirma la creació fiscal abans de `RectificationRepository::linkRectification()` i `markOriginalRectified()`. Una fallada després del COMMIT pot deixar factura R fiscalment creada però sense relació o sense canvi d'estat de l'original.

**Criteri de tancament:** una única unitat transaccional o un patró de saga/reconciliació explícit, provat amb fallades injectades.

### UC005-F02 — P0/P1 · fiscalitat massa rígida

`ManualRectificationPayloadBuilder` força `IVA_REGIM=EXEMPT`, `IVA_PCT=0`, `IVA_IMPORT=0`. No és una generalització segura per qualsevol factura original.

### UC005-F03 — P1 · substitució incompleta

El builder reutilitza `billing()` de la factura original. Per tant `SUBSTITUCIO` no acredita canvi efectiu de receptor.

### UC005-F04 — P1 · import zero prohibit

La validació rebutja imports amb valor absolut < 0,005. Cal decidir documentalment com representar correccions que no alteren total però sí receptor/concepte, sense inventar una figura fiscal.

### UC005-F05 — P1 · falta classificador abans de mutar

El botó llegat “anul·lar” no pot mapar-se directament a UC-005. Cal passar per UC-74 i derivar UC-005, UC-30, UC-31, UC-27/72 o UC-28 segons el cas.

### UC005-F06 — TANCAT EN BRANCA · alias incoherents

El builder acceptava `motiu`/`mode_rectificacio`, però la persistència de `factura_rectificacio` llegia `reason`/`mode`. S'ha normalitzat l'entrada a `ManualRectificationService` i afegit test.

### UC005-F07 — documentació antiga de GET desactualitzada

Els endpoints `guardarDadesFactura_Factures.php` i `anularFactura_Factures.php` revisats actualment exigeixen POST, sessió, same-origin/permís i guard de mutació. La documentació que els descriu com GET s'ha de considerar històrica.

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
**IMPLEMENTAT:** nucli parcial + correcció d'alias.  
**VERIFICAT:** revisió estàtica del codi i tests existents.  
**PENDENT:** atomicitat, fiscalitat general, substitució real, classificador, UI/HTTP, proves executades i evidència de preproducció.
