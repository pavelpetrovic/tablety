<?php
if(PHP_SAPI!=='cli')exit("CLI only\n");
require dirname(__DIR__).'/web/includes/bootstrap.php';
require dirname(__DIR__).'/web/includes/mailer.php';
$tomorrow=(new DateTimeImmutable('tomorrow'))->format('Y-m-d');
$q=db()->prepare('SELECT * FROM reservations WHERE DATE(starts_at)=? AND reminder_sent_at IS NULL ORDER BY starts_at');
$q->execute([$tomorrow]);
foreach($q->fetchAll() as $r){
 try{
  notify_reservation($r,'Pripomienka na zajtra');
  $u=db()->prepare('UPDATE reservations SET reminder_sent_at=NOW() WHERE id=? AND reminder_sent_at IS NULL');
  $u->execute([$r['id']]);
  echo "Reminder sent for #{$r['id']}\n";
 }catch(Throwable $e){error_log('Reminder failed #'.$r['id'].': '.$e->getMessage());}
}
