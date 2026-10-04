<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class GiftReservationNotificationBoundaryTest
{
    public function testReservationNotificationApiIsSignedScopedAndIdempotent(): void
    {
        $endpoint = $this->read('sif/public/api/gifts/reservation/notifications.php');
        $service = $this->read('sif/src/Service/GiftReservationNotificationService.php');
        $client = $this->read('codi-drive/web-actual/inc/SifGiftReservationNotificationClient.php');

        Assert::stringContainsString('InternalApiAuthenticator', $endpoint);
        Assert::stringContainsString("'PAYMENT_CHANNEL'", $endpoint);
        Assert::stringContainsString('GIFT_RESERVATION_', $endpoint);
        Assert::stringContainsString("action' => 'enqueue'", $client);
        Assert::stringContainsString("action' => 'claim'", $client);
        Assert::stringContainsString("action' => 'complete'", $client);
        Assert::stringContainsString('CURLOPT_SSL_VERIFYPEER => true', $client);
        Assert::stringContainsString('CURLOPT_SSL_VERIFYHOST => 2', $client);
        Assert::stringContainsString('NOTIFY|', $service);
        Assert::stringContainsString("'gift_code_in_payload' => false", $service);
    }

    public function testCutoverUsesOutboxAndLegacyDirectMailOnlyAsRollback(): void
    {
        $source = $this->read('codi-drive/web-actual/RegalCurs.php');
        $method = $this->reservationMethod($source);

        Assert::stringContainsString('SifGiftReservationNotificationClient', $method);
        Assert::stringContainsString('GIFT_RESERVATION_BUYER_CONFIRMATION', $method);
        Assert::stringContainsString('GIFT_RESERVATION_INTERNAL_CONFIRMATION', $method);
        Assert::stringContainsString('$giftReservationNotificationClient->claim(', $method);
        Assert::stringContainsString('$giftReservationNotificationClient->complete(', $method);
        Assert::stringContainsString('if (!$giftCutoverEnabled && $reservationCreated)', $method);
        Assert::stringContainsString('if ($giftCutoverEnabled)', $method);
        Assert::stringContainsString('AMBIGUOUS_SENDING', $method);

        $cutover = strpos($method, 'if ($giftCutoverEnabled)');
        $governed = strpos($method, '$sendGovernedReservationMail');
        Assert::same(true, $cutover !== false && $governed !== false && $cutover < $governed);
    }

    public function testGiftPreflightRequiresReservationNotificationBridge(): void
    {
        $source = $this->read('sif/scripts/preflight-redsys-gift.php');

        Assert::stringContainsString(
            'gift_reservation_notification_signed_path_matches_bridge',
            $source
        );
        Assert::stringContainsString(
            'gift_reservation_notification_endpoint_present',
            $source
        );
    }

    private function reservationMethod(string $source): string
    {
        $start = strpos($source, 'public function enviarInscripcioRegal(');
        $end = strpos($source, 'private function __mostrarClaseTamanyNomCurs', $start ?: 0);

        return ($start !== false && $end !== false)
            ? substr($source, $start, $end - $start)
            : '';
    }

    private function read(string $relativePath): string
    {
        $content = file_get_contents(dirname(__DIR__, 3) . '/' . $relativePath);
        if ($content === false) {
            Assert::fail('Could not read ' . $relativePath);
        }

        return $content;
    }
}
