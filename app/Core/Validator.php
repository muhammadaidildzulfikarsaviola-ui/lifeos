<?php
declare(strict_types=1);
namespace LifeOS\Core;

final class Validator
{
    private array $errors=[];
    public function __construct(private readonly array $data) {}
    public function required(string $field,string $label): self { $v=$this->data[$field]??null; if ($v===null || (is_string($v)&&trim($v)==='') || $v===[]) $this->errors[$field][]=$label.' wajib diisi.'; return $this; }
    public function string(string $field,string $label,int $max,bool $nullable=true): self { $v=$this->data[$field]??null; if ($v===null&&$nullable)return $this; if(!is_string($v))$this->errors[$field][]=$label.' harus berupa teks.'; elseif(mb_strlen($v,'UTF-8')>$max)$this->errors[$field][]=$label.' maksimal '.$max.' karakter.'; return $this; }
    public function integer(string $field,string $label,?int $min=null,?int $max=null,bool $nullable=true): self { $v=$this->data[$field]??null; if($v===null&&$nullable)return $this; $n=filter_var($v,FILTER_VALIDATE_INT); if($n===false||($min!==null&&$n<$min)||($max!==null&&$n>$max))$this->errors[$field][]=$label.' tidak valid.'; return $this; }
    public function oneOf(string $field,string $label,array $allowed,bool $nullable=true): self { $v=$this->data[$field]??null; if($v===null&&$nullable)return $this; if(!in_array($v,$allowed,true))$this->errors[$field][]=$label.' tidak valid.'; return $this; }
    public function date(string $field,string $label,bool $nullable=true): self { $v=$this->data[$field]??null; if($v===null&&$nullable)return $this; $d=is_string($v)?\DateTimeImmutable::createFromFormat('!Y-m-d',$v):false; if(!$d||$d->format('Y-m-d')!==$v)$this->errors[$field][]=$label.' harus berformat YYYY-MM-DD.'; return $this; }
    public function valid(): bool { return $this->errors===[]; }
    public function errors(): array { return $this->errors; }
}
