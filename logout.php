<?php
require_once __DIR__ . '/lib/auth.php';
do_logout();
redirect('login.php');
