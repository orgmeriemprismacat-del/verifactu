<?php

namespace Prisma\Sif\Contract;

interface AeatTransport
{
    /**
     * @return array{status:string,response:array,request_xml?:string}
     */
    public function send(array $fiscalPayload): array;
}
