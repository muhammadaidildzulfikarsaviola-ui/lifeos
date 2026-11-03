<?php
declare(strict_types=1);
namespace LifeOS\Core\Exceptions;

final class ForbiddenOperationException extends AppException
{
    public function __construct(string $message='Operasi tidak diizinkan.') {
        parent::__construct($message,403,'FORBIDDEN');
    }
}
