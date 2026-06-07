<?php

namespace App\Exceptions;

use Exception;

/**
 * استثناء أعمال عام يُحوَّل مركزياً إلى استجابة JSON موحّدة في bootstrap/app.php.
 * استخدم المُنشئات الثابتة لإنتاج الأكواد القياسية بدل تكرار try/catch.
 */
class ApiException extends Exception
{
    public function __construct(string $message, protected int $status = 400)
    {
        parent::__construct($message);
    }

    public function getStatus(): int
    {
        return $this->status;
    }

    public static function badRequest(string $message): static
    {
        return new static($message, 400);
    }

    public static function forbidden(string $message): static
    {
        return new static($message, 403);
    }

    public static function notFound(string $message): static
    {
        return new static($message, 404);
    }

    public static function conflict(string $message): static
    {
        return new static($message, 409);
    }

    public static function unprocessable(string $message): static
    {
        return new static($message, 422);
    }
}
