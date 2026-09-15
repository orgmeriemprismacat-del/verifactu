# 13 - Cua asincrona de callbacks Redsys

## 1. Objectiu i decisio

El callback de Redsys ha de validar i persistir la notificacio, crear un treball durable i respondre sense esperar que acabi la factura. El processament fiscal i economic es fara en segon pla.

S'adopta una taula propia, `redsys_callback_queue`. No es reutilitza `redsys_notifications` com a cua:

- `redsys_payment_intent` conserva el context creat abans del TPV;
- `redsys_notifications` conserva l'evidencia rebuda de Redsys;
- `redsys_callback_queue` controla execucio, bloqueig, reintents i resultat;
- `payment_transaction` conserva el moviment economic confirmat.

## 2. Components

| Component | Responsabilitat |
|---|---|
| `RedsysPaymentIntentRepository` | Crear i recuperar la intencio per `DS_ORDER`. |
| `RedsysNotificationRepository` | Registrar una unica notificacio i validar duplicats coherents. |
| `RedsysCallbackQueueRepository` | Encolar, reclamar i finalitzar treballs. |
| `RedsysCallbackService` | Validar intencio/notificacio i crear el treball dins una transaccio curta. |
| `RedsysCallbackDispatcher` | Seleccionar l'orquestrador segons `SOURCE_TYPE`. |
| `RedsysCallbackWorker` | Reclamar treballs, executar-los i aplicar reintents o incidencies. |
| `process-redsys-callback-queue.php` | Punt d'execucio CLI per cron/supervisor i operacio manual controlada. |

## 3. Esquema proposat

```sql
CREATE TABLE IF NOT EXISTS redsys_callback_queue (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    UUID_JOB CHAR(36) NOT NULL UNIQUE,
    NOTIFICATION_ID BIGINT NOT NULL,
    UUID_INTENT CHAR(36) NOT NULL,
    STATUS VARCHAR(30) NOT NULL DEFAULT 'QUEUED',
    ATTEMPTS INT NOT NULL DEFAULT 0,
    AVAILABLE_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    LOCKED_AT DATETIME NULL,
    LOCKED_BY VARCHAR(100) NULL,
    LAST_ERROR TEXT NULL,
    RESULT_JSON JSON NULL,
    UUID_FACTURA CHAR(36) NULL,
    UUID_PAYMENT CHAR(36) NULL,
    PROCESSED_AT DATETIME NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    UPDATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,

    UNIQUE KEY uq_redsys_callback_notification (NOTIFICATION_ID),
    KEY idx_redsys_callback_available (STATUS, AVAILABLE_AT),
    KEY idx_redsys_callback_intent (UUID_INTENT),
    CONSTRAINT fk_redsys_callback_notification
        FOREIGN KEY (NOTIFICATION_ID) REFERENCES redsys_notifications(ID),
    CONSTRAINT fk_redsys_callback_intent
        FOREIGN KEY (UUID_INTENT) REFERENCES redsys_payment_intent(UUID_INTENT)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

Estats permesos:

| Estat | Significat |
|---|---|
| `QUEUED` | Treball nou disponible. |
| `PROCESSING` | Un worker l'ha reclamat. |
| `RETRY` | Error tecnic recuperable; espera `AVAILABLE_AT`. |
| `PROCESSED` | Factura/pagament resolts de forma idempotent. |
| `INCIDENT` | Conflicte funcional o intents tecnics exhaurits. |

## 4. Transaccio del callback

```text
POST signat de Redsys
    -> verificar signatura i extreure camps signats
    -> BEGIN SIF
    -> carregar i bloquejar redsys_payment_intent per DS_ORDER
    -> validar estat, caducitat, import, divisa i terminal
    -> inserir o rellegir redsys_notifications
    -> si resposta autoritzada: inserir un unic job QUEUED
    -> si resposta denegada: conservar notificacio sense job
    -> COMMIT
    -> respondre a Redsys sense emetre factura dins la peticio
```

Un callback repetit amb dades equivalents retorna el resultat persistent i no crea un segon job. Un callback amb el mateix `DS_ORDER` i dades diferents obre una incidencia i no s'encola.

Abans d'activar aquest flux, `redsys_notifications` ha de conservar de forma normalitzada els camps signats necessaris per comparar el callback: `DS_ORDER`, import, resposta, divisa, terminal, versio de signatura i hash del payload. `RAW_PAYLOAD_JSON` continua sent evidencia, pero no substitueix les columnes normalitzades usades per decidir.

## 5. Reclamacio i execucio del worker

El worker no ha de mantenir un bloqueig SQL mentre consulta legacy o emet la factura:

1. obrir una transaccio curta;
2. seleccionar un job `QUEUED` o `RETRY` amb `AVAILABLE_AT <= NOW()`;
3. bloquejar-lo, incrementar `ATTEMPTS` i marcar `PROCESSING` amb `LOCKED_BY`;
4. confirmar la reclamacio;
5. executar el dispatcher fora de la transaccio de reclamacio;
6. guardar resultat o programar reintent en una nova transaccio curta.

Si el proces mor despres d'emetre la factura pero abans de marcar `PROCESSED`, el reintent reutilitza les claus idempotents de `InvoiceService` i `PaymentService`, recupera els mateixos UUID i finalitza el job.

Un job `PROCESSING` amb `LOCKED_AT` anterior al llindar operatiu es pot recuperar com a `RETRY`. El llindar inicial proposat es de 15 minuts.

## 6. Dispatcher per origen

Els orquestradors Redsys actuals carreguen de nou dades de la base legacy a partir de `IDPAG`. Aquest comportament es pot mantenir per als scripts manuals de preproduccio, pero no sera la font de veritat del worker. Per al circuit asincron, cada orquestrador ha d'exposar una entrada que consumeixi el `SNAPSHOT_JSON` immutable de `redsys_payment_intent`; la lectura legacy posterior nomes pot servir per contrast o sincronitzacio, mai per recalcular receptor, linies, descomptes o imports.

| `SOURCE_TYPE` | Orquestrador | Dada addicional congelada abans del TPV |
|---|---|---|
| `CURS` | `RedsysCourseInvoiceService` | `SNAPSHOT_JSON.discount`, si existeix. |
| `PACK` | `RedsysPackInvoiceService` | Cap dada externa addicional. |
| `GRUP` | `RedsysGroupInvoiceService` | Cap dada externa addicional. |
| `REGAL` | `RedsysGiftInvoiceService` | `SOURCE_ID` ha de ser l'ID numeric del regal; el codi es resol abans del TPV. |
| `USOC_ALUMNE` | `RedsysUsocInvoiceService` | `SNAPSHOT_JSON.usoc.entity_amount`. |

Qualsevol tipus desconegut passa a `INCIDENT`. El worker no pot acceptar selectors, imports ni `IDPAG` proporcionats manualment en el moment de processar.

## 7. Reintents i incidencies

- Errors de validacio, import, origen, snapshot o receptor: `INCIDENT` immediat, sense reintent.
- Errors de connexio, timeout o indisponibilitat temporal: `RETRY`.
- Politica inicial: maxim 5 intents i esperes d'1, 5, 15 i 60 minuts.
- En exhaurir intents: `INCIDENT` i entrada a `errors_verifactu` amb `DS_ORDER` i `UUID_JOB` als detalls.
- Un reintent manual nomes canvia `INCIDENT` a `RETRY`; no crea un job nou.

## 8. Resultat persistent

Quan el job acaba correctament ha de guardar:

- `UUID_FACTURA`;
- `UUID_PAYMENT`;
- `RESULT_JSON` amb `num_visible`, reutilitzacio idempotent i resultats especials;
- `PROCESSED_AT`;
- `STATUS = PROCESSED`.

Per a `USOC_ALUMNE`, `RESULT_JSON` conserva tambe `entity_invoice_pending`. La factura d'entitat continua sent un flux separat amb dades fiscals explicites.

## 9. Sincronitzacio legacy

La primera versio del worker no executara automaticament `LegacySyncService`. El metode actual concatena una nova anotacio a `inscripcions.OBSERVACIONS` en cada execucio i, per tant, no es idempotent davant un reintent.

La factura i el pagament SIF poden quedar `PROCESSED` encara que la sincronitzacio resum legacy estigui pendent. Abans d'automatitzar-la cal afegir una clau o marca idempotent i una prova que dos intents no dupliquen l'anotacio.

## 10. Proves obligatories

1. callback autoritzat crea una notificacio i un job, sense factura immediata;
2. callback denegat crea notificacio pero no job;
3. callback duplicat equivalent conserva un sol job;
4. callback duplicat contradictori crea incidencia;
5. dos workers no reclamen el mateix job;
6. error tecnic programa `RETRY` i incrementa intents;
7. conflicte funcional acaba en `INCIDENT` sense factura;
8. reintent posterior a factura creada reutilitza factura i pagament;
9. dispatcher cobreix curs, pack, grup, regal i USOC;
10. tipus desconegut queda bloquejat;
11. job processat conserva UUID de factura i pagament;
12. cap reintent executa sincronitzacio legacy no idempotent.

## 11. Ordre d'implementacio Trello

Les nou targetes son independents al tauler pero s'han d'executar en aquest ordre:

1. finalitzar mapping `DS_ORDER -> payment intent`;
2. crear `redsys_callback_queue`;
3. fer que el callback generi jobs;
4. implementar el worker;
5. crear el dispatcher;
6. persistir el resultat;
7. implementar reintents i incidencies;
8. validar duplicats i contradiccions;
9. provar el circuit asincron complet.

## 12. Fora d'abast d'aquest bloc

- activar el worker directament en produccio;
- automatitzar la sincronitzacio legacy no idempotent;
- emetre automaticament la factura `USOC_ENTITAT`;
- substituir `fiscal_queue`, que continua dedicada a l'enviament fiscal AEAT;
- canviar transferencies manuals o conciliacio de fitxers TPV.
