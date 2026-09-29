<?php

namespace Prisma\Sif\Cli;

/** Shared by preview and confirmation; reads local JSON only, never URLs. */
final class FiscalRecordArguments
{
    public const USAGE = '--type=ANULACIO|SUBSANACIO (--uuid-factura=UUID|--num-visible=NUM) --reason=REASON '
        . '[--subsanation-kind=SUBSANACION|RECHAZO_PREVIO|SIN_REGISTRO_PREVIO|SUBSANACION_RECHAZADA] '
        . '[--corrected-fields=LOCAL_JSON] [--cancellation-mode=NORMAL|RECHAZO_PREVIO|SIN_REGISTRO_PREVIO] '
        . '[--reference=REF] [--created-by=USER] [--detail=TEXT] [--correction-summary=TEXT]';

    public function parse(array $args): array
    {
        $names = ['type' => 'type', 'uuid-factura' => 'uuid', 'uuid' => 'uuid',
            'num-visible' => 'number', 'num-fact' => 'number', 'reason' => 'reason', 'motiu' => 'reason',
            'subsanation-kind' => 'subsanation_kind', 'tipus-subsanacio' => 'subsanation_kind',
            'detail' => 'detail', 'detall' => 'detail', 'correction-summary' => 'correction_summary',
            'resum-correccio' => 'correction_summary', 'created-by' => 'created_by', 'usuari' => 'created_by',
            'reference' => 'reference', 'referencia' => 'reference', 'corrected-fields' => 'corrected_fields',
            'cancellation-mode' => 'cancellation_mode'];
        $values = [];
        foreach ($args as $arg) {
            if (!is_string($arg) || !preg_match('/^--([^=]+)=(.+)$/sD', $arg, $match)
                || !isset($names[$match[1]]) || trim($match[2]) === '') {
                throw new \InvalidArgumentException('Invalid CLI option. Usage: ' . self::USAGE);
            }
            $key = $names[$match[1]];
            if (array_key_exists($key, $values)) {
                throw new \InvalidArgumentException('Repeated CLI option.');
            }
            $values[$key] = trim($match[2]);
        }
        $type = strtoupper($values['type'] ?? '');
        if (!in_array($type, ['ANULACIO', 'SUBSANACIO'], true) || !isset($values['reason'])
            || isset($values['uuid']) === isset($values['number'])) {
            throw new \InvalidArgumentException('Type, reason and exactly one invoice selector are required. Usage: ' . self::USAGE);
        }
        $selector = isset($values['uuid']) ? ['type' => 'uuid', 'value' => $values['uuid']]
            : ['type' => 'num_visible', 'value' => $values['number']];
        unset($values['type'], $values['uuid'], $values['number']);
        if ($type === 'SUBSANACIO') {
            if (!isset($values['subsanation_kind']) || isset($values['cancellation_mode'])) {
                throw new \InvalidArgumentException('Subsanation requires its kind and cannot specify cancellation mode.');
            }
            if (isset($values['corrected_fields'])) {
                $values['corrected_fields'] = $this->readFields($values['corrected_fields']);
            }
        } elseif (isset($values['subsanation_kind']) || isset($values['corrected_fields'])) {
            throw new \InvalidArgumentException('Cancellation cannot specify corrected fields or subsanation kind.');
        }
        return [$type, $selector, $values];
    }

    private function readFields(string $path): array
    {
        if (str_contains($path, '://') || !is_file($path) || !is_readable($path)) {
            throw new \InvalidArgumentException('Corrected fields must be a readable local JSON file.');
        }
        $json = file_get_contents($path, false, null, 0, 1048577);
        if ($json === false || strlen($json) > 1048576) {
            throw new \InvalidArgumentException('Corrected fields exceed the 1 MiB limit.');
        }
        try {
            $object = json_decode($json, false, 64, JSON_THROW_ON_ERROR);
            if (!$object instanceof \stdClass || get_object_vars($object) === []) {
                throw new \InvalidArgumentException('Corrected fields must be a non-empty JSON object.');
            }
            return json_decode($json, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new \InvalidArgumentException('Invalid corrected fields JSON.');
        }
    }
}
