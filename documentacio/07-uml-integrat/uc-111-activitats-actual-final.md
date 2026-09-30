# UC-111 · Diagrames d'activitat ACTUAL / FINAL

**Objectiu:** tenir les activitats separades per pàgina/apartat i per estat ACTUAL/FINAL, en lloc de dependre només del document UML integrat.

## 1. Web d'inscripció JASOM · ACTUAL contrastat

```plantuml
@startuml
title UC-111 | Web inscripció | ACTUAL contrastat
start
:Rebre dades d'inscripció, curs, preu,
descompte ordinari i opció novell;
:Validar camps legacy;
:Calcular preu segons regles actuals;
:INSERT inscripcions;
if (CURS = JASOM i novell = sí?) then (sí)
  :INSERT recent_titulat(ID_INSC);
  :VALIDAT queda pendent (0);
  :Informar que secretaria revisarà la titulació;
  :Permetre pujada del resguard;
else (no)
  :No crear recent_titulat en aquesta ruta;
endif
if (S'ha aplicat promoció/descompte ordinari?) then (sí)
  :Persistir/consumir segons circuit legacy independent;
endif
:Continuar comunicacions d'alta;
stop
@enduml
```

## 2. Web d'inscripció JASOM · FINAL

```plantuml
@startuml
title UC-111 | Web inscripció JASOM | FINAL
start
:Autenticar o identificar participant;
:Validar curs/edició i quote de preu;
if (Sol·licita docent novell?) then (sí)
  if (Producte és JASOM?) then (no)
    :Rebutjar opció novell al servidor;
    stop
  endif
  :Crear/reutilitzar commercial_operation JASOM;
  :Congelar participant, preu i versió de regla;
  :Crear/reutilitzar expedient PENDING_VALIDATION;
  :Rebre evidència amb storage privat,
MIME/mida/hash i owner;
  :Mantenir pagament BLOQUEJAT mentre PENDING;
else (no)
  :Continuar checkout JASOM ordinari;
endif
:No concedir cap dret en l'alta;
stop
@enduml
```

## 3. Pujada de justificació · ACTUAL / FINAL

```plantuml
@startuml
title UC-111 | Evidència docent novell | ACTUAL vs FINAL
start
partition "ACTUAL legacy" {
  :POST fitxer + camps;
  :Construir nom derivat de dades rebudes;
  :move_uploaded_file a ruta legacy;
  :Preparar comunicació;
}
partition "FINAL requerit" {
  :Autenticar titular/operació;
  :Validar mida, MIME i tipus;
  :Generar identificador opac;
  :Desar fora d'accés públic;
  :Persistir hash, owner, retenció i versió;
  :Permetre esmena sense destruir evidència anterior;
  :Lectura només per rol autoritzat;
}
stop
@enduml
```

## 4. Intranet alumnes-validar-descomptes · ACTUAL contrastat

```plantuml
@startuml
title UC-111 | Intranet validar docent novell | ACTUAL contrastat
start
:Carregar mostrarMain.php;
:Mostrar files recent_titulat pendents;
:Operador alterna Sí/No només al DOM;
if (Clica "validar"?) then (sí)
  :JS llegeix ID_INSC + estat visual;
  :GET sendMsgValidatProfessorNovell.php;
  :Deserialitzar sessió i delegar a Intranet;
  :Carregar inscripció/curs/preu/dades;
  if (verificat = 1?) then (sí)
    :UPDATE recent_titulat.VALIDAT = 1;
    :Preparar correu d'aprovació + opcions de pagament;
  else (no)
    :UPDATE recent_titulat.VALIDAT = 2;
    :Preparar correu de denegació + opcions de pagament;
  endif
  :Enviar correu a alumne i secretaria;
  if (Resposta textual conté "error"?) then (sí)
    :Mostrar modal error;
  else (no)
    :Mostrar "Canvi aplicat";
  endif
endif
stop
@enduml
```

## 5. Intranet alumnes-validar-descomptes · FINAL

```plantuml
@startuml
title UC-111 | Intranet validar docent novell | FINAL
start
:Autenticar secretaria i comprovar rol/abast;
:Carregar operació, participant, evidències i estat;
:Mostrar PENDING / VALIDATED / REJECTED;
:Comprovar títol, titular i data d'expedició
respecte DATAI de JASOM;
if (Cal esmena documental?) then (sí)
  :Registrar requeriment i instant d'enviament;
  :Calcular venciment 48 h en dies feiners
segons calendari configurat;
  :Mantenir PENDING;
  stop
endif
:Operador tria Aprovar o Denegar + motiu;
:POST segur amb CSRF/idempotència;
:Servidor rellegeix versió i autorització;
if (Conflicte o sense permís?) then (sí)
  :Rebutjar i auditar;
  stop
endif
if (Aprovat?) then (sí)
  :Persistir/projectar VALIDATED + actor/data/regla;
else (no)
  :Persistir/projectar REJECTED + motiu;
endif
:Obrir pagament només després de decisió 1/2;
:Commit;
:Notificar resultat;
note right
  La concessió del benefici NO es fa aquí
  si JASOM encara no està completament pagat.
end note
stop
@enduml
```

## 6. Pagament JASOM · ACTUAL contrastat

```plantuml
@startuml
title UC-111 | Pagament JASOM | ACTUAL contrastat
start
:Obrir PagamentCursAutomatic.php;
if (Hi ha sol·licitud novell?) then (sí)
  :Consultar recent_titulat;
  note right
    La vista legacy té branques inconsistents:
    existència de fila vs VALIDAT i inicialització.
  end note
endif
:Iniciar pagament;
:realitzaPagamentAutomatic.php registra/actualitza cobrament;
if (pendentPagar = 0 i CURS = JASOM?) then (sí)
  :SELECT recent_titulat WHERE ID_INSC=? AND VALIDAT=1;
  if (VALIDAT=1?) then (sí)
    :SELECT últim codi MACABODETITULAR del DNI;
    note right
      Còpia auditada:
      no hi ha INSERT del dret nou aquí
      i el correu antic conté un codi literal.
    end note
    :Preparar text promocional legacy;
  endif
endif
:Enviar confirmació de pagament;
stop
@enduml
```

## 7. Pagament JASOM i concessió · FINAL implementat a la branca

```plantuml
@startuml
title UC-111 | Pagament JASOM -> dret -> codi | FINAL
start
:Processar callback Redsys validat;
:InvoiceService emet/reutilitza factura i payment;
:COMMIT econòmic;
:NovicePromotionInvoiceLinkService vincula factura a operació;
if (Decisió secretaria és VALIDATED?) then (no)
  :No concedir dret;
  stop
endif
if (Totes les F1/F2 JASOM estan ISSUED i PAID?) then (no)
  :Deixar PAYMENT_PENDING;
  stop
endif
:Reconciliar CHARGE confirmats - REFUND;
if (Cash net != total factures o != NET_AMOUNT?) then (sí)
  :Bloquejar concessió i requerir conciliació;
  stop
endif
:NovicePromotionGrantService.issueForOperation();
if (Ja existeix dret d'aquesta operació?) then (sí)
  :Reutilitzar entitlement;
else (no)
  if (La persona ja va rebre benefici novell abans?) then (sí)
    :Conflicte; no crear segon dret;
    stop
  endif
  :Crear commercial_entitlement + novice_promotion_grant;
  :Registrar event ISSUE;
endif
:COMMIT grant;
:NovicePromotionCodePreparationService.prepare();
:Generar token NOV-* aleatori;
:Guardar només hash al dret;
:Xifrar token a outbox amb secret runtime;
:Marcar dret ACTIVE i outbox PREPARED;
:COMMIT preparació de codi;
:No crear CHARGE promocional;
stop
@enduml
```

## 8. Consum del saldo original · FINAL

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

## 9. Canvi de curs · FINAL

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

## 10. Baixa del curs destí i saldo derivat · FINAL

```plantuml
@startuml
title UC-111 | Baixa curs destí -> saldo derivat | FINAL
start
:Identificar origen ACTUAL de la promoció;
if (Origen = aplicació original?) then (sí)
  :Review original + rectificativa;
elseif (Origen = primer traspàs confirmat?) then (sí)
  :Review amb SOURCE_UUID_TRANSFER;
elseif (Origen = derived_application APPLIED?) then (sí)
  :Review fill amb PARENT_UUID_DERIVED_BALANCE;
  :SOURCE_UUID_DERIVED_APPLICATION;
else (segon/tercer traspàs confirmat)
  :PENDENT servei de baixa sobre últim transfer;
  :No activar saldo derivat automàticament;
  stop
endif
:Separar component promocional i diners reals;
:Crear review PENDING amb available=0;
:Obtenir aprovació independent;
:Revalidar rectificativa + titular + JASOM;
:Reconciliar de nou factura i cash actual;
if (Dades han canviat?) then (sí)
  :Bloquejar i recalcular;
  stop
endif
:Tancar predecessor com a històric;
:Activar nou saldo derivat;
:Fixar nou any propi de vigència;
:No restaurar JASOM ni el dret pare consumit;
:Enviar diners reals, si pertoquen, a circuit separat;
stop
@enduml
```

**Implementació de branca:** la baixa directa d'una `novice_promotion_derived_application.APPLIED` usa `NovicePromotionDerivedApplicationCancellationReviewService` i `NovicePromotionDerivedApplicationCancellationActivationService`; el fill conserva `PARENT_UUID_DERIVED_BALANCE` i el predecessor passa a `CONVERTED_TO_DERIVED` sense recreditar el pare.
## 11. Consum del saldo derivat · FINAL

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

## 12. Devolució JASOM · review, freeze i recovery · FINAL

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

## 13. Matriu pàgina/apartat → activitat

| Superfície | ACTUAL | FINAL |
| --- | --- | --- |
| web alta curs | §1 | §2 |
| pujada justificant | §3 | §3 |
| intranet validar descomptes | §4 | §5 |
| pagament JASOM | §6 | §7 |
| checkout curs posterior | consulta legacy | §8 |
| canvi curs | flux general legacy | §9 |
| baixa curs | no canònic | §10 |
| saldo derivat | inexistent com a model canònic | §11 |
| refund JASOM | manual/dispers | §12 |

## 14. Estat

Les activitats FINAL dels §§7–12 corresponen a serveis de la branca, però els adaptadors d'UI/autenticació/pricing/fiscalitat/evidències externes no estan acreditats com a desplegats. **No s'han executat proves MySQL en aquesta auditoria.**

[Fitxes d'acció](../06-fitxes-funcionals/uc-111-accions.md) · [Classes](uc-111-classes-actual-final.md) · [Seqüències](uc-111-sequencies-actual-final.md) · [Dades i estats](uc-111-dades-estats-actual-final.md) · [Traçabilitat](uc-111-tracabilitat-implementacio.md)
