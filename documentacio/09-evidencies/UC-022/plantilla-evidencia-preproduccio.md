# UC-022 · Evidència de test/preproducció

> Plantilla. No marcar cap cas com PASS sense resultat observat.

## Identificació

- Data/hora:
- Entorn:
- Host intranet:
- Host SIF:
- Commit/SHA:
- Operador:
- Factura de prova: **guardar identificador només si la política interna ho permet; preferentment hash**
- Total inicial:
- Estat inicial SIF:
- Total pagat legacy inicial:

## Preflight

- Fitxer JSON:
- Resultat:
- Observacions:

## Resultats

| Prova | Esperat | Resultat | Evidència | Estat |
|---|---|---|---|---|
| T01 parcial CREATED | 1 payment, PARTIAL, projecció parcial | | | PENDENT |
| T02 retry REUSED | mateix payment, cap doble projecció | | | PENDENT |
| T03 CONFLICT | 409, cap mutació | | | PENDENT |
| T04 completar | nou payment, PAID | | | PENDENT |
| T05 TPV separat | bloqueig manual | | | PENDENT |
| T06 PENDING_RETRY | payment confirmat + sync recuperable | | | PENDENT |

## Comprovacions d'invariants

- [ ] El mateix event bancari equivalent no crea un segon `payment_transaction`.
- [ ] El mateix event amb payload diferent produeix conflicte.
- [ ] La projecció legacy no modifica `factures`.
- [ ] `SUM(inscripcions.PAGAMENT)` concorda amb l'import projectable confirmat.
- [ ] Existeix `payment_action_event` per CREATE/REUSE i SYNC_LEGACY.
- [ ] Existeix `operational_event` per REGISTER_MANUAL_TRANSFER.
- [ ] Existeix `sif_audit_event`.
- [ ] Les notificacions queden a `notification_outbox`.
- [ ] Cap secret HMAC apareix als fitxers d'evidència.

## Incidències

Documentar qualsevol desviació amb:
- request/correlation id;
- estat SIF;
- estat legacy;
- error;
- acció de recuperació;
- evidència que el reintent no duplica el cobrament.

## Decisió

- [ ] VERIFIED_PREPRODUCTION
- [ ] PENDENT
- [ ] BLOQUEJAT

Motiu:
