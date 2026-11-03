<?php
declare(strict_types=1);
namespace LifeOS\Core\Exceptions;

final class StorageException extends AppException
{
    public function __construct(string $message='Operasi penyimpanan gagal.',?\Throwable $previous=null) {
        parent::__construct($message,500,'STORAGE_ERROR');
        if($previous!==null)$this->previous=$previous;
    }
}
