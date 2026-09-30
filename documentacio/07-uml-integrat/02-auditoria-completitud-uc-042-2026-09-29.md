# Auditoria de completitud — UC-042 — Consulta / Modifica alumne

**Data de tall:** 2026-09-29  
**Branca original auditada:** `audit/uc-042-completitud-2026-09-29`  
**Branca de reintegració:** `integrate/uc-042-main-reintegrada-2026-09-30`  
**Estat:** `DOCUMENTACIO_COMPLETA_AL_TALL` · `SIF_PARCIAL_IMPLEMENTAT` · `ADAPTADOR_INTRAnet_PENDENT` · `PROVES_ENTORN_PENDENTS`

## 1. Objectiu

Consolidar en un únic punt què existeix realment per UC-042, què és només disseny FINAL i quins artefactes faltaven. La pantalla llegada `/alumnes/mostrar-alumne/` continua sent la font ACTUAL de consulta i mutació, però no es considera una frontera segura del SIF només perquè tingui sessió o controls de navegador.

## 2. Inventari d'artefactes

| Artefacte | Estat | Observació |
| --- | --- | --- |
| [Fitxa funcional UC-042](../06-fitxes-funcionals/uc-042.md) | ACTUALITZADA | Inventari de pantalla, subcasos, proves i límits de domini. |
| [UML integrat UC-042](uc-042-consultar-modificar-alumne.md) | ACTUALITZAT | Classes ACTUAL/SIF parcial/FINAL i seqüències ACTUAL/SIF parcial/FINAL. |
| [Auditoria visual](00-captures-auditoria-alumnes-consulta-modifica-2026-09-22.md) | EXISTENT | 26 diagrames ACTUAL/FINAL de pàgina i subfluxos. |
| [Tancament documental de pantalla](01-tancament-documental-pantalla-alumnes-consulta-modifica-2026-09-23.md) | EXISTENT | AL-01–24 + AL-25 backend sense botó acreditat. |
| [Diagrames generals de classes](../04-estat-final/31-diagrames-classes-sif.md) | ACTUALITZAT | Subvista UC-042. |
| [Diagrames generals de seqüència](../04-estat-final/32-diagrames-sequencia-sif.md) | ACTUALITZAT | Consulta/proposta SIF i frontera d'aplicació. |
| [Matriu de traçabilitat dirigida](00-matriu-traçabilitat-accions-revisades.md) | ACTUALITZADA | Files explícites UC-042. |
| [Matriu de cobertura del catàleg](00-matriu-cobertura-cataleg.md) | ACTUALITZADA | UC-42 passa de DISSENY a PARCIAL. |

## 3. Codi ACTUAL llegat acreditat

- `codi-drive/intranet-actual/alumnes-mostrar-alumne.php` carrega el JS minificat `alumnes-mostrar-alumne.min.js?ver=1.4`.
- `js/alumnes-mostrar-alumne.js` conté cerca, edició de perfil, modal d'inscripció, pagament, observacions, factura i certificat.
- `ajax/alumnes/guardarDadesPersonals.php` i `Intranet::guardarDadesPersonals_resultatCerca()` muten directament una fila d'`inscripcions`.
- `guardarDadesPersonals_ConsultaInformacio.php` permet un UPDATE mixt amb camps de perfil, estat acadèmic, mailing, certificat i baixa.
- `guardarDadesPagament_ConsultaInformacio.php` modifica resum econòmic llegat.
- `guardarEnviarDadesPagament_ConsultaInformacio.php` desa primer i envia després.
- `afegirObservacio.php` / `amagarObservacio.php` treballen sobre `aobservacions`.
- `eliminarArxiu.php` rep un nom per GET i executa `unlink()`; requereix substitució per token opac i directori privat.

Aquests punts són **evidència estàtica del repositori**, no prova de desplegament ni d'autorització efectiva a producció.

## 4. Codi SIF creat en aquesta auditoria

| Fitxer | Responsabilitat | Estat |
| --- | --- | --- |
| `sif/src/Contract/StudentProfileAuthorizationPolicyInterface.php` | Contracte d'autorització per perfil/inscripció | IMPLEMENTAT |
| `sif/src/Service/ResolvedStudentProfileAuthorizationPolicy.php` | Scope resolt server-side, fail-closed | IMPLEMENTAT |
| `sif/src/Repository/StudentProfileReadRepository.php` | Lectura acotada del perfil llegat per `ID_INSC` | IMPLEMENTAT |
| `sif/src/Repository/PersonalDataChangeRepository.php` | Persistència/relectura de `personal_data_change_request` | IMPLEMENTAT |
| `sif/src/Service/StudentProfileService.php` | Consulta autoritzada i proposta idempotent de canvi | IMPLEMENTAT PARCIAL |

La primera capa **no aplica encara el canvi a `inscripcions`**. Registra una petició estructurada amb `REQUEST_ID`, `CORRELATION_ID`, actor i before/after per camp. Aquesta separació és intencionada: evita reproduir l'UPDATE mixt del llegat abans de tenir adaptador, autorització, concurrència i propagació verificats.

### Resultats del servei

- `REQUESTED`: petició nova persistida.
- `REUSED`: mateix `request_id`, subjecte, actor, correlació i changeset.
- `NO_CHANGE`: el payload no canvia cap valor; no crea petició.
- `409 CONFLICT`: mateix `request_id` reutilitzat amb payload diferent.
- `403`: scope de perfil no autoritza lectura/escriptura.
- `404`: inscripció inexistent.
- `422`: camps no suportats o identificadors invàlids.

Els camps econòmics, fiscals, mailing, certificat i baixa **no formen part de l'allowlist de perfil** i han de derivar al UC corresponent.

## 5. Proves creades

- `ResolvedStudentProfileAuthorizationPolicyTest`: fail-closed, READ vs WRITE i scope global explícit.
- `PersonalDataChangeRepositoryTest`: persistència i relectura del changeset estructurat.
- `StudentProfileServiceTest`: autorització, diffs reals, `NO_CHANGE`, reús idempotent, conflicte i bloqueig de camp `pagament`.

Aquestes proves queden **ESCRITES, NO EXECUTADES EN AQUESTA AUDITORIA** fins disposar de l'entorn MySQL de test configurat amb `SIF_ENV=test`.

## 6. UML que faltava i queda completat

Abans d'aquest tall hi havia molta cobertura d'activitats, però el diagrama de classes i la seqüència principal barrejaven codi existent i disseny. Queda separat en tres nivells:

1. **ACTUAL llegat:** Browser/JS → wrapper GET → `Intranet` → `ConnexioWeb` → `inscripcions`.
2. **SIF parcial implementat:** `StudentProfileService` → política server-side → lector llegat → `PersonalDataChangeRepository`.
3. **FINAL:** adaptador POST autenticat → servei → request → aplicador/propagació → relectura per destí → auditoria, amb derivació fiscal/acadèmica quan pertoqui.

## 7. Mancances que continuen obertes

### P0 — seguretat i privacitat

- Els endpoints `mostraModalConsultaCertificat.php` i `mostrarCertificat.php` són referenciats pel JS però no estan versionats al `main` examinat.
- `eliminarArxiu.php` no ha de rebre una ruta/filename arbitrari del client.
- La incidència històrica de certificats amb identificadors personals al nom necessita revisió separada de HEAD, historial Git, còpies i desplegament.

### P1 — adaptació executable

- Crear endpoint/adaptador autenticat POST per consultar/proposar/aplicar canvi.
- Resoldre `student_scope` des de la sessió/rol real del servidor; mai des del payload del navegador.
- Afegir aplicador del canvi amb lock/versió, allowlist per procés, relectura i resultat tipificat.
- Connectar `operational_event` / auditoria abans-després.
- No reutilitzar el mateix comandament per baixa, consentiment, certificat o pagament.

### P1 — UI

- Cancel·lar ha de restaurar snapshot original, no el valor temporal.
- Una resposta amb error no pot convertir inputs locals en valors de lectura aparentment confirmats.
- Mutacions han de deixar de viatjar per GET.

### P2 — proves

Executar T-AL-08–17 i T-AL-F01–F12 amb dades sintètiques, verificar l'asset minificat realment desplegat i registrar BD abans/després. Moodle i correus continuen exclosos d'aquest lot segons la decisió ja documentada.

## 8. Criteri de tancament

UC-042 es podrà declarar **FUNCIONALMENT_COMPLET** només quan:

1. l'adaptador d'intranet utilitzi el contracte SIF;
2. autorització de recurs/camp sigui server-side;
3. aplicació i propagació tinguin estat verificable per destí;
4. errors/cancel·lació no mostrin dades no persistides;
5. certificat/temporals quedin resolts;
6. les proves no excloses passin amb evidència.

En la reintegració del 30/09/2026: **la capa SIF inicial i les proves han estat trasplantades selectivament sobre el `main` actual; no s'ha fusionat la branca antiga completa. L'adaptador d'intranet, l'aplicador/propagació i les proves d'entorn continuen pendents.**


## 9. Nota de reintegració 30/09/2026

La branca antiga estava 22 commits per davant però 1.228 commits per darrere de `main`. Per evitar regressions, s'han recuperat selectivament els fitxers UC-042 de codi/proves i els fragments documentals específics. No s'han importat recomptes globals ni contingut aliè al UC-042 procedent de l'estat antic del repositori.
