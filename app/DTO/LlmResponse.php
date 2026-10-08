<?php

namespace App\DTO;

class LlmResponse
{
    public function __construct(
        public readonly string $content,
        public readonly string $model,
        public readonly ?int $durationMs = null,
        public readonly ?int $promptTokens = null,
        public readonly ?int $completionTokens = null,
        public readonly ?string $doneReason = null,
    ) {}
}
