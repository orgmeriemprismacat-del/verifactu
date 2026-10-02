<?php

namespace Prisma\Sif\Contract;

interface DocumentStorageWriterInterface
{
    /**
     * @return array{storage_key:string,hash:string,size:int,reused:bool}
     */
    public function writeVerified(string $storageKey, string $contents): array;
}
