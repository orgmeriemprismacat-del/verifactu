<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class PublicWebMutationAuthorizationTest
{
    public function testAllowsConfiguredSameOriginAjaxRequest(): void
    {
        $this->withGuardEnvironment(
            'https://www.prisma.cat',
            '',
            'XMLHttpRequest',
            static function (): void {
                \PublicWebMutationAuthorization::assertSameOriginAjax();
            }
        );
    }

    public function testAllowsAdditionalConfiguredOrigin(): void
    {
        $this->withGuardEnvironment(
            'https://checkout.prisma.cat',
            '',
            'XMLHttpRequest',
            static function (): void {
                \PublicWebMutationAuthorization::assertSameOriginAjax();
            },
            'https://www.prisma.cat;https://prisma.cat;https://checkout.prisma.cat'
        );
    }

    public function testRejectsCrossOriginRequest(): void
    {
        $this->expect403(
            'https://evil.example',
            '',
            'XMLHttpRequest'
        );
    }

    public function testRejectsNonAjaxRequest(): void
    {
        $this->expect403(
            'https://www.prisma.cat',
            '',
            ''
        );
    }

    public function testAllowsConfiguredRefererFallback(): void
    {
        $this->withGuardEnvironment(
            '',
            'https://www.prisma.cat/pack/exemple',
            'XMLHttpRequest',
            static function (): void {
                \PublicWebMutationAuthorization::assertSameOriginAjax();
            }
        );
    }

    private function expect403(string $origin, string $referer, string $requestedWith): void
    {
        $caught = null;
        $this->withGuardEnvironment(
            $origin,
            $referer,
            $requestedWith,
            static function () use (&$caught): void {
                try {
                    \PublicWebMutationAuthorization::assertSameOriginAjax();
                } catch (\RuntimeException $exception) {
                    $caught = $exception;
                }
            }
        );

        Assert::same(true, $caught instanceof \RuntimeException);
        Assert::same(403, $caught?->getCode());
    }

    private function withGuardEnvironment(
        string $origin,
        string $referer,
        string $requestedWith,
        callable $callback,
        string $allowedOrigins = 'https://www.prisma.cat;https://prisma.cat'
    ): void {
        require_once dirname(__DIR__, 3)
            . '/codi-drive/web-actual/inc/PublicWebMutationAuthorization.php';

        $oldAllowed = getenv('WEB_ALLOWED_ORIGINS');
        $oldOrigin = $_SERVER['HTTP_ORIGIN'] ?? null;
        $oldReferer = $_SERVER['HTTP_REFERER'] ?? null;
        $oldRequested = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? null;

        putenv('WEB_ALLOWED_ORIGINS=' . $allowedOrigins);
        $this->setServer('HTTP_ORIGIN', $origin);
        $this->setServer('HTTP_REFERER', $referer);
        $this->setServer('HTTP_X_REQUESTED_WITH', $requestedWith);

        try {
            $callback();
        } finally {
            if ($oldAllowed === false) {
                putenv('WEB_ALLOWED_ORIGINS');
            } else {
                putenv('WEB_ALLOWED_ORIGINS=' . $oldAllowed);
            }
            $this->restoreServer('HTTP_ORIGIN', $oldOrigin);
            $this->restoreServer('HTTP_REFERER', $oldReferer);
            $this->restoreServer('HTTP_X_REQUESTED_WITH', $oldRequested);
        }
    }

    private function setServer(string $key, string $value): void
    {
        if ($value === '') {
            unset($_SERVER[$key]);
            return;
        }
        $_SERVER[$key] = $value;
    }

    private function restoreServer(string $key, mixed $value): void
    {
        if ($value === null) {
            unset($_SERVER[$key]);
            return;
        }
        $_SERVER[$key] = $value;
    }
}
