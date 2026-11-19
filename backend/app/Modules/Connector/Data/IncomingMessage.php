<?php

namespace App\Modules\Connector\Data;

use Carbon\CarbonImmutable;

final readonly class IncomingMessage
{
    public function __construct(
        public string $providerMessageId,
        public string $direction,
        public string $phone,
        public ?string $name,
        public string $type,
        public ?string $body,
        public ?Attachment $attachment,
        public CarbonImmutable $sentAt,
        public ?string $conversationId,
        public array $raw,
    ) {}
}
