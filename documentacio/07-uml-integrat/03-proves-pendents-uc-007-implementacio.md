# UC-007 · Proves pendents d'implementació

**Decisió 2026-09-29:** les proves runtime/E2E queden documentades per executar-se en una fase posterior. La implementació pot continuar, però cap element d'aquesta llista es marca com a verificat fins disposar d'un entorn de prova/preproducció i evidència.

## 1. Autenticació i autorització

- [ ] Sessió intranet vàlida → actor/rol refrescat des de BD.
- [ ] Rol revocat durant sessió → nova petició denegada.
- [ ] Rol no inclòs a `SIF_INVOICE_FULL_READ_ROLES`/`SIF_INVOICE_MINIMAL_READ_ROLES` → 403.
- [ ] Capçalera HMAC modificada → 401.
- [ ] Body modificat després de signar → 401.
- [ ] Timestamp caducat → 401.
- [ ] `request_id` repetit → bloqueig anti-replay.
- [ ] UUID fora d'abast → denegació sense dades fiscals.
- [ ] Projecció MINIMAL → sense billing, totals, moviments ni documents.

## 2. Cerca

- [ ] NIF/CIF exacte.
- [ ] Email exacte.
- [ ] NUM_VISIBLE exacte.
- [ ] UUID exacte.
- [ ] FACTURA_RELACIONADA llegada.
- [ ] Combinació de criteris = AND.
- [ ] `%` i `_` no actuen com wildcard.
- [ ] Criteri desconegut → 422.
- [ ] Límit server-side.
- [ ] 0 resultats SIF → fallback llegat.
- [ ] Error/403 SIF → no fallback insegur al llegat.

## 3. Consulta read-only

- [ ] Mateix UUID de principi a fi.
- [ ] Línies ordenades.
- [ ] Moviments econòmics separats.
- [ ] Original i rectificativa separats.
- [ ] Últim registre fiscal i estat AEAT coherents.
- [ ] Metadata documental sense `PATH_FITXER`.
- [ ] N consultes → cap INSERT/UPDATE a factura, línies, registre fiscal, cua, pagaments o documents.
- [ ] Només es permet l'escriptura tècnica del guard anti-replay/auditoria.

## 4. UI intranet

- [ ] Resultat SIF mostra badge SIF i estats separats.
- [ ] Detall SIF és només lectura.
- [ ] No apareixen editar/anul·lar/regenerar en el bloc SIF.
- [ ] Valors HTML es renderitzen amb `.text()`, no com markup.
- [ ] Factura SIF amb 0 resultats no impedeix consultar històric llegat.
- [ ] Errors de configuració del pont no exposen secrets al navegador.

## 5. Documents — UC-080 posterior

- [ ] READY + bytes/hash correctes → stream exacte.
- [ ] Metadata sense bytes → unavailable.
- [ ] Hash incorrecte → denegació + incidència.
- [ ] Actor/grant/token revocat → denegació.
- [ ] Original i rectificativa → dos documents independents.
- [ ] Històric original vs reconstruït correctament etiquetat.
- [ ] Cap document fiscal immutable passa per `eliminarArxiu.php`.

## 6. Llegat pendent de regressió

Conservar les proves LEG-UC007 documentades a l'[auditoria detallada](02-auditoria-detallada-uc-007-consultar-factura-estat-document-2026-09-29.md): F01 rols pare/fill, F02 warnings/wildcards/volum/concurrència, F03 CIF/delimitadors, F04 JOIN/ordenació, F05 agrupació/escaping/affectedRows, F06 original+R, F07 doble GET/fitxer/nom, AL-17 multipàgina i AL-18 cleanup.

**Estat:** PENDENT / AJORNAT. No bloqueja continuar desenvolupant, però sí bloqueja marcar UC-007 com a IMPLEMENTAT I PROVAT.
