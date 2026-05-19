# 11 - Inventari de canvis pendents VERI*FACTU

> Document viu. Recull tots els canvis detectats fins ara, incloent canvis funcionals, tecnics, de BD, de permisos, de pantalles, de correus i de fluxos.

## 1. Factures abans de pagar i responsables de grup

Problema:

- quan es genera una factura abans de pagar a nom d'una empresa/responsable, posteriorment es pot generar una altra factura en passar el pagament;
- passa especialment si el responsable posa el CIF de l'empresa com a DNI/contacte;
- es pot acabar amb dues factures per la mateixa operacio.

Canvi necessari:

- detectar factura previa real abans d'emetre una nova;
- si hi ha factura previa, registrar cobrament amb `registerPayment()`;
- desactivar enllacos de pagament individuals;
- tractar-ho com a factura real abans de cobrament, no com a proforma.

## 2. Anulacio d'enllac de pagament per factura abans de pagar

Quan una inscripcio queda inclosa en una factura abans de pagar:

- desactivar enllac TPV;
- mostrar missatge que pagara l'entitat/responsable;
- evitar que l'alumne pugui pagar individualment i generar duplicat.

## 3. Pagament posterior d'inscripcions incloses en factura abans de pagar

Quan es registra el pagament:

- no crear factura nova;
- registrar pagament contra factura existent;
- actualitzar estat cobrament.

## 4. Desactivacio URL TPV

Cal:

- trobar logica actual de generacio d'URL;
- afegir estat d'enllac actiu/inactiu;
- afegir motiu d'inactivacio;
- moure URL a `pay.prisma.cat`;
- mostrar missatge adequat si l'enllac esta desactivat;
- si el pagament ja esta fet, mostrar acces a consultar factura/PDF/QR en lloc d'insistir en pagar.

## 5. Analitzar fitxer TPV

Cal adaptar l'apartat a:

- SIF;
- idempotencia;
- `DS_ORDER`;
- pagaments sense factura;
- factures sense pagament;
- duplicats;
- incidencies.

## 6. Pas de dades de facturacio

Afegir a cada tipus d'inscripcio/pagament:

- pas de confirmacio de dades fiscals;
- per defecte, dades del client;
- boto per canviar dades de facturacio;
- guardar snapshot fiscal abans d'emetre factura.

## 7. Descarrega de factures i registres

Afegir apartat per:

- descarregar factures per mes;
- descarregar/exportar registres fiscals;
- consultar registres enviats/pendents/error;
- facilitar inspeccio o revisio interna.

## 8. Factures manuals

Afegir apartat per:

- generar factura manual;
- escollir entitat existent;
- introduir dades fiscals manualment;
- crear linies;
- cridar SIF;
- enviar correus.

## 8.1. Entitats i responsables d'entitat

L'apartat actual:

```text
https://intranet.prisma.cat/alumnes/genera-entitat/
```

serveix per crear i editar entitats i la persona responsable de la seva gestio.

Canvi necessari:

- validar i normalitzar CIF, rao, adreca, CP i poblacio;
- validar correu del responsable;
- evitar o avisar duplicats per CIF;
- guardar historial de canvis si les dades poden haver estat utilitzades en factures;
- en emetre factura, copiar snapshot fiscal al SIF;
- no usar l'edicio d'entitat per modificar factures ja emeses;
- distingir responsable de contacte i receptor fiscal.

## 9. Correus amb factura

Cada generacio de factura ha de enviar correus segons cas:

- client/alumne;
- empresa/responsable;
- participant, si correspon;
- gestio interna.

Decisio pendent:

- adjuntar PDF;
- o enviar enllac segur.

## 9.1. Correus amb enllac de pagament

Qualsevol correu que tingui enllac de pagament s'ha de modificar dins l'adaptacio VERI*FACTU/pay.prisma.cat.

Afecta:

- correus amb placeholder `[URL_PAGAMENT]`;
- correus amb URL de pagament construida directament dins el PHP;
- reclamacions;
- morositat;
- recordatoris de pagament;
- factures abans de cobrament;
- factures d'empresa/responsable amb URL propia;
- diferencies per canvi de curs;
- pagaments fraccionats.

Canvi necessari:

- substituir URL antiga per URL controlada de `pay.prisma.cat`;
- tipificar el tipus d'URL: individu, pack, grup, regal, empresa, USOC, diferencia, reclamacio o morositat;
- assegurar que el pagament posterior crida `registerPayment()` si la factura ja existeix;
- assegurar que no es genera factura duplicada des d'un correu/reintent;
- afegir opcio de veure factura/PDF/QR quan el destinatari ja ha pagat o quan la factura ja consta com emesa;
- revisar el text del correu per evitar que una persona que ja ha pagat torni a clicar una URL de pagament;
- si es reprograma l'apartat, moure el correu a `Template`.

## 10. Pagaments fraccionats i recordatoris

Hi ha molts apartats que mostren:

- pagat fins ara;
- pendent;
- data limit;
- URL de pagament.

Cal adaptar-los per mostrar:

- factures associades;
- estat cobrament;
- estat AEAT;
- enllac actiu/inactiu;
- saldo/compensacio si existeix.

## 11. Optimitzacio BD

Tasques:

- revisar indexos;
- mantenir historics fora de taules actives;
- separar BD fiscal de BD intranet;
- evitar consultes lentes en pantalles de gestio;
- garantir InnoDB en taules fiscals;
- revisar permisos MySQL.

## 12. Informacio de l'alumne

Cal adaptar:

- icones de factura;
- estat factura;
- estat cobrament;
- estat AEAT;
- visibilitat de factura d'empresa/grup;
- dades de pagament;
- URL TPV;
- PDFs.

## 13. Veure factura

Cal modificar:

- visualitzacio de factura;
- mostrar VERI*FACTU / no VERI*FACTU;
- mostrar QR/PDF immutable;
- mostrar rectificatives;
- no permetre edicio de factura emesa.

## 14. Canvis de curs i pagament per diferencia

Cal:

- guardar historic de canvis;
- detectar si el pagament correspon a diferencia per canvi de curs;
- recalcular descompte;
- registrar despeses de gestio;
- generar factura/rectificativa/compensacio segons cas.

## 15. Canvi de curs amb curs mes car o mes barat

Casos:

- nou curs mes car: diferencia pendent;
- nou curs mes barat: retorn o saldo;
- pagaments traslladats;
- mes d'un canvi consecutiu;
- descompte reaplicat o recalculat.

## 16. Descompte excepcional per canvi de curs

Problema actual:

- es modifica `A_PAGAR` directament.

Canvi necessari:

- crear descompte/ajust documentat;
- guardar motiu intern;
- mostrar text visible generic si cal;
- evitar modificar nomes import final sense rastre.

## 17. Anulacio/baixa de curs

Flux nou:

- baixa de curs marca inscripcio;
- no toca pagament al moment;
- client decideix retorn/saldo/no retorn;
- Adam/Pablo registren devolucio o saldo;
- rectificativa quan pertoqui.

## 18. Migracio a pay.prisma.cat

Cal traspassar:

- confirmacio de pagament;
- callbacks Redsys;
- URL pagament;
- generacio/control de factures;
- PDF/QR;
- endpoints SIF.

També cal crear un panell intern del SIF a `pay.prisma.cat/sif` per:

- documentacio i declaracio responsable;
- versions;
- factures i registres AEAT;
- incidencies SIF;
- exports;
- configuracio.

La intranet principal tindra un apartat `VERI*FACTU` amb indicador visual de pendents, resum d'incidencies i accessos, pero no sera la font oficial fiscal.

## 19. PDFs immutables

Canvi necessari:

- deixar de regenerar PDF des de dades vives;
- generar PDF en emissio;
- guardar fitxer;
- guardar hash;
- servir amb permisos.

## 20. Permisos i bloquejos

Cal:

- bloquejar edicio directa de factures emeses a app;
- restringir permisos MySQL;
- evitar que altres persones modifiquin BD fiscal manualment;
- documentar qui pot fer rectificatives.

## 21. Notificacions fiscals

Cal implementar:

- notificacio per error AEAT;
- notificacio per retries fallits;
- notificacio per factura `FAILED`;
- notificacio per anomalies de pagament/factura;
- indicador visual de pendents a l'apartat `VERI*FACTU` de la intranet;
- resum d'incidencies pendents a la intranet;
- gestio oficial de la incidencia al panell SIF.

## 22. Declaracio responsable i documentacio

Cal mantenir:

- document intern del projecte;
- document funcionament SIF AEAT;
- declaracio responsable;
- fluxos;
- model BD;
- integracio Redsys;
- pantalles intranet;
- correus;
- checklist produccio.

## 23. Migracio de factures historiques

Decisio:

- migrar `web.factures` al SIF;
- marcar-les com a historic no VERI*FACTU;
- conservar numeracio original;
- conservar relacio amb `FACTURA_RELACIONADA`;
- vincular-les a `fact_rels`;
- no generar registres VERI*FACTU retroactivament.

## 24. fact_rels

Crear nova taula `fact_rels` per:

- mantenir compatibilitat amb `FACTURA_RELACIONADA`;
- relacionar factures fiscals per `UUID_FACTURA`;
- gestionar rectificatives;
- gestionar factures de grup/empresa;
- controlar visibilitat a alumne.

## 25. PDF en cua

Decisio:

- `issueInvoice()` crea factura i registre fiscal;
- la generacio PDF/QR pot anar en cua o worker;
- el sistema retorna factura creada encara que el PDF es generi immediatament despres;
- si falla PDF, es crea incidencia, no es desfà la factura.

## 26. Crear panell intern del SIF a pay.prisma.cat/sif

Cal crear una petita intranet/panell intern del SIF a:

```text
pay.prisma.cat/sif
```

Aquest panell sera la font oficial de:

- factures;
- registres AEAT;
- documents fiscals;
- declaracio responsable;
- versions;
- incidencies SIF;
- exportacions;
- configuracio tecnica.

Apartats a crear:

- Dashboard;
- Factures;
- Registres AEAT;
- Incidencies;
- Documents;
- Versions;
- Exportacions;
- Configuracio.

### 26.1. Dashboard

Crear pantalla resum amb:

- factures emeses avui/mes/any;
- registres AEAT pendents, acceptats, rebutjats o en retry;
- incidencies obertes per prioritat;
- PDF/QR pendents;
- ultima factura emesa;
- estat connexio AEAT;
- versio activa del SIF.

### 26.2. Factures

Crear pantalla de consulta de factures amb:

- cerca per numero, UUID, NIF/CIF, `FACTURA_RELACIONADA`;
- filtres per estat factura, estat AEAT i estat cobrament;
- linies de factura;
- pagaments associats;
- rectificatives;
- documents PDF/XML/QR;
- origen de la factura.

No permetre edicio directa de factura emesa.

### 26.3. Registres AEAT

Crear pantalla per:

- consultar registres fiscals;
- veure hash, hash anterior i fiscal order;
- veure estat AEAT;
- veure intents i proper retry;
- veure resposta/error AEAT;
- exportar registres;
- reintentar si el rol ho permet.

### 26.4. Incidencies

Crear pantalla oficial d'incidencies SIF:

- prioritat;
- estat;
- factura afectada;
- origen;
- error tecnic;
- responsable assignat;
- historial d'accions;
- resolucio.

La intranet principal només mostra resum/indicador; la resolucio oficial es fa aquí.

### 26.5. Documents

Crear pantalla de documents:

- PDF factura;
- XML si correspon;
- QR;
- declaracio responsable signada;
- documentacio tecnica;
- documents de versio;
- hash del fitxer;
- data de generacio.

### 26.6. Versions

Crear pantalla de versions:

- versio activa;
- data entrada produccio;
- responsable tecnic;
- responsable legal/direccio;
- declaracio responsable associada;
- historial de versions.

### 26.7. Exportacions

Crear pantalla per generar i conservar exports:

- factures per periode;
- registres AEAT;
- registre d'events;
- incidencies;
- documents vinculats;
- relacions factura-inscripcio/pagament.

Cada export ha de guardar:

- usuari;
- data;
- criteris;
- fitxer;
- hash;
- motiu si es per requeriment.

### 26.8. Configuracio

Crear pantalla restringida per:

- dades emissor fiscal;
- series actives;
- mode VERI*FACTU;
- endpoints AEAT;
- certificat digital/configuracio segura;
- retries;
- rutes de documents;
- permisos/rols;
- estat de workers.

Tots els canvis de configuracio han de generar log.

## 27. Crear apartat VERI*FACTU a la intranet principal

Cal crear un apartat unic a la intranet:

```text
VERI*FACTU + indicador visual de pendents
```

Aquest apartat ha de mostrar:

- boto per obrir `pay.prisma.cat/sif`;
- resum d'incidencies pendents;
- acces als documents SIF;
- acces a exportacions;
- estat general de cua AEAT si cal.

Regla:

```text
La intranet mostra resum i accessos.
El SIF conserva i gestiona la informacio fiscal oficial.
```

## 28. Com generar aquests apartats

Implementacio recomanada:

Cada pantalla nova no s'ha de quedar nomes amb el nom. Per cada apartat cal documentar, com a minim:

- URL i fitxer PHP o ruta del panell SIF;
- rols que hi poden entrar;
- camps de cerca i filtres;
- columnes de resultat;
- accions permeses;
- accions prohibides;
- endpoints o metodes que crida;
- taules principals;
- correus que pot enviar;
- errors que generen incidencia o notificacio;
- criteris de prova abans de donar-ho per tancat.

1. Crear estructura base de `pay.prisma.cat/sif`.
2. Crear autenticacio i rols del panell SIF.
3. Crear taules i endpoints SIF necessaris per dashboard, incidencies, documents, versions i exports.
4. Crear pantalla Dashboard.
5. Crear pantalla Factures.
6. Crear pantalla Registres AEAT.
7. Crear pantalla Incidencies.
8. Crear pantalla Documents.
9. Crear pantalla Versions.
10. Crear pantalla Exportacions.
11. Crear pantalla Configuracio.
12. Crear apartat `VERI*FACTU` a la intranet principal.
13. Connectar la intranet amb el SIF per mostrar indicador visual i resum d'incidencies.
14. Afegir permisos i rol auditor/AEAT nomes lectura.
15. Afegir proves al pla de validacio.
