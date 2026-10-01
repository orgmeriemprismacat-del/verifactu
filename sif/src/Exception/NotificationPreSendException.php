<?php

declare(strict_types=1);

namespace Prisma\Sif\Exception;

/**
 * Delivery failed before the transport handed the message to the provider.
 * Retrying is safe because no external delivery may have occurred.
 */
final class NotificationPreSendException extends \RuntimeException
{
}
