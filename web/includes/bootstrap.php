<?php
declare(strict_types=1);
$config=require dirname(__DIR__,2).'/config.php';
date_default_timezone_set($config['timezone'] ?? 'Europe/Bratislava');
ini_set('session.use_strict_mode','1');
session_set_cookie_params(['httponly'=>true,'secure'=>true,'samesite'=>'Lax','path'=>'/']);
session_start();
function db(): PDO {
 global $config;
 static $db=null;
 if (!$db) {
  $db=new PDO('mysql:host='.$config['db_host'].';dbname='.$config['db_name'].';charset=utf8mb4',$config['db_user'],$config['db_password'],[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
 }
 return $db;
}
function setting(string $key): string {
 $s=db()->prepare('SELECT value FROM settings WHERE name=?');$s->execute([$key]);return (string)($s->fetchColumn() ?: '');
}
function set_setting(string $key,string $value): void {
 $s=db()->prepare('INSERT INTO settings(name,value) VALUES (?,?) ON DUPLICATE KEY UPDATE value=VALUES(value)');$s->execute([$key,$value]);
}
function role(): string {return $_SESSION['role'] ?? '';}
function admin(): bool {return role()==='admin';}
function authenticated(): bool {return role()!=='';}
function can_view(): bool {
 if (authenticated()) return true;
 $token=(string)($_GET['view'] ?? $_POST['view'] ?? '');
 $hash=setting('view_token_hash');
 return strlen($token)===64 && $hash!=='' && hash_equals($hash,hash('sha256',$token));
}
function csrf(): string {return $_SESSION['csrf'] ??= bin2hex(random_bytes(32));}
function require_csrf(): void {if (!hash_equals(csrf(),(string)($_POST['csrf']??''))) {http_response_code(403);exit('Neplatný bezpečnostný token.');}}
function redirect(string $url='/'): never {header('Location: '.$url, true,303);exit;}
function h(mixed $v): string {return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
function flash(string $message): void {$_SESSION['flash']=$message;}
function valid_interval(string $start,string $end): array {
 $tz=new DateTimeZone('Europe/Bratislava');
 $a=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$start,$tz);
 $b=DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$end,$tz);
 if (!$a||!$b||$a->format('Y-m-d\TH:i')!==$start||$b->format('Y-m-d\TH:i')!==$end) throw new RuntimeException('Neplatný dátum alebo čas.');
 $now=new DateTimeImmutable('now',$tz);
 if ($a<=$now || $a>$now->modify('+3 months') || $b<=$a) throw new RuntimeException('Termín musí začínať v budúcnosti (najviac 3 mesiace vopred) a končiť po začiatku.');
 return [$a->format('Y-m-d H:i:s'),$b->format('Y-m-d H:i:s')];
}
function reservations(bool $past=false): array {
 $q=db()->prepare('SELECT id,teacher,tablets,starts_at,ends_at FROM reservations WHERE '.($past?'ends_at < NOW()':'ends_at >= NOW()').' ORDER BY starts_at,id');
 $q->execute();return $q->fetchAll();
}
function calendar_link(array $r): string {
 global $config;
 $start=(new DateTimeImmutable($r['starts_at']))->format('Ymd\THis');
 $end=(new DateTimeImmutable($r['ends_at']))->format('Ymd\THis');
 $p=['action'=>'TEMPLATE','text'=>'Školské tablety – '.$r['teacher'].' ('.$r['tablets'].' ks)','dates'=>$start.'/'.$end,'ctz'=>'Europe/Bratislava','details'=>'Rezervácia školských tabletov: '.$config['base_url']];
 return 'https://calendar.google.com/calendar/render?'.http_build_query($p,'','&',PHP_QUERY_RFC3986);
}
