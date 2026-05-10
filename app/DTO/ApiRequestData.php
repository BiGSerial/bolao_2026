<?php

namespace App\DTO;

class ApiRequestData
{
    public function __construct(
        public string $method,
        public string $endpoint,
        public array $query = [],
        public array $headers = [],
        public array $body = [],
    ) {}

    public static function get(string $endpoint, array $query = [], array $headers = []): self
    {
        return new self('GET', $endpoint, $query, $headers);
    }
}
