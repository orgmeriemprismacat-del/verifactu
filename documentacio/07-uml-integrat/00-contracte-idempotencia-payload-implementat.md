# Contracte transversal d'idempotència de payload · implementació PHP

**Abast:** ampliació del core a la branca `fix/payload-idempotency-guard-2026-09-21`.
Aquesta fitxa descriu codi versionat, **no acredita una execució de la suite MySQL,
un desplegament en producció ni la conformitat fiscal davant l'AEAT**.
L'auditoria històrica [00-auditoria-contractes-core-php.md](00-auditoria-contractes-core-php.md)
identificava els bloquejos abans d'aquesta modificació.

## Classes i taules

- `Contract/PayloadIdempotencyValidatorInterface.php` i `Service/PayloadIdempotencyValidator.php`:
  hash SHA-256 de JSON canonitzat (claus associatives ordenades; les llistes conserven
  l'ordre); una cadena s'interpreta com a seqüència literal de bytes.
- `factura.IDEMPOTENCY_PAYLOAD_HASH` és independent de
  `factura_registres.HASH_FACT`. Es calcula sobre **tota la petició validada**
  d'emissió, inclòs el bloc `payment` si n'hi ha, abans de generar UUID/seqüència.
  L'alta i el hash es persisteixen a la mateixa transacció.
- `payment_transaction.PAYLOAD_HASH_VERSION`: `1` per als registres
  històrics (hash del JSON en l'ordre d'inserció) i `2` per a nous cobraments
  (hash del payload canonitzat). No es reinterpreten ni reescriuen hashes antics.
- `InvoiceService` i `PaymentService` comparen el payload abans de reutilitzar
  una clau, inclosa la recuperació després d'una col·lisió de clau única.
  Una discrepància retorna error funcional 409 sense modificar l'original.
- `FiscalQueueRepository::assertImmutablePayload` compara el JSON de la cua
  amb el de `factura_registres` de la mateixa factura i ordre fiscal, i
  recalcula `HASH_FACT` sobre el registre i el hash anterior. Si no quadra,
  `FiscalQueueProcessor` **no crida el transport**, aïlla la feina en
  `DEAD_LETTER` i obre una incidència `FISCAL_PAYLOAD_CONFLICT`.
  Ni el registre fiscal ni la factura originals es modifiquen en aquest camí.
- `RedsysPaymentIntentService::sameIntent()` **ja comparava** `DS_ORDER`,
  IDPAG, origen, import, moneda, terminal, snapshot, creador i caducitat;
  aquesta implementació no substitueix aquest contracte ni la validació
  de la signatura del callback.

## Migració i compatibilitat

1. Desplegar la migració additiva
   `sif/database/migrations/2026_09_21_000007_add_idempotency_payload_hashes.sql`
   **abans del codi nou**. La migració no s'ha d'executar manualment dues
   vegades: el `MigrationRunner` registra el fitxer i el seu SHA-256.
2. Les factures emeses abans de la migració tenen un hash d'entrada `NULL`.
   Com que el registre fiscal no conserva necessàriament la petició sencera,
   un reintent de la seva clau es **rebutja amb 409** fins que hi hagi un
   procediment verificat de conciliació manual. No es fa backfill inventat.
   No afecta la consulta dels documents existents.
3. Els pagaments antics mantenen `PAYLOAD_HASH_VERSION=1`: una petició
   igual en la serialització històrica es reutilitza; una petició amb les
   claus en ordre diferent pot ser rebutjada de manera conservadora.
4. L'ordre de camps associatius no altera el hash nou, però `72`, `72.0`
   i `"72.00"` continuen sent diferents. Cal normalitzar imports, dates
   i camps opcionals en cada builder abans d'entrar al servei.
   En especial, no s'han de regenerar dates de moviment en un reintent
   si formen part de la identitat immutable del cobrament.

## Límits que segueixen oberts

- Un hash coincident **no substitueix** les claus úniques de BD,
  `SELECT ... FOR UPDATE`, l'autorització per recurs ni les restriccions
  monetàries (imports positius, assignacions i devolucions).
- Dues **claus diferents** per al mateix moviment bancari requereixen
  conciliació del proveïdor; no les identifica aquest guard.
- Aquesta comparació no confirma si l'AEAT va rebre una tramesa
  la resposta de la qual es va perdre. Cal conciliació d'estats remots.
- Els snapshots d'intenció Redsys i els callbacks requereixen
  continuar validant signatura, resposta, import, moneda i DS_ORDER.
- Les proves afegides cobreixen hashes nous/antics, conflicte de factura
  i cobrament i alteració de cua/registre fiscal. **No es presenten com
  executades** fins disposar de PHP, pdo_mysql i MySQL 8 de test aïllat.

## Traçabilitat

UC-01, UC-02, UC-03, UC-04, UC-09, UC-52, UC-54, UC-77, UC-86 i UC-112.
Classes de referència: `InvoiceService`, `PaymentService`,
`RedsysPaymentIntentService`, `FiscalQueueProcessor`,
`InvoiceRepository`, `PaymentRepository`, `FiscalQueueRepository`.
