<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\FunctionalCard\CaseInputValidator;
use Prisma\Sif\FunctionalCard\FunctionalCardValidator;
use Prisma\Sif\FunctionalCard\SourceManifestBuilder;
use Prisma\Sif\Tests\Support\Assert;

final class FunctionalCardValidationTest
{
    public function testInputValidatorNormalizesUnknownContext(): void
    {
        $input = (new CaseInputValidator())->validate($this->minimalInput());

        Assert::same('UC-26', $input['case']['id']);
        Assert::same('UNKNOWN', $input['context']['invoice_state']);
        Assert::same('PREVIEW', $input['output']['mode']);
    }

    public function testInputValidatorRejectsMissingTitle(): void
    {
        $input = $this->minimalInput();
        unset($input['case']['title']);

        Assert::throws(\InvalidArgumentException::class, static function () use ($input): void {
            (new CaseInputValidator())->validate($input);
        });
    }

    public function testManifestBuilderFindsExcerptsAndHash(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            Assert::same(1, count($manifest['sources']));
            Assert::same(true, $manifest['sources'][0]['exists']);
            Assert::same(hash_file('sha256', $root . '/source.md'), $manifest['sources'][0]['sha256']);
            Assert::same(1, count($manifest['sources'][0]['excerpts']));
            Assert::same('Canvi de curs', $manifest['sources'][0]['excerpts'][0]['section']);
        });
    }

    public function testCardValidatorAcceptsAuthorizedConfirmedClaim(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            $result = (new FunctionalCardValidator($root))->validate($this->validCard(), $manifest);

            Assert::same(true, $result['valid']);
            Assert::same(19, $result['claims']);
        });
    }

    public function testCardValidatorRejectsConfirmedClaimWithoutAuthorizedSource(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            $manifest['sources'][0]['validation'] = 'CANDIDATA';
            $result = (new FunctionalCardValidator($root))->validate($this->validCard(), $manifest);

            Assert::same(false, $result['valid']);
            Assert::same(true, $this->hasError($result, 'CONFIRMAT requires'));
        });
    }

    public function testCardValidatorRejectsConfirmedClaimWhenAuthorizedSourceIsMissing(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            $manifest['sources'][0]['exists'] = false;
            $manifest['sources'][0]['sha256'] = null;
            $result = (new FunctionalCardValidator($root))->validate($this->validCard(), $manifest);

            Assert::same(false, $result['valid']);
            Assert::same(true, $this->hasError($result, 'CONFIRMAT requires'));
        });
    }

    public function testCardValidatorRejectsManifestPathOutsideRepository(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            $manifest['sources'][0]['path'] = '../source.md';
            $result = (new FunctionalCardValidator($root))->validate($this->validCard(), $manifest);

            Assert::same(false, $result['valid']);
            Assert::same(true, $this->hasError($result, 'Unsafe manifest source path'));
        });
    }

    public function testCardValidatorRejectsDuplicateNumberedHeading(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            $card = $this->validCard() . "\n## 21. Tasques de programació\n";
            $result = (new FunctionalCardValidator($root))->validate($card, $manifest);

            Assert::same(false, $result['valid']);
            Assert::same(true, $this->hasError($result, 'Unexpected numbered heading'));
        });
    }

    public function testCardValidatorRejectsConflictWithOneSource(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            $card = str_replace(
                '[CONFIRMAT] Cas confirmat.',
                '[CONFLICTE] A: línia separada. B: motiu intern.',
                $this->validCard()
            );
            $result = (new FunctionalCardValidator($root))->validate($card, $manifest);

            Assert::same(false, $result['valid']);
            Assert::same(true, $this->hasError($result, 'CONFLICTE requires at least two'));
        });
    }

    public function testCardValidatorRejectsStaleSourceHash(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            file_put_contents($root . '/source.md', "# Canvi de curs\nContingut modificat\n");
            $result = (new FunctionalCardValidator($root))->validate($this->validCard(), $manifest);

            Assert::same(false, $result['valid']);
            Assert::same(true, $this->hasError($result, 'hash is stale'));
        });
    }

    public function testCardValidatorRejectsHashPrintedDifferentlyFromManifest(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            $card = str_replace(
                (string) $manifest['sources'][0]['sha256'],
                str_repeat('0', 64),
                $this->validCard()
            );
            $result = (new FunctionalCardValidator($root))->validate($card, $manifest);

            Assert::same(false, $result['valid']);
            Assert::same(true, $this->hasError($result, 'card manifest hash differs'));
        });
    }

    public function testReadyForProgrammingRejectsBlockingPendingClaim(): void
    {
        $this->withTemporaryRepository(function (string $root, array $manifest): void {
            $card = str_replace('NEEDS_DECISION', 'READY_FOR_PROGRAMMING', $this->validCard());
            $card = str_replace(
                '[CONFIRMAT] Cas confirmat. | Fonts: SRC-001 | Bloqueja: NO',
                '[PENDENT] Pregunta: quin criteri? Responsable: Xat 3. | Fonts: SRC-001 | Bloqueja: SÍ',
                $card
            );
            $result = (new FunctionalCardValidator($root))->validate($card, $manifest);

            Assert::same(false, $result['valid']);
            Assert::same(true, $this->hasError($result, 'READY_FOR_PROGRAMMING'));
        });
    }

    public function testManifestBuilderRejectsAbsoluteSourcePath(): void
    {
        $root = sys_get_temp_dir() . '/prisma-sif-functional-card-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);

        try {
            $config = [
                'allowed_extensions' => ['md'],
                'defaults' => [[
                    'id' => 'SRC-001',
                    'path' => 'C:/outside.md',
                    'role' => 'FUNCTIONAL_FLOW',
                    'context' => 'OBJECTIU',
                    'validation' => 'AUTORITZADA',
                ]],
                'cases' => [],
            ];
            $input = (new CaseInputValidator())->validate($this->minimalInput());

            Assert::throws(\InvalidArgumentException::class, static function () use ($root, $config, $input): void {
                (new SourceManifestBuilder($root, $config))->build($input);
            });
        } finally {
            @rmdir($root);
        }
    }

    private function minimalInput(): array
    {
        return [
            'schema_version' => '1.0',
            'case' => ['id' => 'UC-26', 'title' => 'Canvi de curs'],
            'context' => [],
            'output' => ['language' => 'ca', 'mode' => 'PREVIEW'],
        ];
    }

    private function validCard(): string
    {
        $headings = [
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

        $lines = ['# Fitxa funcional — UC-26 — Canvi de curs'];
        foreach ($headings as $index => $heading) {
            $lines[] = '';
            $lines[] = $heading;
            $lines[] = '';
            if ($index === 0) {
                $lines[] = '**Estat de preparació:** NEEDS_DECISION';
            } elseif ($index === 1) {
                $hash = hash('sha256', "# Canvi de curs\nRegla confirmada del cas.\n");
                $lines[] = "- SRC-001 | `source.md` | L1-L2 | `{$hash}` | AUTORITZADA — OBJECTIU — CLEAN";
            } else {
                $sequence = str_pad((string) ($index - 1), 3, '0', STR_PAD_LEFT);
                $lines[] = "- [UC26-TEST-{$sequence}] [CONFIRMAT] Cas confirmat. | Fonts: SRC-001 | Bloqueja: NO";
            }
        }
        return implode("\n", $lines) . "\n";
    }

    private function withTemporaryRepository(callable $callback): void
    {
        $root = sys_get_temp_dir() . '/prisma-sif-functional-card-' . bin2hex(random_bytes(6));
        mkdir($root, 0777, true);
        file_put_contents($root . '/source.md', "# Canvi de curs\nRegla confirmada del cas.\n");

        try {
            $config = [
                'excerpt_window' => 0,
                'max_excerpts_per_source' => 10,
                'allowed_extensions' => ['md'],
                'blocked_prefixes' => ['.git', 'xat-original'],
                'blocked_patterns' => [],
                'defaults' => [[
                    'id' => 'SRC-001',
                    'path' => 'source.md',
                    'role' => 'FUNCTIONAL_FLOW',
                    'context' => 'OBJECTIU',
                    'validation' => 'AUTORITZADA',
                    'keywords' => ['Canvi de curs'],
                ]],
                'cases' => [],
            ];
            $input = (new CaseInputValidator())->validate($this->minimalInput());
            $manifest = (new SourceManifestBuilder($root, $config))->build($input);
            $callback($root, $manifest);
        } finally {
            @unlink($root . '/source.md');
            @rmdir($root);
        }
    }

    private function hasError(array $result, string $needle): bool
    {
        foreach ($result['errors'] as $error) {
            if (str_contains($error, $needle)) {
                return true;
            }
        }
        return false;
    }
}
