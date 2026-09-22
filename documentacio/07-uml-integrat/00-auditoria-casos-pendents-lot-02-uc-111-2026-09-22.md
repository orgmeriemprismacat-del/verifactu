# Auditoria de casos pendents · lot 02 · UC-111 docent novell i dret futur

**Data:** 22/09/2026. **Versió de codi contrastada:** \`main\`, commit \`e71958b3026549bde09fb4b25f2ec3ba370937ec\`. **Abast:** UC-111, amb dependències UC-14/20d/23/90/112/116/117, via alta web + aportació de document + revisió a intranet + emissió de dret futur. **Estat:** contrast parcial dirigit amb el codi versionat; NO prova de desplegament, base de dades, correus efectivament lliurats ni execució de tests. [Registre mestre](../00-index-i-pla/42-registre-mestre-cobertura-funcional-implementacio-documentacio.md) · [Fitxa UC-111](uc-111-docent-novell-dret-futur.md) · [Lot 01](00-auditoria-casos-pendents-lot-01-2026-09-22.md).

## 1. Matriu funcional: acció REAL → cas d'ús → diferència

| Acció identificada | Font actual verificable al codi | Relació amb UC i descobriment | Estat / canvi |
| --- | --- | --- | --- |
| Marcar «docent novell» en alta de curs | [web enviarInscripcio.php L36–69](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L36-L69) llegeix titulació i \`novell=yes\`; [L403–420](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L403-L420) prepara comunicació específica quan novell i \`TIPUS_DESC\` 0–3. | UC-111 no ha de fusionar «novell marcat» amb «titulat validat». En el cas especial \`JASOM\` hi ha persistència extra; cal identificar al formulari i al contracte quan aquesta opció existeix. | DOC: variants JASOM/altre curs, descompte simultani, camp i condició d'entrada. IMP: validació al backend, no només marca GET. |
| Crear inscripció originària i sol·licitud de docent novell | [enviarInscripcio.php L586–620](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L586-L620) insereix \`inscripcions\` i, **únicament si \`CURS='JASOM'\` i novell**, insereix \`recent_titulat(ID_INSC)\`. | La fitxa citava recent_titulat però no acreditava el disparador concret. **L'alta de recent_titulat no confirma ni titulació ni pagament ni dret futur**. | DOC: declarar separadament alta pendent, validació i pagament; descobrir si hi ha altres rutes legítimes per altres cursos. IMP: orquestrar estat per ID_INSC/operació, amb deduplicació transaccional. |
| Informar de la política comercial a la persona sol·licitant | [enviarInscripcio.php L403–420](../../codi-drive/web-actual/ajax/enviarInscripcio.php#L403-L420) comunica que la validació del títol precedeix el procés de reserva i les dades de pagament, i que després de confirmar títol i pagament rebrà un **codi de descompte «per valor de» l'import de curs exposat al correu**, aplicable al curs següent. | La fitxa deia «codi, saldo o altre dret per decidir»: el **canal documenta concretament un codi amb valor monetari promocional** en aquest text. No deduir d'això que els diners de l'origen es traspassin com a crèdit prepagat ni que aquesta política sigui vigent en tots els programes. | DOC: confirmar amb negoci import exacte, mínims, curs admissible, caducitat, acumulació, titular i regla si el curs futur és més barat; decidir si és un **cupó amb descompte** o realment saldo ja cobrat. IMP: regla versionada i snapshot sense alterar factura inicial. |
| Pujar justificació documental | [enviarImatgeSocRecentTitulat.php L13–39](../../codi-drive/web-actual/ajax/enviarImatgeSocRecentTitulat.php#L13-L39) rep any/curs/edició/documentació i fitxer per POST, concatena aquests camps per construir el nom, conserva l'extensió indicada pel nom d'origen i desa a \`resguards/\`. [L40–76](../../codi-drive/web-actual/ajax/enviarImatgeSocRecentTitulat.php#L40-L76) prepara comunicació que referencia ruta \`ajax/carnets/\` i dades de correu. | **No equival a discount_evidence protegida**. En el fitxer revisat no hi ha verificació visible de mida/MIME/permisos/fitxer relacionat amb el propietari ni persistència de hash i decisió. La carpeta usada en el MOVE i la carpeta referenciada al correu no coincideixen: verificar l'URL efectiva sense assumir que funciona o que és inaccessible. | DOC: custòdia, accés, retenció, equivalència entre \`resguards\` i \`carnets\`, classes de dades personals i rastre de decisió. IMP: autenticació i titularitat de la petició, nom aleatori opac, storage no públic, validació de contingut/tipus/mida, hash, retenció i descàrrega autoritzada. |
| Operació de gestió: marcar acceptat/rebutjat | [alumnes-validar-descomptes.php](../../codi-drive/intranet-actual/alumnes-validar-descomptes.php) carrega [JS L42–53](../../codi-drive/intranet-actual/js/alumnes-validar-descomptes.js#L42-L53): clic a \`.resguard-valid\` només canvia el Sí/No mostrat i la classe CSS. [JS L95–117](../../codi-drive/intranet-actual/js/alumnes-validar-descomptes.js#L95-L117) en clicar \`.validatResguard\` envia \`idInsc\` i \`verificat\` via GET. | Cal separar **botó de selecció visual** i **acció de validació real**. La fitxa no identificava els dos controls de la pantalla. Un missatge «Canvi aplicat» del JS no acredita per si sol la persistència, l'emissió del dret o el cobrament. | DOC: ID d'acció, classes i estat, actor/permís, alternatives, errors i prova de confirmació. IMP: acció servidor autenticada amb POST/CSRF o mecanisme equivalent, validació de rol/recurs, canvi auditat i resultat estructurat. |
| Delegació intranet al mètode de domini llegat | [sendMsgValidatProfessorNovell.php L13–25](../../codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatProfessorNovell.php#L13-L25) obre sessió, deserialitza objectes, llegeix GET i crida \`Intranet::sendMsgValidatCurosProfessorNovell($idInsc,$verificat)\`. | **Límit explícit:** \`Intranet.php\` (1,47 MB) no s'ha pogut recuperar per inspeccionar el cos del mètode amb la consulta disponible. Per això **NO** està acreditat en aquesta auditoria si aquí es comprova PAGAMENT, s'actualitza VALIDAT, s'emet el codi a \`promocions\` o s'envia el correu, ni en quin ordre. | DOC: obtenir i contrastar cos exacte del mètode i les crides SQL/SMTP abans de tancar el comportament ACTUAL; no substituir aquesta comprovació amb afirmacions d'un document derivat. IMP: orquestració conforme al resultat confirmat i a la regla aprovada. |
| Validació/consum del codi en compra futura | [obtenirDadesPromo.php L17–52](../../codi-drive/web-actual/ajax/obtenirDadesPromo.php#L17-L52) consulta \`promocions\` i retorna estat temporal, USED, DNI, curs, percentatge, tipus de càlcul, preus i acumulabilitat; el valor retornat és informatiu per al client, no un consum fiscal confirmat. | UC-20d/117 han de verificar **al servidor** titular, finalitat, vigència, disponibilitat/consum, import i concurrència en la compra final; una consulta GET no prova reserva/consum efectiu del dret. | DOC: traça del checkout de destinació i regla exacta dels camps; IMP: reserve/consume idempotent + factura amb snapshot correcte, sense crear CHARGE original nou. |

## 2. Decisions i variants que s'han de recuperar abans de tancar UC-111

1. **Abast real de la promoció:** l'alta a \`recent_titulat\` vista en aquest handler és condicional a \`JASOM\`. Cal identificar altres programes/edicions/rutes, si n'hi ha, i si la comunicació de l'avantatge s'emet també fora de \`JASOM\`.
2. **Moment de cobrament:** la frase del correu exigeix comprovació del pagament, però no s'ha verificat el cos de \`Intranet::sendMsgValidatCurosProfessorNovell\`. Distingir validació acadèmica anterior al pagament, creació del cupó i enviament del missatge; una factura PENDING o un botó «Sí» no són un ingrés.
3. **Naturalesa del benefici:** el correu del canal anomena codi de descompte per un import; el model proposa cupó, crèdit o altre dret. No convertir-lo automàticament en crèdit de diners cobrats: la decisió comercial/fiscal requereix prova documental i regla versionada. Comprovar si en la ruta llegadа hi ha camp percentatge \`promocions.PERCENTATGE\`, import fix, curs tipus, \`TIPUS_CALC\`, \`PREU\`, \`PREU_FIX\` i \`ACUM\`, i com es combina amb altres descomptes.
4. **Titular i receptor de compra futura:** propietari de promoció, destinatari de correu i receptor fiscal poden ser diferents. L'ús del codi per una altra persona ha de seguir política explícita, mai només identificar-se per email.
5. **Caducitat, ús parcial, import superior al preu futur, reús i devolució d'origen:** documentar regles existents vs. propostes, amb dates i edicions reals; no deduir que el dret es renova o es converteix en saldo bancari.
6. **Justificant i dades:** confirmar tipus de prova mínima, validació humana, accés d'operadors, ubicació real i retenció, especialment després d'un rebuig.
7. **Processos no visuals:** determinar si hi ha cron, callback o una acció manual que expedeixi \`promocions\`; la crida a intranet coneguda no basta per identificar-ne tots els escriptors.

## 3. Seguretat i privacitat detectades en el codi versionat

**ALERTA D'ACCÉS A JUSTIFICANTS · P0 fins a verificació de l'entorn.** L'arbre del **repositori públic** conté arxius de justificació en directoris \`codi-drive/web-actual/ajax/carnets/\` i \`.../resguards/\`; alguns noms incorporen identificadors personals. No reproduir noms, identificadors ni continguts en documents d'auditoria. Cal revisar immediatament la publicació de fitxers i el seu historial (esborrar un commit nou **no** garanteix eliminar còpies històriques), restringir l'accés als fitxers i validar-ne la política de retenció/avisos segons el responsable de privacitat. No s'ha inspeccionat si les còpies coincideixen amb contingut real desplegat; l'exposició al GitHub públic és un fet separat.

A l'endpoint de pujada revisat no es veuen validació segura de tipus/mida, autorització per document i denominació aleatòria opaca. El resultat de \`move_uploaded_file\` no acredita per si sol la validació de la titulació ni la custòdia confidencial; tampoc la creació de \`discount_evidence\`.

## 4. Diagrames d'activitat d'accions UC-111 (RM-037)

**Abast:** subfluxos de la pàgina d'inscripció i de l'apartat «recent titulat» de la pàgina de validació; **NO** diagrama complet de totes les accions de les dues pàgines. Marcar els estats del servidor llegat que no s'han pogut recuperar com a NO VERIFICATS.

### 4.1. Inscripció web — subflux actual observable

\`\`\`plantuml
@startuml
title UC-111 | Alta curs i sol·licitud docent novell | ACTUAL parcial
start
:Rebre dades d'inscripció, titulació i novell via GET;
:Construir missatge d'alta i imports;
if (novell i descompte tipus 0-3?) then (sí)
 :Preparar missatge amb validació del títol,
 reserva i codi futur després del pagament;
endif
:INSERT inscripcions (ID_INSC, IDPAG, imports, dades);
if (curs == JASOM i novell?) then (sí)
 :INSERT recent_titulat (ID_INSC);
else (no)
 :No inserir recent_titulat en aquesta branca;
endif
:Continuar tramitació i comunicacions del handler;
stop
@enduml
\`\`\`

### 4.2. Inscripció web — subflux objectiu pendent

\`\`\`plantuml
@startuml
title UC-111 | Alta i dret futur | OBJECTIU, no implementat
start
:Verificar actor, dades i elegibilitat de la promoció;
:Crear/reutilitzar operació i inscripció origen amb regla versionada;
if (Sol·licita docent novell?) then (sí)
 :Rebre prova via emmagatzematge restringit;
 :Registrar evidència i decisió pendent sense dret nou;
 if (Evidència validada per persona autoritzada?) then (sí)
  :Conservar decisió aprovada i traça d'actor;
  if (Cobrament REAL origen confirmat i conciliat?) then (sí)
   :Crear/reutilitzar UNA promoció comercial
   lligada a origen, titular i regla;
   :Enviar comunicació posterior al commit;
  else (no)
   :Esperar cobrament; no emetre promoció;
  endif
 else (no)
  :Denegar o deixar pendent justificació amb motiu;
 endif
endif
:No alterar factura fiscal de la compra original;
stop
@enduml
\`\`\`

### 4.3. Intranet · Validar descomptes · apartat docent novell — subflux actual observable

\`\`\`plantuml
@startuml
title UC-111 | Apartat intranet recent titulat | ACTUAL observable
start
:Obrir pàgina intranet i carregar mostrarMain.php;
:Visualitzar files de inscripcions_recent_titulat;
:Clicar indicador resguard-valid;
:Canviar classe CSS i text Sí/No al navegador;
if (Operador clica validatResguard?) then (sí)
 :Llegir ID_INSC i Sí/No visual;
 :AJAX GET sendMsgValidatProfessorNovell.php;
 :Endpoint obre sessió i delega a Intranet::sendMsgValidatCurosProfessorNovell;
 note right
  COS DEL MÈTODE NO VERIFICAT
  Canvi BD, generació de cupó,
  verificació bancària i correu
  no acreditats en aquest lot.
 end note
 if (Resposta HTML inclou "error"?) then (sí)
  :Mostrar modal d'error;
 else (no)
  :Mostrar modal Canvi aplicat;
 endif
endif
stop
@enduml
\`\`\`

### 4.4. Intranet · Validar descomptes · apartat docent novell — subflux final pendent

\`\`\`plantuml
@startuml
title UC-111 | Apartat intranet docent novell | OBJECTIU, no implementat
start
:Carregar justificants del titular autoritzat;
if (Operador té rol i abast per validar?) then (no)
 :Denegar accés i registrar intent;
 stop
else (sí)
 :Mostrar informació mínima i estat del cobrament origen;
 :Seleccionar aprovar/rebutjar amb motiu i confirmació;
 :POST segur amb CSRF o equivalent i idempotència;
 :Servidor comprova permís, titularitat, versions i evidència;
 if (Decisió aprovada?) then (sí)
  :Persistir validació acadèmica i actor;
  if (Pagament confirmat i dret no emès?) then (sí)
   :IssueOrReuse promoció una sola vegada;
  else (no)
   :Deixar dret pendent o recuperar existent;
  endif
 else (no)
  :Persistir denegació motivada sense promoció nova;
 endif
 :Notificar el resultat real segons estat posterior al commit;
 :Actualitzar pantalla amb estat retornat pel servidor;
endif
stop
@enduml
\`\`\`

## 5. Proves proposades (NO EXECUTADES)

| ID | Variant i precondició | Resultat exigible |
| --- | --- | --- |
| DN-111-01 | Alta JASOM amb \`novell=yes\` i sense justificació | Una inscripció i sol·licitud pendent, cap dret futur per simple alta. |
| DN-111-02 | Alta en curs diferent de JASOM amb \`novell=yes\` | Regla explícita del curs; no atribuir alta a \`recent_titulat\` per aquest handler si no es crea; informar coherentment. |
| DN-111-03 | Evidència aprovada i factura d'origen PENDING sense ingrés | Cap dret futur; conservar decisió i esperar cobrament real. |
| DN-111-04 | Evidència aprovada + un ingrés real + dues notificacions/reintents | Un sol dret i un sol missatge efectiu o reintent de lliurament; cap segon CHARGE. |
| DN-111-05 | Dues peticions de validació de la mateixa inscripció amb decisió contradictòria | Conflicte/revisió, amb actor i versions; cap emissió duplicada. |
| DN-111-06 | Justificant invàlid, aliè o massa gran i URL sense permís | Rebuig, sense publicació ni dret, evidència d'accés auditat. |
| DN-111-07 | Codi presentat per titular aliè, caducat o ja consumit | Rebuig sense exposar dades personals ni emetre factura contradictòria. |
| DN-111-08 | Codi de valor monetari superior al preu futur o combinat amb altres promocions | Resultat d'acord amb regla comercial **aprovada i versionada**; cap import negatiu o reemborsament automàtic. |
| DN-111-09 | Compra d'origen retornada abans/després d'usar cupó | Decisió de reversió registrada, sense reescriure factures, sense invalidar silenciosament una compra posterior ja emesa. |
| DN-111-10 | Missatge de validació no lliurat després de persistir el dret | Reintentar només comunicació; no generar una segona promoció. |
| DN-111-11 | Rol sense permís fa GET llegat o POST nou de validació | Cap canvi d'estat ni lliurament indegut, fins i tot coneixent ID_INSC. |
| DN-111-12 | Repositori públic conté documents identificatius i es retira de branca visible | Verificar control d'accés i tractament de l'historial, sense incorporar-los als tests ni al log. |

**Porta de tancament UC-111:** obtenir el cos del mètode llegat i els escriptors efectius de \`promocions\`, confirmar política d'import/titular/vigència, tancar custòdia de justificants, completar adaptador SIF i traça web/intranet, validar totes les variants, executar proves amb evidència i comprovar entorn desplegat. Fins llavors: **COBERTURA DOCUMENTAL PARCIAL / CORE DE DRET FUTUR NO ACREDITAT / TESTS NO EXECUTATS**.
