# UC-108 — Auditoria consolidada i matriu de traçabilitat

**Data:** 29/09/2026  
**Abast:** fitxa funcional, PHP/JS versionat, P-TAS-01…04, entrada Home, classes, seqüències, activitats, mailing, confirmació i fronteres amb campus/UC-125.  
**No acreditat:** runtime, BD real, desplegament productiu, Moodle, SMTP real i execució de proves.

## 1. Convenció d'estat

- **Documentat:** consta en fitxa/UML.
- **Implementat/observat estàticament:** existeix al codi versionat.
- **Verificat:** requereix execució o evidència externa; no s'utilitza per inferència.
- **Pendent:** falta decisió, implementació o prova.

## 2. Decisions vigents

| Decisió | Estat |
|---|---|
| 24–48 hores laborals per alta manual de secretaria | ACORDADA |
| Accés 7 dies exactes des de l'activació efectiva, mateixa hora | ACORDADA |
| Sense convocatòries/edicions pròpies; alta contínua mentre tastet actiu | ACORDADA |
| Pendent bloqueja duplicat | ACORDADA |
| Actiu bloqueja duplicat | ACORDADA |
| Caducat requereix desbloqueig persona+tastet i nova alta web | ACORDADA |
| Baixa/denegació permet nova alta web conservant historial | ACORDADA |
| Mailing obligatori associat al tastet gratuït; baixa posterior possible | ACORDADA |
| Retry de la mateixa petició no pot reactivar mailing després d'una baixa | ACORDADA |
| Tastet A → baixa → tastet B | OBERTA |
| Secretaria activa Moodle manualment en la fase actual | ACORDADA |
| Nou compte Moodle: correu automàtic de credencials; compte existent: conserva claus | ACORDADA |
| Incidència claus: secretaria → Isa → desenvolupament | ACORDADA |
| Incidència que impedeix entrar: nova setmana completa des de resolució | ACORDADA |
| Mateixa plantilla manual per avís inicial/pròrroga, adaptada | ACORDADA funcionalment; ubicació/text no acreditats al repo |
| Tracker específic UC-108 | NO, fora del disseny actual |
| Automatització Moodle | 2027, fora d'abast actual |
| DEC-108-06: SIF FREE_SAMPLE vs fora SIF | OBERTA |
| DEC-108-01: política exacta token/consulta confirmació | OBERTA |

## 3. Inventari documental

### Existents i mantinguts

- `documentacio/06-fitxes-funcionals/uc-108.md`
- `documentacio/07-uml-integrat/uc-108-tastet-repte-gratuit.md`
- `documentacio/07-uml-integrat/uc-108-activitats-pagines-tastets-actual-final.md`
- `documentacio/07-uml-integrat/uc-108-vistes-grafiques-activitats-pagines.md`
- `documentacio/07-uml-integrat/uc-125-consentiment-comunicacions-separat.md`

### Creats en aquesta auditoria

- `uc-108-classes-actual-final.md`
- `uc-108-sequencies-actual-final.md`
- `uc-108-auditoria-tracabilitat-2026-09-29.md`

## 4. Entrada i pàgines

| ID | Superfície | Estat |
|---|---|---|
| EN-108-HOME | Home/carrusel de tastets | Implementat; punt d'entrada compartit |
| P-TAS-01 | /tastets | Implementat |
| P-TAS-02 | /tastets/{slug} | Implementat |
| P-TAS-03 | /inscripcions/tastets/{slug} | Implementat |
| P-TAS-04 | /tastets/confirmacio/{slug}/{token} | Implementat |

La Home no es compta com cinquena pàgina pròpia del UC-108; es documenta com a punt d'entrada.

## 5. Troballes consolidades

| ID | Resum | Estat |
|---|---|---|
| UC108-P01-01 | P-TAS-01 depèn del curs original | Confirmada estàticament |
| UC108-P01-02 | ús de `$this->nivells` no declarat al card | Confirmada estàticament |
| UC108-P01-03 | modal d'avisos de llistat no acredita alta UC-108 | Confirmada |
| UC108-P02-01 | detall depèn del curs original; pot fallar si està inactiu | Confirmada |
| UC108-P02-02 | `Tastet` fixa `estat=1` sempre | Confirmada |
| UC108-P02-03 | recurs inexistent/inactiu pot acabar en error 5300 | Confirmada estàticament |
| UC108-P03-01 | `InscripcioTastet` calcula estat=0 però `mostrar()` l'ignora | Confirmada |
| UC108-P03-02 | `obtenirCodiTastet.php` no té contracte net de no trobat | Confirmada |
| UC108-P03-03 | codi de tastet final controlat pel request client | Confirmada |
| UC108-P04-01 | confirmació construeix `Imatge`/`Curs` innecessaris | Confirmada |
| UC108-P04-02 | slug visible no es valida contra el token/registre | Confirmada |
| UC108-ENTRY-01 | Home és entrada real; defecte d'URL d'imatge al carrusel | Confirmada |
| UC108-RACE-01 | `codiCurs` es resol asíncronament amb botó ja actiu | Confirmada |
| UC108-API-01 | resposta d'alta és token textual no estructurat | Confirmada |
| UC108-API-02 | sortides posteriors poden contaminar la resposta-token | Confirmada |
| UC108-FLOW-01 | DB + mailing + SMTP sense coordinació/idempotència | Confirmada |
| UC108-FLOW-02 | correus interns poden precedir l'INSERT | Confirmada |
| UC108-MAIL-03 | `PHPMailer::send()` false ignorat | Confirmada |
| UC108-MAIL-04 | P-TAS-04 pressuposa correu sense estat de lliurament | Confirmada |
| UC108-MAIL-05 | `templates/mailings/tastet-*` són promocionals, no accés | Confirmada |
| UC108-MAIL-06 | plantilla manual d'accés no identificada al repo revisat | Confirmada documentalment |
| UC108-MAILING-01 | mecanisme tècnic de baixa posterior no acreditat | Pendent |
| UC108-MAILING-02 | UC-108 fa alta directa a `mailing`; flux general usa `subscriptors` + confirmació | Confirmada |
| UC108-CONF-01 | client/servidor discrepen pel retall de 16 caràcters del token | Confirmada al snapshot; runtime pendent |
| UC108-SEC-02 | GET mutador públic sense protecció de replay/idempotència acreditada | Confirmada com a risc estàtic |
| UC108-SEC-03 | token sense política temporal explícita | Confirmada |
| UC108-SEC-04 | resolució auxiliar usa SQL concatenat | Confirmada |
| UC108-HTTP-01 | PII via GET | Confirmada |
| UC108-HTTP-02 | `missatgeError()` registra `REQUEST_URI` completa | Confirmada |
| UC108-VAL-01 | validacions client inconsistents NIF/NIE/email | Confirmada |
| UC108-ID-01 | tipus document no arriba al servidor; `USUARI_MDL` redueix a dígits | Confirmada |
| UC108-DATE-01 | `DATA_INSC=CURRENT_TIME`; tipus d'esquema no acreditat | Pendent de BD |
| UC108-COPY-01 | formulari diu 24/48 hores; altres punts diuen laborals | Confirmada |
| UC108-LEG-01 | codi postal residual sense camp actual | Confirmada |
| UC108-LEG-02 | promocions residuals / variables sense origen | Confirmada |
| UC108-DATA-02 | `$comentaris` pot usar-se sense inicialització | Confirmada |
| UC108-DATA-03 | P-TAS-04 recupera DNI sense usar-lo | Confirmada |
| UC108-ACA-01 | fila web no acredita compte/matrícula/accés Moodle | Confirmada com a frontera |
| UC108-UML-01..07 | mancances de classes/seqüències/decisions als UML | Corregides documentalment amb fitxers dedicats |
| UC108-TR-01..07 | divergències de commit/Home/mailing/SIF/edició/venciment | Corregides o explicitades en aquesta revisió |

## 6. Proves proposades

Es conserven TG-108-01…84 definides durant l'auditoria. Prioritat de regressió abans de tancar implementació:

- duplicat concurrent i retry després de timeout;
- codi de tastet manipulat o encara no resolt;
- tastet desactivat entre render i submit;
- SMTP fallit amb INSERT correcte;
- confirmació/token legítim, manipulat i caducat;
- curs original inactiu;
- mailing: alta inicial, baixa, retry mateix request i cas A→baixa→B;
- 7 dies exactes des d'activació/resolució;
- credencials noves vs usuari existent.

## 7. Matriu de cobertura documental final

| Peça | ACTUAL | FINAL | Estat després d'aquesta revisió |
|---|---:|---:|---|
| Fitxa funcional | Sí | Sí | Actualitzada |
| Activitats P-TAS-01…04 | Sí | Sí | Existents; decisions sincronitzades |
| Vista gràfica resum | Sí | Sí | Existents; decisions sincronitzades |
| Classes | Sí | Sí | **Creat fitxer dedicat** |
| Seqüència alta web | Sí | Sí | **Creat fitxer dedicat** |
| Seqüència secretaria/campus | N/A al web | Sí | **Creat fitxer dedicat** |
| Seqüència incidència credencials | N/A al web | Sí | **Creat fitxer dedicat** |
| Entrada Home | Sí | Sí | Traçada com EN-108-HOME |
| Matriu troballes | Sí | Sí/pendent | **Aquest document** |

## 8. Criteri de tancament

**Documentació** es pot considerar coherent quan els documents enllaçats reflecteixen aquestes decisions vigents.  
**Implementació** no es considera tancada fins que els canvis PHP/JS/BD necessaris s'hagin implementat i les proves s'executin.  
**Producció** no s'infereix del repositori `codi-drive`.
