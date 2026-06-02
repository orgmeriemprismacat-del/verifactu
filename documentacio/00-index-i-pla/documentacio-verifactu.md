# Documentacio interna del projecte VERI*FACTU

> Document intern de projecte. Recull els canvis funcionals, tecnics i operatius necessaris per adaptar la web, intranets, TPV, pagaments, factures, rectificatives, PDFs i consultes al nou SIF centralitzat.

## 0. Documents del projecte

Aquest document funciona com a document mare. La documentacio detallada es reparteix en documents especifics:

- `../03-canvis-pendents/04-fluxos-facturacio.md`: fluxos de facturacio per cas.
- `../04-estat-final/05-model-bd-sif.md`: model de base de dades fiscal i relacio amb BD intranet.
- `../03-canvis-pendents/06-integracio-redsys-pay-prisma.md`: integracio Redsys i migracio a `pay.prisma.cat`.
- `../03-canvis-pendents/07-pantalles-intranet.md`: canvis necessaris en pantalles d'intranet actuals.
- `../03-canvis-pendents/08-correus-i-plantilles.md`: correus, plantilles i destinataris.
- `../03-canvis-pendents/09-checklist-posada-en-produccio.md`: checklist abans d'activar el SIF.
- `../03-canvis-pendents/10-procediments-intranet-ecommerce.md`: procediment de cada apartat de la intranet, ecommerce i canals relacionats.
- `../03-canvis-pendents/11-inventari-canvis-pendents.md`: inventari complet de canvis detectats i pendents.
- `../02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md`: documentacio global del sistema web, ecommerce, intranets, pagaments i SIF per a una persona externa.
- `../02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md`: mapa de bases de dades, taules existents, taules noves i relacions entre BDs.
- `14-pla-documentacio-i-auditoria.md`: pla documental, que ha d'estar disponible al SIF i que es conserva com a evidencia.
- `26-matriu-cobertura-casos.md`: matriu de cobertura de casos documentats, parcials i pendents.
- `27-informe-auditoria-documental.md`: informe d'auditoria documental sobre si la documentacio actual es suficient.
- `../04-estat-final/15-estat-final-sistema.md`: estat final esperat del sistema complet.
- `../04-estat-final/16-estat-final-pantalles.md`: estat final de pantalles d'intranet, ecommerce, intranet alumne i consulta.
- `../04-estat-final/17-estat-final-bd-relacions.md`: model final de BD i relacions entre bases de dades.
- `../04-estat-final/18-estat-final-operacio-incidencies.md`: operacio final, errors, notificacions, logs i manteniment.
- `../04-estat-final/25-panell-sif-pay-prisma.md`: panell intern del SIF a `pay.prisma.cat`.
- `../05-governanca-operacio/19-registre-versions-i-canvis-sif.md`: versions del SIF i canvis associats.
- `../05-governanca-operacio/20-pla-proves-validacio-sif.md`: pla de proves i validacio abans de produccio.
- `../05-governanca-operacio/21-seguretat-permisos-accessos.md`: seguretat, rols, permisos i bloquejos.
- `../05-governanca-operacio/22-manual-operatiu-intern.md`: manual de treball per usuaris interns.
- `../05-governanca-operacio/23-annex-captures-pantalla.md`: captures finals documentades.
- `../05-governanca-operacio/24-diccionari-camps-i-valors.md`: camps, estats i valors controlats.
- `../01-compliment-aeat/documentacio-sif-aeat.md`: document tecnic/organitzatiu del SIF per compliment AEAT.
- `../01-compliment-aeat/declaracio-responsable-sif-prisma.md`: esborrany de declaracio responsable.

## 0.1. Nota de lectura per a persones externes

Aquest document intern no pressuposa que una persona externa conegui el funcionament de PrisMa. Tot i aixi, per auditoria, inspeccio, assessor fiscal o direccio, la lectura recomanada sera:

1. `../02-context-i-estat-actual/12-documentacio-sistema-ecommerce-intranet-sif.md`
2. `../02-context-i-estat-actual/13-mapa-bases-dades-i-taules.md`
3. `../01-compliment-aeat/documentacio-sif-aeat.md`
4. `../01-compliment-aeat/declaracio-responsable-sif-prisma.md`
5. `../04-estat-final/15-estat-final-sistema.md`
6. `../04-estat-final/16-estat-final-pantalles.md`
7. `../04-estat-final/17-estat-final-bd-relacions.md`
8. `../04-estat-final/18-estat-final-operacio-incidencies.md`
9. `../04-estat-final/25-panell-sif-pay-prisma.md`
10. `../05-governanca-operacio/19-registre-versions-i-canvis-sif.md`
11. `../05-governanca-operacio/21-seguretat-permisos-accessos.md`
12. `../05-governanca-operacio/24-diccionari-camps-i-valors.md`

Els documents de `../03-canvis-pendents/` no formen part de la lectura principal externa. Serveixen com a documentacio interna de projecte per planificar, executar i controlar els canvis fins arribar a l'estat final.

El document actual funciona com a guia mare del projecte, pero la documentacio de context complet del sistema es mantindra separada per no barrejar decisions de projecte amb descripcio operativa del sistema.

## 1. Objectiu

L'objectiu del projecte es adaptar el sistema de facturacio de PrisMa a la modalitat VERI*FACTU mitjancant un SIF centralitzat.

El canvi principal es deixar de tenir diversos punts que decideixen factures i passar a tenir:

- diversos canals que proposen operacions facturables;
- un servei central que emet factures fiscals;
- una base de dades fiscal unica;
- una cadena hash global;
- cua d'enviament AEAT;
- PDFs/QR immutables;
- traçabilitat d'incidencies, rectificatives, canvis de curs, baixes, devolucions i saldos.

## 2. Arquitectura General

Canals existents:

- ecommerce propi;
- Redsys / TPV virtual;
- intranet nova;
- intranet antiga, sense gestio fiscal activa;
- intranet alumne, consulta;
- intranet tutor, sense generacio fiscal;
- Moodle, sense impacte fiscal directe.

Arquitectura prevista:

```text
Ecommerce / Redsys / Intranet
        |
        v
SIF central a pay.prisma.cat o subdomini fiscal
        |
        v
BD dades fiscals
        |
        +--> hash chain / registres VERI*FACTU
        +--> cua AEAT
        +--> PDF / QR immutable
        +--> relacions amb inscripcions / pagaments
```

Regla principal:

```text
Els canals no assignen numero fiscal.
Els canals no creen la factura final.
Els canals criden el SIF.
El SIF decideix numero, hash, registre, linies, estat AEAT i document.
```

Nota sobre l'estat inicial:

```text
Si un canal historic calcula i genera una factura completa pel seu compte,
aquell comportament s'ha de migrar o encapsular darrere el SIF.
```

La documentacio no defensa un model de "multi-emissor coordinat" com a estat final. El criteri final es SIF centralitzat: els canals proposen, el SIF emet.

## 3. Series Fiscals

Es mantenen dues series:

- `A2026/000001`: factures positives/ordinaries.
- `R2026/000001`: factures rectificatives.

No es creen series diferents per ecommerce, TPV, intranet, empreses, cursos, packs o grups.

El canal/origen es guarda com a dada interna.

La numeracio visible es independent de l'ordre tecnic de cadena fiscal.

Exemple:

```text
FISCAL_ORDER 1001 -> A2026/000010
FISCAL_ORDER 1002 -> R2026/000002
FISCAL_ORDER 1003 -> A2026/000011
```

## 4. Hash Chain

El SIF tindra una cadena hash global unica.

No hi ha hash chain per serie ni per canal. Tot el que emet el SIF entra a la mateixa cadena.

La generacio de numero fiscal, obtencio del hash anterior, insercio de factura, insercio de registre fiscal i actualitzacio de chain state han d'anar dins la mateixa transaccio.

Taules clau:

- `fiscal_sequence`
- `fiscal_chain_state`
- `factura_registres`

Regles critiques:

- no fer `SELECT MAX(NUM)+1`;
- bloquejar `fiscal_sequence` amb `FOR UPDATE`;
- bloquejar `fiscal_chain_state` amb `FOR UPDATE`;
- inserir factura i registre fiscal abans del `COMMIT`;
- no generar hash despres de crear la factura en un procés separat.

## 5. Taules Principals del SIF

### factura

Document fiscal immutable.

Camps principals:

- `UUID_FACTURA`
- `IDEMPOTENCY_KEY`
- `TIPUS_SERIE`
- `ANY_FACT`
- `NUM_SEQ`
- `NUM_VISIBLE`
- `TIPUS_FACTURA`
- `DATA_EMISSIO`
- `EMESA_ABANS_COBRAMENT`
- `ESTAT_COBRAMENT`
- `ESTAT_FACTURA`
- `ESTAT_AEAT`
- snapshot fiscal del receptor:
  - nom/rao social;
  - NIF/CIF;
  - adreca;
  - CP;
  - poblacio;
  - pais;
  - email.
- totals:
  - import base;
  - descompte;
  - base imposable;
  - IVA/exempcio;
  - total.

### factura_linia

Detall fiscal de la factura.

S'utilitza per:

- packs, una linia per curs;
- grups, una linia per participant;
- descomptes;
- codis promocionals;
- despeses de gestio;
- diferencies per canvi de curs;
- rectificatives parcials.

Camps principals:

- `UUID_FACTURA`
- `ORDRE`
- `CONCEPTE`
- `DETALL`
- `QUANTITAT`
- `PREU_UNITARI`
- dades de descompte:
  - origen;
  - mode;
  - tipus;
  - ID de descompte;
  - codi promocional;
  - percentatge;
  - import;
  - text visible;
  - motiu intern.
- imports:
  - base linia;
  - IVA;
  - total linia.
- `SOURCE_TYPE`
- `SOURCE_ID`

### payment_transaction

Moviment economic real o compensacio.

Tipus:

- `CHARGE`: cobrament.
- `REFUND`: devolucio.
- `COMPENSATION`: saldo/compensacio.

Metodes:

- Redsys;
- transferencia;
- compensacio;
- manual.

### payment_allocation

Relacio entre un moviment economic i una factura.

Serveix per:

- factures abans de cobrar;
- pagaments parcials;
- una transferencia que paga diverses factures;
- devolucions parcials;
- compensacions.

### fact_rels

Relacio entre factura fiscal i origen de negoci.

Pot vincular:

- inscripcio;
- regal;
- pack;
- grup;
- entitat;
- rectificacio.

Ha de permetre indicar si la factura es visible o no a l'alumne.

Exemple:

```text
factura d'empresa/grup:
visible a admin/responsable = si
visible a alumne = no
```

### fiscal_queue

Cua d'enviament a AEAT.

Guarda:

- factura;
- idempotency key;
- estat;
- intents;
- proper reintent;
- ultim error;
- bloqueig de worker.

### factura_documents

Documents immutables associats a factura:

- PDF;
- XML;
- QR.

El PDF s'ha de guardar com a fitxer generat, no reconstruir-se sempre a partir de BD viva.

## 6. Idempotencia

Cada operacio facturable ha de tenir una `IDEMPOTENCY_KEY`.

Regla:

```text
mateixa operacio + mateix retry = mateixa factura
mai factura duplicada
```

Exemples:

```text
REDSYS|CURS|IDPAG:123|ORDER:999999
REDSYS|PACK|IDPAG:123|ORDER:999999
TRANSFERENCIA|REF:ABC123|DATA:2026-05-15
INTRANET|FACTURA_ABANS_PAGAR|GRUP:456
```

No es pot usar nomes `ID_INSC`, perque una inscripcio pot tenir diversos pagaments, fraccionaments, canvis o compensacions.

## 7. Endpoints del SIF

### POST /api/factures/issue

Crea una factura fiscal nova.

Casos:

- curs normal;
- pack;
- grup;
- regal;
- factura manual;
- factura abans de cobrar;
- rectificativa.

### POST /api/payments/register

Registra un moviment economic sobre una factura ja existent.

Casos:

- factura abans de pagar que posteriorment es cobra;
- transferencia que paga factura emesa;
- pagament parcial;
- compensacio;
- devolucio.

Regla clau:

```text
si ja hi ha factura real, no es crida issueInvoice per cobrar-la.
es crida registerPayment.
```

## 8. Flux Redsys - Curs Normal

Flux nou:

```text
Redsys confirma pagament
        |
        v
validar signatura Redsys
        |
        v
detectar DS_ORDER duplicat
        |
        v
carregar inscripcio per IDPAG
        |
        v
mirar si hi ha factura previa real
        |
        +--> si existeix: registerPayment()
        |
        +--> si no existeix: issueInvoice()
        |
        v
actualitzar inscripcio amb referencia fiscal
        |
        v
enviar correus
```

El callback de Redsys no ha de calcular `A2026/x` ni inserir directament a `web.factures`.

## 9. Factura Abans de Pagar

Cal separar:

```text
PROFORMA / pressupost
- no te A2026/x
- pot canviar
- no va a VERI*FACTU
- no es comptabilitza com factura

FACTURA ABANS DE COBRAR
- te A2026/x
- va a VERI*FACTU
- te PDF immutable
- es comptabilitza
- si canvia, cal rectificativa o factura complementaria
```

El camp actual `E_FACT` no ha de continuar barrejant significats.

Cal separar:

- factura electronica;
- emesa abans de cobrament.

Quan una factura abans de cobrar ja existeix:

```text
quan arriba pagament -> registerPayment()
no es crea factura nova
```

Aixo resol duplicats en casos d'empresa/responsable de grup.

## 10. Dades de Facturacio

Cal afegir una pantalla intermedia abans del pagament:

```text
dades alumne / responsable / entitat
        |
        v
confirmar dades de facturacio
        |
        v
snapshot fiscal
        |
        v
pagament / factura
```

La factura emesa guarda snapshot fiscal immutable.

Editar dades personals de l'alumne no ha de modificar factures ja emeses.

## 11. Descomptes

El SIF no ha de recalcular tota la logica de descomptes.

La web/intranet calcula i valida. El SIF rep la foto final:

- preu original;
- tipus de descompte;
- ID de descompte;
- codi promocional, si existeix;
- mode:
  - percentatge;
  - import fix;
  - preu fix;
- import descomptat;
- total final;
- text visible;
- motiu intern.

Tipus principals:

- 0: regalar un curs;
- 1: alumne PrisMa;
- 2: Carnet Jove;
- 4: afiliat USOC;
- 5: discapacitat;
- 6: familia nombrosa;
- 7: familia monoparental;
- 8: violencia de genere;
- 11-99: promocions temporals;
- grup: taula `descomptes_grup`;
- codis promocionals: logica propia.

### 11.1. Codis promocionals i promocions temporals

Del xat antic es recupera aquesta distincio:

- `descomptes.TIPUS` de l'11 al 99 s'utilitza per promocions temporals;
- el codi promocional introduït pel client te logica propia i pot venir de la taula `promocions`;
- el SIF no valida si el codi es vigent, caducat o usat: aixo ho fa ecommerce/intranet abans de facturar;
- el SIF conserva el resultat fiscal final dins `factura_linia`.

Consultes/criteris recuperats:

```text
cnsSiTePromocioDispo:
  promocions.CODI_DESCOMPTE LIKE ?
  DNI = ?
  USED = 0
  DATAI <= CURRENT_TIME
  DATAF >= CURRENT_TIME

updDataFPromocio:
  UPDATE promocions SET DATAF = CURRENT_TIME
  WHERE CODI_DESCOMPTE LIKE ?
    AND DNI = ?
    AND USED = 0
    AND DATAI <= CURRENT_TIME
    AND DATAF >= CURRENT_TIME
```

Exemple recuperat de promocio/codi:

```text
MACABODETITULAR#...
Codi personal i intransferible
Un sol us
Valid fins a una data concreta
```

Quan es crea la factura, la linia ha de guardar:

- `desc_origen = CODI_PROMO` o `PROMOCIO_TEMPORAL`;
- `desc_codi_promo`, si existeix;
- `desc_id` o referencia operativa, si existeix;
- percentatge o import fix aplicat;
- import descomptat;
- text visible generic, per exemple `Descompte promocional aplicat`;
- total final.

Si el codi caduca o queda marcat com usat despres d'emetre la factura, la factura no canvia. Si el codi es detecta invalid abans de pagar/facturar, s'ha de recalcular l'import sense el codi o demanar revisio abans d'emetre.

Text visible recomanat:

```text
Descompte aplicat 25%
Descompte promocional aplicat
```

Evitar exposar dades sensibles al PDF.

## 12. Packs

Regla futura:

```text
1 pagament -> 1 factura -> N linies
```

Normalment:

- 2 cursos;
- 2 inscripcions;
- mateix `IDPAG`;
- una linia per curs;
- descompte del pack aplicat al segon curs.

Excepcionalment, si hi ha pagaments separats des de la intranet, es pot generar una factura per cada pagament.

## 13. Grups

Regla futura:

```text
1 pagament -> 1 factura -> N linies
```

Una linia per participant.

Aixo es necessari per justificacions tipus FUNDAE/Tripartita.

El receptor pot ser:

- empresa/escola;
- responsable particular.

La factura de grup no ha de ser visible a cada alumne a la intranet d'alumne.

## 14. Regalar un Curs

La factura va al comprador.

El beneficiari/destinatari encara no genera inscripcio definitiva en el moment de compra.

Flux:

```text
comprador compra regal
        |
        v
factura al comprador
        |
        v
es genera codi regal
        |
        v
el destinatari el bescanvia
        |
        v
es crea inscripcio sense factura nova
```

## 15. USOC

Cas confirmat:

- alumne paga la seva part;
- factura a l'alumne per la seva part;
- USOC paga la diferencia;
- factura a USOC per la diferencia.

S'han de generar dues factures si hi ha dos pagadors/receptors reals.

## 16. Canvi de Curs

El canvi de curs ha de ser un event auditable.

No s'ha de modificar directament `A_PAGAR` sense deixar rastre.

Flux:

```text
admin tria nou curs/edicio
        |
        v
sistema recalcula preu, descompte i despeses
        |
        v
admin pot ajustar camps permesos
        |
        v
previsualitzacio fiscal
        |
        v
confirmacio
        |
        v
registre a canvi_curs
        |
        v
accio fiscal, si cal
```

Accions fiscals possibles:

- sense factura emesa: actualitzar pendent;
- factura emesa i mateix import: rectificativa per concepte, si cal;
- import superior: factura/diferencia pendent de pagament;
- import inferior: devolucio o saldo;
- despeses de gestio: linia separada;
- descompte excepcional: descompte manual amb motiu intern.

## 17. Baixes

La baixa es un event sobre la inscripcio.

No ha de generar rectificativa automaticament.

Flux:

```text
Adam/Pablo marca baixa
        |
        v
es guarda baixa_inscripcio
        |
        v
es pregunta decisio al client
        |
        +--> retorn diners
        +--> saldo a favor
        +--> no retornar
        |
        v
accio economica/fiscal posterior
```

Casos:

- baixa sense pagament: no factura, no rectificativa;
- baixa amb pagament i devolucio: refund + rectificativa;
- baixa amb saldo: credit balance + rectificativa si cal;
- baixa sense devolucio: factura original queda igual.

## 18. Saldos a Favor

Els saldos per baixa poden usar-se sempre.

No caduquen formalment, pero secretaria pot revisar saldos molt antics, especialment mes de 5 anys.

Cal taula `credit_balance`.

Quan s'usa saldo:

```text
payment_transaction.TIPUS_MOVIMENT = COMPENSATION
```

## 19. Morosos i Reclamacions

Morositat no es baixa.

Flux:

```text
alumne fa curs
no paga tot
es reclama
si no paga, passa a M
factura continua existint
no hi ha rectificativa nomes per morositat
```

Cal registrar reclamacions en taula propia:

- import total;
- import pagat;
- import pendent;
- fase de reclamacio;
- data;
- missatge;
- estat.

## 20. Consulta - Modifica Alumne

Aquest apartat es pantalla de consulta i inici d'accions.

No ha de ser el lloc on es modifiquen factures emeses.

### Dades personals

Editar dades personals nomes afecta inscripcions pendents o dades vives.

No modifica factures ja emeses.

Cal avis:

```text
Aquest canvi no modificara factures ja emeses.
Per canviar dades fiscals d'una factura cal generar rectificativa.
```

### Llistat d'inscripcions

Mantenir `INSC_CURS` com estat academic/administratiu:

- 0: no matriculat;
- 1: matriculat;
- X: baixa;
- C: canvi de curs;
- M: moros.

L'estat economic es calcula a partir de pagaments.

L'estat factura/AEAT ve del SIF.

### Modal de dades del curs

Afegir:

- factura;
- receptor;
- estat cobrament;
- estat AEAT;
- PDF disponible;
- URL pagament activa/inactiva;
- motiu d'inactivacio.

### Canvi de curs

Cal previsualitzacio fiscal abans de confirmar.

### Baixa

Marca baixa i crea event.

No rectifica automaticament.

### Veure factura

Nomes lectura:

- numero;
- receptor fiscal;
- linies;
- total;
- estat VERI*FACTU;
- PDF immutable;
- rectificatives vinculades.

Factures antigues:

```text
Factura historica no VERI*FACTU
```

## 21. PDFs i QR

Actualment el PDF es regenera llegint dades vives.

Nou criteri:

```text
PDF generat en emissio
PDF guardat al servidor
PDF no es modifica
hash del fitxer guardat
descarga sempre serveix el fitxer immutable
```

Els PDFs estaran en espai no public de `pay.prisma.cat` i la intranet els servira amb permisos.

La intranet alumne nomes pot veure factures seves, no factures d'empresa/grup.

## 22. Correus

Pendent de documentar en detall.

Casos coneguts:

- pagament acceptat;
- pagament denegat;
- factura abans de pagar;
- factura manual;
- devolucio;
- rectificativa;
- canvi de curs;
- baixa;
- reclamacions;
- morositat.

Decisio pendent:

- adjuntar PDF;
- o enviar enllac segur.

## 23. Permisos i Bloqueig

Objectiu:

```text
cap factura emesa pot modificar-se silenciosament
```

Mesures:

- bloqueig a nivell d'aplicacio;
- permisos MySQL sense UPDATE/DELETE sobre factures emeses per usuaris no SIF;
- la BD fiscal nomes gestionada pel SIF;
- rectificatives per corregir;
- logs d'events.

## 24. Migracio i Subdomini

El pagament i callbacks Redsys s'han de moure a `pay.prisma.cat`.

També:

- nous endpoints SIF;
- nova gestio de Redsys;
- nova gestio de PDFs;
- revisio d'analisi fitxer TPV;
- adaptacio de pantalles intranet.

## 25. Punts Pendents

- Certificat digital:
  - el certificat digital es de l'entitat;
  - falta disponibilitat/configuracio tecnica per connectar amb AEAT.
- Criteris fiscals interns a documentar sense assessor fiscal disponible:
  - rectificatives per canvi de NIF/rao;
  - factura abans de cobrar no pagada;
  - saldos/compensacions;
  - factura complementaria vs rectificativa en canvis de curs.
- Mencio d'exempcio IVA ja utilitzada:
  - `Factura exempta d'IVA d'acord amb l'article 20.1.9 de la Llei 37/1992, de 28 de desembre, de l'Impost sobre el Valor Afegit (formacio i reciclatge professionals realitzats per entitats privades autoritzades per a l'exercici de les activitats).`
- Documentar "Passar pagaments".
- Documentar "Consulta - Edita - Anula factura".
- Documentar "Generar factura abans de pagar".
- Documentar "Analitzar fitxer TPV".
- Documentar correus i plantilles.
- Payloads base ja definits a `../03-canvis-pendents/04-fluxos-facturacio.md`; falta convertir-los en payloads finals de codi i prova per:
  - curs normal;
  - pack;
  - grup;
  - regal;
  - USOC;
  - factura manual;
  - factura abans de cobrar;
  - rectificativa.
