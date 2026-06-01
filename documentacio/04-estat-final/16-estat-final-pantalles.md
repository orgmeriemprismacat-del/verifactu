# 16 - Estat final de pantalles

> Document d'estat final de pantalles d'intranet, ecommerce i consultes. Incloura captures quan el sistema estigui implementat.

## 1. Objectiu

Documentar com han de quedar les pantalles finals, no nomes quins canvis cal fer.

Aquest document no substitueix `07-pantalles-intranet.md`.

- `../03-canvis-pendents/07-pantalles-intranet.md`: canvis necessaris sobre pantalles actuals.
- `16-estat-final-pantalles.md`: estat final esperat de les pantalles quan el projecte estigui acabat.

Cada pantalla haura d'incloure:

- objectiu;
- usuaris autoritzats;
- dades visibles;
- accions disponibles;
- validacions;
- impacte fiscal;
- captures finals.

## 2. Ecommerce - inscripcio i compra

Pantalles finals:

- seleccio curs/producte;
- dades alumne;
- dades facturacio;
- resum de compra;
- pagament;
- confirmacio.

Regla:

```text
Abans de pagar, l'usuari confirma dades de facturacio.
Despres de pagar, el SIF emet factura si no existia factura previa.
```

## 3. Ecommerce - tipus de pagament

Tipus d'URL final:

- individu;
- pack;
- grup;
- regal;
- empresa/factura abans de cobrament;
- USOC;
- diferencia per canvi de curs;
- morositat/reclamacio.

## 3.1. Panell intern del SIF

El panell oficial del SIF viura a:

```text
pay.prisma.cat/sif
```

Pantalles finals:

- dashboard;
- factures;
- registres AEAT;
- incidencies;
- documents;
- versions;
- exportacions;
- configuracio.

La intranet principal tindra enllacos, indicador visual de pendents i resum d'incidencies, pero no substituira aquestes pantalles.

El detall funcional de cada pantalla del panell SIF queda documentat a:

```text
04-estat-final/25-panell-sif-pay-prisma.md
```

## 4. Intranet - Consulta / Modifica alumne

Objectiu:

- consultar una fitxa completa d'alumne;
- veure inscripcions pendents i acabades;
- entendre l'estat academic, economic i fiscal de cada inscripcio;
- iniciar accions controlades sense editar directament factures emeses.

Base tecnica actual:

- pantalla servida des de `Intranet.php`;
- ruta actual: `/alumnes/mostrar-alumne/`;
- les accions s'executen principalment amb crides JS/AJAX a metodes de la classe `Intranet`;
- l'estat final pot mantenir aquest patró, pero les accions fiscals hauran de passar pel SIF.

Usuaris autoritzats:

- Meriem;
- Adam;
- Pablo;
- Isa per consulta i suport de Secretaria quan correspongui, sense accions fiscals critiques.

Pantalla final, blocs visibles:

- dades personals;
- inscripcions pendents;
- inscripcions acabades;
- observacions;
- estat academic;
- estat pagament calculat;
- estat factura;
- estat AEAT;
- accessos a accions;
- avisos o indicadors quan hi hagi incidencia fiscal relacionada.

Bloc de dades personals:

- nom;
- cognoms;
- DNI/NIF;
- correu;
- telefon;
- adreca;
- codi postal;
- poblacio;
- observacions generals.

Regla:

```text
Editar dades personals no modifica factures ja emeses.
Si cal canviar dades fiscals d'una factura, s'ha d'iniciar flux de rectificativa.
```

Decisions de comportament final:

- la fitxa d'alumne mostra dades academiques, economiques i fiscals de manera conjunta, pero separant clarament responsabilitats;
- la inscripcio continua sent la base academica i administrativa;
- el SIF es la base fiscal de factures, pagaments, rectificatives, PDF, QR i AEAT;
- `FACTURA_RELACIONADA` es mostra com a referencia de compatibilitat quan calgui;
- el UUID de factura SIF ha de ser la referencia fiscal principal;
- els estats visibles han de separar `INSC_CURS`, cobrament, factura i AEAT;
- les factures historiques han de quedar marcades com a no VERI*FACTU;
- les factures d'empresa/grup han de controlar visibilitat per evitar mostrar dades d'altres participants;
- les accions de canvi de curs, baixa, pagament, factura i certificat han d'estar disponibles o bloquejades segons estat i permisos.

Taules d'inscripcions:

- pendents de comencar;
- acabades;
- baixes o canvis quan es decideixi mostrar-les en filtres.

Columnes finals recomanades:

- tipus;
- any;
- mes;
- curs;
- grup;
- estat `INSC_CURS`;
- import a pagar;
- import pagat;
- pendent;
- factura associada;
- receptor fiscal;
- estat cobrament;
- estat AEAT;
- PDF/QR;
- URL de pagament;
- accions.

Accions:

- veure dades;
- veure dades de pagament;
- canvi de curs;
- baixa;
- veure factura;
- certificat.

Regles de les accions:

- les icones no disponibles es mostren desactivades o amb baixa opacitat;
- veure factura es nomes lectura;
- canvi de curs ha d'obrir previsualitzacio fiscal abans de confirmar;
- baixa marca primer l'event administratiu i no genera rectificativa automatica;
- si la factura es d'empresa/grup, el sistema ha de controlar si es visible o no per aquell alumne;
- si la factura es historica, s'ha de mostrar com a `Factura historica no VERI*FACTU`;
- si la factura es nova, ha de mostrar estats SIF i AEAT.

Impacte fiscal:

- la pantalla no ha de crear ni modificar factures directament;
- pot iniciar fluxos que acabin cridant `issueInvoice()`, `registerPayment()` o rectificativa;
- qualsevol accio amb impacte fiscal ha de quedar registrada amb usuari, data, motiu i referencia d'inscripcio/factura.
- les dades editables actuals de pagament han de quedar separades entre dades operatives de la inscripcio i moviments fiscals/pagaments del SIF.

## 5. Intranet - Dades pagament

Regla de redisseny obligatoria:

```text
Dades pagament deixa de ser un editor directe de camps fiscals.
Passa a ser una vista d'estat i un punt d'entrada a fluxos controlats.
```

Ha de mostrar:

- A pagar;
- pagat;
- pendent;
- pagaments associats;
- factura associada;
- receptor;
- estat cobrament;
- estat AEAT;
- PDF disponible;
- URL activa/inactiva;
- motiu d'inactivacio;
- opcio de marcar factura electronica si correspon.

Els camps `A_PAGAR`, `PAGAMENT`, `DATA PAG`, `IDPAG`, `FACTURA_RELACIONADA`, `FRACCIONAT` i `FRACCIO` no s'han de modificar lliurement des d'aquesta pantalla quan tinguin impacte fiscal.

Excepcio controlada per `A_PAGAR`:

- es pot modificar quan hi hagi un cas justificat;
- el modal ha de marcar l'ajust com a manual;
- s'ha d'informar el motiu;
- s'ha de mostrar l'impacte sobre pendent, pagat, factura i possible rectificativa;
- si ja hi ha factura emesa, el canvi no pot quedar nomes com a update de `inscripcions`, sino que ha d'obrir o deixar preparat el flux fiscal corresponent.

Fonts de dades finals:

- `inscripcions` mantindra dades operatives com import previst, estat academic, reclamacions i observacions;
- `payment_transaction` i `payment_allocation` seran la font del cobrament fiscal;
- `factura` i `factura_linia` seran la font de l'import facturat;
- `factura_registres` i `fiscal_queue` seran la font de l'estat fiscal/AEAT;
- `factura_documents` sera la font del PDF/QR.

Accions disponibles:

- veure detall de pagaments registrats;
- obrir factura associada;
- copiar o obrir URL de pagament;
- iniciar registre de pagament si el rol ho permet;
- iniciar compensacio/saldo si el rol ho permet;
- obrir canvi de curs;
- obrir baixa;
- obrir reclamacio/morositat;
- marcar factura electronica si el rol ho permet.

Accions prohibides:

- canviar manualment la factura associada sense relacio SIF;
- marcar un cobrament fiscal nomes canviant `PAGAMENT`;
- modificar import facturat sense rectificativa o flux justificat;
- canviar `IDPAG` com a solucio manual d'una incidencia fiscal.

Ha de diferenciar:

- pagament individual;
- pagament de pack;
- pagament de grup;
- regal;
- factura d'empresa/responsable abans de cobrament;
- USOC;
- diferencia de canvi de curs;
- morositat o reclamacio.

Regles finals:

- si hi ha factura real previa, el cobrament posterior nomes pot registrar-se contra aquella factura;
- si una factura d'empresa esta pendent, pot tenir URL de pagament propia;
- si l'alumne esta moros, no es bloqueja el pagament pel fet de ser moros;
- si la inscripcio esta coberta per una factura d'empresa/responsable, la URL individual s'ha de desactivar o substituir per la URL correcta;
- si la URL esta inactiva, la pantalla ha de mostrar el motiu.

## 6. Intranet - Veure factura

Pantalla final nomes lectura:

- numero;
- data;
- receptor;
- linies;
- descomptes visibles;
- total;
- estat factura;
- estat AEAT;
- estat cobrament;
- PDF;
- QR;
- rectificatives;
- marca factura electronica.

També ha de mostrar:

- si es `EMESA_ABANS_COBRAMENT`;
- relacio amb `FACTURA_RELACIONADA`;
- UUID de factura o referencia interna SIF;
- document PDF disponible i hash si es mostra en vista tecnica;
- estat de generacio PDF/QR: disponible, pendent, error o no aplicable per factura historica;
- si es factura historica no VERI*FACTU;
- pagaments assignats a la factura.

Accions permeses:

- descarregar PDF;
- obrir QR o veure'l dins el PDF;
- obrir enllac segur de consulta si el receptor hi te permis;
- obrir rectificatives relacionades;
- marcar factura electronica nomes si el rol ho permet.

No ha de permetre:

- editar receptor;
- editar concepte;
- editar imports;
- regenerar PDF sense log;
- anul·lar factura sense flux de rectificativa.

Regles PDF/QR:

- les factures noves del SIF han d'incorporar QR i text associat segons l'especificacio AEAT vigent;
- el PDF visible ha de sortir de `factura_documents`, no de dades vives regenerades sense control;
- si el PDF/QR esta pendent de cua, la pantalla mostra l'estat i no ofereix un document antic o reconstruït;
- si hi ha error de PDF/QR, la pantalla enllaca la incidencia SIF corresponent;
- el QR no substitueix el registre fiscal ni l'enviament AEAT.

## 7. Intranet - Canvi de curs

Ha de tenir:

- dades actuals;
- dades nou curs;
- import nou calculat;
- descompte reaplicat/no aplicable;
- despeses de gestio;
- pagat fins ara;
- diferencia;
- accio fiscal prevista;
- previsualitzacio;
- confirmacio.

Ha de contemplar:

- curs nou amb mateix import;
- curs nou amb import superior;
- curs nou amb import inferior;
- descompte original reaplicable;
- descompte original no reaplicable;
- descompte excepcional justificat;
- despeses de gestio calculades;
- despeses de gestio modificades manualment amb motiu;
- pagament fraccionat;
- factura ja emesa;
- factura pendent de cobrament.

La confirmacio ha de deixar registre a:

- historial de canvi de curs;
- relacio amb inscripcio anterior i nova;
- factura original;
- rectificativa o factura de diferencia si cal;
- pagament, saldo o retorn si cal.

## 8. Intranet - Baixa

Ha de tenir:

- motiu baixa;
- import pagat;
- decisio pendent del client;
- retorn/saldo/no retorn;
- estat economic posterior;
- rectificativa vinculada si cal.

Flux final:

- primer es registra la baixa administrativa;
- despres es decideix economicament si hi ha retorn, saldo o no retorn;
- nomes quan hi ha decisio economica es genera l'impacte fiscal que correspongui;
- la baixa per si sola no ha de modificar una factura emesa.

## 8.1. Intranet - Reclamacions i morositat

Pantalles actuals relacionades:

| URL actual | Funcio |
| --- | --- |
| `/facturacio/primera-reclamacio/` | Primera reclamacio de pagament. |
| `/facturacio/baixes/` | Revisio de baixes de segona setmana. |
| `/facturacio/recordatori-pagament/` | Recordatori de pagament final. |
| `/facturacio/reclamacio-final/` | Reclamacio final. |
| `/facturacio/morosos/` | Control de morositat. |

Flux final:

- abans de començar el curs, ha d'existir un primer pagament o justificacio;
- a la segona setmana, si no hi ha pagament ni justificacio, es pot tramitar baixa administrativa;
- al final del curs, una setmana despres i un mes despres es poden registrar reclamacions;
- si continua pendent, l'estat passa a morositat;
- la morositat no anul·la ni rectifica una factura per si sola.

Pantalla final de reclamacions/morositat:

- mostrar import total, import pagat i import pendent;
- mostrar fase de reclamacio;
- mostrar dates de reclamacio;
- mostrar missatges enviats o pendents;
- mostrar factura associada i estat cobrament SIF;
- permetre registrar nova reclamacio amb usuari, data i motiu;
- permetre obrir `Passar pagaments` o factura associada quan correspongui;
- crear incidencia si hi ha incoherencia entre estat de curs, pagament i factura.

No ha de permetre:

- tocar imports d'una factura emesa;
- donar per resolt un pendent fiscal sense `registerPayment()`, compensacio, devolucio o rectificativa;
- marcar baixa administrativa com si fos rectificativa automatica.

## 9. Intranet - Passar pagaments

URL actual:

```text
https://intranet.prisma.cat/alumnes/pagaments/
```

Base tecnica actual:

- fitxer d'entrada: `alumnes-pagaments.php`;
- JS general: `general.js`;
- JS de pantalla: `alumnes-pagaments.js`;
- CSS especific: `alumnes-pagaments.css`;
- la logica funcional es carrega via JS/AJAX i classe `Intranet.php`.

Pantalla final:

- bloc d'analisi de fitxer TPV;
- cerca per `NIF/NIE`, codi regal i numero de factura;
- selector alumne/grup;
- resultats amb import a pagar, pagat, pendent, fraccio i observacions;
- camp per registrar pagament manual o transferencia;
- data de pagament;
- banc/metode;
- avisos de factura existent, factura abans de cobrament o cobertura per empresa/responsable.
- indicador de darrer fitxer TPV analitzat;
- origen del pagament: TPV, transferencia, compensacio, regal, grup, morositat o manual;
- estat de conciliacio: conciliat, pendent, duplicat, incidencia o revisio manual;
- diferencia calculada entre `A PAGAR`, `PAGAT`, nou `PAGAMENT` i pendent SIF.

Accio final:

- si hi ha factura SIF existent, `registerPayment()`;
- si hi ha factura abans de cobrament, nomes `registerPayment()`;
- si no hi ha factura i la venda s'ha de facturar, `issueInvoice()` + `registerPayment()`;
- si hi ha compensacio o saldo, registrar moviment identificat i assignacio.
- si ve d'un fitxer TPV, conciliar primer amb transaccio existent abans de crear nous registres;
- si es manual, registrar usuari, data, metode, import i observacio.

Despres de registrar pagament:

- mostrar factura vinculada;
- donar opcio de veure PDF/QR;
- actualitzar estat cobrament;
- crear incidencia si hi ha duplicat, import inconsistent o factura no localitzada.
- no enviar correu de factura definitiva fins que el SIF hagi generat la factura i el document quan el cas requereixi emissio nova.

## 9.1. Intranet - Entitats i responsables

URL actual:

```text
https://intranet.prisma.cat/alumnes/genera-entitat/
```

Base tecnica actual:

- fitxer d'entrada: `alumnes-genera-entitat.php`;
- JS general: `general.js`;
- JS de pantalla: `alumnes-genera-entitat.js`;
- CSS especific: `alumnes-genera-entitat.css`;
- la logica funcional es carrega via JS/AJAX i classe `Intranet.php`.

Pantalla final:

- crear entitat;
- editar entitat;
- consultar entitats existents;
- guardar dades fiscals: CIF, rao, adreca, codi postal i poblacio;
- guardar dades de contacte/responsable: nom, cognoms i correu;
- detectar possibles duplicats per CIF;
- mostrar avis si l'entitat te factures emeses;
- historial de canvis quan afecti dades fiscals utilitzades per facturacio.

Regla SIF:

- l'entitat es origen de dades, pero la factura ha de guardar snapshot fiscal;
- editar l'entitat no modifica factures ja emeses;
- si cal corregir receptor d'una factura emesa, s'ha d'obrir rectificativa/substitucio;
- el responsable d'entitat pot ser destinatari de correus o enllacos, pero no substitueix necessariament el receptor fiscal.

## 10. Intranet - Generar factura abans de cobrar

URL actual:

```text
https://intranet.prisma.cat/alumnes/genera-factura-abans-pagar/
```

Pantalla final:

- cerca inscripcions per `NIF/NIE`;
- seleccio d'una o diverses inscripcions del mateix curs i edicio;
- seleccio de receptor fiscal/entitat;
- previsualitzacio de concepte visible;
- previsualitzacio de linies fiscals;
- import total;
- observacions;
- confirmacio d'emissio.

Regla final:

- factura real abans de cobrar = `EMESA_ABANS_COBRAMENT = 1`;
- no implica `E_FACT = 1`;
- pot generar URL especifica de pagament d'empresa/responsable.

Resultat final:

- factura SIF amb numero visible;
- relacions a `fact_rels`;
- estat AEAT;
- estat cobrament pendent;
- PDF/QR immutable;
- si posteriorment es paga, el flux sera `registerPayment()`.

## 10.1. Intranet - Consulta - Edita - Anula factura

URL actual:

```text
https://intranet.prisma.cat/alumnes/factura/
```

Base tecnica actual:

- fitxer d'entrada: `alumnes-factura.php`;
- JS general: `general.js`;
- JS de pantalla: `alumnes-factura.js`;
- CSS especific: `alumnes-factura.css`;
- el `<title>` actual del fitxer diu `Consulta / Anul·la factura`, pero l'apartat funcional es `Consulta - Edita - Anul·la factura`;
- la logica funcional es carrega via JS/AJAX i classe `Intranet.php`.

Pantalla final:

- cerca per `NIF/NIE`, email, factura relacionada i numero de factura;
- resultats amb factura relacionada, any, numero visible, rao, CIF, curs i accions;
- vista de dades d'inscripcio i dades de factura;
- PDF/QR immutable;
- historial de rectificatives;
- estat VERI*FACTU o historic no VERI*FACTU;
- estat AEAT;
- estat cobrament.
- diferenciacio visual entre factura ordinaria, rectificativa, historica i SIF;
- visualitzacio d'import retornat, saldo o devolucio quan hi hagi anul·lacio o rectificativa.

Accions finals:

- veure informacio;
- descarregar PDF;
- iniciar rectificativa;
- registrar devolucio o saldo quan correspongui;
- marcar/desmarcar `E_FACT` amb permisos Meriem, Adam i Pablo;
- veure log d'accions.
- consultar factura original i rectificatives vinculades;
- consultar pagaments i devolucions vinculats.

No permetre:

- editar factura emesa directament;
- canviar receptor, concepte o import sense rectificativa;
- anul·lar sense motiu i sense factura rectificativa quan correspongui.
- regenerar PDF de factura nova a partir de dades vives.

## 11. Intranet personalitzada de l'alumne

L'alumne no es un rol de la intranet principal.

Te una intranet personalitzada separada, amb acces restringit a les seves dades.

Pantalla final:

- llistat de factures visibles;
- descarrega PDF;
- QR dins PDF;
- estat de pagament si correspon;
- no mostrar factures de grup/empresa no visibles.

Regles de visibilitat:

- l'alumne pot veure factures on sigui receptor fiscal o estigui autoritzat segons relacio documentada;
- una factura d'empresa, grup o responsable no es visible automaticament a tots els participants;
- si la factura existeix pero el PDF/QR esta pendent, es mostra estat pendent o enllac segur, no un PDF regenerat.

## 11.0. Empresa/responsable

L'empresa o responsable no te acces a la intranet principal.

Si ha de consultar factures, s'haura de fer per:

- correu;
- enllac segur;
- gestio interna des del SIF/intranet;
- o un futur espai especific, si es decideix crear-lo.

Regles:

- l'enllac segur ha de validar token/permis i consultar el SIF;
- pot permetre veure factura, estat de cobrament, PDF/QR i URL de pagament d'empresa/responsable si encara esta pendent;
- no ha de redirigir cap a la URL individual d'un alumne quan la factura pendent es d'empresa/responsable.

## 11.1. Intranet principal - acces VERI*FACTU

La intranet principal tindra un apartat:

```text
VERI*FACTU
```

Aquest apartat ha de mostrar:

- indicador visual de pendents al titol o menu;
- boto per obrir el panell SIF a `pay.prisma.cat/sif`;
- resum de les incidencies pendents;
- acces als documents del SIF;
- acces a les exportacions;
- estat general de cua AEAT, si cal.

Les dades han de venir del SIF.

Nomenclatura visual:

- `indicador`: marca visual al menu o titol.
- `avis`: text puntual a la pantalla.
- `notificacio`: avis guardat i recuperable.
- `incidencia SIF`: registre oficial que es resol al panell SIF.

## 12. Captures

Afegir captures finals quan cada pantalla estigui implementada.
