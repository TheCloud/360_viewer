<?php
session_start();
require_once __DIR__ . '/auth.php';

logout();

header("Location: login.php");
exit;
