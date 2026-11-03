<?php
declare(strict_types=1);
namespace LifeOS\Core\Exceptions;

use RuntimeException;

class AppException extends RuntimeException
{
    public function __construct(
        string $message,
        private readonly int $httpStatus=400,
        private readonly string $errorCode='APPLICATION_ERROR',
        private readonly ?array $fieldErrors=null
    ) { parent::__construct($message); }

    public function httpStatus(): int { return $this->httpStatus; }
    public function errorCode(): string { return $this->errorCode; }
    public function fieldErrors(): ?array { return $this->fieldErrors; }
}
