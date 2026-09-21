<?php

namespace Prisma\Sif\Aeat;

use Prisma\Sif\Contract\AeatTransport;

/** SOAP 1.1 over cURL/mTLS. No network access in construction or preview. */
final class SoapTransport implements AeatTransport
{
    public const TEST_ENDPOINT = 'https://prewww1.aeat.es/wlpl/TIKE-CONT/ws/SistemaFacturacion/VerifactuSOAP';

    public function __construct(private ClientCertificate $certificate, private EvidenceStore $evidence,
        private string $endpoint = self::TEST_ENDPOINT, private ?string $caFile = null)
    {
        // Deliberately limited to the external test service until release qualification.
        if ($endpoint !== self::TEST_ENDPOINT) {
            throw new \InvalidArgumentException('This candidate transport only supports the AEAT test endpoint.');
        }
    }

    public function send(array $fiscalPayload): array
    {
        $snapshot = $fiscalPayload['aeat'] ?? null;
        if (!is_array($snapshot)) {
            throw new \RuntimeException('Legacy/internal payload cannot be sent to AEAT.');
        }
        $request = (new XmlCodec())->request($snapshot);
        $certificateInfo = $this->certificate->inspect();
        if (!extension_loaded('curl')) {
            throw new \RuntimeException('AEAT transport requires cURL.');
        }
        $attempt = $this->evidence->begin($request, [
            'created_at_utc' => gmdate('c'), 'environment' => 'preproduction',
            'endpoint' => $this->endpoint, 'fiscal_order' => $fiscalPayload['fiscal_order'] ?? null,
            'uuid_factura' => $fiscalPayload['uuid_factura'] ?? null,
            'record_hash' => $snapshot['record']['Huella'], 'certificate' => $certificateInfo,
        ]);
        $curl = curl_init($this->endpoint);
        $raw = '';
        $options = [CURLOPT_POST => true, CURLOPT_POSTFIELDS => $request,
            CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'SOAPAction: ""'],
            CURLOPT_FOLLOWLOCATION => false, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_CONNECTTIMEOUT => 10, CURLOPT_TIMEOUT => 45,
            CURLOPT_WRITEFUNCTION => static function ($handle, string $chunk) use (&$raw): int {
                if (strlen($raw) + strlen($chunk) > 8 * 1024 * 1024) {
                    return 0;
                }
                $raw .= $chunk;
                return strlen($chunk);
            },
        ] + $this->certificate->curlOptions();
        if ($this->caFile !== null) {
            $options[CURLOPT_CAINFO] = $this->caFile;
        }
        try {
            curl_setopt_array($curl, $options);
            $ok = curl_exec($curl);
            $http = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
            $errno = curl_errno($curl);
            $this->evidence->response($attempt, $raw, $http);
            if ($ok === false || $http !== 200) {
                $this->evidence->failure($attempt, 'CURL_' . $errno . '_HTTP_' . $http);
                throw new \RuntimeException('AEAT delivery uncertain; evidence=' . $attempt);
            }
            try {
                $result = (new ResponseParser())->parse($raw, $snapshot);
            } catch (\Throwable $error) {
                $this->evidence->failure($attempt, 'INVALID_SOAP_RESPONSE');
                throw new \RuntimeException('AEAT response requires review; evidence=' . $attempt);
            }
            $result['request_xml'] = $request;
            $result['response']['evidence_id'] = $attempt;
            return $result;
        } finally {
            curl_close($curl);
        }
    }
}
