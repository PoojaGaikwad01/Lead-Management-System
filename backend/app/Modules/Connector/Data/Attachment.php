<?php

namespace App\Modules\Connector\Data;

final readonly class Attachment
{
    public function __construct(
        public string $url,
        public string $type,
        public ?string $name,
    ) {}
}
