# 41 - Matriu de superfície executable, casos funcionals i persistència

Data de tall: 16/09/2026.

## 1. Objectiu i límit

Aquesta matriu evita confondre un catàleg documental amb la cobertura del codi
real. Classifica les superfícies trobades a les set carpetes de `codi-drive` i
indica quin cas, registre i substitut SIF necessita cada família.

No s'ha carregat el JSONL de `xat-original`, no s'ha modificat PHP llegat i no
s'afirma que totes les còpies locals estiguin actives a producció. La selecció
definitiva d'endpoints actius requereix congelar versió, virtual hosts, rutes i
crons de producció.

## 2. Volum que obliga a revisar més enllà dels callbacks

| Carpeta | PHP | PHP propis | Endpoints/directoris AJAX detectats | Lectura |
| --- | ---: | ---: | ---: | --- |
| `web-actual` | 443 | 443 | 256 | Principal superfície d'inscripció, descomptes, regals, packs i promocions. |
| `intranet-actual` | 342 | 342 | 218 | Gestió acadèmica, edicions, factures, cobraments i validacions. |
| `intranet-alumne-actual` | 60 | 48 | 20 | Consulta de pendent, enllaç de pagament, dades personals i accés acadèmic. |
| `old-intranet` | 579 | 375 | 38 | Superfície històrica; cal decidir retirada o compatibilitat. |
| `intranet-collaboradors` | 471 | 459 | 29 | Circuit adjacent de tutors/proveïdors, no venda a alumnes. |
| `intranet-nova-canvis-verifactu` | 2 | 2 | 1 | No representa encara tota la intranet adaptada. |
| `pay-prisma-cat-canvis-verifactu` | 23 | 23 | 3 | Candidata parcial centrada en pagament/callback, no SIF complet. |

`Intranet.php` conté 510 mètodes i `IntranetAlumne.php`, 68. El recompte no
converteix cada mètode en un cas d'ús, però invalida qualsevol conclusió de
cobertura basada només en els 23 fitxers de la candidata de pagament.

## 3. Llegenda

- `MAPPED`: responsabilitat diferenciada i cas explícit; no implica implementació.
- `PARTIAL`: existeix un cas proper però li faltava el cicle o registre complet.
- `GAP`: la conducta executable no tenia cas/persistència suficient abans
  d'aquesta revisió.
- `ADJACENT`: circuit real que s'ha de separar del SIF de venda.
- `UNKNOWN_ACTIVE`: còpia, endpoint o script pendent de confirmar a producció.

## 4. Matriu de punts d'entrada i escriptures

| Superfície/evidència | Escriptura o decisió llegada | Cobertura anterior | Cas canònic després de revisar | Persistència/contracte necessari | Estat de retirada/integració |
| --- | --- | --- | --- | --- | --- |
| `ajax/enviarInscripcio.php` | `inscripcions`, `mailing`, població, docent novell, promoció | `PARTIAL` | UC-106, UC-107, UC-109, UC-111, UC-112, UC-115 | `commercial_operation`, línies/parts, reserva, validació i snapshot | Adaptador SIF pendent. |
| `ajax/enviarInscripcioAfiliat.php` | inscripció i dades territorials | `PARTIAL` | UC-106, UC-107, UC-112, UC-115 | operació, línia, participant, plaça i classificació | Adaptador SIF pendent. |
| `ajax/enviarInscripcioTaller.php` | inscripció de taller abans de pagament | `PARTIAL` | UC-106, UC-107, UC-112, UC-115 | operació/línia/reserva; factura i cobrament separats | Adaptador SIF pendent. |
| `ajax/enviarInscripcioPack.php` | alta de pack/components | `GAP` | UC-106, UC-112, UC-115, UC-122 | línia pare/components, places i enllaç a línies fiscals | Migració 000005; servei pendent. |
| `ajax/enviarInscripcioTastet.php` | `inscripcions_reptes`, accés gratuït, mailing | `MAPPED` | UC-108 | operació `FREE_SAMPLE`; consentiment separat | Adaptador pendent. |
| `DescompteAmic.php` | dues inscripcions, responsable i un `IDPAG` | `MAPPED/PARTIAL` | UC-110, UC-112, UC-115 | dues línies/participants, una intenció i assignació explícita | Regla fiscal pendent. |
| `DescompteGrup.php`, `enviaDades_DescompteGrup.php` | responsable, participants, tram i import | `GAP` | UC-91, UC-115, UC-118 | grup mutable, línies, places, tram, lock i snapshot | Servei/grup pendent. |
| `ajax/enviarInscripcioBescanvia.php` | nova inscripció, nou `IDPAG`, reutilització de `FACT_REL` | `GAP` | UC-17, UC-18, UC-119 | dret/regal i events, operació de bescanvi, relació fiscal explícita | Prohibit copiar equivalències sense regla. |
| `ajax/obtenirCorreusValidsPromo.php` | crea/assigna codis promocionals | `GAP` | UC-20d, UC-111, UC-117 | `commercial_entitlement` i ledger de reserva/consum/reversió | Servei pendent. |
| `ajax/enviarImatgeCarnetInscripcio.php` | fitxer de carnet al webroot i correu | `GAP` | UC-90, UC-111, UC-116 | custòdia protegida, hash, accés, decisió, retenció/supressió | Retirada del webroot bloquejant. |
| `Intranet::pujar_Inscripcions()` | marca alta i genera CSV per Moodle | `GAP` | UC-113, UC-124 | execució/fila d'importació i event acadèmic; cap cobrament inferit | Adaptador i conciliació pendents. |
| `modalEditaInscripcio_pujadaAlumnes()` | edita dades d'inscripció mentre consulta camps econòmics | `PARTIAL` | UC-70, UC-100, UC-113, UC-120 | sol·licitud/versionat i `operational_event` | Edició directa a retirar. |
| `desarCanvisDadesEdicio()` | nom, dates, hores, codis i resolució de curs | `GAP` | UC-70, UC-114 | canvi mestre versionat i decisió per operació oberta | Gateway pendent. |
| `desarCanvisDadesAulaEdicio()` | dates/observacions d'aula | `GAP` | UC-100, UC-114 | canvi mestre, actor, motiu i efectes acadèmics | Gateway pendent. |
| `inscripcionOberta()`, `inscripcioVisible()` i recomptes | obertura/visibilitat/capacitat eventual | `GAP` | UC-106, UC-115 | ledger de places amb lock, expiració i llista d'espera | Implementació pendent. |
| `sendMsgValidatCurosDescomptes()` | valida `TIPUS_DESC`, recalcula `A_PAGAR`, envia URL | `GAP/PARTIAL` | UC-90, UC-111, UC-116, UC-117, UC-121 | evidències múltiples, decisió, dret futur, snapshot i enllaç nou | Update/import viu a retirar. |
| `generarFacturaElectronica_Alumnes()` i `E_FACT` | marca/genera factura electrònica | `PARTIAL` | UC-32, UC-123 | document immutable i ledger d'entrega/retry | Format/canal/SLA pendents. |
| `marcarInscripcioCursSuperat/NoSuperat()` | actualitza certificat i comunica segons deute | `PARTIAL` | UC-95, UC-124 | event acadèmic/econòmic correlacionat | Regles i adaptador Moodle pendents. |
| `IntranetAlumne::obtenirUrlPagament()` | reconstrueix URL xifrada des d'`IDPAG` | `PARTIAL` | UC-61, UC-86, UC-103, UC-121 | `payment_link` amb token hash, caducitat/revocació i events | URL llegada a retirar. |
| `enviarMsgSolicitantModificacioDades()` | envia correu amb dades abans/després | `GAP` | UC-70, UC-120 | expedient, decisió i propagació auditada | Correu sol no és registre. |
| `subscripcioAulaOberta()` | canvia `PERENNE` i toca matrícules/rol Moodle | `PARTIAL` | UC-95, UC-124 | event acadèmic, adaptador idempotent i reconciliació | Integració pendent. |
| `ajax/mailing.php`, `mailingNou.php`, `inscripcio_mailing.php` i alta des d'inscripció | consentiment sí/no, sol·licitud de subscripció i correu de confirmació | `GAP` | UC-108, UC-125 | consentiment per finalitat/canal i ledger de versió, confirmació i retirada | Separació del camp `INSC_MAILING` pendent. |
| `mostrarTable_Alumnes_CorreuDiferentBDCampus()` i comprovacions DNI/duplicat | compara identitat/correu entre inscripció i Moodle | `GAP` | UC-107, UC-120, UC-126 | enllaços d'identitat externa i expedient de conflicte | L'avís per correu no resol ni acredita la identitat. |
| `desarCanvisEstatEnviarMsg_PreviIniciCursos()` | activa, deixa pendent o anul·la edició; baixa alumnes i consulta factura/pagament | `GAP` | UC-27, UC-28, UC-29, UC-74, UC-114, UC-127 | event d'edició, inventari d'operacions afectades i decisió individual | Orquestrador massiu i verificació de totals pendents. |
| writers a `poblacions_validar` | acumula CP/població desconeguts durant altes web | `GAP` | UC-69, UC-120, UC-128 | cas de validació amb entrada original, proposta, regla, decisió i propagació | No s'ha detectat cicle complet de revisió. |
| `mostrar_Dades_ComprovacioNombreAlumnes()` i comprovacions correu/perfil | compara usuaris, cursos, correus i matrícules Prisma/Moodle | `GAP` | UC-124, UC-129 | `reconciliation_run` i ítems acadèmics amb resolució idempotent | Automatització/autoritat per camp pendents. |
| callbacks/classes de `pay-prisma-cat-canvis-verifactu` | efectes de pagament/factura per tipus | `PARTIAL` | UC-03, UC-63, UC-68, UC-86, UC-112 | intenció, moviment, factura, events i cua | Només candidata; no substitueix canals. |
| `old-intranet` i còpies datades/proves | escriptures potencialment duplicades | `UNKNOWN_ACTIVE` | UC-64, UC-68 | inventari de rutes/crons, bloqueig o adaptador únic | Confirmació productiva bloquejant. |
| `intranet-collaboradors` | factures/honoraris/cobraments de tutors | `ADJACENT` | UC-65, UC-66, UC-99 | domini de proveïdors separat i permisos | No integrar com a venda SIF. |

## 5. Buits de model que la matriu fa explícits

La migració 000004 resol l'arrel comercial, parts, validació i enllaços, però no
pot representar per si sola:

1. diverses línies/components, quantitats i fiscalitat d'un pack o grup;
2. el vincle determinista de cada línia comercial amb `factura_linia`;
3. reserva/alliberament de places amb concurrència i llista d'espera;
4. més d'una evidència sensible, accés i supressió;
5. emissió, reserva, consum, expiració i reversió de promocions/regals;
6. execució i resultat per fila d'una importació;
7. versionat de canvis mestres i decisió sobre reserves obertes;
8. expedient persistent de canvi de dades personals;
9. prova de generació i lliurament de factura electrònica;
10. correlació append-only entre estat acadèmic i snapshot econòmic;
11. consentiment versionat i independent de la inscripció;
12. identitat canònica i conflictes entre web, intranet, Moodle i llegat;
13. canvi massiu d'estat d'una edició amb decisió per operació afectada;
14. revisió de CP/població amb original, proposta i propagació;
15. reconciliació acadèmica per execució i ítem entre Prisma i Moodle.

La migració 000005 materialitza els deu primers punts i la 000006 els cinc
últims com a disseny additiu. No s'han aplicat ni provat en MySQL i encara no
tenen serveis/adaptadors.

## 6. Regla de cobertura per donar un punt d'entrada per tancat

Una fila només passarà a `CLOSED` quan s'acrediti:

1. que és activa o retirada a producció, amb versió i ruta;
2. cas/variant i decisió funcional específica;
3. actor, permís, CSRF/autenticació i validació de servidor;
4. persistència, idempotència, concurrència i auditoria;
5. absència d'escriptura fiscal/econòmica paral·lela al llegat;
6. prova nominal, duplicat, error, concurrència i recuperació;
7. evidència de preproducció i responsable d'acceptació.

Fins a completar aquest control, el sistema continua `NO-GO` i el recompte de
142 fitxes no és una declaració de completitud.
