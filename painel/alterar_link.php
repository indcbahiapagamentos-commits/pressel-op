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
$acesso  = isset($_POST['acesso'])  ? trim((string)$_POST['acesso'])  : '';
if ($dominio==='' || $acesso==='') flash_back(null,'Informe domínio e o novo link.');

$dominio = preg_replace('~[^A-Za-z0-9\.\-]~','',$dominio);
$acesso = trim($acesso, " \t\n\r\0\x0B\"' ");
if (!preg_match('~^https?://~i',$acesso)) $acesso = 'https://'.$acesso;
$acesso = rtrim($acesso,'/');

$base = realpath(__DIR__.'/../sites') ?: (__DIR__.'/../sites');
$dir = $base . DIRECTORY_SEPARATOR . $dominio;
$idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
if (!is_file($idx)) flash_back(null,'index.html não encontrada.');

// Lê HTML atual
$html = (string)@file_get_contents($idx);
if ($html==='') flash_back(null,'Falha ao ler a index.');

// Tenta obter o link antigo pelo meta.json; se não tiver, detecta pelo HTML.
$metaPath = $dir . DIRECTORY_SEPARATOR . 'meta.json';
$old = null;
if (is_file($metaPath)) {
  $meta = @json_decode((string)@file_get_contents($metaPath), true);
  if (is_array($meta) && !empty($meta['acesso'])) $old = (string)$meta['acesso'];
}
if (!$old) {
  // procura primeiro botão/anchor com texto “Acesso/Atendimento/Entrar”
  if (preg_match('~<a[^>]+href=["\'](https?://[^"\']+)["\'][^>]*>\s*(?:<[^>]+>\s*)*(Acesso|Atendimento|Entrar)~i', $html, $m)) {
    $old = $m[1];
  } elseif (preg_match('~href=["\'](https?://[^"\']+)["\'][^>]*class=["\'][^"\']*(btn|cta)~i', $html, $m)) {
    $old = $m[1];
  }
}

// Normaliza variantes do link antigo para substituição ampla
$variants = [];
if ($old) {
  $old = trim($old, " \t\n\r\0\x0B\"' ");
  $old = rtrim($old,'/');
  $variants[] = $old;
  $variants[] = $old.'/';
  // Versões HTML-escaped
  $variants[] = htmlspecialchars($old, ENT_QUOTES, 'UTF-8');
  $variants[] = htmlspecialchars($old.'/', ENT_QUOTES, 'UTF-8');
  // Com %22 nas pontas (caso artefatos)
  $variants[] = '%22'.$old.'%22';
  $variants[] = '%22'.$old.'/%22';
}

// Novo link seguro
$safe = htmlspecialchars($acesso, ENT_QUOTES, 'UTF-8');

// Substituições:
// 1) href com âncoras antigas (#contato/#sobre)
$html = preg_replace('~href=("|\')#contato\1~i', 'href="'.$safe.'" target="_blank" rel="noopener noreferrer nofollow"', $html);
$html = preg_replace('~href=("|\')#sobre\1~i',   'href="'.$safe.'" target="_blank" rel="noopener noreferrer nofollow"', $html);

// 2) href com o link antigo (todas as variantes)
if ($variants) {
  foreach ($variants as $ov) {
    $q = preg_quote($ov, '~');
    // href="old"  ou  href='old'
    $html = preg_replace('~href=("|\')'.$q.'\1~i', 'href="'.$safe.'" target="_blank" rel="noopener noreferrer nofollow"', $html);
    // data-link="old" | data-url="old"
    $html = preg_replace('~data-(link|url|href)=("|\')'.$q.'\2~i', 'data-$1="'.$safe.'"', $html);
    // JS: location.href='old'  | window.open("old")
    $html = preg_replace('~(location\s*\.\s*href\s*=\s*)("|\')'.$q.'\2~i', '$1"'.$safe.'"', $html);
    $html = preg_replace('~(open\s*\(\s*)("|\')'.$q.'\2~i', '$1"'.$safe.'"', $html);
  }
}

// 3) Safety: corrige href="%22https...%22"
$html = preg_replace('~href=\"%22(https?://[^\"]+)%22\"~i', 'href="$1"', $html);

// Backup e grava
@copy($idx, $dir . DIRECTORY_SEPARATOR . 'index.html.bak-'.date('Ymd-His'));
if (@file_put_contents($idx, $html) === false) flash_back(null,'Falha ao gravar index.');

// Atualiza meta.json
$meta = is_file($metaPath) ? @json_decode((string)@file_get_contents($metaPath), true) : [];
if (!is_array($meta)) $meta = [];
$meta['acesso'] = $acesso;
$meta['updated_at'] = date('c');
@file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));

flash_back('Link atualizado para '.$dominio, null);
