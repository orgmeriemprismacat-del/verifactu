<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class UsocCourseChangePreviewService
{
    public function __construct(
        private UsocLifecyclePlanService $lifecycle,
        private UsocCourseChangeTargetResolver $targets,
        private UsocCourseChangeFundPlanService $funds
    ) {
    }

    public function preview(
        \PDO $db,
        int $idInsc,
        int $idpag,
        array $targetInput
    ): array {
        $plan = $this->lifecycle->plan(
            $db,
            $idInsc,
            $idpag,
            'course_change'
        );

        if (($plan['requires_usoc_orchestration'] ?? false) !== true) {
            throw SifException::conflict(
                'USOC course change preview requires an orchestrated financing case'
            );
        }

        $target = $this->targets->resolve($targetInput);
        $fundPlan = $this->funds->plan($plan, $target);

        return [
            'ok' => true,
            'operation' => 'course_change',
            'id_insc' => $idInsc,
            'idpag' => $idpag,
            'lifecycle_plan' => $plan,
            'target' => $target,
            'fund_plan' => $fundPlan,
            'can_execute' => false,
            'execution_status' => 'EXECUTOR_NOT_IMPLEMENTED',
            'invariants' => [
                'preview_has_no_fiscal_effect' => true,
                'preview_has_no_economic_effect' => true,
                'target_prices_are_server_resolved' => true,
                'never_cross_payer_funds' => true,
            ],
        ];
    }
}
