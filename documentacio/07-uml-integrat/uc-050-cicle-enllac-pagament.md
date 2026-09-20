# UC-50 · Crear, consultar, desactivar o caducar un enllaç de pagament

**Objectiu canònic:** token segur, caducitat, estat i auditoria. L'enllaç és una **autorització temporal per iniciar una compra/pagament**, no una factura ni una garantia que la caixa hagi rebut diners. UC-33 tracta específicament la revocació i UC-61 la consulta de l'import pendent des de l'alumnat.

## 1. Evidència actual i abast

La migració `2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql` defineix `payment_link` amb `UUID_PAYMENT_LINK`, `UUID_OPERATION`, `TOKEN_HASH` únic, `STATUS` (per defecte `ACTIVE`), `PAYER_PARTY_KEY`, `EXPECTED_AMOUNT`, `CURRENCY`, `EXPIRES_AT`, `REVOKED_AT/BY/REASON`, `REPLACED_BY_UUID` i `LAST_ACCESSED_AT`. La FK apunta a `commercial_operation`; **no hi ha un `UUID_FACTURA` directe** a `payment_link`: cal arribar-hi per operació/relacions.

`RedsysPaymentIntentService::create()` sí que crea una intenció de TPV a partir de `DS_ORDER`, snapshot i import; comprova el reús contradictori de la mateixa ordre. **No és** un servei d'emissió, lectura o revocació de `payment_link`. En els serveis PHP revisats no s'ha acreditat `PaymentLinkService`, validació server-side de token o connexió efectiva del portal d'alumnat amb aquesta taula. `TOKEN_HASH` en SQL per si sol no controla autenticació, reutilització o filtració de la URL.

## 2. Contracte funcional específic

| Acció | Condicions i resultat |
| --- | --- |
| Crear | Validar actor, `UUID_OPERATION`, titular/pagador, import **pendent real** derivat de factura i assignacions, moneda, oferta acceptada i venciment. Generar token aleatori opac, guardar-ne només hash i comunicar el secret una sola vegada al destinatari autoritzat; el mecanisme de generació/custòdia **no està implementat al servei SIF revisat**. |
| Consultar | Comprovar token/permís, `STATUS`, venciment, pagador/operació, import actual i enllaç substitutiu. Evitar que consultar un token reveli matrícula, NIF o documents d'una altra persona. Registrar `LAST_ACCESSED_AT`/event quan la ruta real estigui integrada. |
| Iniciar TPV | Revalidar estat i import **immediatament abans** de crear la intenció UC-63: un enllaç emès per 100 € no pot iniciar una nova captura de 100 € si ja s'han ingressat 60 € i el pendent real és 40 €, sense nova oferta autoritzada. |
| Revocar/caducar | Canviar estat amb versió, actor, motiu i moment; impedir **noves iniciacions** des del token. `REPLACED_BY_UUID` permet referenciar un enllaç renovat, però la lògica de transició/revocat no està acreditada. |
| Efecte fiscal | Crear o revocar enllaç **no modifica** `factura`, `factura_registres`, numeració, cadena, total ni receptor. Una factura emesa abans de cobrar continua existint quan caduqui la URL. |
| Efecte monetari | Cap `CHARGE` per crear, visualitzar, revocar o caducar. Un pagament real es registra només mitjançant el callback/worker o via de cobrament acreditada, amb identificació única del moviment i fons individuals quan correspongui. |

### Flux objectiu

1. L'operador/portal identifica l'operació i la persona legitimada, consulta factura i pagaments **reals** i classifica si l'operació ja està pagada, cancel·lada o en curs.
2. El gestor pendent crea/reutilitza un enllaç per petició idempotent, token hash, import i caducitat; **la taula no té clau idempotent per enllaç** diferent del hash únic. Cal definir unicitat lògica de l'operació/versió per evitar múltiples URLs actives contradictòries.
3. En visitar-lo, el servidor verifica hash/estat/venciment i torna a calcular el pendent; un token caducat o revocat **no redirigeix a Redsys**. El navegador no decideix l'import final.
4. En confirmar pagament, es congela snapshot coherent UC-112 i es crea intenció UC-63; el callback UC-03 només valida signatura, import, divisa i terminal de la **intenció**, no l'estat actual de `payment_link`. Cal integrar explícitament aquests controls abans d'acceptar una nova captura.
5. Després de confirmar el moviment real, s'actualitza estat de l'enllaç (consumit o parcial, segons política), es conserva evidència de l'operació i s'evita capturar una altra vegada pel mateix deute.
6. En revocació/renovació, preservar l'operació i factura; si arriba **després** un callback d'una intenció iniciada mentre l'enllaç era vàlid, cal conciliar l'ingrés existent en comptes d'esborrar-lo o emetre una factura duplicada.

### Alternatives de prova

| Escenari | Control esperat |
| --- | --- |
| URL reenviada a persona no autoritzada | No revelar dades/factura; definir autenticació addicional segons política i risc. |
| Dos clics sobre el mateix token | Una única intenció/comanda equivalent, sense doble captura o dues matrícules. |
| Enllaç antic de 100 € després de pagament parcial de 60 € | No cobrar 100 € de nou; nova proposta/enllaç per import pendent real de 40 €, si és correcte. |
| Revocació amb Redsys ja iniciat | Bloquejar nous inicis i conciliar callback tardà; revocar URL **no desfà** una autorització bancària en tràmit. |
| Nova oferta després d'expiració | UC-121: nou snapshot, plaça i consentiment quan correspongui; no reutilitzar `DS_ORDER` amb dades diferents. |

**Pendents:** servei de token, permisos i consulta real, criteri de múltiples enllaços, lock de saldo, ús parcial, integració de validació al TPV, idempotència i proves de callbacks/revocació concurrents.

## 3. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Pagador autoritzat" as P
actor "Gestió" as G
rectangle "SIF · enllaç de pagament" {
 usecase "UC-50\nGestionar enllaç" as Main
 usecase "Crear token i venciment" as Create
 usecase "Verificar token i pendent" as Read
 usecase "UC-63\nIniciar TPV autoritzat" as Pay
 usecase "UC-33\nRevocar enllaç" as Revoke
}
G --> Main
P --> Read
P --> Pay
Main ..> Create : <<include>> (en crear)
Main ..> Read : <<include>> (en consultar)
G --> Revoke
@enduml
```

## 4. UML de classes — SQL existent vs PHP real

```mermaid
classDiagram
class PaymentLinkService {
 <<DISSENY: no acreditat>>
 +issue(uuidOperation,actor,expiresAt) link
 +resolve(token,actor) offer
 +revoke(uuidLink,reason) result
}
class PaymentLinkRepository {
 <<DISSENY: taula SQL definida>>
 +findByTokenHash(db,hash) link
 +createOrReuse(db,command) link
 +transition(db,uuid,status) result
}
class RedsysPaymentIntentService {
 <<PHP existent, no valida payment_link>>
 +create(db,input) array
}
class PaymentService {
 <<PHP existent: registra ingrés real>>
 +registerPayment(payload) array
}
PaymentLinkService --> PaymentLinkRepository : token i estat
PaymentLinkService ..> RedsysPaymentIntentService : nova intenció si token vigent [pendent]
```

## 5. UML de seqüència — intent amb revocació concurrent (DISSENY/PARCIAL)

```mermaid
sequenceDiagram
actor P as Pagador
actor G as Gestió
participant L as PaymentLinkService [DISSENY]
participant DB as payment_link [SQL definit]
participant I as RedsysPaymentIntentService [PHP]
participant R as Redsys/worker UC-03
G->>L: Crear URL per operació i pendent acreditat
L->>DB: Guardar TOKEN_HASH, import i caducitat
L-->>P: Token opac per canal autoritzat
P->>L: Obrir token i sol·licitar pagament
L->>DB: Comprovar estat i venciment + saldo actual
alt Caducat, revocat o import desactualitzat
 L-->>P: No iniciar nova captura; revisió/enllaç nou
else Actiu i oferta coherent
 L->>I: create(DS_ORDER,import,snapshot)
 I-->>P: Intenció pendent/redirecció
 G->>L: Revocar URL quan TPV ja s'havia iniciat
 L->>DB: Revocar per a nous inicis
 opt Redsys confirma la intenció ja iniciada
  R-->>L: Pagament real UC-03
  L->>DB: Correlacionar cobrament sense recuperar URL revocada
 end
end
Note over L,I: La revocació no anul·la per si sola una operació bancària existent
```

## 6. Traçabilitat

[UC-50 original](../06-fitxes-funcionals/uc-050.md) · [UC-33 revocació original](../06-fitxes-funcionals/uc-033.md) · [UC-61 pendent original](../06-fitxes-funcionals/uc-061.md) · [UC-121 renovació](uc-121-repreuar-renovar-reserva-caducada.md) · [UC-112 snapshot](uc-112-congelar-snapshot-abans-tpv.md) · [UC-03 cobrament](uc-003-processar-cobrament-redsys-asincron.md) · [Migració payment_link](../../sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql) · [RedsysPaymentIntentService](../../sif/src/Service/RedsysPaymentIntentService.php) · [RedsysCallbackService](../../sif/src/Service/RedsysCallbackService.php).
