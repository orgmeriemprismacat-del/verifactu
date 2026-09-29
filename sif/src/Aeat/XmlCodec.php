<?php

namespace Prisma\Sif\Aeat;

final class XmlCodec
{
    public const SOAP = 'http://schemas.xmlsoap.org/soap/envelope/';
    public const BASE = 'https://www2.agenciatributaria.gob.es/static_files/common/internet/dep/aplicaciones/es/aeat/tike/cont/ws/';

    public function __construct(private ?string $schemaDir = null)
    {
        $this->schemaDir ??= dirname(__DIR__, 2) . '/resources/aeat';
        (new SchemaManifest())->verify($this->schemaDir);
    }

    /** Native AEAT fields in XSD order. Lists represent repeated elements. */
    public function request(array $snapshot): string
    {
        $type = $snapshot['type'] ?? '';
        $record = $snapshot['record'] ?? [];
        if (!in_array($type, ['RegistroAlta', 'RegistroAnulacion'], true)
            || !isset($record['Huella'])
            || !hash_equals((new RecordHash())->calculate($type, $record), $record['Huella'])) {
            throw new \RuntimeException('Missing or invalid immutable AEAT snapshot/hash.');
        }
        $doc = new \DOMDocument('1.0', 'UTF-8');
        $root = $doc->createElementNS(self::BASE . 'SuministroLR.xsd', 'sum:RegFactuSistemaFacturacion');
        $doc->appendChild($root);
        $header = $doc->createElementNS(self::BASE . 'SuministroLR.xsd', 'sum:Cabecera');
        $root->appendChild($header);
        $this->append($doc, $header, $snapshot['header'] ?? [], 'CabeceraType');
        $entry = $doc->createElementNS(self::BASE . 'SuministroLR.xsd', 'sum:RegistroFactura');
        $root->appendChild($entry);
        $node = $doc->createElementNS(self::BASE . 'SuministroInformacion.xsd', 'sf:' . $type);
        $entry->appendChild($node);
        $this->append($doc, $node, $record, $type === 'RegistroAlta'
            ? 'RegistroFacturacionAltaType' : 'RegistroFacturacionAnulacionType');
        $this->validate($doc, 'SuministroLR.xsd');
        $soap = new \DOMDocument('1.0', 'UTF-8');
        $envelope = $soap->createElementNS(self::SOAP, 'soap:Envelope');
        $soap->appendChild($envelope);
        $body = $soap->createElementNS(self::SOAP, 'soap:Body');
        $envelope->appendChild($body);
        $body->appendChild($soap->importNode($root, true));
        return $soap->saveXML();
    }

    public function parse(string $xml): \DOMDocument
    {
        if (strlen($xml) > 8 * 1024 * 1024 || preg_match('/<!DOCTYPE|<!ENTITY/i', $xml)) {
            throw new \RuntimeException('Unsafe or oversized AEAT XML.');
        }
        $previous = libxml_use_internal_errors(true);
        try {
            $doc = new \DOMDocument();
            if (!$doc->loadXML($xml, LIBXML_NONET)) {
                throw new \RuntimeException('Malformed AEAT XML.');
            }
            return $doc;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    public function validate(\DOMDocument $doc, string $schema): void
    {
        if (!in_array($schema, ['SuministroLR.xsd', 'RespuestaSuministro.xsd'], true)) {
            throw new \InvalidArgumentException('Unknown local schema.');
        }
        $previous = libxml_use_internal_errors(true);
        // Only bundled imports are allowed, including the W3C signature dependency.
        libxml_set_external_entity_loader(function ($public, $system) {
            $name = basename((string) $system);
            if (!in_array($name, ['SuministroLR.xsd', 'SuministroInformacion.xsd',
                'RespuestaSuministro.xsd', 'xmldsig-core-schema.xsd'], true)) {
                return null;
            }
            return fopen($this->schemaDir . '/' . $name, 'rb');
        });
        try {
            if (!$doc->schemaValidate($this->schemaDir . '/' . $schema)) {
                throw new \RuntimeException('AEAT XSD validation failed.');
            }
        } finally {
            libxml_set_external_entity_loader(null);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function append(\DOMDocument $doc, \DOMElement $parent, array $values, string|\DOMElement|null $spec = null): void
    {
        // MySQL JSON reorders object keys. Derive element order from the bundled XSD,
        // not from PHP/JSON insertion order, so a persisted snapshot remains valid.
        $definitions = $this->definitions($spec);
        if ($definitions !== []) {
            if (array_diff(array_keys($values), array_keys($definitions))) {
                throw new \InvalidArgumentException('Unknown AEAT fields for schema type.');
            }
            $values = array_replace(array_intersect_key($definitions, $values), $values);
        }
        foreach ($values as $name => $value) {
            if (!is_string($name) || !preg_match('/^[A-Za-z][A-Za-z0-9]*$/D', $name) || $name === 'Signature') {
                throw new \InvalidArgumentException('Invalid AEAT element name.');
            }
            foreach (is_array($value) && array_is_list($value) ? $value : [$value] as $item) {
                $node = $doc->createElementNS(self::BASE . 'SuministroInformacion.xsd', 'sf:' . $name);
                $parent->appendChild($node);
                if (is_array($item)) {
                    $definition = $definitions[$name] ?? null;
                    $nested = $definition?->getAttribute('type');
                    $this->append($doc, $node, $item, $nested ? preg_replace('/^sf:/', '', $nested) : $definition);
                } elseif (is_string($item)) {
                    $node->appendChild($doc->createTextNode($item));
                } else {
                    throw new \InvalidArgumentException('AEAT XML scalars must be strings.');
                }
            }
        }
    }

    private function definitions(string|\DOMElement|null $spec): array
    {
        if ($spec === null) {
            return [];
        }
        if (is_string($spec)) {
            $schema = new \DOMDocument();
            $schema->load($this->schemaDir . '/SuministroInformacion.xsd', LIBXML_NONET);
            $xp = new \DOMXPath($schema);
            $xp->registerNamespace('xs', 'http://www.w3.org/2001/XMLSchema');
            $spec = $xp->query('/xs:schema/xs:complexType[@name="' . $spec . '"]')->item(0);
            if (!$spec) {
                return [];
            }
        }
        $result = [];
        foreach ($spec->childNodes as $child) {
            if (!$child instanceof \DOMElement) {
                continue;
            }
            if ($child->localName === 'element') {
                $name = $child->getAttribute('name');
                if ($name !== '') {
                    $result[$name] = $child;
                }
            } elseif (in_array($child->localName, ['complexType', 'sequence', 'choice'], true)) {
                $result += $this->definitions($child);
            }
        }
        return $result;
    }
}
