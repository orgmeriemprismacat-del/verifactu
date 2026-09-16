param(
    [Parameter(Mandatory = $true)]
    [string[]]$ExportPath
)

$ErrorActionPreference = 'Stop'
$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot '../../..')).Path
$inventories = foreach ($path in $ExportPath) {
    $resolvedExport = (Resolve-Path -LiteralPath $path).Path
    $data = Get-Content -Raw -LiteralPath $resolvedExport | ConvertFrom-Json
    $list = $data.lists | Where-Object { $_.name -eq 'Fitxes mare' -and -not $_.closed } | Select-Object -First 1
    if (-not $list) { throw "No s’ha trobat la llista oberta «Fitxes mare» a $resolvedExport." }
    $cards = @($data.cards | Where-Object { $_.idList -eq $list.id -and -not $_.closed } | Sort-Object pos)
    [pscustomobject]@{
        Path = $resolvedExport
        Hash = (Get-FileHash -Algorithm SHA256 -LiteralPath $resolvedExport).Hash.ToLowerInvariant()
        Cards = $cards
        Count = $cards.Count
    }
}

$expectedCounts = @(10, 30, 145)
$actualCounts = @($inventories | ForEach-Object { $_.Count } | Sort-Object)
if ($inventories.Count -ne 3 -or (Compare-Object $expectedCounts $actualCounts)) {
    throw "S’esperaven tres exports amb 10, 30 i 145 Fitxes mare; s’han trobat: $($actualCounts -join ', ')."
}
$cards = @($inventories | ForEach-Object { $_.Cards })
if ($cards.Count -ne 185) { throw "S’esperaven 185 fitxes mare totals i se n’han trobat $($cards.Count)." }

function Get-Mapping([string]$name) {
    switch -Regex ($name) {
        'Generador UUID complet' { return 'META-INFRA → UC transversals' }
        'Idempotència genèrica' { return 'META-INFRA → UC-01, UC-02, UC-86, UC-106…UC-112' }
        'Repositori de seqüència fiscal' { return 'UC-01' }
        'Validador de payload `issueInvoice' { return 'UC-01, UC-112' }
        'Validador de payload `registerPayment' { return 'UC-02, UC-86' }
        'Migració callback Redsys taller' { return 'UC-03, UC-14a, UC-51, UC-68' }
        'Migració callback Redsys jornada' { return 'UC-03, UC-14b, UC-51, UC-68' }
        'Migració callback Redsys pack' { return 'UC-03, UC-15, UC-51, UC-68' }
        'Migració callback Redsys grup' { return 'UC-03, UC-16, UC-51, UC-68' }
        'Migració callback Redsys regal' { return 'UC-03, UC-17, UC-51, UC-68' }
        'Resposta idempotent a Redsys' { return 'UC-03, UC-51, UC-52' }
        'Passar pagaments com a conciliació' { return 'UC-25, UC-53, UC-82, UC-86' }
        '`fiscal_queue` i retries' { return 'UC-09, UC-54, UC-77' }
        'Sincronització resum cap a BD antiga' { return 'UC-47, UC-68' }
        '`motiu_canvi`' { return 'UC-26, UC-71, UC-94' }
        'Baixa com event administratiu' { return 'UC-27, UC-72' }
        'Script `preflight-sif.php`' { return 'META-OPERACIÓ → UC-39, UC-60, UC-83' }
        'Permisos MySQL restrictius' { return 'META-SEGURETAT → UC-45, UC-59, UC-102' }
        'Taula `notificacions`' { return 'UC-43, UC-58' }
        'Despeses de gestió com a línia' { return 'UC-73, UC-94' }
        'Despeses de gestió com a penalització' { return 'UC-73, UC-74, UC-94' }
        '`credit_balance`' { return 'UC-29, UC-29a' }
        'Promocions temporals separades' { return 'UC-20c, UC-90, UC-112' }
        'Canvi de dades fiscals posterior' { return 'UC-70, UC-74, UC-93' }
        'Registres AEAT|XML / registre AEAT' { return 'UC-35, UC-54, UC-75, UC-76, UC-77' }
        'Proves i validació|Preproducció / entorn de proves' { return 'UC-39' }
        'Empresa/responsable substitueix|URL de pagament d.alumne substituïda|Desactivar URL individual|Empresa que substitueix' { return 'UC-21, UC-33, UC-50, UC-103' }
        'Factures SIF|Factures al panell' { return 'UC-07, UC-34, UC-35' }
        'Documents SIF' { return 'UC-36, UC-55, UC-78, UC-80' }
        'Apartat VERI.FACTU intranet' { return 'UC-34, UC-60' }
        'Grup amb participants' { return 'UC-16' }
        '1a Reclamació|Reclamació Final|Recordatori Pagament|Control morosos|Reclamació de pagament|Pagament morositat|calendari de reclamacions' { return 'UC-12, UC-24, UC-43, UC-95, UC-96' }
        'Validar descomptes' { return 'UC-19, UC-20, UC-90, UC-91' }
        'Baixes 2a setmana' { return 'UC-27, UC-72, UC-96' }
        'Promoció temporal' { return 'UC-20c' }
        'Canvi de curs amb major import' { return 'UC-26, UC-28, UC-71' }
        'Canvi de curs amb menor import' { return 'UC-26, UC-28, UC-29, UC-71' }
        'Canvi de curs amb mateix import|Previsualitzar canvi de curs|Cas d.ús: Canvi de curs$' { return 'UC-26, UC-71' }
        'Passar pagament de factura ja generada' { return 'UC-02, UC-22, UC-56, UC-86' }
        'Transferència validada' { return 'UC-22, UC-56, UC-86' }
        'Curs normal Redsys|compra de curs online' { return 'UC-03, UC-14, UC-63' }
        'Baixa d.inscripció' { return 'UC-27, UC-72' }
        'Despeses de gestió en grup' { return 'UC-73, UC-91, UC-94' }
        'Despeses de gestió per canvi' { return 'UC-71, UC-73, UC-94' }
        'Despeses de gestió com a .*cobrament' { return 'UC-22, UC-73, UC-86, UC-94' }
        'Cas d.ús: Despeses de gestió$' { return 'UC-73, UC-94' }
        'Aplicar saldo futur|Compensació / saldo' { return 'UC-29, UC-29a' }
        'Devolució transferència|Devolució total|Devolució parcial' { return 'UC-28' }
        'PDF/QR pendent|Factura amb PDF/QR pendent' { return 'UC-55, UC-78' }
        'Històric Associació/SL' { return 'UC-97' }
        'Botiga de llibres' { return 'UC-98' }
        'Redsys denegat i després acceptat|Callback Redsys duplicat|Callback Redsys autoritzat' { return 'UC-03, UC-51, UC-52' }
        'Diversos intents amb mateix' { return 'UC-51, UC-52, UC-86' }
        'Client canvia dades fiscals després' { return 'UC-70, UC-93' }
        'Operació no facturable|Operació informativa' { return 'UC-100' }
        'Cercar pagaments sense criteri|Consultar pagaments|Passar pagaments$' { return 'UC-56, UC-86' }
        'Una transferència paga diverses factures' { return 'UC-02, UC-22, UC-105' }
        'Anul.lar pagament des de la web' { return 'UC-68, UC-86, UC-103' }
        'Generar factura abans de pagar|Factura abans de cobrament' { return 'UC-04, UC-21' }
        'Analitzar fitxer TPV' { return 'UC-25, UC-82' }
        'Descarregar factures' { return 'UC-07, UC-36, UC-80' }
        'Exportació de factures|Exportació de registres' { return 'UC-37, UC-84' }
        'Informació de l.alumne|Dades del curs / dades pagament' { return 'UC-42, UC-61, UC-86' }
        'Consulta / edita / anul.la factura' { return 'UC-05, UC-07, UC-30, UC-31, UC-32' }
        'Cas d.ús: Pack$' { return 'UC-15' }
        'Cas d.ús: Grup de persones$' { return 'UC-16, UC-16a, UC-16b' }
        'Cas d.ús: Regal$' { return 'UC-17, UC-18, UC-18a' }
        'Cas d.ús: USOC$' { return 'UC-13, UC-19, UC-19a, UC-19b' }
        'Factura rectificativa' { return 'UC-05, UC-74' }
        'Factura manual|Factures manuals' { return 'UC-01, UC-92' }
        'Pagament fraccionat' { return 'UC-23, UC-86' }
        'Consulta intranet alumne' { return 'UC-61, UC-102' }
        'Error SIF amb cua|Incidències SIF' { return 'UC-08, UC-54, UC-55, UC-81' }
        'Pagament cobrat sense factura' { return 'UC-01, UC-25, UC-53, UC-86' }
        'Factura emesa pendent de cobrament' { return 'UC-04, UC-61' }
        'Error PDF/QR|Error PDF$' { return 'UC-08, UC-55, UC-78, UC-81' }
        'comprador estranger|entitat amb dades fiscals incompletes' { return 'UC-87' }
        'factura d.empresa visible' { return 'UC-07, UC-80' }
        'Consulta auditor|Rol auditor/AEAT' { return 'UC-45, UC-59, UC-84' }
        'Jornada$' { return 'UC-14b' }
        'Taller$' { return 'UC-14a' }
        'Codi promocional' { return 'UC-20d' }
        'Factura electrònica E_FACT' { return 'UC-32' }
        'Factura ordinària A' { return 'UC-01' }
        'Veure factura' { return 'UC-07, UC-36, UC-80' }
        'canvi de concepte després' { return 'UC-89' }
        'falsa proforma' { return 'UC-48' }
        'factura agrupada|una factura per pagament' { return 'UC-88' }
        'Notificació fiscal pendent' { return 'UC-58, UC-79' }
        'migració de factures antigues|Migració factures històriques' { return 'UC-11, UC-47, UC-97' }
        'Intranet tutor' { return 'UC-99' }
        'factura i cobrament neixen junts' { return 'UC-01, UC-02' }
        'Privacitat de participants' { return 'UC-07, UC-16, UC-80' }
        'Accés segur de l.empresa' { return 'UC-50, UC-80' }
        'alumne sense rol intranet' { return 'UC-102' }
        'curs no superat|curs superat' { return 'UC-12, UC-95' }
        'Carnet Jove' { return 'UC-20a' }
        'Descompte Alumne PrisMa' { return 'UC-20' }
        'Descompte grup|descompte de grup per trams' { return 'UC-91' }
        'Discapacitat|família nombrosa|monoparental|violència de gènere' { return 'UC-20b' }
        'QR fiscal' { return 'UC-36, UC-78' }
        'Subdomini/SSL' { return 'UC-38, UC-101' }
        'adopció de modalitat VeriFactu' { return 'META-ARQ → UC-62, UC-68, UC-103' }
        'Separació entre pagament i factura' { return 'META-REGLE → UC-01, UC-02, UC-88' }
        'Identificació de casos|Separació entre cas d.ús i programació' { return 'META-CATÀLEG → UC-01…UC-112' }
        'Detecció de casos de descomptes|Analitzar descomptes reals|Analitzar codis promocionals' { return 'META-ANÀLISI → UC-20, UC-20a…UC-20d, UC-90, UC-91' }
        'Detecció de casos de morositat' { return 'META-ANÀLISI → UC-12, UC-24, UC-95, UC-96' }
        'Detecció de casos de document fiscal pendent' { return 'META-ANÀLISI → UC-55, UC-78' }
        'Configuració SIF' { return 'UC-38, UC-83, UC-101' }
        'Dashboard SIF' { return 'UC-34, UC-60' }
        'Línies de factura' { return 'UC-01, UC-88' }
        'PDF immutable' { return 'UC-36, UC-55, UC-78' }
        'Permisos i rols' { return 'UC-45, UC-59, UC-102' }
        'client compra com a particular' { return 'UC-93' }
        'canvi de dades personals' { return 'UC-42, UC-70' }
        'venda manual des d.intranet' { return 'UC-92' }
        'canvi manual d.A_PAGAR' { return 'UC-94' }
        'no paga a l.inici' { return 'UC-96' }
        'SIF centralitzat' { return 'META-ARQ → UC-62, UC-68, UC-103' }
        'pas intermedi de dades fiscals' { return 'UC-69, UC-87' }
        'Descompte validat després' { return 'UC-90' }
        'Diccionari camps/valors' { return 'META-DICCIONARI → UC-01…UC-112' }
        default { return 'REVISAR' }
    }
}

$rows = foreach ($card in $cards) {
    [pscustomobject]@{
        Name = $card.name
        Url = $card.shortUrl
        Mapping = Get-Mapping $card.name
    }
}
$unmapped = @($rows | Where-Object { $_.Mapping -eq 'REVISAR' })
if ($unmapped.Count -gt 0) {
    $unmapped.Name | ForEach-Object { Write-Error "Fitxa mare sense mapar: $_" }
    throw 'La reconciliació conté fitxes sense mapar.'
}

$inventories | ForEach-Object {
    Write-Output ("VALID SOURCE: {0}; {1} fitxes; SHA-256 {2}" -f [IO.Path]::GetFileName($_.Path), $_.Count, $_.Hash)
}
Write-Output "VALID: 185 fitxes mare inventariades en tres exports; 0 sense mapar. El document 39 conserva la reconciliació revisada."
