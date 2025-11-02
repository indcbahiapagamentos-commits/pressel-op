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

$acesso  = isset($_POST['acesso'])  ? trim((string)$_POST['acesso'])  : '';
if ($acesso==='') flash_back(null,'Informe o novo link.');

$acesso = trim($acesso, " \t\n\r\0\x0B\"' ");
if (!preg_match('~^https?://~i',$acesso)) $acesso = 'https://'.$acesso;
$acesso = rtrim($acesso,'/');
$safe = htmlspecialchars($acesso, ENT_QUOTES, 'UTF-8');

$base = $SITES_DIR;
if (!$base || !is_dir($base)) flash_back(null, 'Pasta sites/ não encontrada.');

$ok = []; $skip = []; $fail = [];
$dh = opendir($base);
if ($dh) {
  while (($f = readdir($dh)) !== false) {
    if ($f === '.' || $f === '..' || $f === '.keep') continue;
    $dir = $base . DIRECTORY_SEPARATOR . $f;
    if (!is_dir($dir)) continue;
    $idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
    $metaPath = $dir . DIRECTORY_SEPARATOR . 'meta.json';
    if (!is_file($idx) || !is_file($metaPath)) { $skip[]=$f.' (sem index/meta)'; continue; }
    $meta = @json_decode((string)@file_get_contents($metaPath), true);
    if (!is_array($meta) || empty($meta['acesso'])) { $skip[]=$f.' (sem link no meta)'; continue; }
    $old = (string)$meta['acesso'];

    // Lê HTML
    $html = (string)@file_get_contents($idx);
    if ($html==='') { $fail[]=$f.' (não leu index)'; continue; }

    // Variantes do link antigo
    $variants = [];
    $old = trim($old, " \t\n\r\0\x0B\"' "); $old = rtrim($old,'/');
    $variants[] = $old; $variants[] = $old.'/';
    $variants[] = htmlspecialchars($old, ENT_QUOTES, 'UTF-8');
    $variants[] = htmlspecialchars($old.'/', ENT_QUOTES, 'UTF-8');
    $variants[] = '%22'.$old.'%22'; $variants[] = '%22'.$old.'/%22';

    // Substituições
    $html = preg_replace('~href=(\"|\\\')#contato\\1~i', 'href="'.$safe.'" target="_blank" rel="noopener noreferrer nofollow"', $html);
    $html = preg_replace('~href=(\"|\\\')#sobre\\1~i',   'href="'.$safe.'" target="_blank" rel="noopener noreferrer nofollow"', $html);
    foreach ($variants as $ov) {
      $q = preg_quote($ov, '~');
      $html = preg_replace('~href=(\"|\\\')'.$q.'\\1~i', 'href="'.$safe.'" target="_blank" rel="noopener noreferrer nofollow"', $html);
      $html = preg_replace('~data-(link|url|href)=(\"|\\\')'.$q.'\\2~i', 'data-$1="'.$safe.'"', $html);
      $html = preg_replace('~(location\\s*\\.\\s*href\\s*=\\s*)(\"|\\\')'.$q.'\\2~i', '$1"'.$safe.'"', $html);
      $html = preg_replace('~(open\\s*\\(\\s*)(\"|\\\')'.$q.'\\2~i', '$1"'.$safe.'"', $html);
    }
    $html = preg_replace('~href=\\"%22(https?://[^\\"]+)%22\\"~i', 'href="$1"', $html);

    // Grava
    @copy($idx, $dir . DIRECTORY_SEPARATOR . 'index.html.bak-'.date('Ymd-His'));
    if (@file_put_contents($idx, $html) === false) { $fail[]=$f.' (não gravou)'; continue; }

    // Atualiza meta
    $meta['acesso'] = $acesso; $meta['updated_at'] = date('c');
    @file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

    $ok[]=$f;
  }
  closedir($dh);
}

$msg = [];
if ($ok)   $msg[] = 'Atualizados: '.implode(', ', $ok);
if ($skip) $msg[] = 'Ignorados: '.implode(', ', $skip);
if ($fail) $msg[] = 'Falhas: '.implode(', ', $fail);

flash_back($msg ? implode(' | ', $msg) : 'Nada para atualizar.', null);
