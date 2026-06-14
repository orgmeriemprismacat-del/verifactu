# 04 - Fluxos de facturacio

> Estat 2026-06-14: document funcional de referencia dels fluxos fiscals del SIF. Els fluxos especials ja tenen criteri tancat i circuit tecnic preparat, pero continuen pendents d'execucio amb PHP/MySQL de test i evidencies de preproduccio.

## Fluxos a documentar

- Curs normal Redsys.
- Pack.
- Grup.
- Regal.
- USOC.
- Factura abans de cobrament.
- Transferencia validada a intranet.
- Compensacio.
- Devolucio.
- Rectificativa.
- Canvi de curs.
- Baixa.
- Morositat i reclamacions.
- Proformes o documents previs no fiscals.
- Pagaments fraccionats.
- Factura manual.
- Migracio de factures historiques.

Nota de cobertura:

```text
Els fluxos de curs normal Redsys, pack, grup i regal ja estan explicats funcionalment.
Els fluxos fiscals especials ja estan tancats a nivell de criteri i preparats
a nivell de circuit CLI. El que falta no es coneixement del cas, sino execucio
amb PHP/MySQL de test, pantalles finals, correus/enllacos segurs i evidencies
de preproduccio.
```

Tancament de criteri:

```text
Compensacio/saldo, pagaments fraccionats, rectificatives, devolucions,
baixes, canvis de curs, factura manual i migracio historica queden definits
com a fluxos fiscals separats. Cap d'aquests casos pot resoldre's tocant
imports, dates o factures emeses directament.
```

## Regles generals ja definides

```text
Els canals proposen factures.
El SIF emet factures.
```

Una factura real:

- te serie i numero fiscal;
- genera registre fiscal;
- entra a hash chain;
- genera PDF/QR immutable;
- s'envia o queda en cua AEAT.

Un pagament no sempre crea factura:

- si ja hi ha factura emesa pendent de cobrament, el pagament es registra contra aquella factura;
- si no hi ha factura, el pagament pot generar factura;
- si es compensacio, es registra com moviment economic;
- si es devolucio, normalment s'associa a rectificativa.

Matissos operatius recuperats del xat antic:

- un mateix `IDPAG` pot tenir diversos intents Redsys amb `Ds_Order` diferents, especialment amb pagaments fraccionats o intents denegats i posteriorment acceptats;
- si un intent Redsys es denega i despres s'accepta, pot conservar el mateix `IDPAG`;
- una transferencia pot pagar diverses factures ja emeses;
- una factura pot acabar cobrada amb diversos pagaments;
- una compensacio pot ser saldo a favor o descompte, pero fiscalment s'ha de tipificar en el SIF;
- si una persona paga de mes, es pregunta si vol devolucio o deixar saldo per una altra inscripcio.

## Contracte general d'entrada de pagaments

El SIF ha de separar quatre fets que en el sistema antic podien quedar barrejats:

| Fet | Que significa | Taula/font principal |
| --- | --- | --- |
| Notificacio Redsys | Redsys ha enviat una resposta tecnica del TPV. | `redsys_notifications` |
| Analisi TPV | Un fitxer del TPV/banc s'ha pujat i s'ha comparat. | registre d'analisi/auditoria SIF |
| Moviment economic | PrisMa considera que hi ha un cobrament, devolucio o compensacio real. | `payment_transaction` |
| Assignacio a factura | El moviment queda vinculat a una o mes factures. | `payment_allocation` |

Flux unic:

```text
entrada de pagament
    -> validar origen, permis, signatura o evidencia
    -> deduplicar amb clau estable
    -> identificar factura SIF existent o origen facturable
    -> si factura existent: registerPayment()
    -> si no hi ha factura i el cobrament crea obligacio fiscal: issueInvoice() amb bloc payment
    -> si hi ha dubte: incidencia/proposta de conciliacio, sense actualitzar camps fiscals antics
    -> sincronitzar BD antiga nomes com a resum posterior
```

Criteris:

- Redsys entra per callback a `pay.prisma.cat`; el primer registre es `redsys_notifications` per `DS_ORDER`.
- Les transferencies i pagaments manuals entren per `Passar pagaments`; el primer registre economic es `payment_transaction`, amb usuari, metode, import, data, referencia i clau idempotent.
- El fitxer TPV no emet ni registra pagaments per si sol si hi ha qualsevol ambiguitat; crea analisi, incidencies o propostes de conciliacio.
- `payment_transaction.PROVIDER_REF` ha de conservar `DS_ORDER`, referencia bancaria, referencia TPV o clau interna equivalent.
- `payment_allocation` permet pagament parcial, una transferencia que cobreix diverses factures o diversos moviments sobre la mateixa factura.
- `IDPAG` identifica l'origen operatiu, pero no deduplica per si sol.

## Transferencia validada a intranet

El xat antic confirma que, quan un pagament es fa per transferencia, administracio el valida a l'apartat `Passar pagaments` de la intranet i des d'alla s'ha de cridar el SIF.

Flux final:

```text
transferencia rebuda
    -> administracio localitza factura, alumne, grup, pack o regal a Passar pagaments
    -> informa import, data, banc/metode, observacions i referencia si existeix
    -> servidor recalcula pendent i valida permisos
    -> si factura SIF existent: registerPayment()
    -> si no hi ha factura i el cas es facturable: issueInvoice() amb bloc payment
    -> sincronitzacio historica de PAGAMENT, DATA PAG i FRACCIO nomes com a resum
```

Informacio recuperada del codi antic:

- `efectuarPagament.php` rep `id`, `tipus`, `pagament`, `dataPag`, `banc`, `obs`, `numFact` i `efact`;
- `efact == 0` crea factura historica nova i deriva per tipus `R`, `G`, `P` o `I`;
- `efact != 0` crida `efectuarPagamentFacturaGenerada()`, pensat per factura ja generada;
- el cami de factura generada usa `buscarPagamentsByFact`, `updFactGenerada`, `searchMembresFactRel`, `updPayInscr`, `updDateInscr` i `updFraccBDByFact`;
- `mostrarModalConfPag()` avisava historicament que s'actualitzaria la factura i es podria enviar correu a l'entitat/responsable.

Regla VERI*FACTU:

```text
factura ja emesa
    -> no s'actualitza import, numero, receptor ni concepte
    -> el cobrament es registra a payment_transaction
    -> la vinculacio es registra a payment_allocation
```

Si la transferencia paga parcialment, el SIF ha de conservar el moviment real i l'assignacio parcial. `FRACCIO` pot quedar com a camp historic sincronitzat, pero no com a prova fiscal primaria.

Idempotencia:

```text
TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}
```

Si la referencia no existeix:

```text
TRANSFERENCIA|FACT:{NUM_FACT}|DATA:{DATA_PAG}|IMPORT:{IMPORT}|BANC:{BANC}
```

## Pagaments fraccionats

Regla:

```text
cada moviment real de cobrament es registra una vegada
una factura pot quedar pendent, parcialment cobrada o cobrada
una factura no es duplica per cada fraccio
```

Un pagament fraccionat pot venir de Redsys, transferencia, efectiu registrat a intranet o compensacio. El SIF ha de separar:

- import total facturat;
- imports cobrats;
- imports pendents;
- data limit o calendari de pagament;
- enllac de pagament actiu/inactiu;
- factura o factures assignades.

Si encara no hi ha factura i el cobrament crea obligacio fiscal, el flux correcte es:

```text
issueInvoice() amb bloc payment del primer cobrament
```

Si la factura ja existeix:

```text
registerPayment()
payment_allocation parcial
recalcular ESTAT_COBRAMENT
```

Idempotencia:

```text
REDSYS|{SOURCE_TYPE}|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
TRANSFERENCIA|REF:{REFERENCIA_BANCARIA}
MANUAL|FRACCIO|ID_INSC:{ID_INSC}|DATA:{DATA}|IMPORT:{IMPORT}|USUARI:{ID_USUARI}
COMPENSACIO|UUID_CREDIT:{UUID_CREDIT}|FACT:{UUID_FACTURA}|IMPORT:{IMPORT}
```

Regles especials:

- el mateix `IDPAG` pot tenir diversos intents Redsys i diversos `DS_ORDER`;
- la deduplicacio Redsys es fa per `DS_ORDER`, no nomes per `IDPAG`;
- si una notificacio Redsys es repeteix, es retorna l'estat ja registrat;
- `FRACCIO` pot quedar sincronitzat com a resum historic, pero la prova primaria es `payment_transaction` + `payment_allocation`;
- els recordatoris de pagament han de mostrar factura, cobrat, pendent, data limit i URL controlada de `pay.prisma.cat`.

## Compensacio i saldo

Regla:

```text
compensacio no es edicio d'import
saldo no es descompte silencios
```

Una compensacio pot representar:

- `SALDO_A_FAVOR`: credit creat per una baixa, devolucio no monetaria o pagament de mes;
- `DESCOMPTE_COMERCIAL`: rebaixa concedida abans d'emetre factura;
- `DESCOMPTE_INCIDENCIA`: ajust per incidencia o canvi imputable a PrisMa;
- `BECA_INTERNA`: import assumit internament;
- `AJUST_MANUAL`: regularitzacio autoritzada amb motiu documentat.

Criteri fiscal:

- si s'aplica abans d'emetre factura, queda congelada com a descompte o linia ajustada dins `factura_linia`;
- si neix despres d'una factura emesa i redueix el servei o l'import facturat, cal rectificativa;
- si es un saldo disponible per usar en una factura futura, queda registrat com `credit_balance`;
- quan s'usa el saldo, es registra un moviment economic `COMPENSATION` i una `payment_allocation`;
- no es modifica `A_PAGAR`, `PAGAMENT` o `IMPORT` sense motiu, log i relacio fiscal.

Saldo per baixa:

```text
baixa confirmada
client decideix saldo
crear credit_balance a nom del titular economic habitual
rectificativa si la factura original queda reduida o anul·lada
factura futura usa saldo com COMPENSATION
```

El titular habitual del saldo sera l'alumne/client que ha pagat. Si el pagador real es empresa/responsable, el saldo ha de quedar a nom del receptor economic que PrisMa decideixi i amb justificacio interna.

Els saldos per baixa no caduquen automaticament. Si secretaria detecta saldo molt antic, per exemple superior a 5 anys, el cas s'ha de revisar manualment abans d'usar-lo o tancar-lo.

## Curs normal Redsys

Estat actual:

- Redsys confirma pagament a `realitzaPagamentAutomatic.php`;
- es busca inscripcio per `IDPAG`;
- es calcula factura i s'insereix a `web.factures`;
- s'actualitza `inscripcions.PAGAMENT`, `DATA PAG`, `FACTURA_RELACIONADA` i `FRACCIO`;
- s'envien correus.

Dades actuals conegudes:

- abans de pagar ja existeix una fila a `web.inscripcions`;
- `IDPAG` identifica el pagament/enllac de pagament;
- el mateix `IDPAG` pot tenir diversos intents Redsys;
- Redsys retorna `Ds_Order`, que actualment es guarda a `web.factures.NUM_COMANDA`;
- `A_PAGAR` es el total esperat;
- `PAGAMENT` es el pagat real acumulat;
- `DATA PAG` s'omple quan Redsys confirma o quan es valida transferencia des de intranet;
- `FACTURA_RELACIONADA` apunta a la familia/agrupacio de factura;
- nom del curs i convocatoria surten de `ANY`, `MES`, `CURS`, `Grup` i taula `curs`;
- `CONCEPTE1` exemple: `Curs Gestio Emocional en Moments de Perdua i Dol`;
- `CONCEPTE2` exemple: `Convocatoria juliol 2025`;
- IVA: 0%, amb mencio d'exempcio a la factura.

Canvi necessari:

```text
validar signatura Redsys
detectar DS_ORDER duplicat
carregar inscripcio per IDPAG
detectar si ja hi ha factura previa real
si existeix factura -> registerPayment()
si no existeix -> issueInvoice()
actualitzar relacions i pagament
enviar correus
```

Idempotencia orientativa:

```text
REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Pendent de tancar:

- payload JSON definitiu cap al SIF;
- resposta esperada del SIF;
- tractament concret si Redsys repeteix notificacio;
- prova de concurrencia;
- correu final amb PDF o enllac segur.

## Pack

Regla futura:

```text
1 pagament -> 1 factura -> N linies
```

Normalment:

- 2 cursos;
- 2 inscripcions;
- mateix `IDPAG`;
- una linia per curs;
- descompte pack aplicat al segon curs.

Estat actual conegut:

- el pack sol incloure 2 cursos;
- es crea una inscripcio per cada curs;
- el camp que relaciona les inscripcions del pack es `IDPAG`;
- si hi ha un sol pagament, actualment pot acabar en una sola factura;
- si es fracciona o es gestiona manualment, pot haver-hi mes d'una factura;
- el preu del pack surt de la taula de preus relacionada amb la taula de packs;
- el descompte del 25% s'aplica sempre al segon curs;
- el fraccionament o la divisio excepcional del pack nomes ha de ser una accio d'intranet, no una opcio que pugui escollir el client a ecommerce.

Estat final:

```text
1 pagament de pack
    -> 1 factura
    -> 2 linies de factura
    -> descompte aplicat a la linia del segon curs
```

Idempotencia orientativa:

```text
REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Criteri per excepcions:

- si el pack te un sol pagament real, es genera una sola factura amb totes les linies del pack;
- si excepcionalment intranet registra mes d'un pagament real, es genera una factura per cada pagament real, amb idempotencia propia i sense partir un mateix `DS_ORDER` en diverses factures;
- les dues inscripcions del pack continuen vinculades pel mateix `IDPAG`, pero la deduplicacio de callback es fa amb `DS_ORDER`.

Pendent de tancar:

- SQL/estructura exacta de taules de pack;
- camps de preu base i descompte per linia, validats contra el SQL real;
- exemple real de `factura_linia` per pack provat amb dades de preproduccio;
- correu associat.

## Grup

Regla futura:

```text
1 pagament -> 1 factura -> N linies
```

Una linia per participant.

Motiu:

- necessitat de justificacio per Tripartita/FUNDAE;
- traçabilitat per participant.

La factura de grup pot estar a nom d'una escola/empresa o d'un responsable particular.

Estat actual conegut:

- una empresa o persona pot pagar per N participants;
- hi ha una fila a `inscripcions` per participant;
- el receptor fiscal no sempre es empresa/escola: si el grup es d'una escola o empresa, la factura va a l'entitat; si es un grup d'amics o particular, pot anar a un responsable particular;
- el preu per participant surt de `descomptes_grup`;
- el nom del participant pot sortir a la linia; el DNI es conserva internament i nomes s'imprimeix si es imprescindible per justificacio.

Estat tecnic 2026-06-14:

- snapshot i payload preparats amb `LegacyGroupSnapshotRepository` i `LegacyGroupInvoicePayloadBuilder`;
- circuit Redsys manual de preproduccio preparat amb `RedsysGroupInvoiceService`, `preflight-redsys-group.php`, `preview-redsys-group.php` i `process-redsys-group.php`;
- circuit manual de `Passar pagaments` preparat amb `ManualGroupInvoicePayloadBuilder`, `ManualGroupInvoiceService`, `preflight-manual-group.php`, `preview-manual-group.php` i `process-manual-group.php`;
- els processadors permeten `--sync-legacy` nomes despres d'un resultat SIF correcte;
- pendent d'executar amb PHP/MySQL de test i de validar el SQL final de `descomptes_grup` abans d'activacio real.

## Regal

Regla:

- factura al comprador;
- destinatari no genera factura quan bescanvia el codi;
- la inscripcio posterior es vincula al regal/factura original.

Estat actual conegut:

- el comprador entra a l'apartat de regalar un curs;
- escull curs o tipus de curs;
- pot posar dedicatoria;
- posa les seves dades de facturacio;
- paga;
- rep un codi per bescanviar;
- el destinatari omple les seves dades mes endavant quan bescanvia el codi;
- la factura va al comprador, no al beneficiari.
- en el moment de la compra encara no hi ha inscripcio definitiva del destinatari;
- el registre operatiu del regal conserva comprador, curs, codi regal, origen, desti i `FACT_REL`.

Estat final:

```text
compra regal
    -> factura al comprador
    -> codi regal
    -> bescanvi posterior
    -> inscripcio del destinatari sense factura nova
```

Idempotencia orientativa:

```text
REDSYS|REGAL|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Regla de bescanvi:

- la venda del regal es factura una sola vegada al comprador;
- el SIF rep `SOURCE_TYPE = REGAL` i `SOURCE_ID = regal.ID`;
- el codi regal pot aparèixer al concepte visible de la linia;
- quan el destinatari bescanvia el codi, es crea o completa la inscripcio sense generar factura nova;
- la relacio entre regal, factura i inscripcio posterior s'ha de conservar a `fact_rels` o taula equivalent, no recalcular a partir de dades vives.

Pendent de tancar:

- SQL/estructura exacta de taules de regals;
- relacio entre codi regal, comprador, destinatari i factura;
- visibilitat de factura a intranet alumne;
- correu de compra regal i correu de bescanvi.

## USOC

Regla:

- l'alumne paga la seva part i rep factura per aquesta part;
- USOC paga la diferencia i rep factura per la diferencia.

Matisos recuperats del xat antic:

- el cas actual es `curs afiliat d'USOC`;
- `TIPUS_DESC = 4` identifica el descompte `Afiliat USOC`;
- `VALID_DESC` controla la validacio: `0` no validat, `1` validat i valid, `2` validat i no valid;
- el descompte USOC es del 25% i es valida manualment a la intranet despres de confirmar afiliacio amb USOC;
- el text historic de concepte indicava que el pagament de la diferencia el realitza l'entitat USOC;
- en el cas normal recuperat, l'alumne fa un primer pagament de 10 euros i USOC paga la diferencia;
- hi ha un cas especial historic `Altres: Curs gratüit USOC` que pot usar el parametre `anticipi-preu-usoc`.

La factura de l'alumne i la factura d'USOC han de compartir referencia interna a la mateixa inscripcio/curs, pero no s'han de fusionar en una sola factura amb dos pagadors.

## Factura abans de cobrament

Regla:

```text
Si porta A2026/x es factura real.
Si es document modificable, ha de ser proforma/pressupost sense numero fiscal.
```

Quan la factura abans de cobrament ja existeix:

```text
el pagament posterior no crea factura nova
el pagament posterior es registra amb registerPayment()
```

Context actual recuperat:

- historicament `E_FACT` podia confondre's amb factura electronica i factura abans de pagar; queda separat;
- quan es genera una factura abans de cobrar, normalment es coneixen import i participants, pero poden canviar per anulacio de curs o per afegir participant;
- encara que l'empresa no pagui, la factura abans de cobrar es una factura real si s'ha emes perque la necessiten per pagar;
- aquesta factura es comptabilitza/exporta com una factura cobrada, encara que economicament quedi pendent;
- el pagament posterior no pot crear una segona factura.

## Proformes i documents previs

PrisMa no treballara amb proformes fiscals separades dins del SIF.

Criteri:

- si un document porta numero fiscal o es tracta com a factura, es factura real i entra al SIF;
- si es un pressupost o document editable abans d'emetre, no porta serie fiscal, no entra al SIF i no es comptabilitza com a factura;
- les antigues "proformes" que en realitat es feien servir com a factura s'han de reclassificar documentalment com a factura real o eliminar com a concepte fiscal ambigu.

## Canvi de curs

Regla:

- si no hi ha factura emesa, es pot actualitzar inscripcio amb historial;
- si hi ha factura emesa i canvia concepte/import, cal accio fiscal;
- si el nou curs es mes car, queda diferencia pendent;
- si el nou curs es mes barat, hi pot haver retorn o saldo;
- si hi ha despeses de gestio, han de quedar documentades;
- si hi ha descompte excepcional, no s'ha de tocar nomes `A_PAGAR`, cal motiu intern.

Matisos del funcionament actual:

- el sistema calcula automaticament el nou `A_PAGAR` tenint en compte el descompte anterior si aplica;
- si el descompte original no aplica al nou curs, el sistema ho detecta automaticament;
- el primer canvi pot ser gratuit i despres poden aplicar-se despeses de gestio segons el cas;
- les despeses de gestio actualment estan incloses dins l'import final, no com a linia separada;
- si hi ha part pagada i el nou curs es mes barat, el retorn el fa Adam manualment i despres es genera la rectificativa quan pertoqui;
- si el curs canvia pero l'import es igual, igualment cal rectificativa si la factura ja no descriu el servei real.

Flux final:

```text
canvi de curs sol·licitat
registrar historic de canvi
comparar curs antic/nou, imports, descomptes i pagat
si no hi ha factura emesa -> ajustar inscripcio i pagament pendent amb log
si hi ha factura emesa i canvia servei/import -> rectificativa o complementaria
si nou curs mes car -> crear diferencia pendent i URL de pagament
si nou curs mes barat -> decidir retorn o saldo
si hi ha despeses de gestio -> linia o motiu intern documentat
```

La taula d'historic de canvis de curs ha de conservar com a minim:

- inscripcio origen i desti;
- curs/edicio/grup antic i nou;
- import antic i nou;
- descompte antic i nou;
- diferencia;
- despeses de gestio;
- motiu;
- usuari que fa el canvi;
- data;
- factura original;
- rectificativa, factura complementaria o pagament de diferencia relacionat.

Quan el pagament posterior correspon a una diferencia per canvi de curs, el callback Redsys o `Passar pagaments` ha de detectar el registre de canvi i registrar el cobrament amb `SOURCE_TYPE = CANVI_CURS_DIFERENCIA`, no com si fos una nova inscripcio ordinaria.

## Baixa

Regla:

- baixa = event sobre inscripcio;
- devolucio/saldo = event economic posterior;
- rectificativa = nomes quan la decisio economica ho exigeix.

Funcionament actual recuperat:

- la baixa marca la inscripcio, pero no toca necessariament el pagament o la factura en aquell moment;
- si hi ha retorn de diners, primer es fa o confirma el retorn i despres es genera la factura negativa/rectificativa des de `Consulta - Edita - Anula factura`;
- les devolucions poden fer-se per Redsys, transferencia o manualment;
- una devolucio parcial pot afectar una linia concreta quan el cas ho permeti.

### Baixa amb saldo a favor

Criteri funcional recomanat:

```text
si el curs/servei original queda totalment o parcialment sense efecte
i el client decideix deixar els diners com a saldo
llavors el saldo neix en aquell moment
i s'ha de valorar rectificativa de la factura original en aquell moment
```

Flux:

```text
baixa confirmada
decisio client = saldo
crear credit_balance
generar rectificativa si el servei facturat original queda reduit/anul·lat
en factura futura: usar saldo com COMPENSATION
```

Si no hi ha devolucio ni saldo i la factura original continua corresponent a un servei prestat o import no retornable, la factura pot quedar igual.

## Devolucio

Regla:

```text
la devolucio es un moviment economic
la rectificativa es la correccio fiscal
```

Les devolucions poden fer-se per Redsys, transferencia o manualment. Adam/Pablo poden executar o registrar la devolucio segons el procediment intern, i la rectificativa es genera despres del retorn de diners o de la decisio economica confirmada.

Flux final:

```text
devolucio aprovada
executar o registrar retorn real
crear payment_transaction TIPUS_MOVIMENT = REFUND
assignar refund a factura/linia afectada
generar rectificativa si redueix o anul·la l'import facturat
vincular baixa/canvi/incidencia amb factura original i rectificativa
notificar segons plantilla o enllac segur
```

Casos:

- devolucio total: rectificativa total o per substitucio segons criteri fiscal;
- devolucio parcial: rectificativa parcial, idealment vinculada a la linia afectada si es pot identificar;
- devolucio + saldo: es poden combinar `REFUND` i `credit_balance` si el client vol retornar una part i deixar una altra com saldo;
- pagament duplicat: si no hi ha servei nou, no crea factura nova; es registra incidencia/pagament duplicat i devolucio o saldo.

Idempotencia orientativa:

```text
REFUND|FACT:{UUID_FACTURA}|METODE:{METODE}|DATA:{DATA}|IMPORT:{IMPORT}|REF:{REFERENCIA}
```

## Rectificatives

Regla:

```text
qualsevol canvi fiscal posterior a una factura emesa es resol amb rectificativa,
factura complementaria o event economic auditat, mai amb update silencios.
```

S'admeten dos modes de disseny:

- `DIFERENCIES`: rectifica nomes l'import o linies afectades;
- `SUBSTITUCIO`: substitueix fiscalment una factura per una versio correcta, util per dades fiscals o errors de receptor/concepte quan correspongui.

Motius controlats:

- `DADES_FISCALS`;
- `DEVOLUCIO_TOTAL`;
- `DEVOLUCIO_PARCIAL`;
- `CANVI_CURS`;
- `CANVI_CONCEPTE`;
- `BAIXA`;
- `AJUST_IMPORT`;
- `DESCOMPTE_POSTERIOR`;
- `ERROR_OPERATIU`;
- `MIGRACIO_HISTORICA`.

Regles:

- `R` te serie i numeracio propia;
- cada rectificativa apunta directament a la factura rectificada;
- `FACTURA_RELACIONADA` pot conservar-se com a agrupacio historica, pero no substitueix la relacio directa;
- canvi de nom/NIF/CIF/rao social despres d'emetre factura es tracta com rectificativa per substitucio o criteri fiscal validat;
- anul·lacio historica deixa de ser una factura negativa lliure: passa per motiu, mode i relacio directa.

## Morositat

Regla:

- morositat no es baixa;
- factura continua existint;
- no hi ha rectificativa nomes pel fet de reclamar;
- cal documentar reclamacions.

Quan la reclamacio acaba en cobrament d'una factura SIF existent:

```text
reclamacio/morositat
    -> factura original continua vigent
    -> cobrament rebut
    -> registerPayment()
    -> payment_allocation CLAIM_PAYMENT
    -> recalcul ESTAT_COBRAMENT
```

Estat tecnic 2026-06-14:

- circuit CLI preparat amb `ClaimPaymentPayloadBuilder`, `ClaimPaymentService`, `preflight-claim-payment.php`, `preview-claim-payment.php` i `process-claim-payment.php`;
- localitza factura per `UUID_FACTURA` o `NUM_VISIBLE`;
- no crea factura, no crea registre fiscal, no toca hash chain i no sincronitza legacy;
- idempotencia `CLAIM|REF:{REFERENCIA_RECLAMACIO}` si hi ha referencia, o fallback `CLAIM|FACT:{NUM_VISIBLE}|DATA:{DATA}|IMPORT:{IMPORT}|USUARI:{USUARI}` quan no n'hi ha;
- pendent d'integrar pantalla/URL/correus finals de reclamacio i executar PHP/MySQL de test.

## Payloads SIF per cas

Aquest apartat fixa el criteri de payload. No substitueix el codi final, pero evita que cada canal inventi la factura de manera diferent.

### Estructura comuna

Tots els casos que creen factura han d'enviar:

```json
{
  "idempotency_key": "...",
  "tipus_serie": "A",
  "source_channel": "REDSYS | INTRANET | TRANSFERENCIA | MANUAL",
  "source_type": "CURS | PACK | GRUP | REGAL | USOC | MANUAL | FACTURA_ABANS_COBRAR | RECTIFICATIVA",
  "source_id": 123,
  "emesa_abans_cobrament": false,
  "e_fact": false,
  "billing": {
    "nom_rao": "...",
    "nif_cif": "...",
    "adreca": "...",
    "cp": "...",
    "poblacio": "...",
    "pais": "ES",
    "email": "..."
  },
  "lines": [],
  "payment": null,
  "references": {}
}
```

Regles:

- `billing` es snapshot fiscal; no es recalcula mes endavant des de dades vives.
- `lines` sempre inclou imports base, descompte, exempcio IVA i total final.
- `payment` nomes s'inclou si el pagament i la factura neixen al mateix flux.
- si la factura ja existia, no s'envia `issueInvoice()`: s'envia `registerPayment()`.
- els codis promocionals no son un `source_type` de factura: son dades de descompte congelades dins la linia.

### Curs normal

Idempotencia:

```text
REDSYS|CURS|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Linies:

```text
1 linia = 1 curs / taller / jornada
SOURCE_TYPE = INSCRIPCIO
SOURCE_ID = inscripcions.ID
```

La linia ha de conservar:

- `concepte`: `Curs ...` o titol de jornada/taller;
- `detall`: `Convocatoria ...`;
- `preu_unitari`;
- descompte congelat, si existeix;
- `iva_regim = EXEMPT`;
- mencio d'exempcio IVA en PDF.

### Pack

Idempotencia:

```text
REDSYS|PACK|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Regla:

```text
1 pagament de pack -> 1 factura -> una linia per curs
```

Normalment:

- linia 1: primer curs, sense descompte pack;
- linia 2: segon curs, amb `DESC_ORIGEN = PACK` i descompte del 25%;
- cada linia apunta a la seva `inscripcions.ID`;
- el preu del pack surt de les taules de pack/preu existents, pendent d'incorporar al document quan es passi el SQL;
- si hi ha fraccionament excepcional via intranet, cada pagament real te factura/idempotencia propia i les linies/imports han de coincidir amb el snapshot fiscal aprovat.

Exemple orientatiu:

| Linia | Origen | Preu base | Descompte | Total |
| --- | --- | ---: | ---: | ---: |
| Curs A | `SOURCE_TYPE = INSCRIPCIO`, `SOURCE_ID = inscripcions.ID` | 120,00 | 0,00 | 120,00 |
| Curs B | `SOURCE_TYPE = INSCRIPCIO`, `SOURCE_ID = inscripcions.ID`, `DESC_ORIGEN = PACK` | 120,00 | 30,00 | 90,00 |

Total factura d'exemple: 210,00.

### Grup de persones

Idempotencia:

```text
REDSYS|GRUP|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Regla:

```text
1 pagament de grup -> 1 factura -> una linia per participant
```

La factura va al receptor fiscal:

- escola/empresa, si el grup el paga una entitat;
- responsable particular, si el grup no es d'empresa.

La linia ha d'identificar el participant prou per justificacio interna. El nom del participant pot sortir a la factura. El DNI nomes s'ha de mostrar si es necessari per justificacio; si no, es pot conservar internament vinculat a `SOURCE_ID = inscripcions.ID`.

Regla recuperada del xat antic:

- `TIPUS_INSC = G` identifica inscripcions de grup;
- `IDPAG` agrupa els membres del grup;
- `respGrups` relaciona el responsable amb el `IDPAG`;
- `descomptes_grup` aporta el preu/descompte per participant;
- cada linia de factura ha de portar `SOURCE_TYPE = INSCRIPCIO` i `SOURCE_ID = inscripcions.ID`;
- si FUNDAE/Tripartita exigeix identificacio forta, el DNI pot quedar a `factura_linia` o en annex intern, pero no s'ha d'imprimir per defecte.

Exemple orientatiu:

| Linia | Origen | Text visible | Dada interna |
| --- | --- | --- | --- |
| Participant 1 | `SOURCE_TYPE = INSCRIPCIO`, `SOURCE_ID = inscripcions.ID` | Curs X - Participant: Maria Exemple | DNI intern si cal justificacio |
| Participant 2 | `SOURCE_TYPE = INSCRIPCIO`, `SOURCE_ID = inscripcions.ID` | Curs X - Participant: Joan Exemple | DNI intern si cal justificacio |

### Regal

Idempotencia:

```text
REDSYS|REGAL|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
```

Regla:

```text
compra regal -> factura al comprador -> codi regal -> bescanvi posterior sense factura nova
```

La factura:

- receptor: comprador;
- `SOURCE_TYPE = REGAL`;
- `SOURCE_ID = ID_REGAL`;
- concepte visible: `Regal curs ...` o `Regal curs de X hores`;
- pot incloure `CODI REGAL` com a dada visible o interna segons criteri de comunicacio;
- destinatari: no es receptor de factura en el moment de compra.

Quan el destinatari bescanvia el codi, es crea o completa inscripcio, pero no es crea una factura nova.

Consultes actuals identificades:

- `buscarRegNoPayByCodi` localitza regals pendents de factura per `CODI`;
- `buscarRegNoPayByDni` localitza regals pendents de factura per NIF del comprador;
- `buscarRegalById` recupera curs, comprador, adreca, codi, `FACT_REL`, `ORIGEN` i `DESTI`;
- `updFactRegal` actualitza `regal.FACT_REL` amb la factura relacionada.

El SIF final no ha de dependre nomes de `FACT_REL`: ha de conservar `UUID_FACTURA`, idempotencia, relacio amb `regal.ID` i vincle posterior amb la inscripcio del destinatari quan es bescanviï.

### USOC

Regla:

```text
alumne paga la seva part -> factura a l'alumne
USOC paga diferencia -> factura a USOC
```

Factura alumne:

- receptor: alumne;
- import final: import pagat per l'alumne;
- linia amb preu base i descompte visible generic;
- motiu intern: `TIPUS_DESC = 4 / USOC`;
- `VALID_DESC = 1` abans d'aplicar el descompte USOC i abans d'emetre factura amb import reduit;
- si `VALID_DESC = 2` o afiliacio no confirmada, cal recalcular sense descompte abans de pagar/facturar.

Factura USOC:

- receptor: USOC;
- import: diferencia assumida per USOC;
- linia vinculada al curs/alumne i convocatoria;
- relacio interna amb la factura de l'alumne i la inscripcio.

Idempotencia orientativa:

```text
REDSYS|USOC_ALUMNE|IDPAG:{IDPAG}|ORDER:{DS_ORDER}
INTRANET|USOC_ENTITAT|ID_INSC:{ID_INSC}|FACT_ALUMNE:{UUID_FACTURA_ALUMNE}
```

La part d'USOC no pot deduir-se nomes de la diferencia entre `A_PAGAR` i `PAGAMENT` sense snapshot: el SIF ha de conservar import base, import pagat per l'alumne, import assumit per USOC, `TIPUS_DESC`, `VALID_DESC`, data/usuari de validacio i receptor fiscal complet d'USOC.

### Codis promocionals i promocions temporals

Regla:

```text
ecommerce/intranet valida promocio
    -> calcula preu/descompte
    -> SIF congela resultat a factura_linia
```

El SIF no ha de validar si un codi promocional es valid, caducat o usat. Aquesta validacio es fa abans de cridar `issueInvoice()`.

Informacio recuperada:

- els codis promocionals es poden introduir al camp `Codi promocional` del formulari d'inscripcio;
- `promocions` conserva `CODI_DESCOMPTE`, `DNI`, `MES`, `CURS`, `PERCENTATGE`, `USED`, `DATAI` i `DATAF`;
- `cnsSiTePromocioDispo` comprova codi, DNI, `USED = 0` i vigencia `DATAI/DATAF`;
- `updDataFPromocio` pot tancar la vigencia posant `DATAF = CURRENT_TIME`;
- `MACABODETITULAR#...` es un exemple de codi personal i intransferible, d'un sol us i valid fins a una data;
- `descomptes.TIPUS` de l'11 al 99 identifica promocions temporals per dates.

La linia fiscal ha de conservar:

- `desc_origen = CODI_PROMO` o `PROMOCIO_TEMPORAL`;
- codi aplicat, si existeix;
- percentatge o import fix;
- import descomptat;
- text visible generic;
- base i total final;
- referencia interna a `promocions` o `descomptes`, si existeix.

Si el codi caduca o es marca usat despres d'emetre factura, la factura no canvia. Si el codi es declara invalid abans d'emetre, cal recalcular sense el descompte o obrir incidencia.

### Factura manual

Regla:

```text
intranet -> issueInvoice()
```

Origen:

- dades fiscals manuals; o
- entitat seleccionada de `entitats` / `entitats_resp`.

Sempre ha d'incloure:

- receptor fiscal;
- linies estructurades;
- import base/descompte/total;
- responsable intern que l'emet;
- correu o enllac segur al receptor quan correspongui.

Flux final:

```text
usuari autoritzat obre factura manual
tria entitat/responsable existent o introdueix dades fiscals manuals
afegeix linies estructurades
el servidor valida permisos i imports
issueInvoice()
SIF retorna UUID i numero fiscal
es genera document/enllac segur
es notifica client/empresa/gestio interna segons cas
```

Regles:

- no es pot crear factura manual escrivint directament a `web.factures`;
- la factura manual tambe entra a hash chain, cua AEAT i `factura_documents`;
- el receptor fiscal es snapshot, encara que provingui de `entitats` o `entitats_resp`;
- si la factura manual neix cobrada, el payload inclou `payment`;
- si neix pendent, el cobrament posterior va per `registerPayment()`.

Estat tecnic 2026-06-14:

- circuit CLI preparat amb `ManualInvoicePayloadBuilder`, `ManualInvoiceService`, `preview-manual-invoice.php` i `process-manual-invoice.php`;
- `source_channel = INTRANET` i `source_type = MANUAL`;
- usuari intern obligatori;
- idempotencia per referencia o per usuari/data/hash del payload fiscal;
- pagament inicial opcional dins `issueInvoice(payment)`;
- pendent d'executar amb PHP/MySQL de test, pantalla final, permisos finals, correus o enllac segur i evidencies.

Idempotencia orientativa:

```text
INTRANET|MANUAL|USUARI:{ID_USUARI}|DATA:{DATA}|HASH_LINIES:{HASH}
```

### Factura abans de cobrar

Regla:

```text
factura real abans de cobrament -> issueInvoice() amb EMESA_ABANS_COBRAMENT = 1
pagament posterior -> registerPayment()
```

No s'ha de fer servir `E_FACT` per indicar que es abans de cobrar.

`E_FACT` nomes indica factura electronica. Per tant, la intranet ha de tenir una accio separada per marcar una factura com electronica.

Si s'afegeixen o treuen participants despres d'emetre factura real, cal rectificativa o factura complementaria segons el cas fiscal.

### Rectificativa

Regla:

```text
canvi posterior sobre factura emesa -> nova factura R o rectificativa per substitucio/diferencies
```

El payload ha d'indicar:

- `tipus_serie = R`;
- factura original rectificada;
- motiu: `DADES_FISCALS`, `DEVOLUCIO`, `CANVI_CURS`, `BAIXA`, `AJUST_IMPORT`, etc.;
- mode: substitucio o diferencies;
- linies rectificades;
- relacio a `factura_rectificacio`.

Casos que generen rectificativa:

- canvi de dades fiscals en factura ja emesa;
- canvi de curs amb canvi de concepte;
- devolucio parcial o total;
- baixa amb saldo/devolucio quan el servei facturat queda reduit o anul·lat;
- modificacio d'import/descompte sobre factura ja emesa.

Estat actual i canvi necessari:

- fins ara les rectificatives s'han tractat sobretot com a factures negatives;
- amb VERI*FACTU cal suportar rectificativa per substitucio o per diferencies segons el cas;
- el vincle historic `FACTURA_RELACIONADA` agrupa factures relacionades, pero no substitueix la relacio directa entre factura rectificativa i factura rectificada;
- el tipus concret de rectificativa per canvis de dades fiscals s'ha de validar amb criteri fiscal abans de produccio.

### Migracio de factures historiques

Regla:

```text
les factures historiques es migren com a historic no VERI*FACTU
no es generen registres VERI*FACTU retroactivament
```

Objectiu:

- conservar `web.factures` i la numeracio original;
- marcar origen historic i estat `NO_VERIFACTU`;
- conservar PDF antic si existeix o marca de document no immutable si no existeix;
- vincular factures historiques a `fact_rels`;
- conservar `FACTURA_RELACIONADA`, inscripcions, pagaments i relacions operatives;
- impedir que la migracio alteri numeracio fiscal nova;
- separar clarament factures historiques de factures emeses pel SIF.

Regles:

- una factura historica no entra a hash chain nova;
- una rectificativa nova sobre factura historica, si cal en produccio, s'ha de tractar com a operacio SIF nova amb referencia a l'historic;
- les consultes han de mostrar si una factura es `VERIFACTU` o `NO_VERIFACTU`;
- la migracio ha de tenir informe de control: totals per any/serie, primer/ultim numero, imports i incidencies.

Estat tecnic 2026-06-14:

- circuit CLI preparat amb `HistoricalInvoicePayloadBuilder`, `HistoricalInvoiceMigrationRepository`, `HistoricalInvoiceMigrationService`, `preview-historical-invoice-migration.php` i `process-historical-invoice-migration.php`;
- importa `NUM_VISIBLE`, serie, any, numero, receptor, totals, linies, relacio `HISTORIC_LINK` i document antic opcional amb hash;
- marca `ESTAT_FACTURA = HISTORICAL`, `ESTAT_AEAT = NO_VERIFACTU` i `SOURCE_CHANNEL = MIGRACIO`;
- no toca `factura_registres`, `fiscal_queue`, `fiscal_sequence` ni `fiscal_chain_state`;
- pendent d'executar amb PHP/MySQL de test, validar totals agregats per any/serie i preparar informe de control de migracio.

## Fluxos del panell SIF

Els fluxos següents no creen necessàriament factures, pero formen part del funcionament operatiu del SIF.

### Dashboard SIF

Flux:

```text
usuari entra a pay.prisma.cat/sif
SIF calcula resum operatiu
mostra indicadors de factures, AEAT, PDF/QR i incidencies
usuari obre el detall que calgui
```

No modifica dades fiscals.

### Consulta de factures SIF

Flux:

```text
usuari cerca factura
SIF mostra factura, linies, pagaments, documents i estat AEAT
si cal rectificar -> usuari autoritzat inicia flux de rectificativa
si cal descarregar -> SIF serveix PDF immutable
```

Regla:

```text
consulta no edita factura emesa
```

### Registres AEAT

Flux:

```text
usuari filtra registres
SIF mostra hash, hash anterior, fiscal order i estat AEAT
si hi ha error temporal -> es pot reintentar segons permisos
si hi ha error no resolt -> es crea incidencia
```

### Incidencies SIF

Flux:

```text
SIF detecta error o incidencia
crea incidencia fiscal
intranet pot mostrar indicador/resum
usuari autoritzat entra a pay.prisma.cat/sif/incidencies
assigna responsable
afegeix notes o accions
resol incidencia
SIF registra log de resolucio
```

Regla:

```text
la resolucio oficial de la incidencia es fa al SIF
```

### Documents SIF

Flux:

```text
factura emesa
worker genera PDF/QR/XML si correspon
SIF guarda document i hash
usuari consulta o descarrega document
si falla document -> incidencia SIF
```

Regla:

```text
document fiscal generat no es modifica silenciosament
```

### Versions SIF

Flux:

```text
es prepara nova versio
es documenten canvis
es passen proves
es vincula declaracio responsable si cal
usuari autoritzat marca versio activa
SIF conserva historial
```

### Exportacions SIF

Flux:

```text
usuari tria periode i tipus d'export
SIF genera fitxer
calcula hash
guarda registre d'exportacio
permet descarrega
```

### Configuracio SIF

Flux:

```text
usuari autoritzat entra a configuracio
modifica parametre tecnic
SIF valida permisos
SIF guarda canvi
SIF registra log
```

Regla:

```text
cap canvi de configuracio sense log
```

### Apartat VERI*FACTU de la intranet

Flux:

```text
usuari entra a intranet / VERI*FACTU
intranet consulta resum al SIF
mostra indicador visual de pendents
mostra resum d'incidencies
ofereix boto obrir panell SIF
ofereix acces a documents SIF i exportacions
```

Regla:

```text
la intranet resumeix i enllaça
el SIF conserva i gestiona
```
