# UC-111 · Diagrames d'activitat ACTUAL / FINAL

**Objectiu:** tenir les activitats separades per pàgina/apartat i per estat ACTUAL/FINAL, en lloc de dependre només del document UML integrat.

## 1. Web d'inscripció JASOM · ACTUAL observable

```plantuml
@startuml
title UC-111 | Web alta JASOM | ACTUAL observable
start
:Rebre dades d'inscripció i opció novell;
:Crear inscripció legacy;
if (CURS = JASOM i novell?) then (sí)
  :INSERT recent_titulat(ID_INSC);
  :Preparar comunicació de validació futura;
else (no)
  :Continuar flux ordinari;
endif
stop
@enduml
```

## 2. Web d'inscripció JASOM · FINAL/SIF

```plantuml
@startuml
title UC-111 | Web alta JASOM | FINAL objectiu
start
:Autenticar/identificar participant i pagador;
:Crear/reutilitzar CommercialOperation JASOM;
:Congelar participants, curs/edició i preu;
if (Sol·licita docent novell?) then (sí)
  :Crear/reutilitzar expedient PENDING_VALIDATION;
  :Acceptar evidència només per storage segur;
else (no)
  :Continuar checkout sense UC-111;
endif
:No concedir cap dret encara;
stop
@enduml
```

## 3. Pujada de justificació · ACTUAL / FINAL

```plantuml
@startuml
title UC-111 | Justificant docent novell | ACTUAL vs FINAL
start
partition "ACTUAL legacy" {
  :POST fitxer + camps;
  :Construir nom derivat de dades rebudes;
  :move_uploaded_file a ruta legacy;
  :Preparar comunicació;
}
partition "FINAL requerit" {
  :Autenticar titular;
  :Validar mida, MIME i tipus;
  :Generar nom opac;
  :Desar fora d'accés públic;
  :Persistir hash, owner, retenció i audit event;
  :Permetre lectura només a rol autoritzat;
}
stop
@enduml
```

## 4. Intranet · validar docent novell · ACTUAL observable

```plantuml
@startuml
title UC-111 | Intranet validar descompte | ACTUAL observable
start
:Carregar files recent_titulat;
:Operador canvia Sí/No visual;
if (Clica validar?) then (sí)
  :JS llegeix ID_INSC + valor visual;
  :AJAX GET sendMsgValidatProfessorNovell.php;
  :Delegar a Intranet::sendMsgValidatCurosProfessorNovell;
  if (Resposta conté error?) then (sí)
    :Mostrar modal error;
  else (no)
    :Mostrar "Canvi aplicat";
  endif
endif
stop
@enduml
```

## 5. Intranet · validar docent novell · FINAL

```plantuml
@startuml
title UC-111 | Intranet validar docent novell | FINAL
start
:Autenticar secretaria i comprovar rol/abast;
:Carregar evidència mínima i estat actual;
:Mostrar PENDING / VALIDATED / REJECTED;
:Operador tria decisió i motiu;
:POST segur amb CSRF/idempotència;
:Servidor rellegeix expedient i versió;
if (Conflicte o falta permís?) then (sí)
  :Rebutjar i auditar;
  stop
endif
if (Aprovat?) then (sí)
  :Persistir/projectar VALIDATED;
  if (JASOM completament pagat?) then (sí)
    :Concedir/reutilitzar dret únic;
  else (no)
    :Esperar conciliació de cobrament;
  endif
else (no)
  :Persistir REJECTED amb motiu;
endif
:Notificar només després del commit;
stop
@enduml
```

## 6. Pagament JASOM i concessió · FINAL

```plantuml
@startuml
title UC-111 | JASOM pagat -> dret novell únic | FINAL
start
:Rebre/conciliar factura(s) JASOM;
:Verificar VALIDATED;
:Bloquejar operació i titular;
if (Totes F1/F2 ISSUED/PAID?) then (no)
  :No concedir dret;
  stop
endif
:Sumar CHARGE confirmats - REFUND;
if (Cash net = totals factura = NET_AMOUNT?) then (no)
  :Bloquejar i deixar per conciliació;
  stop
endif
if (Ja existeix dret per holder?) then (sí)
  :Reutilitzar dret;
else (no)
  :Crear dret únic amb import elegible;
endif
:Preparar codi de forma xifrada;
:No crear CHARGE promocional;
stop
@enduml
```

## 7. Consum del saldo original · FINAL

```plantuml
@startuml
title UC-111 | Saldo original | reserve / apply / release
start
:Checkout calcula net després d'altres descomptes;
:Persistir quote BEFORE_PROMOTION;
:Bloquejar dret + destí;
if (Titular, vigència i saldo correctes?) then (no)
  :Rebutjar;
  stop
endif
:Reservar import;
:Restar AVAILABLE una sola vegada;
if (Factura/cobrament final es confirma?) then (sí)
  :Validar snapshot final;
  :Marcar APPLIED sense segon dèbit;
else (no)
  if (Sense intent Redsys ni factura?) then (sí)
    :Restaurar saldo;
    :Marcar RELEASED;
  else (no)
    :No restaurar; exigir conciliació;
  endif
endif
stop
@enduml
```

## 8. Canvi de curs · FINAL

```plantuml
@startuml
title UC-111 | Canvi de curs | FINAL
start
:Identificar aplicació o traspàs actual;
:Emetre/registrar rectificativa del curs origen;
:Crear curs successor i quote de preu;
if (Nou net < promoció a traspassar?) then (sí)
  :Bloquejar tractament automàtic;
  :Exigir decisió econòmica/fiscal específica;
  stop
endif
:Crear review PENDING_FISCAL_REVIEW;
:Obtenir aprovació independent;
if (Aprovació concorda?) then (no)
  :No confirmar;
  stop
endif
:Verificar factura final del curs nou + cash residual;
:Marcar predecessor històric;
:Confirmar traspàs amb el MATEIX import;
:No tornar a consumir saldo;
stop
@enduml
```

## 9. Baixa del curs destí i saldo derivat · FINAL

```plantuml
@startuml
title UC-111 | Baixa curs destí -> saldo derivat | FINAL
start
:Identificar origen ACTUAL de la promoció;
note right
  Aplicació original,
  aplicació derivada o
  traspàs confirmat.
end note
:Registrar rectificativa real;
:Separar component promocional i diners reals;
:Crear review PENDING amb available=0;
:Obtenir aprovació independent;
:Reconciliar de nou factura i cash;
if (Dades han canviat?) then (sí)
  :Bloquejar i recalcular;
  stop
endif
:Tancar predecessor com a històric;
:Activar nou saldo derivat;
:Fixar nou any propi de vigència;
:Enviar diners reals, si pertoquen, a circuit separat;
stop
@enduml
```

## 10. Consum del saldo derivat · FINAL

```plantuml
@startuml
title UC-111 | Saldo derivat | N consums parcials
start
:Seleccionar saldo ACTIVE per titular autenticat + UUID intern;
:Validar vigència pròpia i JASOM arrel encara pagat;
:Persistir quote BEFORE_DERIVED_PROMOTION;
:Reservar import parcial;
:AVAILABLE_PROMOTIONAL_AMOUNT -= import;
if (Factura final i residual conciliats?) then (sí)
  :Marcar derived_application APPLIED;
else (no)
  if (Sense intenció ni factura?) then (sí)
    :Restaurar import al mateix saldo;
    :Marcar RELEASED;
  else (no)
    :Bloquejar release fins conciliació;
  endif
endif
stop
@enduml
```

## 11. Devolució JASOM · review, freeze i recovery · FINAL

```plantuml
@startuml
title UC-111 | Refund JASOM | FINAL modelat en branca
start
:Bloquejar arrel i projectar tota la procedència;
if (Hi ha reserves pendents o graf inconsistent?) then (sí)
  :No obrir execució;
  stop
endif
:Crear review + fingerprint del pla;
:Freeze de noves reserves;
:Esperar decisió/evidència refund origen;
if (Refund JASOM no confirmat?) then (sí)
  :Rebutjar/cancel·lar review i desfer hold segons workflow;
  stop
endif
:Cancel·lar romanents vius;
:Crear recovery item només per promoció actualment aplicada;
:No incloure predecessors REPLACED/TRANSFERRED/CONVERTED;
while (Queden recovery items pendents?)
  :Esperar evidència de recovery o waiver autoritzat;
  :Marcar RECOVERED / WAIVED / CANCELLED;
endwhile
:Tancar workflow amb resum i evidència;
stop
@enduml
```

## 12. Matriu pàgina/apartat → activitat

| Superfície | ACTUAL | FINAL |
| --- | --- | --- |
| web alta curs | §1 | §2 |
| pujada justificant | §3 | §3 |
| intranet validar descomptes | §4 | §5 |
| pagament JASOM | parcial/dispers | §6 |
| checkout curs posterior | consulta legacy | §7 |
| canvi curs | flux general legacy | §8 |
| baixa curs | no canònic | §9 |
| saldo derivat | inexistent com a model canònic | §10 |
| refund JASOM | manual/dispers | §11 |

## 13. Estat

Les activitats FINAL dels §§6–11 corresponen a serveis de la branca, però els adaptadors d'UI/autenticació/pricing/fiscalitat/evidències externes no estan acreditats com a desplegats. **No s'han executat proves MySQL en aquesta auditoria.**

[Fitxes d'acció](../06-fitxes-funcionals/uc-111-accions.md) · [Classes](uc-111-classes-actual-final.md) · [Seqüències](uc-111-sequencies-actual-final.md) · [Traçabilitat](uc-111-tracabilitat-implementacio.md)
