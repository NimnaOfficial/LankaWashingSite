<?php
ini_set('session.cookie_path', '/');
session_start();
session_unset();
session_destroy();
header("Location: ../html/supplier-login.html");
exit;