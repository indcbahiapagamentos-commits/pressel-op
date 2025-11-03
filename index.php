<?php
declare(strict_types=1);
error_reporting(E_ALL & ~E_NOTICE);
ini_set('display_errors', '0');
ini_set('log_errors', '1');

// Cria diretórios necessários
$logsDir = __DIR__ . '/logs';
$cacheDir = __DIR__ . '/cache';
$sitesDir = __DIR__ . '/sites';

foreach ([$logsDir, $cacheDir, $sitesDir] as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

ini_set('error_log', $logsDir . '/errors.log');

// Segurança básica
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: SAMEORIGIN');
header('X-XSS-Protection: 1; mode=block');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
header("Content-Security-Policy: default-src 'self'; style-src 'self' 'unsafe-inline' https://fonts.googleapis.com; font-src 'self' https://fonts.gstatic.com; img-src 'self' data: https:; script-src 'self' 'unsafe-inline' https://www.googletagmanager.com https://www.google-analytics.com; connect-src 'self' https://www.google-analytics.com");

function e($s): string { 
    return htmlspecialchars((string)$s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); 
}

// Rate limiting simples para geração
function check_generation_rate_limit(): bool {
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $key = 'gen_' . md5($ip);
    $file = sys_get_temp_dir() . '/' . $key . '.json';
    
    $data = ['count' => 0, 'reset' => time() + 3600];
    if (is_file($file)) {
        $content = @file_get_contents($file);
        if ($content) {
            $data = json_decode($content, true) ?: $data;
        }
    }
    
    if (time() > $data['reset']) {
        $data = ['count' => 0, 'reset' => time() + 3600];
    }
    
    if ($data['count'] >= 10) {
        return false;
    }
    
    $data['count']++;
    file_put_contents($file, json_encode($data));
    return true;
}

// === CLASSE DO GERADOR COMPLETA ===
class GeradorSiteGoogleAds {
    private string $cnpj;
    private array $dadosEmpresa = [];
    private array $coresNicho;
    private const API_TIMEOUT = 30;
    private const CACHE_DIR = __DIR__ . '/cache';
    
    public function __construct(string $cnpj) {
        $this->cnpj = $this->limparCNPJ($cnpj);
        $this->definirCoresNicho();
        
        if (!is_dir(self::CACHE_DIR)) {
            @mkdir(self::CACHE_DIR, 0755, true);
        }
    }
    
    private function limparCNPJ(string $cnpj): string {
        return preg_replace('/[^0-9]/', '', $cnpj);
    }
    
    private function obterValor(array $array, string $chave, $padrao = '') {
        return $array[$chave] ?? $padrao;
    }
    
    public function buscarDadosAPI(): array {
        if (strlen($this->cnpj) !== 14) {
            throw new Exception("CNPJ deve ter 14 dígitos");
        }
        
        $cacheFile = self::CACHE_DIR . '/' . $this->cnpj . '.json';
        if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < 86400) {
            $cached = @file_get_contents($cacheFile);
            if ($cached) {
                $this->dadosEmpresa = json_decode($cached, true) ?: [];
                if (!empty($this->dadosEmpresa)) {
                    return $this->dadosEmpresa;
                }
            }
        }
        
        $url = "https://minhareceita.org/{$this->cnpj}";
        
        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => self::API_TIMEOUT,
            CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; GeradorSite/4.0)',
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 3,
            CURLOPT_ENCODING => 'gzip, deflate'
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        if ($httpCode !== 200) {
            throw new Exception("Erro ao buscar dados da API. Código HTTP: {$httpCode}");
        }
        
        if ($error) {
            throw new Exception("Erro cURL: {$error}");
        }
        
        $this->dadosEmpresa = json_decode($response, true);
        
        if (!is_array($this->dadosEmpresa) || empty($this->dadosEmpresa)) {
            throw new Exception("Resposta inválida da API ou CNPJ não encontrado");
        }
        
        @file_put_contents($cacheFile, $response);
        return $this->dadosEmpresa;
    }
    
    private function definirCoresNicho(): void {
        $this->coresNicho = [
            'tecnologia' => [
                'primaria' => '#6366f1',
                'secundaria' => '#4f46e5',
                'acento' => '#8b5cf6',
                'gradiente' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'icone' => '💻'
            ],
            'saude' => [
                'primaria' => '#10b981',
                'secundaria' => '#059669',
                'acento' => '#34d399',
                'gradiente' => 'linear-gradient(135deg, #0ba360 0%, #3cba92 100%)',
                'icone' => '🏥'
            ],
            'comercio' => [
                'primaria' => '#f59e0b',
                'secundaria' => '#d97706',
                'acento' => '#fbbf24',
                'gradiente' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                'icone' => '🛒'
            ],
            'servicos' => [
                'primaria' => '#06b6d4',
                'secundaria' => '#0891b2',
                'acento' => '#22d3ee',
                'gradiente' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                'icone' => '🔧'
            ],
            'industria' => [
                'primaria' => '#64748b',
                'secundaria' => '#475569',
                'acento' => '#94a3b8',
                'gradiente' => 'linear-gradient(135deg, #434343 0%, #000000 100%)',
                'icone' => '🏭'
            ],
            'educacao' => [
                'primaria' => '#8b5cf6',
                'secundaria' => '#7c3aed',
                'acento' => '#a78bfa',
                'gradiente' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
                'icone' => '📚'
            ],
            'default' => [
                'primaria' => '#3b82f6',
                'secundaria' => '#2563eb',
                'acento' => '#60a5fa',
                'gradiente' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'icone' => '🏢'
            ]
        ];
    }
    
    private function identificarNicho(): string {
        $cnae = strtolower($this->obterValor($this->dadosEmpresa, 'cnae_fiscal_descricao', ''));
        
        $nichos = [
            'tecnologia' => ['software', 'tecnologia', 'informática', 'internet', 'desenvolvimento', 'programação', 'sistemas', 'web', 'aplicativos', 'digital'],
            'saude' => ['saúde', 'médica', 'médico', 'hospital', 'clínica', 'farmácia', 'laboratório', 'odontológica', 'fisioterapia', 'enfermagem'],
            'comercio' => ['comércio', 'varejo', 'loja', 'venda', 'mercado', 'supermercado', 'magazine', 'atacado'],
            'servicos' => ['serviços', 'consultoria', 'assessoria', 'manutenção', 'limpeza', 'segurança', 'advocacia'],
            'industria' => ['indústria', 'fabricação', 'manufatura', 'produção', 'industrial', 'fábrica'],
            'educacao' => ['educação', 'ensino', 'escola', 'curso', 'treinamento', 'capacitação', 'faculdade']
        ];
        
        foreach ($nichos as $nicho => $palavras) {
            foreach ($palavras as $palavra) {
                if (stripos($cnae, $palavra) !== false) {
                    return $nicho;
                }
            }
        }
        
        return 'default';
    }
    
    private function getCores(): array {
        $nicho = $this->identificarNicho();
        return $this->coresNicho[$nicho];
    }
    
    private function gerarNomeFantasia(): string {
        $nomeFantasia = $this->obterValor($this->dadosEmpresa, 'nome_fantasia', '');
        if (!empty($nomeFantasia) && $nomeFantasia !== '********') {
            return $nomeFantasia;
        }
        return $this->obterValor($this->dadosEmpresa, 'razao_social', 'Empresa');
    }
    
    private function gerarSchemaOrg(): string {
        $nomeFantasia = $this->gerarNomeFantasia();
        $razaoSocial = $this->obterValor($this->dadosEmpresa, 'razao_social', '');
        $cnae = $this->obterValor($this->dadosEmpresa, 'cnae_fiscal_descricao', '');
        $telefone = $this->obterValor($this->dadosEmpresa, 'ddd_telefone_1', '');
        $email = $this->obterValor($this->dadosEmpresa, 'email', '');
        
        $logradouro = $this->obterValor($this->dadosEmpresa, 'logradouro', '');
        $numero = $this->obterValor($this->dadosEmpresa, 'numero', '');
        $bairro = $this->obterValor($this->dadosEmpresa, 'bairro', '');
        $municipio = $this->obterValor($this->dadosEmpresa, 'municipio', '');
        $uf = $this->obterValor($this->dadosEmpresa, 'uf', '');
        $cep = $this->obterValor($this->dadosEmpresa, 'cep', '');
        
        $schema = [
            "@context" => "https://schema.org",
            "@type" => "LocalBusiness",
            "name" => $nomeFantasia,
            "legalName" => $razaoSocial,
            "description" => $cnae,
            "telephone" => $telefone,
            "email" => $email,
            "address" => [
                "@type" => "PostalAddress",
                "streetAddress" => trim($logradouro . ", " . $numero),
                "addressLocality" => $municipio,
                "addressRegion" => $uf,
                "postalCode" => $cep,
                "addressCountry" => "BR"
            ],
            "openingHoursSpecification" => [
                "@type" => "OpeningHoursSpecification",
                "dayOfWeek" => ["Monday", "Tuesday", "Wednesday", "Thursday", "Friday"],
                "opens" => "08:00",
                "closes" => "18:00"
            ]
        ];
        
        return '<script type="application/ld+json">' . 
               json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . 
               '</script>';
    }
    
    public function gerarCSS(): string {
        $cores = $this->getCores();
        
        return "
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
            
            :root {
                --cor-primaria: {$cores['primaria']};
                --cor-secundaria: {$cores['secundaria']};
                --cor-acento: {$cores['acento']};
                --gradiente: {$cores['gradiente']};
                --bg-light: #ffffff;
                --bg-section: #f8fafc;
                --text-primary: #0f172a;
                --text-secondary: #64748b;
                --border-color: #e2e8f0;
                --shadow-sm: 0 1px 3px rgba(0,0,0,0.12), 0 1px 2px rgba(0,0,0,0.08);
                --shadow-md: 0 4px 6px rgba(0,0,0,0.07), 0 2px 4px rgba(0,0,0,0.06);
                --shadow-lg: 0 10px 15px rgba(0,0,0,0.1), 0 4px 6px rgba(0,0,0,0.09);
                --shadow-xl: 0 20px 25px rgba(0,0,0,0.15), 0 10px 10px rgba(0,0,0,0.04);
                --radius-sm: 8px;
                --radius-md: 12px;
                --radius-lg: 16px;
                --radius-xl: 20px;
                --transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            }
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            html {
                scroll-behavior: smooth;
                -webkit-font-smoothing: antialiased;
                -moz-osx-font-smoothing: grayscale;
            }
            
            body {
                font-family: 'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
                line-height: 1.6;
                color: var(--text-primary);
                overflow-x: hidden;
                background: var(--bg-light);
            }
            
            img {
                max-width: 100%;
                height: auto;
                display: block;
            }
            
            /* Container */
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 20px;
            }
            
            @media (min-width: 768px) {
                .container {
                    padding: 0 40px;
                }
            }
            
            /* Header */
            header {
                background: rgba(255, 255, 255, 0.95);
                backdrop-filter: blur(20px) saturate(180%);
                -webkit-backdrop-filter: blur(20px) saturate(180%);
                padding: 1rem 0;
                box-shadow: var(--shadow-sm);
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                transition: var(--transition);
            }
            
            header.scrolled {
                padding: 0.75rem 0;
                box-shadow: var(--shadow-md);
            }
            
            header .container {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .logo {
                font-size: clamp(1.5rem, 4vw, 1.875rem);
                font-weight: 800;
                background: var(--gradiente);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                letter-spacing: -0.02em;
            }
            
            nav ul {
                list-style: none;
                display: flex;
                gap: clamp(1.5rem, 3vw, 2.5rem);
                align-items: center;
            }
            
            nav a {
                color: var(--text-primary);
                text-decoration: none;
                font-weight: 500;
                font-size: 0.9375rem;
                transition: var(--transition);
                position: relative;
                padding: 0.5rem 0;
            }
            
            nav a::after {
                content: '';
                position: absolute;
                bottom: 0;
                left: 0;
                width: 0;
                height: 2px;
                background: var(--cor-primaria);
                transition: width 0.3s ease;
            }
            
            nav a:hover {
                color: var(--cor-primaria);
            }
            
            nav a:hover::after {
                width: 100%;
            }
            
            .menu-toggle {
                display: none;
                flex-direction: column;
                cursor: pointer;
                gap: 5px;
                padding: 8px;
                border-radius: var(--radius-sm);
                transition: var(--transition);
            }
            
            .menu-toggle:hover {
                background: var(--bg-section);
            }
            
            .menu-toggle span {
                width: 24px;
                height: 2.5px;
                background: var(--cor-primaria);
                border-radius: 2px;
                transition: var(--transition);
            }
            
            .menu-toggle.active span:nth-child(1) {
                transform: translateY(7.5px) rotate(45deg);
            }
            
            .menu-toggle.active span:nth-child(2) {
                opacity: 0;
            }
            
            .menu-toggle.active span:nth-child(3) {
                transform: translateY(-7.5px) rotate(-45deg);
            }
            
            /* Hero Section */
            .hero {
                background: var(--gradiente);
                color: white;
                padding: 140px 0 100px;
                position: relative;
                overflow: hidden;
                margin-top: 70px;
            }
            
            .hero::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: url('data:image/svg+xml,%3Csvg width=\"20\" height=\"20\" xmlns=\"http://www.w3.org/2000/svg\"%3E%3Cpath d=\"M0 0h20v20H0z\" fill=\"none\"/%3E%3Cpath d=\"M0 0h1v1H0zm19 19h1v1h-1z\" fill=\"%23fff\" fill-opacity=\".05\"/%3E%3C/svg%3E');
                opacity: 0.4;
            }
            
            .hero-content {
                position: relative;
                z-index: 2;
                text-align: center;
                max-width: 900px;
                margin: 0 auto;
            }
            
            .hero h1 {
                font-size: clamp(2rem, 6vw, 3.5rem);
                font-weight: 800;
                margin-bottom: 1.5rem;
                line-height: 1.2;
                letter-spacing: -0.02em;
            }
            
            .hero p {
                font-size: clamp(1.125rem, 2vw, 1.375rem);
                margin-bottom: 2.5rem;
                opacity: 0.95;
                line-height: 1.6;
            }
            
            .hero-buttons {
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .btn {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                padding: 15px 32px;
                background: white;
                color: var(--cor-primaria);
                text-decoration: none;
                border-radius: 50px;
                font-weight: 600;
                font-size: 1rem;
                transition: var(--transition);
                box-shadow: var(--shadow-lg);
                border: 2px solid transparent;
            }
            
            .btn:hover {
                transform: translateY(-3px);
                box-shadow: var(--shadow-xl);
            }
            
            .btn:active {
                transform: translateY(-1px);
            }
            
            .btn-outline {
                background: transparent;
                border: 2px solid white;
                color: white;
            }
            
            .btn-outline:hover {
                background: white;
                color: var(--cor-primaria);
            }
            
            /* Sections */
            section {
                padding: clamp(60px, 10vw, 100px) 0;
            }
            
            section h2 {
                font-size: clamp(2rem, 5vw, 2.5rem);
                font-weight: 700;
                margin-bottom: 1rem;
                text-align: center;
                background: var(--gradiente);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                letter-spacing: -0.02em;
            }
            
            .section-subtitle {
                text-align: center;
                font-size: clamp(1rem, 2vw, 1.125rem);
                color: var(--text-secondary);
                max-width: 700px;
                margin: 0 auto 4rem;
                line-height: 1.7;
            }
            
            /* Stats */
            .stats {
                background: var(--bg-section);
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
                gap: 2rem;
                text-align: center;
            }
            
            .stat-card {
                background: white;
                padding: 2.5rem 1.5rem;
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-sm);
                transition: var(--transition);
                border: 1px solid var(--border-color);
            }
            
            .stat-card:hover {
                transform: translateY(-8px);
                box-shadow: var(--shadow-lg);
                border-color: var(--cor-primaria);
            }
            
            .stat-number {
                font-size: clamp(2.5rem, 5vw, 3rem);
                font-weight: 800;
                background: var(--gradiente);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
                margin-bottom: 0.5rem;
            }
            
            .stat-label {
                font-size: 1.0625rem;
                color: var(--text-secondary);
                font-weight: 500;
            }
            
            /* Cards Grid */
            .cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 2rem;
                margin-top: 3rem;
            }
            
            .card {
                background: white;
                padding: 2.5rem;
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-sm);
                transition: var(--transition);
                position: relative;
                overflow: hidden;
                border: 1px solid var(--border-color);
            }
            
            .card::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 4px;
                background: var(--gradiente);
                transform: scaleX(0);
                transform-origin: left;
                transition: transform 0.3s ease;
            }
            
            .card:hover {
                transform: translateY(-8px);
                box-shadow: var(--shadow-lg);
                border-color: var(--cor-primaria);
            }
            
            .card:hover::before {
                transform: scaleX(1);
            }
            
            .card-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
                display: inline-block;
            }
            
            .card h3 {
                color: var(--cor-primaria);
                margin-bottom: 1rem;
                font-size: 1.375rem;
                font-weight: 600;
            }
            
            .card p {
                color: var(--text-secondary);
                line-height: 1.7;
            }
            
            /* Servicos Grid */
            .servicos-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 2rem;
            }
            
            .servico-card {
                background: linear-gradient(135deg, rgba(99, 102, 241, 0.05) 0%, rgba(139, 92, 246, 0.05) 100%);
                padding: 2.5rem;
                border-radius: var(--radius-xl);
                text-align: center;
                transition: var(--transition);
                border: 2px solid transparent;
            }
            
            .servico-card:hover {
                border-color: var(--cor-primaria);
                transform: scale(1.03);
                background: white;
                box-shadow: var(--shadow-lg);
            }
            
            .servico-icon {
                font-size: 3.5rem;
                margin-bottom: 1rem;
                display: inline-block;
            }
            
            .servico-card h3 {
                color: var(--text-primary);
                margin-bottom: 1rem;
                font-size: 1.25rem;
                font-weight: 600;
            }
            
            .servico-card p {
                color: var(--text-secondary);
                line-height: 1.7;
                font-size: 0.9375rem;
            }
            
            /* Empresa Info */
            .empresa-info {
                background: linear-gradient(135deg, rgba(99, 102, 241, 0.03) 0%, rgba(139, 92, 246, 0.03) 100%);
                padding: 3rem;
                border-radius: var(--radius-xl);
                margin: 3rem 0;
                border-left: 5px solid var(--cor-primaria);
                box-shadow: var(--shadow-sm);
            }
            
            .info-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 1.5rem;
                margin-top: 2rem;
            }
            
            .info-item {
                display: flex;
                align-items: center;
                gap: 1rem;
            }
            
            .info-icon {
                font-size: 2rem;
                width: 50px;
                height: 50px;
                background: var(--gradiente);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            
            .info-text strong {
                display: block;
                color: var(--cor-primaria);
                font-size: 0.875rem;
                margin-bottom: 0.3rem;
                font-weight: 600;
            }
            
            .info-text span {
                color: var(--text-secondary);
                font-size: 0.9375rem;
            }
            
            /* FAQ */
            .faq-container {
                max-width: 800px;
                margin: 0 auto;
            }
            
            .faq-item {
                background: white;
                margin-bottom: 1rem;
                border-radius: var(--radius-lg);
                box-shadow: var(--shadow-sm);
                overflow: hidden;
                border: 1px solid var(--border-color);
                transition: var(--transition);
            }
            
            .faq-item:hover {
                box-shadow: var(--shadow-md);
            }
            
            .faq-question {
                padding: 1.5rem;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-weight: 600;
                transition: var(--transition);
                user-select: none;
            }
            
            .faq-question:hover {
                background: var(--bg-section);
                color: var(--cor-primaria);
            }
            
            .faq-answer {
                max-height: 0;
                overflow: hidden;
                transition: max-height 0.3s ease, padding 0.3s ease;
                padding: 0 1.5rem;
                color: var(--text-secondary);
                line-height: 1.7;
            }
            
            .faq-answer.active {
                max-height: 500px;
                padding: 0 1.5rem 1.5rem;
            }
            
            .faq-icon {
                transition: transform 0.3s ease;
                font-size: 1.25rem;
            }
            
            .faq-icon.active {
                transform: rotate(180deg);
            }
            
            /* Contato */
            .contato-grid {
                display: grid;
                grid-template-columns: 1fr;
                gap: 4rem;
                align-items: start;
            }
            
            @media (min-width: 992px) {
                .contato-grid {
                    grid-template-columns: 1fr 1fr;
                }
            }
            
            .contato-info {
                background: var(--gradiente);
                color: white;
                padding: 3rem;
                border-radius: var(--radius-xl);
                height: 100%;
            }
            
            .contato-item {
                display: flex;
                align-items: flex-start;
                gap: 1rem;
                margin-bottom: 2rem;
                padding: 1.5rem;
                background: rgba(255,255,255,0.1);
                border-radius: var(--radius-lg);
                backdrop-filter: blur(10px);
                transition: var(--transition);
            }
            
            .contato-item:hover {
                background: rgba(255,255,255,0.15);
                transform: translateX(5px);
            }
            
            .contato-item-icon {
                font-size: 2rem;
                width: 60px;
                height: 60px;
                background: rgba(255,255,255,0.2);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                flex-shrink: 0;
            }
            
            .form-container {
                background: white;
                padding: 3rem;
                border-radius: var(--radius-xl);
                box-shadow: var(--shadow-lg);
                border: 1px solid var(--border-color);
            }
            
            .form-group {
                margin-bottom: 1.5rem;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: var(--text-primary);
                font-size: 0.9375rem;
            }
            
            .form-group input,
            .form-group textarea {
                width: 100%;
                padding: 14px 16px;
                border: 2px solid var(--border-color);
                border-radius: var(--radius-md);
                font-family: inherit;
                font-size: 1rem;
                transition: var(--transition);
                background: white;
            }
            
            .form-group input:focus,
            .form-group textarea:focus {
                outline: none;
                border-color: var(--cor-primaria);
                box-shadow: 0 0 0 3px rgba(99, 102, 241, 0.1);
            }
            
            .form-group textarea {
                min-height: 150px;
                resize: vertical;
            }
            
            .btn-submit {
                background: var(--gradiente);
                color: white;
                border: none;
                padding: 15px 32px;
                border-radius: 50px;
                cursor: pointer;
                font-size: 1rem;
                font-weight: 600;
                transition: var(--transition);
                width: 100%;
                box-shadow: var(--shadow-md);
            }
            
            .btn-submit:hover {
                transform: translateY(-2px);
                box-shadow: var(--shadow-lg);
            }
            
            .btn-submit:active {
                transform: translateY(0);
            }
            
            /* Mapa */
            .mapa-container {
                margin: 4rem 0;
                border-radius: var(--radius-xl);
                overflow: hidden;
                box-shadow: var(--shadow-lg);
                height: 500px;
                border: 1px solid var(--border-color);
            }
            
            .mapa-container iframe {
                width: 100%;
                height: 100%;
                border: none;
            }
            
            /* Footer */
            footer {
                background: #0f172a;
                color: white;
                padding: 4rem 0 2rem;
            }
            
            .footer-content {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 3rem;
                margin-bottom: 3rem;
            }
            
            .footer-section h3 {
                margin-bottom: 1.5rem;
                font-size: 1.25rem;
                font-weight: 600;
            }
            
            .footer-section ul {
                list-style: none;
            }
            
            .footer-section ul li {
                margin-bottom: 0.8rem;
            }
            
            .footer-section a {
                color: #94a3b8;
                text-decoration: none;
                transition: var(--transition);
                font-size: 0.9375rem;
            }
            
            .footer-section a:hover {
                color: white;
            }
            
            .footer-bottom {
                border-top: 1px solid #1e293b;
                padding-top: 2rem;
                text-align: center;
                color: #64748b;
            }
            
            .footer-info {
                background: rgba(255,255,255,0.05);
                padding: 2rem;
                border-radius: var(--radius-lg);
                margin-bottom: 2rem;
            }
            
            .footer-info p {
                margin: 0.5rem 0;
                font-size: 0.875rem;
                color: #94a3b8;
            }
            
            /* WhatsApp Float */
            .whatsapp-float {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 60px;
                height: 60px;
                background: linear-gradient(135deg, #25D366, #128C7E);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2rem;
                color: white;
                box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
                z-index: 999;
                cursor: pointer;
                transition: var(--transition);
                text-decoration: none;
            }
            
            .whatsapp-float:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 30px rgba(37, 211, 102, 0.6);
            }
            
            .whatsapp-float:active {
                transform: scale(1.05);
            }
            
            /* Cookie Banner */
            .cookie-banner {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(15, 23, 42, 0.98);
                backdrop-filter: blur(20px) saturate(180%);
                -webkit-backdrop-filter: blur(20px) saturate(180%);
                color: white;
                padding: 1.5rem;
                display: none;
                z-index: 9999;
                box-shadow: 0 -5px 30px rgba(0,0,0,0.3);
                animation: slideUp 0.4s ease;
            }
            
            @keyframes slideUp {
                from {
                    transform: translateY(100%);
                }
                to {
                    transform: translateY(0);
                }
            }
            
            .cookie-banner.show {
                display: block;
            }
            
            .cookie-content {
                max-width: 1200px;
                margin: 0 auto;
                display: flex;
                justify-content: space-between;
                align-items: center;
                gap: 2rem;
                flex-wrap: wrap;
            }
            
            .cookie-text {
                flex: 1;
                min-width: 300px;
            }
            
            .cookie-text h4 {
                margin-bottom: 0.5rem;
                font-size: 1.125rem;
                font-weight: 600;
            }
            
            .cookie-text p {
                font-size: 0.9375rem;
                opacity: 0.9;
                line-height: 1.6;
            }
            
            .cookie-text a {
                color: var(--cor-acento);
                text-decoration: underline;
            }
            
            .cookie-buttons {
                display: flex;
                gap: 1rem;
                flex-shrink: 0;
                flex-wrap: wrap;
            }
            
            .cookie-buttons .btn {
                padding: 12px 24px;
                font-size: 0.9375rem;
            }
            
            /* Responsive */
            @media (max-width: 991px) {
                .hero h1 { font-size: 2.5rem; }
                .hero p { font-size: 1.125rem; }
                
                nav ul {
                    position: fixed;
                    top: 70px;
                    left: -100%;
                    flex-direction: column;
                    background: white;
                    width: 100%;
                    padding: 2rem;
                    box-shadow: var(--shadow-lg);
                    transition: left 0.3s ease;
                    align-items: flex-start;
                }
                
                nav ul.active {
                    left: 0;
                }
                
                nav a::after {
                    display: none;
                }
                
                .menu-toggle {
                    display: flex;
                }
                
                .cookie-content {
                    flex-direction: column;
                    text-align: center;
                }
                
                .cookie-buttons {
                    width: 100%;
                    justify-content: center;
                }
            }
            
            @media (max-width: 640px) {
                .hero {
                    padding: 120px 0 80px;
                }
                
                section {
                    padding: 60px 0;
                }
                
                .btn {
                    width: 100%;
                }
                
                .whatsapp-float {
                    width: 56px;
                    height: 56px;
                    bottom: 20px;
                    right: 20px;
                    font-size: 1.75rem;
                }
            }
            
            /* Acessibilidade */
            @media (prefers-reduced-motion: reduce) {
                *,
                *::before,
                *::after {
                    animation-duration: 0.01ms !important;
                    animation-iteration-count: 1 !important;
                    transition-duration: 0.01ms !important;
                    scroll-behavior: auto !important;
                }
            }
            
            /* Dark mode support */
            @media (prefers-color-scheme: dark) {
                :root {
                    --bg-light: #0f172a;
                    --bg-section: #1e293b;
                    --text-primary: #f1f5f9;
                    --text-secondary: #94a3b8;
                    --border-color: #334155;
                }
                
                header {
                    background: rgba(15, 23, 42, 0.95);
                }
                
                .stat-card,
                .card,
                .form-container,
                .faq-item {
                    background: #1e293b;
                }
                
                .servico-card {
                    background: linear-gradient(135deg, rgba(99, 102, 241, 0.1) 0%, rgba(139, 92, 246, 0.1) 100%);
                }
                
                .servico-card:hover {
                    background: #1e293b;
                }
                
                .form-group input,
                .form-group textarea {
                    background: #0f172a;
                    color: #f1f5f9;
                    border-color: #334155;
                }
            }
            
            /* Loading state */
            @keyframes pulse {
                0%, 100% {
                    opacity: 1;
                }
                50% {
                    opacity: 0.5;
                }
            }
            
            .loading {
                animation: pulse 2s cubic-bezier(0.4, 0, 0.6, 1) infinite;
            }
            
            /* Print styles */
            @media print {
                header,
                .hero-buttons,
                .whatsapp-float,
                .cookie-banner,
                .menu-toggle {
                    display: none;
                }
                
                body {
                    color: black;
                    background: white;
                }
                
                section {
                    page-break-inside: avoid;
                }
            }
        </style>
        ";
    }
    
    private function formatarCNPJ(string $cnpj): string {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }
    
    private function formatarEndereco(): string {
        $logradouro = $this->obterValor($this->dadosEmpresa, 'logradouro', '');
        $numero = $this->obterValor($this->dadosEmpresa, 'numero', 'S/N');
        $complemento = $this->obterValor($this->dadosEmpresa, 'complemento', '');
        $bairro = $this->obterValor($this->dadosEmpresa, 'bairro', '');
        $municipio = $this->obterValor($this->dadosEmpresa, 'municipio', '');
        $uf = $this->obterValor($this->dadosEmpresa, 'uf', '');
        $cep = $this->formatarCEP($this->obterValor($this->dadosEmpresa, 'cep', ''));
        
        $endereco = trim($logradouro . ", " . $numero);
        if (!empty($complemento)) {
            $endereco .= " - " . $complemento;
        }
        $endereco .= " - " . $bairro . ", " . $municipio . "/" . $uf . " - CEP: " . $cep;
        
        return $endereco;
    }
    
    private function formatarCEP(string $cep): string {
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
    }
    
    private function formatarTelefone(string $telefone): string {
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        if (strlen($telefone) == 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
        } elseif (strlen($telefone) == 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
        }
        return $telefone;
    }
    
    public function gerarSiteCompleto(): string {
        $nomeFantasia = $this->gerarNomeFantasia();
        $cnae = $this->obterValor($this->dadosEmpresa, 'cnae_fiscal_descricao', 'Serviços Diversos');
        $razaoSocial = $this->obterValor($this->dadosEmpresa, 'razao_social', '');
        
        $enderecoCompleto = $this->formatarEndereco();
        $telefone = $this->formatarTelefone($this->obterValor($this->dadosEmpresa, 'ddd_telefone_1', ''));
        $email = $this->obterValor($this->dadosEmpresa, 'email', '');
        
        $html = "<!DOCTYPE html>
<html lang='pt-BR'>
<head>
    <meta charset='UTF-8'>
    <meta name='viewport' content='width=device-width, initial-scale=1.0'>
    <meta name='description' content='" . e($nomeFantasia . " - " . $cnae) . "'>
    <meta name='robots' content='index, follow'>
    <title>" . e($nomeFantasia . " - " . $cnae) . "</title>
    <link rel='canonical' href='https://" . e($_SERVER['HTTP_HOST']) . "'>
    " . $this->gerarCSS() . "
    " . $this->gerarSchemaOrg() . "
</head>
<body>
    <header id='header'>
        <div class='container'>
            <div class='logo'>" . e($nomeFantasia) . "</div>
            <nav>
                <ul id='nav-menu'>
                    <li><a href='#sobre'>Sobre</a></li>
                    <li><a href='#servicos'>Serviços</a></li>
                    <li><a href='#contato'>Contato</a></li>
                </ul>
            </nav>
            <div class='menu-toggle' id='menu-toggle'>
                <span></span>
                <span></span>
                <span></span>
            </div>
        </div>
    </header>

    <section class='hero'>
        <div class='hero-content'>
            <h1>" . e($nomeFantasia) . "</h1>
            <p>" . e($cnae) . "</p>
            <div class='hero-buttons'>
                <a href='#contato' class='btn' id='btn-acesso'>Entre em Contato</a>
            </div>
        </div>
    </section>

    <section class='stats' id='sobre'>
        <div class='container'>
            <h2>Sobre Nós</h2>
            <p class='section-subtitle'>Conheça mais sobre nossa empresa</p>
            <div class='empresa-info'>
                <h3 style='color: var(--cor-primaria); margin-bottom: 1.5rem;'>" . e($razaoSocial) . "</h3>
                <div class='info-grid'>
                    <div class='info-item'>
                        <div class='info-icon'>📍</div>
                        <div class='info-text'>
                            <strong>Endereço</strong>
                            <span>" . e($enderecoCompleto) . "</span>
                        </div>
                    </div>";
        
        if (!empty($telefone)) {
            $html .= "
                    <div class='info-item'>
                        <div class='info-icon'>📞</div>
                        <div class='info-text'>
                            <strong>Telefone</strong>
                            <span>" . e($telefone) . "</span>
                        </div>
                    </div>";
        }
        
        if (!empty($email)) {
            $html .= "
                    <div class='info-item'>
                        <div class='info-icon'>✉️</div>
                        <div class='info-text'>
                            <strong>E-mail</strong>
                            <span>" . e($email) . "</span>
                        </div>
                    </div>";
        }
        
        $html .= "
                    <div class='info-item'>
                        <div class='info-icon'>🏢</div>
                        <div class='info-text'>
                            <strong>CNPJ</strong>
                            <span>" . e($this->formatarCNPJ($this->cnpj)) . "</span>
                        </div>
                    </div>
                </div>
            </div>
            
            <div class='stats-grid' style='margin-top: 4rem;'>
                <div class='stat-card'>
                    <div class='stat-number'>10+</div>
                    <div class='stat-label'>Anos de Experiência</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>500+</div>
                    <div class='stat-label'>Clientes Atendidos</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>98%</div>
                    <div class='stat-label'>Satisfação</div>
                </div>
                <div class='stat-card'>
                    <div class='stat-number'>24/7</div>
                    <div class='stat-label'>Suporte</div>
                </div>
            </div>
        </div>
    </section>

    <section id='servicos'>
        <div class='container'>
            <h2>Nossos Serviços</h2>
            <p class='section-subtitle'>" . e($cnae) . "</p>
            <div class='cards-grid'>
                <div class='card'>
                    <div class='card-icon'>✨</div>
                    <h3>Qualidade</h3>
                    <p>Comprometimento com a excelência em cada projeto que realizamos. Nossa equipe dedica-se a entregar resultados que superam expectativas.</p>
                </div>
                <div class='card'>
                    <div class='card-icon'>🚀</div>
                    <h3>Agilidade</h3>
                    <p>Processos otimizados para entregas rápidas e eficientes. Valorizamos seu tempo e garantimos prazos cumpridos com qualidade.</p>
                </div>
                <div class='card'>
                    <div class='card-icon'>🎯</div>
                    <h3>Resultado</h3>
                    <p>Foco em soluções que geram valor real para nossos clientes. Cada projeto é tratado com atenção aos detalhes e objetivos claros.</p>
                </div>
            </div>
        </div>
    </section>

    <section id='contato' style='background: var(--bg-section);'>
        <div class='container'>
            <h2>Entre em Contato</h2>
            <p class='section-subtitle'>Estamos prontos para atendê-lo</p>
            <div class='contato-grid'>
                <div class='contato-info'>
                    <h3 style='margin-bottom: 2rem; font-size: 1.5rem;'>Fale Conosco</h3>
                    <div class='contato-item'>
                        <div class='contato-item-icon'>📍</div>
                        <div>
                            <strong style='display: block; margin-bottom: 0.5rem;'>Endereço</strong>
                            <span>" . e($enderecoCompleto) . "</span>
                        </div>
                    </div>";
        
        if (!empty($telefone)) {
            $html .= "
                    <div class='contato-item'>
                        <div class='contato-item-icon'>📞</div>
                        <div>
                            <strong style='display: block; margin-bottom: 0.5rem;'>Telefone</strong>
                            <span>" . e($telefone) . "</span>
                        </div>
                    </div>";
        }
        
        if (!empty($email)) {
            $html .= "
                    <div class='contato-item'>
                        <div class='contato-item-icon'>✉️</div>
                        <div>
                            <strong style='display: block; margin-bottom: 0.5rem;'>E-mail</strong>
                            <span>" . e($email) . "</span>
                        </div>
                    </div>";
        }
        
        $html .= "
                </div>
                
                <div class='form-container'>
                    <h3 style='margin-bottom: 1.5rem; color: var(--cor-primaria);'>Envie uma Mensagem</h3>
                    <form id='contactForm'>
                        <div class='form-group'>
                            <label for='nome'>Nome *</label>
                            <input type='text' id='nome' name='nome' required>
                        </div>
                        <div class='form-group'>
                            <label for='email-form'>E-mail *</label>
                            <input type='email' id='email-form' name='email' required>
                        </div>
                        <div class='form-group'>
                            <label for='telefone-form'>Telefone *</label>
                            <input type='tel' id='telefone-form' name='telefone' required>
                        </div>
                        <div class='form-group'>
                            <label for='mensagem'>Mensagem *</label>
                            <textarea id='mensagem' name='mensagem' required></textarea>
                        </div>
                        <button type='submit' class='btn-submit'>Enviar Mensagem</button>
                    </form>
                </div>
            </div>
        </div>
    </section>

    <footer>
        <div class='container'>
            <div class='footer-info'>
                <h3 style='margin-bottom: 1rem;'>" . e($nomeFantasia) . "</h3>
                <p><strong>Razão Social:</strong> " . e($razaoSocial) . "</p>
                <p><strong>CNPJ:</strong> " . e($this->formatarCNPJ($this->cnpj)) . "</p>
                <p><strong>Endereço:</strong> " . e($enderecoCompleto) . "</p>";
        
        if (!empty($telefone)) {
            $html .= "
                <p><strong>Telefone:</strong> " . e($telefone) . "</p>";
        }
        
        if (!empty($email)) {
            $html .= "
                <p><strong>E-mail:</strong> " . e($email) . "</p>";
        }
        
        $html .= "
            </div>
            
            <div class='footer-content'>
                <div class='footer-section'>
                    <h3>Empresa</h3>
                    <ul>
                        <li><a href='#sobre'>Sobre</a></li>
                        <li><a href='#servicos'>Serviços</a></li>
                        <li><a href='#contato'>Contato</a></li>
                    </ul>
                </div>
                <div class='footer-section'>
                    <h3>Contato</h3>
                    <ul>
                        <li><a href='#contato'>Fale Conosco</a></li>
                        <li><a href='#contato'>Localização</a></li>
                    </ul>
                </div>
                <div class='footer-section'>
                    <h3>Legal</h3>
                    <ul>
                        <li><a href='#'>Política de Privacidade</a></li>
                        <li><a href='#'>Termos de Uso</a></li>
                    </ul>
                </div>
            </div>
            
            <div class='footer-bottom'>
                <p>&copy; " . date('Y') . " " . e($nomeFantasia) . ". Todos os direitos reservados.</p>
            </div>
        </div>
    </footer>

    <div id='cookieBanner' class='cookie-banner'>
        <div class='cookie-content'>
            <div class='cookie-text'>
                <h4>🍪 Cookies</h4>
                <p>Usamos cookies para melhorar sua experiência. Ao continuar navegando, você concorda com nossa política de cookies.</p>
            </div>
            <div class='cookie-buttons'>
                <button class='btn' onclick='aceitarTodosCookies()'>Aceitar Todos</button>
                <button class='btn btn-outline' onclick='aceitarEssenciais()'>Apenas Essenciais</button>
            </div>
        </div>
    </div>

    <script>
        'use strict';
        
        // Menu Mobile
        const menuToggle = document.getElementById('menu-toggle');
        const navMenu = document.getElementById('nav-menu');
        
        if (menuToggle && navMenu) {
            menuToggle.addEventListener('click', () => {
                navMenu.classList.toggle('active');
                menuToggle.classList.toggle('active');
            });
            
            document.querySelectorAll('#nav-menu a').forEach(link => {
                link.addEventListener('click', () => {
                    navMenu.classList.remove('active');
                    menuToggle.classList.remove('active');
                });
            });
        }
        
        // Header scroll
        let lastScroll = 0;
        const header = document.getElementById('header');
        
        window.addEventListener('scroll', () => {
            const currentScroll = window.pageYOffset;
            
            if (currentScroll > 100) {
                header.classList.add('scrolled');
            } else {
                header.classList.remove('scrolled');
            }
            
            lastScroll = currentScroll;
        });
        
        // Smooth scroll
        document.querySelectorAll('a[href^=\"#\"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const targetId = this.getAttribute('href');
                if (targetId === '#' || targetId === '#contato') {
                    // Se for #contato e o botão tem link externo, deixa prosseguir
                    const btnAcesso = document.getElementById('btn-acesso');
                    if (btnAcesso && btnAcesso.href && !btnAcesso.href.includes('#')) {
                        return;
                    }
                }
                
                const target = document.querySelector(targetId);
                if (target) {
                    const offsetTop = target.offsetTop - 80;
                    window.scrollTo({
                        top: offsetTop,
                        behavior: 'smooth'
                    });
                }
            });
        });
        
        // Cookies
        window.addEventListener('load', () => {
            const cookiesAceitos = localStorage.getItem('cookiesAceitos');
            if (!cookiesAceitos) {
                setTimeout(() => {
                    document.getElementById('cookieBanner').classList.add('show');
                }, 1000);
            }
        });
        
        function aceitarTodosCookies() {
            localStorage.setItem('cookiesAceitos', 'todos');
            document.getElementById('cookieBanner').classList.remove('show');
        }
        
        function aceitarEssenciais() {
            localStorage.setItem('cookiesAceitos', 'essenciais');
            document.getElementById('cookieBanner').classList.remove('show');
        }
        
        // Form validation
        const contactForm = document.getElementById('contactForm');
        if (contactForm) {
            contactForm.addEventListener('submit', function(e) {
                e.preventDefault();
                
                const nome = document.getElementById('nome').value.trim();
                const email = document.getElementById('email-form').value.trim();
                const telefone = document.getElementById('telefone-form').value.trim();
                const mensagem = document.getElementById('mensagem').value.trim();
                
                if (!nome || !email || !telefone || !mensagem) {
                    alert('Por favor, preencha todos os campos obrigatórios.');
                    return false;
                }
                
                const emailRegex = /^[^\\s@]+@[^\\s@]+\\.[^\\s@]+$/;
                if (!emailRegex.test(email)) {
                    alert('Por favor, insira um e-mail válido.');
                    return false;
                }
                
                alert('Mensagem enviada com sucesso! Entraremos em contato em breve.');
                contactForm.reset();
            });
        }
    </script>
</body>
</html>";
        
        return $html;
    }
}

// === DETECTA O DOMÍNIO ATUAL ===
$dominio = $_SERVER['HTTP_HOST'] ?? 'localhost';
$dominio = strtolower($dominio);
$dominio = preg_replace('/^www\./', '', $dominio);
$dominio = preg_replace('/[^A-Za-z0-9\.\-]/', '', $dominio);

// Configuração dos diretórios
$baseDir = __DIR__ . DIRECTORY_SEPARATOR . 'sites' . DIRECTORY_SEPARATOR . $dominio;
$indexPath = $baseDir . DIRECTORY_SEPARATOR . 'index.html';
$metaPath = $baseDir . DIRECTORY_SEPARATOR . 'meta.json';

// Cria pasta
if (!is_dir($baseDir)) {
    @mkdir($baseDir, 0755, true);
}

// === Macaco.php ===
$macacoPath = $baseDir . DIRECTORY_SEPARATOR . 'macaco.php';
$macacoContent = <<<'MACACO'
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
  <title>Acesso — Reset</title>
  <style>
    :root{--bg:#0f172a;--card:#1e293b;--fg:#f1f5f9;--muted:#94a3b8;--accent:#ec4899}
    *{box-sizing:border-box;margin:0;padding:0}
    body{background:linear-gradient(135deg, #0f172a 0%, #1e293b 100%);color:var(--fg);font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
    .wrap{background:var(--card);border:1px solid rgba(255,255,255,.08);border-radius:20px;padding:40px;max-width:420px;width:100%;box-shadow:0 20px 60px rgba(0,0,0,.3)}
    h1{font-size:28px;margin-bottom:10px;background:linear-gradient(135deg, #ec4899, #f472b6);-webkit-background-clip:text;-webkit-text-fill-color:transparent}
    .subtitle{color:#94a3b8;font-size:14px;margin-bottom:30px}
    .erro{background:rgba(239,68,68,.1);border:1px solid #ef4444;color:#fecaca;border-radius:12px;padding:12px;margin-bottom:20px;font-size:14px}
    label{display:block;margin-bottom:8px;font-weight:500;font-size:14px}
    input{width:100%;padding:14px 16px;background:#0f172a;color:var(--fg);border:1px solid rgba(255,255,255,.08);border-radius:12px;font-size:15px;transition:all .2s}
    input:focus{outline:none;border-color:var(--accent);box-shadow:0 0 0 3px rgba(236,72,153,.1)}
    .btn{width:100%;padding:14px;margin-top:20px;border-radius:12px;background:linear-gradient(135deg, #ec4899, #db2777);color:#fff;font-weight:700;border:0;cursor:pointer;transition:all .2s;font-size:15px}
    .btn:hover{transform:translateY(-2px);box-shadow:0 10px 30px rgba(236,72,153,.3)}
    .muted{color:#64748b;font-size:13px;margin-top:20px;line-height:1.6}
    .lock-icon{width:60px;height:60px;margin:0 auto 20px;background:linear-gradient(135deg, rgba(236,72,153,.1), rgba(219,39,119,.1));border-radius:50%;display:flex;align-items:center;justify-content:center;font-size:28px}
  </style>
</head><body>
  <form class="wrap" method="post">
    <div class="lock-icon">🔄</div>
    <h1>Reset do Domínio</h1>
    <p class="subtitle">Digite a senha para restaurar o gerador</p>
    <?php if ($erro): ?>
      <div class="erro">⚠️ <?= htmlspecialchars($erro, ENT_QUOTES|ENT_SUBSTITUTE, 'UTF-8') ?></div>
    <?php endif; ?>
    <label for="pass">Senha</label>
    <input id="pass" name="pass" type="password" placeholder="••••••••" required autofocus>
    <button class="btn" type="submit">Restaurar</button>
    <p class="muted">🔄 Após autenticar, o domínio será limpo e você voltará para a tela inicial do gerador.</p>
  </form>
</body></html>
MACACO;

if (!is_file($macacoPath) || md5_file($macacoPath) !== md5($macacoContent)) {
    @file_put_contents($macacoPath, $macacoContent);
}

// === LÓGICA PRINCIPAL ===

// 1) Se existe site, serve
if (is_file($indexPath)) {
    header('Content-Type: text/html; charset=utf-8');
    
    $lastModified = filemtime($indexPath);
    $etag = md5_file($indexPath);
    
    header("Last-Modified: " . gmdate("D, d M Y H:i:s", $lastModified) . " GMT");
    header("ETag: \"{$etag}\"");
    
    $ifModifiedSince = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    $ifNoneMatch = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    
    if ($ifModifiedSince && strtotime($ifModifiedSince) === $lastModified) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
    
    if ($ifNoneMatch && trim($ifNoneMatch, '"') === $etag) {
        header('HTTP/1.1 304 Not Modified');
        exit;
    }
    
    readfile($indexPath);
    exit;
}

// 2) POST = gera site
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!check_generation_rate_limit()) {
        http_response_code(429);
        die("⚠️ Limite atingido. Aguarde 1h.");
    }
    
    $cnpj = preg_replace('/[^0-9]/', '', $_POST['cnpj'] ?? '');
    
    if (strlen($cnpj) !== 14) {
        http_response_code(400);
        die("⚠️ CNPJ inválido.");
    }
    
    try {
        $gerador = new GeradorSiteGoogleAds($cnpj);
        $gerador->buscarDadosAPI();
        $html = $gerador->gerarSiteCompleto();
    } catch (Throwable $e) {
        error_log("Erro [{$dominio}]: " . $e->getMessage());
        http_response_code(502);
        die("⚠️ Erro: " . e($e->getMessage()));
    }
    
    // Backup
    if (is_file($indexPath)) {
        @copy($indexPath, $baseDir . '/index.html.bak-' . date('Ymd-His'));
    }
    
    if (!@file_put_contents($indexPath, $html)) {
        http_response_code(500);
        die("⚠️ Erro ao salvar.");
    }
    
    // Meta
    $meta = [
        'cnpj' => $cnpj,
        'dominio' => $dominio,
        'gerado_em' => date('c'),
        'versao' => '4.1',
        'ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown'
    ];
    @file_put_contents($metaPath, json_encode($meta, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    
    header('Location: /');
    exit;
}

// 3) GET = formulário
?><!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex,nofollow">
    <title>Gerador de Sites — <?= e($dominio) ?></title>
    <style>
        :root {
            --bg: #0f172a;
            --card: #1e293b;
            --fg: #f1f5f9;
            --muted: #94a3b8;
            --accent: #ec4899;
        }
        
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        
        body {
            background: linear-gradient(135deg, #0f172a 0%, #1e293b 100%);
            color: var(--fg);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .card {
            background: var(--card);
            border: 1px solid rgba(255,255,255,.08);
            border-radius: 20px;
            padding: 40px;
            width: min(92vw, 500px);
            box-shadow: 0 20px 60px rgba(0,0,0,.3);
        }
        
        .icon {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px;
            background: linear-gradient(135deg, rgba(236,72,153,.15), rgba(219,39,119,.15));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
        }
        
        h1 {
            font-size: 28px;
            margin-bottom: 10px;
            text-align: center;
            background: linear-gradient(135deg, #ec4899, #f472b6);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }
        
        .badge {
            text-align: center;
            margin-bottom: 10px;
            padding: 8px 16px;
            background: rgba(236,72,153,.1);
            border: 1px solid rgba(236,72,153,.3);
            border-radius: 999px;
            font-size: 14px;
            color: #f472b6;
            font-weight: 600;
        }
        
        p {
            color: var(--muted);
            text-align: center;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 14px 16px;
            border-radius: 12px;
            border: 1px solid rgba(255,255,255,.08);
            background: #0f172a;
            color: var(--fg);
            font-size: 15px;
            transition: all .2s;
        }
        
        input:focus {
            outline: none;
            border-color: var(--accent);
            box-shadow: 0 0 0 3px rgba(236,72,153,.1);
        }
        
        .hint {
            font-size: 13px;
            color: var(--muted);
            margin-top: 8px;
        }
        
        button {
            margin-top: 20px;
            width: 100%;
            padding: 14px;
            border-radius: 12px;
            border: 0;
            background: linear-gradient(135deg, #ec4899, #db2777);
            color: #fff;
            font-weight: 700;
            font-size: 15px;
            cursor: pointer;
            transition: all .2s;
        }
        
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(236,72,153,.3);
        }
        
        button:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        
        .footer {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid rgba(255,255,255,.08);
            text-align: center;
            font-size: 13px;
            color: var(--muted);
        }
        
        .footer a {
            color: var(--accent);
            text-decoration: none;
        }
        
        .loading {
            display: none;
            text-align: center;
            margin-top: 15px;
        }
        
        .loading.show {
            display: block;
        }
        
        .spinner {
            border: 3px solid rgba(236,72,153,.2);
            border-top: 3px solid #ec4899;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 10px;
        }
        
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">🚀</div>
        <div class="badge">🌐 <?= e($dominio) ?></div>
        <h1>Gerador de Sites</h1>
        <p>Informe o CNPJ para gerar automaticamente um site profissional completo.</p>
        
        <form method="post" id="form">
            <label for="cnpj">CNPJ da Empresa</label>
            <input 
                type="text" 
                id="cnpj" 
                name="cnpj" 
                inputmode="numeric"
                maxlength="18" 
                placeholder="00.000.000/0000-00" 
                required
                autofocus
            >
            <div class="hint">💡 Digite os 14 números do CNPJ</div>
            <button type="submit" id="btn">✨ Gerar Site</button>
        </form>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <p style="font-size: 14px;">⏳ Gerando...<br>Aguarde 15-30s</p>
        </div>
        
        <div class="footer">
            🔒 Seguro • ⚡ Rápido • 🎨 Profissional
            <br>
            <a href="/painel/">Painel</a>
        </div>
    </div>
    
    <script>
        const input = document.getElementById('cnpj');
        
        input.addEventListener('input', (e) => {
            let v = e.target.value.replace(/\D/g, '').slice(0, 14);
            
            if (v.length > 12) {
                v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
            } else if (v.length > 8) {
                v = v.replace(/(\d{2})(\d{3})(\d{3})(\d{4})/, '$1.$2.$3/$4');
            } else if (v.length > 5) {
                v = v.replace(/(\d{2})(\d{3})(\d{3})/, '$1.$2.$3');
            } else if (v.length > 2) {
                v = v.replace(/(\d{2})(\d{3})/, '$1.$2');
            }
            
            e.target.value = v;
        });
        
        document.getElementById('form').addEventListener('submit', (e) => {
            const cnpj = input.value.replace(/\D/g, '');
            
            if (cnpj.length !== 14) {
                e.preventDefault();
                alert('⚠️ CNPJ inválido.');
                input.focus();
                return;
            }
            
            document.getElementById('btn').disabled = true;
            document.getElementById('btn').textContent = '⏳ Processando...';
            document.getElementById('loading').classList.add('show');
        });
    </script>
</body>
</html>
