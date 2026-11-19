<?php

namespace App\Modules\Connector\Data;

final readonly class StatusUpdate
{
    public function __construct(
        public string $providerMessageId,
        public string $status,
    ) {}
}
