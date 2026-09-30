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
        'source_ids',
        'source_type',
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

        if (($value = $this->stringValue($criteria, 'source_type', 30)) !== null) {
            $value = strtoupper($value);
            if (preg_match('/^[A-Z0-9_]+$/D', $value) !== 1) {
                throw SifException::validation('Invalid source type');
            }
            $clean['source_type'] = $value;
        }

        if (array_key_exists('source_ids', $criteria)) {
            if (!is_array($criteria['source_ids'])) {
                throw SifException::validation('Invalid source ids');
            }

            $sourceIds = [];
            foreach ($criteria['source_ids'] as $sourceId) {
                $value = (string) $sourceId;
                if (!ctype_digit($value) || (int) $value <= 0) {
                    throw SifException::validation('Invalid source id');
                }
                $sourceIds[(int) $value] = true;
            }

            if (count($sourceIds) > 200) {
                throw SifException::validation('Too many source ids');
            }

            if ($sourceIds !== []) {
                $clean['source_ids'] = array_keys($sourceIds);
            }
        }

        if (array_key_exists('factura_relacionada', $criteria)
            && $criteria['factura_relacionada'] !== null
            && $criteria['factura_relacionada'] !== '') {
            $legacy = (string) $criteria['factura_relacionada'];
            if (!ctype_digit($legacy)) {
                throw SifException::validation('Invalid legacy invoice relation');
            }
            $clean['factura_relacionada'] = (int) $legacy;
        }

        if (($value = $this->stringValue($criteria, 'source_type', 40)) !== null) {
            $value = strtoupper($value);
            if (preg_match('/^[A-Z0-9_:-]+$/D', $value) !== 1) {
                throw SifException::validation('Invalid invoice source type');
            }
            $clean['source_type'] = $value;
        }

        if (array_key_exists('source_ids', $criteria) && $criteria['source_ids'] !== null) {
            if (!is_array($criteria['source_ids'])) {
                throw SifException::validation('Invalid invoice source ids');
            }

            $sourceIds = [];
            foreach ($criteria['source_ids'] as $sourceId) {
                $value = (string) $sourceId;
                if (!ctype_digit($value) || (int) $value <= 0) {
                    throw SifException::validation('Invalid invoice source id');
                }
                $sourceIds[(int) $value] = true;
                if (count($sourceIds) > 200) {
                    throw SifException::validation('Too many invoice source ids');
                }
            }

            if ($sourceIds !== []) {
                $clean['source_ids'] = array_keys($sourceIds);
            }
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
