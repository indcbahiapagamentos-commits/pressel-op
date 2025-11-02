<?php
require __DIR__.'/config.php';
require_auth();
header('X-Robots-Tag: noindex, nofollow', true);

$site = (string)($_POST['site'] ?? '');
$html = (string)($_POST['html'] ?? '');
if (!preg_match('/^[A-Za-z0-9.\-_]+$/', $site)) { http_response_code(400); exit('site inválido'); }
if ($html === '') { http_response_code(400); exit('html vazio'); }

$dir = $SITES_DIR . DIRECTORY_SEPARATOR . $site;
$idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
if (!is_dir($dir)) { http_response_code(404); exit('pasta não encontrada'); }

@rename($idx, $dir . DIRECTORY_SEPARATOR . ('index.html.bak-' . date('Ymd-His')));
if (file_put_contents($idx, $html) === false) { http_response_code(500); exit('erro ao gravar'); }

header('Content-Type: text/html; charset=utf-8');
echo '<!doctype html><meta charset="utf-8"><title>OK</title>';
echo '<p>Atualizado <strong>'.e($site).'</strong> com sucesso. <a href="./">Voltar</a> — <a target="_blank" href="/sites/'.rawurlencode($site).'/index.html">Ver site</a></p>';
