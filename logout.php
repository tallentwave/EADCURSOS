<?php
require __DIR__ . '/app/bootstrap.php';
if ($_SERVER['REQUEST_METHOD'] === 'POST') { csrf_check(); logout_user(); }
redirect('login.php');
