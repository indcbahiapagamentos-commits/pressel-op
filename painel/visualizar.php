<?php
require __DIR__.'/config.php';
require_auth();
$site = (string)($_GET['site'] ?? '');
if (!preg_match('/^[A-Za-z0-9.\-_]+$/', $site)) { http_response_code(400); exit('site inválido'); }
$idx = $SITES_DIR . DIRECTORY_SEPARATOR . $site . DIRECTORY_SEPARATOR . 'index.html';
if (!is_file($idx)) { http_response_code(404); exit('não encontrado'); }
header('Content-Type: text/html; charset=utf-8');
readfile($idx);
