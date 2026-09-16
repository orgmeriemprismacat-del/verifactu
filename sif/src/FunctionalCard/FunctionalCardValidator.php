<?php

namespace Prisma\Sif\FunctionalCard;

final class FunctionalCardValidator
{
    private const STATUSES = ['CONFIRMAT', 'PROPOSTA', 'PENDENT', 'CONFLICTE', 'NO APLICABLE'];

    private const READINESS_STATES = ['DRAFT', 'NEEDS_DECISION', 'READY_FOR_PROGRAMMING'];

    private const REQUIRED_HEADINGS = [
        '## 1. Metadades',
        '## 2. Manifest de fonts',
        '## 3. Resum funcional',
        '## 4. Actors i permisos',
        '## 5. Precondicions',
        '## 6. Dades d’entrada',
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
        '## 21. Tasques de programació',
    ];

    public function __construct(private string $repoRoot)
    {
        $resolved = realpath($repoRoot);
        if ($resolved === false || !is_dir($resolved)) {
            throw new \InvalidArgumentException('Repository root does not exist: ' . $repoRoot);
        }
        $this->repoRoot = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function validate(string $markdown, array $manifest): array
    {
        $errors = [];
        $warnings = [];
        $this->validateHeadingOrder($markdown, $errors);

        $caseId = (string) ($manifest['case']['id'] ?? '');
        $claimPrefix = strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', $caseId));
        if ($claimPrefix === '') {
            $errors[] = 'Manifest case.id is missing';
        }

        $sourceMap = $this->sourceMap($manifest, $errors);
        $inputMap = $this->inputEvidenceMap($manifest, $errors);
        $this->validateManifestHashes($sourceMap, $errors);

        $claims = [];
        $seenIds = [];
        $sectionClaimCounts = array_fill(3, 19, 0);
        $currentSection = 0;
        $lines = preg_split('/\R/u', $markdown) ?: [];

        foreach ($lines as $lineNumber => $line) {
            if (preg_match('/^##\s+([0-9]+)\./u', trim($line), $sectionMatch) === 1) {
                $currentSection = (int) $sectionMatch[1];
                continue;
            }

            if (!str_starts_with(trim($line), '- [')) {
                continue;
            }

            $claim = $this->parseClaim($line, $lineNumber + 1, $errors);
            if ($claim === null) {
                continue;
            }

            if ($claimPrefix !== '' && !str_starts_with($claim['id'], $claimPrefix . '-')) {
                $errors[] = sprintf('Line %d: claim id %s must start with %s-', $lineNumber + 1, $claim['id'], $claimPrefix);
            }
            if (isset($seenIds[$claim['id']])) {
                $errors[] = sprintf('Line %d: duplicate claim id %s', $lineNumber + 1, $claim['id']);
            }
            $seenIds[$claim['id']] = true;

            if ($currentSection >= 3 && $currentSection <= 21) {
                $sectionClaimCounts[$currentSection]++;
            }

            $this->validateClaim($claim, $sourceMap, $inputMap, $lineNumber + 1, $errors, $warnings);
            $claims[] = $claim;
        }

        foreach ($sectionClaimCounts as $section => $count) {
            if ($count === 0) {
                $errors[] = sprintf('Section %d must contain at least one classified claim', $section);
            }
        }

        $readiness = $this->readiness($markdown);
        if ($readiness === null) {
            $errors[] = 'Missing metadata field: **Estat de preparació:**';
        } elseif (!in_array($readiness, self::READINESS_STATES, true)) {
            $errors[] = 'Unknown readiness state: ' . $readiness;
        } elseif ($readiness === 'READY_FOR_PROGRAMMING') {
            foreach ($claims as $claim) {
                if ($claim['status'] === 'CONFLICTE'
                    || ($claim['status'] === 'PENDENT' && $claim['blocking'] === true)) {
                    $errors[] = 'READY_FOR_PROGRAMMING is not allowed with conflicts or blocking pending decisions';
                    break;
                }
            }
        }

        return [
            'valid' => $errors === [],
            'case_id' => $caseId,
            'readiness' => $readiness,
            'claims' => count($claims),
            'errors' => array_values(array_unique($errors)),
            'warnings' => array_values(array_unique($warnings)),
        ];
    }

    private function parseClaim(string $line, int $lineNumber, array &$errors): ?array
    {
        $statusPattern = implode('|', array_map(static fn (string $status): string => preg_quote($status, '/'), self::STATUSES));
        $pattern = '/^- \[(?<id>[A-Z0-9-]+)\] \[(?<status>' . $statusPattern . ')\] '
            . '(?<content>.+?) \| Fonts: (?<sources>.+?) \| Bloqueja: (?<blocking>SÍ|SI|NO)\s*$/u';
        if (preg_match($pattern, trim($line), $matches) !== 1) {
            $errors[] = sprintf('Line %d: invalid claim format', $lineNumber);
            return null;
        }

        $sources = [];
        if (!in_array(trim($matches['sources']), ['-', '—'], true)) {
            foreach (explode(',', $matches['sources']) as $source) {
                $source = trim($source);
                if ($source !== '') {
                    $sources[] = $source;
                }
            }
        }

        return [
            'id' => $matches['id'],
            'status' => $matches['status'],
            'content' => trim($matches['content']),
            'sources' => array_values(array_unique($sources)),
            'blocking' => $matches['blocking'] !== 'NO',
            'line' => $lineNumber,
        ];
    }

    private function validateClaim(
        array $claim,
        array $sourceMap,
        array $inputMap,
        int $lineNumber,
        array &$errors,
        array &$warnings
    ): void {
        $knownSources = [];
        foreach ($claim['sources'] as $sourceId) {
            if (isset($sourceMap[$sourceId])) {
                $knownSources[$sourceId] = $sourceMap[$sourceId];
                if (($sourceMap[$sourceId]['exists'] ?? false) !== true) {
                    $errors[] = sprintf('Line %d: referenced repository source is missing: %s', $lineNumber, $sourceId);
                }
                continue;
            }
            if (isset($inputMap[$sourceId])) {
                $knownSources[$sourceId] = $inputMap[$sourceId];
                continue;
            }
            $errors[] = sprintf('Line %d: unknown source id %s', $lineNumber, $sourceId);
        }

        if ($claim['status'] === 'CONFIRMAT') {
            $authorized = array_filter(
                $knownSources,
                static fn (array $source): bool => ($source['validation'] ?? null) === 'AUTORITZADA'
                    && ($source['exists'] ?? false) === true
            );
            if ($authorized === []) {
                $errors[] = sprintf('Line %d: CONFIRMAT requires at least one AUTORITZADA repository source', $lineNumber);
            }
        }

        if ($claim['status'] === 'CONFLICTE') {
            if (count($knownSources) < 2) {
                $errors[] = sprintf('Line %d: CONFLICTE requires at least two known sources', $lineNumber);
            }
            if (!$this->containsToken($claim['content'], 'A:') || !$this->containsToken($claim['content'], 'B:')) {
                $errors[] = sprintf('Line %d: CONFLICTE content must identify A: and B:', $lineNumber);
            }
        }

        if ($claim['status'] === 'PENDENT') {
            if (!$this->containsToken($claim['content'], 'Pregunta:')
                || !$this->containsToken($claim['content'], 'Responsable:')) {
                $errors[] = sprintf('Line %d: PENDENT must contain Pregunta: and Responsable:', $lineNumber);
            }
        }

        if ($claim['status'] === 'NO APLICABLE') {
            if ($knownSources === []) {
                $errors[] = sprintf('Line %d: NO APLICABLE requires an explicit source', $lineNumber);
            }
            if (!$this->containsToken($claim['content'], 'Exclusió:')) {
                $errors[] = sprintf('Line %d: NO APLICABLE must contain Exclusió:', $lineNumber);
            }
        }

        if ($claim['status'] === 'PROPOSTA' && $claim['sources'] === []) {
            $warnings[] = sprintf('Line %d: uncited PROPOSTA; this is allowed but must remain a proposal', $lineNumber);
        }
    }

    private function validateHeadingOrder(string $markdown, array &$errors): void
    {
        preg_match_all('/^##\s+[0-9]+\..*$/mu', $markdown, $matches);
        $actual = array_map('trim', $matches[0] ?? []);

        if ($actual === self::REQUIRED_HEADINGS) {
            return;
        }

        foreach (self::REQUIRED_HEADINGS as $index => $heading) {
            if (!isset($actual[$index])) {
                $errors[] = 'Missing required heading: ' . $heading;
            } elseif ($actual[$index] !== $heading) {
                $errors[] = sprintf(
                    'Heading %d must be "%s"; found "%s"',
                    $index + 1,
                    $heading,
                    $actual[$index]
                );
            }
        }

        if (count($actual) > count(self::REQUIRED_HEADINGS)) {
            foreach (array_slice($actual, count(self::REQUIRED_HEADINGS)) as $heading) {
                $errors[] = 'Unexpected numbered heading: ' . $heading;
            }
        }
    }

    private function sourceMap(array $manifest, array &$errors): array
    {
        $map = [];
        foreach ($manifest['sources'] ?? [] as $source) {
            if (is_array($source) && isset($source['id'])) {
                $id = (string) $source['id'];
                if (isset($map[$id])) {
                    $errors[] = 'Duplicate manifest source id: ' . $id;
                    continue;
                }
                $map[$id] = $source;
            }
        }
        return $map;
    }

    private function inputEvidenceMap(array $manifest, array &$errors): array
    {
        $map = [];
        foreach ($manifest['input_evidence'] ?? [] as $source) {
            if (is_array($source) && isset($source['id'])) {
                $id = (string) $source['id'];
                if (isset($map[$id])) {
                    $errors[] = 'Duplicate input evidence id: ' . $id;
                    continue;
                }
                $map[$id] = $source;
            }
        }
        return $map;
    }

    private function validateManifestHashes(array $sourceMap, array &$errors): void
    {
        foreach ($sourceMap as $sourceId => $source) {
            if (($source['exists'] ?? false) !== true || !is_string($source['path'] ?? null)) {
                continue;
            }

            $relativePath = str_replace('\\', '/', trim($source['path']));
            if ($relativePath === ''
                || str_starts_with($relativePath, '/')
                || preg_match('#^[A-Za-z]:/#', $relativePath) === 1
                || preg_match('#(^|/)\.\.(/|$)#', $relativePath) === 1) {
                $errors[] = 'Unsafe manifest source path: ' . $sourceId;
                continue;
            }

            $path = $this->repoRoot . DIRECTORY_SEPARATOR
                . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
            $resolved = realpath($path);
            if ($resolved === false || !is_file($resolved)) {
                $errors[] = 'Manifest source is now missing: ' . $sourceId;
                continue;
            }
            $rootPrefix = strtolower($this->repoRoot . DIRECTORY_SEPARATOR);
            if (!str_starts_with(strtolower($resolved), $rootPrefix)) {
                $errors[] = 'Manifest source is outside the repository: ' . $sourceId;
                continue;
            }
            $expected = $source['sha256'] ?? null;
            if (!is_string($expected) || $expected === '' || !hash_equals($expected, (string) hash_file('sha256', $resolved))) {
                $errors[] = 'Manifest source hash is stale: ' . $sourceId;
            }
        }
    }

    private function readiness(string $markdown): ?string
    {
        if (preg_match('/^\*\*Estat de preparació:\*\*\s*([A-Z_]+)\s*$/mu', $markdown, $matches) !== 1) {
            return null;
        }
        return $matches[1];
    }

    private function containsToken(string $content, string $token): bool
    {
        if (function_exists('mb_stripos')) {
            return mb_stripos($content, $token, 0, 'UTF-8') !== false;
        }
        return stripos($content, $token) !== false;
    }
}
