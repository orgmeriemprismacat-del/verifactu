# UC-116 · Custodiar i revisar evidències sensibles de descompte

**Objectiu canònic de la fitxa original:** cada justificant té hash, custòdia protegida, finalitat, controls d'accés, decisió i termini de retenció; no queda exposat al webroot ni reduït a un correu. **Bloquejant:** determinar per `TIPUS_DESC` quina evidència es demana, la base jurídica/necessitat de custòdia, rols habilitats, ubicació i termini de supressió amb negoci i protecció de dades.

**Evidència d'esquema:** `discount_evidence` conté `UUID_VALIDATION` (FK a `discount_validation`), `STORAGE_REF`, `CONTENT_HASH`, `MIME_TYPE`, `SIZE_BYTES`, `ACCESS_CLASSIFICATION=RESTRICTED` per defecte, `PURPOSE_CODE`, `RETENTION_POLICY_CODE`, `DELETE_AFTER`, `DELETED_AT` i `DELETION_PROOF_HASH`. `UNIQUE(UUID_VALIDATION,CONTENT_HASH)` només deduplica fitxers dins una validació. **No s'ha acreditat** un servei PHP que encripti/carregui/verifiqui bytes, protegeixi descàrregues, controli accessos o n'executi la supressió. `DocumentRepository` tracta documents de factura, **no prova la custòdia dels justificants**.

## 1. Fitxa específica

| Pas | Contracte i risc |
| --- | --- |
| Sol·licitud | Relacionar subjecte, `ID_INSC`, dret de descompte, norma comercial, verificació que cal fer, actor i finalitat. No incloure diagnòstic o dades innecessàries al concepte de factura ni al log de Redsys. |
| Recepció | Validar format, extensió/MIME real, mida i integritat i transportar per canal restringit. Generar hash dels **bytes**, no només d'una ruta o nom de fitxer, i guardar `STORAGE_REF` opac fora del webroot. |
| Custòdia | Permisos de lectura per finalitat i rol; l'existència de `ACCESS_CLASSIFICATION` a la BD **no és autorització executable**. No exposar `STORAGE_REF` com a URL directa pública. |
| Revisió | Enllaçar justificant a `UUID_VALIDATION`, decisió, validador i instant. Guardar informació mínima de motiu intern; preparar text visible de descompte **diferent** i genèric per a la factura (UC-20b). |
| Retenció/supressió | Definir termini real i política de legal hold quan correspongui; en venciment, comprovar autoritzacions i eliminar bytes/metadades segons política amb prova, no marcar només `DELETED_AT` sense verificació. |
| Economia/fiscalitat | Validar una evidència **no és** `CHARGE`, `REFUND`, factura ni moviment de fons. Si s'aprova després d'emetre, una rectificació/ajust requereix UC-90 i classificació fiscal separada; no fer `UPDATE` sobre línia original. |

### 1.1. Flux objectiu

1. La persona/gestió inicia validació de descompte amb tipus, regla, finalitat i rol legitimats; un controlador de dades **pendent** especifica quina evidència concreta és necessària, sense sol·licitar-ne més.
2. El servei de custòdia **pendent** valida bytes, calcula `CONTENT_HASH`, els emmagatzema en ubicació protegida i només després escriu la metadata `discount_evidence` associada a `UUID_VALIDATION`; si un dels dos passos falla, deixa incidència recuperable i no declara el fitxer custodiat.
3. Un operador amb rol adequat accedeix a la prova mitjançant una acció auditada, registra decisió i versió de la regla, i restringeix qualsevol descàrrega a la finalitat aprovada.
4. La capa comercial passa **només la decisió/valor de descompte** al snapshot fiscal. `LegacyCourseInvoicePayloadBuilder` pot transportar motiu intern i text de visualització, però **no garanteix** que el document PDF exclogui el motiu intern.
5. En caducar el termini s'aplica la política aprovada, incloent dependències/revisions, destrucció real i prova, sense esborrar un registre fiscal immutable com a «neteja» del justificant.

### 1.2. Alternatives i proves

| Cas | Resultat |
| --- | --- |
| Dos uploads idèntics a la mateixa validació | Reús/metadada única per la restricció `UNIQUE(UUID_VALIDATION,CONTENT_HASH)`; no saltar validació de permisos per coincidència de hash. |
| Document malmès o hash no coincideix | Bloquejar revisió automàtica i obrir incidència; no aprovar descompte només per `STORAGE_REF`. |
| Operador sense rol de justificants | Denegar la lectura **i auditar l'intent**; el rol no deriva de disposar de l'UUID. |
| Factura a empresa amb persona beneficiària | Text genèric i import quan pertoca; no lliurar al pagador el justificant personal per defecte. |
| Prova ja no necessària però factura conservada | Aplicar retenció diferenciada: el document fiscal històric i el justificant intern **no són el mateix objecte**. |

**Proves pendents:** upload/download restringit, hash de bytes, documents maliciosos, fuites al PDF/exportacions/log, retenció i prova d'eliminació, recuperació tras fallada de storage, control d'accés i reintents. Cap prova d'aquest servei ha estat executada.

### 1.3. Del justificant enviat pel llegat a una prova custodiada

**Contrast directe amb main (22/09/2026):** [`enviarImatgeCarnetInscripcio.php`](../../codi-drive/web-actual/ajax/enviarImatgeCarnetInscripcio.php) rep POST i fitxer, forma el nom amb paràmetres i l'extensió original, desa via `move_uploaded_file()` sota `carnets/` i construeix una URL web directa al justificant en un HTML d'avís. No es veu en aquest fitxer una comprovació explícita de sessió/rol, MIME real/mida, hash dels bytes, vinculació amb `ID_INSC/UUID_VALIDATION`, custòdia fora de webroot o autorització de descàrrega. **La instanciació de `MailSMTPFile` no acredita el lliurament del correu.** A la intranet, [`sendMsgValidatCurosDescomptes.php`](../../codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatCurosDescomptes.php) rep `idInsc/verificat` i delega a `Intranet::sendMsgValidatCurosDescomptes()`, però **el cos d'aquest mètode no s'ha pogut recuperar**: no és correcte afirmar ni descartar el càlcul, l'UPDATE, els permisos interns o l'enviament efectiu. El [JS de la pàgina](../../codi-drive/intranet-actual/js/alumnes-validar-descomptes.js) només acredita la commutació visual i la crida GET. Tot això no acredita la custòdia `discount_evidence`, hash, rol per acció, retenció o esborrat del nou SIF. No marcar `UUID_VALIDATION` com a «prova custodiada» per haver rebut una imatge o un correu al circuit antic.

**Condició de l'evidència per etapa.** Distingir `REBUDA` (aportació del document al canal), `PENDENT_REVISIO`, `VALIDADA/DENEGADA` (decisió sobre el dret) i `CUSTODIADA` (bytes emmagatzemats, hash i controls d'accés comprovats); són **estats funcionals proposats**, no enums implementats en les taules. Una validació manual pot ser una decisió real encara que no s'hagi migrat el fitxer, però no s'ha d'afirmar que les dues fites són equivalents. Conservar referència a la inscripció i regla comercial; limitar l'atribució de decisió a l'operador que realment l'ha pres.

**Dades en trànsit i sortides.** El justificant personal no s'inclou en `redsys_payment_intent.SNAPSHOT_JSON` ni en `factura_linia.DESC_MOTIU_INTERN` com a document complet. La factura i els correus al pagador han de contenir només l'import i el text públic necessari; no enviar la imatge personal al receptor d'una factura de grup/empresa per defecte. Qualsevol descàrrega d'evidència ha de validar autorització al servidor, registrar l'accés i recuperar els bytes per una referència interna protegida, no per un `filename` aportat lliurement pel navegador.

**Manteniment i supressió.** El termini de retenció d'un justificant **no es dedueix** del nombre d'anys de conservació de la factura ni del venciment del codi promocional; la regla real queda pendent d'aprovació. Si s'elimina la prova segons política, conservar l'event mínim i les dades fiscals històriques que pertoquin **sense deixar una URL pública al fitxer original**; marcar `DELETED_AT` no prova que s'hagin destruït els bytes ni les còpies temporals.

### 1.4. Proves específiques de migració i accés (no executades)

| ID | Escenari | Resultat esperat |
| --- | --- | --- |
| EV-01 | El canal antic rep una imatge de carnet | Estat de recepció diferent de validació i de custòdia segura. |
| EV-02 | Dret validat manualment però fitxer no migrat al SIF | Decisió identificable, evidència/custòdia no acreditada fins a verificació real. |
| EV-03 | Hash o bytes absents malgrat metadades `discount_evidence` | Incidència; no donar per custodiat el document. |
| EV-04 | Responsable de grup o alumne d'una altra inscripció consulta justificant | Accés denegat i auditat al servidor. |
| EV-05 | Correu/PDF de pagament amb descompte | Només import/text públic, sense imatge o motiu intern personal. |
| EV-06 | Termini de retenció esgotat amb fitxer temporal encara present | Eliminar segons política aprovada i registrar prova sobre totes les còpies controlades. |

## 2. UML de casos d'ús

```plantuml
@startuml
left to right direction
actor "Persona sol·licitant" as P
actor "Gestió amb accés restringit" as G
actor "Procés de retenció" as R
rectangle "Evidència de descompte" {
 usecase "UC-116\nCustodiar i revisar evidència" as Main
 usecase "Verificar fitxer i hash" as File
 usecase "Comprovar permisos i registrar decisió" as Review
 usecase "Destruir prova vençuda amb evidència" as Delete
}
P --> Main
G --> Main
R --> Delete
Main ..> File : <<include>>
Main ..> Review : <<include>>
@enduml
```

## 3. Diagrama de classes

```mermaid
classDiagram
class DiscountEvidenceService {
 <<DISSENY: no acreditat>>
 +store(uuidValidation,bytes,purpose) evidence
 +review(uuidEvidence,actor,decision) result
 +deleteDue(uuidEvidence,policy) result
}
class DiscountEvidenceRepository {
 <<DISSENY: taula SQL definida>>
 +appendMetadata(db,evidence) result
 +findRestricted(db,uuidEvidence,actor) evidence
 +markDeletion(db,proof) result
}
class ProtectedEvidenceStorage {
 <<DISSENY: no acreditat>>
 +write(bytes) storageRef
 +verifyHash(ref,hash) bool
 +delete(ref) proof
}
class LegacyCourseInvoicePayloadBuilder {
 <<PHP existent: no controla accés als justificants>>
 +build(snapshot) array
}
DiscountEvidenceService --> DiscountEvidenceRepository : metadades i decisió
DiscountEvidenceService --> ProtectedEvidenceStorage : bytes i integritat
```

## 4. Seqüència — custòdia, revisió i retenció (DISSENY)

```mermaid
sequenceDiagram
actor P as Persona
actor G as Gestió autoritzada
participant S as DiscountEvidenceService [DISSENY]
participant Store as ProtectedEvidenceStorage [DISSENY]
participant R as DiscountEvidenceRepository [DISSENY]
P->>S: Adjuntar prova a UUID_VALIDATION per canal segur
S->>S: Comprovar format, necessitat i hash de bytes
S->>Store: write(bytes) fora del webroot
Store-->>S: STORAGE_REF restringit
S->>R: appendMetadata(UUID_VALIDATION,hash,finalitat,retenció)
R-->>S: UUID_EVIDENCE
G->>S: Revisar UUID_EVIDENCE
S->>R: Comprovar rol/finalitat i accés
S->>Store: verifyHash(STORAGE_REF,CONTENT_HASH)
alt Hash o permís incorrecte
 S-->>G: Accés/validació rebutjats, incidència
else Prova íntegra
 S->>R: Registrar decisió de validació separada
 S-->>G: Dret de descompte aprovat/rebutjat
end
opt Retenció vençuda i supressió autoritzada
 S->>Store: delete(STORAGE_REF)
 Store-->>S: Prova de supressió
 S->>R: markDeletion(DELETED_AT,DELETION_PROOF_HASH)
end
Note over S,R: DDL present, custòdia física i permisos executable NO acreditats.
```

## 5. Traçabilitat

[UC-116 original](../06-fitxes-funcionals/uc-116.md) · [UC-20b descompte sensible](uc-020b-aplicar-descompte-sensible.md) · [UC-111 docent novell](uc-111-docent-novell-dret-futur.md) · [UC-36 document fiscal](uc-036-generar-consultar-documents.md) · [Migració discount_evidence](../../sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql) · [Builder de factura](../../sif/src/Service/LegacyCourseInvoicePayloadBuilder.php).

## 6. Contrast de les pàgines i del PHP real — auditoria 22/09/2026

**Dossiers vinculats:** [fitxa funcional individual amb accions reals, variants i canvis pendents](../06-fitxes-funcionals/uc-116.md#22-contrast-especific-amb-les-accions-actuals-de-prisma--auditoria-22092026) · [lot 05, UC-116: traçabilitat i incidència de seguretat sense URLs de documents personals](00-auditoria-casos-pendents-lot-05-uc-116-2026-09-22.md) · [diagrames PlantUML d'activitat ACTUAL i FINAL de la pàgina de descomptes, del subflux de pujada i de la pàgina de validació a intranet](uc-116-activitats-pagines-justificants-actual-final.md).

**Classes reals / DISSENY:**
- EXISTENT llegat: \`Descomptes\` renderitza les cinc famílies; \`enviarImatgeCarnetInscripcio.php\` carrega bytes sota webroot i prepara URL directa; \`alumnes-validar-descomptes.js\` presenta indicadors SÍ/NO i crida el wrapper \`sendMsgValidatCurosDescomptes.php\`; \`mostrarMain.php\` comprova permís de VISUALITZACIÓ de pàgina.
- EXISTENT però **cos no inspeccionat**: \`Intranet::sendMsgValidatCurosDescomptes()\` i \`Intranet::mostrarPage()\`; les seves regles/effects no es poden certificar des del wrapper o JS.
- SQL DEFINIT, ús executable NO acreditat: \`discount_validation\` (000004) i \`discount_evidence\` (000005); el fet de tenir \`STORAGE_REF\`, \`CONTENT_HASH\`, \`ACCESS_CLASSIFICATION\` o \`DELETED_AT\` no implanta autorització ni custòdia.
- DISSENY: \`DiscountEvidenceService\`, \`DiscountEvidenceRepository\`, \`ProtectedEvidenceStorage\` en aquest UC. \`sif/src/Aeat/EvidenceStore.php\` és custòdia d'intents AEAT, no un servei equivalent per justificar descomptes.

**Incidència prioritària independent del roadmap SIF:** l'arbre públic de \`main\` conté 145 fitxers PDF/imatge sota el directori de justificants i el llegat genera URLs web directes a fitxers del mateix tipus. No inspeccionar ni publicar noms/bytes individuals. Restringir accés i valorar repositori/historial, servidor i còpies abans d'assumir que el nou servei resoldrà l'exposició anterior; no s'ha verificat el desplegament ni una contenció real.

**Criteri d'acabament documental RM-037:** els tres blocs de diagrames distingeixen explícitament el que està observat del que no: l'origen/pàgina/JS de l'upload no s'han identificat; el \`mostrarPage\` i la persistència efectiva de la decisió a \`Intranet.php\` no s'han recuperat. Completar pantalla i apartats abans de marcar-los auditats exhaustivament. Els processos de descompte comercial/preu, rectificació i justificants tenen responsabilitats separades. **Proves UC116-T01–T10 no executades; UC-116 no tancada.**
