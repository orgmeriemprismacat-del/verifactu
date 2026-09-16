# 22 - Manual operatiu intern

> Manual per a l'us diari del sistema per part de gestio, secretaria, Adam, Pablo i administracio.

## 1. Objectiu

Explicar que s'ha de fer en cada cas real sense entrar en codi.

## 2. Procediments pendents de completar

- curs individual pagat per Redsys;
- pagament per transferencia;
- pagament per compensacio;
- factura abans de cobrar;
- pack;
- grup;
- regal;
- USOC;
- canvi de curs;
- baixa;
- devolucio;
- saldo;
- morositat;
- rectificativa;
- factura manual;
- analitzar fitxer TPV i passar pagaments a `/alumnes/pagaments/`;
- consultar, editar accions fiscals i anul·lar factura a `/alumnes/factura/`;
- error AEAT;
- PDF no generat;
- factura electronica.
- consulta panell SIF `pay.prisma.cat/sif`;
- gestio d'incidencies SIF;
- consulta de declaracio responsable i versio activa.

## 3. Format de cada procediment

Cada procediment haura d'indicar:

- qui ho pot fer;
- pantalla;
- passos;
- validacions;
- efecte fiscal;
- correu enviat;
- notificacions;
- que no s'ha de fer mai.

## 4. Regles operatives generals

### 4.1. Abans i despres d'emetre factura

Abans d'emetre factura:

- es poden corregir dades de venda, curs, import, descompte o receptor fiscal dins els fluxos previstos;
- cal validar dades fiscals abans de crear factura real;
- si el canvi afecta preu o concepte, cal recalcular i previsualitzar abans de confirmar.

Despres d'emetre factura:

- no es modifica receptor, concepte o import directament;
- el canvi va per rectificativa, devolucio, compensacio, saldo o event controlat;
- el PDF de factura nova s'ha de consultar com a document immutable del SIF.

### 4.2. Permisos

Regla practica:

| Accio | Qui la pot fer |
| --- | --- |
| Consulta d'alumne | Meriem, Adam, Pablo, Isa si dona suport. |
| Passar pagaments | Meriem, Adam, Pablo. |
| Consulta de factures | Meriem, Adam, Pablo. |
| Marcar `E_FACT` | Meriem, Adam, Pablo. |
| Rectificativa operativa | Meriem, Adam, Pablo. |
| Configuracio SIF | Meriem. |
| Exportacions fiscals | Meriem i Adam, segons cas. |
| Suport Moodle/cursos | Isa quan calgui, sense accions fiscals critiques. |

El fet que una pantalla mostri un boto no es suficient. L'accio ha de validar permisos al servidor.

### 4.3. Consulta - Modifica alumne

Us:

- consultar estat academic, economic i fiscal;
- iniciar canvi de curs, baixa, pagament o consulta de factura;
- veure avisos quan hi ha incidencia fiscal relacionada.

No fer:

- editar imports o dades de factura ja emesa;
- canviar `FACTURA_RELACIONADA` com a solucio manual;
- considerar `INSC_CURS` com a estat fiscal.

### 4.3.1. Com usar la pantalla

Procediment orientatiu:

1. Cercar l'alumne i revisar dades personals, inscripcions pendents, inscripcions acabades i observacions.
2. Mirar l'estat de cada inscripcio separant tres coses: estat academic, estat de cobrament i estat fiscal.
3. Si cal veure dades del curs, obrir la icona d'informacio.
4. Si cal revisar imports, pagaments o URL, obrir dades de pagament, pero no corregir camps fiscals a ma.
5. Si cal canvi de curs, obrir el flux de canvi i revisar import nou, descompte, despeses de gestio, diferencia i accio fiscal prevista.
6. Si cal baixa, registrar primer la baixa administrativa i esperar decisio economica: retorn, saldo o no retorn.
7. Si cal veure factura, obrir la icona de factura nomes en lectura i descarrega.
8. Si cal corregir una factura, anar a `Consulta - Edita - Anula factura` o al flux SIF corresponent.

Recordatoris:

- editar dades personals no canvia factures ja emeses;
- morositat no impedeix pagar;
- una factura d'empresa/responsable pot tenir URL propia;
- una inscripcio coberta per factura d'empresa/responsable no ha de mantenir URL individual que pugui duplicar el cobrament;
- les despeses de gestio de canvi de curs han de quedar justificades i, si generen factura nova o diferencia, conve que apareguin com a linia explicita.

### 4.3.2. Passar pagaments i analitzar TPV

Us:

- revisar cobraments que han entrat pel TPV o pel banc;
- passar pagaments manuals, transferencies, regals, grups o fraccions;
- detectar pagaments sense factura, duplicats o pendents de conciliacio.

Procediment orientatiu:

1. Si hi ha fitxer TPV, analitzar-lo abans de passar pagaments manuals relacionats.
2. Revisar si el resultat es correcte, amb incidencies o amb format rebutjat.
3. Obrir les incidencies amb l'enllac a alumne o factura quan el sistema les proposi.
4. Per passar un pagament manual, cercar nomes per un criteri: `NIF/NIE`, `CODI REGAL` o `NUM FACTURA`.
5. Verificar si hi ha factura SIF existent, factura abans de cobrament, factura d'empresa/responsable o pendent sense factura.
6. Informar import, data, banc/metode i observacio quan sigui necessari.
7. Confirmar nomes si el pendent, l'origen i la factura associada quadren.
8. Despres de confirmar, comprovar que el pagament consta com a registrat i que la factura o incidencia vinculada es pot consultar.

No fer:

- no passar el mateix pagament dues vegades perque el TPV o el navegador hagi fallat;
- no corregir imports de factura emesa a ma;
- no convertir una devolucio TPV en simple import negatiu sense revisio;
- no usar una URL individual si el cobrament correspon a empresa o responsable;
- no considerar que el text "actualitzara la factura" del sistema antic sigui valid per al SIF final.

Si hi ha dubte, crear o deixar incidencia SIF abans de tocar pagaments.

Comprovacio abans de confirmar:

- factura existent o no existent;
- si es factura abans de cobrament;
- receptor correcte: alumne, empresa, responsable, grup, regal o USOC;
- import pendent recalculat pel SIF;
- data i metode/banc;
- referencia TPV o bancaria si existeix;
- avis de duplicat o incidencia.

Resultat correcte:

- si ja hi havia factura, ha quedat un pagament registrat contra aquella factura;
- si no hi havia factura i tocava facturar, ha quedat factura i pagament dins la mateixa operacio;
- si hi havia dubte, ha quedat incidencia o revisio manual, no un update silencios.

### 4.3.3. Generar factura abans de pagar

Us:

- emetre una factura real abans de cobrar;
- agrupar diverses inscripcions del mateix curs i edicio en una factura d'empresa o responsable;
- generar una factura pendent de cobrament que despres es cobrara per URL de factura o per `Passar pagaments`.

Procediment orientatiu:

1. Cercar l'alumne o inscripcions pel `NIF/NIE`.
2. Afegir nomes inscripcions que hagin d'anar a la mateixa factura.
3. Revisar que totes siguin del mateix curs i de la mateixa edicio.
4. Seleccionar l'entitat o receptor fiscal correcte.
5. Revisar rao social, CIF, adreca, codi postal i poblacio abans d'emetre.
6. Revisar concepte visible, linies/import total i observacions.
7. Confirmar l'emissio sabent que la factura ja sera real encara que estigui pendent de pagament.
8. Despres d'emetre, comprovar numero de factura, estat pendent de cobrament i PDF/QR.
9. Si el pagament arriba despres, registrar-lo contra aquesta factura, no crear-ne una altra.

No fer:

- no usar aquest flux com a proforma;
- no marcar `E_FACT` automaticament pel fet d'emetre abans de cobrar;
- no repetir l'emissio si el navegador queda carregant;
- no deixar URL individual activa si el cobrament correspon a empresa/responsable;
- no corregir receptor, concepte o import d'aquesta factura editant dades vives.

Si les dades fiscals del receptor son dubtoses, cal corregir l'entitat abans d'emetre.

Comprovacio abans de confirmar:

- totes les inscripcions son del mateix curs i edicio;
- cap inscripcio ja te factura incompatible;
- el receptor fiscal esta complet i triat per entitat/responsable correcte;
- el concepte visible i les linies fiscals coincideixen;
- queda clar que es factura real abans de cobrar i no proforma;
- `E_FACT` no es marca automaticament.

Resultat correcte:

- factura SIF amb `EMESA_ABANS_COBRAMENT = 1`;
- estat de cobrament pendent;
- PDF/QR disponible o incidencia documental;
- URL individual desactivada o substituida si el cobrament correspon a empresa/responsable;
- pagament posterior sempre contra aquesta factura.

### 4.3.4. Consulta - Edita - Anula factura

Us:

- consultar factures ja emeses;
- veure factura original, rectificatives, pagaments, devolucions, saldo i PDF/QR;
- iniciar rectificativa o devolucio quan una factura no es pot deixar tal com esta;
- marcar o desmarcar `E_FACT` quan correspongui.

Procediment orientatiu:

1. Cercar la factura per `NIF/NIE`, email, factura relacionada o numero de factura.
2. Obrir informacio i revisar si la factura es SIF o historica no VERI*FACTU.
3. Si nomes cal consulta, descarregar PDF/QR immutable o revisar historial.
4. Si cal corregir receptor, CIF, adreca, concepte o import, iniciar rectificativa amb motiu.
5. Si cal retornar diners, decidir devolucio, saldo o compensacio i vincular-ho a factura/pagament.
6. Si la factura esta vinculada a diverses inscripcions, revisar assignacions abans de confirmar.
7. Si cal `E_FACT`, fer-ho amb l'accio especifica i deixar motiu intern.

No fer:

- editar directament una factura SIF emesa;
- canviar `PAGAMENT`, `DATA PAG`, receptor, concepte o import com a correccio fiscal manual;
- usar observacions lliures com a substitut de motiu de rectificativa;
- regenerar PDF des de dades vives.

Comprovacio abans de confirmar una accio fiscal:

- factura original identificada;
- estat SIF o historica no VERI*FACTU;
- motiu escrit i tipificat;
- import afectat i assignacions si hi ha diverses inscripcions;
- decisio economica clara: devolucio, saldo, compensacio o no retorn;
- document o rectificativa que es generara;
- usuari amb permis servidor.

Resultat correcte:

- la factura original queda conservada;
- la rectificativa, devolucio, saldo o marca `E_FACT` queda auditada;
- el PDF/QR surt de document immutable;
- l'historial mostra usuari, data, motiu i accio.

### 4.4. Baixes

Sequencia:

1. Registrar baixa administrativa.
2. Esperar decisio economica del client: retorn, saldo o no retorn.
3. Quan hi ha decisio, tramitar impacte fiscal si correspon.

Regla:

```text
Baixa administrativa no genera rectificativa automatica.
```

### 4.5. Reclamacions i morositat

Sequencia operativa:

1. Abans de començar curs: comprovar primer pagament o justificacio.
2. Segona setmana: si no hi ha pagament ni justificacio, revisar baixa.
3. Final de curs: reclamar pendent.
4. Una setmana despres: nova reclamacio.
5. Un mes despres: nova reclamacio.
6. Despres: marcar morositat i continuar seguiment.

Regla:

```text
Morositat no es baixa i no rectifica factura per si sola.
```

Cada reclamacio ha de conservar import total, pagat, pendent, fase, data, missatge i estat.

### 4.6. Consulta externa d'alumne, empresa o responsable

Regla general:

```text
L'alumne nomes veu factures propies o autoritzades.
L'empresa/responsable veu factures on es receptor fiscal o contacte autoritzat.
La factura de grup/empresa no es mostra completa als participants.
```

Procediment orientatiu:

1. Identificar si la factura es individual, d'empresa/responsable o de grup.
2. Comprovar receptor fiscal i relacio autoritzada abans d'enviar PDF/QR.
3. Si es factura individual de l'alumne, permetre consulta a la intranet personalitzada.
4. Si es factura d'empresa/responsable, enviar correu o enllac segur al responsable autoritzat.
5. Si el PDF/QR esta pendent, mostrar estat pendent o incidencia SIF, no regenerar document.
6. Si la factura esta pendent de cobrament, enviar URL de pagament d'empresa/responsable quan correspongui.

No fer:

- enviar factura completa d'empresa/grup a cada participant;
- exposar rutes internes de `factura_documents`;
- redirigir una factura d'empresa pendent a URL individual d'alumne;
- permetre accions fiscals des d'un enllac de consulta.

Avisos habituals:

- alumne cobert per empresa/responsable: informar cobertura, no enviar factura completa;
- empresa/responsable amb factura pendent: enviar URL de factura d'empresa/responsable;
- PDF/QR pendent: informar estat o incidencia, no regenerar document;
- token caducat o invalid: no mostrar dades fiscals.

### 4.7. Apartat VERI*FACTU de la intranet

Serveix per:

- veure indicador visual de pendents;
- veure resum d'incidencies;
- obrir el panell SIF;
- obrir documents i exportacions.

No serveix per:

- resoldre oficialment incidencies;
- editar factures;
- canviar configuracio SIF.

La resolucio oficial viu a `pay.prisma.cat/sif`.

Interpretacio dels avisos:

- `indicador`: hi ha pendents o avisos; cal obrir el resum;
- `avis`: missatge puntual de pantalla;
- `notificacio`: avis intern guardat;
- `incidencia SIF`: problema oficial que s'ha de resoldre al panell SIF.

Si el SIF no respon, no s'ha d'assumir que no hi ha pendents. Cal veure l'hora de darrera sincronitzacio valida i tornar-ho a provar o revisar el panell.

### 4.8. Redsys curs normal

Regla general:

```text
Redsys confirma cobrament, pero el SIF decideix factura i pagament fiscal.
```

Procediment esperat:

1. Redsys envia callback al domini configurat.
2. El sistema valida signatura, resposta, ordre i import signats.
3. El sistema registra la notificacio i comprova duplicats per `DS_ORDER`.
4. Es carrega la inscripcio per `IDPAG` i el snapshot fiscal confirmat.
5. Si no hi ha factura previa real, el SIF emet factura ordinaria.
6. Si ja hi ha factura abans de cobrament, el SIF registra cobrament contra la factura existent.
7. Despres de resposta SIF, es sincronitza la inscripcio i s'envien correus.

No fer:

- interpretar el correu intern de "pagament automatic" com a prova fiscal;
- crear factura local directament des del callback;
- tornar a processar un mateix `DS_ORDER`;
- confiar en import o ordre de URL si no coincideixen amb dades signades Redsys;
- corregir manualment `PAGAMENT` o `FACTURA_RELACIONADA` si el callback queda en incidencia.
