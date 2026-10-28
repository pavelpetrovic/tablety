<?php
if(PHP_SAPI!=='cli')exit("CLI only\n");
require dirname(__DIR__).'/web/includes/bootstrap.php';
// One transaction keeps the stored token and its verifier synchronized.
$token=bin2hex(random_bytes(32));
db()->beginTransaction();
try{
 set_setting('view_token_hash',hash('sha256',$token));
 set_setting('view_token_plain',$token);
 db()->commit();
}catch(Throwable $e){db()->rollBack();throw $e;}
echo "Read-only email link rotated; previous links are invalid.\n";
