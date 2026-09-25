<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Cli\FiscalRecordArguments;
use Prisma\Sif\Tests\Support\Assert;

final class FiscalRecordArgumentsTest
{
    public function testReadsLocalCorrectedFieldsWithDecimalStrings(): void
    {
        $file = tempnam(sys_get_temp_dir(), 'sif-fields-');
        try {
            file_put_contents($file, '{"ImporteTotal":"121.00","DescripcionOperacion":"Formació"}');
            [$type, $selector, $input] = (new FiscalRecordArguments())->parse([
                '--type=SUBSANACIO', '--num-visible=A2026/000001', '--reason=ERROR',
                '--subsanation-kind=SUBSANACION_RECHAZADA', '--corrected-fields=' . $file]);
            Assert::same('SUBSANACIO', $type);
            Assert::same('num_visible', $selector['type']);
            Assert::same('121.00', $input['corrected_fields']['ImporteTotal']);
            Assert::same(false, str_contains(json_encode($input), $file));
        } finally {
            unlink($file);
        }
    }

    public function testRejectsAmbiguousAndIrrelevantOptions(): void
    {
        $base = ['--type=ANULACIO', '--uuid=123', '--reason=ERROR'];
        foreach ([['--num-visible=other'], ['--uuid-factura=other'], ['--unknown=x'],
            ['--subsanation-kind=SUBSANACION'], ['--corrected-fields=anything']] as $extra) {
            Assert::throws(\InvalidArgumentException::class,
                fn () => (new FiscalRecordArguments())->parse(array_merge($base, $extra)));
        }
    }

    public function testRejectsUrlsMalformedJsonListsAndOversizedFiles(): void
    {
        $base = ['--type=SUBSANACIO', '--uuid=123', '--reason=ERROR', '--subsanation-kind=SUBSANACION'];
        Assert::throws(\InvalidArgumentException::class, fn () => (new FiscalRecordArguments())->parse(
            array_merge($base, ['--corrected-fields=https://example.invalid/fields.json'])));
        $file = tempnam(sys_get_temp_dir(), 'sif-fields-');
        try {
            foreach (['{', '[]', '{}', 'null', str_repeat(' ', 1048577)] as $contents) {
                file_put_contents($file, $contents);
                Assert::throws(\InvalidArgumentException::class, fn () => (new FiscalRecordArguments())->parse(
                    array_merge($base, ['--corrected-fields=' . $file])));
            }
        } finally {
            unlink($file);
        }
    }
}
