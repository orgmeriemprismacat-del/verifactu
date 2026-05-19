# 06 - Integracio Redsys i migracio a pay.prisma.cat

> Document especific sobre la migracio dels pagaments i callbacks Redsys cap al subdomini de pagament/facturacio i la integracio amb el SIF.

## 1. Decisio d'arquitectura

`pay.prisma.cat` no sera nomes el domini de pagament. Sera tambe el domini oficial del SIF.

Incloura:

- API de facturacio;
- callbacks Redsys;
- generacio/consulta de documents fiscals;
- cua AEAT;
- incidencies del SIF;
- control de versions;
- documentacio signada i evidencia interna;
- exports fiscals.

La intranet principal tindra accessos al panell SIF i podra mostrar avisos, pero no substituira la font de veritat fiscal.

## 2. Canvi principal respecte el flux actual

Actualment `realitzaPagamentAutomatic.php`:

- rep la notificacio Redsys;
- busca inscripcio per `IDPAG`;
- calcula numero fiscal localment;
- insereix a `web.factures`;
- actualitza `web.inscripcions`;
- envia correus.

Amb el SIF:

```text
Redsys callback
    -> validar signatura
    -> detectar duplicat Ds_Order
    -> carregar origen/inscripcio/pagament
    -> decidir si cal issueInvoice() o registerPayment()
    -> cridar SIF
    -> actualitzar relacions operatives
    -> enviar correus
```

El callback Redsys no ha de calcular `A2026/x` ni inserir directament a `web.factures`.

## 3. Validacio real de Redsys

En el codi actual es calcula `$firma`, pero cal assegurar que es compara amb `$signatureRecibida` abans de tocar BD.

Regla:

```php
if ($firma !== $signatureRecibida) {
    throw new Exception('Signatura Redsys no valida', 3001);
}
```

Per dades critiques cal usar preferentment les dades signades per Redsys:

```php
$ordre = $miObj->getParameter('Ds_Order');
$importRedsys = intval($miObj->getParameter('Ds_Amount')) / 100;
```

No s'ha de confiar en `$_GET['import']` ni `$_GET['order']` per emetre factura.

## 4. Deteccio de callbacks duplicats

Redsys pot repetir notificacions. Si es processen dues vegades, es podria duplicar pagament o factura.

Taula proposada:

```sql
CREATE TABLE redsys_notifications (
    ID BIGINT AUTO_INCREMENT PRIMARY KEY,
    DS_ORDER VARCHAR(30) NOT NULL UNIQUE,
    IDPAG INT NOT NULL,
    ID_INSC INT NULL,
    IMPORT DECIMAL(12,2) NOT NULL,
    RESPONSE_CODE VARCHAR(10) NOT NULL,
    STATUS VARCHAR(20) NOT NULL,
    CREATED_AT DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

Flux:

```text
callback Redsys rebut
    -> validar signatura
    -> INSERT redsys_notifications
    -> si DS_ORDER duplicat: sortir sense tornar a processar
    -> si nou: continuar flux
```

## 5. Bloc que s'ha de substituir

Aquest patro actual s'ha d'eliminar del client Redsys:

```text
SELECT factura_relacionada ...
SELECT ordre FROM factures ...
$numFact = "A".$anyFiscal."/".$ordreFact;
INSERT INTO factures ...
```

S'ha de substituir per:

```text
si no existeix factura previa real -> POST /api/factures/issue
si ja existeix factura previa real -> POST /api/payments/register
```

## 6. Endpoint `POST /api/factures/issue`

Serveix per crear una factura fiscal nova.

Casos:

- curs normal Redsys;
- pack;
- grup;
- regal;
- factura manual;
- factura abans de cobrar;
- transferencia sense factura previa;
- rectificativa si el flux ho requereix.

Exemple base curs normal:

```php
$idempotencyKey = hash('sha256', 'REDSYS|CURS|IDPAG:' . $idPag . '|ORDER:' . $ordre);

$payload = [
    'idempotency_key' => $idempotencyKey,
    'tipus_serie' => 'A',
    'source_channel' => 'REDSYS',
    'source_type' => 'CURS',
    'source_id' => $idInsc,
    'payment_id' => $idPag,
    'payment_order' => $ordre,
    'payment_date' => $dataPagCompleta,
    'billing' => [
        'nom_rao' => $rao,
        'nif_cif' => $cif,
        'adreca' => $adreca,
        'cp' => $cp,
        'poblacio' => $poble,
        'pais' => 'ES'
    ],
    'lines' => [[
        'concepte' => $concepte1,
        'detall' => $concepte2,
        'quantitat' => '1',
        'preu_unitari' => number_format($importRedsys, 2, '.', ''),
        'desc_import' => '0.00',
        'tax_rate' => '0.00',
        'iva_regim' => 'EXEMPT',
        'total_linia' => number_format($importRedsys, 2, '.', ''),
        'source_type' => 'INSCRIPCIO',
        'source_id' => $idInsc
    ]],
    'references' => [
        'inscripcio_id' => $idInsc,
        'any' => $any,
        'mes' => $mes,
        'curs' => $codiCurs,
        'grup' => $grup ?? null
    ]
];

$facturaSif = crearFacturaSif($payload);
```

Resposta esperada:

```json
{
  "ok": true,
  "uuid_factura": "...",
  "num_visible": "A2026/000123",
  "estat_aeat": "PENDING",
  "estat_cobrament": "COBRADA",
  "idempotency_reused": false
}
```

## 7. Endpoint `POST /api/payments/register`

Serveix per registrar cobrament, devolucio o compensacio sobre una factura ja existent.

Casos:

- factura abans de pagar que es cobra despres;
- transferencia que paga factura emesa;
- pagament parcial;
- compensacio;
- devolucio;
- pagament d'empresa amb factura ja emesa.

Payload:

```json
{
  "idempotency_key": "TRANSFERENCIA|20260515|REF:ABC123",
  "uuid_factura": "550e8400-e29b-41d4-a716-446655440000",
  "tipus_moviment": "CHARGE",
  "metode": "TRANSFERENCIA",
  "import": "210.00",
  "data_moviment": "2026-05-15 12:00:00",
  "referencia_bancaria": "ABC123"
}
```

Aquest endpoint:

- crea `payment_transaction`;
- crea `payment_allocation`;
- actualitza `ESTAT_COBRAMENT`;
- no crea factura nova;
- no assigna numero fiscal nou.

## 8. Decisio per cada canal/cas

| Cas | Endpoint |
| --- | --- |
| Redsys curs normal sense factura previa | `issueInvoice()` |
| Redsys pack | `issueInvoice()` amb diverses linies |
| Redsys grup | `issueInvoice()` amb una linia per participant |
| Redsys regal | `issueInvoice()` al comprador |
| USOC alumne paga part | `issueInvoice()` factura alumne |
| USOC paga diferencia | `issueInvoice()` factura USOC |
| Factura abans de cobrar | `issueInvoice()` amb `EMESA_ABANS_COBRAMENT = 1` |
| Pagament posterior d'una factura ja emesa | `registerPayment()` |
| Transferencia sense factura previa | `issueInvoice()` |
| Transferencia amb factura previa | `registerPayment()` |
| Compensacio sobre factura existent | `registerPayment()` |
| Devolucio | rectificativa + `registerPayment(REFUND)` |

## 9. Idempotencia per cas

| Cas | Clau idempotent orientativa |
| --- | --- |
| Curs Redsys | `REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` |
| Pack Redsys | `REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` |
| Grup Redsys | `REDSYS|GRUP|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` |
| Regal Redsys | `REDSYS|REGAL|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` |
| USOC alumne | `REDSYS|USOC_ALUMNE|IDPAG:{IDPAG}|ORDER:{DS_ORDER}` |
| USOC entitat | `INTRANET|USOC_ENTITAT|IDPAG:{IDPAG}|REF:{REF}` |
| Transferencia | `TRANSFERENCIA|{DATA}|REF:{REFERENCIA}` |
| Factura abans de cobrar | `INTRANET|FACTURA_ABANS_COBRAR|FACT_REL:{FACTURA_RELACIONADA}` |
| Compensacio | `COMPENSACIO|{UUID_CREDIT}|FACT:{UUID_FACTURA}` |
| Devolucio | `REFUND|{UUID_FACTURA}|{REF_RETORN}` |

La unitat idempotent del curs Redsys no es `inscripcions.ID`, perque una mateixa inscripcio pot tenir fraccionaments o mes d'un pagament.

## 10. Dades fiscals abans d'enviar a Redsys

Per reduir rectificatives per nom/NIF, la pantalla de dades de facturacio ha d'anar abans d'enviar a Redsys.

Regla:

```text
El client confirma dades fiscals
    -> es guarda snapshot fiscal vinculat a IDPAG / ordre logic
    -> es redirigeix a Redsys
    -> callback Redsys usa aquell snapshot
```

El callback de Redsys no hauria d'inventar:

```php
$rao = $nom . ' ' . $cognoms;
```

si l'usuari ja ha confirmat dades fiscals.

## 11. Flux Redsys curs normal

```text
1. validar signatura Redsys
2. obtenir Ds_Order i import signats
3. inserir redsys_notifications
4. si Ds_Order duplicat, sortir sense tornar a processar
5. carregar inscripcio per IDPAG
6. carregar snapshot fiscal confirmat
7. mirar si ja hi ha factura previa real
8. si existeix factura -> registerPayment()
9. si no existeix -> issueInvoice()
10. actualitzar inscripcions i fact_rels
11. enviar correus
```

## 12. Flux Redsys pack

```text
1 pagament
    -> diverses inscripcions amb mateix IDPAG
    -> una factura
    -> una linia per curs
    -> descompte pack aplicat al segon curs
```

Idempotencia:

```text
REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

## 13. Flux Redsys grup

```text
1 pagament
    -> diverses inscripcions/participants
    -> una factura a empresa o responsable
    -> una linia per participant
```

El nom del participant pot sortir a la linia. El DNI nomes hauria d'apareixer si cal per justificacio.

## 14. Flux Redsys regal

```text
comprador paga regal
    -> factura al comprador
    -> SOURCE_TYPE = REGAL
    -> SOURCE_ID = ID_REGAL
    -> codi regal
    -> bescanvi posterior
    -> inscripcio del destinatari sense factura nova
```

## 15. Flux USOC

Cas confirmat:

```text
alumne paga la seva part
    -> factura a l'alumne

USOC paga diferencia
    -> factura a USOC
```

Son dues factures si hi ha dos pagadors/receptors reals.

## 16. Analisi fitxer TPV

L'apartat actual d'analisi de fitxer TPV s'ha d'adaptar al SIF.

Objectiu:

- detectar pagaments Redsys que consten al banc/TPV;
- comprovar si existeix `redsys_notifications`;
- comprovar si existeix `payment_transaction`;
- comprovar si existeix factura o assignacio;
- si hi ha cobrament sense factura, crear incidencia i permetre accio controlada.

No ha de crear factures duplicades.
