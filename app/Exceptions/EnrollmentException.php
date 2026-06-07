<?php

namespace App\Exceptions;

class EnrollmentException extends ApiException
{
    public static function alreadyEnrolled(): self
    {
        return new self('أنت مسجَّل بالفعل في هذا الكورس.', 409);
    }

    public static function notEnrolled(): self
    {
        return new self('يجب التسجيل في الكورس أولاً.', 403);
    }
}
