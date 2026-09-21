<?php

namespace Prisma\Sif\FunctionalCard;

final class CaseInputValidator
{
    private const CONTEXT_ENUMS = [
        'channel' => ['UNKNOWN', 'INTRANET', 'ECOMMERCE', 'PAY_PRISMA', 'API', 'CLI', 'MULTI_CHANNEL'],
        'actor' => ['UNKNOWN', 'ALUMNE', 'EMPRESA_RESPONSABLE', 'OPERADOR', 'RESPONSABLE_TECNICA', 'AUTOMATIC'],
        'invoice_state' => ['UNKNOWN', 'NONE', 'DRAFT', 'ISSUED', 'RECTIFIED', 'HISTORICAL'],
        'payment_state' => ['UNKNOWN', 'UNPAID', 'PARTIAL', 'PAID', 'OVERPAID', 'REFUNDED'],
        'aeat_state' => ['UNKNOWN', 'NOT_APPLICABLE', 'PENDING', 'ACCEPTED', 'ACCEPTED_WITH_ERRORS', 'REJECTED'],
    ];

    private const ARRAY_FIELDS = [
        'entities',
        'known_data',
        'variants',
        'explicit_exclusions',
        'asserted_decisions',
        'additional_sources',
    ];

    public function validate(array $input): array
    {
        $errors = [];

        if (($input['schema_version'] ?? null) !== '1.0') {
            $errors[] = 'schema_version must be exactly 1.0';
        }

        $case = $input['case'] ?? null;
        if (!is_array($case)) {
            $errors[] = 'case must be an object';
            $case = [];
        }

        $this->requireString($case, 'id', 'case.id', $errors);
        $this->requireString($case, 'title', 'case.title', $errors);

        if (isset($case['id']) && is_string($case['id'])
            && preg_match('/^[A-Z][A-Z0-9]*-[0-9]+[A-Z]?$/', $case['id']) !== 1) {
            $errors[] = 'case.id must use a stable identifier such as UC-26';
        }

        $context = $input['context'] ?? [];
        if (!is_array($context)) {
            $errors[] = 'context must be an object';
            $context = [];
        }

        foreach (self::CONTEXT_ENUMS as $field => $allowed) {
            $value = $context[$field] ?? 'UNKNOWN';
            if (!is_string($value) || !in_array($value, $allowed, true)) {
                $errors[] = sprintf('context.%s must be one of: %s', $field, implode(', ', $allowed));
            }
        }

        foreach (self::ARRAY_FIELDS as $field) {
            if (isset($input[$field]) && !is_array($input[$field])) {
                $errors[] = $field . ' must be an array';
            }
        }

        foreach (($input['known_data'] ?? []) as $index => $item) {
            if (!is_array($item)) {
                $errors[] = "known_data[{$index}] must be an object";
                continue;
            }
            $this->requireString($item, 'field', "known_data[{$index}].field", $errors);
            if (!array_key_exists('value', $item)) {
                $errors[] = "known_data[{$index}].value is required";
            }
        }

        foreach (($input['asserted_decisions'] ?? []) as $index => $item) {
            if (!is_array($item)) {
                $errors[] = "asserted_decisions[{$index}] must be an object";
                continue;
            }
            $this->requireString($item, 'statement', "asserted_decisions[{$index}].statement", $errors);
        }

        foreach (($input['explicit_exclusions'] ?? []) as $index => $item) {
            if (!is_array($item)) {
                $errors[] = "explicit_exclusions[{$index}] must be an object";
                continue;
            }
            $this->requireString($item, 'statement', "explicit_exclusions[{$index}].statement", $errors);
            $this->requireString($item, 'source', "explicit_exclusions[{$index}].source", $errors);
        }

        foreach (($input['additional_sources'] ?? []) as $index => $path) {
            if (!is_string($path) || trim($path) === '') {
                $errors[] = "additional_sources[{$index}] must be a non-empty repository-relative path";
            }
        }

        $output = $input['output'] ?? [];
        if (!is_array($output)) {
            $errors[] = 'output must be an object';
            $output = [];
        }

        if (($output['language'] ?? 'ca') !== 'ca') {
            $errors[] = 'the MVP only supports output.language=ca';
        }
        if (($output['mode'] ?? 'PREVIEW') !== 'PREVIEW') {
            $errors[] = 'the MVP only supports output.mode=PREVIEW';
        }

        if ($errors !== []) {
            throw new \InvalidArgumentException("Invalid functional-card input:\n- " . implode("\n- ", $errors));
        }

        return [
            'schema_version' => '1.0',
            'case' => [
                'id' => trim((string) $case['id']),
                'title' => trim((string) $case['title']),
                'objective' => $this->optionalString($case, 'objective', 'UNKNOWN'),
                'trigger' => $this->optionalString($case, 'trigger', 'UNKNOWN'),
                'requested_by' => $this->optionalString($case, 'requested_by', 'UNKNOWN'),
            ],
            'context' => [
                'channel' => $context['channel'] ?? 'UNKNOWN',
                'actor' => $context['actor'] ?? 'UNKNOWN',
                'invoice_state' => $context['invoice_state'] ?? 'UNKNOWN',
                'payment_state' => $context['payment_state'] ?? 'UNKNOWN',
                'aeat_state' => $context['aeat_state'] ?? 'UNKNOWN',
            ],
            'entities' => array_values($input['entities'] ?? []),
            'known_data' => array_values($input['known_data'] ?? []),
            'variants' => array_values($input['variants'] ?? []),
            'explicit_exclusions' => array_values($input['explicit_exclusions'] ?? []),
            'asserted_decisions' => array_values($input['asserted_decisions'] ?? []),
            'additional_sources' => array_values($input['additional_sources'] ?? []),
            'output' => [
                'language' => 'ca',
                'mode' => 'PREVIEW',
            ],
        ];
    }

    private function requireString(array $values, string $field, string $label, array &$errors): void
    {
        if (!isset($values[$field]) || !is_string($values[$field]) || trim($values[$field]) === '') {
            $errors[] = $label . ' must be a non-empty string';
        }
    }

    private function optionalString(array $values, string $field, string $default): string
    {
        $value = $values[$field] ?? $default;
        return is_string($value) && trim($value) !== '' ? trim($value) : $default;
    }
}
