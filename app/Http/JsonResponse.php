<?php

declare(strict_types=1);

namespace App\Http;

final class JsonResponse
{
    private function __construct(
        private readonly array $payload,
        private readonly int $code
    ) {}

    public static function success(mixed $data = null, ?string $message = null, int $code = 200): self
    {
        return new self(['ok' => true, 'mensaje' => $message, 'data' => $data], $code);
    }

    public static function error(?string $message = null, mixed $data = null, int $code = 400): self
    {
        return new self(['ok' => false, 'mensaje' => $message, 'data' => $data], $code);
    }

    public static function raw(array $payload, int $code = 200): self
    {
        return new self($payload, $code);
    }

    public function send(): never
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }

        header('Content-Type: application/json');
        http_response_code($this->code);
        echo json_encode($this->payload);
        exit;
    }
}