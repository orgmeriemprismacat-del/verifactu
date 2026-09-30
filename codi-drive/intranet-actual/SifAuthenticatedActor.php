<?php

final class SifAuthenticatedActor
{
    public static function fromUser($user): array
    {
        if (!is_object($user)
            || !method_exists($user, 'getUsuari')
            || !method_exists($user, 'getRols')) {
            throw new RuntimeException('Context d’usuari no vàlid', 401);
        }

        $actor = $user->getUsuari();
        $actorId = is_object($actor) && method_exists($actor, 'get')
            ? trim((string) $actor->get())
            : trim((string) $actor);

        $roles = $user->getRols();
        if ($actorId === '' || !is_array($roles) || $roles === []) {
            throw new RuntimeException('Identitat o rols d’usuari no disponibles', 401);
        }

        return [$actorId, $roles];
    }
}
