# UC-018 · Proves pendents d'implementació

Aquest document és el backlog verificable del bescanvi de regal. Cap prova de compra UC-017 substitueix aquestes proves.

## 1. Servei de domini

- [ ] `preview` amb GIFT vàlid i origen pagat.
- [ ] codi desconegut retorna error neutre.
- [ ] codi alterat no revela dades.
- [ ] dret `EXPIRED` deriva a UC-18a.
- [ ] dret `CANCELLED` rebutjat.
- [ ] dret `CONSUMED` amb mateix idempotency/payload retorna `REUSED`.
- [ ] dret `CONSUMED` amb destí diferent retorna `CONFLICT`.
- [ ] titular/beneficiari no autoritzat rebutjat.
- [ ] `preview` no muta cap taula.

## 2. Persistència i concurrència

- [x] lock `FOR UPDATE` sobre el dret.
- [x] dues peticions concurrents produeixen un únic consum.
- [x] event `RESERVE` append-only.
- [x] event `CONSUME` append-only.
- [ ] event `RELEASE` quan falla l'alta abans del consum.
- [ ] cap update/delete d'events.
- [ ] replay equivalent recupera la mateixa operació/inscripció.

## 3. Inscripció

- [ ] crea una única matrícula pel beneficiari.
- [ ] reintent després de timeout no crea matrícula duplicada.
- [ ] alta fallida no deixa el dret consumit silenciosament.
- [ ] alta creada + error posterior queda reconciliable.
- [ ] canvi de curs posterior conserva origen del regal.

## 4. Economia

- [ ] regal de 100 € aplicat a curs de 100 €: **0 CHARGE nous** al bescanvi.
- [ ] l'origen monetari conserva el `UUID_PAYMENT` de la compra UC-017.
- [ ] regal 100 € → curs 120 €: només els 20 € reals poden generar cobrament addicional.
- [ ] regal 100 € → curs 80 €: no crear refund/saldo automàtic sense regla aprovada.
- [ ] regal amb compra retornada no és aplicable com si tingués 100 € disponibles.

## 5. Fiscalitat

- [ ] bescanvi simple no crea segona factura.
- [ ] canvi material de servei/import es deriva al classificador corresponent.
- [ ] cap modificació de factura original per “actualitzar” beneficiari.
- [ ] factura del comprador continua immutable.

## 6. Seguretat

- [ ] codi no apareix en URL.
- [ ] codi no apareix en logs/errors.
- [ ] consulta usa hash.
- [ ] CSRF a confirmació.
- [ ] actor/rol resolt al servidor.
- [ ] posseir el codi no permet baixar factura del comprador.
- [ ] resposta a codi desconegut no permet enumeració.

## 7. API/UI

- [ ] preview i confirmació són endpoints/accions diferents.
- [x] doble clic/reintent equivalent reutilitza resultat.
- [ ] errors mostren estat operatiu, no detalls sensibles.
- [ ] estat pendent/reconciliació no es presenta com a èxit.
- [ ] suport/intranet veu timeline sense poder editar estat directament.

## 8. E2E preproducció

- [ ] compra UC-017 confirmada.
- [ ] dret GIFT creat/activat amb origen correcte.
- [ ] beneficiari bescanvia.
- [ ] inscripció queda creada.
- [ ] dret queda consumit una vegada.
- [ ] no hi ha segon `payment_transaction CHARGE`.
- [ ] timeline i correlació reconstruïbles.
- [ ] replay complet no duplica res.
- [x] prova concurrent real amb dos processos/requests.

## 9. Criteri GO

Només GO quan el flux complet pugui demostrar:

```text
1 compra pagada
1 dret GIFT
1 consum
1 inscripció
0 cobraments duplicats
0 factures duplicades
traça completa
```


## 10. Evidència tancada de concurrència — 2026-10-02

- `GiftRedemptionConcurrencyTest::testTwoProcessesRedeemingSameEnrollmentReuseSingleSaga`: PASS.
- `GiftRedemptionConcurrencyTest::testTwoProcessesWithDifferentEnrollmentsAllowOnlyOneDestination`: PASS.
- GitHub Actions `36942440699`: **834 passed · 0 failed**.
- S'ha verificat que no apareixen segon `CHARGE`, segon `CONSUME`, segona compensació ni dues operacions destí per un únic regal.
