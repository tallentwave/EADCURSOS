<?php
require __DIR__ . '/app/bootstrap.php';
redirect(current_user() ? 'home.php' : 'login.php');
