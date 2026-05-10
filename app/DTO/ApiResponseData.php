<?php

namespace App\DTO;

class ApiResponseData
{
    public function __construct(
        public int $status,
        public array $headers,
        public mixed $payload,
        public bool $fromCache = false,
    ) {}

    public function json(): array
    {
        return is_array($this->payload) ? $this->payload : [];
    }
}
