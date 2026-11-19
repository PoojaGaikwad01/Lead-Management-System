<?php

namespace App\Modules\Connector;

use App\Modules\Connector\Providers\ChatwootProvider;
use App\Modules\Connector\Providers\MockProvider;
use App\Modules\Connector\Providers\PeriskopeProvider;
use InvalidArgumentException;

class WhatsAppManager
{
    private const PROVIDERS = [
        'mock' => MockProvider::class,
        'chatwoot' => ChatwootProvider::class,
        'periskope' => PeriskopeProvider::class,
    ];

    public function activeName(): string
    {
        return (string) config('whatsapp.provider');
    }

    public function driver(?string $name = null): WhatsAppProvider
    {
        $name ??= $this->activeName();

        return app(self::PROVIDERS[$name] ?? throw new InvalidArgumentException("Unknown WhatsApp provider [{$name}]."));
    }
}
