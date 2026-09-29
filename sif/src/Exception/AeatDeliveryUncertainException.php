<?php

namespace Prisma\Sif\Exception;

/**
 * The request may have reached AEAT, but the local process cannot prove the
 * final remote outcome. This state must be reviewed/reconciled, never retried
 * blindly as a normal transport failure.
 */
final class AeatDeliveryUncertainException extends \RuntimeException
{
}
