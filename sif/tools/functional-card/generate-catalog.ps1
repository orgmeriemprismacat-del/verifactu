param(
    [switch]$Generate,
    [switch]$ValidateOnly
)

$ErrorActionPreference = 'Stop'
if ($Generate -and $ValidateOnly) {
    throw 'No es poden combinar -Generate i -ValidateOnly.'
}
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../../..')).Path
$catalogPath = Join-Path $repoRoot 'documentacio/04-estat-final/33-casos-us-sif.md'
$outputDirectory = Join-Path $repoRoot 'documentacio/06-fitxes-funcionals'

$requiredHeadings = @(
    '## 1. Metadades',
    '## 2. Manifest de fonts',
    '## 3. Resum funcional',
    '## 4. Actors i permisos',
    '## 5. Precondicions',
    "## 6. Dades d’entrada",
    '## 7. Càlculs i regles de negoci',
    '## 8. Dades de sortida i postcondicions',
    '## 9. Flux principal',
    '## 10. Fluxos alternatius',
    '## 11. Errors, bloquejos i recuperació',
    '## 12. Impacte fiscal',
    '## 13. Factures, rectificatives, pagaments, devolucions i saldos',
    '## 14. Impacte VERI*FACTU/AEAT',
    '## 15. Components afectats',
    '## 16. Auditoria, concurrència i idempotència',
    '## 17. Notificacions i correus',
    '## 18. Casos de prova',
    '## 19. Conflictes, buits i traçabilitat',
    '## 20. Decisions pendents',
    '## 21. Tasques de programació'
)

$sourceDefinitions = [ordered]@{
    'SRC-001' = 'documentacio/04-estat-final/33-casos-us-sif.md'
    'SRC-002' = 'documentacio/03-canvis-pendents/04-fluxos-facturacio.md'
    'SRC-003' = 'documentacio/04-estat-final/05-model-bd-sif.md'
    'SRC-004' = 'documentacio/04-estat-final/16-estat-final-pantalles.md'
    'SRC-005' = 'documentacio/04-estat-final/18-estat-final-operacio-incidencies.md'
    'SRC-006' = 'documentacio/05-governanca-operacio/20-pla-proves-validacio-sif.md'
    'SRC-007' = 'documentacio/05-governanca-operacio/21-seguretat-permisos-accessos.md'
    'SRC-008' = 'documentacio/05-governanca-operacio/24-diccionari-camps-i-valors.md'
    'SRC-009' = 'documentacio/04-estat-final/38-matriu-transformacio-funcional-verifactu.md'
    'SRC-010' = 'sif/database/migrations/2026_09_15_000003_add_functional_audit_control.sql'
    'SRC-011' = 'documentacio/04-estat-final/39-auditoria-fitxes-funcionals.md'
    'SRC-012' = 'documentacio/01-compliment-aeat/documentacio-sif-aeat.md'
    'SRC-013' = 'sif/database/migrations/2026_09_16_000004_add_commercial_operation_and_fiscal_fields.sql'
    'SRC-028' = '00-control/registre-decisions.md'
}

$codeSourceDefinitions = [ordered]@{
    'SRC-024' = 'sif/database/migrations/2026_09_16_000005_add_operation_lifecycle_tables.sql'
    'SRC-027' = 'sif/database/migrations/2026_09_16_000006_add_cross_system_control_tables.sql'
    'SRC-029' = 'sif/tools/functional-card/fixtures/canvi-curs.json'
}

$actorOverrides = @{
    'UC-01' = 'Ecommerce, intranet, operador autoritzat o procés automàtic'
    'UC-02' = 'Operador, empresa/responsable o procés automàtic'
    'UC-03' = 'Redsys i procés automàtic SIF'
    'UC-04' = 'Operador autoritzat'
    'UC-05' = 'Operador autoritzat'
    'UC-06' = 'Operador i responsable tècnica'
    'UC-07' = 'Receptor, operador, suport o auditor segons abast'
    'UC-08' = 'Responsable tècnica i operador autoritzat'
    'UC-09' = 'Procés automàtic SIF i AEAT'
    'UC-10' = 'Responsable tècnica'
    'UC-11' = 'Responsable tècnica o procés de migració'
    'UC-12' = 'Gestió de cobraments'
    'UC-13' = 'Gestió, alumne, USOC i procés automàtic'
    'UC-86' = 'Qualsevol actor o procés que consulti o actuï sobre un pagament'
    'UC-106' = 'Alumne, ecommerce o gestió'
    'UC-107' = 'Alumne, ecommerce o gestió'
    'UC-108' = 'Alumne i ecommerce'
    'UC-109' = 'Alumne, ecommerce i gestió'
    'UC-110' = 'Dos participants i la persona pagadora'
    'UC-111' = 'Alumne, validador i gestió'
    'UC-112' = 'Ecommerce i SIF'
    'UC-113' = "Gestió o procés d’importació autoritzat"
    'UC-114' = 'Gestió acadèmica i validador autoritzat'
    'UC-115' = 'Alumne, ecommerce o gestió'
    'UC-116' = 'Alumne, validador i responsable de protecció de dades'
    'UC-117' = 'Gestió, ecommerce i titular del dret'
    'UC-118' = 'Responsable de grup, participants i gestió'
    'UC-119' = 'Comprador, beneficiari i gestió'
    'UC-120' = 'Alumne i gestió autoritzada'
    'UC-121' = 'Pagador, ecommerce i SIF'
    'UC-122' = 'Alumne, gestió i SIF'
    'UC-123' = 'Receptor, gestió i procés documental'
    'UC-124' = 'Gestió i procés acadèmic'
    'UC-125' = 'Persona interessada, gestió i procés de comunicacions'
    'UC-126' = 'Gestió, suport i persona interessada'
    'UC-127' = 'Gestió acadèmica, cobraments i responsable autoritzat'
    'UC-128' = 'Alumne, gestió i validador de dades'
    'UC-129' = 'Procés acadèmic, gestió i suport'
}

$paymentCases = @(
    2, 3, 6, 22, 23, 24, 25, 28, 29, 33, 47, 50, 51, 52, 53, 56, 61, 62,
    63, 68, 72, 79, 81, 82, 86, 90, 92, 94, 95, 96, 103, 104, 105, 110, 112,
    118, 119, 121, 122, 124, 127
)
$fiscalCases = @(
    1, 4, 5, 9, 11, 13, 14, 15, 16, 17, 19, 21, 30, 31, 32, 35, 36, 37,
    41, 46, 48, 54, 55, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 80, 83, 84,
    87, 88, 89, 90, 91, 92, 93, 94, 97, 98, 109, 110, 112, 114, 116,
    117, 118, 119, 120, 121, 122, 123, 124, 126, 127, 128
)
$documentCases = @(7, 36, 37, 45, 48, 49, 55, 59, 61, 78, 79, 80, 84, 97, 99, 102, 116, 120, 123)
$governanceCases = @(8, 9, 10, 34, 35, 37, 38, 39, 40, 44, 45, 46, 53, 54, 57, 58, 59, 60, 64, 67, 77, 81, 82, 83, 84, 85, 97, 98, 101, 113, 114, 115, 116, 117, 120, 124, 125, 126, 127, 128, 129)

function Get-NumericId([string]$id) {
    if ($id -notmatch '^UC-(\d+)([a-z]?)$') {
        throw "Identificador de cas invàlid: $id"
    }
    return [int]$Matches[1]
}

function Get-FileName([string]$id) {
    $null = $id -match '^UC-(\d+)([a-z]?)$'
    $suffix = $Matches[2].ToLowerInvariant()
    return ('uc-{0:D3}{1}.md' -f [int]$Matches[1], $suffix)
}

function Get-Domain([int]$number) {
    if ($number -ge 125 -and $number -le 129) { return 'consentiment, identitat i coherència entre sistemes' }
    if ($number -ge 113 -and $number -le 124) { return 'cicle de vida comercial, acadèmic i documental' }
    if ($number -ge 106 -and $number -le 112) { return 'operació comercial i inscripció' }
    if ($number -in $governanceCases) { return 'governança i operació' }
    if ($number -in $documentCases) { return 'documents, accés i comunicacions' }
    if ($number -in $paymentCases) { return 'pagaments i conciliació' }
    if ($number -in $fiscalCases) { return 'facturació i registre fiscal' }
    if ($number -ge 14 -and $number -le 25) { return 'venda i descomptes' }
    if ($number -ge 26 -and $number -le 33) { return 'canvis posteriors' }
    if ($number -ge 41 -and $number -le 43) { return 'gestió operativa' }
    if ($number -ge 65 -and $number -le 66) { return 'circuit adjacent de col·laboradors' }
    if ($number -ge 69 -and $number -le 76) { return 'transformació funcional i registral' }
    if ($number -ge 87 -and $number -le 100) { return 'casuística recuperada' }
    return 'integració SIF'
}

function Get-Channels([int]$number) {
    if ($number -eq 86) {
        return "web, intranet, portal d’alumne, panell SIF, API, Redsys, worker, CLI, migració, conciliació i sincronització"
    }
    if ($number -in @(3, 14, 15, 17, 18, 20, 21, 50, 51, 61, 63, 102, 103)) {
        return 'web/ecommerce, portal extern i `pay.prisma.cat`'
    }
    if ($number -in @(9, 39, 40, 47, 52, 54, 55, 58, 60, 67, 77, 78, 79, 82, 85, 101)) {
        return '`pay.prisma.cat`, API/worker/CLI i panell SIF'
    }
    if ($number -in @(34, 35, 37, 38, 45, 46, 57, 59, 64, 81, 83, 84)) {
        return 'panell `pay.prisma.cat/sif`'
    }
    if ($number -in @(65, 66, 99)) {
        return 'intranet de col·laboradors, en circuit separat'
    }
    if ($number -in @(106, 107, 108, 109, 110, 111, 112, 115, 117, 118, 119, 121, 122)) {
        return 'web/ecommerce, adaptador SIF i intranet de gestió quan cal validació'
    }
    if ($number -in @(113, 114, 116, 120, 123, 124)) {
        return "intranet principal, portal d’alumne o procés controlat; persistència canònica al SIF"
    }
    if ($number -in @(125, 126, 128)) {
        return "web/ecommerce, intranet principal i portal d’alumne; expedient canònic i propagació controlada"
    }
    if ($number -in @(127, 129)) {
        return 'intranet principal, Prisma/Moodle i processos de reconciliació; efectes econòmics només al SIF'
    }
    return 'intranet principal amb comanda al SIF; consulta externa quan correspongui'
}

function Get-Persistence([int]$number) {
    $tables = [System.Collections.Generic.List[string]]::new()
    $tables.Add('sif_audit_event')
    $tables.Add('operational_event')
    if ($number -in $paymentCases) {
        $tables.Add('payment_action_event')
        $tables.Add('payment_transaction/payment_allocation')
    }
    if ($number -in $fiscalCases) {
        $tables.Add('factura/factura_linia')
        $tables.Add('factura_registres/factura_registre_control')
    }
    if ($number -in $documentCases) {
        $tables.Add('document_job/factura_documents/fiscal_document_access')
    }
    if ($number -in @(8, 53, 54, 55, 60, 77, 78, 79, 81, 82, 85)) {
        $tables.Add('errors_verifactu/sif_incident_action')
    }
    if ($number -in @(26, 71)) { $tables.Add('course_change_event') }
    if ($number -in @(27, 72, 96)) { $tables.Add('enrollment_cancellation_event') }
    if ($number -in @(41, 69, 70, 87, 93)) { $tables.Add('billing_profile_history') }
    if ($number -in @(9, 30, 31, 35, 54, 75, 76, 77)) { $tables.Add('aeat_submission_attempt') }
    if ($number -in @(43, 49, 58, 79)) { $tables.Add('notification_outbox/notification_delivery_attempt') }
    if ($number -in @(10, 46, 83)) { $tables.Add('sif_version/sif_declaration') }
    if ($number -in @(37, 45, 59, 84)) { $tables.Add('fiscal_export/fiscal_export_access') }
    if ($number -in @(25, 53, 82)) { $tables.Add('reconciliation_run/reconciliation_item') }
    if ($number -in @(40, 60, 85)) { $tables.Add('backup_restore_evidence') }
    if ($number -ge 106 -and $number -le 112) {
        $tables.Add('commercial_operation/commercial_operation_party')
    }
    if ($number -ge 113 -and $number -le 124) {
        $tables.Add('commercial_operation/commercial_operation_line')
    }
    if ($number -ge 125 -and $number -le 129) {
        $tables.Add('commercial_operation')
    }
    if ($number -in @(90, 91, 94, 110, 111, 112)) { $tables.Add('discount_validation') }
    if ($number -in @(50, 61, 103, 110, 112)) { $tables.Add('payment_link') }
    if ($number -eq 113) { $tables.Add('enrollment_import_run/enrollment_import_item') }
    if ($number -eq 114) { $tables.Add('master_data_change_request') }
    if ($number -in @(115, 118, 121, 122)) { $tables.Add('capacity_reservation') }
    if ($number -eq 116) { $tables.Add('discount_validation/discount_evidence/fiscal_document_access') }
    if ($number -in @(117, 119)) { $tables.Add('commercial_entitlement/commercial_entitlement_event') }
    if ($number -eq 120) { $tables.Add('personal_data_change_request/billing_profile_history') }
    if ($number -in @(121, 123)) { $tables.Add('payment_link/document_job') }
    if ($number -eq 123) { $tables.Add('electronic_invoice_delivery') }
    if ($number -eq 124) { $tables.Add('academic_economic_state_event') }
    if ($number -eq 125) { $tables.Add('communication_consent/communication_consent_event') }
    if ($number -eq 126) { $tables.Add('external_identity_link/identity_conflict_case') }
    if ($number -eq 127) { $tables.Add('edition_lifecycle_event/edition_operation_impact') }
    if ($number -eq 128) { $tables.Add('address_validation_case/billing_profile_history') }
    if ($number -eq 129) { $tables.Add('reconciliation_run/academic_reconciliation_item/academic_economic_state_event') }
    if ($number -in @(118, 119, 122)) { $tables.Add('operation_line_invoice_link') }
    return ($tables | Select-Object -Unique) -join ', '
}

function Get-FiscalRule([int]$number) {
    if ($number -in @(106, 107)) {
        return "La reserva o detecció de duplicat no emet factura ni registre fiscal; conserva la classificació provisional i deriva a emissió només quan l’operació queda congelada i facturable."
    }
    if ($number -eq 108) {
        return 'El tastet gratuït es classifica `NON_BILLABLE/FREE_SAMPLE`: no crea factura, registre AEAT, pagament ni enllaç de pagament.'
    }
    if ($number -eq 109) {
        return "La subvenció no equival automàticament a operació no subjecta ni a factura de zero; el finançador, receptor i obligació documental han de quedar decidits abans d’emetre."
    }
    if ($number -eq 110) {
        return "El SIF congela dues prestacions i les seves parts; la decisió d’una factura amb dues línies o factures separades no es dedueix del fet que hi hagi un únic pagador o IDPAG."
    }
    if ($number -eq 111) {
        return 'El dret de descompte futur neix després de la validació i el cobrament confirmat, però no reescriu preu, línia ni impost de la factura ja emesa.'
    }
    if ($number -eq 112) {
        return "El snapshot pre-TPV fixa receptor, línies, preu, descompte, base, règim, tipus/quota, causa d’exempció o no subjecció i classificació; el callback només valida i materialitza aquest snapshot."
    }
    if ($number -eq 113) {
        return "Importar o crear una inscripció no prova cap cobrament ni autoritza factura: cada fila es classifica i només una comanda posterior pot emetre o registrar pagament."
    }
    if ($number -eq 114) {
        return "Un canvi de producte o edició no reescriu línies ni documents emesos; les operacions obertes conserven la versió acceptada o passen per un canvi explícit i auditat."
    }
    if ($number -eq 115) {
        return "Reservar o alliberar plaça no és un fet fiscal; l’event només condiciona si l’operació comercial pot avançar fins al snapshot facturable."
    }
    if ($number -eq 116) {
        return "Validar una evidència pot confirmar un descompte abans d’emetre; si la factura ja existeix, qualsevol diferència passa pel classificador fiscal i no per un canvi de total."
    }
    if ($number -eq 117) {
        return "Emetre o reservar un dret no altera cap factura; el consum entra com a descompte d’una línia nova i el snapshot conserva codi, regla i event de consum."
    }
    if ($number -eq 118) {
        return "El grup manté línies i participants separats; la regla aprovada decideix receptor i agrupació de factura, sense deduir-los del responsable o d’un IDPAG compartit."
    }
    if ($number -eq 119) {
        return "La compra del regal i el seu bescanvi són fets diferenciats: titular, contraprestació, moment fiscal i eventual rectificació requereixen una regla aprovada, no una còpia del FACT_REL."
    }
    if ($number -eq 120) {
        return "Aprovar dades mestres només afecta operacions futures o encara no congelades; les factures i snapshots emesos romanen immutables i qualsevol correcció fiscal es classifica."
    }
    if ($number -eq 121) {
        return "Una reserva caducada genera una proposta nova; no reutilitza preu, impost, plaça o enllaç anteriors sense nova acceptació, i mai reescriu una factura emesa."
    }
    if ($number -eq 122) {
        return "Cada component del pack conserva import i tractament fiscal; una substitució o baixa parcial posterior a l’emissió deriva a rectificació/classificació per línia."
    }
    if ($number -eq 123) {
        return "La factura electrònica és una representació immutable de la factura emesa; generar o reenviar el fitxer no crea una altra factura ni modifica el registre fiscal."
    }
    if ($number -eq 124) {
        return "Accés, Moodle, superació i certificat no creen efectes fiscals; si la regla depèn del deute, consulta l’estat econòmic sense alterar ni ocultar el moviment de pagament."
    }
    if ($number -eq 125) {
        return 'El consentiment comercial és independent de la prestació, el pagament i la factura; concedir-lo o retirar-lo no crea ni modifica cap document fiscal.'
    }
    if ($number -eq 126) {
        return 'Resoldre identitat no fusiona snapshots fiscals ni documents emesos; qualsevol receptor històric roman immutable i una correcció posterior es classifica.'
    }
    if ($number -eq 127) {
        return "Canviar l’estat d’una edició no anul·la ni reescriu factures: cada operació afectada rep una decisió econòmica i fiscal explícita, inclosa rectificació quan correspongui."
    }
    if ($number -eq 128) {
        return 'La normalització només alimenta dades vives o operacions no congelades; una adreça fiscal ja emesa es conserva i la petició de correcció passa pel classificador.'
    }
    if ($number -eq 129) {
        return "Reconciliar Prisma i Moodle no és un fet fiscal; l’execució pot consultar l’estat econòmic canònic però no crea, edita ni elimina pagaments o factures."
    }
    if ($number -in @(30, 75)) {
        return 'Si el registre és improcedent, crea `RegistroAnulacion` immutable, encadenat i remès; no crea una rectificativa per substituir-lo.'
    }
    if ($number -in @(31, 76)) {
        return 'La subsanació conserva el registre anterior, els indicadors AEAT i el registre corrector; no modifica la factura original.'
    }
    if ($number -in @(5, 71, 72, 73, 74, 89, 90, 93, 94)) {
        return "Amb factura emesa, el classificador decideix rectificativa, complementària, anul·lació, subsanació o cap efecte; queda prohibit l’``UPDATE`` fiscal directe."
    }
    if ($number -in $fiscalCases) {
        return 'Quan el resultat és facturable, només el SIF crea factura, línies, registre encadenat i cua AEAT dins la transacció corresponent.'
    }
    if ($number -in $paymentCases) {
        return 'El pagament posterior no crea registre fiscal per si sol; si manca factura i la venda és facturable, deriva explícitament a emissió abans de registrar o assignar el cobrament.'
    }
    return "Per defecte no genera registre fiscal; qualsevol canvi d’aquesta classificació exigeix UC-74 i una decisió registrada."
}

function Get-PaymentRule([int]$number) {
    if ($number -in @(106, 107, 108, 109)) {
        return "No crea cap moviment de pagament. Si posteriorment cal cobrar, una comanda separada crea l’enllaç/intenció i tots els intents i resultats queden al ledger."
    }
    if ($number -eq 110) {
        return 'Un únic pagador pot cobrir dues inscripcions, però la intenció, el moviment i les assignacions mantenen la relació explícita amb cada participant i línia.'
    }
    if ($number -eq 111) {
        return 'La confirmació del cobrament és una precondició consultada i auditada; generar el dret futur és idempotent i no duplica el pagament.'
    }
    if ($number -in @(113, 114, 115, 116, 117, 120, 123, 124, 125, 126, 128, 129)) {
        return 'El cas no crea ni modifica un pagament per si mateix; qualsevol consulta, derivació o bloqueig relacionat amb un cobrament deixa `payment_action_event` i usa un cas econòmic explícit.'
    }
    if ($number -eq 127) {
        return "L’event d’edició no inventa ni anul·la cobraments; cada afectat conserva el moviment original i deriva separadament a trasllat, devolució, saldo, compensació o cap efecte."
    }
    if ($number -eq 118) {
        return 'El grup pot tenir un únic pagador, però el moviment i les assignacions es vinculen a línies i participants congelats; afegir o treure membres després del TPV obre incidència o canvi classificat.'
    }
    if ($number -eq 119) {
        return 'Compra, bescanvi, reemborsament i possible saldo del regal són moviments/assignacions diferents i correlacionats; el codi no és un comprovant de pagament.'
    }
    if ($number -eq 121) {
        return "Caducar una reserva revoca l’enllaç anterior; el nou preu genera una nova versió i un nou enllaç/intenció, sense capturar cap import fins a l’acceptació."
    }
    if ($number -eq 122) {
        return 'Un únic moviment pot cobrir diverses línies del pack, però cancel·lació, devolució o saldo es calculen per component i queden en assignacions/events nous.'
    }
    if ($number -in $paymentCases) {
        return 'Tota petició, consulta, denegació, reutilització i resultat sobre pagaments genera `payment_action_event`; les correccions creen nous events i moviments compensatoris.'
    }
    return 'No crea ni altera pagaments tret que el classificador derivi a un cas de cobrament, devolució, saldo o compensació; aquesta derivació queda correlacionada.'
}

function Get-Decision([string]$id, [string]$title, [string]$state) {
    if ($id -eq 'UC-98') {
        return 'Pregunta: quin titular jurídic, sèries i base de dades corresponen a la botiga de llibres/SL? Responsable: direcció, Adam/Pablo i responsable tècnica.'
    }
    if ($id -eq 'UC-99') {
        return 'Pregunta: quines dades exactes pot consultar cada perfil de tutor sense entrar al circuit fiscal de venda? Responsable: negoci i responsable de protecció de dades.'
    }
    if ($id -eq 'UC-106') {
        return 'Pregunta bloquejant: quant dura cada reserva, quan consumeix plaça i qui pot alliberar-la o reobrir-la? Responsable: negoci i gestió acadèmica.'
    }
    if ($id -eq 'UC-107') {
        return 'Pregunta bloquejant: quina combinació exacta de document/correu/persona, curs i edició defineix duplicat, i quins estats permeten una nova inscripció? Responsable: negoci i gestió acadèmica.'
    }
    if ($id -eq 'UC-108') {
        return "Pregunta bloquejant: el consentiment de mailing actual és vàlid i separat, quina prova es conserva i quina regla impedeix duplicar l’accés gratuït? Responsable: negoci i protecció de dades."
    }
    if ($id -eq 'UC-109') {
        return "Pregunta bloquejant: qui és el finançador i receptor fiscal del curs subvencionat i quin document econòmic/fiscal s’ha d’emetre a cadascú? Responsable: direcció i assessoria fiscal."
    }
    if ($id -eq 'UC-110') {
        return "Pregunta bloquejant: la promoció d’amics genera una factura amb dues línies al pagador, una factura per participant o una altra regla segons receptor? Responsable: negoci i assessoria fiscal."
    }
    if ($id -eq 'UC-111') {
        return 'Pregunta bloquejant: el benefici futur és codi promocional, saldo comercial o un altre dret, quina caducitat té i qui el pot transferir o consumir? Responsable: negoci.'
    }
    if ($id -eq 'UC-112') {
        return 'Pregunta bloquejant: en quin moment es bloquegen plaça i preu, quant dura el snapshot i quina divergència entre callback i snapshot obre incidència? Responsable: negoci i responsable tècnica.'
    }
    if ($id -eq 'UC-113') { return "Pregunta bloquejant: quins formats, camps, permisos i criteris de duplicat s’admeten per a cada importació, i qui resol cada fila rebutjada? Responsable: gestió acadèmica i responsable tècnica." }
    if ($id -eq 'UC-114') { return 'Pregunta bloquejant: quins canvis mantenen el snapshot acceptat, quins exigeixen consentiment i qui pot versionar o cancel·lar una edició amb reserves? Responsable: negoci i gestió acadèmica.' }
    if ($id -eq 'UC-115') { return "Pregunta bloquejant: capacitat per curs o aula, durada de reserva, llista d’espera, sobreaforament autoritzat i ordre d’alliberament? Responsable: gestió acadèmica." }
    if ($id -eq 'UC-116') { return "Pregunta bloquejant: evidència exigida per TIPUS_DESC, base jurídica, ubicació, perfils d’accés i termini de supressió? Responsable: negoci i protecció de dades." }
    if ($id -eq 'UC-117') { return 'Pregunta bloquejant: tipologies de dret, transferibilitat, acumulació, caducitat, reserva de codi i reversió després de cancel·lació? Responsable: negoci.' }
    if ($id -eq 'UC-118') { return 'Pregunta bloquejant: quan es tanca el grup, com es recalcula el tram i quina factura/receptor correspon en cada composició? Responsable: negoci i assessoria fiscal.' }
    if ($id -eq 'UC-119') { return "Pregunta bloquejant: quin fet genera factura en compra/bescanvi, qui n’és receptor i com es tracten caducitat, canvi de beneficiari i devolució? Responsable: negoci i assessoria fiscal." }
    if ($id -eq 'UC-120') { return "Pregunta bloquejant: qui aprova cada camp, a quins sistemes es propaga i com s’exerceixen rectificació/supressió sense alterar documents històrics? Responsable: gestió i protecció de dades." }
    if ($id -eq 'UC-121') { return 'Pregunta bloquejant: quina tolerància de caducitat existeix i quins canvis de preu/plaça exigeixen nova acceptació? Responsable: negoci.' }
    if ($id -eq 'UC-122') { return "Pregunta bloquejant: quins components són obligatoris/substituïbles i com es calcula l’efecte fiscal/econòmic d’una baixa parcial? Responsable: negoci i assessoria fiscal." }
    if ($id -eq 'UC-123') { return 'Pregunta bloquejant: format electrònic, canal, consentiment, destinatari, SLA, evidència de lliurament i regla de reintent? Responsable: facturació i protecció de dades.' }
    if ($id -eq 'UC-124') { return 'Pregunta bloquejant: quines combinacions de baixa, deute, pròrroga, pagador de grup i superació permeten accés, Moodle i certificat? Responsable: gestió acadèmica i cobraments.' }
    if ($id -eq 'UC-125') { return "Pregunta bloquejant: quines finalitats, canals, textos/versionats, doble confirmació, caducitat i mecanismes de retirada s’han d’acreditar? Responsable: màrqueting i protecció de dades." }
    if ($id -eq 'UC-126') { return 'Pregunta bloquejant: quin identificador és canònic, quines coincidències poden fusionar-se automàticament i qui aprova conflictes de DNI/correu/usuaris Moodle? Responsable: gestió, suport i protecció de dades.' }
    if ($id -eq 'UC-127') { return "Pregunta bloquejant: per cada estat d’edició, quines operacions es traslladen, cancel·len o mantenen i quina decisió econòmica/fiscal correspon a pagats, facturats i pendents? Responsable: negoci, cobraments i assessoria fiscal." }
    if ($id -eq 'UC-128') { return 'Pregunta bloquejant: quina font/normalitzador valida CP, població, país i adreça, qui resol discrepàncies i quan es bloqueja una emissió? Responsable: gestió i facturació.' }
    if ($id -eq 'UC-129') { return 'Pregunta bloquejant: quin sistema mana per usuari, correu, curs, aula, rol i matrícula, i quines correccions poden automatitzar-se? Responsable: gestió acadèmica i responsable tècnica.' }
    if ($state -match 'BLOQUEJANT|PENDENT|DISSENY|PARCIAL') {
        return "Pregunta: quines variants, permisos, dades reals i criteri fiscal s’han de validar per tancar «$title»? Responsable: negoci/facturació i responsable tècnica SIF."
    }
    return "Pregunta: queda alguna variant productiva de «$title» fora de les proves i pantalles inventariades? Responsable: responsable funcional i responsable tècnica SIF."
}

function Get-SpecificInput([string]$id) {
    switch ($id) {
        'UC-106' { return 'Producte i edició, persona inscrita, places, preu/regla versionats, pagador provisional, receptor provisional, canal, caducitat i clau idempotent.' }
        'UC-107' { return 'Identitat normalitzada, producte, edició, reserva existent, estat de pagament/factura i identificadors de reintent.' }
        'UC-108' { return 'Tastet/repte, identitat, correu, accés temporal i consentiment de mailing independent.' }
        'UC-109' { return 'Curs subvencionat, programa/finançador, elegibilitat, import finançat, possible copagament, receptor i evidència.' }
        'UC-110' { return "Dues persones, dos cursos/edicions, preus base, percentatge/regla d’amics, pagador escollit i receptor fiscal." }
        'UC-111' { return 'Identitat, titulació/evidència, estat de validació, curs origen, pagament confirmat i regla/caducitat del benefici futur.' }
        'UC-112' { return 'Producte/edició/places, parts, receptor, línies, preu, descompte, impostos, import, moneda i versió de totes les regles.' }
        'UC-113' { return 'Fitxer o alta manual, format/versió, cada fila original, actor, origen, persona, producte/edició, estat acadèmic, classificació econòmica i clau idempotent.' }
        'UC-114' { return 'Producte/edició, versió vigent, camps abans/després, data efectiva, motiu, operacions obertes afectades i política de consentiment/cancel·lació.' }
        'UC-115' { return "Producte, edició/aula, quantitat, capacitat versionada, titular, prioritat, venciment, llista d’espera i versió de lock." }
        'UC-116' { return 'Tipus de descompte, subjecte, regla, un o més documents, hash, finalitat, data, consentiment/base jurídica, validador i política de retenció.' }
        'UC-117' { return 'Tipus de dret, codi hash, titular, origen, regla/valor, productes aplicables, acumulabilitat, emissió, caducitat i clau de consum.' }
        'UC-118' { return 'Grup, responsable, participants, cursos/edicions, estat de cada plaça, tram/regla, pagador, receptor, línies i moment de bloqueig.' }
        'UC-119' { return 'Regal, comprador, beneficiari, valor/producte, codi hash, entrega, activació, caducitat, operació de compra i operació de bescanvi.' }
        'UC-120' { return 'Sol·licitant, perfil/camp, valor abans/després, justificació, evidència, abast de propagació, operacions obertes i snapshots emesos afectats.' }
        'UC-121' { return 'Operació/reserva caducada, snapshot anterior, disponibilitat actual, preu/regla vigents, diferència, venciment nou i acceptació del pagador.' }
        'UC-122' { return 'Pack/version, components ordenats, obligatorietat, places, quantitat, preu, descompte i impost per línia, substitucions admeses i estat de cada component.' }
        'UC-123' { return 'Factura emesa, preferència/consentiment, format i versió, destinatari/canal verificats, plantilla, document immutable, hash i política de reintent.' }
        'UC-124' { return 'Inscripció, operació, persona, pagador/responsable, baixa/pròrroga, saldo/deute, accés, matrícula Moodle, superació, certificat i regla versionada.' }
        'UC-125' { return 'Subjecte, finalitat, canal, abast, versió del text, base jurídica, resposta, font/referència, dates de captura/confirmació/retirada i evidència hash.' }
        'UC-126' { return 'Identificadors de web, intranet, Moodle antic/nou i llegat; DNI/correu normalitzats, candidats, operacions afectades, evidències i decisió de vincular, separar o escalar.' }
        'UC-127' { return 'Producte/edició, estat abans/després, data efectiva, motiu, reserves, inscripcions, pagaments, factures, accessos, tutors, alternatives i política per afectat.' }
        'UC-128' { return "Subjecte/operació, rol de l’adreça, entrada original, CP, població, país, font, regla de normalització, proposta, estat fiscal/comercial i correlació." }
        'UC-129' { return 'Execució, sistema origen/destí, edició/aula, usuari, correu, rol, matrícula, accés, snapshots/hash, diferència, severitat i acció de resolució.' }
        default { return $null }
    }
}

function Get-SpecificRule([string]$id) {
    switch ($id) {
        'UC-106' { return "Crear una inscripció no implica cobrar ni facturar; la seva màquina d’estats és pròpia i conserva qui la va iniciar i fins quan reserva plaça." }
        'UC-107' { return "Mateixa persona, producte i edició amb una reserva activa és conflicte o reutilització; mai s’obté un nou efecte econòmic només repetint la petició." }
        'UC-108' { return 'Gratuïtat, alta acadèmica i consentiment comercial són tres decisions separades; el mailing no es pot deduir de la gratuïtat.' }
        'UC-109' { return "Preu zero per a l’alumne no resol qui suporta la contraprestació ni qui és receptor fiscal; el flux queda bloquejat fins a classificació aprovada." }
        'UC-110' { return "El percentatge s’aplica a cada curs segons la seva base; un IDPAG compartit no fusiona participants, cursos ni receptors." }
        'UC-111' { return 'El dret futur es crea una sola vegada després de validació i cobrament; conserva origen, titular, import/regla, caducitat i consum.' }
        'UC-112' { return 'Redsys i el worker no consulten preus, places o descomptes vius: comparen import i materialitzen exclusivament el snapshot acceptat abans del TPV.' }
        'UC-113' { return "Cada fila és independent i idempotent: pot crear/reutilitzar una inscripció o quedar rebutjada, però mai dedueix cobrament, factura o pagador d’un import o estat acadèmic." }
        'UC-114' { return 'Canviar dades mestres crea una versió i una decisió per operació oberta; cap reserva acceptada ni document emès passa silenciosament a valors nous.' }
        'UC-115' { return "La capacitat es consumeix mitjançant reserves atòmiques amb venciment; confirmar, alliberar, expirar o passar de llista d’espera són events, no recomptes eventuals sense lock." }
        'UC-116' { return "Les evidències sensibles no s’envien com a únic registre per correu ni es guarden al webroot; accés, decisió i supressió són traçables i mínims." }
        'UC-117' { return 'Un dret només es consumeix una vegada sota lock; reservar-lo durant el checkout no equival a consumir-lo i una reversió crea un event nou.' }
        'UC-118' { return 'El tram es calcula sobre membres elegibles i queda congelat amb les línies; canviar participants després del bloqueig recalcula abans de cobrar o obre un canvi classificat.' }
        'UC-119' { return 'Regal, codi, pagament de compra, beneficiari i inscripció de bescanvi són objectes separats; cada transició conserva qui la va executar i la relació causal.' }
        'UC-120' { return 'La sol·licitud no modifica dades fins a aprovació; la propagació és camp/sistema específica i exclou factures, registres fiscals i snapshots històrics.' }
        'UC-121' { return 'Una operació expirada no es reactiva: disponibilitat i preu es recalculen en una versió nova que el pagador accepta abans de crear un altre enllaç.' }
        'UC-122' { return 'Pack i components tenen versions i estats propis; no es pot facturar una descripció agregada que impedeixi reconstruir preu, fiscalitat, plaça o devolució de cada línia.' }
        'UC-123' { return '`E_FACT` és una preferència/ordre, no prova de generació ni entrega; cada artefacte i intent conserva format, versió, hash, destinatari, canal i resultat.' }
        'UC-124' { return "La decisió acadèmica consulta l’estat econòmic canònic però no el modifica; qualsevol alta/baixa Moodle, accés o certificat crea un event amb regla i causalitat." }
        'UC-125' { return 'Absència de resposta no és consentiment; cada finalitat/canal té estat propi i retirar-lo crea un event nou sense esborrar la prova històrica mínima.' }
        'UC-126' { return 'Cap coincidència parcial fusiona persones automàticament quan pot barrejar operacions o documents; els enllaços externs són versionats i els conflictes exigeixen decisió auditada.' }
        'UC-127' { return "Un canvi d’edició crea una llista completa i immutable d’afectats; cada impacte es resol una vegada i cap correu o baixa massiva substitueix la decisió econòmica/fiscal." }
        'UC-128' { return 'La dada original i la normalitzada coexisteixen amb hash i regla; només la decisió aprovada es propaga i mai a snapshots o factures ja emesos.' }
        'UC-129' { return 'La reconciliació és per execució i ítem, bidireccional però amb autoritat per camp; cada correcció és idempotent, reversible quan sigui possible i separada dels moviments econòmics.' }
        default { return $null }
    }
}

function Get-SpecificFlow([string]$id) {
    switch ($id) {
        'UC-106' { return "1) validar persona/producte/edició; 2) bloquejar la clau de reserva; 3) crear o reutilitzar commercial_operation; 4) afegir parts i snapshot provisional; 5) reservar plaça amb caducitat; 6) retornar UUID_OPERATION sense factura ni cobrament." }
        'UC-107' { return "1) normalitzar identitat; 2) cercar reserves/inscripcions actives sota lock; 3) reutilitzar el reintent equivalent o bloquejar el conflicte; 4) registrar decisió i retornar l’operació existent, sense nou IDPAG ni comunicació duplicada." }
        'UC-108' { return "1) validar tastet actiu i duplicat; 2) crear reserva FREE_SAMPLE; 3) registrar participant i accés temporal; 4) guardar consentiment de mailing separadament; 5) confirmar inscripció sense crear factura, pagament o URL de pagament." }
        'UC-109' { return "1) crear reserva; 2) capturar programa, finançador i evidència d’elegibilitat; 3) classificar SUBSIDISED_PENDING_DECISION; 4) sotmetre receptor/document a revisió; 5) només després derivar a l’efecte aprovat." }
        'UC-110' { return "1) crear operació conjunta; 2) registrar dos participants/cursos i el pagador; 3) calcular i validar el descompte per línia; 4) decidir receptor i composició de factura; 5) congelar snapshot; 6) crear un únic enllaç/intenció per l’import aprovat." }
        'UC-111' { return "1) registrar sol·licitud i evidència; 2) validar titulació; 3) esperar cobrament confirmat del curs origen; 4) crear una sola vegada el dret futur amb titular, regla i caducitat; 5) comunicar-lo després del commit." }
        'UC-112' { return "1) rellegir places, producte i regles versionades; 2) validar parts/receptor; 3) calcular línies, descompte i fiscalitat; 4) serialitzar i hashejar el snapshot; 5) crear enllaç/intenció; 6) exigir que callback i worker usin aquest snapshot." }
        'UC-113' { return '1) crear execució amb hash del fitxer/ordre; 2) validar format i permisos; 3) processar cada fila sota idempotència; 4) crear o reutilitzar operació/inscripció sense efecte econòmic; 5) conservar errors per fila; 6) tancar totals i evidència.' }
        'UC-114' { return '1) llegir versió i operacions obertes sota lock; 2) previsualitzar abans/després; 3) aprovar la nova versió; 4) mantenir snapshot, demanar consentiment o cancel·lar segons regla; 5) registrar cada decisió; 6) publicar la versió efectiva.' }
        'UC-115' { return "1) bloquejar comptador/ledger d’edició; 2) verificar capacitat; 3) crear reserva o entrada de llista d’espera; 4) confirmar amb l’operació; 5) expirar/alliberar idempotentment; 6) promocionar el següent candidat amb event." }
        'UC-116' { return '1) obrir validació; 2) pujar a custòdia protegida i calcular hash; 3) registrar metadades mínimes; 4) revisar amb accés auditat; 5) acceptar/rebutjar amb regla versionada; 6) programar retenció/supressió i derivar el descompte.' }
        'UC-117' { return '1) emetre dret idempotent; 2) comunicar el codi sense guardar-lo en clar; 3) validar titular/regla/caducitat; 4) reservar durant checkout; 5) consumir al commit; 6) expirar, cancel·lar o revertir amb events append-only.' }
        'UC-118' { return '1) crear grup i responsable; 2) afegir participants/línies i reservar places; 3) recalcular tram; 4) validar pagador/receptor; 5) bloquejar composició/snapshot; 6) crear una intenció; 7) classificar qualsevol canvi posterior.' }
        'UC-119' { return '1) crear compra de regal; 2) emetre/lliurar el dret; 3) validar codi i beneficiari; 4) reservar i consumir en una operació de bescanvi; 5) vincular inscripció sense copiar pagament; 6) gestionar canvi, expiració o devolució amb decisió.' }
        'UC-120' { return '1) crear sol·licitud amb diferències; 2) autenticar i validar evidència; 3) revisar impacte en sistemes/operacions; 4) aprovar o rebutjar; 5) propagar només a destinacions admeses; 6) conservar resultats i exclusions històriques.' }
        'UC-121' { return '1) detectar expiració; 2) revocar enllaç i alliberar plaça; 3) recalcular preu/capacitat amb versions vigents; 4) mostrar diferència; 5) obtenir acceptació; 6) crear operació/reserva/enllaç nous correlacionats.' }
        'UC-122' { return "1) carregar versió del pack; 2) crear línia pare i components; 3) reservar plaça per component; 4) congelar preu/descompte/impost; 5) validar disponibilitat total; 6) davant canvi, proposar substitució, baixa parcial o cancel·lació i classificar-ne l’efecte." }
        'UC-123' { return '1) verificar factura emesa i preferència; 2) generar artefacte versionat; 3) calcular hash i custodiar; 4) crear entrega a destinatari/canal verificat; 5) registrar cada intent/resultat; 6) permetre reintent o descàrrega auditada sense regeneració silenciosa.' }
        'UC-124' { return '1) llegir inscripció, estat acadèmic i econòmic; 2) aplicar regla versionada; 3) decidir accés/Moodle/certificat sense mutar pagaments; 4) executar adaptador idempotent; 5) registrar abans/després i notificació; 6) reconciliar divergències.' }
        'UC-125' { return '1) mostrar text/finalitat versionats; 2) capturar resposta i font; 3) confirmar quan cal; 4) activar o denegar per canal/abast; 5) propagar a la llista de comunicacions; 6) retirar o renovar amb event i evidència.' }
        'UC-126' { return '1) normalitzar identificadors; 2) cercar enllaços i candidats; 3) bloquejar conflictes amb impacte; 4) decidir vincular/separar/escalar; 5) actualitzar enllaços externs i propagar només camps autoritzats; 6) reconciliar operacions i accessos sense fusionar històrics.' }
        'UC-127' { return "1) bloquejar i versionar l’edició; 2) crear event i inventari d’afectats; 3) decidir alternativa per cada operació; 4) executar canvis acadèmics i de capacitat; 5) derivar cada efecte econòmic/fiscal al cas corresponent; 6) notificar després dels commits i reconciliar totals." }
        'UC-128' { return '1) conservar entrada original; 2) validar/normalitzar amb regla versionada; 3) obrir cas si no hi ha coincidència; 4) revisar i aprovar/rebutjar; 5) propagar a dades vives o operacions obertes; 6) excloure i registrar snapshots emesos.' }
        'UC-129' { return '1) crear reconciliation_run amb abast/hash; 2) llegir Prisma i Moodle; 3) generar ítems per absent, sobrant, correu, rol, curs o aula; 4) aplicar autoritat/regla; 5) corregir idempotentment o escalar; 6) tancar resum i verificar que no hi ha efecte econòmic.' }
        default { return $null }
    }
}

function Get-SpecificTest([string]$id) {
    switch ($id) {
        'UC-106' { return "Provar reserva nova, reintent, plaça esgotada, expiració/alliberament, dues peticions concurrents i absència de factura/pagament." }
        'UC-107' { return "Provar mateix DNI i correu, correu amb document diferent, mateixa persona en edició diferent, reserva cancel·lada, doble clic concurrent i comunicació única." }
        'UC-108' { return "Provar tastet actiu/inactiu, inscripció duplicada, accés d’una setmana, import zero sense objectes fiscals i consentiment de mailing sí/no amb evidència separada." }
        'UC-109' { return "Provar elegible/no elegible, finançador i receptor coneguts/desconeguts, possible copagament i bloqueig absolut de factura zero mentre la decisió fiscal sigui pendent." }
        'UC-110' { return "Provar cursos iguals/diferents, imports diferents, cada opció de pagador/receptor, percentatge per línia, duplicat, places concurrents i un sol moviment assignat correctament." }
        'UC-111' { return "Provar evidència acceptada/rebutjada, pagament pendent/confirmat, reintent del worker, caducitat, consum i impossibilitat de crear dos drets futurs." }
        'UC-112' { return "Provar canvi de preu/places/descompte després de redirigir, callback duplicat o import divergent, snapshot caducat i impossibilitat que el worker consulti dades vives." }
        'UC-113' { return 'Provar fitxer mixt, fila duplicada, reexecució del mateix hash, error parcial, inscripció existent i absència absoluta de factura/pagament inferits.' }
        'UC-114' { return 'Provar canvi de nom, dates, hores, preu i fiscalitat amb cap reserva, reserva oberta, TPV iniciat i factura emesa; verificar snapshots i consentiment.' }
        'UC-115' { return "Provar última plaça concurrent, expiració, confirmació contra expiració, llista d’espera, sobreaforament denegat/autoritzat i recompte reconstruïble." }
        'UC-116' { return 'Provar diversos documents, tipus MIME/mida invàlids, hash, accés denegat, acceptació/rebuig, retenció i supressió sense perdre la decisió auditada.' }
        'UC-117' { return 'Provar emissió duplicada, codi incorrecte, titular diferent, reserva concurrent, consum únic, caducitat, acumulació denegada i reversió.' }
        'UC-118' { return 'Provar altes/baixes abans i després del bloqueig, canvi de tram, cursos diferents, places concurrents, pagador/receptor diferents i una sola intenció.' }
        'UC-119' { return 'Provar compra i bescanvi per persones diferents, codi duplicat/caducat, canvi de beneficiari, bescanvi parcial denegat/admesa segons regla, devolució i factura correcta.' }
        'UC-120' { return "Provar canvi de DNI, nom, correu i adreça; aprovació/rebuig, propagació parcial amb retry, factura existent immutable i accés a l’expedient limitat." }
        'UC-121' { return 'Provar mateix preu/plaça, pujada/baixada, plaça esgotada, acceptació rebutjada, enllaç antic revocat i callback tardà contra snapshot caducat.' }
        'UC-122' { return 'Provar pack complet, component sense plaça, substitució, baixa parcial, impostos diferents, canvi després de cobrar i reconciliació línia-operació-factura.' }
        'UC-123' { return 'Provar formats/canals admesos, destinatari no verificat, document immutable, hash, duplicat idempotent, error temporal/permanent, reintent i evidència de lliurament.' }
        'UC-124' { return 'Provar superat/no superat amb deute, pròrroga, pagador de grup, baixa, Moodle ja divergent, retry i garantia que cap transició altera pagaments o factura.' }
        'UC-125' { return 'Provar sí/no/sense resposta, doble confirmació, text nou, finalitats i canals independents, retirada, realta, propagació fallida amb retry i inscripció/factura inalterades.' }
        'UC-126' { return 'Provar mateix DNI amb correus/usuaris diferents, mateix correu amb DNI diferent, Moodle antic/nou, fals positiu, fusió rebutjada, resolució concurrent i històrics separats.' }
        'UC-127' { return "Provar edició pendent, activa, ajornada, tancada i cancel·lada amb reserva, llista d’espera, pagament pendent/pagat, factura emesa, grup/pack/regal, retry i recompte complet d’afectats." }
        'UC-128' { return 'Provar CP/població coneguts i desconeguts, accents/variants, país estranger, normalització rebutjada, dues revisions concurrents i factura existent immutable.' }
        'UC-129' { return 'Provar usuari absent/sobrant, correu diferent, rol incorrecte, curs/aula invisible, Moodle antic/nou, reexecució, error parcial i absència de qualsevol mutació econòmica.' }
        default { return $null }
    }
}

function Get-SpecificSourceIds([string]$id) {
    switch ($id) {
        { $_ -in @('UC-106', 'UC-107', 'UC-109', 'UC-111', 'UC-112') } { return 'SRC-014, SRC-009, SRC-013' }
        'UC-108' { return 'SRC-015, SRC-009, SRC-013' }
        'UC-110' { return 'SRC-016, SRC-009, SRC-013' }
        'UC-113' { return 'SRC-017, SRC-009, SRC-024' }
        'UC-114' { return 'SRC-017, SRC-009, SRC-024' }
        'UC-115' { return 'SRC-017, SRC-014, SRC-024' }
        'UC-116' { return 'SRC-017, SRC-022, SRC-007, SRC-024' }
        'UC-117' { return 'SRC-021, SRC-014, SRC-024' }
        'UC-118' { return 'SRC-020, SRC-009, SRC-024' }
        'UC-119' { return 'SRC-019, SRC-009, SRC-024' }
        'UC-120' { return 'SRC-023, SRC-017, SRC-007, SRC-024' }
        'UC-121' { return 'SRC-014, SRC-009, SRC-024' }
        'UC-122' { return 'SRC-018, SRC-009, SRC-024' }
        'UC-123' { return 'SRC-017, SRC-005, SRC-024' }
        'UC-124' { return 'SRC-017, SRC-023, SRC-009, SRC-024' }
        'UC-125' { return 'SRC-025, SRC-026, SRC-017, SRC-027' }
        'UC-126' { return 'SRC-017, SRC-023, SRC-007, SRC-027' }
        'UC-127' { return 'SRC-017, SRC-009, SRC-027' }
        'UC-128' { return 'SRC-014, SRC-017, SRC-027' }
        'UC-129' { return 'SRC-017, SRC-023, SRC-009, SRC-027' }
        default { return 'SRC-001, SRC-009' }
    }
}

function Read-Cases {
    $cases = [ordered]@{}
    foreach ($line in Get-Content -LiteralPath $catalogPath) {
        if ($line -notmatch '^\|\s*(UC-\d+[a-z]?)\s*\|') { continue }
        $cells = $line.Trim().Trim('|').Split('|') | ForEach-Object { $_.Trim() }
        $id = $cells[0]
        if ($cases.Contains($id)) { continue }
        $actor = if ($cells.Count -ge 5 -and $cells[2] -notmatch '^\[') { $cells[2] } else { $actorOverrides[$id] }
        if ([string]::IsNullOrWhiteSpace($actor)) { $actor = 'Actor o procés autoritzat segons el cas' }
        $stateCell = $cells | Where-Object { $_ -match '\[[A-ZÀ-Ü/ ]+\]' } | Select-Object -First 1
        $result = if ($cells.Count -ge 5) { $cells[$cells.Count - 1] } else { "Executar «$($cells[1])» sense duplicats ni mutacions fiscals fora del SIF." }
        $cases[$id] = [ordered]@{
            id = $id
            title = $cells[1]
            actor = $actor
            state = if ($stateCell) { $stateCell } else { '[DISSENY]' }
            result = $result
        }
    }
    if (-not $cases.Contains('UC-86')) {
        $cases['UC-86'] = [ordered]@{
            id = 'UC-86'
            title = 'Registrar qualsevol acció sobre un pagament'
            actor = $actorOverrides['UC-86']
            state = '[DISSENY/BLOQUEJANT]'
            result = 'Cronologia universal append-only de petició, decisió i resultat per a tots els entorns.'
        }
    }
    return $cases.Values | Sort-Object @{Expression={Get-NumericId $_.id}}, @{Expression={$_.id}}
}

function Write-Card([System.Collections.IDictionary]$case) {
    $number = Get-NumericId $case.id
    $prefix = $case.id.Replace('-', '').ToUpperInvariant()
    $fileName = Get-FileName $case.id
    $domain = Get-Domain $number
    $channels = Get-Channels $number
    $persistence = Get-Persistence $number
    $fiscalRule = Get-FiscalRule $number
    $paymentRule = Get-PaymentRule $number
    $decision = Get-Decision $case.id $case.title $case.state
    $specificInput = Get-SpecificInput $case.id
    $specificRule = Get-SpecificRule $case.id
    $specificFlow = Get-SpecificFlow $case.id
    $specificTest = Get-SpecificTest $case.id
    $specificSources = Get-SpecificSourceIds $case.id
    $isPayment = $number -in $paymentCases
    $blocking = if ($case.state -match 'BLOQUEJANT|PENDENT') { 'SÍ' } else { 'NO' }
    $implementation = if ($case.state -match 'BASE' -and $case.state -notmatch 'PARCIAL|DISSENY') { 'PARTIAL_CODE_AVAILABLE' } else { 'NOT_COMPLETE' }

    $manifestSources = [ordered]@{}
    foreach ($entry in $sourceDefinitions.GetEnumerator()) {
        $manifestSources[$entry.Key] = $entry.Value
    }
    foreach ($sourceId in ($specificSources -split ',\s*')) {
        if ($codeSourceDefinitions.Contains($sourceId)) {
            $manifestSources[$sourceId] = $codeSourceDefinitions[$sourceId]
        }
    }

    $sourceLines = foreach ($entry in $manifestSources.GetEnumerator()) {
        $absolute = Join-Path $repoRoot $entry.Value
        if (-not (Test-Path -LiteralPath $absolute)) { throw "Falta la font $($entry.Value)" }
        $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $absolute).Hash.ToLowerInvariant()
        $sourceStatus = if ($entry.Key -in @('SRC-024', 'SRC-027')) {
            'DISSENY_MATERIALITZAT_NO_APLICAT'
        } elseif ($codeSourceDefinitions.Contains($entry.Key)) {
            'OBSERVADA'
        } elseif ($entry.Key -eq 'SRC-011') {
            'AUDITADA_AMB_LIMITS'
        } else {
            'AUTORITZADA'
        }
        "- $($entry.Key) | $($entry.Value) | SHA-256: $hash | $sourceStatus"
    }

    $lines = [System.Collections.Generic.List[string]]::new()
    $lines.Add("# Fitxa funcional — $($case.id) — $($case.title)")
    $lines.Add('')
    $lines.Add('## 1. Metadades')
    $lines.Add('')
    $lines.Add('**Versió de la fitxa:** 1.1')
    $lines.Add('')
    $lines.Add('**Estat de preparació:** STRUCTURED_DRAFT_NEEDS_CASE_REVIEW')
    $lines.Add('')
    $lines.Add("**Estat d’implementació:** $implementation")
    $lines.Add('')
    $lines.Add("**Domini funcional:** $domain")
    $lines.Add('')
    $lines.Add('## 2. Manifest de fonts')
    $lines.Add('')
    foreach ($sourceLine in $sourceLines) { $lines.Add($sourceLine) }
    $lines.Add('')
    $lines.Add('## 3. Resum funcional')
    $lines.Add('')
    $lines.Add("- [$prefix-SUM-001] [CONFIRMAT] Objectiu canònic: $($case.result) | Fonts: SRC-001 | Bloqueja: NO")
    $lines.Add("- [$prefix-SUM-002] [CONFIRMAT] La fitxa cobreix «$($case.title)» com a cas de $domain i no com una simple pantalla. | Fonts: SRC-001, SRC-009 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 4. Actors i permisos')
    $lines.Add('')
    $lines.Add("- [$prefix-ACT-001] [CONFIRMAT] Actor principal: $($case.actor). El servidor autentica identitat, rol, abast i estat abans d’executar o retornar dades. | Fonts: SRC-001, SRC-007 | Bloqueja: NO")
    $lines.Add("- [$prefix-ACT-002] [CONFIRMAT] Canal previst: $channels. Ocultar un botó no substitueix l’autorització del backend. | Fonts: SRC-004, SRC-007 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 5. Precondicions')
    $lines.Add('')
    $lines.Add("- [$prefix-PRE-001] [CONFIRMAT] La identitat, el `REQUEST_ID`, el `CORRELATION_ID`, la versió de l’objecte, el motiu i la clau idempotent existeixen abans del commit. | Fonts: SRC-005, SRC-007, SRC-009 | Bloqueja: NO")
    $lines.Add("- [$prefix-PRE-002] [CONFIRMAT] La factura emesa és immutable i l’estat vigent es rellegeix amb control de concurrència abans de decidir. | Fonts: SRC-002, SRC-003 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add("## 6. Dades d’entrada")
    $lines.Add('')
    $lines.Add("- [$prefix-IN-001] [CONFIRMAT] Entren l’identificador d’origen, actor i canal, estat de factura/pagament/AEAT, import i divisa quan pertoqui, receptor, motiu estructurat i dades específiques de «$($case.title)». | Fonts: SRC-001, SRC-002, SRC-008 | Bloqueja: NO")
    $lines.Add("- [$prefix-IN-002] [CONFIRMAT] No entren secrets, PAN/CVV, signatures completes, tokens reutilitzables ni dades sensibles no necessàries. | Fonts: SRC-007, SRC-009 | Bloqueja: NO")
    if ($specificInput) {
        $lines.Add("- [$prefix-IN-003] [OBSERVAT/PROPOSTA] $specificInput | Fonts: $specificSources | Bloqueja: $blocking")
    }
    if ($number -in $fiscalCases) {
        $lines.Add("- [$prefix-IN-FISC-001] [CONFIRMAT] Abans d’emetre s’han de congelar emissor, receptor, sèrie/número, dates, tipus de factura, descripció, línies, base, règim, tipus i quota d’IVA, inversió del subjecte passiu, recàrrec d’equivalència i causa d’exempció/no subjecció quan pertoqui. | Fonts: SRC-008, SRC-012, SRC-013 | Bloqueja: SÍ")
    }
    $lines.Add('')
    $lines.Add('## 7. Càlculs i regles de negoci')
    $lines.Add('')
    $lines.Add("- [$prefix-RUL-001] [CONFIRMAT] Imports i diferències es calculen amb decimals, a partir de snapshots validats; el client no decideix totals ni impacte fiscal. | Fonts: SRC-002, SRC-003 | Bloqueja: NO")
    $lines.Add("- [$prefix-RUL-002] [CONFIRMAT] $fiscalRule | Fonts: SRC-002, SRC-005, SRC-009 | Bloqueja: NO")
    if ($specificRule) {
        $lines.Add("- [$prefix-RUL-003] [OBSERVAT/PROPOSTA] $specificRule | Fonts: $specificSources | Bloqueja: $blocking")
    }
    $lines.Add('')
    $lines.Add('## 8. Dades de sortida i postcondicions')
    $lines.Add('')
    $lines.Add("- [$prefix-OUT-001] [CONFIRMAT] La resposta retorna resultat tipificat, UUIDs creats o reutilitzats, estats fiscal/econòmic/documental i correlació; no presenta èxit si falta la persistència obligatòria. | Fonts: SRC-003, SRC-005, SRC-009 | Bloqueja: NO")
    $lines.Add("- [$prefix-OUT-002] [CONFIRMAT] Persistència mínima del cas: $persistence. | Fonts: SRC-003, SRC-010 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 9. Flux principal')
    $lines.Add('')
    $lines.Add("- [$prefix-FLOW-001] [CONFIRMAT] 1) El canal presenta l’estat real i la previsualització; 2) l’usuari o procés confirma; 3) el gateway valida permisos, dades, idempotència i concurrència; 4) registra la gestió i executa la decisió al SIF; 5) persisteix resultat i retorna l’estat. | Fonts: SRC-002, SRC-004, SRC-005, SRC-009 | Bloqueja: NO")
    if ($specificFlow) {
        $lines.Add("- [$prefix-FLOW-002] [OBSERVAT/PROPOSTA] $specificFlow | Fonts: $specificSources | Bloqueja: $blocking")
    }
    $lines.Add('')
    $lines.Add('## 10. Fluxos alternatius')
    $lines.Add('')
    $lines.Add("- [$prefix-ALT-001] [CONFIRMAT] Reintent equivalent reutilitza el resultat; `NO_CHANGE` no crea moviments; una variant amb dades o imports contradictoris deriva a revisió o incidència. | Fonts: SRC-005, SRC-006, SRC-009 | Bloqueja: NO")
    $lines.Add("- [$prefix-ALT-002] [PROPOSTA] Si l’impacte no es pot classificar de manera determinista, conservar la petició com a pendent i exigir decisió humana abans de cap mutació. | Fonts: SRC-009 | Bloqueja: $blocking")
    $lines.Add('')
    $lines.Add('## 11. Errors, bloquejos i recuperació')
    $lines.Add('')
    $lines.Add("- [$prefix-ERR-001] [CONFIRMAT] Falta de permís, validació, traça, lock, document, certificat o integració bloqueja l’acció; l’error queda correlacionat i no s’aplica una escriptura alternativa al llegat. | Fonts: SRC-005, SRC-006, SRC-007, SRC-009 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 12. Impacte fiscal')
    $lines.Add('')
    $lines.Add("- [$prefix-FISC-001] [CONFIRMAT] $fiscalRule | Fonts: SRC-002, SRC-003, SRC-005, SRC-009 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 13. Factures, rectificatives, pagaments, devolucions i saldos')
    $lines.Add('')
    $lines.Add("- [$prefix-FIN-001] [CONFIRMAT] $paymentRule | Fonts: SRC-002, SRC-003, SRC-009, SRC-010 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 14. Impacte VERI*FACTU/AEAT')
    $lines.Add('')
    $lines.Add("- [$prefix-AEAT-001] [CONFIRMAT] Només un registre fiscal tipificat entra a cadena i cua AEAT; cada intent/resposta queda a aeat_submission_attempt, i un error no desfà la factura ni autoritza reescriure-la. | Fonts: SRC-003, SRC-005, SRC-010 | Bloqueja: NO")
    if ($number -in $fiscalCases) {
        $lines.Add("- [$prefix-AEAT-002] [CONFIRMAT] El registre conserva emissor, identificació del SIF/productor, versió, zona horària, hash anterior i dades suficients per generar XML, PDF i QR; no es delega aquest snapshot al document final. | Fonts: SRC-008, SRC-012, SRC-013 | Bloqueja: SÍ")
    }
    $lines.Add('')
    $lines.Add('## 15. Components afectats')
    $lines.Add('')
    $lines.Add("- [$prefix-COMP-001] [CONFIRMAT] Components: adaptador del canal ($channels), gateway d’ordres, servei de domini, repositoris SIF, panell/worker quan correspongui i sincronització llegada només posterior al commit. | Fonts: SRC-001, SRC-004, SRC-009 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 16. Auditoria, concurrència i idempotència')
    $lines.Add('')
    $auditText = if ($isPayment) { "L’intent ``REQUESTED`` i el resultat terminal es correlacionen a ``payment_action_event``; la mutació correcta i el terminal comparteixen transacció, i una correlació incompleta obre incidència." } else { "La comanda i el resultat es correlacionen a ``operational_event``/``sif_audit_event``; els snapshots abans/després i els hashes permeten reconstruir la decisió." }
    $lines.Add("- [$prefix-AUD-001] [CONFIRMAT] $auditText | Fonts: SRC-009, SRC-010 | Bloqueja: NO")
    $lines.Add("- [$prefix-AUD-002] [CONFIRMAT] Cap event històric s’edita o s’esborra des de l’aplicació; una correcció afegeix events i, quan cal, contramoviments o registres nous. | Fonts: SRC-003, SRC-009 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 17. Notificacions i correus')
    $lines.Add('')
    $lines.Add("- [$prefix-NOT-001] [CONFIRMAT] Qualsevol comunicació es crea a l’outbox després del commit, usa plantilla versionada i destinatari verificat, i no promet factura, cobrament o document abans que l’estat ho permeti. | Fonts: SRC-004, SRC-005, SRC-010 | Bloqueja: NO")
    $lines.Add('')
    $lines.Add('## 18. Casos de prova')
    $lines.Add('')
    $lines.Add("- [$prefix-TEST-001] [CONFIRMAT] Provar recorregut nominal de «$($case.title)», reintent idempotent, permís denegat, dades invàlides, conflicte concurrent, fallada de dependència i reconstrucció completa de la traça. | Fonts: SRC-006 | Bloqueja: NO")
    $lines.Add("- [$prefix-TEST-002] [CONFIRMAT] Si el cas toca pagaments, provar intent/resultat des de cada canal activat i bloqueig total quan el ledger no està disponible. | Fonts: SRC-006, SRC-009 | Bloqueja: NO")
    if ($specificTest) {
        $lines.Add("- [$prefix-TEST-003] [PROPOSTA] $specificTest | Fonts: $specificSources, SRC-006 | Bloqueja: $blocking")
    }
    $lines.Add('')
    $lines.Add('## 19. Conflictes, buits i traçabilitat')
    $lines.Add('')
    $lines.Add("- [$prefix-GAP-001] [CONFIRMAT] Estat documental/codi actual: $($case.state). Aquesta fitxa no converteix el disseny en implementació ni acredita dades, permisos o entorn productiu. | Fonts: SRC-001, SRC-009 | Bloqueja: $blocking")
    $lines.Add("- [$prefix-GAP-002] [CONFIRMAT] La reconciliació Trello disponible és de cobertura per correspondència, no una incorporació automàtica de totes les descripcions i checklists. Aquesta fitxa continua sent esborrany fins a revisió de contingut i evidència específica. | Fonts: SRC-011 | Bloqueja: SÍ")
    $lines.Add('')
    $lines.Add('## 20. Decisions pendents')
    $lines.Add('')
    $lines.Add("- [$prefix-DEC-001] [PENDENT] $decision | Fonts: SRC-001, SRC-009 | Bloqueja: $blocking")
    $lines.Add('')
    $lines.Add('## 21. Tasques de programació')
    $lines.Add('')
    $lines.Add("- [$prefix-DEV-001] [PROPOSTA] Implementar o completar adaptador, autorització servidor, servei, persistència ($persistence), pantalla/worker, proves i evidència de preproducció específics de «$($case.title)». | Fonts: SRC-001, SRC-006, SRC-009, SRC-010 | Bloqueja: NO")

    Set-Content -LiteralPath (Join-Path $outputDirectory $fileName) -Value $lines -Encoding utf8
}

function Test-Catalog([array]$cases) {
    $errors = [System.Collections.Generic.List[string]]::new()
    if ($cases.Count -ne 142) {
        $errors.Add("S’esperaven 142 casos/variants i se n’han detectat $($cases.Count).")
    }
    $numeric = $cases | Where-Object { $_.id -match '^UC-\d+$' }
    if ($numeric.Count -ne 129) {
        $errors.Add("S’esperaven 129 identificadors numèrics i se n’han detectat $($numeric.Count).")
    }
    foreach ($case in $cases) {
        $path = Join-Path $outputDirectory (Get-FileName $case.id)
        if (-not (Test-Path -LiteralPath $path)) {
            $errors.Add("Falta la fitxa $($case.id): $path")
            continue
        }
        $content = Get-Content -Raw -LiteralPath $path
        foreach ($heading in $requiredHeadings) {
            if (-not $content.Contains($heading)) {
                $errors.Add("$($case.id) no conté l’apartat: $heading")
            }
        }
        if ($content -match '\{\{[^}]+\}\}') {
            $errors.Add("$($case.id) conté placeholders sense resoldre.")
        }
        if ([regex]::Matches($content, '(?m)^## ').Count -ne 21) {
            $errors.Add("$($case.id) no conté exactament 21 apartats.")
        }
        if (-not $content.Contains('STRUCTURED_DRAFT_NEEDS_CASE_REVIEW')) {
            $errors.Add("$($case.id) no declara honestament l’estat d’esborrany estructurat.")
        }
        if (-not $content.Contains('Fonts: SRC-011')) {
            $errors.Add("$($case.id) no explicita el límit de la reconciliació Trello.")
        }
        $number = Get-NumericId $case.id
        if ($number -in $fiscalCases) {
            if (-not $content.Contains("[$($case.id.Replace('-', '').ToUpperInvariant())-IN-FISC-001]")) {
                $errors.Add("$($case.id) no enumera les dades fiscals mínimes d’entrada.")
            }
            if (-not $content.Contains("[$($case.id.Replace('-', '').ToUpperInvariant())-AEAT-002]")) {
                $errors.Add("$($case.id) no conserva identificació SIF/productor, zona horària i documents fiscals.")
            }
        }
        if ($number -ge 106 -and $number -le 129) {
            $prefix = $case.id.Replace('-', '').ToUpperInvariant()
            if (-not $content.Contains("[$prefix-IN-003]") -or
                -not $content.Contains("[$prefix-RUL-003]") -or
                -not $content.Contains("[$prefix-FLOW-002]") -or
                -not $content.Contains("[$prefix-TEST-003]")) {
                $errors.Add("$($case.id) no conté entrada, regla, flux i prova específics observats al codi.")
            }
        }
    }
    $actualCards = Get-ChildItem -LiteralPath $outputDirectory -Filter 'uc-*.md' -File
    if ($actualCards.Count -ne $cases.Count) {
        $errors.Add("Hi ha $($actualCards.Count) fitxes físiques i $($cases.Count) entrades canòniques.")
    }
    if ($errors.Count -gt 0) {
        $errors | ForEach-Object { Write-Error $_ }
        throw 'Validació del catàleg de fitxes fallida.'
    }
    Write-Output "VALID: $($cases.Count) fitxes; $($numeric.Count) casos numèrics; 21 apartats per fitxa."
}

$cases = @(Read-Cases)
if (-not $ValidateOnly) {
    New-Item -ItemType Directory -Force -Path $outputDirectory | Out-Null
    foreach ($case in $cases) { Write-Card $case }

    $index = [System.Collections.Generic.List[string]]::new()
    $index.Add('# Fitxes funcionals del SIF')
    $index.Add('')
    $index.Add('Catàleg navegable generat des de `33-casos-us-sif.md`. Cada fitxa té 21 apartats, però és un **esborrany estructurat**, no una anàlisi funcional completa. Cal revisar descripcions/checklists Trello, evidència de codi, dades reals, decisions fiscals, permisos i proves específiques abans de marcar cap fitxa com a preparada per programar o completa.')
    $index.Add('')
    $index.Add('| ID | Cas | Domini | Estat documental | Fitxa |')
    $index.Add('| --- | --- | --- | --- | --- |')
    foreach ($case in $cases) {
        $number = Get-NumericId $case.id
        $fileName = Get-FileName $case.id
        $index.Add("| $($case.id) | $($case.title) | $(Get-Domain $number) | $($case.state.Replace('|', '/')) | [$fileName](./$fileName) |")
    }
    $index.Add('')
    $index.Add('## Validació')
    $index.Add('')
    $index.Add('```powershell')
    $index.Add('pwsh -File sif/tools/functional-card/generate-catalog.ps1 -ValidateOnly')
    $index.Add('```')
    Set-Content -LiteralPath (Join-Path $outputDirectory 'README.md') -Value $index -Encoding utf8
}

Test-Catalog $cases
