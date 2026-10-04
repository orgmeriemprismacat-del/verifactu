<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class UsocFinancingTermsStateHasher
{
    public function hash(array $legacy): string
    {
        foreach (['ID', 'IDPAG', 'ANY', 'MES', 'CURS', 'TIPUS_DESC', 'VALID_DESC', 'A_PAGAR'] as $field) {
            if (!array_key_exists($field, $legacy) || $legacy[$field] === '' || $legacy[$field] === null) {
                throw SifException::validation(
                    'Missing legacy USOC financing terms field ' . $field
                );
            }
        }

        if (!is_numeric($legacy['A_PAGAR'])) {
            throw SifException::validation('Invalid legacy USOC A_PAGAR amount');
        }

        $canonical = json_encode([
            'ID' => (int) $legacy['ID'],
            'IDPAG' => (int) $legacy['IDPAG'],
            'ANY' => (int) $legacy['ANY'],
            'MES' => (string) $legacy['MES'],
            'CURS' => (string) $legacy['CURS'],
            'TIPUS_DESC' => (int) $legacy['TIPUS_DESC'],
            'VALID_DESC' => (int) $legacy['VALID_DESC'],
            'A_PAGAR' => number_format((float) $legacy['A_PAGAR'], 2, '.', ''),
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES);

        return hash('sha256', $canonical);
    }
}
