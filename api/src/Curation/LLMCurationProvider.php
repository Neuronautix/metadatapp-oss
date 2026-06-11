<?php

declare(strict_types=1);

namespace App\Curation;

interface LLMCurationProvider
{
    public function curate(string $systemPrompt, array $payload): string;
}
