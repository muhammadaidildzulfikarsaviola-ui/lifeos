<?php
declare(strict_types=1);
namespace LifeOS\Core\Exceptions;

final class ValidationException extends AppException
{
    public function __construct(array $errors,string $message='Periksa kembali data yang dimasukkan.') {
        parent::__construct($message,422,'VALIDATION_FAILED',$errors);
    }
}
