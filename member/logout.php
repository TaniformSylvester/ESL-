<?php
require_once __DIR__ . '/../includes/init.php';
require_once __DIR__ . '/../includes/auth.php';

logout_user();
flash_set('success', 'You have been logged out.');
redirect('login.php');
