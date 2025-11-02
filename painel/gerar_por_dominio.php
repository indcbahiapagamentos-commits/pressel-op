<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';
require_auth();

/**
 * Este endpoint do PAINEL dispara a geração automática do site
 * no domínio selecionado, usando o gerador já existente no DOMÍNIO.
 *
 * Regras importantes:
 * - NÃO altera a estrutura do projeto.
 * - Usa o index.php do próprio domínio (/) que já sabe salvar em /sites/<dominio>/index.html.
 * - O CNPJ é extraído automaticamente do link atual ou do próprio site, então o usuário só informa o LINK DE ACESSO.
 */

header('Content-Type: text/html; charset=utf-8');
require_once __DIR__.'/lib_gerador_site.php';

/** Gerador mínimo incorporado ao painel (sem mexer no index.php raiz) */
class PainelGeradorAtendimento {
  private string $cnpj;
  private array $dadosEmpresa = [];

  public function __construct(string $cnpj) {
    $this->cnpj = preg_replace('/\D+/', '', $cnpj);
  }

  public function buscarDadosAPI(): void {
    $url = "https://minhareceita.org/{$this->cnpj}";
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_TIMEOUT, 25);
    $resp = curl_exec($ch);
    $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($code !== 200 || !$resp) throw new \RuntimeException("API $code");
    $j = json_decode($resp, true);
    if (!is_array($j)) throw new \RuntimeException("JSON inválido");
    $this->dadosEmpresa = $j;
  }

  private function get(string $k, $def=''){ return isset($this->dadosEmpresa[$k]) ? $this->dadosEmpresa[$k] : $def; }

  public function gerarHTMLAtendimento(string $linkAcesso): string {
    $razao  = htmlspecialchars((string)$this->get('razao_social','Empresa'), ENT_QUOTES,'UTF-8');
    $fant   = htmlspecialchars((string)($this->get('nome_fantasia') ?: $razao), ENT_QUOTES,'UTF-8');
    $cnpjf  = $this->formatarCNPJ($this->cnpj);
    $cidade = htmlspecialchars((string)($this->get('municipio') ?: ''), ENT_QUOTES,'UTF-8');
    $uf     = htmlspecialchars((string)($this->get('uf') ?: ''), ENT_QUOTES,'UTF-8');
    $link   = htmlspecialchars((string)$linkAcesso, ENT_QUOTES,'UTF-8');

    $schema = '<script type="application/ld+json">'.json_encode([
      "@context"=>"https://schema.org", "@type"=>"Organization",
      "name"=>$fant, "legalName"=>$razao, "url"=>"https://".$_SERVER['HTTP_HOST']."/",
      "identifier"=>["@type"=>"PropertyValue","name"=>"CNPJ","value"=>$cnpf=$cnpjf],
      "address"=>["@type"=>"PostalAddress","addressLocality"=>$cidade,"addressRegion"=>$uf]
    ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES).'</script>';

    return '<!doctype html><html lang="pt-br"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Atendimento • '.$fant.'</title>
<meta name="robots" content="noindex,nofollow">
<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">'.$schema.'
<style>
:root{--pink:#ec4899;--ink:#2f2a3a;--bg:#fff7fb;--hero:#ffe7f1}
*{box-sizing:border-box}body{margin:0;font-family:Poppins,system-ui,Arial,sans-serif;background:var(--bg);color:var(--ink)}
.container{max-width:940px;margin:0 auto;padding:24px}
.header{background:linear-gradient(135deg,#fae1ea 0%, #ffffff 100%);padding:28px 0}
h1{margin:0 0 8px 0} .card{background:#fff;border:1px solid #f2d5e2;border-radius:16px;box-shadow:0 8px 24px rgba(31,41,55,.08);padding:20px}
.btn{display:inline-block;background:#fff;border:2px solid var(--pink);color:#111;font-weight:800;border-radius:12px;padding:14px 18px;text-decoration:none}
.btn:hover{filter:brightness(1.05)} .cta{background:#ec4899;color:#111;border:0}
.small{opacity:.7;font-size:12px}
footer{padding:22px 0;color:#6b7280}
</style></head><body>
<header class="header"><div class="container">
  <div class="card" style="text-align:center">
    <h1>Atendimento ao Cliente</h1>
    <p>'.$fant.' — CNPJ '.$cnpjf.'</p>
    <p class="small">'.$cidade.' / '.$uf.'</p>
    <p style="margin:22px 0"><a class="btn cta" target="_blank" rel="noopener nofollow" href="'.$link.'">Acesso</a></p>
  </div>
</div></header>
<main class="container">
  <div class="card"><strong>Transparência:</strong> Portal informativo com contato e acesso. Não representamos órgãos governamentais nem instituições financeiras.</div>
</main>
<footer class="container"><div class="small">© '.date('Y').' '.$fant.'. Termos • Privacidade • Cookies</div></footer>
</body></html>';
  }

  private function formatarCNPJ(string $d): string {
    $d=preg_replace('/\D+/','',$d);
    return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/','$1.$2.$3/$4-$5',$d);
  }
}


function flash_and_back(?string $ok, ?string $err){
  if (session_status() !== PHP_SESSION_ACTIVE) @session_start();
  if ($ok) $_SESSION['flash_ok'] = $ok;
  if ($err) $_SESSION['flash_err'] = $err;
  header('Location: ./index.php'); exit;
}


// ---------- Helpers ----------

/** Normaliza e retorna um CNPJ apenas com dígitos (14). */
function so_digitos(string $s): string {
  return preg_replace('/\D+/', '', $s);
}

/** Tenta extrair CNPJ (14 dígitos) de uma string qualquer. */
function extrair_cnpj_de_texto(string $texto): ?string {
  // 1) formatado 00.000.000/0000-00
  if (preg_match('~(\d{2}\.\d{3}\.\d{3}/\d{4}-\d{2})~', $texto, $m)) {
    $cnpj = so_digitos($m[1]);
    if (strlen($cnpj) === 14) return $cnpj;
  }
  // 2) só dígitos
  if (preg_match('~\b(\d{14})\b~', $texto, $m)) {
    return $m[1];
  }
  return null;
}

/**
 * Tenta descobrir o CNPJ do DOMÍNIO a partir de várias fontes:
 * 1) Parâmetro link_atual enviado pelo painel (se existir) – extrai CNPJ do link.
 * 2) HTML do próprio domínio (GET /) – procura CNPJ renderizado na página.
 */
function descobrir_cnpj_do_dominio(string $dominio, ?string $linkAtual = null): ?string {
  // 1) a partir do link Atual (se enviado pelo painel que já o conhece)
  if ($linkAtual) {
    if ($c = extrair_cnpj_de_texto($linkAtual)) return $c;
  }
  // 2) tenta buscar o HTML do site ativo e procurar um CNPJ impresso
  foreach (['https://', 'http://'] as $scheme) {
    $url = $scheme . $dominio . '/';
    $ctx = stream_context_create([ 'http'=> ['timeout'=>8, 'follow_location'=>1, 'ignore_errors'=>true] ]);
    $html = @file_get_contents($url, false, $ctx);
    if (is_string($html) && $html !== '') {
      if ($c = extrair_cnpj_de_texto($html)) return $c;
    }
  }
  return null;
}

/** POST simples com cURL seguindo redirecionamentos. */
function curl_post_follow(string $url, array $fields, int $timeout=25, ?string $hostHeader=null): array {
  $ch = curl_init();
  curl_setopt($ch, CURLOPT_URL, $url);
  curl_setopt($ch, CURLOPT_POST, true);
  curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($fields));
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
  curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
  curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
  curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
  // Alguns ambientes usam proxy/CF com headers
  curl_setopt($ch, CURLOPT_HTTPHEADER, array_filter(['User-Agent: PainelBot/1.0', $hostHeader ? ('Host: '.$hostHeader) : null]));
  $body = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);
  return ['code'=>$code,'body'=>$body,'err'=>$err];
}

// ---------- Fluxo principal ----------

$dominio = isset($_POST['dominio']) ? trim($_POST['dominio']) : '';
$acesso  = isset($_POST['acesso'])  ? trim($_POST['acesso'])  : '';
$linkAtualOpc = isset($_POST['link_atual']) ? trim($_POST['link_atual']) : null; // opcional


// Suporte a operação em massa: lista de domínios por linha
if (isset($_POST['em_massa']) && $_POST['em_massa'] === '1') {
  $lista = isset($_POST['sites_lista']) ? trim((string)$_POST['sites_lista']) : '';
  $dominios = preg_split('~\r?\n~', $lista);
  $ok = []; $falhas = [];
  foreach ($dominios as $d) {
    $d = trim($d);
    if ($d === '') continue;
    $d = preg_replace('~[^A-Za-z0-9\.\-]~', '', $d);
    $c = descobrir_cnpj_do_dominio($d, $linkAtualOpc);
    if (!$c) { $falhas[] = $d . ' (CNPJ não encontrado)'; continue; }
    $feito = false;
    foreach (['https://'.$d.'/', 'http://'.$d.'/'] as $u) {
      $r = curl_post_follow($u, ['cnpj'=>$c, 'link'=>$acesso]);
      if ($r['code'] >= 200 && $r['code'] < 400 && is_string($r['body']) && $r['body'] !== '') { $feito = true; break; }
    }
    if ($feito) $ok[] = $d; else $falhas[] = $d . ' (HTTP erro)';
  }
  $_SESSION['flash_ok'] = 'Gerado com sucesso: ' . implode(', ', $ok);
  if ($falhas) $_SESSION['flash_err'] = 'Falhas: ' . implode('; ', $falhas);
  header('Location: ./index.php'); exit;
}

if ($dominio === '' || $acesso === '') {
  flash_and_back(null, 'Informe domínio e o link de acesso.');
}

// Saneia domínio
$dominio = preg_replace('~[^A-Za-z0-9\.\-]~', '', $dominio);

// Descobre CNPJ automaticamente
$cnpj = descobrir_cnpj_do_dominio($dominio, $linkAtualOpc);
if (!$cnpj) {
  flash_and_back(null, 'Não consegui identificar o CNPJ automaticamente para ' . $dominio . '. Verifique se o link/HTML contém CNPJ.');
}

// Dispara geração no PRÓPRIO domínio (index.php raíz lida com salvar em /sites/<dominio>/index.html)
$gerarUrls = ["https://{$dominio}/", "http://{$dominio}/"];
$res = null;


// === Gerar localmente e gravar em /sites/<dominio>/index.html ===
$ger = new PainelGeradorAtendimento($cnpj);
try { $ger->buscarDadosAPI(); } catch (\Throwable $e) {
  flash_and_back(null, 'API erro: '.$e->getMessage());
}

// === Gerar usando a classe completa (site completo) e customizar para "Atendimento" ===
try {
  $Ger = new GeradorSiteGoogleAds($cnpj);
  $Ger->buscarDadosAPI();
  $siteHTML = $Ger->gerarSiteCompleto();
} catch (\Throwable $e) {
  flash_and_back(null, 'Falha ao gerar com classe completa: '.$e->getMessage());
}

// Link seguro
$linkAtual = $acesso;
if (!preg_match('~^https?://~i', $linkAtual)) { $linkAtual = 'https://'.$linkAtual; }
$safeLink = htmlspecialchars($linkAtual, ENT_QUOTES, 'UTF-8');

// Personalizações "Atendimento": rosa claro puxando p/ branco + H1/Logo/Botão
// Remove gradientes anteriores e injeta tema rosa->branco
$siteHTML = preg_replace('~background\s*:\s*linear-gradient\([^;]*\)~i', 'background-color: #fffdfd', $siteHTML);
$siteHTML = preg_replace('~background-image\s*:\s*linear-gradient\([^;]*\)~i', 'background-image: none', $siteHTML);
$__pinkStyle = "<style>
  body{background: linear-gradient(180deg,#fff8fb 0%, #ffffff 55%) !important;}
  header, .hero, .hero-section, #home, .topo, .banner, .header{
    background: linear-gradient(135deg,#fae1ea 0%, #ffffff 100%) !important;
    background-image:none !important;
  }
</style>";
$siteHTML = preg_replace('~</head>~i', $__pinkStyle . '</head>', $siteHTML, 1);

// Ajustes de título e subtítulo
$siteHTML = preg_replace('~<h1[^>]*>.*?</h1>~is', '<h1>Atendimento ao Cliente</h1>', $siteHTML, 1);
$siteHTML = preg_replace('~(<h1[^>]*>.*?</h1>)\s*<p[^>]*>.*?</p>~is', '$1', $siteHTML, 1);

// Logo do topo substituída por "Atendimento"
$logoAt = "<div class='logo'><a href='#home' class='no-underline' style='display:inline-flex;gap:.5rem;align-items:center'>
  <span style='display:inline-flex;width:28px;height:28px;border-radius:9px;background:#ec4899;box-shadow:0 2px 10px rgba(236,72,153,.35)'></span>
  <strong style='color:inherit;'>Atendimento</strong></a></div>";
$siteHTML = preg_replace('~<div class=[\'"]logo[\'"][^>]*>.*?</div>~is', $logoAt, $siteHTML, 1);

// Botões do hero apontam para o link de acesso
$siteHTML = str_ireplace('>Fale Conosco<', '>Acesso<', $siteHTML);
$safeLink = htmlspecialchars($acesso, ENT_QUOTES, 'UTF-8');
// ---- Atendimento: forçar navegação pro CTA ----
// Anchors comuns de menu
$siteHTML = preg_replace('~href=("|\')#(home|inicio|topo|sobre|contato)\1~i', 'href="'.$safeLink.'" target="_blank" rel="noopener noreferrer nofollow"', $siteHTML);
// Âncoras genéricas #algo (mantém outras se necessário)
$siteHTML = preg_replace('~href=("|\')#[a-z0-9_-]+\1~i', 'href="'.$safeLink.'" target="_blank" rel="noopener noreferrer nofollow"', $siteHTML);
// Links WhatsApp viram CTA
$siteHTML = preg_replace('~href=("|\')(?:https?:)?//(?:wa\.me|api\.whatsapp\.com)[^"\']*\1~i', 'href="'.$safeLink.'" target="_blank" rel="noopener noreferrer nofollow"', $siteHTML);
// Botões com classe relacionada a whatsapp
$siteHTML = preg_replace('~<a([^>]+class=\"[^\"]*whats[^\"]*\"[^>]*)href=\"[^\"]*\"~i', '<a$1href="'.$safeLink.'"', $siteHTML);

$safeLink = htmlspecialchars($acesso, ENT_QUOTES, 'UTF-8');
$siteHTML = preg_replace('~href=("|\')#contato\1~i', 'href="'.$safeLink.'" target="_blank" rel="noopener noreferrer nofollow"', $siteHTML);
$siteHTML = preg_replace('~href=("|\')#sobre\1~i',   'href="'.$safeLink.'" target="_blank" rel="noopener noreferrer nofollow"', $siteHTML);
$siteHTML = preg_replace('~href=\"%22(https?://[^\"]+)%22\"~i', 'href="$1"', $siteHTML);

$siteHTML = preg_replace('~href=["\']#contato["\']~i', 'href="'.$safeLink.'" target="_blank" rel="noopener noreferrer nofollow"', $siteHTML);
$siteHTML = preg_replace('~href=["\']#sobre["\']~i',   'href="'.$safeLink.'" target="_blank" rel="noopener noreferrer nofollow"', $siteHTML);

// CSS final de refinamento
$refino = "<style>:root{--pink-body:#fff7fb;--pink-hero:#ffe7f1;--ink-strong:#2f2a3a;--ink:#4a4458;--line:#efdfe6}
body{background:var(--pink-body)!important;color:var(--ink)} header,.hero,.hero-section,#home,.topo,.banner,.header{background:var(--pink-hero)!important;background-image:none!important}
h1,h2,h3{color:#1f2937}</style>";
$siteHTML = preg_replace('~</head>~i', $refino . '</head>', $siteHTML, 1);

// HTML final para gravar
$html = $siteHTML;
// Caminhos
$baseSites = realpath(__DIR__.'/../sites') ?: (__DIR__.'/../sites');
$dir = $baseSites . DIRECTORY_SEPARATOR . $dominio;
$indexPath = $dir . DIRECTORY_SEPARATOR . 'index.html';

// Cria pasta se não existir
if (!is_dir($dir)) { @mkdir($dir, 0775, true); }

// Backup da index atual (se existir)
if (is_file($indexPath)) {
  $stamp = date('Ymd-His');
  @copy($indexPath, $dir . DIRECTORY_SEPARATOR . 'index.html.bak-'.$stamp);
}

// Grava nova index
$okWrite = @file_put_contents($indexPath, $html);
if ($okWrite === false) {
  flash_and_back(null, 'Falha ao gravar em '.$indexPath);
}

// macaco.php
@file_put_contents($dir . DIRECTORY_SEPARATOR . 'macaco.php', "<?php echo 'ok'; ?>");

// Salva meta.json com link e cnpj
$meta = ['cnpj'=>$cnpj, 'acesso'=>$acesso, 'updated_at'=>date('c')];
@file_put_contents($dir . DIRECTORY_SEPARATOR . 'meta.json', json_encode($meta, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT));


flash_and_back('Gerado e gravado em /sites/'.$dominio.'/index.html', null);

