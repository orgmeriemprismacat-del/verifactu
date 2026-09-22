# Lot 05 — UC-116: custodiar i revisar justificants de descompte

**Data i versió:** 22/09/2026 · `main` @ `e71958b3026549bde09fb4b25f2ec3ba370937ec`. **Branca de documents:** `docs/registre-mestre-auditoria-2026-09-22`. **UC auditada en aquest lot:** només UC-116; NO es revisen UC-111, UC-113 ni UC-114. **Resultat:** DOC CONTRASTAT PARCIALMENT / IMPLEMENTACIÓ INTEGRAL NO ACREDITADA / TEST NO EXECUTAT / PRODUCCIÓ NO VERIFICADA.

[Registre mestre](../00-index-i-pla/42-registre-mestre-cobertura-funcional-implementacio-documentacio.md) · [Fitxa funcional UC-116](../06-fitxes-funcionals/uc-116.md) · [Fitxa UML UC-116](uc-116-custodiar-evidencies-descompte.md) · [Diagrames d'activitat de les pàgines i apartats inspeccionats](uc-116-activitats-pagines-justificants-actual-final.md).

> **Privacitat:** no obrir, enumerar ni citar fitxers individuals de justificants, identificadors personals, adreces de correu, credencials, diagnòstics o documents adjunts. Aquesta auditoria es basa en **metadades d'arbre Git i codi PHP/JS/SQL**, sense inspeccionar el contingut dels documents de terceres persones.

## 1. Quines funcionalitats actuals estan representades realment

| Acció real / canal | Traça contrastada al codi de main | BD, fitxer, efecte o límit | Correspondència UC i separació |
| --- | --- | --- | --- |
| Consultar informació de descomptes (web pública) | [Descomptes.php, apartats tipus 1–5](../../codi-drive/web-actual/Descomptes.php#L185-L257): alumni PrisMa, Carnet Jove, socials, USOC, grups. Per «Socials», la pàgina indica adjuntar carnet/resolució i esperar validació abans de pagar amb el descompte. | És informació comercial; no prova que el formulari ni el servidor imposin aquesta regla. Les famílies de descomptes poden tenir requisits documentals DIFERENTS: no demanar un justificant sensible indiscriminadament. | UC-116 només cobreix l'evidència requerida en les variants que la necessitin; elegibilitat, preu i rectificació pertanyen als UC de descompte corresponents. |
| Aportar una prova en formulari de web | [ajax/enviarImatgeCarnetInscripcio.php](../../codi-drive/web-actual/ajax/enviarImatgeCarnetInscripcio.php#L12-L38) rep POST amb curs/edició/any/documentacio i `$_FILES['file']`; compon un nom des de valors rebuts i l'extensió original, després executa `move_uploaded_file(...,'carnets/'.$nomImg)`, retorna el camí o 0. | Al codi no hi ha associació explícita a `ID_INSC` o `UUID_VALIDATION`, hash dels bytes, comprovació MIME real/mida, comprovació de permisos o emmagatzematge fora del webroot. Aquestes absències es refereixen **a aquest endpoint inspeccionat**, no a tots els controls d'infraestructura. | UC-116: «aportació rebuda» no equival a «custodiada/validada»; vincular-la al dret/inscripció i mantenir les proves fora d'URLs públiques. |
| Preparar avís de recepció de prova | [mateix endpoint, L39–75](../../codi-drive/web-actual/ajax/enviarImatgeCarnetInscripcio.php#L39-L75): construeix HTML amb URL directa `https://www.prisma.cat/ajax/carnets/` + nom del fitxer; instancia objecte de correu. | El PHP exposa un enllaç nominal al fitxer a qui disposi d'aquella URL. La construcció de l'objecte no prova per si sola que s'enviï efectivament el correu; no existeix en aquest flux verificat un lliurament segur amb comprovació de rol abans de descarregar. | UC-116: missatge d'operació sense adjuntar ni enllaçar prova personal via URL directa; accessos protegits i auditats. |
| Obrir «Validar descomptes» a la intranet | [alumnes-validar-descomptes.php](../../codi-drive/intranet-actual/alumnes-validar-descomptes.php#L1-L51) inclou comprovació de sessió; [mostrarMain.php](../../codi-drive/intranet-actual/ajax/mostrarMain.php#L14-L84) consulta rols de visualització de l'apartat, comprova `tePermisVisualitzacio` i prepara `Intranet::mostrarPage`. | **Hi ha** control de visualització de la pàgina; això no demostra permís independent per a cada descàrrega ni per a executar una validació des d'una URL AJAX. El contingut exacte generat pel mètode `mostrarPage` no s'ha pogut inspeccionar en aquesta auditoria. | UC-116: separar dret a visualitzar l'apartat, dret a consultar prova i dret a aprovar/denegar la validació. |
| Canviar «SÍ/NO» i enviar decisió | [js/alumnes-validar-descomptes.js, L28–94](../../codi-drive/intranet-actual/js/alumnes-validar-descomptes.js#L28-L94) commuta les classes visuals de `.descompte-valid` i, en clicar `.validat`, envia GET `idInsc,verificat` a [sendMsgValidatCurosDescomptes.php](../../codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatCurosDescomptes.php#L1-L37). | L'endpoint recupera la sessió serialitzada i delega a `Intranet::sendMsgValidatCurosDescomptes(idInsc,verificat)`. **No s'ha recuperat el cos d'aquest mètode**; no deduir ni absència ni presència de comprovació de rol, modificació de `VALID_DESC`, càlcul del preu, escriptura d'auditoria o correu efectivament enviat. En el wrapper inspeccionat no hi ha comprovació explícita de rol per acció. | La decisió sobre dret i import s'ha de provar amb UC de validació/descompte; UC-116 manté evidència, autoritza consulta i enllaça a la decisió sense confondre-la amb el justificant custodiat. |
| Persistir un model futur d'evidències | [migració 000004, L105–128](../../sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql#L105-L128) defineix `discount_validation`; [000005, L79–99](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql#L79-L99) defineix `discount_evidence` amb `UUID_VALIDATION`, `STORAGE_REF`, `CONTENT_HASH`, `ACCESS_CLASSIFICATION`, `PURPOSE_CODE`, `RETENTION_POLICY_CODE`, `DELETE_AFTER`, `DELETED_AT` i `DELETION_PROOF_HASH`. | **SQL definit** no prova migració aplicada, bytes custodiats, hash verificat, autorització ni supressió real. `sif/src/Aeat/EvidenceStore.php` protegeix proves de trameses AEAT, NO és el servei de justificants de descompte; `DiscountSnapshotFileReader.php` només llegeix JSON de snapshot, NO valida ni custodia documents personals. | UC-116: `DiscountEvidenceService/Repository/ProtectedEvidenceStorage` a UML continuen DISSENY; no declarar-los PHP existent. |

## 2. Incidència de confidencialitat P0 — acció separada del refactor

**Comprovació de metadades:** l'arbre Git de `main` inclou **145 objectes PDF/imatge** sota `codi-drive/web-actual/ajax/carnets/`. Els noms observables suggereixen relació amb justificants de descompte o d'inscripció i incorporen patrons d'identificadors personals. **No s'ha obert cap document**, ni verificat la identitat de les persones o l'accés efectiu al servidor productiu.

**Risc tècnic observable:** si el repositori és públic, aquests bytes poden formar part del contingut descarregable o de l'historial Git; l'endpoint llegat també construeix URLs directes sota `/ajax/carnets/`. Encara que el servidor actual negui l'accés directe, no es resol la presència de còpies al repositori o als seus forks/clons.

**Accions immediates de contenció a acordar amb la persona responsable de seguretat/dades, sense esperar al SIF final:**
1. Restringir el repositori i el contingut, preservar només l'evidència mínima del fet sense ampliar-ne la difusió, comprovar qui té accés i valorar l'eliminació segura d'historial/còpies segons el protocol intern. No enviar URLs individuals de justificants en tiquets, correus o fitxes públiques.
2. Bloquejar la publicació directa de `/ajax/carnets/` al servidor i invalidar rutes/URLs antigues quan correspongui; verificar amb una prova d'accés no autenticat **sense difondre les dades**.
3. Retirar de les noves còpies de codi qualsevol document personal o de client; ignorar els directoris d'uploads i custodiar els originals en emmagatzematge privat controlat.
4. Inventariar referències del llegat (BD, correus i expedients) i definir migració sense perdre la possibilitat de verificar legítimament el dret ni sobreescriure els documents fiscals històrics.

**Estat:** incidència detectada per auditoria estàtica; **contenció, retirada, revocació, prova d'accessos i desplegament NO ACREDITATS**. No executar eliminacions destructives o reescriptura de l'historial sense pla i autorització.

## 3. Variants de negoci i dades a contrastar

| Variant | Què queda per acreditar | Contracte final a acordar |
| --- | --- | --- |
| Descompte alumni PrisMa / descompte automàtic | Elegibilitat per DNI/BD i si és necessari algun document; no inferir que es demana carnet. | Evitar recollir justificant si la regla no en requereix. |
| Carnet Jove | Font i comprovació de vigència en formulari/servei; circuit antic d'imatge si aplica. | Tipus de prova necessari, caducitat i revalidació d'accés. |
| Socials (família nombrosa, monoparental, discapacitat) | La pàgina pública demana document; no està contrastada la correspondència `TIPUS_DESC`→prova→rol→vigència i no s'ha de publicar el motiu personal en factura. | Evidència estrictament necessària i textos públics genèrics per categoria; accés només a personal autoritzat. |
| USOC | La pàgina descriu validació de l'afiliació per l'entitat; això no prova que calgui carregar un document al mateix endpoint. | Transferència/verificació mínima d'informació, sense barrejar la custòdia de proves alienes amb la factura o descomptes socials. |
| Grup/pagador empresa | Pagador, participant i receptor poden diferir; el justificant del participant no és una dada lliurable al responsable de grup per defecte. | Separar titularitat, rol revisor i text fiscal visible. |
| Document absent, il·legible, caducat, erroni, duplicat o substituït | No hi ha una política executada de versions de fitxer ni resolució d'error verificada. | Estats REBUDA / PENDENT / ACCEPTADA / DENEGADA / CUSTODIADA com a **conceptes proposats**, no enums existents. Guardar decisions i revisions sense exposar la prova anterior. |
| Prova aprovada abans/després de factura | No s'ha acreditat el mètode llegat de decisió ni l'ajust de preu; el justificant per si sol no acredita cobrament ni correcció fiscal. | Abans d'emetre: import/snapshot validat; després: classificar el canvi amb UC de rectificació si afecta obligació fiscal; mai editar factura emesa directament. |

## 4. Tasques de documentació i implementació verificables

| ID | DOC pendent | IMP pendent / criteri de prova |
| --- | --- | --- |
| UC116-D01 | Desglossar formulari concret d'origen: pàgina/URL, tots els apartats i camps, scripts JS, consentiment/necessitat per tipus i confirmació. La ruta PHP no acredita la pàgina que l'invoca. | Confirmar versió desplegada; no traspassar cegament paràmetres de fitxer, origen o identificador; prova d'injecció de valors invàlids en entorn aïllat. |
| UC116-D02 | Documentar apartat de validació de descomptes de la intranet: camps, consulta de proves, rols per acció, estat i missatges reals. Marcar `Intranet::sendMsgValidatCurosDescomptes` com **mètode no inspeccionat**. | Recuperar implementació real o reproduir-la en prova controlada; validar rol per consulta i per modificació al servidor; cap GET mutador ni acció delegada a un rol inadequat. |
| UC116-D03 | Decidir per `TIPUS_DESC` prova exigible, actor, finalitat, text públic, necessitat, rol, conservació i supressió, amb negoci i responsable de dades. | Només els tipus que realment necessitin prova permeten càrrega; no conservar documents personals en un camp de factura, email o snapshot bancari. |
| UC116-D04 | Mapar identitat: `ID_INSC`, persona beneficiària, regla/versió, `UUID_VALIDATION`, `UUID_EVIDENCE`, versió i decisió. | Servei SIF transaccional que vinculi la càrrega al registre comercial adequat sense fer `INSERT` de factura, CHARGE o dret validat automàticament. |
| UC116-D05 | Definir estats diferents per recepció, custòdia física, revisió/decisió, aplicació econòmica, notificació i eliminació. | Bytes en espai privat fora webroot i repositori; validació de tipus real/mida i contingut, nom opac, hash bytes, idempotència i recuperació d'error. |
| UC116-D06 | Matriu de permisos: aportant, alumne, revisor, administrador, empresa pagadora i procés de retenció. | Control backend per cada lectura/baixada/revisió i event d'accés; URL caducable o streaming autoritzat sense nom original/ID personal. |
| UC116-D07 | Política diferenciada de retenció justificants vs factura fiscal històrica i auditoria de supressió. | Worker de terminis i legal hold segons política aprovada; esborrat real de bytes/còpies controlades i prova; `DELETED_AT` sol no és evidència suficient. |
| UC116-D08 | Actualitzar UC-116 funcional, UML, activitats de pàgines i matriu acció→codi→BD→test; deixar la incidència de documents públics sense enllaços de fitxer. | Executar proves UC116-T01–T10 després del canvi; sense tancament per la sola presència d'SQL o diagrames. |

## 5. Proves d'acceptació proposades — NO EXECUTADES

| Test | Entrada/condició | Resultat esperat i evidència de sortida |
| --- | --- | --- |
| UC116-T01 | Document vàlid per una regla que efectivament requereix prova | Recepció + bytes privats + hash verificat + `UUID_VALIDATION` i evidència vinculada; cap factura/pagament creat per la càrrega. |
| UC116-T02 | Document amb MIME real/mida no admesos, nom/path maliciós o valor origen manipulat | Rebuig i cap fitxer accessible públicament ni error amb dades sensibles. |
| UC116-T03 | Usuari anònim o actor sense permís a upload o consulta | Rebuig del backend i cap accés als bytes encara que es conegui un identificador. |
| UC116-T04 | Empresa pagadora o alumne B demana prova personal de participant A | Denegació i auditoria de l'intent; factura pública/titular només amb text genèric necessari. |
| UC116-T05 | Operador autoritzat accepta/denega prova, i reintenta | Decisió atribuïda i versionada, idempotència i sense nova factura/càrrec. |
| UC116-T06 | Fallada entre guardar bytes i escriure metadades, i a la inversa | Incidència i reconciliació sense declarar `CUSTODIADA` ni perdre un original de manera silenciosa. |
| UC116-T07 | Mateix fitxer dos cops per la mateixa validació i per dues validacions legítimes diferents | Deduplicació per `UUID_VALIDATION+CONTENT_HASH` sense saltar permisos ni fusionar titulars diferents. |
| UC116-T08 | Revisió d'un PDF, correu, URL, export i log d'una factura amb descompte sensible | No s'hi exposen bytes/URL del justificant ni motiu personal no necessari. |
| UC116-T09 | Termini de retenció aplicable vençut | Supressió real i prova verificable de totes les còpies sota control segons política, sense esborrar factura immutable. |
| UC116-T10 | Revisió de seguretat del repositori públic i del servidor | Absència de justificants en versions publicades accessibles i accés directe a webroot denegat; historial/còpies tractats amb pla de seguretat. |

## 6. Diagrames i estat de tancament

[**Dos parells de diagrames d'activitat ACTUAL/FINAL**](uc-116-activitats-pagines-justificants-actual-final.md): recepció des de formulari web (aquest formulari/JS encara no identificat) i pàgina de validació de descomptes d'intranet (l'HTML generat dins `Intranet::mostrarPage` i el mètode que persisteix la decisió no inspeccionats). Els diagrames distingeixen activitat **observada** de punts desconeguts, no simulen permisos o decisions realment acreditades.

**No tancar UC-116:** falta el contrast de les pàgines/apartats complets RM-037, definició de variants/retenció i rol, inspecció del mètode `Intranet`, servei i emmagatzematge privat del SIF, integració real, conteniment del material sensible i execució de la suite.
