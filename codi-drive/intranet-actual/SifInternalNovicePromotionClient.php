<?php

require_once __DIR__ . '/SifInternalUsocClient.php';

final class SifInternalNovicePromotionClient
{
    private SifInternalUsocClient $client;

    public function __construct()
    {
        $url = trim((string) (getenv('SIF_INTERNAL_NOVICE_PROMOTION_URL') ?: ''));
        $signedPath = trim((string) (
            getenv('SIF_INTERNAL_NOVICE_PROMOTION_SIGNED_PATH')
                ?: '/api/novice-promotion/manage.php'
        ));

        $this->client = new SifInternalUsocClient($url, $signedPath);
    }

    public function projectDecision(
        string $actorId,
        array $roles,
        string $requestId,
        int $idInsc
    ): array {
        return $this->client->request($actorId, $roles, [
            'action' => 'project_decision',
            'request_id' => trim($requestId),
            'id_insc' => $idInsc,
        ]);
    }
}
