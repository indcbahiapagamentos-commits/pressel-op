<?php
// ===================== Painel :: Config (Senha Única) =====================
if (session_status() !== PHP_SESSION_ACTIVE) @session_start();

// Caminhos
$ROOT_DIR  = dirname(__DIR__);
$SITES_DIR = $ROOT_DIR . DIRECTORY_SEPARATOR . 'sites';

// ====== SENHA ÚNICA DO PAINEL ======
const PAINEL_SENHA = 'senha123';

function e($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

function require_auth(){
  // logout explícito
  if (!empty($_POST['__logout'])) {
    @session_destroy();
    header('Location: ./'); exit;
  }

  // se já autenticado, ok
  if (!empty($_SESSION['painel_ok']) && $_SESSION['painel_ok'] === true) return;

  // Se mandou senha via POST, valida
  if (isset($_POST['__senha'])) {
    $s = (string)$_POST['__senha'];
    if (hash_equals(PAINEL_SENHA, $s)) {
      $_SESSION['painel_ok'] = true;
      // volta para a mesma página sem reenvio de formulário
      $url = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : 'index.php';
      header('Location: ' . $url); exit;
    } else {
      $erro = 'Senha incorreta.';
    }
  }

  // Tela simples de senha (inline), sem usuário e sem página extra
  http_response_code(401);
  ?>
  <!doctype html>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Acesso ao Painel</title>
  <style>
    :root{ --bg:#0f172a; --card:#0b1226; --fg:#e5e7eb; --soft:rgba(255,255,255,.08); --accent:#ec4899 }
    *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);font-family: ui-sans-serif,system-ui,Inter,Segoe UI,Roboto,Ubuntu,Arial}
    .wrap{min-height:100vh;display:grid;place-items:center;padding:24px}
    .card{background:var(--card);border:1px solid var(--soft);border-radius:16px;padding:20px;max-width:360px;width:100%;box-shadow:0 20px 40px rgba(0,0,0,.25)}
    h1{margin:0 0 18px 0;font-size:20px}
    input{width:100%;padding:12px;background:#0e1630;color:#e5e7eb;border:1px solid #223054;border-radius:12px;margin-bottom:12px}
    .btn{display:inline-block;width:100%;padding:12px;border-radius:12px;background:var(--accent);color:#111;font-weight:800;border:0;cursor:pointer}
    .err{background:#2b0b0b;border:1px solid #7f1d1d;color:#fecaca;border-radius:12px;padding:10px;margin-bottom:12px}
    .muted{color:#94a3b8;font-size:12px}
  </style>
  <div class="wrap">
    <form class="card" method="post">
      <h1>Digite a senha</h1>
      <?php if (!empty($erro)): ?><div class="err"><?= e($erro) ?></div><?php endif; ?>
      <input type="password" name="__senha" placeholder="Senha" autofocus required>
      <button class="btn" type="submit">Entrar</button>
      <p class="muted" style="margin-top:10px">Proteção simples por sessão. Acesso expira ao fechar o navegador.</p>
    </form>
  </div>
  <?php
  exit;
}
