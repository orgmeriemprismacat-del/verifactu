<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class Uc002LegacyExistingInvoiceTest
{
    public function testExistingInvoicePaymentDoesNotOverwriteInvoiceAmount(): void
    {
        $source = $this->source();

        $queryStart = strpos($source, '"updFactGenerada"');
        Assert::same(true, $queryStart !== false);
        $query = substr($source, (int) $queryStart, 260);

        Assert::stringContainsString('UPDATE factures SET data_pagament = ?', $query);
        Assert::stringContainsString('FORMA_PAGAMENT = ? WHERE NUM = ?', $query);
        Assert::same(false, str_contains($query, 'IMPORT = ?'));
        Assert::stringContainsString(
            '$stmtInsert->bind_param("sss", $dataPagament, $formaPagament, $numFact);',
            $source
        );
    }

    public function testExistingInvoicePartialPaymentAccumulatesPreviousAmount(): void
    {
        $method = $this->existingInvoiceMethod($this->source());

        Assert::stringContainsString(
            '$pagat = floatval($pagamentMembre) + floatval($auxPagat);',
            $method
        );
        Assert::stringContainsString('$auxPagat = 0.0;', $method);
    }

    public function testExistingInvoicePaymentDoesNotLeakDebugEchoes(): void
    {
        $method = $this->existingInvoiceMethod($this->source());

        Assert::same(0, preg_match('/^\\s*echo\\s+/m', $method));
    }

    public function testBothLegacyCopiesKeepTheSameUc002Fixes(): void
    {
        $root = dirname(__DIR__, 3);
        $current = file_get_contents($root . '/codi-drive/intranet-actual/Intranet.php');
        $verifactu = file_get_contents($root . '/codi-drive/intranet-nova-canvis-verifactu/Intranet.php');

        if (!is_string($current) || !is_string($verifactu)) {
            Assert::fail('Could not load both Intranet.php copies');
        }

        foreach ([$current, $verifactu] as $source) {
            $method = $this->existingInvoiceMethod($source);
            Assert::stringContainsString(
                '$pagat = floatval($pagamentMembre) + floatval($auxPagat);',
                $method
            );
            Assert::same(0, preg_match('/^\\s*echo\\s+/m', $method));

            $queryStart = strpos($source, '"updFactGenerada"');
            Assert::same(true, $queryStart !== false);
            $query = substr($source, (int) $queryStart, 260);
            Assert::same(false, str_contains($query, 'IMPORT = ?'));
        }
    }

    private function source(): string
    {
        $root = dirname(__DIR__, 3);
        $source = file_get_contents($root . '/codi-drive/intranet-actual/Intranet.php');

        if (!is_string($source)) {
            Assert::fail('Could not load UC-002 Intranet.php');
        }

        return $source;
    }

    private function existingInvoiceMethod(string $source): string
    {
        $start = strpos($source, 'function efectuarPagamentFacturaGenerada');
        Assert::same(true, $start !== false);

        $next = strpos($source, "\n\tfunction ", (int) $start + 20);
        if ($next === false) {
            $next = strlen($source);
        }

        return substr($source, (int) $start, (int) $next - (int) $start);
    }
}
