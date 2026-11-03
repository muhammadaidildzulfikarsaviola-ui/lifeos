<?php
declare(strict_types=1);
namespace LifeOS\Core;

final class Request
{
    private ?array $decodedJson = null;
    private bool $jsonParsed = false;
    private bool $jsonIsValid = true;

    public function __construct(
        private array $query = [],
        private array $form = [],
        private array $files = [],
        private array $server = [],
        private ?string $rawBody = null
    ) {}

    public static function capture(): self {
        $body=file_get_contents('php://input');
        return new self($_GET,$_POST,$_FILES,$_SERVER,is_string($body)?$body:'');
    }

    public function method(): string {
        $method=strtoupper((string)($this->server['REQUEST_METHOD']??'GET'));
        $override=strtoupper((string)($this->form['_method']??''));
        return $method==='POST'&&in_array($override,['PUT','PATCH','DELETE'],true)?$override:$method;
    }

    public function path(): string {
        $path=parse_url((string)($this->server['REQUEST_URI']??'/'),PHP_URL_PATH);
        $path='/'.trim(is_string($path)?$path:'/','/');
        return $path==='/'?'/':rtrim($path,'/');
    }

    public function isJson(): bool { return str_contains(strtolower((string)($this->server['CONTENT_TYPE']??'')),'application/json'); }
    public function expectsJson(): bool { return $this->isJson()||str_contains((string)($this->server['HTTP_ACCEPT']??''),'application/json')||str_starts_with($this->path(),'/api/'); }

    public function header(string $name): ?string {
        $key=$name==='Content-Type'?'CONTENT_TYPE':'HTTP_'.strtoupper(str_replace('-','_',$name));
        $value=$this->server[$key]??null;
        return is_string($value)?$value:null;
    }

    public function query(?string $key=null,mixed $default=null): mixed { return $key===null?$this->query:($this->query[$key]??$default); }

    public function all(): array {
        if(!$this->isJson())$data=$this->form;
        else $data=$this->jsonBody()??[];
        unset($data['_method']);
        return $data;
    }

    public function input(?string $key=null,mixed $default=null): mixed { $data=$this->all();return $key===null?$data:($data[$key]??$default); }
    public function file(string $key): ?array { return isset($this->files[$key])&&is_array($this->files[$key])?$this->files[$key]:null; }
    public function ip(): string { return (string)($this->server['REMOTE_ADDR']??'127.0.0.1'); }

    public function hasValidJsonBody(): bool {
        if(!$this->isJson())return true;
        $this->jsonBody();
        return $this->jsonIsValid;
    }

    public function jsonBody(): ?array {
        if($this->jsonParsed)return $this->decodedJson;
        $this->jsonParsed=true;
        $raw=$this->rawBody;
        if($raw===null){$body=file_get_contents('php://input');$raw=is_string($body)?$body:'';}
        if(trim($raw)===''){$this->decodedJson=[];return $this->decodedJson;}
        try{$decoded=json_decode($raw,true,512,JSON_THROW_ON_ERROR);}
        catch(\JsonException){$this->jsonIsValid=false;return null;}
        if(!is_array($decoded)){$this->jsonIsValid=false;return null;}
        $this->decodedJson=$decoded;
        return $this->decodedJson;
    }
}
