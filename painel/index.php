<?php
require __DIR__.'/config.php';
require_auth();
header('X-Robots-Tag: noindex, nofollow', true);

$sites = [];
$base = $SITES_DIR;
if ($base && is_dir($base)) {
  $dh = opendir($base);
  if ($dh) {
    while (($f = readdir($dh)) !== false) {
      if ($f === '.' || $f === '..' || $f === '.keep') continue;
      $dir = $base . DIRECTORY_SEPARATOR . $f;
      if (!is_dir($dir)) continue;
      $idx = $dir . DIRECTORY_SEPARATOR . 'index.html';
      if (!is_file($idx)) continue;
      $metaPath = $dir . DIRECTORY_SEPARATOR . 'meta.json';
      $meta = is_file($metaPath) ? @json_decode(@file_get_contents($metaPath), true) : null;
      $sites[] = [
        'name' => $f,
        'path' => $idx,
        'mtime'=> @filemtime($idx) ?: 0,
        'size' => @filesize($idx) ?: 0,
        'acesso' => (is_array($meta) && !empty($meta['acesso'])) ? (string)$meta['acesso'] : ''
      ];
    }
    closedir($dh);
  }
}
usort($sites, fn($a,$b)=> $b['mtime'] <=> $a['mtime']);

function fmtBytes($b){ $u=['B','KB','MB','GB']; $i=0; while($b>1024 && $i<3){$b/=1024;$i++;} return number_format($b, $i?2:0, ',', '.') . ' ' . $u[$i]; }
?>
<!doctype html>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Painel — Gerador por CNPJ</title>
<style>
  :root{ --bg:#0f172a; --card:#0b1226; --fg:#e5e7eb; --muted:#94a3b8; --accent:#ec4899; --soft:rgba(255,255,255,.08) }
  *{box-sizing:border-box} body{margin:0;background:var(--bg);color:var(--fg);font-family: ui-sans-serif,system-ui,Inter,Segoe UI,Roboto,Ubuntu,Arial,Helvetica,sans-serif}
  header{display:flex;justify-content:space-between;align-items:center;padding:16px 18px;border-bottom:1px solid var(--soft)}
  .wrap{padding:18px;display:grid;gap:18px}
  .card{background:var(--card);border:1px solid var(--soft);border-radius:16px;padding:16px}
  table{width:100%;border-collapse:collapse}
  th,td{border-bottom:1px solid var(--soft);padding:10px 8px;text-align:left;font-size:14px;vertical-align:top}
  th{color:#cbd5e1;font-weight:600}
  a{color:#93c5fd;text-decoration:none}
  .btn{display:inline-block;padding:8px 12px;border-radius:10px;background:#ec4899;color:#111;font-weight:700;text-decoration:none;border:0;cursor:pointer}
  textarea{width:100%;min-height:180px;background:#0e1630;color:#e5e7eb;border:1px solid #223054;border-radius:12px;padding:12px}
  input, select{width:100%;padding:12px;background:#0e1630;color:#e5e7eb;border:1px solid #223054;border-radius:12px}
  .grid{display:grid;gap:14px}
  @media(min-width:900px){ .grid{grid-template-columns:1fr 1fr} }
  .muted{color:var(--muted);font-size:12px}
</style>
<header>
  <div><strong>Painel</strong> — Gerador por CNPJ</div>
  <form method="post" action="index.php"><input type="hidden" name="__logout" value="1"><button class="btn" type="submit" style="background:#334155;color:#e5e7eb">Sair</button></form>

<script>
(function(){
  var host = (location.host || '').toLowerCase();
  var sel = document.getElementById('sel-dominio');
  if (!sel || !host) return;
  for (var i=0;i<sel.options.length;i++){
    var v = sel.options[i].value.toLowerCase();
    if (v === host || ('www.'+v)===host || v===host.replace(/^www\./,'')){
      sel.selectedIndex = i; break;
    }
  }
})();
</script>

</header>

<?php if (session_status() !== PHP_SESSION_ACTIVE) @session_start(); ?>
<?php if (!empty($_SESSION['flash_ok']) || !empty($_SESSION['flash_err'])): ?>
  <div class="wrap" style="padding-top:0">
    <?php if (!empty($_SESSION['flash_ok'])): ?>
      <div class="card" style="border-color:#14532d;background:#052e1b;color:#d1fae5"><strong>Sucesso:</strong> <?= e($_SESSION['flash_ok']); ?></div>
    <?php endif; if (!empty($_SESSION['flash_err'])): ?>
      <div class="card" style="border-color:#7f1d1d;background:#2b0b0b;color:#fecaca"><strong>Erro:</strong> <?= e($_SESSION['flash_err']); ?></div>
    <?php endif; ?>
  </div>
  <?php $_SESSION['flash_ok']=null; $_SESSION['flash_err']=null; ?>
<?php endif; ?>

<div class="wrap">

  <div class="card">
    <h3>Gerar via CNPJ automático (Atendimento)</h3>
    <form method="post" action="gerar_por_dominio.php" style="display:grid;gap:10px;max-width:720px">
      <label>Escolha o site</label>
      <!-- [ADD] Barra de pesquisa de domínios -->
<div class="input-search-dominios" style="margin-bottom:10px;">
  <input id="siteSearch" type="text" inputmode="url"
    placeholder="Pesquisar domínio (ex.: meudominio.com)"
    aria-label="Pesquisar domínio"
    style="width:100%;padding:10px 12px;border-radius:8px;border:1px solid rgba(255,255,255,0.12);background:#111827;color:#e5e7eb;outline:none;" />
</div><select id="sel-dominio" name="dominio" required>
        <?php foreach ($sites as $s): ?>
          <option value="<?= e($s['name']) ?>"><?= e($s['name']) ?></option>
        <?php endforeach; ?>
      </select>
      <label>Link de Acesso (para o botão “Acesso”)</label>
      <input type="url" name="acesso" placeholder="https://seu-link-de-acesso.com" required>
      <button class="btn" type="submit">Gerar (Atendimento)</button>
    </form>
    <p class="muted">O CNPJ é identificado automaticamente a partir do link/HTML do site.</p>
  </div>

  <div class="card">
    <h3>Domínios ativos (sites/)</h3>
    <table>
      <thead>
        <tr><th>Site</th><th>Última alteração</th><th>Link</th><th>Tamanho</th><th>Visualizar</th></tr>
      </thead>
      <tbody>
        <?php foreach ($sites as $s): ?>
          <tr>
            <td><?= e($s['name']) ?></td>
            <td><?= e(date('d/m/Y H:i:s', $s['mtime'])) ?></td>
            <td style="min-width:360px">
              <?php if (!empty($s['acesso'])): ?>
                <a href="<?= e($s['acesso']) ?>" target="_blank" rel="noopener"><?= e($s['acesso']) ?></a>
              <?php else: ?>
                <span class="muted">—</span>
              <?php endif; ?>
              <div style="display:flex;flex-wrap:wrap;gap:10px;margin-top:8px"><form method="post" action="alterar_link.php" style="display:grid;gap:6px;max-width:520px">
                <input type="hidden" name="dominio" value="<?= e($s['name']) ?>">
                <input type="url" name="acesso" value="<?= e($s['acesso']) ?>" placeholder="https://novo-link.com" required>
                <button class="btn" type="submit" style="width:auto">Salvar novo link</button>
              </form><form method="post" action="restaurar_index.php" onsubmit="return confirm('Restaurar a index anterior para este domínio?')" style="display:inline-block"><input type="hidden" name="dominio" value="<?= e($s['name']) ?>"><button class="btn" type="submit" style="background:#64748b;color:#fff">Restaurar index anterior</button></form></div>
            </td>
            <td><?= e(fmtBytes($s['size'])) ?></td>
            <td><a target="_blank" rel="noopener" href="https://<?= e($s['name']) ?>/">abrir</a></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>


  <div class="card">
    <h3>Alterar link em massa (apenas sites que já possuem link)</h3>
    <form method="post" action="alterar_link_massa.php" style="display:grid;gap:10px;max-width:720px" onsubmit="return confirm('Aplicar novo link em todos os domínios que já possuem link?')">
      <label>Novo link para aplicar</label>
      <input type="url" name="acesso" placeholder="https://novo-link.com" required>
      <button class="btn" type="submit">Aplicar link em massa</button>
      <p class="muted">Somente os domínios que tiverem link em <code>meta.json</code> serão alterados. Os demais são ignorados.</p>
    </form>
  </div>

</div>
<!-- [ADD] Filtro de pesquisa para select[name="site"] -->
<script>
(function(){
  try {
    var searchInput = document.getElementById('siteSearch');
    var selectEl = document.querySelector('select[name="site"]');
    if (!searchInput || !selectEl) return;

    // Cache das opções originais
    var originalOptions = Array.prototype.slice.call(selectEl.options).map(function(opt){
      return { value: opt.value, text: opt.text };
    });

    function rebuildSelect(list, keepValue) {
      var prev = (typeof keepValue !== 'undefined' ? keepValue : selectEl.value);
      // Limpa
      while (selectEl.options.length) selectEl.remove(0);
      // Recria
      list.forEach(function(item){
        var o = document.createElement('option');
        o.value = item.value;
        o.text = item.text;
        selectEl.add(o);
      });
      // Mantém seleção quando possível
      var hasPrev = list.some(function(i){ return i.value === prev; });
      if (hasPrev) selectEl.value = prev;
      else if (selectEl.options.length) selectEl.selectedIndex = 0;
    }

    var t = null;
    function onSearch(){
      var q = (searchInput.value || '').trim().toLowerCase();
      if (!q) {
        rebuildSelect(originalOptions);
        return;
      }
      var filtered = originalOptions.filter(function(o){
        return (o.text || '').toLowerCase().indexOf(q) !== -1
            || (o.value || '').toLowerCase().indexOf(q) !== -1;
      });
      rebuildSelect(filtered);
    }

    searchInput.addEventListener('input', function(){
      if (t) window.clearTimeout(t);
      t = window.setTimeout(onSearch, 120);
    });

    // Atalhos de navegação
    searchInput.addEventListener('keydown', function(e){
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        var dir = (e.key === 'ArrowDown') ? 1 : -1;
        var idx = Math.max(0, Math.min(selectEl.options.length - 1, selectEl.selectedIndex + dir));
        selectEl.selectedIndex = idx;
      }
    });
  } catch (err) {
    console.error('Filtro de pesquisa de domínio falhou:', err);
  }
})();
</script>

<!-- [ADD] Filtro de pesquisa para select[name="dominio"] -->
<script>
(function(){
  try {
    var searchInput = document.getElementById('siteSearch');
    var selectEl = document.querySelector('select[name="dominio"]') || document.querySelector('select#dominio');
    if (!searchInput || !selectEl) return;

    var originalOptions = Array.prototype.slice.call(selectEl.options).map(function(opt){
      return { value: opt.value, text: opt.text };
    });

    function rebuildSelect(list, keepValue) {
      var prev = (typeof keepValue !== 'undefined' ? keepValue : selectEl.value);
      while (selectEl.options.length) selectEl.remove(0);
      list.forEach(function(item){
        var o = document.createElement('option');
        o.value = item.value;
        o.text = item.text;
        selectEl.add(o);
      });
      var hasPrev = list.some(function(i){ return i.value === prev; });
      if (hasPrev) selectEl.value = prev;
      else if (selectEl.options.length) selectEl.selectedIndex = 0;
    }

    var t = null;
    function onSearch(){
      var q = (searchInput.value || '').trim().toLowerCase();
      if (!q) { rebuildSelect(originalOptions); return; }
      var filtered = originalOptions.filter(function(o){
        return (o.text || '').toLowerCase().indexOf(q) !== -1
            || (o.value || '').toLowerCase().indexOf(q) !== -1;
      });
      rebuildSelect(filtered);
    }

    searchInput.addEventListener('input', function(){
      if (t) window.clearTimeout(t);
      t = window.setTimeout(onSearch, 120);
    });

    // Enter: se só tiver 1 resultado, mantém foco e não submete acidentalmente
    searchInput.addEventListener('keydown', function(e){
      if (e.key === 'Enter') {
        e.preventDefault();
        if (selectEl.options.length === 1) selectEl.selectedIndex = 0;
      }
    });

    searchInput.addEventListener('keydown', function(e){
      if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
        e.preventDefault();
        var dir = (e.key === 'ArrowDown') ? 1 : -1;
        var idx = Math.max(0, Math.min(selectEl.options.length - 1, selectEl.selectedIndex + dir));
        selectEl.selectedIndex = idx;
      }
    });
  } catch (err) {
    console.error('Filtro de pesquisa de domínio falhou:', err);
  }
})();
</script>
