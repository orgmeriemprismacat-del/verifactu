<?php

namespace Prisma\Sif\Service;

use Prisma\Sif\Exception\SifException;

final class RedsysCallbackDispatcher implements RedsysJobProcessor
{
    private array $handlers = [];

    public function __construct(iterable $handlers)
    {
        foreach ($handlers as $handler) {
            if (!$handler instanceof RedsysIntentHandler) {
                throw new \InvalidArgumentException('Invalid Redsys intent handler');
            }

            $type = $handler->sourceType();
            if (isset($this->handlers[$type])) {
                throw new \InvalidArgumentException('Duplicate Redsys intent handler');
            }

            $this->handlers[$type] = $handler;
        }
    }

    public function process(\PDO $sifDb, array $job): array
    {
        $sourceType = strtoupper(trim((string) ($job['SOURCE_TYPE'] ?? '')));
        $handler = $this->handlers[$sourceType] ?? null;
        if ($handler === null) {
            throw SifException::validation('Unsupported Redsys source type');
        }

        $snapshot = json_decode((string) ($job['SNAPSHOT_JSON'] ?? ''), true);
        if (!is_array($snapshot)) {
            throw SifException::validation('Invalid Redsys intent snapshot');
        }

        return $handler->issueFromIntentSnapshot(
            $sifDb,
            (string) ($job['DS_ORDER'] ?? ''),
            $snapshot
        );
    }
}
