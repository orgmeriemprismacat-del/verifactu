# UC-007 · Matriu de traçabilitat i estats — 2026-10-03

| Requisit / invariant | Codi | UML | Prova/evidència | Estat |
| --- | --- | --- | --- | --- |
| UUID és identitat canònica | validator/repository/query service + JS deep link | seq S1-S3 | InvoiceQueryServiceTest + boundary test | IMPLEMENTAT / VERIFICACIÓ CI PENDENT |
| Consulta no muta fiscal/econòmic | InvoiceReadRepository/QueryService | classes + S2 | InvoiceQueryServiceTest | IMPLEMENTAT; runtime pendent |
| Criteris whitelist/exactes | InvoiceQueryCriteriaValidator | S1 | tests query | IMPLEMENTAT |
| Actor/rol no ve del navegador | Legacy read context + SifAuthenticatedActor + HMAC | S1 | InternalApiAuthenticatorTest + boundary | IMPLEMENTAT |
| Scope FULL/MINIMAL fail-closed | resolver + visibility policy | classes/S1 | ResolvedInvoiceVisibilityPolicyTest | IMPLEMENTAT |
| Estat factura/cobrament/AEAT separats | projection + UI | S2 | tests servei | IMPLEMENTAT |
| Original/rectificativa separats | read repository rectifications | S2 | tests servei + runtime pendent | IMPLEMENTAT PARCIAL |
| Metadata document sense path físic | findDocumentMetadata | classes/S5 | test servei | IMPLEMENTAT |
| Bytes només via UC-080 | sifDocument + document endpoint/service | S5 | boundary + runtime pendent | IMPLEMENTAT PARCIAL |
| Hash físic verificat abans stream | PrivateDocumentStore/InvoiceDocumentAccessService | S5 | runtime pendent | IMPLEMENTAT / NO VERIFICAT ENTORN |
| Accés document auditat | FiscalDocumentAccessRepository | S5 | runtime pendent | IMPLEMENTAT |
| AL-16 deep link UUID | alumnes-mostrar-alumne.js + alumnes-factura.js | S3 | Uc007IntranetBoundaryTest | CORREGIT/PROTEGIT |
| AL-17 modal SIF | alumnes-mostrar-alumne.js | S4 | boundary + E2E pendent | IMPLEMENTAT |
| Fallback download POST | descarregaFactura.php + JS font | activitat fallback | boundary | CORREGIT; E2E pendent |
| Paginació fallback conserva handlers | JS font | activitat fallback | boundary | CORREGIT |
| Una sola implementació JS per pàgina | includes PHP | inventari | boundary | CORREGIT |
| Feature flags fail explicit | sifFactures/sifDocument | S1/S5 | runtime pendent | IMPLEMENTAT |
| Bloqueig mutació llegada sobre SIF | SifLegacyInvoiceMutationGuard | fallback | runtime pendent | IMPLEMENTAT PARCIAL |
| Canals externs alumne/empresa | UC-102/126 | FINAL classes | no | PENDENT |
| Retirada fallback | n/a | FINAL activitats | no | PENDENT |

## Lectura dels estats

- **DOCUMENTAT:** contracte i traça presents.
- **IMPLEMENTAT:** hi ha codi que satisfà el contracte estàticament.
- **VERIFICAT ESTÀTICAMENT:** s'ha contrastat el codi/asset que s'executa.
- **VERIFICACIÓ CI PENDENT:** hi ha prova automatitzada però encara cal resultat del run del PR.
- **RUNTIME PENDENT:** necessita BD/storage/rol/navegador de test/preproducció.
