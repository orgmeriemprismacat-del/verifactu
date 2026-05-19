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

Classes de suport relacionades:

- `Date`: formata dates en catala i formats curts/llargs.
- `Text`: normalitza text, accents, majuscules/minuscules i reemplaços.
- `ConnexioIntranet`, `ConnexioWeb`, `ConnexioMoodle`, `ConnexioMoodleAntic`: connexions a BD.

Apunt tecnic:

- algunes classes SMTP envien el correu durant el constructor; quan s'adapti a SIF, s'ha de vigilar que la simple creacio de l'objecte no enviï correus abans que la factura, PDF, QR o pagament estiguin confirmats.
- els correus no han de substituir el registre fiscal; nomes comuniquen un estat o una accio ja registrada.
- si es reprograma un apartat de manera important per VERI*FACTU, s'aprofitara per portar els correus directes d'aquell apartat a `Template`, sempre que no compliqui el desplegament.

El constructor de `Intranet.php` confirma que les condicions de molts correus no viuen dins `Template`, sino dins consultes i metodes de la classe `Intranet`.

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
| `[TEXT_MES_EDICIO]` | Mes de l'edicio en text. |
| `[DATA_SESSIO1]` | Data llarga de la primera sessio. |
| `[HORA_SESSIO1]` | Hora de la primera sessio. |
| `[DIASETMANA_DATAI]` | Dia de la setmana de la data d'inici. |
| `[EDICIO_MES]` | Mes amb text llarg. |
| `[NOM_CURS_AULA_OBERTA]` | Nom de l'aula oberta. |
| `[ID_MDL_AULA_BERTA]` | ID Moodle de l'aula oberta. |

Placeholders detectats dins plantilles pero no inclosos encara a l'esquema inicial:

- `[DESPESES_GESTIO]`
- `[PAGAMENT]`

Cal afegir-los a l'esquema de `Template` quan es revisi la classe.

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
| `getTemplate_Comunicat_Certificat()` | Comunicat de certificat. |
| `getTemplate_Comunicat_Tancament($cas1)` | Tancament de curs. |

Impacte VERI*FACTU:

- anul·lar curs o donar baixa no implica per si sol rectificativa automatica;
- si hi ha retorn, saldo o canvi economic, s'haura de generar el flux fiscal separat;
- el certificat depen de coherencia entre estat academic, pagament i morositat, pero no genera factura.

## 5. Regles per adaptar correus al SIF

- No cal migrar tots els correus antics a `Template` de cop.
- Quan un apartat s'hagi de reprogramar de manera important per VERI*FACTU, s'aprofitara per passar els correus d'aquell apartat a `Template` si actualment estan construits directament dins el PHP.
- Els correus nous del SIF s'haurien de crear ja com a plantilles, no com HTML dispers dins el flux de negoci.
- Qualsevol correu que contingui un enllac de pagament s'ha de revisar i modificar.
- Aixo inclou correus amb placeholder `[URL_PAGAMENT]` i correus on la URL estigui construida directament dins el PHP.
- Les URLs de pagament dels correus han de passar a `pay.prisma.cat` quan el flux corresponent estigui migrat.
- El correu ha de conservar el tipus d'URL de pagament: individu, pack, grup, regal, empresa, USOC, diferencia de canvi de curs, reclamacio o morositat.
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

## 6. Pendents

- Per cada apartat revisat, documentar el mapa real: PHP de pantalla, JS, AJAX, metode `Intranet.php`, plantilla o codi directe, subject, destinatari, CC/BCC, adjunts i moment d'enviament.
- Quan es revisi un apartat, incorporar el JS/AJAX aportat i contrastar-lo amb el metode corresponent de `Intranet.php`.
- Localitzar tots els correus que contenen `[URL_PAGAMENT]`, `URL_PAGAMENT`, `href` a pagament o URLs TPV construides manualment.
- Documentar tambe tots els correus que han d'afegir enllac per consultar factura quan el pagament ja esta fet.
- Afegir `[DESPESES_GESTIO]` i `[PAGAMENT]` a l'esquema intern de placeholders si encara no hi son.
- Revisar quins correus han d'adjuntar PDF i quins han d'enviar enllac segur.
- Identificar correus construits directament dins PHP i decidir si es migren a `Template` quan es reprogrami l'apartat afectat.

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
