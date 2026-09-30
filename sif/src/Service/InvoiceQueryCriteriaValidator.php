<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class InvoiceQueryCriteriaValidator
{
    private const ALLOWED = [
        'uuid_factura',
        'num_visible',
        'billing_nif',
        'billing_email',
        'factura_relacionada',
        'source_type',
        'source_ids',
    ];

    public function validate(array $criteria): array
    {
        foreach ($criteria as $key => $_value) {
            if (!in_array((string) $key, self::ALLOWED, true)) {
                throw SifException::validation('Unknown invoice search criterion: ' . $key);
            }
        }

        $clean = [];

        if (($value = $this->stringValue($criteria, 'uuid_factura', 36)) !== null) {
            if (preg_match('/^[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[1-5][0-9a-fA-F]{3}-[89abAB][0-9a-fA-F]{3}-[0-9a-fA-F]{12}$/D', $value) !== 1) {
                throw SifException::validation('Invalid invoice UUID');
            }
            $clean['uuid_factura'] = strtolower($value);
        }

        if (($value = $this->stringValue($criteria, 'num_visible', 40)) !== null) {
            $clean['num_visible'] = strtoupper($value);
        }

        if (($value = $this->stringValue($criteria, 'billing_nif', 32)) !== null) {
            $clean['billing_nif'] = strtoupper($value);
        }

        if (($value = $this->stringValue($criteria, 'billing_email', 190)) !== null) {
            if (filter_var($value, FILTER_VALIDATE_EMAIL) === false) {
                throw SifException::validation('Invalid billing email');
            }
            $clean['billing_email'] = strtolower($value);
        }

        if (array_key_exists('factura_relacionada', $criteria)
            && $criteria['factura_relacionada'] !== null
            && $criteria['factura_relacionada'] !== '') {
            $legacy = (string) $criteria['factura_relacionada'];
            if (!ctype_digit($legacy) || (int) $legacy <= 0) {
                throw SifException::validation('Invalid legacy invoice relation');
            }
            $clean['factura_relacionada'] = (int) $legacy;
        }

        $hasSourceType = array_key_exists('source_type', $criteria)
            && trim((string) ($criteria['source_type'] ?? '')) !== '';
        $hasSourceIds = array_key_exists('source_ids', $criteria)
            && $criteria['source_ids'] !== null;

        if ($hasSourceType || $hasSourceIds) {
            if (!$hasSourceType || !$hasSourceIds || !is_array($criteria['source_ids'])) {
                throw SifException::validation('Source type and source ids are required together');
            }

            $sourceType = strtoupper(trim((string) $criteria['source_type']));
            if (mb_strlen($sourceType, 'UTF-8') > 30
                || preg_match('/^[A-Z0-9_:-]+$/D', $sourceType) !== 1) {
                throw SifException::validation('Invalid source type');
            }

            $sourceIds = [];
            foreach ($criteria['source_ids'] as $sourceId) {
                $value = (string) $sourceId;
                if (!ctype_digit($value) || (int) $value <= 0) {
                    throw SifException::validation('Invalid source id');
                }

                $sourceIds[(int) $value] = true;
                if (count($sourceIds) > 200) {
                    throw SifException::validation('Too many source ids');
                }
            }

            if ($sourceIds === []) {
                throw SifException::validation('Source ids cannot be empty');
            }

            $clean['source_type'] = $sourceType;
            $clean['source_ids'] = array_keys($sourceIds);
        }

        if ($clean === []) {
            throw SifException::validation('At least one invoice search criterion is required');
        }

        return $clean;
    }

    private function stringValue(array $criteria, string $key, int $maxLength): ?string
    {
        if (!array_key_exists($key, $criteria) || $criteria[$key] === null) {
            return null;
        }

        $value = trim((string) $criteria[$key]);
        if ($value === '') {
            return null;
        }

        if (mb_strlen($value, 'UTF-8') > $maxLength) {
            throw SifException::validation('Invoice search criterion is too long: ' . $key);
        }

        return $value;
    }
}
