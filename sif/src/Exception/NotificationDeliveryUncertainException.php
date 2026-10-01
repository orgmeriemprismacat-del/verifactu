<?php

declare(strict_types=1);

namespace Prisma\Sif\Exception;

/**
 * The transport cannot prove whether the provider accepted the message.
 * Automatic retry is unsafe because it could duplicate the notification.
 */
final class NotificationDeliveryUncertainException extends \RuntimeException
{
}
