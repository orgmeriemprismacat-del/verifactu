# 08 - Correus i plantilles

> Document viu. Recull plantilles, correus construits en codi directe, destinataris, adjunts/enllacos i condicions d'enviament.

## 1. Implementacio actual coneguda

La intranet disposa d'una classe `Template` per construir textos de correu i diverses classes d'enviament:

- `Template`: defineix plantilles i placeholders.
- `MailSMTPComvive`: envia correu amb SMTP de `prisma.cat`.
- `MailSMTPComviveBBCC`: variant SMTP amb possibilitat d'afegir destinataris addicionals abans d'enviar.
- `MailSMTP`: envia amb SMTP de Gmail.
- `Mail`: envia amb `mb_send_mail()`.

Aixo no vol dir que tots els correus actuals surtin de `Template`.

Situacio real:

- hi ha correus que es construeixen amb metodes de la classe `Template`;
- hi ha correus originals construits directament dins el codi PHP, especialment dins `Intranet.php` o dins fluxos concrets de la intranet;
- els fitxers PHP de pantalla i els AJAX acostumen a cridar metodes de `Intranet.php`, i dins aquests metodes hi ha el subject, destinatari, CC/BCC, adjunts i moment real d'enviament;
- per tant, la documentacio de correus s'ha de fer apartat per apartat, lligant: pantalla -> JS -> AJAX -> metode `Intranet.php` -> correu enviat.

Classificacio recuperada del xat antic:

1. Correus amb `Template`: reclamacions finals, control de morosos, comunicats, certificats i anul·lacions de curs.
2. Correus directes en PHP: HTML, subject, destinataris i enviament construits dins el metode o flux concret; poden fer servir `MailSMTP`, `MailSMTPComvive`, `MailSMTPComviveBBCC`, `Mail` o enviament directe.
3. Correus nous o tecnics del SIF: pagament acceptat/denegat, factura emesa, factura abans de cobrament, rectificativa i incidencia fiscal.

Regla:

```text
VERI*FACTU no obliga a passar tots els correus antics a Template de cop.
Si un correu es reprograma per motius SIF, s'ha de normalitzar i documentar.
```

Classes de suport relacionades:

- `Date`: formata dates en catala i formats curts/llargs.
- `Text`: normalitza text, accents, majuscules/minuscules i reemplaços.
- `ConnexioIntranet`, `ConnexioWeb`, `ConnexioMoodle`, `ConnexioMoodleAntic`: connexions a BD.

Apunt tecnic:

- algunes classes SMTP envien el correu durant el constructor; quan s'adapti a SIF, s'ha de vigilar que la simple creacio de l'objecte no enviï correus abans que la factura, PDF, QR o pagament estiguin confirmats.
- els correus no han de substituir el registre fiscal; nomes comuniquen un estat o una accio ja registrada.
- si es reprograma un apartat de manera important per VERI*FACTU, s'aprofitara per portar els correus directes d'aquell apartat a `Template`, sempre que no compliqui el desplegament.

El constructor de `Intranet.php` confirma que les condicions de molts correus no viuen dins `Template`, sino dins consultes i metodes de la classe `Intranet`.

### 1.1. Correu tecnic de pagament automatic

Al xat antic es va detectar que `realitzaPagamentAutomatic.php` envia un correu intern/tecnic de "pagament automatic" amb dades com:

- DNI;
- import;
- fraccio;
- `IDPAG`;
- `ORDER` o numero de comanda.

Lectura VERI*FACTU:

- aquest correu serveix per avis o diagnosi interna, no com a prova fiscal de factura emesa;
- no pot substituir el registre de Redsys, el registre de pagament ni la factura SIF;
- si es manté, s'ha d'enviar nomes quan la notificacio Redsys estigui validada o quedar substituit per log/notificacio interna;
- si el pagament queda confirmat pero la factura SIF falla, el correu o avis ha de dir "factura pendent/incidencia", no "factura creada".

Flux futur esperat:

```text
Redsys confirma pagament
-> es valida signatura, import i ordre
-> es registra o deduplica payment_transaction
-> el SIF decideix issueInvoice() o registerPayment()
-> nomes despres es comunica pagament/factura/incidencia segons estat real
```

Claus de consulta relacionades amb reclamacio/pagament detectades:

- `cnsReclamacions`;
- `cnsRegBaixesSegonaSetnaba`;
- `cnsCursosRecordarPag`;
- `cnsAlumnesRecordarPag`;
- `cnsCursosClaimBaixes`;
- `cnsAlumnClaimPag`;
- `cnsAlumnClaimEntMoros`;
- `cnsAlumnClaimAlumnNoCertMoros`;
- `cnsAlumnClaimAlumnCertMoros`;
- `cnsEntMoros`.

Claus d'actualitzacio relacionades:

- `updPrimeraReclamacio`;
- `updClaimDonarBaixa`;
- `updClaimRecPag`;
- `updInscCursBaixaiMoros`;
- `updReclamatDefaulter`.

Lectura documental:

- aquestes claus indiquen el moment administratiu en que es decideix enviar o marcar una reclamacio;
- el subject, destinatari, CC/BCC, adjunts i cos exacte continuen depenent del metode de `Intranet.php` que faci servir aquestes consultes;
- quan s'incorpori cada metode, s'haura de decidir si el correu queda temporalment com a codi directe o es passa a `Template`.

## 2. Placeholders actuals de `Template`

Placeholders detectats:

| Placeholder | Significat actual |
| --- | --- |
| `[CODI_CURS]` | Shortname del curs. |
| `[TITOL]` | Titol del curs. |
| `[AULA]` | Aula relacionada amb el curs. |
| `[ANY]` | Any del curs/edicio. |
| `[MES]` | Mes del curs/edicio, per exemple `06`. |
| `[DATAI]` | Data d'inici del curs. |
| `[DATAF]` | Data de fi del curs. |
| `[DATAI_DATAF]` | Text de periode del curs. |
| `[DATAF_INCR_3MONTHS]` | Data de fi incrementada 3 mesos. |
| `[NOM_TUTOR]` | Nom del tutor. |
| `[NOM_ALUMNE]` | Nom de l'alumne. |
| `[COG_ALUMNE]` | Cognoms de l'alumne. |
| `[CORREU_ALUMNE]` | Correu de l'alumne. |
| `[DNI_ALUMNE]` | DNI de l'alumne. |
| `[TEL_ALUMNE]` | Telefon de l'alumne. |
| `[PAY_ALUMNE]` | Pagament de l'alumne. |
| `[METHOD_PAY]` | Metode de pagament. |
| `[NOM_RESPONSABLE]` | Nom del responsable d'entitat/grup. |
| `[URL_PAGAMENT]` | Enllac de pagament. |
| `[DESPESES_GESTIO]` | Despeses de gestio esmentades en plantilles de baixa/reclamacio. |
| `[PAGAMENT]` | Import ja pagat o reservat per a una edicio futura segons plantilla. |
| `[TEXT_MES_EDICIO]` | Mes de l'edicio en text. |
| `[DATA_SESSIO1]` | Data llarga de la primera sessio. |
| `[HORA_SESSIO1]` | Hora de la primera sessio. |
| `[DIASETMANA_DATAI]` | Dia de la setmana de la data d'inici. |
| `[EDICIO_MES]` | Mes amb text llarg. |
| `[NOM_CURS_AULA_OBERTA]` | Nom de l'aula oberta. |
| `[ID_MDL_AULA_BERTA]` | ID Moodle de l'aula oberta. |

Els placeholders `[DESPESES_GESTIO]` i `[PAGAMENT]` s'han detectat dins plantilles existents i s'han d'incorporar formalment a l'esquema de `Template` si encara no hi son.

## 3. Tipus de correu

- Pagament acceptat.
- Pagament denegat.
- Factura abans de pagar.
- Factura manual.
- Factura emesa.
- Rectificativa.
- Devolucio.
- Canvi de curs.
- Baixa.
- Reclamacio.
- Morositat.
- Incidencia fiscal.

## 4. Plantilles actuals detectades

### 4.1. Reclamacio final

Metodes detectats:

| Metode | Destinatari | Cas funcional |
| --- | --- | --- |
| `getTemplate_LastClaimPayNoApproveAlumnPayNothing()` | Alumne | Reclamacio final: no ha superat el curs i no ha pagat res. |
| `getTemplate_LastClaimPayNoApproveAlumnPay()` | Alumne | Reclamacio final: no ha superat el curs pero hi ha import pagat reservat. |
| `getTemplate_LastClaimPayNoApproveTut()` | Tutor | Avís al tutor de baixa per no pagament/no superacio. |
| `getTemplate_LastClaimPayApproveAlumn()` | Alumne | Ha superat el curs pero no ha completat pagament; cal regularitzar per certificat. |
| `getTemplate_LastClaimPayApproveTut()` | Tutor | Avís al tutor de suspensio temporal per no pagament tot i curs superat. |
| `getTemplate_LastClaimPay_Responsable()` | Responsable | Reclamacio a responsable d'entitat/grup amb URL de pagament. |

Impacte VERI*FACTU:

- aquests correus poden incloure `[URL_PAGAMENT]`;
- la URL de pagament ha de passar a `pay.prisma.cat` quan el flux estigui migrat;
- la morositat o reclamacio no genera rectificativa automatica;
- si el pagament arriba despres, el flux ha de passar per `registerPayment()` o `issueInvoice()` segons si ja existeix factura fiscal.

### 4.2. Control morosos

Metodes detectats:

| Metode | Plantilla inclosa | Cas funcional |
| --- | --- | --- |
| `getTemplate_RespEntity_EntityClaimPayDefaulter()` | `templates/facturacio/control-morosos/responsable-grup-entitat.php` | Reclamacio a responsable d'entitat/grup. |
| `getTemplate_AlumnNoCertClaimPayDefaulter()` | `templates/facturacio/control-morosos/alumnes-sense-certificat.php` | Alumne moros sense certificat. |
| `getTemplate_AlumnCertClaimPayDefaulter()` | `templates/facturacio/control-morosos/deutes-antics.php` | Deutes antics d'alumnes amb certificat. |

Impacte VERI*FACTU:

- ser moros no bloqueja el pagament;
- les reclamacions han de quedar com a seguiment administratiu;
- si hi ha factura emesa, no s'ha de modificar la factura per enviar reclamacio;
- si es genera una URL, cal tipificar si es individual, empresa, grup, reclamacio o morositat.

### 4.3. Secretaria i comunicats

Metodes amb relacio indirecta:

| Metode | Cas |
| --- | --- |
| `getTemplate_Secretaria_AnularCurs_Alumne($dates, $pagament)` | Comunicacio a l'alumne quan s'anul·la un curs. |
| `getTemplate_Secretaria_EnviarCertificat_Alumne()` | Enviament de certificat. |
| `getTemplate_Comunicat_aPuntComencar()` | Comunicat de curs a punt de començar. |
| `getTemplate_Comunicat_AulesObertes()` | Comunicat relacionat amb aules obertes. |
| `getTemplate_Comunicat_Certificat()` | Comunicat de certificat. |
| `getTemplate_Comunicat_IA()` | Comunicat relacionat amb IA o servei especific. |
| `getTemplate_Comunicat_OberturaAules()` | Comunicat d'obertura d'aules. |
| `getTemplate_Comunicat_ServeiAtencio()` | Comunicat de servei d'atencio. |
| `getTemplate_Comunicat_Tancament($cas1)` | Tancament de curs. |

Impacte VERI*FACTU:

- anul·lar curs o donar baixa no implica per si sol rectificativa automatica;
- si hi ha retorn, saldo o canvi economic, s'haura de generar el flux fiscal separat;
- el certificat depen de coherencia entre estat academic, pagament i morositat, pero no genera factura.

### 4.4. Textos sensibles dins plantilles de reclamacio

El xat antic mostra que algunes plantilles de reclamacio final poden comunicar baixa del curs, reserva d'import per a una edicio futura, despeses de gestio o suspensio de certificat.

Regles:

- una plantilla pot comunicar una decisio administrativa o academica, pero no crea ni modifica una factura;
- si es parla d'import reservat, el SIF ha de tenir un moviment equivalent de saldo/compensacio o una decisio documentada;
- si es parla de despeses de gestio, aquestes no poden quedar nomes com a text de correu si tenen impacte economic;
- la baixa administrativa no implica rectificativa automatica;
- la morositat i la reclamacio no alteren imports d'una factura ja emesa.

## 5. Regles per adaptar correus al SIF

- No cal migrar tots els correus antics a `Template` de cop.
- Quan un apartat s'hagi de reprogramar de manera important per VERI*FACTU, s'aprofitara per passar els correus d'aquell apartat a `Template` si actualment estan construits directament dins el PHP.
- Els correus nous del SIF s'haurien de crear ja com a plantilles, no com HTML dispers dins el flux de negoci.
- Qualsevol correu que contingui un enllac de pagament s'ha de revisar i modificar.
- Aixo inclou correus amb placeholder `[URL_PAGAMENT]` i correus on la URL estigui construida directament dins el PHP.
- Les URLs de pagament dels correus han de passar a `pay.prisma.cat` quan el flux corresponent estigui migrat.
- El correu ha de conservar el tipus d'URL de pagament: individu, pack, grup, regal, empresa, USOC, diferencia de canvi de curs, reclamacio o morositat.
- Si existeix una factura d'empresa/responsable pendent de cobrament, el correu pot tenir URL de pagament, pero ha de ser la URL correcta d'empresa/responsable, no la URL individual de l'alumne.
- Un enllac de pagament enviat per correu ha d'estar tipificat i ha de saber si quan es paga s'ha de fer `issueInvoice()` o nomes `registerPayment()` contra una factura existent.
- Si el destinatari ja ha pagat o el correu es pot reobrir despres del pagament, el missatge ha de donar opcio a consultar la factura corresponent, amb enllac segur o acces a PDF/QR quan el SIF ja hagi generat el document.
- Un correu pot contenir una URL de pagament, una URL de consulta de factura, o totes dues si el cas ho requereix; el text ha d'evitar que l'usuari pagui dues vegades.
- No enviar correu de factura emesa abans que el SIF retorni `UUID_FACTURA` i `NUM_VISIBLE`.
- Si el correu adjunta PDF, el PDF ha d'existir a `factura_documents`.
- Si el PDF encara no existeix, el correu hauria d'enviar enllac segur o quedar pendent en cua.
- Els correus de reclamacio/morositat no poden modificar imports ni factures.
- Les plantilles han de distingir:
  - factura emesa;
  - factura abans de cobrament;
  - pagament pendent;
  - reclamacio;
  - morositat;
  - baixa;
  - saldo a favor;
  - rectificativa;
  - incidencia fiscal.

### 5.1. PDF, QR i enllac segur

Regles documentals:

- el PDF de factura nova no s'ha de regenerar des de dades vives cada vegada que algu el consulta;
- el SIF ha de generar el PDF en emissio o en una cua immediata posterior i conservar-lo a `factura_documents` amb hash;
- el QR i el text associat s'han d'incorporar a la factura segons l'especificacio AEAT vigent;
- en mode VERI*FACTU, el QR permet al receptor cotejar la factura a la seu electronica de l'AEAT;
- el contingut tecnic del QR ha d'incloure, com a minim, la URL de coteig/remissio i les dades fiscals exigides per la normativa vigent: NIF emissor, numero/serie de factura, data d'expedicio i import total;
- si el PDF/QR encara esta en cua, el correu de factura pot esperar o enviar un enllac segur que mostri l'estat "document pendent";
- si la generacio de PDF/QR falla, s'ha de crear incidencia SIF i no s'ha de desfer la factura.

Fonts oficials revisades el 2026-06-01:

- BOE, Orden HAC/1177/2024, capitol VIII sobre QR i frase associada: `https://www.boe.es/diario_boe/txt.php?id=BOE-A-2024-22138`
- AEAT, FAQ sobre QR, factura verificable i factura electronica: `https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/posibilidad-remision-informacion-factura-parte-receptor.html`

Regla d'enllac segur:

```text
L'enllac segur consulta el SIF i els permisos.
No exposa rutes internes ni fitxers publics sense control.
```

### 5.2. Incidencia, notificacio, avis i indicador

Nomenclatura acordada al xat antic:

- `incidencia SIF`: problema real registrat al SIF, amb estat, prioritat, responsable i log.
- `notificacio`: avis persistent guardat per mostrar a usuaris interns.
- `avis`: text puntual mostrat a una pantalla o correu.
- `indicador`: marca visual o nombre de pendents a l'apartat `VERI*FACTU`.

Evitar el terme `comptador` per a aquesta funcio, perque pot confondre's amb numeracio fiscal o comptadors de serie.

## 6. Pendents

- Per cada apartat revisat, documentar el mapa real: PHP de pantalla, JS, AJAX, metode `Intranet.php`, plantilla o codi directe, subject, destinatari, CC/BCC, adjunts i moment d'enviament.
- Quan es revisi un apartat, incorporar el JS/AJAX aportat i contrastar-lo amb el metode corresponent de `Intranet.php`.
- Localitzar tots els correus que contenen `[URL_PAGAMENT]`, `URL_PAGAMENT`, `href` a pagament o URLs TPV construides manualment.
- Documentar tambe tots els correus que han d'afegir enllac per consultar factura quan el pagament ja esta fet.
- Confirmar al codi de `Template` que `[DESPESES_GESTIO]` i `[PAGAMENT]` existeixen a l'esquema intern de placeholders.
- Revisar quins correus han d'adjuntar PDF i quins han d'enviar enllac segur.
- Identificar correus construits directament dins PHP i decidir si es migren a `Template` quan es reprogrami l'apartat afectat.
- Mapar el correu intern de `realitzaPagamentAutomatic.php` i decidir si queda com a log/notificacio SIF.

### 6.1. Primer apartat a mapar: Consulta - Modifica alumne

Aquest apartat no es nomes una fitxa de consulta. Des de les seves accions es poden enviar correus o notificacions relacionades amb:

- dades de pagament i observacions de pagament;
- reclamacio o seguiment de pagament;
- canvi de curs;
- baixa de curs;
- factura visible/descarregable;
- certificat.

Quan es revisi aquest apartat, no cal tornar a preguntar genericament pels subject/destinataris: s'han d'extreure del codi de `Intranet.php` i del JS/AJAX de la pantalla.

Sortida documental esperada per cada correu:

```text
Apartat:
Accio que dispara el correu:
Metode Intranet.php:
Template o codi directe:
Subject:
Destinatari:
CC/BCC:
Adjunt PDF o enllac segur:
Inclou URL de pagament? si/no
Inclou opcio veure factura si ja ha pagat? si/no
Condicio SIF abans d'enviar:
```
