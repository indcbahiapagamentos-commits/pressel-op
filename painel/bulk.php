<?php
require __DIR__.'/config.php';
require_auth();
header('Content-Type: application/json; charset=utf-8');
$sitesRaw = (string)($_POST['sites'] ?? '');
$html = (string)($_POST['html'] ?? '');
if ($sitesRaw === '' || $html === '') { http_response_code(400); echo json_encode(['ok'=>false,'error'=>'sites/html vazio']); exit; }

$lines = preg_split('/\R+/', $sitesRaw, -1, PREG_SPLIT_NO_EMPTY);
$lines = array_map('trim', $lines);
$lines = array_values(array_unique($lines));

$out = ['ok'=>true, 'results'=>[]];
foreach ($lines as $site) {
  if (!preg_match('/^[A-Za-z0-9.\-_]+$/', $site)) { $out['results'][]=['site'=>$site,'ok'=>false,'error'=>'site inválido']; continue; }
  $dir = $SITES_DIR . DIRECTORY_SEPARATOR . $site;
  $idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
  if (!is_dir($dir)) { $out['results'][]=['site'=>$site,'ok'=>false,'error'=>'pasta não encontrada']; continue; }
  @rename($idx, $dir . DIRECTORY_SEPARATOR . ('index.html.bak-' . date('Ymd-His')));
  $w = @file_put_contents($idx, $html);
  $out['results'][] = ['site'=>$site,'ok'=>($w!==false), 'error'=>($w===false?'erro ao gravar':null)];
}

echo json_encode($out);
