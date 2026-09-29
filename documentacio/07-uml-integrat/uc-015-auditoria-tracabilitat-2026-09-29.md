# UC-015 · Auditoria detallada i traçabilitat — 2026-09-29

## 1. Resultat

**Estat global:** IMPLEMENTACIÓ PARCIAL / DOCUMENTACIÓ COMPLETADA EN AQUEST LOT / PROVES D'ENTORN PENDENTS.

Aquest registre diferencia:
- **DOCUMENTAT:** existeix contracte o UML.
- **IMPLEMENTAT:** existeix codi.
- **VERIFICAT:** inspeccionat directament al repositori.
- **PENDENT:** manca implementació, execució de proves o evidència d'entorn.

## 2. Matriu

| Element | Documentat | Implementat | Verificat | Pendent |
|---|---:|---:|---:|---:|
| Catàleg i fitxa pack | sí | sí | sí | proves navegador |
| N inscripcions amb IDPAG comú | sí | sí | sí | substituir identitat concurrent |
| Preu pack | sí | sí | sí | backend autoritatiu |
| Snapshot comercial | sí | parcial | sí | ordinal/receptor/preu versionat |
| Intenció Redsys PACK | sí | sí | sí | connexió ecommerce |
| Callback SIF | sí | sí | sí | activació real canal |
| Callback legacy | sí | sí | sí | retirada |
| Factura N línies | sí | sí | sí | prova end-to-end |
| Conciliació factura/import Redsys | sí | **sí (29/09)** | sí | executar test |
| Idempotència factura/payment | sí | sí | sí | evidència runtime |
| Ledger ID_INSC | sí | no | sí | implementar |
| Outbox correu | sí | no per aquest canal | sí | implementar |
| Activitats ACTUAL/FINAL | **sí (29/09)** | n/a | sí | mantenir sincronitzat |
| Classes ACTUAL/FINAL | **sí (29/09)** | n/a | sí | mantenir sincronitzat |
| Seqüències ACTUAL/FINAL | **sí (29/09)** | n/a | sí | mantenir sincronitzat |

## 3. Codi web/llegat inspeccionat

- `codi-drive/web-actual/Pack.php`
- `codi-drive/web-actual/EdicioPack.php`
- `codi-drive/web-actual/InscripcioPack.php`
- `codi-drive/web-actual/js1619773569/mostrarInscripcioPack.min.js`
- `codi-drive/web-actual/ajax/mostrar_inscripcio_packs.php`
- `codi-drive/web-actual/ajax/obtenirIdPack.php`
- `codi-drive/web-actual/ajax/obtenirIdPreuPack.php`
- `codi-drive/web-actual/ajax/obtenirPreusPack.php`
- `codi-drive/web-actual/ajax/enviarInscripcioPack.php`
- `codi-drive/pay-prisma-cat-canvis-verifactu/realitzaPagamentPackAutomatic.php`

## 4. Codi SIF inspeccionat

- `sif/src/Service/RedsysPaymentIntentService.php`
- `sif/src/Service/RedsysCallbackService.php`
- `sif/src/Service/RedsysCallbackDispatcher.php`
- `sif/src/Service/RedsysCallbackWorker.php`
- `sif/src/Service/RedsysPackInvoiceService.php`
- `sif/src/Repository/LegacyPackSnapshotRepository.php`
- `sif/src/Service/LegacyPackInvoicePayloadBuilder.php`
- `sif/src/Service/RedsysInvoicePayloadBuilder.php`
- `sif/src/Service/InvoiceService.php`
- `sif/src/Repository/InvoiceRepository.php`
- `sif/src/Repository/PaymentRepository.php`

## 5. Proves localitzades

- `sif/tests/Integration/LegacyPackInvoicePayloadBuilderTest.php`
- `sif/tests/Integration/RedsysPackInvoiceServiceTest.php`
- `sif/tests/Integration/RedsysPackPreflightScriptTest.php`
- `sif/tests/Integration/RedsysPackPreproductionScriptTest.php`

### Prova afegida 2026-09-29

`RedsysPackInvoiceServiceTest::testRejectsPackWhenValidatedRedsysAmountDiffersFromInvoiceLines()`

Exigeix:
- notificació Redsys `VALIDATED`;
- import notificat diferent de la suma de línies;
- resposta 409;
- zero factures;
- zero `payment_transaction`.

**Execució:** pendent d'evidència; no hi ha workflow associat al commit inspeccionat.

## 6. Troballes P0

### UC15-P0-01 · Preu enviat pel navegador
`mostrarInscripcioPack.min.js` envia `preuCursos` i `preuPack`; `enviarInscripcioPack.php` els consumeix. El preu definitiu ha de ser rellegit/congelat al servidor.

### UC15-P0-02 · IDPAG concurrent
`SELECT IDPAG ... ORDER BY IDPAG DESC LIMIT 1` + 1 no és un generador segur.

### UC15-P0-03 · Callback legacy fiscal
`realitzaPagamentPackAutomatic.php` encara calcula numeració i insereix `factures` directament.

### UC15-P0-04 · Signatura
El callback llegit calcula signatura Redsys, però no s'ha acreditat la comparació bloquejant amb la signatura rebuda abans de mutar dades.

### UC15-P0-05 · Ordinal comercial
`LegacyPackSnapshotRepository` ordena per `A_PAGAR DESC, ID`, mentre el builder aplica la regla del descompte segons índex. Això no equival a l'ordinal de l'oferta.

### UC15-P0-06 · Receptor
El receptor del builder prové del primer item; el primer item pot dependre de l'ordre per `A_PAGAR`.

### UC15-P0-07 · Conciliació import
**Corregit al SIF el 2026-09-29:** `RedsysPackInvoiceService` bloqueja si total factura i import Redsys no coincideixen.

## 7. Troballes P1

- ledger quantitatiu per ID_INSC;
- retirar fraccionament del checkout o limitar-lo al contracte decidit;
- outbox de correus;
- sincronització acadèmica postcommit;
- packs N i combinacions de descompte;
- component indisponible (UC-122);
- factura prèvia al cobrament (RM-016).

## 8. Fitxers UML associats

- [Fitxa UC-015](../06-fitxes-funcionals/uc-015.md)
- [UML integrat](uc-015-comprar-pack.md)
- [Classes ACTUAL/FINAL](uc-015-classes-actual-final.md)
- [Seqüències ACTUAL/FINAL](uc-015-sequencies-actual-final.md)
- [Activitats ACTUAL/FINAL](uc-015-activitats-pagines-pack-actual-final.md)

## 9. Criteri de tancament

UC-015 no pot passar a **VERIFICAT/TANCAT** fins que:
- ecommerce creï la intenció SIF amb snapshot comercial;
- el callback fiscal legacy deixi de ser autoritatiu;
- ordinal, imports i receptor siguin congelats abans del TPV;
- ledger per inscripció estigui resolt;
- proves PK-01..PK-11 i de callback duplicat s'executin en entorn controlat.
