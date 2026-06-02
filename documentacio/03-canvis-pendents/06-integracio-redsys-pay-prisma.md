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

### 2.1. Dades recuperades del codi actual

El xat antic incloia fragments de `realitzaPagamentAutomatic.php` que concreten el flux actual:

- rep per `GET` valors com `codiCurs`, `dni`, `import`, `frac`, `idPag` i `order`;
- envia un correu intern amb DNI, import, fraccio, `IDPAG` i `ORDER`;
- desa `Ds_Order` a `web.factures.NUM_COMANDA`;
- insereix la factura historica amb camps com `factura_relacionada`, `tipus`, `ANY`, `ORDRE`, `NUM`, `DATA`, `data_pagament`, `num_comanda`, `RAO`, `CIF`, `ADRECA`, `CP`, `POBLACIO`, `CONCEPTE1`, `CONCEPTE2`, `IMPORT`, `ENTITAT`, `FORMA_PAGAMENT`, `CURS` i `HORES`;
- actualitza dades de pagament a `web.inscripcions`.

Aquests valors serveixen per identificar l'origen operatiu i per mantenir compatibilitat, pero no poden ser la prova fiscal principal. En la migracio, l'import i l'ordre fiscalment sensibles han de venir de les dades signades Redsys i el numero de factura l'ha de decidir el SIF.

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

### 8.1. Canals TPV i URL identificats

El xat antic confirma que hi ha diversos canals o URLs de pagament que poden acabar entrant pel mateix ecosistema Redsys/TPV:

- curs individual;
- regal de curs;
- pack;
- grup de persones;
- taller o jornada;
- cas USOC, amb part pagada per alumne i part pagada per entitat;
- empresa/responsable;
- morositat, reclamacio o diferencia pendent.

Cada canal ha de conservar `source_channel`, `source_type`, `source_id`, `IDPAG` i referencia Redsys/TPV. La migracio no ha de reduir-los a un unic cas generic de curs, perque canvien receptor, linies, permisos de consulta i criteri de conciliacio.

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

També ha d'usar el snapshot de preu/descompte calculat abans de Redsys. Si l'usuari ha aplicat un codi promocional:

- ecommerce/intranet valida `CODI_DESCOMPTE`, DNI, `USED` i vigencia;
- el snapshot guarda codi, percentatge/import, text visible i total final;
- Redsys cobra l'import final signat;
- el callback no revalida el codi ni recalcula el descompte;
- `issueInvoice()` rep la linia amb `desc_origen = CODI_PROMO` o `PROMOCIO_TEMPORAL`.

Si la validacio del codi falla abans de Redsys, no s'ha d'enviar a pagar amb aquell import. Si el codi caduca o es marca usat despres d'emetre, la factura queda igual perquè ja conserva el snapshot fiscal.

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

### 11.1. Revisio especialitzada del flux curs normal

Informacio concreta recuperada del xat antic sobre `realitzaPagamentAutomatic.php`:

- el fitxer rep la notificacio de Redsys i crea l'objecte `RedsysAPI`;
- llegeix `Ds_SignatureVersion`, `Ds_MerchantParameters` i `Ds_Signature`;
- calcula `$firma = $miObj->createMerchantSignatureNotif($kc, $datos)`;
- recupera `Ds_Order`, `Ds_Date`, `Ds_Hour`, `Ds_Amount` i `Ds_Response`;
- considera autoritzada la transaccio quan `Ds_Response` esta entre `0` i `99`;
- cerca la inscripcio per `IDPAG` i estats `INSC CURS` `0`, `1` o `M`;
- recupera dades operatives de la inscripcio: `ID`, `ANY`, `MES`, `CURS`, nom, cognoms, DNI, correu, adreca, CP, poblacio, `FACTURA_RELACIONADA`, `A_PAGAR`, `PAGAMENT` i `FRACCIO`;
- envia correus interns de "pagament automatic" amb DNI, import, fraccio, `IDPAG` i `ORDER`;
- el bloc antic `Generem la factura` calcula `factura_relacionada`, `ANY`, `ORDRE` i `NUM` localment;
- si no hi ha `FACTURA_RELACIONADA`, busca l'ultim valor a `factures` i en crea un de nou;
- si ja hi havia pagament anterior, reutilitza la mateixa `FACTURA_RELACIONADA`;
- genera el numero visible amb `$numFact = "A".$anyFiscal."/".$ordreFact`;
- construeix receptor amb `$rao = $nom." ".$cognoms` i `$cif = $dni`;
- genera conceptes de factura a partir del curs, convocatòria, jornada i fraccionament;
- insereix directament a `web.factures` amb `num_comanda = $order`, `FORMA_PAGAMENT = TPV` i `ENTITAT = Asso`;
- actualitza `web.inscripcions` amb `PAGAMENT`, `FACTURA_RELACIONADA`, `DATA PAG` i `FRACCIO`.

Lectura SIF:

- el callback Redsys actual barreja notificacio, conciliacio, emissio fiscal, actualitzacio d'inscripcio i correus;
- la signatura calculada s'ha de comparar obligatoriament amb `Ds_Signature` abans de tocar BD;
- l'import per facturar ha de sortir de `Ds_Amount` signat, no de `$_GET['import']`;
- `Ds_Order` ha d'entrar primer a `redsys_notifications` i actuar com a deduplicacio de callback;
- `IDPAG` no es clau unica de factura, perque pot tenir diversos intents Redsys, pagaments fraccionats o pagament denegat i despres acceptat;
- el bloc `SELECT ordre FROM factures... INSERT INTO factures...` s'ha de substituir per la decisio `issueInvoice()` o `registerPayment()`;
- `web.inscripcions` nomes s'ha d'actualitzar despres que el SIF retorni `UUID_FACTURA`, numero visible i estat de cobrament;
- els correus a client o interns no han de ser prova que la factura SIF existeix.

Criteri final per curs normal:

```text
Redsys confirma pagament
    -> validar signatura i resposta
    -> registrar redsys_notifications
    -> si DS_ORDER duplicat, retornar sense efecte nou
    -> carregar inscripcio i snapshot fiscal vinculat a IDPAG
    -> si hi ha factura SIF previa real, registerPayment()
    -> si no hi ha factura SIF previa, issueInvoice() + payment_transaction
    -> sincronitzar resum operatiu d'inscripcio
    -> generar o consultar PDF/QR
    -> enviar correus segons estat SIF
```

Proves de tancament:

- callback valid crea una sola factura i un cobrament;
- callback duplicat amb el mateix `DS_ORDER` no duplica factura ni pagament;
- signatura incorrecta no toca BD fiscal ni operativa;
- `Ds_Amount` diferent de l'import esperat obre incidencia i no factura automaticament;
- mateix `IDPAG` amb `DS_ORDER` diferent per fraccionament no es tracta com a duplicat simple;
- pagament denegat i despres acceptat amb el mateix `IDPAG` nomes processa l'autoritzat;
- factura abans de cobrament existent rep `registerPayment()`, no una factura nova;
- la sincronitzacio amb `inscripcions` es posterior a l'acceptacio del SIF.

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

Regla recuperada del xat antic:

- el pack normal inclou 2 cursos;
- es crea una inscripcio per cada curs;
- les inscripcions del mateix pack comparteixen `IDPAG`;
- el futur desitjat es una factura per pagament real, amb una linia per curs;
- el descompte de pack del 25% s'aplica a la linia del segon curs;
- `SOURCE_TYPE = INSCRIPCIO` i `SOURCE_ID = inscripcions.ID` en cada linia;
- `DESC_ORIGEN = PACK` nomes a la linia on s'aplica el descompte.

Lectura operativa actual:

- `buscarPagamentsPack` agrupa pagaments pendents per `IDPAG`;
- `buscarInfoPack` consulta `info_pack`;
- `cnsInscsPack` i `cnsDadesCursPack` identifiquen les inscripcions i dades de curs que han d'entrar a les linies fiscals.

Cas excepcional:

- si intranet registra mes d'un pagament real d'un pack fraccionat, el SIF ha de crear una factura per pagament real;
- el client ecommerce no ha de poder escollir dividir el pack en factures diferents;
- un callback duplicat del mateix `DS_ORDER` no pot crear una segona factura ni duplicar linies.

## 13. Flux Redsys grup

```text
1 pagament
    -> diverses inscripcions/participants
    -> una factura a empresa o responsable
    -> una linia per participant
```

El nom del participant pot sortir a la linia. El DNI nomes hauria d'apareixer si cal per justificacio.

Regla recuperada del xat antic:

- una empresa o persona paga per N participants;
- hi ha una fila a `inscripcions` per participant;
- el receptor fiscal pot ser escola/empresa o responsable particular;
- el preu per participant surt de `descomptes_grup`;
- la factura te una linia per participant, sobretot per justificacio FUNDAE/Tripartita;
- cada linia apunta a `SOURCE_TYPE = INSCRIPCIO` i `SOURCE_ID = inscripcions.ID`;
- el nom del participant pot sortir al text visible de la linia;
- el DNI del participant es guarda com a dada interna o annex si cal, pero no s'imprimeix per defecte.

Lectura operativa actual:

- `TIPUS_INSC = G` identifica inscripcions de grup;
- `buscarPersRespGrup2` uneix `inscripcions` amb `respGrups` per `IDPAG` i permet buscar pel DNI del responsable o del participant;
- `buscarPersGrup` llista participants del grup per `IDPAG`;
- `buscarPagamentsGrup` agrupa imports i pagaments per `IDPAG`;
- `searchMembresGrup` i `searchMembresGrup2` reparteixen el pagament entre membres del grup en l'operativa historica.

La migracio a `pay.prisma.cat` ha de substituir aquest repartiment directe sobre `inscripcions` per `payment_transaction`, `payment_allocation`, linies fiscals congelades i sincronitzacio posterior.

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

Regla recuperada del xat antic:

- paga qui regala el curs;
- el destinatari es la persona indicada al formulari, pero encara no omple les seves dades d'inscripcio;
- el comprador tria curs, pot posar dedicatoria i posa les seves dades de facturacio;
- la factura va al comprador, no al beneficiari;
- es genera un codi regal per bescanviar;
- quan el destinatari bescanvia el codi i crea la inscripcio, no es genera una factura nova.

Lectura operativa actual:

- `buscarRegNoPayByCodi` cerca a `regal` per `CODI` i `FACT_REL = 0`;
- `buscarRegNoPayByDni` cerca regals pendents per NIF del comprador (`NIFC`);
- `buscarRegalById` recupera `NOM_CURS`, `CCURS`, `NOMC`, `NIFC`, `MAILC`, adreca, `CODI`, `FACT_REL`, `ORIGEN` i `DESTI`;
- `updFactRegal` marca el regal amb la factura relacionada historica;
- el correu historic de confirmacio envia el codi i enllaça la targeta regal PDF;
- el codi regal te validesa operativa d'un any des de la compra segons el missatge actual.

La migracio a `pay.prisma.cat` ha de conservar el codi regal, la relacio amb la factura SIF i la posterior inscripcio del destinatari. El PDF de targeta regal pot continuar com a document comercial, pero la factura/PDF fiscal ha de sortir de `factura_documents`.

## 15. Flux USOC

Cas confirmat:

```text
alumne paga la seva part
    -> factura a l'alumne

USOC paga diferencia
    -> factura a USOC
```

Son dues factures si hi ha dos pagadors/receptors reals.

Detalls recuperats:

- el canal historic es `curs afiliat d'USOC`;
- el text de concepte podia indicar: `El pagament de la diferencia el realitza l'entitat USOC`;
- el descompte intern es `TIPUS_DESC = 4 / Afiliat USOC`;
- `VALID_DESC` diferencia pendent, validat valid i validat no valid;
- la validacio es manual a intranet, despres de consultar o confirmar l'afiliacio amb USOC;
- en el cas habitual recuperat, l'alumne paga un anticipi/import parcial de 10 euros i USOC cobreix la diferencia;
- pot existir el cas especial `Altres: Curs gratüit USOC`, amb parametre operatiu `anticipi-preu-usoc`.

Flux final recomanat:

| Moment | Accio |
| --- | --- |
| Alumne sol·licita descompte USOC | Guardar `TIPUS_DESC = 4`, `VALID_DESC = 0` i bloquejar emissio amb descompte fins validacio. |
| Intranet valida afiliacio | Actualitzar `VALID_DESC = 1`, congelar preu/descompte i preparar URL o pagament alumne. |
| Redsys cobra part alumne | `issueInvoice()` a l'alumne amb idempotencia `REDSYS|USOC_ALUMNE|IDPAG:{IDPAG}|ORDER:{DS_ORDER}`. |
| USOC paga diferencia | `issueInvoice()` a USOC amb relacio interna a inscripcio i factura alumne. |
| Afiliacio denegada | Recalcular sense descompte, informar l'alumne i no crear factura USOC. |

La factura d'USOC no ha de sortir com a rectificativa de la factura de l'alumne: es una factura ordinaria separada per un pagador/receptor diferent. Les dues factures han de quedar relacionades per traçabilitat interna i proves.

## 16. Analisi fitxer TPV

L'apartat actual d'analisi de fitxer TPV s'ha d'adaptar al SIF.

Objectiu:

- detectar pagaments Redsys que consten al banc/TPV;
- comprovar si existeix `redsys_notifications`;
- comprovar si existeix `payment_transaction`;
- comprovar si existeix factura o assignacio;
- si hi ha cobrament sense factura, crear incidencia i permetre accio controlada.

No ha de crear factures duplicades.

## 17. Passar pagaments i transferencies

L'apartat actual de la intranet `Passar pagaments` valida pagaments manuals, transferencies i regularitzacions. El xat antic confirma que, despres de validar el pagament, el flux final ha de cridar el SIF.

Regla final:

```text
pagament manual / transferencia / compensacio
    -> validar usuari, data, metode, import i referencia
    -> buscar factura SIF existent
    -> si existeix factura real: registerPayment()
    -> si no existeix factura i cal factura: issueInvoice()
    -> sincronitzar camps operatius historics nomes com a resum
```

Subcas recuperat del xat antic: transferencia validada sobre factura ja generada.

En el codi antic, `efectuarPagament.php` rep `id`, `tipus`, `pagament`, `dataPag`, `banc`, `obs`, `numFact` i `efact`. Quan `efact != 0`, `efectuarPagament()` crida `efectuarPagamentFacturaGenerada()`.

Aquest cami historic fa:

- cerca `buscarPagamentsByFact` per `NUM`;
- recupera `A_PAGAR`, `PAGAMENT`, `FACTURA_RELACIONADA`, `FRACCIO`, `IDPAG`, `cif` i `E_FACT`;
- calcula pendent amb `A_PAGAR - PAGAMENT - importPag`;
- actualitza `web.factures` amb `updFactGenerada` (`data_pagament`, `IMPORT`, `FORMA_PAGAMENT`);
- reparteix l'import per membres de la factura amb `searchMembresFactRel`, `updPayInscr` i `updDateInscr`;
- actualitza `FRACCIO` amb `updFraccBDByFact` si queda pagament parcial;
- envia correu de confirmacio a entitat/responsable quan aplica.

Traduccio SIF:

```text
transferencia confirmada
    -> payment_transaction(METODE=TRANSFERENCIA, DATA_MOVIMENT=dataPag, IMPORT=pagament)
    -> payment_allocation contra UUID_FACTURA existent
    -> actualitzacio historica nomes despres de resposta correcta
```

`updFactGenerada` queda prohibit com a mecanisme fiscal sobre factura VERI*FACTU: no pot canviar import, data de pagament ni forma de pagament de la factura emesa. La data i metode del cobrament viuen a `payment_transaction`.

Idempotencia recomanada:

```text
TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}
```

Si no hi ha referencia bancaria:

```text
TRANSFERENCIA|FACT:{NUM_FACT}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}
```

En el sistema actual, quan hi ha factura abans de pagar, es pot detectar buscant factura historica amb `E_FACT = 1`, CIF/entitat i opcions de seleccio si hi ha mes d'una candidata. En el SIF aquesta logica s'ha de substituir per:

- `EMESA_ABANS_COBRAMENT = 1` per saber que la factura real ja existeix;
- `UUID_FACTURA` i `FACTURA_RELACIONADA` com a claus de relacio;
- seleccio explicita si hi ha mes d'una factura candidata;
- registre de cobrament amb `registerPayment()`, sense generar un nou numero fiscal.

## 18. Migracio de callbacks i URLs de pagament

La confirmacio de pagament i la programacio TPV s'han de moure de `prisma.cat` cap a `pay.prisma.cat`.

Regla:

```text
URL antiga o intranet
    -> pot iniciar o redirigir el flux
pay.prisma.cat
    -> controla pagament, callback, conciliacio i crida SIF
SIF
    -> decideix factura, registre fiscal, PDF/QR i estat cobrament
```

Les URLs antigues poden quedar temporalment com a redireccions o clients, pero no com a font de veritat fiscal. La migracio ha de conservar una taula o mapa intern de rutes antigues, canal, `source_type`, `IDPAG` i endpoint nou per poder auditar incidencies.

## 19. Visibilitat de factura i PDF despres del pagament

El PDF exacte generat en el moment d'emissio s'ha de conservar en un espai controlat de `pay.prisma.cat`. Quan el pagament correspon a una factura d'empresa, grup o responsable, l'alumne no ha de veure automaticament la factura si no n'era el receptor fiscal.

Regla:

```text
consulta de factura/PDF/QR
    -> comprovar receptor fiscal i permisos
    -> si factura de grup o empresa: visible al responsable autoritzat
    -> si factura individual: visible al receptor o usuari autoritzat
```
