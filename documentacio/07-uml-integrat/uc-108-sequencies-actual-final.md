# UC-108 — Diagrames de seqüència ACTUAL i FINAL

**Data:** 29/09/2026  
**Regla:** ACTUAL descriu el codi versionat; FINAL descriu l'objectiu i no acredita implementació.

## 1. SEQ-108-ACTUAL · Alta web llegada

```mermaid
sequenceDiagram
autonumber
actor P as Participant
participant JS as mostrarInscripcionsTastets.js
participant FORM as mostrar_inscripcio_tastets.php
participant CODE as obtenirCodiTastet.php
participant DUP as buscarSiHaRealitzatElTastet.php
participant SUB as enviarInscripcioTastet.php
participant DB as MySQL llegat
participant SMTP as MailSMTPComvive
participant CONF as Confirmació

P->>FORM: GET pàgina/formulari
FORM->>DB: resol URL + SELECT repte actiu
DB-->>FORM: CODI_CURS + TITOL
FORM-->>JS: HTML formulari

par Resolució auxiliar asíncrona
  JS->>CODE: GET url
  CODE->>DB: tornar a resoldre URL + CODI_CURS
  DB-->>CODE: codi
  CODE-->>JS: codiCurs
and Formulari ja operatiu
  JS-->>P: botó Enviar actiu
end

P->>JS: Enviar dades
JS->>DUP: GET doc + codiCurs
DUP->>DB: SELECT DATA_INSC WHERE CURS+DNI+INSC_CURS=1

alt coincidència
  DUP->>DB: SELECT TITOL
  DUP-->>JS: titol|data
  JS-->>P: modal bloquejant "Tanca"
else cap coincidència
  DUP-->>JS: buit
  JS->>SUB: GET PII + mailing=yes + codiCurs

  SUB->>DB: SELECT repte ESTAT=1
  SUB->>DB: llegir paràmetres SMTP/xifrat

  SUB->>SMTP: intents interns PRE-INSERT
  Note over SMTP,SUB: send() pot retornar false i el resultat s'ignora

  SUB->>DB: INSERT inscripcions_reptes
  DB-->>SUB: id inserit
  SUB->>SUB: generar token
  SUB-->>JS: echo token

  SUB->>SMTP: intents POST-INSERT
  SUB->>DB: SELECT/INSERT mailing
  SUB->>DB: blocs residuals població/promoció
  SUB->>SMTP: correu participant

  JS->>CONF: redirect amb token
end
```

### Riscos visibles a la seqüència ACTUAL

- cursa perquè `codiCurs` es resol en segon AJAX sense bloquejar el botó;
- GET mutador amb PII;
- precheck de duplicat separat de l'INSERT;
- correus abans de persistir;
- efectes posteriors després de l'`echo token`;
- resultat SMTP ignorat;
- cap `request_id` idempotent.

## 2. SEQ-108-FINAL-A · Sol·licitud web

```mermaid
sequenceDiagram
autonumber
actor P as Participant
participant WEB as Web
participant S as FreeSampleRequestService
participant CAT as FreeSampleCatalogRepository
participant R as FreeSampleRequestRepository
participant M as CommercialSubscriptionGateway
participant O as NotificationOutbox
participant T as ConfirmationTokenService
participant C as CommercialOperationRepository (opcional)

P->>WEB: POST /tastets/{slug}/requests + idempotency_key
WEB->>S: submit(command)
S->>CAT: findActiveBySlug(slug)

alt tastet inexistent/inactiu
  CAT-->>S: no disponible
  S-->>WEB: INVALID_PRODUCT
  WEB-->>P: no disponible
else tastet actiu
  CAT-->>S: sample
  S->>R: findByPersonAndSample(person,sample)

  alt pendent existent
    R-->>S: PENDING
    S-->>WEB: DUPLICATE_PENDING + request_id
  else accés actiu
    R-->>S: ACTIVE
    S-->>WEB: ALREADY_ENROLLED + request_id
  else caducat sense desbloqueig
    R-->>S: EXPIRED_LOCKED
    S-->>WEB: ACCESS_EXPIRED
  else baixa/denegació o nou
    S->>R: createOrReusePending(idempotency_key)
    R-->>S: request_id

    S->>M: ensureSubscriptionForRequest(request_id, person)
    Note over S,M: alta comercial obligatòria del tastet; retry idempotent; no reactivar baixa per simple retry

    opt DEC-108-06 = integrar al SIF
      S->>C: createOrReuseFreeSample(request_id)
      C-->>S: UUID_OPERATION
    end

    S->>O: enqueueOperationalNotice(request_id)
    S->>T: issue(request_id, expiració)
    T-->>S: token
    S-->>WEB: REQUEST_RECEIVED + request_id + token
  end

  WEB-->>P: Sol·licitud rebuda
end
```

**Important:** aquesta seqüència acaba en `REQUEST_RECEIVED`. No espera secretaria ni Moodle.

## 3. SEQ-108-FINAL-B · Tramitació manual per secretaria

```mermaid
sequenceDiagram
autonumber
actor SEC as Secretaria
participant R as Registre UC-108
participant MDL as Moodle/Campus
participant MAIL as Plantilla manual d'accés

SEC->>R: consultar peticions pendents
R-->>SEC: petició UC-108

SEC->>MDL: localitzar/crear usuari
alt compte nou
  MDL-->>SEC: compte creat
  MDL-->>SEC: campus dispara correu automàtic de credencials
else compte existent
  MDL-->>SEC: conservar credencials
end

SEC->>MDL: activar accés al tastet
SEC->>MDL: venciment = activació + 7 dies exactes, mateixa hora
MDL-->>SEC: activació efectiva

SEC->>R: registrar/verificar activació i venciment
SEC->>MAIL: preparar plantilla existent
SEC->>MAIL: enviar manualment avís "accés activat"

Note over SEC,MAIL: la plantilla concreta encara s'ha de localitzar/contrastar al repositori o procediment real
```

## 4. SEQ-108-FINAL-C · Incidència de credencials

```mermaid
sequenceDiagram
autonumber
actor P as Participant
participant SEC as Secretaria
participant ISA as Isa / suport tècnic
participant DEV as Desenvolupament / Meriem
participant MDL as Moodle/Campus
participant MAIL as Plantilla manual adaptada

P->>SEC: no pot accedir amb credencials
SEC->>MDL: reenviar/regenerar credencials

alt resolt per secretaria
  MDL-->>SEC: accés recuperat
else no resolt
  SEC->>ISA: escalar
  alt resolt per Isa
    ISA-->>SEC: resolució
  else persisteix
    ISA->>DEV: escalar
    DEV-->>SEC: resolució
  end
end

SEC->>MDL: fixar nou venciment = resolució + 7 dies exactes, mateixa hora
SEC->>MAIL: reutilitzar mateixa plantilla amb missatge de pròrroga
MAIL-->>P: "tindràs 7 dies d'accés" sense data exacta

Note over SEC,DEV: no es crea una nova inscripció ni un tracker UC-108 específic en la fase actual
```

## 5. SEQ-108-FINAL-D · Repetició després d'expiració

```mermaid
sequenceDiagram
autonumber
actor P as Participant
actor SUP as Secretaria/Suport
participant R as Registre UC-108
participant WEB as Web

P->>SUP: demana tornar a fer el tastet
SUP->>R: desbloquejar persona+tastet
R-->>SUP: desbloqueig registrat
SUP-->>P: pot tornar a enviar el formulari
P->>WEB: nova sol·licitud web
Note over P,WEB: nova petició, historial anterior conservat
```

## 6. Estat

- **Documentat:** sí.
- **Implementat:** només la seqüència ACTUAL llegada.
- **Verificat en runtime:** no.
- **DEC-108-06:** oberta; el bloc SIF és deliberadament `opt`.
- **Mailing A→baixa→B:** decisió pendent, no resolta per aquests diagrames.
