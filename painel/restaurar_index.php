<?php
declare(strict_types=1);
require_once __DIR__.'/config.php';
require_auth();
header('Content-Type: text/html; charset=utf-8');

function flash_back($ok=null,$err=null){
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  if ($ok) $_SESSION['flash_ok']=$ok;
  if ($err) $_SESSION['flash_err']=$err;
  header('Location: ./index.php'); exit;
}

$dominio = isset($_POST['dominio']) ? trim((string)$_POST['dominio']) : '';
if ($dominio==='') flash_back(null,'Informe o domínio.');
$dominio = preg_replace('~[^A-Za-z0-9\.\-]~','', $dominio);

$base = $SITES_DIR;
$dir = $base . DIRECTORY_SEPARATOR . $dominio;
$idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
if (!is_dir($dir)) flash_back(null,'Pasta do domínio não existe.');

// Encontra backups index.html.bak-YYYYMMDD-HHMMSS
$backs = [];
$dh = opendir($dir);
if ($dh) {
  while (($f = readdir($dh)) !== false) {
    if (preg_match('~^index\.html\.bak-\d{8}-\d{6}$~', $f)) {
      $backs[] = $dir . DIRECTORY_SEPARATOR . $f;
    }
  }
  closedir($dh);
}
if (!$backs) flash_back(null,'Nenhum backup encontrado para restaurar.');


// Escolhe o primeiro backup cujo hash seja diferente do arquivo atual
$currentHash = is_file($idx) ? md5_file($idx) : null;
$bkToUse = null;
foreach ($backs as $candidate) {
  $h = @md5_file($candidate);
  if (!$currentHash || !$h || $h !== $currentHash) { $bkToUse = $candidate; break; }
}
// Se todos os backups forem iguais (raro), pega o mais antigo
if (!$bkToUse) { $bkToUse = end($backs); reset($backs); }

// Backup do atual e restaura
if (is_file($idx)) {
  @copy($idx, $dir . DIRECTORY_SEPARATOR . 'index.html.bak-restore-'.date('Ymd-His'));
}
if (!@copy($bkToUse, $idx)) {
  flash_back(null,'Falha ao restaurar a index a partir de '.basename($bkToUse));
}

// Cache-busting: acrescenta um comentário invisível com timestamp para forçar ETag diferente
@file_put_contents($idx, (string)@file_get_contents($idx) . "\n<!-- restore ".date('c')." -->");
// Atualiza meta.json com link detectado do HTML restaurado (se achar)
$restored = (string)@file_get_contents($idx);
$linkRest = null;
if ($restored) {
  if (preg_match('~href=[\'\"]\s*(https?://[^\'\"]+)\s*[\'\"][^>]*>(?:\s*<[^>]+>\s*)*(Acesso|Atendimento|Entrar)~i', $restored, $m)) {
    $linkRest = rtrim($m[1], '/');
  } elseif (preg_match('~data-(?:link|url|href)=[\'\"](https?://[^\'\"]+)[\'\"]~i', $restored, $m)) {
    $linkRest = rtrim($m[1], '/');
  }
}
$metaPath = $dir . DIRECTORY_SEPARATOR . 'meta.json';
$meta = is_file($metaPath) ? @json_decode((string)@file_get_contents($metaPath), true) : [];
if (!is_array($meta)) $meta = [];
if ($linkRest) $meta['acesso'] = $linkRest;
$meta['updated_at'] = date('c');
@file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

flash_back('Index anterior restaurada para '.$dominio, null);
