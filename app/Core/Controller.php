<?php
declare(strict_types=1);
namespace LifeOS\Core;

abstract class Controller
{
    protected function view(string $name,array $data=[]): Response {
        return Response::html(View::render($name,$data));
    }

    protected function json(mixed $data=null,string $message='OK',int $status=200,?array $meta=null): Response {
        return Response::success($data,$message,$status,$meta);
    }

    protected function validationError(Validator $validator): Response {
        return Response::error('Validasi gagal.',422,$validator->errors());
    }
}
