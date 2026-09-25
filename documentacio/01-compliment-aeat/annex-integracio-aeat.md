# Integració AEAT: protocol, operació i evidències

Revisió: 2026-09-24. Candidata local, **NO-GO productiu**. Aquest annex descriu
el codi disponible i les proves locals; no acredita cap presentació real a AEAT.

## 1. Fonts i esquemes congelats

- [AEAT: esquemes oficials](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/informacion-tecnica/esquemas.html).
- [AEAT: descripció dels serveis web, 1.0.3](https://sede.agenciatributaria.gob.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_Descripcion_SWeb.pdf).
- [AEAT: especificació de huella, 0.1.2](https://www.agenciatributaria.es/static_files/AEAT_Desarrolladores/EEDD/IVA/VERI-FACTU/Veri-Factu_especificaciones_huella_hash_registros.pdf).
- [AEAT: modalitat VERI*FACTU](https://sede.agenciatributaria.gob.es/Sede/iva/sistemas-informaticos-facturacion-verifactu/preguntas-frecuentes/sistemas-verifactu.html).

`sif/resources/aeat/manifest.json` identifica origen i SHA-256 de les còpies
locals d'XSD/WSDL. `XmlCodec` resol exclusivament les dependències locals
permeses, també la de W3C, i valida peticions i respostes sense descarregar
esquemes durant l'enviament. Un canvi d'esquemes requereix revisió del manifest
i regressió. Validar XSD no substitueix les validacions de negoci d'AEAT.

El constructor de `XmlCodec` i el preflight comproven els cinc hashes amb
`SchemaManifest`; una còpia absent o alterada bloqueja la validació.
Les còpies es normalitzen a UTF-8 sense BOM i finals LF, fixats a
`.gitattributes`; el manifest identifica aquests bytes locals normalitzats.
Aquesta comprovació detecta inconsistències, però no autentica un paquet
si algú substitueix alhora els fitxers i el manifest.

## 2. Generació i correccions

`RegistrationSnapshot` genera el bloc `aeat` dins la transacció d'emissió,
amb el bloqueig de `fiscal_chain_state`. Número, data, tipus, receptor i totals
provenen de l'emissió; la classificació fiscal i la identificació del sistema
han d'arribar del backend mitjançant `aeat_fields` i `aeat_header`.
No s'han d'exposar aquests blocs com a camps editables del navegador.

| Operació | Registre XML | Marques |
| --- | --- | --- |
| Alta inicial | `RegistroAlta` | Sense subsanació ni rebuig previ |
| Subsanació ordinària | `RegistroAlta` complet | `Subsanacion=S`, `RechazoPrevio=N` |
| Alta inicial rebutjada | `RegistroAlta` complet | `Subsanacion=S`, `RechazoPrevio=X` |
| Subsanació prèvia rebutjada | `RegistroAlta` complet | `Subsanacion=S`, `RechazoPrevio=S` |
| Anul·lació ordinària | `RegistroAnulacion` | `SinRegistroPrevio=N`, `RechazoPrevio=N` |
| Anul·lació prèvia rebutjada | `RegistroAnulacion` | `SinRegistroPrevio=N`, `RechazoPrevio=S` |
| Anul·lació sense alta registrada a AEAT | `RegistroAnulacion` | `SinRegistroPrevio=S`, `RechazoPrevio=N` |

El mode intern `SIN_REGISTRO_PREVIO` de subsanació és un àlies heretat del
cas d'alta rebutjada amb `RechazoPrevio=X`; no és una etiqueta XML d'alta.
Només s'ha d'usar quan s'hagi acreditat el rebuig de l'alta inicial. Una
absència de resposta o un timeout no acrediten que AEAT no tingui el registre.

La subsanació conserva la identitat NIF emissor/número/data i exigeix
`corrected_fields` complets. No canvia la factura emesa ni substitueix una
rectificativa quan aquesta sigui necessària. L'operador autoritzat ha de
classificar la correcció fiscal abans de confirmar-la. Els CLI de
preview/process admeten `--corrected-fields=FITXER_JSON` amb l'objecte
complet de camps del registre corregit (imports decimals com a strings),
`--subsanation-kind=SUBSANACION_RECHAZADA` i
`--cancellation-mode=NORMAL|RECHAZO_PREVIO|SIN_REGISTRO_PREVIO`.
El parser compartit rebutja opcions desconegudes/repetides, selectors
ambigus, URLs i JSON malformat, buit o superior a 1 MiB.

Exemple orientatiu de preview (els valors han de provenir de la revisió real):

```text
php sif/scripts/preview-fiscal-record.php --type=SUBSANACIO --uuid-factura=UUID --reason=ERROR_REGISTRE --reference=REF_UNICA --subsanation-kind=SUBSANACION --corrected-fields=correccio.json
```

La confirmació usa el mateix conjunt d'arguments a `process-fiscal-record.php`.
Cap dels dos scripts remet a AEAT; la confirmació crea registre i cua.
El preview retorna `xml_preview` validat contra XSD sense escriure a la BD,
i reutilitza les regles de transició de la confirmació. `advisory_only=true`
indica que hora i ordre fiscal són provisionals: la confirmació els genera
de nou sota bloqueig i revalida l'estat. Un reús idempotent retorna
`existing_result` i no proposa un nou XML. No és un token de confirmació UI.

La reutilització d'una referència exigeix el mateix hash de petició, inclosa
la factura destinatària. Canviar dades amb la mateixa referència retorna
conflicte. Una anul·lació rebutjada pot generar un registre posterior, amb
una referència nova i mode de rebuig; no s'edita el registre rebutjat.

## 3. Huella, cadena i XML

`RecordHash` calcula SHA-256 hexadecimal en majúscules sobre els camps i
l'ordre oficials. La data/hora amb fus i la huella queden congelades quan
es genera el registre. L'encadenament AEAT apunta al registre immediatament
anterior generat globalment, encara que sigui d'una altra factura.

La huella AEAT viu a `PAYLOAD_JSON.aeat.record.Huella`; `HASH_FACT` i
`HASH_FACT_ANT` continuen essent la cadena interna de digests JSON del prototip.
No són camps intercanviables. Les cues antigues sense snapshot AEAT es
rebutgen per al transport. No es barregen cadenes de prototip i oficials ni
es recalculen registres antics silenciosament. La qualificació ha d'emprar
una instal·lació de proves buida o un procediment de transició revisat.

`XmlCodec` construeix SOAP 1.1 amb namespaces oficials, escapament de text,
ordre dels elements derivat de l'XSD i límit de mida. Rebutja DTD i entitats
externes. L'ordre de claus retornat per MySQL JSON no altera l'XML del retry.

## 4. Certificat i signatura

La candidata és exclusivament VERI*FACTU. El certificat client autentica la
connexió TLS amb AEAT. La signatura XAdES dels registres no forma part
d'aquesta modalitat implementada; tampoc es confon amb la signatura de la
declaració responsable.

`ClientCertificate` admet PKCS#12 amb clau privada, comprova contrasenya,
vigència i correspondència de clau/certificat. Rebutja fitxers dins el
repositori. Aquestes comprovacions locals no acrediten confiança d'AEAT,
revocació ni facultats de representació.

El desplegament ha de situar certificat i evidències fora de qualsevol
webroot, amb ACL de l'usuari del worker. El codi comprova exclusió del
repositori, però no pot descobrir tots els webroots del servidor. En Windows,
els modes Unix de fitxer no substitueixen les ACL. Cal verificar-ho al hosting.

Secrets: `SIF_AEAT_CERT_PATH` i `SIF_AEAT_CERT_PASSWORD`, injectats de manera
protegida. Evidències: `SIF_AEAT_EVIDENCE_DIR`; CA, si cal:
`SIF_AEAT_CA_FILE`. No incloure secrets al manifest, captures o logs.
L'`IdSistemaInformatico` XML té longitud màxima de dos caràcters: el codi
documental `SIF-PRISMA` no serveix com a valor d'aquest camp. `PM` només és
el valor de les fixtures; cal confirmar l'identificador real de la candidata.

## 5. Worker, retries i respostes

Punt d'entrada: `sif/scripts/run-aeat-worker.php --send-test`, exclusivament
CLI amb `SIF_ENV=preproduction`. El transport admet només l'endpoint oficial
de proves fixat a `SoapTransport::TEST_ENDPOINT`, verifica TLS, no segueix
redireccions i aplica timeouts. No s'ha habilitat producció.

`SerialWorker` conserva un lock MySQL durant el cicle complet i processa una
entrada per invocació. `aeat_worker_state.NEXT_SEND_AT` persisteix l'espera
global: respecta `TiempoEsperaEnvio` amb un mínim conservador de 60 segons.
La migració `2026_09_23_000008_add_aeat_flow_control.sql` és additiva.

Abans del transport es desa una espera preventiva. Si el transport llança
una excepció o retorna un temps d'espera invàlid, es torna a desar un mínim
de 60 segons des del final de l'intent; el temps consumit en la petició no
es descompta d'aquesta espera posterior. Un worker reiniciat respecta
aquest termini encara que el retry individual ja estigui vençut.
Un tancament abrupte del procés conserva el termini preventiu i requereix
la recuperació explícita del lock antic; no executa el tractament d'excepció.

El processador manté retries exponencials persistents amb `NEXT_RETRY_AT`
(60 segons de base, límit 3.600, tres intents per defecte). Cada retry usa
el mateix snapshot. La recuperació explícita de locks antics usa un llindar
de 900 segons; un intent esgotat passa a dead-letter i requereix revisió.
Una entrada pendent d'espera, en processament o dead-letter impedeix
avançar la cua serial. No invocar el processador genèric en paral·lel al worker.

Verificació local de recuperació (2026-09-24): amb dues connexions MySQL
independents, un worker que rep `WORKER_BUSY` no recupera ni reclama
registres, encara que el lock de la fila sigui antic. Després d'alliberar
el lock global, la recuperació explícita permet reprendre un intent amb
pressupost disponible. Si ja té tres intents (límit per defecte), passa
a `DEAD_LETTER`, obre `AEAT_DEAD_LETTER` i bloqueja el registre següent
sense cridar el transport. Aquesta simulació no substitueix la prova de
caiguda i recuperació al servidor real.

`ResponseParser` valida XSD, emissor, identitat, operació i marques de
correcció. L'estat de línia és el que determina `ACCEPTED`,
`ACCEPTED_WITH_ERRORS` o `REJECTED`. `SENT` a la cua significa que hi ha
resposta fiscal processada, no necessàriament acceptació. Un duplicat no
es converteix automàticament en acceptació. Errors i advertiments generen
revisió; un rebuig fiscal requereix correcció, no reenviament cec.

El processador propaga `requires_review` al resultat del cicle si la resposta
ho indica, conté `duplicate=true` o l'estat no és `ACCEPTED`.
El worker obre una incidència `AEAT_REVIEW` també quan una resposta
acceptada porta aquesta marca. Conserva l'estat retornat i la resposta
completa; la incidència no provoca un reenviament automàtic del registre.

Els estats i la resposta estructurada queden a `factura_registres`; la factura
manté el resum AEAT. CSV, error, espera, hash de resposta i ID d'evidència són
al JSON de resposta. La cua també rep `AEAT_CSV`, `AEAT_ERROR_CODE`,
`AEAT_ERROR_MESSAGE` i `FLOW_WAIT_SECONDS`. El resum d'error limita la
longitud a 500 caràcters UTF-8; el JSON i l'evidència conserven el text complet.
`FIRST_SENT_AT` i `LAST_SENT_AT` encara no tenen mapatge complet d'intents:
les hores per intent s'han de consultar a l'evidència privada.

El planificador real del hosting encara s'ha de configurar i provar. Ha
d'invocar una sola vegada per interval curt, respectant el resultat `WAIT`,
supervisar incidències i avisar la responsable quan sigui necessària una
actuació. No s'ha creat cap tasca programada a aquest ordinador.

## 6. Expedient i qualificació externa

`EvidenceStore` crea un directori diferent per intent, amb `request.xml`,
`request.json`, `response.xml`, `response.json` i, quan correspon,
`failure.json`. La petició es desa abans d'enviar; la resposta bruta abans
de classificar-la. Fitxers creats en mode exclusiu, amb SHA-256 i flush.
El sistema de fitxers necessita permisos, backup i retenció: no és WORM.

Verificació local de només lectura:

```text
php sif/scripts/verify-aeat-evidence.php --directory=RUTA_PRIVADA --attempt=ID_INTENT
```

Comprova les parelles XML/JSON i els hashes de petició/resposta, rebutja
enllaços i rutes fora de l'intent, i no mostra el contingut fiscal.
Retorna `RESPONSE_RECORDED` (sortida 0), `INCOMPLETE`,
`FAILED_ATTEMPT` o `INVALID` (sortida 1).
La integritat no acredita autenticitat, acceptació ni presentació AEAT:
`aeat_acceptance_verified` sempre és fals. Cal correlacionar la resposta
real i el registre fiscal en la qualificació externa.

Cada prova externa ha de registrar: ID de cas, paquet/hash, migracions,
manifest XSD, configuració no secreta, data, operador, empremta del certificat,
ID d'intent, hash de petició/resposta, resultat esperat/obtingut, CSV real
quan AEAT l'emeti, comprovació de BD i incidència si no passa.

| Cas bloquejant | Evidència requerida a preproducció |
| --- | --- |
| Alta acceptada | XML/XSD, resposta real i correlació amb registre immutable |
| Subsanació i alta rebutjada | Dades completes, marques correctes i nova huella/cadena |
| Anul·lació normal, sense registre i després de rebuig | Resposta real per a cada variant aplicable |
| Acceptació amb errors i rebuig | Estat de línia, codi, incidència i resolució auditada |
| Timeout després de remissió i duplicat | Mateix XML, intents conservats i conciliació abans d'acceptar |
| Reinici i concurrència | Un sol enviament actiu, recuperació de lock i espera persistent |
| Certificat caducat, incorrecte o renovat | Bloqueig o connexió validada, sense exposar secrets |
| Recuperació | Restauració de registres, cua, esperes i evidències coherents |

Proves locals: `php sif/scripts/test-aeat-protocol.php` (protocol/seguretat) i
el mateix comandament amb `--integration` sobre una BD `sif_test_*`
prescindible amb `SIF_ENV=test`. La segona opció buida taules de la BD indicada.
Les fixtures i el certificat autoemès de test no s'envien a AEAT. El CSV
`TEST-CSV-NOT-AEAT-EVIDENCE` no acredita cap presentació.

Pendent per tancar: certificat/apoderament real, ACL i servidor, classificació
fiscal validada de tots els canals, conciliació operativa dels duplicats,
planificador/alertes, proves externes i portes G1..G7. La declaració continua
en esborrany i no es pot considerar signable per haver superat proves locals.

## 7. Resultat de verificació local del 2026-09-23

PHP 8.4.25 i MySQL 8.4.10, BD nova sif_test_aeat_review_20260923:
15 proves específiques AEAT correctes i regressió completa de **363 proves,
cap fallada**. Lint de 20 fitxers revisats correcte. Logs i manifest a
sif/var/evidence/2026-09-23-aeat-review-*. Cap hash dels fitxers verificats
ha canviat durant la regressió final.

El preflight d'aquell tall retornava ready_to_send=false: cURL no estava
activat i faltava certificat usable/configuració privada. En la continuació
del mateix dia s'ha activat cURL al PHP portable local. El certificat i la
configuració externa continuen pendents. L'execució és local/offline;
no hi ha resposta, CSV ni evidència real d'AEAT.


## 8. Continuació del 2026-09-23: CLI i resum de resposta

Completats els arguments de correcció, preview XML/XSD sense escriptures,
validació de transicions compartida i projecció de CSV/error/espera a la cua.
cURL activat al PHP portable local; no hi ha canvis al PHP global.

Resultat: 23 proves AEAT específiques i **371 proves de regressió correctes,
cap fallada**. Lint de 13 fitxers correcte, manifest de codi estable.
Evidències locals: sif/var/evidence/2026-09-23-aeat-cli-*.

El preflight continua ready_to_send=false, amb curl_extension=true,
certificate_usable=false i evidence_directory_private=false. La configuració
real de certificat, ACL, servidor, planificador i qualificació externa
continua pendent. No s'ha fet cap enviament AEAT.

## 9. Integritat local — 2026-09-24

Verificació final 2026-09-24: **374 passed, 0 failed** a la BD aïllada
sif_test_aeat_review_20260923; cap canvi dels hashes de codi durant la regressió.
Logs locals a sif/var/evidence/2026-09-24-aeat-integrity-regression.log,
2026-09-24-aeat-integrity-preflight.json i
2026-09-24-aeat-integrity-source-manifest.json. Les 26 proves específiques
consten a 2026-09-23-aeat-integrity-tests.log. Cap enviament AEAT ni commit/push.
