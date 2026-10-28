<?php
require __DIR__.'/includes/bootstrap.php';
require __DIR__.'/includes/mailer.php';
$error='';$notice='';
// Retain non-sensitive values when validation fails; never echo passwords.
function old(string $key): string {
 return ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' && ($_POST['action'] ?? '') === 'add'
  ? h((string)($_POST[$key] ?? '')) : '';
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
 require_csrf();
 try {
  $action=(string)($_POST['action']??'');
  if ($action==='setup' && setting('admin_hash')==='') {
   $password=(string)($_POST['password']??'');
   if(strlen($password)<12) throw new RuntimeException('Administrátorské heslo musí mať aspoň 12 znakov.');
   set_setting('admin_hash',password_hash($password,PASSWORD_DEFAULT));
   session_regenerate_id(true);$_SESSION['role']='admin';flash('Administrátor nastavený.');redirect('/');
  }
  if ($action==='login') {
   $password=(string)($_POST['password']??'');
   $a=setting('admin_hash');$t=setting('teacher_hash');
   if ($a && password_verify($password,$a)) $r='admin';
   elseif ($t && password_verify($password,$t)) $r='teacher';
   else throw new RuntimeException('Nesprávne heslo.');
   session_regenerate_id(true);$_SESSION['role']=$r;redirect('/');
  }
  if ($action==='logout') {$_SESSION=[];session_regenerate_id(true);redirect('/');}
  if (!authenticated()) throw new RuntimeException('Najprv sa prihláste.');
  if ($action==='settings' && admin()) {
   $count=filter_var($_POST['tablet_count']??'',FILTER_VALIDATE_INT);
   if ($count===false || $count<1 || $count>10000) throw new RuntimeException('Neplatný počet tabletov.');
   set_setting('tablet_count',(string)$count);
   set_setting('welcome',mb_substr(trim((string)($_POST['welcome']??'')),0,5000));
   foreach (['admin','teacher'] as $kind) {
    $pass=(string)($_POST[$kind.'_password']??'');
    if ($pass!=='') {
     if(strlen($pass)<12) throw new RuntimeException('Heslo musí mať aspoň 12 znakov.');
     set_setting($kind.'_hash',password_hash($pass,PASSWORD_DEFAULT));
    }
   }
   flash('Nastavenia uložené.');redirect('/');
  }
  if ($action==='add') {
   $teacher=trim((string)($_POST['teacher']??''));
   $password=(string)($_POST['removal_password']??'');
   $count=filter_var($_POST['tablets']??'',FILTER_VALIDATE_INT);
   if (mb_strlen($teacher)<3||mb_strlen($teacher)>200||strlen($password)<4||$count===false||$count<1) throw new RuntimeException('Vyplňte meno, počet tabletov a heslo na zrušenie.');
   [$start,$end]=valid_interval((string)($_POST['start']??''),(string)($_POST['end']??''));
   $pdo=db();$pdo->beginTransaction();
   try {
    // Lock the single settings row to serialize all reservation writers.
    $lock=$pdo->query("SELECT value FROM settings WHERE name='tablet_count' FOR UPDATE")->fetchColumn();
    $capacity=(int)$lock;
    if ($count>$capacity) throw new RuntimeException('Požadovaný počet prekračuje kapacitu.');
    $q=$pdo->prepare('SELECT tablets,starts_at,ends_at FROM reservations WHERE starts_at < ? AND ends_at > ?');
    $q->execute([$end,$start]);$events=[[$start,$count],[$end,-$count]];
    foreach($q->fetchAll() as $row) {
     $a=max($start,$row['starts_at']);$b=min($end,$row['ends_at']);
     $events[]=[$a,(int)$row['tablets']];$events[]=[$b,-(int)$row['tablets']];
    }
    usort($events,fn($a,$b)=>strcmp($a[0],$b[0]) ?: ($a[1]<=>$b[1]));
    $used=0;foreach($events as $event){$used+=$event[1];if($used>$capacity)throw new RuntimeException('V zadanom intervale nie je dostatok voľných tabletov.');}
    $q=$pdo->prepare('INSERT INTO reservations(teacher,tablets,starts_at,ends_at,removal_hash,created_ip) VALUES(?,?,?,?,?,?)');
    $q->execute([$teacher,$count,$start,$end,password_hash($password,PASSWORD_DEFAULT),$_SERVER['REMOTE_ADDR']??'']);
    $id=(int)$pdo->lastInsertId();$pdo->commit();
   } catch(Throwable $e){$pdo->rollBack();throw $e;}
   $r=['id'=>$id,'teacher'=>$teacher,'tablets'=>$count,'starts_at'=>$start,'ends_at'=>$end];
   if(!admin()) {try {notify_reservation($r,'Nová rezervácia');}catch(Throwable $e){error_log('Mail failed: '.$e->getMessage());$notice='Rezervácia bola uložená, ale email sa nepodarilo odoslať.';}}
   flash($notice?:'Rezervácia uložená.');redirect('/');
  }
  if ($action==='remove') {
   $id=filter_var($_POST['id']??'',FILTER_VALIDATE_INT);
   if (!$id) throw new RuntimeException('Neplatná rezervácia.');
   $pdo=db();$pdo->beginTransaction();
   try {
    $pdo->query("SELECT value FROM settings WHERE name='tablet_count' FOR UPDATE");
    $q=$pdo->prepare('SELECT * FROM reservations WHERE id=? FOR UPDATE');$q->execute([$id]);$r=$q->fetch();
    if (!$r) throw new RuntimeException('Rezervácia neexistuje.');
    if(!admin()&&!password_verify((string)($_POST['removal_password']??''),$r['removal_hash']))throw new RuntimeException('Nesprávne heslo na zrušenie.');
    $q=$pdo->prepare('DELETE FROM reservations WHERE id=?');$q->execute([$id]);$pdo->commit();
   }catch(Throwable $e){$pdo->rollBack();throw $e;}
   if(!admin()){try{notify_reservation($r,'Zrušená rezervácia');}catch(Throwable $e){error_log('Mail failed: '.$e->getMessage());}}
   flash('Rezervácia zrušená.');redirect('/');
  }
 }catch(Throwable $e){$error=$e->getMessage();}
}
$setup=setting('admin_hash')==='';$view=can_view();
$past=admin() && isset($_GET['past']);
if($past && isset($_GET['csv'])) {
 header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="tablety-historia.csv"');
 $out=fopen('php://output','w');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['ID','Učiteľ','Tablety','Od','Do'],';');
 foreach(reservations(true) as $r) fputcsv($out,[$r['id'],$r['teacher'],$r['tablets'],$r['starts_at'],$r['ends_at']],';');
 exit;
}
$list=$view?reservations($past):[];
$flash=$_SESSION['flash']??'';unset($_SESSION['flash']);
?><!doctype html><html lang="sk"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Školské tablety – rezervácie</title><link rel="stylesheet" href="/assets/style.css"></head><body><main>
<header><h1>📱 Školské tablety</h1><p>Rezervačný systém</p></header>
<?php if($error):?><div class="alert"><?=h($error)?></div><?php endif;?>
<?php if($flash):?><div class="success"><?=h($flash)?></div><?php endif;?>
<?php if($setup):?>
<section><h2>Prvé nastavenie administrátora</h2><p>Po nasadení ihneď nastavte administrátorské heslo.</p><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="setup"><label>Nové administrátorské heslo <input type="password" name="password" minlength="12" required></label><button>Nastaviť</button></form></section>
<?php elseif(!$view):?>
<section><h2>Prihlásenie</h2><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="login"><label>Heslo <input type="password" name="password" required autofocus></label><button>Prihlásiť sa</button></form></section>
<?php else:?>
<nav><?php if(authenticated()):?><span><?=admin()?'Administrátor':'Učiteľ'?></span><form method="post"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="logout"><button class="secondary">Odhlásiť sa</button></form><?php else:?><a class="button" href="/">Prihlásiť sa</a><?php endif;?></nav>
<section><h2>Informácie</h2><p class="welcome"><?=nl2br(h(setting('welcome')))?></p><p><strong>Počet tabletov:</strong> <?=h(setting('tablet_count'))?></p></section>
<?php if(authenticated()):?>
<section><h2>Nová rezervácia</h2><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="add">
<label>Meno a priezvisko <input name="teacher" maxlength="200" value="<?=old('teacher')?>" required></label>
<label>Počet tabletov <input type="number" name="tablets" min="1" max="<?=h(setting('tablet_count'))?>" value="<?=old('tablets')?>" required></label>
<label>Od <input type="datetime-local" name="start" value="<?=old('start')?>" required></label><label>Do <input type="datetime-local" name="end" value="<?=old('end')?>" required></label>
<label>Heslo na zrušenie <input type="password" name="removal_password" minlength="4" required></label><div class="form-action"><button>Pridať rezerváciu</button></div></form>
<p class="hint">Interval môže pokračovať aj cez noc alebo viac dní. Pre opakované termíny vytvorte samostatné rezervácie.</p></section>
<?php endif;?>
<section><h2><?= $past?'Minulé rezervácie':'Kalendár rezervácií' ?></h2>
<?php if(admin()):?><p><a href="<?= $past?'/':'/?past=1'?>"><?= $past?'Aktuálne rezervácie':'Zobraziť minulé rezervácie'?></a><?php if($past):?> · <a href="/?past=1&amp;csv=1">Export CSV</a><?php endif;?></p><?php endif;?>
<div id="calendar" data-capacity="<?=h(setting('tablet_count'))?>" data-past="<?= $past?'1':'0'?>">
<?php foreach($list as $r):?><article class="reservation" id="reservation-<?= (int)$r['id']?>" data-start="<?=h($r['starts_at'])?>" data-end="<?=h($r['ends_at'])?>" data-count="<?= (int)$r['tablets']?>">
<div><strong><?=h($r['teacher'])?></strong> · <?= (int)$r['tablets']?> ks<br><small><?=h($r['starts_at'])?> – <?=h($r['ends_at'])?></small></div>
<div class="actions"><a target="_blank" rel="noopener" href="<?=h(calendar_link($r))?>">📅 Google Kalendár</a>
<?php if(authenticated()&&!$past):?><form method="post" onsubmit="return confirm('Naozaj zrušiť rezerváciu?')"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="remove"><input type="hidden" name="id" value="<?=(int)$r['id']?>"><?php if(!admin()):?><input type="password" name="removal_password" placeholder="Heslo na zrušenie" required><?php endif;?><button class="danger">Zrušiť</button></form><?php endif;?></div></article><?php endforeach;?>
</div><div id="days"></div></section>
<?php if(admin()):?><section><h2>Nastavenia</h2><form method="post" class="grid"><input type="hidden" name="csrf" value="<?=h(csrf())?>"><input type="hidden" name="action" value="settings">
<label>Počet tabletov<input type="number" min="1" name="tablet_count" value="<?=h(setting('tablet_count'))?>" required></label>
<label>Nové heslo učiteľov (nechať prázdne = bez zmeny)<input type="password" minlength="12" name="teacher_password"></label>
<label>Nové heslo administrátora<input type="password" minlength="12" name="admin_password"></label>
<label class="wide">Úvodný text<textarea name="welcome" rows="5"><?=h(setting('welcome'))?></textarea></label>
<div class="form-action"><button>Uložiť nastavenia</button></div></form></section><?php endif;?>
<?php endif;?>
<footer>Školské tablety · <?=date('Y')?></footer></main><script src="/assets/app.js" defer></script></body></html>
