<?php
// PHPMailer is installed with composer in the repository root; no credentials in Git.
function notify_reservation(array $r,string $action): void {
 global $config;
 $view=setting('view_token_plain');
 $link=rtrim($config['base_url'],'/').'/?view='.rawurlencode($view).'#reservation-'.(int)$r['id'];
 $body="Školské tablety – $action\n\nUčiteľ: {$r['teacher']}\nPočet: {$r['tablets']}\nOd: {$r['starts_at']}\nDo: {$r['ends_at']}\n\nPrehľad: $link\nGoogle Kalendár: ".calendar_link($r)."\n";
 $autoload=dirname(__DIR__,2).'/vendor/autoload.php';
 if (!is_file($autoload)) throw new RuntimeException('Chýba PHPMailer: spustite composer install.');
 require_once $autoload;
 $m=new PHPMailer\PHPMailer\PHPMailer(true);
 $m->isSMTP();$m->Host=$config['smtp_host'];$m->SMTPAuth=true;
 $m->Username=$config['smtp_user'];$m->Password=$config['smtp_password'];
 $m->SMTPSecure=PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;$m->Port=(int)$config['smtp_port'];
 $m->CharSet='UTF-8';$m->setFrom($config['mail_from'],'Školské tablety');
 $m->addAddress($config['admin_email']);$m->Subject='Tablety: '.$action.' – '.$r['teacher'];
 $m->Body=$body;$m->send();
}
