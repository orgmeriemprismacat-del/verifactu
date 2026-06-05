<?php

namespace Prisma\Sif\Http;

final class JsonResponse
{
    public static function fromInput(): ?array
    {
        $payload = json_decode(file_get_contents('php://input') ?: '', true);

        if (!is_array($payload)) {
            self::send(['ok' => false, 'error' => 'Invalid JSON'], 400);
            return null;
        }

        return $payload;
    }

    public static function fromThrowable(\Throwable $exception): void
    {
        $code = $exception->getCode();
        $status = is_int($code) && $code >= 400 && $code <= 599 ? $code : 500;

        self::send([
            'ok' => false,
            'error' => $exception->getMessage(),
        ], $status);
    }

    public static function send(array $payload, int $status = 200): void
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
