<?php

$_GET['json'] = '1';

// worktime.php starts with a shebang line, which the web SAPI would emit as body
// output before any header can be sent - buffer it away.
ob_start();

require_once __DIR__.'/worktime.php';
