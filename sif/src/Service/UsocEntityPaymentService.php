<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Repository\UsocFinancingCaseRepository;

final class UsocEntityPaymentService
{
    public function __construct(
        private UsocFinancingCaseRepository $cases,
        private ManualPaymentService $manualPayments,
        private UsocCaseReconciler $reconciler
    ) {
    }

    public function registerByEntityInvoiceUuid(
        \PDO $sifDb,
        string $uuidEntityInvoice,
        array $input
    ): array {
        $uuidEntityInvoice = trim($uuidEntityInvoice);
        if ($uuidEntityInvoice === '') {
            throw SifException::validation('Missing USOC entity invoice UUID');
        }

        $case = $this->cases->findByEntityInvoice($sifDb, $uuidEntityInvoice, true);
        if ($case === null) {
            throw SifException::conflict('Invoice is not linked to a USOC financing case');
        }

        $payment = $this->manualPayments->registerByUuid($sifDb, $uuidEntityInvoice, $input);

        try {
            $reconciled = $this->reconciler->reconcile(
                $sifDb,
                (int) $case['ID_INSC'],
                (int) $case['IDPAG']
            );
        } catch (\Throwable $exception) {
            $payment['reconciliation_pending'] = true;
            $payment['reconciliation_error'] = $exception->getMessage();

            return $payment;
        }

        $payment['reconciliation_pending'] = false;
        $payment['usoc_case'] = $reconciled;

        return $payment;
    }
}
