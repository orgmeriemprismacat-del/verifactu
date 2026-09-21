<?php

namespace Prisma\Sif\FunctionalCard;

final class SourceManifestBuilder
{
    private string $repoRoot;

    public function __construct(string $repoRoot, private array $config)
    {
        $resolved = realpath($repoRoot);
        if ($resolved === false || !is_dir($resolved)) {
            throw new \InvalidArgumentException('Repository root does not exist: ' . $repoRoot);
        }
        $this->repoRoot = rtrim($resolved, DIRECTORY_SEPARATOR);
    }

    public function build(array $input): array
    {
        $caseId = (string) $input['case']['id'];
        $sources = array_merge(
            $this->config['defaults'] ?? [],
            $this->config['cases'][$caseId] ?? []
        );

        $additionalIndex = 1;
        foreach ($input['additional_sources'] ?? [] as $path) {
            $sources[] = [
                'id' => sprintf('AUX-%03d', $additionalIndex++),
                'path' => $path,
                'role' => 'ADDITIONAL',
                'context' => 'UNKNOWN',
                'validation' => 'PENDENT_VALIDACIO',
                'keywords' => [],
            ];
        }

        $sources = $this->deduplicateSources($sources);
        $keywords = $this->collectKeywords($input);
        $manifestSources = [];

        foreach ($sources as $source) {
            $manifestSources[] = $this->inspectSource($source, $keywords);
        }

        return [
            'schema_version' => '1.0',
            'generated_at' => gmdate('c'),
            'repository' => [
                'root' => $this->repoRoot,
                'base_revision' => $this->gitValue(['rev-parse', 'HEAD']),
                'branch' => $this->gitValue(['branch', '--show-current']),
            ],
            'case' => $input['case'],
            'context' => $input['context'],
            'source_policy' => [
                'confirmed_requires_validation' => 'AUTORITZADA',
                'historical_code_can_confirm_business_rules' => false,
                'additional_sources_default_validation' => 'PENDENT_VALIDACIO',
            ],
            'sources' => $manifestSources,
            'input_evidence' => $this->buildInputEvidence($input),
        ];
    }

    private function inspectSource(array $source, array $caseKeywords): array
    {
        foreach (['id', 'path', 'role', 'context', 'validation'] as $field) {
            if (!isset($source[$field]) || !is_string($source[$field]) || trim($source[$field]) === '') {
                throw new \InvalidArgumentException('Invalid source configuration field: ' . $field);
            }
        }

        $relativePath = $this->normalizeRelativePath($source['path']);
        $this->assertAllowedPath($relativePath);
        $absolutePath = $this->repoRoot . DIRECTORY_SEPARATOR . str_replace('/', DIRECTORY_SEPARATOR, $relativePath);
        $resolved = realpath($absolutePath);

        if ($resolved === false || !is_file($resolved)) {
            return [
                'id' => $source['id'],
                'path' => $relativePath,
                'role' => $source['role'],
                'context' => $source['context'],
                'validation' => $source['validation'],
                'exists' => false,
                'git_state' => 'MISSING',
                'sha256' => null,
                'excerpts' => [],
            ];
        }

        $this->assertInsideRepository($resolved);
        $keywords = array_values(array_unique(array_filter(array_merge(
            $caseKeywords,
            is_array($source['keywords'] ?? null) ? $source['keywords'] : []
        ), static fn (mixed $value): bool => is_string($value) && trim($value) !== '')));

        return [
            'id' => $source['id'],
            'path' => $relativePath,
            'role' => $source['role'],
            'context' => $source['context'],
            'validation' => $source['validation'],
            'exists' => true,
            'git_state' => $this->gitState($relativePath),
            'sha256' => hash_file('sha256', $resolved),
            'excerpts' => $this->extractExcerpts($resolved, $keywords),
        ];
    }

    private function collectKeywords(array $input): array
    {
        $keywords = [(string) $input['case']['title']];
        $this->collectStringValues($input['variants'] ?? [], $keywords);
        return array_values(array_unique(array_filter(array_map('trim', $keywords))));
    }

    private function collectStringValues(mixed $value, array &$target): void
    {
        if (is_string($value)) {
            $target[] = $value;
            return;
        }
        if (!is_array($value)) {
            return;
        }
        foreach ($value as $child) {
            $this->collectStringValues($child, $target);
        }
    }

    private function extractExcerpts(string $path, array $keywords): array
    {
        if ($keywords === []) {
            return [];
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Unable to read source: ' . $path);
        }

        $window = max(0, (int) ($this->config['excerpt_window'] ?? 3));
        $maxExcerpts = max(1, (int) ($this->config['max_excerpts_per_source'] ?? 30));
        $ranges = [];

        foreach ($lines as $index => $line) {
            foreach ($keywords as $keyword) {
                if ($this->contains((string) $line, (string) $keyword)) {
                    $ranges[] = [max(0, $index - $window), min(count($lines) - 1, $index + $window)];
                    break;
                }
            }
        }

        $ranges = $this->mergeRanges($ranges);
        $excerpts = [];
        foreach (array_slice($ranges, 0, $maxExcerpts) as [$start, $end]) {
            $excerpts[] = [
                'section' => $this->nearestHeading($lines, $start),
                'start_line' => $start + 1,
                'end_line' => $end + 1,
                'text' => implode("\n", array_slice($lines, $start, $end - $start + 1)),
            ];
        }
        return $excerpts;
    }

    private function mergeRanges(array $ranges): array
    {
        if ($ranges === []) {
            return [];
        }
        usort($ranges, static fn (array $a, array $b): int => $a[0] <=> $b[0]);
        $merged = [array_shift($ranges)];
        foreach ($ranges as $range) {
            $lastIndex = count($merged) - 1;
            if ($range[0] <= $merged[$lastIndex][1] + 1) {
                $merged[$lastIndex][1] = max($merged[$lastIndex][1], $range[1]);
            } else {
                $merged[] = $range;
            }
        }
        return $merged;
    }

    private function nearestHeading(array $lines, int $start): ?string
    {
        for ($index = $start; $index >= 0; $index--) {
            $line = trim((string) $lines[$index]);
            if (str_starts_with($line, '#')) {
                return ltrim($line, "# \t");
            }
        }
        return null;
    }

    private function contains(string $haystack, string $needle): bool
    {
        if (function_exists('mb_stripos')) {
            return mb_stripos($haystack, $needle, 0, 'UTF-8') !== false;
        }
        return stripos($haystack, $needle) !== false;
    }

    private function buildInputEvidence(array $input): array
    {
        $evidence = [];
        $index = 1;
        $groups = [
            'known_data' => 'INPUT_CONTEXT',
            'asserted_decisions' => 'ASSERTED_DECISION',
            'explicit_exclusions' => 'EXPLICIT_EXCLUSION',
        ];
        foreach ($groups as $field => $type) {
            foreach ($input[$field] ?? [] as $item) {
                $evidence[] = [
                    'id' => sprintf('IN-%03d', $index++),
                    'type' => $type,
                    'content' => $item,
                    'validation' => $type === 'EXPLICIT_EXCLUSION' ? 'EXPLICITA_USUARI' : 'PENDENT_VALIDACIO',
                ];
            }
        }
        return $evidence;
    }

    private function deduplicateSources(array $sources): array
    {
        $seenPaths = [];
        $seenIds = [];
        $result = [];
        foreach ($sources as $source) {
            $path = $this->normalizeRelativePath((string) ($source['path'] ?? ''));
            $id = (string) ($source['id'] ?? '');
            if (isset($seenIds[$id]) && $seenIds[$id] !== $path) {
                throw new \InvalidArgumentException('Duplicate source id: ' . $id);
            }
            if (isset($seenPaths[$path])) {
                continue;
            }
            $seenIds[$id] = $path;
            $seenPaths[$path] = true;
            $result[] = $source;
        }
        return $result;
    }

    private function normalizeRelativePath(string $path): string
    {
        $path = str_replace('\\', '/', trim($path));
        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:/#', $path) === 1) {
            throw new \InvalidArgumentException('Source path must be repository-relative: ' . $path);
        }
        return $path;
    }

    private function assertAllowedPath(string $path): void
    {
        if ($path === '' || str_contains($path, "\0") || preg_match('#(^|/)\.\.(/|$)#', $path) === 1) {
            throw new \InvalidArgumentException('Unsafe source path: ' . $path);
        }

        foreach ($this->config['blocked_prefixes'] ?? [] as $prefix) {
            $normalized = rtrim(str_replace('\\', '/', (string) $prefix), '/') . '/';
            if (str_starts_with(strtolower($path . '/'), strtolower($normalized))) {
                throw new \InvalidArgumentException('Blocked source path: ' . $path);
            }
        }

        foreach ($this->config['blocked_patterns'] ?? [] as $pattern) {
            if (preg_match((string) $pattern, $path) === 1) {
                throw new \InvalidArgumentException('Blocked source path: ' . $path);
            }
        }

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (!in_array($extension, $this->config['allowed_extensions'] ?? [], true)) {
            throw new \InvalidArgumentException('Unsupported source extension: ' . $path);
        }
    }

    private function assertInsideRepository(string $path): void
    {
        $root = strtolower($this->repoRoot . DIRECTORY_SEPARATOR);
        $candidate = strtolower($path);
        if (!str_starts_with($candidate, $root)) {
            throw new \InvalidArgumentException('Source is outside the repository: ' . $path);
        }
    }

    private function gitState(string $relativePath): string
    {
        $status = $this->gitValue(['status', '--short', '--untracked-files=all', '--', $relativePath]);
        if ($status === null || $status === '') {
            $tracked = $this->gitCommand(['ls-files', '--error-unmatch', '--', $relativePath]);
            return $tracked['exit_code'] === 0 ? 'CLEAN' : 'UNKNOWN';
        }
        if (str_starts_with($status, '??')) {
            return 'UNTRACKED';
        }
        return 'MODIFIED';
    }

    private function gitValue(array $arguments): ?string
    {
        $result = $this->gitCommand($arguments);
        if ($result['exit_code'] !== 0) {
            return null;
        }
        $value = trim($result['output']);
        return $value === '' ? null : $value;
    }

    private function gitCommand(array $arguments): array
    {
        if (!function_exists('proc_open')) {
            return ['exit_code' => 127, 'output' => ''];
        }

        $command = array_merge(['git', '-C', $this->repoRoot], $arguments);
        $pipes = [];
        $process = @proc_open(
            $command,
            [1 => ['pipe', 'w'], 2 => ['pipe', 'w']],
            $pipes,
            $this->repoRoot
        );
        if (!is_resource($process)) {
            return ['exit_code' => 127, 'output' => ''];
        }

        $output = stream_get_contents($pipes[1]);
        $error = stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        $exitCode = proc_close($process);
        return [
            'exit_code' => $exitCode,
            'output' => trim((string) $output . ($exitCode === 0 ? '' : (string) $error)),
        ];
    }
}
