<?php

require_once __DIR__ . '/LegacyDiscountValidationLookup.php';
require_once __DIR__ . '/SifAuthenticatedActor.php';
require_once __DIR__ . '/SifInternalUsocClient.php';

final class LegacyUsocLifecycleGuard
{
    public function __construct(
        private ?LegacyDiscountValidationLookup $legacyLookup = null,
        private ?SifInternalUsocClient $sifClient = null
    ) {
        $this->legacyLookup ??= new LegacyDiscountValidationLookup();
    }

    public function inspect(
        object $user,
        int $idInsc,
        string $operation
    ): array {
        if (!in_array($operation, ['course_change', 'cancellation'], true)) {
            throw new InvalidArgumentException('Invalid USOC lifecycle operation');
        }

        $enrollment = $this->legacyLookup->enrollment($idInsc);
        if ((int) $enrollment['TIPUS_DESC'] !== 4) {
            return [
                'tracked_usoc' => false,
                'allowed' => true,
                'reason' => 'NOT_USOC',
                'operation' => $operation,
                'id_insc' => $idInsc,
            ];
        }

        $idpag = (int) ($enrollment['IDPAG'] ?? 0);
        if ($idpag <= 0) {
            throw new RuntimeException(
                'La inscripció USOC no té un IDPAG vàlid i no es pot modificar amb el flux legacy.',
                409
            );
        }

        [$actorId, $roles] = SifAuthenticatedActor::fromUser($user);
        $this->sifClient ??= new SifInternalUsocClient();
        $response = $this->sifClient->lifecycleGuard(
            $actorId,
            $roles,
            $idInsc,
            $idpag,
            $operation
        );

        $status = (int) ($response['_http_status'] ?? 0);
        if ($status < 200 || $status >= 300 || ($response['ok'] ?? false) !== true) {
            throw new RuntimeException(
                'No s’ha pogut validar l’estat fiscal USOC abans de modificar la inscripció.',
                503
            );
        }

        $guard = $response['guard'] ?? null;
        if (!is_array($guard) || !array_key_exists('allowed', $guard)) {
            throw new RuntimeException('Resposta de control USOC no vàlida.', 502);
        }

        $guard['tracked_usoc'] = true;
        return $guard;
    }

    public function assertMayUseLegacyMutation(
        object $user,
        int $idInsc,
        string $operation
    ): void {
        $guard = $this->inspect($user, $idInsc, $operation);
        if (($guard['allowed'] ?? false) === true) {
            return;
        }

        $label = $operation === 'course_change' ? 'canvi de curs' : 'baixa';
        $reason = (string) ($guard['reason'] ?? '');

        if ($reason === 'USOC_FISCAL_EVIDENCE_WITHOUT_CASE_REQUIRES_REVIEW') {
            throw new RuntimeException(
                'Aquesta inscripció té evidència fiscal USOC al SIF però no té '
                . 'un expedient de finançament coherent. Cal reconciliar-la abans '
                . 'de tramitar el ' . $label . '.',
                409
            );
        }

        throw new RuntimeException(
            'Aquesta inscripció USOC té un expedient SIF amb dues parts. '
            . 'El ' . $label . ' s’ha de tramitar amb el flux SIF específic per evitar '
            . 'rectificar, retornar o reassignar imports del pagador incorrecte.',
            409
        );
    }
}
