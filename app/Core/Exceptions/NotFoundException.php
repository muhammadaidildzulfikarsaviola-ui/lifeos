<?php
declare(strict_types=1);
namespace LifeOS\Core\Exceptions;

final class NotFoundException extends AppException
{
    public function __construct(string $message='Data yang diminta tidak ditemukan.') {
        parent::__construct($message,404,'NOT_FOUND');
    }
}
