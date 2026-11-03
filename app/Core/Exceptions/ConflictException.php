<?php
declare(strict_types=1);
namespace LifeOS\Core\Exceptions;

final class ConflictException extends AppException
{
    public function __construct(string $message='Operasi bertentangan dengan kondisi data saat ini.') {
        parent::__construct($message,409,'CONFLICT');
    }
}
