param(
    [switch]$ValidateOnly
)

$ErrorActionPreference = 'Stop'
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
}

$codeSourceDefinitions = [ordered]@{
    'SRC-014' = 'codi-drive/web-actual/ajax/enviarInscripcio.php'
    'SRC-015' = 'codi-drive/web-actual/ajax/enviarInscripcioTastet.php'
    'SRC-016' = 'codi-drive/web-actual/DescompteAmic.php'
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
}

$paymentCases = @(
    2, 3, 6, 22, 23, 24, 25, 28, 29, 33, 47, 50, 51, 52, 53, 56, 61, 62,
    63, 68, 72, 79, 81, 82, 86, 90, 92, 94, 95, 96, 103, 104, 105, 110, 112
)
$fiscalCases = @(
    1, 4, 5, 9, 11, 13, 14, 15, 16, 17, 19, 21, 30, 31, 32, 35, 36, 37,
    41, 46, 48, 54, 55, 69, 70, 71, 72, 73, 74, 75, 76, 77, 78, 80, 83, 84,
    87, 88, 89, 90, 91, 92, 93, 94, 97, 98, 109, 110, 112
)
$documentCases = @(7, 36, 37, 45, 48, 49, 55, 59, 61, 78, 79, 80, 84, 97, 99, 102)
$governanceCases = @(8, 9, 10, 34, 35, 37, 38, 39, 40, 44, 45, 46, 53, 54, 57, 58, 59, 60, 64, 67, 77, 81, 82, 83, 84, 85, 97, 98, 101)

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
    if ($number -in @(106, 107, 108, 109, 110, 111, 112)) {
        return 'web/ecommerce, adaptador SIF i intranet de gestió quan cal validació'
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
    if ($number -in @(90, 91, 94, 110, 111, 112)) { $tables.Add('discount_validation') }
    if ($number -in @(50, 61, 103, 110, 112)) { $tables.Add('payment_link') }
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
        default { return $null }
    }
}

function Get-SpecificSourceIds([string]$id) {
    switch ($id) {
        { $_ -in @('UC-106', 'UC-107', 'UC-109', 'UC-111', 'UC-112') } { return 'SRC-014, SRC-009, SRC-013' }
        'UC-108' { return 'SRC-015, SRC-009, SRC-013' }
        'UC-110' { return 'SRC-016, SRC-009, SRC-013' }
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
    if ($case.id -in @('UC-106', 'UC-107', 'UC-109', 'UC-111', 'UC-112')) {
        $manifestSources['SRC-014'] = $codeSourceDefinitions['SRC-014']
    }
    if ($case.id -eq 'UC-108') {
        $manifestSources['SRC-015'] = $codeSourceDefinitions['SRC-015']
    }
    if ($case.id -eq 'UC-110') {
        $manifestSources['SRC-016'] = $codeSourceDefinitions['SRC-016']
    }

    $sourceLines = foreach ($entry in $manifestSources.GetEnumerator()) {
        $absolute = Join-Path $repoRoot $entry.Value
        if (-not (Test-Path -LiteralPath $absolute)) { throw "Falta la font $($entry.Value)" }
        $hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $absolute).Hash.ToLowerInvariant()
        $sourceStatus = if ($entry.Key -in @('SRC-014', 'SRC-015', 'SRC-016')) {
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
    if ($cases.Count -ne 125) {
        $errors.Add("S’esperaven 125 casos/variants i se n’han detectat $($cases.Count).")
    }
    $numeric = $cases | Where-Object { $_.id -match '^UC-\d+$' }
    if ($numeric.Count -ne 112) {
        $errors.Add("S’esperaven 112 identificadors numèrics i se n’han detectat $($numeric.Count).")
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
        if ($number -ge 106 -and $number -le 112) {
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
