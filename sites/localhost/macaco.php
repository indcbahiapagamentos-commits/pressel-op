<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors','0');
session_start();

$PASSWORD = 'senha123';

function rrmdir_keep_macaco(string $dir): void {
  if (!is_dir($dir)) return;
  $it = new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS);
  $files = new RecursiveIteratorIterator($it, RecursiveIteratorIterator::CHILD_FIRST);
  foreach ($files as $file) {
    $path = $file->getRealPath();
    if (!$path) continue;
    $base = basename($path);
    if ($base === 'macaco.php') continue;
    if ($file->isDir()) { @rmdir($path); } else { @unlink($path); }
  }
}

$dir = __DIR__;

$erro = '';
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
  $pass = (string)($_POST['pass'] ?? '');
  if (hash_equals($PASSWORD, $pass)) {
    rrmdir_keep_macaco($dir);
    @file_put_contents($dir . DIRECTORY_SEPARATOR . '.factory', date('c'));
    header('Location: /?from=macaco', true, 302);
    exit;
  } else {
    http_response_code(401);
    $erro = 'Senha incorreta.';
  }
}
?>
<!doctype html><html lang="pt-br"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acesso — macaco</title>
  <style>
    :root{--bg:#0f172a;--card:#0b1226;--fg:#e5e7eb;--muted:#94a3b8}
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);font-family:system-ui,Segoe UI,Roboto,Arial}
    .wrap{max-width:420px;margin:10vh auto;padding:24px;background:#0b1226;border-radius:14px}
    h1{margin:0 0 12px} .muted{color:var(--muted);font-size:13px;margin-top:8px}
    input,button{width:100%;padding:10px 12px;border-radius:10px;border:1px solid rgba(255,255,255,.1);background:#111827;color:#e5e7eb}
    button{cursor:pointer;margin-top:10px}
    .erro{color:#ef4444;margin-bottom:8px}
  </style>
</head><body>
<div class="wrap">
  <h1>Acesso — macaco</h1>
  <?php if ($erro): ?><div class="erro"><?=htmlspecialchars($erro, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8')?></div><?php endif; ?>
  <form method="post" action="./macaco" autocomplete="off">
    <label for="pass">Senha</label><br>
    <input id="pass" name="pass" type="password" required>
    <button type="submit">Entrar</button>
  </form>
  <p class="muted">Após autenticar, o domínio será restaurado e você voltará para a tela de CNPJ.</p>
</div>
</body></html>
