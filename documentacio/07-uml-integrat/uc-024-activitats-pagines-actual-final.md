# UC-024 — Activitats ACTUAL / FINAL per pàgina i apartat

**Revisió:** 03/10/2026  
**Etiqueta ACTUAL:** codi localitzat al repositori; no prova desplegament.  
**Etiqueta FINAL:** contracte objectiu.

## 0. Superfícies

| ID | Superfície | Estat UC-024 |
| --- | --- | --- |
| P01 | Primera reclamació de pagament | Llegat, comunicació/estat; no registra CHARGE SIF |
| P02 | Recordatori pagament final | Llegat, comunicació/estat; no registra CHARGE SIF |
| P03 | Reclamació final i baixa | Llegat, decisió/comunicació; no registra CHARGE SIF |
| P04 | Control morosos | Llegat, reclamacions mensuals; no registra CHARGE SIF |
| P05 | CLI preview claim payment | SIF executable, no muta |
| P06 | CLI process claim payment | SIF executable, muta en no-producció |
| P07 | Bridge + API interna UC-024 | Implementat en branca; feature flag OFF per defecte; preproducció pendent |

## 1. P01 — Primera reclamació

```plantuml
@startuml
title P01 ACTUAL — primera reclamació
start
:Obrir pàgina i comprovar sessió;
:mostrarMain comprova permís de visualització;
:Consultar cnsReclamacions;
:Operador marca files;
:POST idInsc a updDadesPrimeraReclamacio.php;
:Carregar inscripció/curs;
:Generar URL de pagament legacy des d'IDPAG;
:Actualitzar reclamat/data_reclamacio;
:Enviar SMTP directe;
note right
  No ClaimPaymentService.
  Endpoint de mutació sense guard CSRF/rol propi localitzat.
end note
stop
@enduml
```

## 2. P02 — Recordatori final

```plantuml
@startuml
title P02 ACTUAL — recordatori fi de curs
start
:Carregar casos amb pendent;
:Mostrar import pendent i fase;
:Operador confirma;
:POST idInsc;
:Actualitzar data/observacions de reclamació;
:Generar URL legacy;
:Enviar correu directe a persona/responsable;
stop
@enduml
```

**Nota de reconciliació:** a `main` hi havia un handler antic `#upd-baixes → confirmaReclamacio()` sense funció definida. Aquesta branca l’elimina; el flux útil queda a `#confirma-reclamacio → confirmaRecordatori()`.

## 3. P03 — Reclamació final / baixa

```plantuml
@startuml
title P03 ACTUAL — reclamació final
start
:Consultar cursos/casos pendents;
:Mostrar pendent, certificat i estat de reclamació;
:Operador selecciona;
:POST idInsc a updLastClaimPay.php;
:Decidir branca aprovat/no aprovat;
:Actualitzar llegat i comunicar;
note right
  La baixa/estat acadèmic és separat
  de l'estat fiscal i econòmic SIF.
end note
stop
@enduml
```

## 4. P04 — Control morosos

```plantuml
@startuml
title P04 ACTUAL — control morosos
start
:Separar entitats / alumnes sense certificat / alumnes amb certificat;
:Consultar casos;
if (Han passat >=30 dies des de darrera reclamació?) then (sí)
 :Permetre marcar reclamació;
 :POST idInsc a endpoint corresponent;
 :Actualitzar reclamat/observacions;
 :Enviar SMTP directe;
else (no)
 :Bloquejar botó de reclamació;
endif
stop
@enduml
```

## 5. P05 — Preview SIF

```plantuml
@startuml
title P05 ACTUAL — preview-claim-payment.php
start
:Exigir CLI;
:Refusar SIF_ENV=production;
:Localitzar factura;
:Construir payload CLAIM_PAYMENT;
:Mostrar JSON dry_run;
note right
  No PaymentService.
  No registra cap moviment.
end note
stop
@enduml
```

## 6. P06 — Process SIF

```plantuml
@startuml
title P06 ACTUAL — process-claim-payment.php
start
:Exigir CLI;
:Refusar SIF_ENV=production;
:Localitzar factura;
:Construir PaymentService i ClaimPaymentService;
:Registrar CHARGE/CLAIM_PAYMENT;
:Retornar JSON;
stop
@enduml
```

## 7. P07 — BRANCA IMPLEMENTADA / intranet canònica

```plantuml
@startuml
title P07 BRANCA — registrar cobrament reclamat
start
:Clicar Registrar cobrament si feature flag activa;
:Bridge valida sessió, Same-Origin/AJAX, permís i CSRF;
:Derivar claim_case_id i actor server-side;
:API HMAC resol factura + IDPAG des de fact_rels;
:Introduir identificador extern tipificat de l'ingrés;
:Resolver deduplicació intercanal;
if (Ingrés ja registrat?) then (sí)
 :Vincular UUID_PAYMENT existent a l'expedient;
else (no)
 if (Ingrés acreditat i import admès?) then (sí)
  :Registrar CHARGE amb clau idempotent per ingrés;
 else (no)
  :PENDING_REVIEW/CONFLICT;
  stop
 endif
endif
:Persistir actor/correlació/resultat LINK_CLAIM_PAYMENT;
:Commit SIF;
:Projectar net de factura al legacy amb guard de baseline/delta;
:Auditar SYNC_LEGACY;
:Retornar estat tipificat o requires_reconciliation;
note right
  Outbox de correus encara no forma part
  de la nova mutació econòmica.
end note;
stop
@enduml
```

## 8. Cobertura per pàgina

| Superfície | Consulta | Mutació llegada | SIF payment | Autorització mutació acreditada | Correu outbox |
| --- | --- | --- | --- | --- | --- |
| P01 | Sí | Sí | Sí amb feature flag | Sí al bridge | No |
| P02 | Sí | Sí | Sí amb feature flag | Sí al bridge | No |
| P03 | Sí | Sí | Sí amb feature flag | Sí al bridge | No |
| P04 | Sí | Sí | Sí amb feature flag | Sí al bridge | No |
| P05 | Sí | No | No | CLI/no-prod | N/A |
| P06 | Sí | Sí | Sí | CLI/no-prod | N/A |
| P07 BRANCA | Sí | Sí | Sí | Implementat | No; correu llegat separat |
