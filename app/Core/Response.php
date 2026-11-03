<?php
declare(strict_types=1);
namespace LifeOS\Core;

final class Response
{
    public function __construct(private string $body='',private int $status=200,private array $headers=[]) {}
    public static function html(string $body,int $status=200): self { return new self($body,$status,['Content-Type'=>'text/html; charset=utf-8']); }
    public static function json(array $data,int $status=200): self { return new self(json_encode($data,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE),$status,['Content-Type'=>'application/json; charset=utf-8']); }
    public static function success(mixed $data=null,string $message='OK',int $status=200,?array $meta=null): self { return self::json(['success'=>true,'message'=>$message,'data'=>$data,'meta'=>$meta],$status); }
    public static function error(string $message,int $status,?array $errors=null): self { return self::json(['success'=>false,'message'=>$message,'data'=>null,'errors'=>$errors,'meta'=>null],$status); }
    public static function redirect(string $url,int $status=302): self { return new self('',$status,['Location'=>$url]); }
    public function statusCode(): int { return $this->status; }
    public function body(): string { return $this->body; }
    public function headers(): array { return $this->headers; }
    public function send(): never {
        http_response_code($this->status);
        foreach($this->headers as $name=>$value)header($name.': '.$value);
        echo $this->body;
        exit;
    }
}
