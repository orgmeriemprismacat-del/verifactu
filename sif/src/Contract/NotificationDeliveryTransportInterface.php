<?php

declare(strict_types=1);

namespace Prisma\Sif\Contract;

interface NotificationDeliveryTransportInterface
{
    /**
     * Delivers one already-claimed notification.
     *
     * @return array{provider_ref?: ?string}
     */
    public function deliver(array $notification): array;
}
