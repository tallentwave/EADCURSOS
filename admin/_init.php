<?php
require dirname(__DIR__) . '/app/bootstrap.php';
$admin = require_admin();
function back(string $to): never { redirect('admin/' . $to); }
