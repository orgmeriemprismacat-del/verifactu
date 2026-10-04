<?php

namespace Prisma\Sif\Tests\Unit;

use Prisma\Sif\Exception\SifException;
use Prisma\Sif\Http\VersionPanelSession;
use Prisma\Sif\Tests\Support\Assert;

final class VersionPanelSessionTest
{
    public function testFreshSessionActorIsAcceptedWithinTtl(): void
    {
        $_SESSION = [
            'sif_version_panel_actor' => [
                'actor_id' => 'meriem',
                'roles' => ['SIF_ADMIN'],
                'request_id' => 'request-1',
                'source_channel' => 'SIF_PANEL',
                'authenticated_at' => time() - 120,
            ],
        ];

        try {
            $actor = (new VersionPanelSession('TESTSESSID', 300))->actor();
            Assert::same('meriem', $actor['actor_id']);
        } finally {
            $_SESSION = [];
        }
    }

    public function testExpiredSessionFailsClosedAndClearsActor(): void
    {
        $_SESSION = [
            'sif_version_panel_actor' => [
                'actor_id' => 'meriem',
                'roles' => ['SIF_ADMIN'],
                'request_id' => 'request-2',
                'source_channel' => 'SIF_PANEL',
                'authenticated_at' => time() - 301,
            ],
            'sif_version_panel_csrf' => str_repeat('a', 64),
        ];

        Assert::throws(
            SifException::class,
            fn () => (new VersionPanelSession('TESTSESSID', 300))->actor(),
            401
        );
        Assert::same([], $_SESSION);
    }
}
