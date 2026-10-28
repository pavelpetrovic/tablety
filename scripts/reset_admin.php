<?php
if(PHP_SAPI!=='cli')exit("CLI only\n");
require dirname(__DIR__).'/web/includes/bootstrap.php';
set_setting('admin_hash','');
echo "Administrator password cleared. Set a new password immediately in the web UI.\n";
