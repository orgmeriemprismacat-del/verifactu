<?php

namespace Prisma\Sif\Tests\Integration;

use Prisma\Sif\Tests\Support\Assert;

final class NovicePromotionInternalApiContractTest
{
    public function testSecretaryDecisionBridgeKeepsIdentityAndSigningServerSide(): void
    {
        $root = dirname(__DIR__, 3);
        $endpoint = file_get_contents($root . '/codi-drive/intranet-actual/ajax/alumnes/sendMsgValidatProfessorNovell.php');
        $client = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalNovicePromotionClient.php');
        $transport = file_get_contents($root . '/codi-drive/intranet-actual/SifInternalUsocClient.php');
        $lookup = file_get_contents($root . '/codi-drive/intranet-actual/LegacyNoviceValidationLookup.php');
        $api = file_get_contents($root . '/sif/public/api/novice-promotion/manage.php');
        $config = file_get_contents($root . '/sif/config/sif.php');

        if ($endpoint === false || $client === false || $transport === false || $lookup === false || $api === false || $config === false) {
            Assert::fail('Could not read UC-111 internal decision bridge files');
        }

        Assert::stringContainsString('SifAuthenticatedActor::fromUser', $endpoint);
        Assert::stringContainsString('LegacyNoviceValidationLookup', $endpoint);
        Assert::stringContainsString('SifInternalNovicePromotionClient', $endpoint);
        Assert::stringContainsString('currentLegacyStatus === 0', $endpoint);
        Assert::stringContainsString('currentLegacyStatus === $desiredLegacyStatus', $endpoint);
        Assert::stringContainsString('pendent de reconciliació', $endpoint);

        Assert::stringContainsString('SIF_INTERNAL_NOVICE_PROMOTION_URL', $client);
        Assert::stringContainsString('/api/novice-promotion/manage.php', $client);
        Assert::stringContainsString("'action' => 'project_decision'", $client);
        Assert::stringContainsString('SifInternalUsocClient', $client);

        Assert::stringContainsString('X-SIF-Signature', $transport);
        Assert::stringContainsString("hash_hmac('sha256'", $transport);

        Assert::stringContainsString('recent_titulat', $lookup);
        Assert::stringContainsString("i.CURS = 'JASOM'", $lookup);

        Assert::stringContainsString('InternalApiAuthenticator', $api);
        Assert::stringContainsString('novice_promotion_signed_path', $api);
        Assert::stringContainsString('manage_roles', $api);
        Assert::stringContainsString('ConnectionFactory::makeLegacy', $api);
        Assert::stringContainsString('NovicePromotionSecretaryDecisionProjector', $api);
        Assert::stringContainsString("PRODUCT_CODE = 'JASOM'", $api);
        Assert::stringContainsString('count($operations) !== 1', $api);

        Assert::stringContainsString('SIF_INTERNAL_NOVICE_PROMOTION_SIGNED_PATH', $config);
        Assert::stringContainsString('SIF_NOVICE_PROMOTION_MANAGE_ROLES', $config);

        foreach ([
            '$payload[\'actor_id\']',
            '$payload[\'roles\']',
            'SIF_INTERNAL_API_SECRET'
        ] as $browserControlled) {
            if (str_contains($endpoint, $browserControlled)) {
                Assert::fail('UC-111 legacy endpoint must not accept internal identity/signing material from browser');
            }
        }
    }
}
