<?php

namespace App\Exceptions;

class ExamException extends ApiException
{
    public static function notConfigured(): self
    {
        return new self('لا يوجد امتحان لهذا الكورس بعد.', 404);
    }

    public static function notPublished(): self
    {
        return new self('امتحان هذا الكورس غير منشور بعد.', 403);
    }

    public static function lessonsNotCompleted(): self
    {
        return new self('أجب على جميع أسئلة دروس الكورس لفتح الامتحان.', 403);
    }

    public static function alreadyAttempted(): self
    {
        return new self('لقد قدّمت هذا الامتحان مسبقاً، ولا يُسمح بأكثر من محاولة.', 409);
    }

    public static function alreadyPassed(): self
    {
        return new self('لقد اجتزت هذا الامتحان بنجاح. لا يمكنك إعادة التقديم.', 409);
    }

    public static function notInProgress(): self
    {
        return new self('لا توجد محاولة امتحان قيد التنفيذ. ابدأ الامتحان أولاً.', 409);
    }

    public static function timeExpired(): self
    {
        return new self('انتهى وقت الامتحان، ولا يمكن حفظ إجابات جديدة.', 422);
    }

    public static function requirementsNotMet(string $reason): self
    {
        return new self($reason, 422);
    }
}
