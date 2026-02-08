<?php
ini_set('session.cookie_path', '/');
session_start();
header("Content-Type: application/json; charset=utf-8");
echo json_encode([
  "session_id" => session_id(),
  "session" => $_SESSION
]);

