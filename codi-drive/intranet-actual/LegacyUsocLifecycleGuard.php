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

    public function plan(
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
                'requires_usoc_orchestration' => false,
                'operation' => $operation,
                'id_insc' => $idInsc,
            ];
        }

        $idpag = (int) ($enrollment['IDPAG'] ?? 0);
        if ($idpag <= 0) {
            throw new RuntimeException(
                'La inscripció USOC no té un IDPAG vàlid i no es pot planificar.',
                409
            );
        }

        [$actorId, $roles] = SifAuthenticatedActor::fromUser($user);
        $this->sifClient ??= new SifInternalUsocClient();
        $response = $this->sifClient->lifecyclePlan(
            $actorId,
            $roles,
            $idInsc,
            $idpag,
            $operation
        );

        $status = (int) ($response['_http_status'] ?? 0);
        if ($status < 200 || $status >= 300 || ($response['ok'] ?? false) !== true) {
            throw new RuntimeException(
                'No s’ha pogut obtenir el pla fiscal USOC abans de continuar.',
                503
            );
        }

        $plan = $response['plan'] ?? null;
        if (!is_array($plan)) {
            throw new RuntimeException('Resposta de pla USOC no vàlida.', 502);
        }

        $plan['tracked_usoc'] = true;
        return $plan;
    }

    public function completedCancellationExecution(
        object $user,
        int $idInsc,
        string $requestId
    ): ?array {
        $requestId = trim($requestId);
        if ($requestId === '') {
            return null;
        }

        $enrollment = $this->legacyLookup->enrollment($idInsc);
        if ((int) $enrollment['TIPUS_DESC'] !== 4) {
            return null;
        }

        $idpag = (int) ($enrollment['IDPAG'] ?? 0);
        if ($idpag <= 0) {
            throw new RuntimeException(
                'La inscripció USOC no té un IDPAG vàlid per verificar la baixa.',
                409
            );
        }

        [$actorId, $roles] = SifAuthenticatedActor::fromUser($user);
        $this->sifClient ??= new SifInternalUsocClient();
        $response = $this->sifClient->cancellationExecutionStatus(
            $actorId,
            $roles,
            $requestId
        );

        $status = (int) ($response['_http_status'] ?? 0);
        if ($status < 200 || $status >= 300 || ($response['ok'] ?? false) !== true) {
            throw new RuntimeException(
                'No s’ha pogut verificar la comanda de baixa USOC al SIF.',
                503
            );
        }

        $execution = $response['execution'] ?? null;
        if (!is_array($execution)) {
            throw new RuntimeException('Resposta de baixa USOC no vàlida.', 502);
        }

        if (
            (int) ($execution['ID_INSC'] ?? 0) !== $idInsc
            || (int) ($execution['IDPAG'] ?? 0) !== $idpag
            || strtoupper((string) ($execution['OPERATION'] ?? '')) !== 'CANCELLATION'
            || strtoupper((string) ($execution['STATE'] ?? '')) !== 'COMPLETED'
        ) {
            return null;
        }

        return $execution;
    }

    public function assertMayUseLegacyMutation(
        object $user,
        int $idInsc,
        string $operation,
        ?string $completedRequestId = null
    ): void {
        if (
            $operation === 'cancellation'
            && $completedRequestId !== null
            && $this->completedCancellationExecution($user, $idInsc, $completedRequestId) !== null
        ) {
            return;
        }
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
