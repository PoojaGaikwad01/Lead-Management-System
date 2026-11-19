<?php

namespace App\Modules\Connector\Data;

final readonly class SentMessage
{
    public function __construct(
        public string $providerMessageId,
        public string $status = 'sent',
    ) {}
}
