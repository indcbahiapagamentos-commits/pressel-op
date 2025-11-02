<?php
class GeradorSiteGoogleAds {
    private $cnpj;
    private $dadosEmpresa;
    private $coresNicho;
    
    public function __construct($cnpj) {
        $this->cnpj = $this->limparCNPJ($cnpj);
        $this->definirCoresNicho();
    }
    
    private function limparCNPJ($cnpj) {
        return preg_replace('/[^0-9]/', '', $cnpj);
    }
    
    private function obterValor($array, $chave, $padrao = '') {
        return isset($array[$chave]) ? $array[$chave] : $padrao;
    }
    
    public function buscarDadosAPI() {
        $url = "https://minhareceita.org/{$this->cnpj}";
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode != 200) {
            throw new Exception("Erro ao buscar dados da API. Código: " . $httpCode);
        }
        
        $this->dadosEmpresa = json_decode($response, true);
        
        if (!$this->dadosEmpresa) {
            throw new Exception("Erro ao decodificar resposta da API");
        }
        
        return $this->dadosEmpresa;
    }
    
    private function definirCoresNicho() {
        $this->coresNicho = array(
            'tecnologia' => array(
                'primaria' => '#6366f1',
                'secundaria' => '#4f46e5',
                'acento' => '#8b5cf6',
                'gradiente' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'icone' => '💻'
            ),
            'saude' => array(
                'primaria' => '#10b981',
                'secundaria' => '#059669',
                'acento' => '#34d399',
                'gradiente' => 'linear-gradient(135deg, #0ba360 0%, #3cba92 100%)',
                'icone' => '🏥'
            ),
            'comercio' => array(
                'primaria' => '#f59e0b',
                'secundaria' => '#d97706',
                'acento' => '#fbbf24',
                'gradiente' => 'linear-gradient(135deg, #f093fb 0%, #f5576c 100%)',
                'icone' => '🛒'
            ),
            'servicos' => array(
                'primaria' => '#06b6d4',
                'secundaria' => '#0891b2',
                'acento' => '#22d3ee',
                'gradiente' => 'linear-gradient(135deg, #4facfe 0%, #00f2fe 100%)',
                'icone' => '🔧'
            ),
            'industria' => array(
                'primaria' => '#64748b',
                'secundaria' => '#475569',
                'acento' => '#94a3b8',
                'gradiente' => 'linear-gradient(135deg, #434343 0%, #000000 100%)',
                'icone' => '🏭'
            ),
            'educacao' => array(
                'primaria' => '#8b5cf6',
                'secundaria' => '#7c3aed',
                'acento' => '#a78bfa',
                'gradiente' => 'linear-gradient(135deg, #a8edea 0%, #fed6e3 100%)',
                'icone' => '📚'
            ),
            'default' => array(
                'primaria' => '#3b82f6',
                'secundaria' => '#2563eb',
                'acento' => '#60a5fa',
                'gradiente' => 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)',
                'icone' => '🏢'
            )
        );
    }
    
    private function identificarNicho() {
        $cnae = strtolower($this->obterValor($this->dadosEmpresa, 'cnae_fiscal_descricao', ''));
        
        $nichos = array(
            'tecnologia' => array('software', 'tecnologia', 'informática', 'internet', 'desenvolvimento', 'programação', 'sistemas', 'web', 'aplicativos'),
            'saude' => array('saúde', 'médica', 'médico', 'hospital', 'clínica', 'farmácia', 'laboratório', 'odontológica', 'fisioterapia'),
            'comercio' => array('comércio', 'varejo', 'loja', 'venda', 'mercado', 'supermercado', 'magazine'),
            'servicos' => array('serviços', 'consultoria', 'assessoria', 'manutenção', 'limpeza', 'segurança'),
            'industria' => array('indústria', 'fabricação', 'manufatura', 'produção', 'industrial'),
            'educacao' => array('educação', 'ensino', 'escola', 'curso', 'treinamento', 'capacitação')
        );
        
        foreach ($nichos as $nicho => $palavras) {
            foreach ($palavras as $palavra) {
                if (stripos($cnae, $palavra) !== false) {
                    return $nicho;
                }
            }
        }
        
        return 'default';
    }
    
    private function getCores() {
        $nicho = $this->identificarNicho();
        return $this->coresNicho[$nicho];
    }
    
    private function gerarNomeFantasia() {
        $nomeFantasia = $this->obterValor($this->dadosEmpresa, 'nome_fantasia', '');
        if (!empty($nomeFantasia)) {
            return $nomeFantasia;
        }
        $razaoSocial = $this->obterValor($this->dadosEmpresa, 'razao_social', 'Empresa');
        return $razaoSocial;
    }
    
    private function gerarSchemaOrg() {
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
        
        $schema = array(
            "@context" => "https://schema.org",
            "@type" => "LocalBusiness",
            "name" => $nomeFantasia,
            "legalName" => $razaoSocial,
            "description" => $cnae,
            "telephone" => $telefone,
            "email" => $email,
            "address" => array(
                "@type" => "PostalAddress",
                "streetAddress" => $logradouro . ", " . $numero,
                "addressLocality" => $municipio,
                "addressRegion" => $uf,
                "postalCode" => $cep,
                "addressCountry" => "BR"
            ),
            "geo" => array(
                "@type" => "GeoCoordinates",
                "latitude" => "",
                "longitude" => ""
            ),
            "openingHoursSpecification" => array(
                "@type" => "OpeningHoursSpecification",
                "dayOfWeek" => array("Monday", "Tuesday", "Wednesday", "Thursday", "Friday"),
                "opens" => "08:00",
                "closes" => "18:00"
            )
        );
        
        return '<script type="application/ld+json">' . json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT) . '</script>';
    }
    
    public function gerarCSS() {
        $cores = $this->getCores();
        
        $css = "
        <style>
            @import url('https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap');
            
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            
            :root {
                --cor-primaria: " . $cores['primaria'] . ";
                --cor-secundaria: " . $cores['secundaria'] . ";
                --cor-acento: " . $cores['acento'] . ";
                --gradiente: " . $cores['gradiente'] . ";
            }
            
            html {
                scroll-behavior: smooth;
            }
            
            body {
                font-family: 'Poppins', sans-serif;
                line-height: 1.6;
                color: #333;
                overflow-x: hidden;
            }
            
            img {
                max-width: 100%;
                height: auto;
                loading: lazy;
            }
            
            .container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 0 20px;
            }
            
            header {
                background: rgba(255, 255, 255, 0.98);
                backdrop-filter: blur(10px);
                padding: 1rem 0;
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
                position: fixed;
                top: 0;
                left: 0;
                right: 0;
                z-index: 1000;
                transition: all 0.3s;
            }
            
            header.scrolled {
                padding: 0.5rem 0;
                box-shadow: 0 2px 30px rgba(0,0,0,0.15);
            }
            
            header .container {
                display: flex;
                justify-content: space-between;
                align-items: center;
            }
            
            .logo {
                font-size: 1.8rem;
                font-weight: 800;
                background: var(--gradiente);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            nav ul {
                list-style: none;
                display: flex;
                gap: 2.5rem;
                align-items: center;
            }
            
            nav a {
                color: #333;
                text-decoration: none;
                font-weight: 500;
                transition: all 0.3s;
                position: relative;
            }
            
            nav a::after {
                content: '';
                position: absolute;
                bottom: -5px;
                left: 0;
                width: 0;
                height: 2px;
                background: var(--cor-primaria);
                transition: width 0.3s;
            }
            
            nav a:hover::after {
                width: 100%;
            }
            
            .menu-toggle {
                display: none;
                flex-direction: column;
                cursor: pointer;
                gap: 5px;
            }
            
            .menu-toggle span {
                width: 25px;
                height: 3px;
                background: var(--cor-primaria);
                border-radius: 3px;
                transition: all 0.3s;
            }
            
            .hero {
                background: var(--gradiente);
                color: white;
                padding: 140px 0 100px;
                position: relative;
                overflow: hidden;
                margin-top: 70px;
            }
            
            .hero-content {
                position: relative;
                z-index: 2;
                text-align: center;
                max-width: 900px;
                margin: 0 auto;
            }
            
            .hero h1 {
                font-size: 3.5rem;
                font-weight: 800;
                margin-bottom: 1.5rem;
                line-height: 1.2;
            }
            
            .hero p {
                font-size: 1.3rem;
                margin-bottom: 2.5rem;
                opacity: 0.95;
            }
            
            .hero-buttons {
                display: flex;
                gap: 1rem;
                justify-content: center;
                flex-wrap: wrap;
            }
            
            .btn {
                display: inline-block;
                padding: 15px 40px;
                background: white;
                color: var(--cor-primaria);
                text-decoration: none;
                border-radius: 50px;
                font-weight: 600;
                transition: all 0.3s;
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            
            .btn:hover {
                transform: translateY(-3px);
                box-shadow: 0 15px 40px rgba(0,0,0,0.3);
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
            
            section {
                padding: 100px 0;
            }
            
            section h2 {
                font-size: 2.5rem;
                font-weight: 700;
                margin-bottom: 1rem;
                text-align: center;
                background: var(--gradiente);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .section-subtitle {
                text-align: center;
                font-size: 1.1rem;
                color: #666;
                max-width: 700px;
                margin: 0 auto 4rem;
            }
            
            .stats {
                background: #f8f9fa;
            }
            
            .stats-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
                gap: 2rem;
                text-align: center;
            }
            
            .stat-card {
                background: white;
                padding: 2.5rem;
                border-radius: 20px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.05);
                transition: all 0.3s;
            }
            
            .stat-card:hover {
                transform: translateY(-10px);
                box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            }
            
            .stat-number {
                font-size: 3rem;
                font-weight: 800;
                background: var(--gradiente);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            
            .stat-label {
                font-size: 1.1rem;
                color: #666;
                font-weight: 500;
            }
            
            .cards-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
                gap: 2rem;
                margin-top: 3rem;
            }
            
            .card {
                background: white;
                padding: 2.5rem;
                border-radius: 20px;
                box-shadow: 0 5px 20px rgba(0,0,0,0.08);
                transition: all 0.3s;
                position: relative;
                overflow: hidden;
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
                transition: transform 0.3s;
            }
            
            .card:hover {
                transform: translateY(-10px);
                box-shadow: 0 15px 40px rgba(0,0,0,0.15);
            }
            
            .card:hover::before {
                transform: scaleX(1);
            }
            
            .card-icon {
                font-size: 3rem;
                margin-bottom: 1rem;
            }
            
            .card h3 {
                color: var(--cor-primaria);
                margin-bottom: 1rem;
                font-size: 1.4rem;
            }
            
            .servicos-grid {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
                gap: 2rem;
            }
            
            .servico-card {
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.1) 0%, rgba(118, 75, 162, 0.1) 100%);
                padding: 2.5rem;
                border-radius: 20px;
                text-align: center;
                transition: all 0.3s;
                border: 2px solid transparent;
            }
            
            .servico-card:hover {
                border-color: var(--cor-primaria);
                transform: scale(1.05);
                background: white;
                box-shadow: 0 15px 40px rgba(0,0,0,0.1);
            }
            
            .servico-icon {
                font-size: 3.5rem;
                margin-bottom: 1rem;
            }
            
            .empresa-info {
                background: linear-gradient(135deg, rgba(102, 126, 234, 0.07) 0%, rgba(118, 75, 162, 0.07) 100%);
                padding: 3rem;
                border-radius: 20px;
                margin: 3rem 0;
                border-left: 5px solid var(--cor-primaria);
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
                font-size: 0.9rem;
                margin-bottom: 0.3rem;
            }
            
            .faq-container {
                max-width: 800px;
                margin: 0 auto;
            }
            
            .faq-item {
                background: white;
                margin-bottom: 1rem;
                border-radius: 15px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.05);
                overflow: hidden;
            }
            
            .faq-question {
                padding: 1.5rem;
                cursor: pointer;
                display: flex;
                justify-content: space-between;
                align-items: center;
                font-weight: 600;
                transition: all 0.3s;
            }
            
            .faq-question:hover {
                background: #f8f9fa;
            }
            
            .faq-answer {
                max-height: 0;
                overflow: hidden;
                transition: all 0.3s;
                padding: 0 1.5rem;
            }
            
            .faq-answer.active {
                max-height: 300px;
                padding: 0 1.5rem 1.5rem;
            }
            
            .faq-icon {
                transition: transform 0.3s;
            }
            
            .faq-icon.active {
                transform: rotate(180deg);
            }
            
            .contato-grid {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 4rem;
                align-items: start;
            }
            
            .contato-info {
                background: var(--gradiente);
                color: white;
                padding: 3rem;
                border-radius: 20px;
                height: 100%;
            }
            
            .contato-item {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin-bottom: 2rem;
                padding: 1.5rem;
                background: rgba(255,255,255,0.1);
                border-radius: 15px;
                backdrop-filter: blur(10px);
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
                border-radius: 20px;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            }
            
            .form-group {
                margin-bottom: 1.5rem;
            }
            
            .form-group label {
                display: block;
                margin-bottom: 0.5rem;
                font-weight: 600;
                color: #333;
            }
            
            .form-group input,
            .form-group textarea {
                width: 100%;
                padding: 15px;
                border: 2px solid #e0e0e0;
                border-radius: 10px;
                font-family: inherit;
                font-size: 1rem;
                transition: all 0.3s;
            }
            
            .form-group input:focus,
            .form-group textarea:focus {
                outline: none;
                border-color: var(--cor-primaria);
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            
            .form-group textarea {
                min-height: 150px;
                resize: vertical;
            }
            
            .btn-submit {
                background: var(--gradiente);
                color: white;
                border: none;
                padding: 15px 40px;
                border-radius: 50px;
                cursor: pointer;
                font-size: 1rem;
                font-weight: 600;
                transition: all 0.3s;
                width: 100%;
            }
            
            .btn-submit:hover {
                transform: translateY(-2px);
                box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            }
            
            .mapa-container {
                margin: 4rem 0;
                border-radius: 20px;
                overflow: hidden;
                box-shadow: 0 10px 40px rgba(0,0,0,0.1);
                height: 500px;
            }
            
            .mapa-container iframe {
                width: 100%;
                height: 100%;
                border: none;
            }
            
            footer {
                background: #1a202c;
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
                font-size: 1.3rem;
            }
            
            .footer-section ul {
                list-style: none;
            }
            
            .footer-section ul li {
                margin-bottom: 0.8rem;
            }
            
            .footer-section a {
                color: #a0aec0;
                text-decoration: none;
                transition: color 0.3s;
            }
            
            .footer-section a:hover {
                color: white;
            }
            
            .footer-bottom {
                border-top: 1px solid #2d3748;
                padding-top: 2rem;
                text-align: center;
                color: #a0aec0;
            }
            
            .footer-info {
                background: rgba(255,255,255,0.05);
                padding: 2rem;
                border-radius: 15px;
                margin-bottom: 2rem;
            }
            
            .footer-info p {
                margin: 0.5rem 0;
                font-size: 0.9rem;
            }
            
            .whatsapp-float {
                position: fixed;
                bottom: 30px;
                right: 30px;
                width: 60px;
                height: 60px;
                background: #25D366;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-size: 2rem;
                color: white;
                box-shadow: 0 4px 20px rgba(37, 211, 102, 0.4);
                z-index: 999;
                cursor: pointer;
                transition: all 0.3s;
                text-decoration: none;
            }
            
            .whatsapp-float:hover {
                transform: scale(1.1);
                box-shadow: 0 6px 30px rgba(37, 211, 102, 0.6);
            }
            
            .cookie-banner {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                background: rgba(26, 32, 44, 0.98);
                backdrop-filter: blur(10px);
                color: white;
                padding: 1.5rem;
                display: none;
                z-index: 9999;
                box-shadow: 0 -5px 30px rgba(0,0,0,0.3);
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
            }
            
            .cookie-text {
                flex: 1;
            }
            
            .cookie-text h4 {
                margin-bottom: 0.5rem;
                font-size: 1.2rem;
            }
            
            .cookie-text p {
                font-size: 0.9rem;
                opacity: 0.9;
            }
            
            .cookie-buttons {
                display: flex;
                gap: 1rem;
                flex-shrink: 0;
            }
            
            .cookie-buttons .btn {
                padding: 10px 25px;
                font-size: 0.9rem;
            }
            
            .cookie-settings {
                margin-top: 1rem;
                padding-top: 1rem;
                border-top: 1px solid rgba(255,255,255,0.2);
            }
            
            .cookie-option {
                display: flex;
                align-items: center;
                gap: 1rem;
                margin: 0.5rem 0;
            }
            
            @media (max-width: 968px) {
                .hero h1 { font-size: 2.5rem; }
                .hero p { font-size: 1.1rem; }
                
                nav ul {
                    position: fixed;
                    top: 70px;
                    left: -100%;
                    flex-direction: column;
                    background: white;
                    width: 100%;
                    padding: 2rem;
                    box-shadow: 0 5px 20px rgba(0,0,0,0.1);
                    transition: left 0.3s;
                }
                
                nav ul.active {
                    left: 0;
                }
                
                .menu-toggle {
                    display: flex;
                }
                
                .contato-grid {
                    grid-template-columns: 1fr;
                }
                
                .cookie-content {
                    flex-direction: column;
                    text-align: center;
                }
                
                .stats-grid,
                .cards-grid,
                .servicos-grid {
                    grid-template-columns: 1fr;
                }
            }
            
            @media (max-width: 640px) {
                .hero h1 { font-size: 2rem; }
                section { padding: 60px 0; }
                section h2 { font-size: 2rem; }
            }
        </style>
        ";
        
        return $css;
    }
    
    public function gerarHome() {
        $nomeFantasia = $this->gerarNomeFantasia();
        $cnae = $this->obterValor($this->dadosEmpresa, 'cnae_fiscal_descricao', 'Serviços Diversos');
        $cores = $this->getCores();
        $icone = $cores['icone'];
        
        return "
        <div class='hero'>
            <div class='hero-content container'>
                <h1>" . $icone . " " . $nomeFantasia . "</h1>
                <p>" . $cnae . "</p>
                <div class='hero-buttons'>
                    <a href='#contato' class='btn'>Fale Conosco</a>
                    <a href='#sobre' class='btn btn-outline'>Saiba Mais</a>
                </div>
            </div>
        </div>
        
        <section class='stats'>
            <div class='container'>
                <div class='stats-grid'>
                    <div class='stat-card'>
                        <div class='stat-number'>100%</div>
                        <div class='stat-label'>Comprometimento</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number'>24/7</div>
                        <div class='stat-label'>Disponibilidade</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number'>+1000</div>
                        <div class='stat-label'>Clientes Satisfeitos</div>
                    </div>
                    <div class='stat-card'>
                        <div class='stat-number'>" . date('Y') . "</div>
                        <div class='stat-label'>Anos de Experiência</div>
                    </div>
                </div>
            </div>
        </section>
        
        <section id='home'>
            <div class='container'>
                <h2>Bem-vindo à " . $nomeFantasia . "</h2>
                <p class='section-subtitle'>
                    Somos especialistas em " . $cnae . ", oferecendo soluções de excelência 
                    com qualidade, inovação e total comprometimento com nossos clientes.
                </p>
                
                <div class='cards-grid'>
                    <div class='card'>
                        <div class='card-icon'>🎯</div>
                        <h3>Nossa Missão</h3>
                        <p>Entregar serviços e produtos de alta qualidade que superem as expectativas, 
                        criando valor sustentável para nossos clientes e parceiros de negócio.</p>
                    </div>
                    <div class='card'>
                        <div class='card-icon'>👁️</div>
                        <h3>Nossa Visão</h3>
                        <p>Ser referência em " . $cnae . ", reconhecida pela excelência operacional, 
                        inovação constante e impacto positivo na vida de nossos clientes.</p>
                    </div>
                    <div class='card'>
                        <div class='card-icon'>💎</div>
                        <h3>Nossos Valores</h3>
                        <p>Integridade, ética profissional, respeito, busca pela excelência, inovação 
                        e foco total na satisfação e sucesso dos nossos clientes.</p>
                    </div>
                </div>
            </div>
        </section>
        
        <section style='background: #f8f9fa;'>
            <div class='container'>
                <h2>Por Que Nos Escolher?</h2>
                <p class='section-subtitle'>
                    Diferenciais que nos tornam a melhor escolha para suas necessidades
                </p>
                
                <div class='servicos-grid'>
                    <div class='servico-card'>
                        <div class='servico-icon'>⚡</div>
                        <h3>Agilidade</h3>
                        <p>Processos otimizados para entregas rápidas e eficientes sem comprometer a qualidade</p>
                    </div>
                    <div class='servico-card'>
                        <div class='servico-icon'>🏆</div>
                        <h3>Qualidade Superior</h3>
                        <p>Padrões elevados em todos os nossos serviços, produtos e relacionamento com clientes</p>
                    </div>
                    <div class='servico-card'>
                        <div class='servico-icon'>💼</div>
                        <h3>Profissionalismo</h3>
                        <p>Equipe altamente qualificada, treinada e comprometida com resultados excepcionais</p>
                    </div>
                    <div class='servico-card'>
                        <div class='servico-icon'>🔒</div>
                        <h3>Segurança</h3>
                        <p>Proteção de dados conforme LGPD e conformidade com todas as normas do setor</p>
                    </div>
                    <div class='servico-card'>
                        <div class='servico-icon'>💰</div>
                        <h3>Melhor Custo-Benefício</h3>
                        <p>Preços justos e competitivos com qualidade incomparável no mercado</p>
                    </div>
                    <div class='servico-card'>
                        <div class='servico-icon'>🤝</div>
                        <h3>Parceria Duradoura</h3>
                        <p>Relacionamento de longo prazo baseado em confiança, transparência e resultados</p>
                    </div>
                </div>
            </div>
        </section>
        ";
    }
    
    public function gerarSobre() {
        $razaoSocial = $this->obterValor($this->dadosEmpresa, 'razao_social', 'Empresa');
        $nomeFantasia = $this->gerarNomeFantasia();
        $cnpj = $this->formatarCNPJ($this->cnpj);
        $situacao = $this->obterValor($this->dadosEmpresa, 'situacao_cadastral', 'Ativa');
        $dataAbertura = $this->formatarData($this->obterValor($this->dadosEmpresa, 'data_inicio_atividade', ''));
        $naturezaJuridica = $this->obterValor($this->dadosEmpresa, 'natureza_juridica_descricao', '');
        $porte = $this->obterValor($this->dadosEmpresa, 'porte', '');
        $cnae = $this->obterValor($this->dadosEmpresa, 'cnae_fiscal_descricao', '');
        $capitalSocial = $this->obterValor($this->dadosEmpresa, 'capital_social', '');
        
        return "
        <section id='sobre'>
            <div class='container'>
                <h2>Sobre Nós</h2>
                <p class='section-subtitle'>
                    Conheça nossa empresa, nossa história e nosso compromisso com a excelência
                </p>
                
                <div class='empresa-info'>
                    <h3 style='color: var(--cor-primaria); margin-bottom: 2rem; font-size: 1.8rem;'>" . $nomeFantasia . "</h3>
                    
                    <p style='margin-bottom: 2rem; line-height: 1.8;'>
                        A " . $nomeFantasia . " é uma empresa consolidada no mercado, comprometida em oferecer 
                        soluções de excelência em " . $cnae . ". Com anos de experiência e uma equipe altamente 
                        qualificada, construímos uma sólida reputação baseada em confiança, qualidade e resultados 
                        consistentes para nossos clientes e parceiros.
                    </p>
                    
                    <div class='info-grid'>
                        <div class='info-item'>
                            <div class='info-icon'>🏢</div>
                            <div class='info-text'>
                                <strong>Razão Social</strong>
                                <span>" . $razaoSocial . "</span>
                            </div>
                        </div>
                        
                        <div class='info-item'>
                            <div class='info-icon'>📋</div>
                            <div class='info-text'>
                                <strong>CNPJ</strong>
                                <span>" . $cnpj . "</span>
                            </div>
                        </div>
                        
                        <div class='info-item'>
                            <div class='info-icon'>✅</div>
                            <div class='info-text'>
                                <strong>Situação Cadastral</strong>
                                <span>" . $situacao . "</span>
                            </div>
                        </div>
                        
                        <div class='info-item'>
                            <div class='info-icon'>📅</div>
                            <div class='info-text'>
                                <strong>Data de Fundação</strong>
                                <span>" . $dataAbertura . "</span>
                            </div>
                        </div>
                        
                        <div class='info-item'>
                            <div class='info-icon'>⚖️</div>
                            <div class='info-text'>
                                <strong>Natureza Jurídica</strong>
                                <span>" . $naturezaJuridica . "</span>
                            </div>
                        </div>
                        
                        <div class='info-item'>
                            <div class='info-icon'>📊</div>
                            <div class='info-text'>
                                <strong>Porte da Empresa</strong>
                                <span>" . $porte . "</span>
                            </div>
                        </div>
                    </div>
                    
                    <div style='margin-top: 2rem; padding: 1.5rem; background: white; border-radius: 15px;'>
                        <strong style='color: var(--cor-primaria); display: block; margin-bottom: 0.5rem;'>Atividade Principal (CNAE)</strong>
                        <p style='line-height: 1.8;'>" . $cnae . "</p>
                    </div>
                </div>
                
                <div style='margin-top: 4rem;'>
                    <p style='text-align: center; max-width: 800px; margin: 0 auto 3rem; font-size: 1.1rem; line-height: 1.8;'>
                        Nossa equipe é formada por profissionais experientes e dedicados, prontos para atender 
                        às necessidades específicas de cada cliente. Trabalhamos continuamente para aprimorar 
                        nossos processos, investir em tecnologia e oferecer as melhores soluções do mercado, 
                        sempre mantendo nosso compromisso com a ética, transparência e excelência.
                    </p>
                </div>
            </div>
        </section>
        ";
    }
    
    public function gerarFAQ() {
        return "
        <section style='background: #f8f9fa;'>
            <div class='container'>
                <h2>Perguntas Frequentes</h2>
                <p class='section-subtitle'>Tire suas dúvidas sobre nossos serviços e empresa</p>
                
                <div class='faq-container'>
                    <div class='faq-item'>
                        <div class='faq-question' onclick='toggleFAQ(this)'>
                            <span>Como posso entrar em contato com a empresa?</span>
                            <span class='faq-icon'>▼</span>
                        </div>
                        <div class='faq-answer'>
                            Você pode entrar em contato conosco através do formulário disponível neste site, 
                            por telefone, e-mail ou WhatsApp. Estamos sempre disponíveis para atendê-lo da melhor forma possível.
                        </div>
                    </div>
                    
                    <div class='faq-item'>
                        <div class='faq-question' onclick='toggleFAQ(this)'>
                            <span>Qual é o horário de atendimento?</span>
                            <span class='faq-icon'>▼</span>
                        </div>
                        <div class='faq-answer'>
                            Nosso atendimento está disponível de segunda a sexta-feira, das 8h às 18h, e aos sábados das 8h às 12h. 
                            Para situações urgentes, oferecemos canais de contato para atendimento prioritário.
                        </div>
                    </div>
                    
                    <div class='faq-item'>
                        <div class='faq-question' onclick='toggleFAQ(this)'>
                            <span>Quais formas de pagamento são aceitas?</span>
                            <span class='faq-icon'>▼</span>
                        </div>
                        <div class='faq-answer'>
                            Aceitamos diversas formas de pagamento incluindo cartão de crédito, débito, PIX, 
                            boleto bancário e transferência bancária. Entre em contato para conhecer condições especiais e parcelamento.
                        </div>
                    </div>
                    
                    <div class='faq-item'>
                        <div class='faq-question' onclick='toggleFAQ(this)'>
                            <span>A empresa oferece garantia nos serviços?</span>
                            <span class='faq-icon'>▼</span>
                        </div>
                        <div class='faq-answer'>
                            Sim! Todos os nossos serviços e produtos possuem garantia de qualidade conforme legislação vigente. 
                            Os prazos e condições específicas variam de acordo com o tipo de serviço ou produto contratado.
                        </div>
                    </div>
                    
                    <div class='faq-item'>
                        <div class='faq-question' onclick='toggleFAQ(this)'>
                            <span>Atendem em outras cidades e estados?</span>
                            <span class='faq-icon'>▼</span>
                        </div>
                        <div class='faq-answer'>
                            Sim, atendemos clientes em todo o território nacional. Entre em contato conosco 
                            para verificar as condições específicas de atendimento e logística para sua região.
                        </div>
                    </div>
                    
                    <div class='faq-item'>
                        <div class='faq-question' onclick='toggleFAQ(this)'>
                            <span>Como solicitar um orçamento?</span>
                            <span class='faq-icon'>▼</span>
                        </div>
                        <div class='faq-answer'>
                            Você pode solicitar um orçamento através do formulário de contato neste site, por telefone, 
                            e-mail ou WhatsApp. Nossa equipe responderá rapidamente com uma proposta personalizada para suas necessidades.
                        </div>
                    </div>
                </div>
            </div>
        </section>
        ";
    }
    
    public function gerarTermosUso() {
        $razaoSocial = $this->obterValor($this->dadosEmpresa, 'razao_social', 'Empresa');
        $cnpj = $this->formatarCNPJ($this->cnpj);
        $nomeFantasia = $this->gerarNomeFantasia();
        
        return "
        <section id='termos'>
            <div class='container'>
                <h2>Termos de Uso</h2>
                <p class='section-subtitle'>Leia atentamente nossos termos e condições de uso</p>
                
                <div style='max-width: 900px; margin: 0 auto;'>
                    <div class='card'>
                        <h3>1. Aceitação dos Termos</h3>
                        <p>Ao acessar e utilizar este website, você declara ter lido, compreendido e concordado com os termos e 
                        condições aqui estabelecidos. Se você não concordar com qualquer parte destes termos, 
                        solicitamos que não utilize nosso site.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>2. Identificação e Propriedade do Site</h3>
                        <p>Este site é de propriedade e operado por:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li><strong>Razão Social:</strong> " . $razaoSocial . "</li>
                            <li><strong>Nome Fantasia:</strong> " . $nomeFantasia . "</li>
                            <li><strong>CNPJ:</strong> " . $cnpj . "</li>
                        </ul>
                        <p style='margin-top: 1rem;'>Este site é fornecido para fins informativos e comerciais relacionados às nossas atividades empresariais.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>3. Uso Permitido do Site</h3>
                        <p>Você concorda em utilizar este site apenas para fins legais e de maneira que:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Não infrinja direitos de terceiros</li>
                            <li>Não restrinja ou impeça o uso do site por outros usuários</li>
                            <li>Não viole leis aplicáveis locais, nacionais ou internacionais</li>
                            <li>Não transmita material ilegal, ofensivo ou prejudicial</li>
                            <li>Não realize tentativas de acesso não autorizado ao site ou sistemas relacionados</li>
                        </ul>
                    </div>
                    
                    <div class='card'>
                        <h3>4. Propriedade Intelectual</h3>
                        <p>Todo o conteúdo deste site, incluindo mas não se limitando a textos, gráficos, logotipos, 
                        ícones, imagens, áudios, vídeos, downloads digitais, compilações de dados e software, é de 
                        propriedade exclusiva da " . $razaoSocial . " ou de seus fornecedores de conteúdo, sendo 
                        protegido por leis brasileiras e internacionais de direitos autorais, marcas registradas e 
                        propriedade intelectual.</p>
                        <p style='margin-top: 1rem;'>É proibida a reprodução, distribuição, modificação ou uso comercial 
                        de qualquer conteúdo sem autorização expressa e por escrito.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>5. Limitação de Responsabilidade</h3>
                        <p>A " . $razaoSocial . " não será responsável por:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Danos diretos, indiretos, incidentais, consequenciais ou punitivos</li>
                            <li>Perda de lucros, dados ou outras perdas intangíveis</li>
                            <li>Interrupções ou erros no funcionamento do site</li>
                            <li>Vírus ou códigos maliciosos que possam infectar seu equipamento</li>
                            <li>Ações de terceiros ou links externos</li>
                        </ul>
                        <p style='margin-top: 1rem;'>Recomendamos manter seu equipamento protegido com software antivírus atualizado.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>6. Links para Sites de Terceiros</h3>
                        <p>Este site pode conter links para sites de terceiros fornecidos apenas para sua conveniência. 
                        Não temos controle sobre o conteúdo, políticas de privacidade ou práticas desses sites externos 
                        e não assumimos qualquer responsabilidade por eles. O acesso a sites de terceiros é por sua conta e risco.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>7. Informações Fornecidas</h3>
                        <p>Embora nos esforcemos para manter as informações deste site precisas e atualizadas, não garantimos 
                        a exatidão, completude ou adequação das informações para qualquer propósito específico. Reservamo-nos 
                        o direito de modificar ou descontinuar qualquer aspecto do site sem aviso prévio.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>8. Privacidade e Proteção de Dados</h3>
                        <p>O tratamento de dados pessoais coletados através deste site é regido por nossa 
                        <a href='#privacidade' style='color: var(--cor-primaria); text-decoration: underline;'>Política de Privacidade</a>, 
                        em total conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018).</p>
                    </div>
                    
                    <div class='card'>
                        <h3>9. Modificações dos Termos</h3>
                        <p>Reservamo-nos o direito de modificar, alterar ou atualizar estes termos de uso a qualquer momento, 
                        sem necessidade de aviso prévio. As modificações entrarão em vigor imediatamente após sua publicação 
                        no site. Seu uso continuado após tais alterações constitui sua aceitação dos termos modificados. 
                        Recomendamos revisar periodicamente esta página.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>10. Lei Aplicável e Jurisdição</h3>
                        <p>Estes termos de uso serão regidos, interpretados e aplicados de acordo com as leis da 
                        República Federativa do Brasil. Qualquer disputa, controvérsia ou reclamação decorrente ou 
                        relacionada a estes termos será submetida à jurisdição exclusiva dos tribunais brasileiros 
                        competentes, renunciando as partes a qualquer outro foro, por mais privilegiado que seja.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>11. Contato</h3>
                        <p>Para questões, dúvidas ou esclarecimentos sobre estes Termos de Uso, entre em contato conosco através:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Formulário de contato disponível neste site</li>
                            <li>Seção de contato com todos nossos canais de comunicação</li>
                        </ul>
                        
                        <p style='margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e0e0e0; color: #666;'>
                            <strong>Última atualização:</strong> " . date('d/m/Y') . "<br>
                            <strong>Versão:</strong> 1.0
                        </p>
                    </div>
                </div>
            </div>
        </section>
        ";
    }
    
    public function gerarPrivacidade() {
        $razaoSocial = $this->obterValor($this->dadosEmpresa, 'razao_social', 'Empresa');
        $cnpj = $this->formatarCNPJ($this->cnpj);
        $email = $this->obterValor($this->dadosEmpresa, 'email', 'contato@empresa.com.br');
        $endereco = $this->formatarEndereco();
        $nomeFantasia = $this->gerarNomeFantasia();
        
        return "
        <section id='privacidade' style='background: #f8f9fa;'>
            <div class='container'>
                <h2>Política de Privacidade</h2>
                <p class='section-subtitle'>Comprometidos com a proteção e segurança dos seus dados pessoais</p>
                
                <div style='max-width: 900px; margin: 0 auto;'>
                    <div class='card'>
                        <h3>1. Informações Gerais</h3>
                        <p>A " . $razaoSocial . " (" . $nomeFantasia . "), inscrita no CNPJ sob o nº " . $cnpj . ", 
                        doravante denominada \"Empresa\", está profundamente comprometida em proteger sua privacidade e 
                        seus dados pessoais. Esta Política de Privacidade explica de forma clara e transparente como 
                        coletamos, usamos, armazenamos, compartilhamos e protegemos suas informações pessoais, em total 
                        conformidade com a Lei Geral de Proteção de Dados (LGPD - Lei nº 13.709/2018) e demais 
                        legislações aplicáveis.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>2. Dados Pessoais que Coletamos</h3>
                        <p><strong>2.1 Dados Fornecidos Voluntariamente:</strong></p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Nome completo, e-mail, telefone ao preencher formulários de contato</li>
                            <li>Mensagens, comentários e outras comunicações que você nos envia</li>
                            <li>Informações de pagamento (quando aplicável e necessário)</li>
                            <li>Dados cadastrais para prestação de serviços contratados</li>
                        </ul>
                        
                        <p style='margin-top: 1rem;'><strong>2.2 Dados Coletados Automaticamente:</strong></p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Endereço IP, tipo e versão do navegador utilizado</li>
                            <li>Sistema operacional, páginas visitadas e tempo de permanência</li>
                            <li>Cookies e tecnologias similares de rastreamento</li>
                            <li>Data e hora de acesso ao site</li>
                            <li>Referência de origem (de onde você veio para nosso site)</li>
                        </ul>
                        
                        <p style='margin-top: 1rem;'><strong>2.3 Google Ads e Google Analytics:</strong></p>
                        <p>Utilizamos Google Analytics e Google Ads para análise de tráfego, comportamento de usuários e 
                        campanhas publicitárias. Estas ferramentas coletam dados como interações com anúncios, 
                        conversões e dados demográficos agregados. Você pode gerenciar suas preferências através das 
                        configurações de anúncios do Google em: 
                        <a href='https://www.google.com/settings/ads' target='_blank' rel='noopener' style='color: var(--cor-primaria);'>www.google.com/settings/ads</a></p>
                    </div>
                    
                    <div class='card'>
                        <h3>3. Como Utilizamos seus Dados</h3>
                        <p>Utilizamos suas informações pessoais exclusivamente para as seguintes finalidades:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Fornecer, operar, manter e melhorar nossos serviços e produtos</li>
                            <li>Responder às suas solicitações, dúvidas e comunicações</li>
                            <li>Enviar informações sobre produtos, serviços e novidades (mediante consentimento prévio)</li>
                            <li>Processar transações e pagamentos quando aplicável</li>
                            <li>Melhorar nosso site, experiência do usuário e estratégias de marketing</li>
                            <li>Analisar tendências, administrar o site e coletar informações demográficas</li>
                            <li>Detectar, prevenir e resolver problemas técnicos, fraudes e questões de segurança</li>
                            <li>Cumprir obrigações legais, regulatórias e contratuais</li>
                            <li>Exercer direitos em processos judiciais ou administrativos</li>
                        </ul>
                    </div>
                    
                    <div class='card'>
                        <h3>4. Base Legal para o Tratamento de Dados</h3>
                        <p>Tratamos seus dados pessoais com base nas seguintes hipóteses legais previstas na LGPD:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li><strong>Consentimento (Art. 7º, I):</strong> Quando você autoriza expressamente o tratamento de seus dados</li>
                            <li><strong>Execução de Contrato (Art. 7º, V):</strong> Para prestar serviços ou cumprir obrigações contratuais</li>
                            <li><strong>Obrigação Legal (Art. 7º, II):</strong> Para cumprir exigências legais ou regulatórias</li>
                            <li><strong>Legítimo Interesse (Art. 7º, IX):</strong> Para melhorar nossos serviços e comunicação</li>
                            <li><strong>Exercício Regular de Direitos (Art. 7º, VI):</strong> Em processos judiciais ou administrativos</li>
                        </ul>
                    </div>
                    
                    <div class='card'>
                        <h3>5. Compartilhamento de Dados Pessoais</h3>
                        <p>Não vendemos, alugamos ou comercializamos seus dados pessoais com terceiros. Podemos compartilhar 
                        suas informações apenas nas seguintes situações:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li><strong>Prestadores de Serviços:</strong> Empresas que auxiliam em hospedagem, processamento de pagamentos, 
                            análise de dados, marketing e suporte técnico, sempre mediante contrato e garantias adequadas</li>
                            <li><strong>Autoridades Legais:</strong> Quando exigido por lei, ordem judicial ou requisição de autoridades competentes</li>
                            <li><strong>Parceiros Comerciais:</strong> Somente mediante seu consentimento explícito e prévio</li>
                            <li><strong>Operações Societárias:</strong> Em caso de fusão, aquisição ou venda de ativos</li>
                        </ul>
                        <p style='margin-top: 1rem;'>Todos os terceiros que têm acesso aos seus dados são obrigados 
                        contratualmente a manter o mesmo nível de proteção e confidencialidade.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>6. Seus Direitos como Titular de Dados (LGPD)</h3>
                        <p>De acordo com a LGPD, você possui os seguintes direitos em relação aos seus dados pessoais:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>✓ <strong>Confirmação e Acesso:</strong> Confirmar a existência de tratamento e acessar seus dados</li>
                            <li>✓ <strong>Correção:</strong> Solicitar correção de dados incompletos, inexatos ou desatualizados</li>
                            <li>✓ <strong>Anonimização ou Bloqueio:</strong> Solicitar anonimização, bloqueio ou eliminação de dados desnecessários ou excessivos</li>
                            <li>✓ <strong>Eliminação:</strong> Solicitar eliminação de dados tratados com base no consentimento</li>
                            <li>✓ <strong>Portabilidade:</strong> Solicitar portabilidade dos dados a outro fornecedor</li>
                            <li>✓ <strong>Informação sobre Compartilhamento:</strong> Obter informações sobre entidades com as quais compartilhamos dados</li>
                            <li>✓ <strong>Revogação do Consentimento:</strong> Revogar consentimento a qualquer momento</li>
                            <li>✓ <strong>Oposição:</strong> Opor-se ao tratamento realizado com base em legítimo interesse</li>
                            <li>✓ <strong>Revisão de Decisões Automatizadas:</strong> Solicitar revisão de decisões tomadas unicamente com base em tratamento automatizado</li>
                        </ul>
                        <p style='margin-top: 1rem;'>Para exercer qualquer um destes direitos, entre em contato através dos 
                        canais disponibilizados na seção \"Contato do Encarregado de Dados\" desta política.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>7. Segurança dos Dados</h3>
                        <p>Implementamos medidas técnicas, administrativas e organizacionais robustas para proteger 
                        suas informações pessoais contra acesso não autorizado, alteração, divulgação ou destruição, incluindo:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Criptografia SSL/TLS para transmissão de dados sensíveis</li>
                            <li>Controles rigorosos de acesso baseados em necessidade e privilégio mínimo</li>
                            <li>Monitoramento contínuo de segurança e detecção de ameaças</li>
                            <li>Backups regulares e seguros dos dados</li>
                            <li>Treinamento periódico de colaboradores sobre proteção de dados</li>
                            <li>Auditorias e testes de segurança regulares</li>
                            <li>Plano de resposta a incidentes de segurança</li>
                        </ul>
                        <p style='margin-top: 1rem;'>Apesar de nossos esforços, nenhum método de transmissão ou armazenamento 
                        eletrônico é 100% seguro. Em caso de incidente de segurança, notificaremos os titulares afetados 
                        conforme exigido pela LGPD.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>8. Cookies e Tecnologias Similares</h3>
                        <p>Utilizamos cookies e tecnologias similares para melhorar sua experiência de navegação, 
                        analisar tendências, administrar o site e coletar informações demográficas. Os cookies são 
                        classificados nas seguintes categorias:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li><strong>Essenciais:</strong> Necessários para funcionamento básico do site</li>
                            <li><strong>Funcionais:</strong> Melhoram funcionalidade e personalização</li>
                            <li><strong>Analíticos:</strong> Coletam informações sobre uso do site (Google Analytics)</li>
                            <li><strong>Publicidade:</strong> Utilizados para campanhas publicitárias direcionadas (Google Ads)</li>
                        </ul>
                        <p style='margin-top: 1rem;'>Você pode gerenciar suas preferências de cookies através do banner 
                        de consentimento ou configurações do seu navegador. Note que desabilitar cookies pode afetar 
                        funcionalidades do site. Para mais informações, consulte nossa 
                        <a href='#' style='color: var(--cor-primaria);'>Política de Cookies</a> detalhada.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>9. Retenção e Eliminação de Dados</h3>
                        <p>Mantemos seus dados pessoais apenas pelo tempo necessário para cumprir as finalidades descritas 
                        nesta política ou conforme exigido por lei. Os critérios para determinar o período de retenção incluem:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Duração do relacionamento comercial</li>
                            <li>Obrigações legais de retenção (fiscais, trabalhistas, etc.)</li>
                            <li>Exercício de direitos em processos judiciais ou administrativos</li>
                            <li>Consentimento fornecido para fins específicos</li>
                        </ul>
                        <p style='margin-top: 1rem;'>Após o período de retenção, os dados são eliminados ou anonimizados 
                        de forma segura e irreversível.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>10. Transferência Internacional de Dados</h3>
                        <p>Seus dados pessoais são armazenados e processados prioritariamente em servidores localizados 
                        no Brasil. Caso seja necessária transferência internacional de dados, ela será realizada apenas:</p>
                        <ul style='margin-left: 1.5rem; margin-top: 0.5rem;'>
                            <li>Para países com nível de proteção adequado reconhecido pela ANPD</li>
                            <li>Mediante cláusulas contratuais específicas que garantam proteção adequada</li>
                            <li>Em cumprimento de obrigação legal ou regulatória</li>
                            <li>Com seu consentimento específico para tal finalidade</li>
                        </ul>
                    </div>
                    
                    <div class='card'>
                        <h3>11. Dados de Menores de Idade</h3>
                        <p>Não coletamos intencionalmente dados pessoais de menores de 18 anos sem o consentimento expresso 
                        e verificável dos pais ou responsáveis legais. Se tomarmos conhecimento de que coletamos inadvertidamente 
                        dados de menores sem o devido consentimento, tomaremos medidas imediatas para excluir tais informações 
                        de nossos sistemas.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>12. Alterações nesta Política de Privacidade</h3>
                        <p>Reservamo-nos o direito de atualizar, modificar ou alterar esta Política de Privacidade periodicamente 
                        para refletir mudanças em nossas práticas, tecnologias, requisitos legais ou outros fatores operacionais. 
                        Notificaremos sobre mudanças significativas através de aviso destacado em nosso site ou por e-mail. 
                        A versão atualizada entrará em vigor imediatamente após sua publicação. Recomendamos revisar esta 
                        página regularmente para se manter informado.</p>
                    </div>
                    
                    <div class='card'>
                        <h3>13. Contato - Encarregado de Proteção de Dados (DPO)</h3>
                        <p>Para exercer seus direitos, esclarecer dúvidas, fazer solicitações ou apresentar reclamações 
                        relacionadas a esta Política de Privacidade e ao tratamento de seus dados pessoais, entre em contato 
                        com nosso Encarregado de Proteção de Dados (Data Protection Officer - DPO):</p>
                        <div style='background: #f8f9fa; padding: 1.5rem; border-radius: 10px; margin-top: 1rem;'>
                            <p><strong>Razão Social:</strong> " . $razaoSocial . "</p>
                            <p><strong>Nome Fantasia:</strong> " . $nomeFantasia . "</p>
                            <p><strong>CNPJ:</strong> " . $cnpj . "</p>
                            <p><strong>E-mail:</strong> " . $email . "</p>
                            <p><strong>Endereço:</strong> " . $endereco . "</p>
                        </div>
                        <p style='margin-top: 1rem;'>Você também pode apresentar reclamação à Autoridade Nacional de 
                        Proteção de Dados (ANPD) através do site: 
                        <a href='https://www.gov.br/anpd' target='_blank' rel='noopener' style='color: var(--cor-primaria);'>www.gov.br/anpd</a></p>
                        
                        <p style='margin-top: 2rem; padding-top: 2rem; border-top: 1px solid #e0e0e0; color: #666;'>
                            <strong>Última atualização:</strong> " . date('d/m/Y') . "<br>
                            <strong>Versão:</strong> 1.0<br>
                            <strong>Vigência:</strong> A partir de " . date('d/m/Y') . "
                        </p>
                    </div>
                </div>
            </div>
        </section>
        ";
    }
    
    public function gerarContato() {
        $nomeFantasia = $this->gerarNomeFantasia();
        $endereco = $this->formatarEndereco();
        $telefone = $this->formatarTelefone($this->obterValor($this->dadosEmpresa, 'ddd_telefone_1', ''));
        $email = $this->obterValor($this->dadosEmpresa, 'email', 'contato@empresa.com.br');
        $enderecoCompleto = urlencode($endereco);
        $whatsappNumero = preg_replace('/[^0-9]/', '', $telefone);
        
        return "
        <section id='contato'>
            <div class='container'>
                <h2>Entre em Contato Conosco</h2>
                <p class='section-subtitle'>Estamos prontos para atender você da melhor forma possível</p>
                
                <div class='contato-grid'>
                    <div class='contato-info'>
                        <h3 style='font-size: 2rem; margin-bottom: 2rem;'>Informações de Contato</h3>
                        
                        <div class='contato-item'>
                            <div class='contato-item-icon'>📍</div>
                            <div>
                                <strong style='display: block; margin-bottom: 0.5rem; font-size: 1.1rem;'>Endereço</strong>
                                <p>" . $endereco . "</p>
                            </div>
                        </div>
                        
                        <div class='contato-item'>
                            <div class='contato-item-icon'>📞</div>
                            <div>
                                <strong style='display: block; margin-bottom: 0.5rem; font-size: 1.1rem;'>Telefone</strong>
                                <p>" . $telefone . "</p>
                            </div>
                        </div>
                        
                        <div class='contato-item'>
                            <div class='contato-item-icon'>📧</div>
                            <div>
                                <strong style='display: block; margin-bottom: 0.5rem; font-size: 1.1rem;'>E-mail</strong>
                                <p>" . $email . "</p>
                            </div>
                        </div>
                        
                        <div class='contato-item'>
                            <div class='contato-item-icon'>⏰</div>
                            <div>
                                <strong style='display: block; margin-bottom: 0.5rem; font-size: 1.1rem;'>Horário de Atendimento</strong>
                                <p>Segunda à Sexta: 8h00 - 18h00<br>Sábado: 8h00 - 12h00</p>
                            </div>
                        </div>
                    </div>
                    
                    <div class='form-container'>
                        <h3 style='margin-bottom: 2rem; color: var(--cor-primaria);'>Envie sua Mensagem</h3>
                        <form method='POST' action='enviar.php' id='contactForm'>
                            <div class='form-group'>
                                <label for='nome'>Nome Completo *</label>
                                <input type='text' id='nome' name='nome' required>
                            </div>
                            
                            <div class='form-group'>
                                <label for='email'>E-mail *</label>
                                <input type='email' id='email' name='email' required>
                            </div>
                            
                            <div class='form-group'>
                                <label for='telefone'>Telefone *</label>
                                <input type='tel' id='telefone' name='telefone' required>
                            </div>
                            
                            <div class='form-group'>
                                <label for='assunto'>Assunto *</label>
                                <input type='text' id='assunto' name='assunto' required>
                            </div>
                            
                            <div class='form-group'>
                                <label for='mensagem'>Mensagem *</label>
                                <textarea id='mensagem' name='mensagem' required></textarea>
                            </div>
                            
                            <button type='submit' class='btn-submit'>Enviar Mensagem</button>
                        </form>
                    </div>
                </div>
                
                <div class='mapa-container'>
                    <iframe 
                        src='https://www.google.com/maps?q=" . $enderecoCompleto . "&output=embed' 
                        allowfullscreen='' 
                        loading='lazy' 
                        referrerpolicy='no-referrer-when-downgrade'
                        title='Localização da empresa'>
                    </iframe>
                </div>
            </div>
        </section>
        
        <a href='https://wa.me/55" . $whatsappNumero . "' target='_blank' rel='noopener' class='whatsapp-float' 
           title='Fale conosco no WhatsApp' aria-label='WhatsApp'>
            💬
        </a>
        ";
    }
    
    private function formatarEndereco() {
        $logradouro = $this->obterValor($this->dadosEmpresa, 'logradouro', '');
        $numero = $this->obterValor($this->dadosEmpresa, 'numero', 'S/N');
        $complemento = $this->obterValor($this->dadosEmpresa, 'complemento', '');
        $bairro = $this->obterValor($this->dadosEmpresa, 'bairro', '');
        $municipio = $this->obterValor($this->dadosEmpresa, 'municipio', '');
        $uf = $this->obterValor($this->dadosEmpresa, 'uf', '');
        $cep = $this->formatarCEP($this->obterValor($this->dadosEmpresa, 'cep', ''));
        
        $endereco = $logradouro . ", " . $numero;
        if (!empty($complemento)) {
            $endereco .= " - " . $complemento;
        }
        $endereco .= " - " . $bairro . ", " . $municipio . "/" . $uf . " - CEP: " . $cep;
        
        return $endereco;
    }
    
    private function formatarCNPJ($cnpj) {
        return preg_replace('/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/', '$1.$2.$3/$4-$5', $cnpj);
    }
    
    private function formatarCEP($cep) {
        return preg_replace('/(\d{5})(\d{3})/', '$1-$2', $cep);
    }
    
    private function formatarData($data) {
        if (empty($data)) {
            return 'N/A';
        }
        $date = date_create($data);
        return date_format($date, 'd/m/Y');
    }
    
    private function formatarTelefone($telefone) {
        $telefone = preg_replace('/[^0-9]/', '', $telefone);
        if (strlen($telefone) == 10) {
            return preg_replace('/(\d{2})(\d{4})(\d{4})/', '($1) $2-$3', $telefone);
        } elseif (strlen($telefone) == 11) {
            return preg_replace('/(\d{2})(\d{5})(\d{4})/', '($1) $2-$3', $telefone);
        }
        return $telefone;
    }
    
    public function gerarSiteCompleto() {
        $nomeFantasia = $this->gerarNomeFantasia();
        $cnae = $this->obterValor($this->dadosEmpresa, 'cnae_fiscal_descricao', 'Serviços Diversos');
        $razaoSocial = $this->obterValor($this->dadosEmpresa, 'razao_social', '');
        $telefone = $this->formatarTelefone($this->obterValor($this->dadosEmpresa, 'ddd_telefone_1', ''));
        $email = $this->obterValor($this->dadosEmpresa, 'email', '');
        $endereco = $this->formatarEndereco();
        
        $html = "
        <!DOCTYPE html>
        <html lang='pt-BR'>
        <head>
            <meta charset='UTF-8'>
            <meta name='viewport' content='width=device-width, initial-scale=1.0'>
            <meta name='description' content='" . $nomeFantasia . " - " . $cnae . ". Excelência em nossos serviços com qualidade, profissionalismo e comprometimento.'>
            <meta name='keywords' content='" . $nomeFantasia . ", " . $razaoSocial . ", " . $cnae . ", empresa, serviços de qualidade'>
            <meta name='author' content='" . $nomeFantasia . "'>
            <meta name='robots' content='index, follow'>
            <meta property='og:title' content='" . $nomeFantasia . " - " . $cnae . "'>
            <meta property='og:description' content='Excelência em " . $cnae . "'>
            <meta property='og:type' content='website'>
            <meta property='og:locale' content='pt_BR'>
            <title>" . $nomeFantasia . " - " . $cnae . "</title>
            <link rel='icon' href='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 100 100%22><text y=%22.9em%22 font-size=%2290%22>🏢</text></svg>'>
            <link rel='canonical' href=''>
            " . $this->gerarCSS() . "
            " . $this->gerarSchemaOrg() . "
        </head>
        <body>
            <header id='header'>
                <div class='container'>
                    <div class='logo'>" . $nomeFantasia . "</div>
                    <nav>
                        <ul id='nav-menu'>
                            <li><a href='#home'>Home</a></li>
                            <li><a href='#sobre'>Sobre</a></li>
                            <li><a href='#termos'>Termos de Uso</a></li>
                            <li><a href='#privacidade'>Privacidade</a></li>
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
            
            <main>
                " . $this->gerarHome() . "
                " . $this->gerarSobre() . "
                " . $this->gerarFAQ() . "
                " . $this->gerarTermosUso() . "
                " . $this->gerarPrivacidade() . "
                " . $this->gerarContato() . "
            </main>
            
            <footer>
                <div class='container'>
                    <div class='footer-content'>
                        <div class='footer-section'>
                            <h3>" . $nomeFantasia . "</h3>
                            <p style='margin-top: 1rem;'>" . $cnae . "</p>
                            <div class='footer-info'>
                                <p><strong>Razão Social:</strong> " . $razaoSocial . "</p>
                                <p><strong>CNPJ:</strong> " . $this->formatarCNPJ($this->cnpj) . "</p>
                            </div>
                        </div>
                        
                        <div class='footer-section'>
                            <h3>Navegação</h3>
                            <ul>
                                <li><a href='#home'>Home</a></li>
                                <li><a href='#sobre'>Sobre Nós</a></li>
                                <li><a href='#contato'>Contato</a></li>
                            </ul>
                        </div>
                        
                        <div class='footer-section'>
                            <h3>Legal</h3>
                            <ul>
                                <li><a href='#termos'>Termos de Uso</a></li>
                                <li><a href='#privacidade'>Política de Privacidade</a></li>
                                <li><a href='#privacidade'>Política de Cookies</a></li>
                            </ul>
                        </div>
                        
                        <div class='footer-section'>
                            <h3>Contato</h3>
                            <p style='margin: 0.5rem 0;'><strong>E-mail:</strong><br>" . $email . "</p>
                            <p style='margin: 0.5rem 0;'><strong>Telefone:</strong><br>" . $telefone . "</p>
                            <p style='margin: 0.5rem 0;'><strong>Endereço:</strong><br>" . $endereco . "</p>
                        </div>
                    </div>
                    
                    <div class='footer-bottom'>
                        <p>&copy; " . date('Y') . " " . $nomeFantasia . ". Todos os direitos reservados.</p>
                        <p style='margin-top: 0.5rem; font-size: 0.9rem;'>Desenvolvido com ❤️ para oferecer a melhor experiência</p>
                    </div>
                </div>
            </footer>
            
            <div class='cookie-banner' id='cookieBanner'>
                <div class='cookie-content'>
                    <div class='cookie-text'>
                        <h4>🍪 Este site utiliza cookies</h4>
                        <p>Utilizamos cookies essenciais e tecnologias semelhantes de acordo com nossa 
                        <a href='#privacidade' style='color: var(--cor-acento); text-decoration: underline;'>Política de Privacidade</a>. 
                        Ao clicar em \"Aceitar todos\", você concorda com o uso de cookies para análise, publicidade e funcionalidades 
                        personalizadas. Também utilizamos Google Analytics e Google Ads para melhorar sua experiência.</p>
                    </div>
                    <div class='cookie-buttons'>
                        <button class='btn' onclick='aceitarTodosCookies()'>Aceitar Todos</button>
                        <button class='btn btn-outline' onclick='aceitarEssenciais()'>Apenas Essenciais</button>
                    </div>
                </div>
            </div>
            
            <script>
                // Menu Mobile
                var menuToggle = document.getElementById('menu-toggle');
                var navMenu = document.getElementById('nav-menu');
                
                menuToggle.addEventListener('click', function() {
                    navMenu.classList.toggle('active');
                });
                
                // Fechar menu ao clicar em link
                var links = document.querySelectorAll('#nav-menu a');
                for (var i = 0; i < links.length; i++) {
                    links[i].addEventListener('click', function() {
                        navMenu.classList.remove('active');
                    });
                }
                
                // Header scroll effect
                window.addEventListener('scroll', function() {
                    var header = document.getElementById('header');
                    if (window.scrollY > 100) {
                        header.classList.add('scrolled');
                    } else {
                        header.classList.remove('scrolled');
                    }
                });
                
                // Scroll suave
                var anchors = document.querySelectorAll('a[href^=\"#\"]');
                for (var i = 0; i < anchors.length; i++) {
                    anchors[i].addEventListener('click', function(e) {
                        e.preventDefault();
                        var targetId = this.getAttribute('href');
                        if (targetId === '#') return;
                        var target = document.querySelector(targetId);
                        if (target) {
                            var offsetTop = target.offsetTop - 80;
                            window.scrollTo({
                                top: offsetTop,
                                behavior: 'smooth'
                            });
                        }
                    });
                }
                
                // FAQ Toggle
                function toggleFAQ(element) {
                    var answer = element.nextElementSibling;
                    var icon = element.querySelector('.faq-icon');
                    
                    answer.classList.toggle('active');
                    icon.classList.toggle('active');
                }
                
                // Gerenciamento de Cookies - GDPR/LGPD Compliant
                window.addEventListener('load', function() {
                    var cookiesAceitos = localStorage.getItem('cookiesAceitos');
                    if (!cookiesAceitos) {
                        setTimeout(function() {
                            document.getElementById('cookieBanner').classList.add('show');
                        }, 1000);
                    } else if (cookiesAceitos === 'todos') {
                        carregarScriptsAnalytics();
                    }
                });
                
                function aceitarTodosCookies() {
                    localStorage.setItem('cookiesAceitos', 'todos');
                    document.getElementById('cookieBanner').classList.remove('show');
                    carregarScriptsAnalytics();
                }
                
                function aceitarEssenciais() {
                    localStorage.setItem('cookiesAceitos', 'essenciais');
                    document.getElementById('cookieBanner').classList.remove('show');
                }
                
                function carregarScriptsAnalytics() {
                    // Google Analytics e Google Ads - Carregar apenas após consentimento
                    // Substitua 'GA_MEASUREMENT_ID' pelo seu ID real do Google Analytics
                    // Exemplo:
                    /*
                    var script = document.createElement('script');
                    script.async = true;
                    script.src = 'https://www.googletagmanager.com/gtag/js?id=GA_MEASUREMENT_ID';
                    document.head.appendChild(script);
                    
                    window.dataLayer = window.dataLayer || [];
                    function gtag(){dataLayer.push(arguments);}
                    gtag('js', new Date());
                    gtag('config', 'GA_MEASUREMENT_ID');
                    */
                    console.log('Analytics carregado após consentimento');
                }
                
                // Validação de formulário
                var contactForm = document.getElementById('contactForm');
                if (contactForm) {
                    contactForm.addEventListener('submit', function(e) {
                        e.preventDefault();
                        
                        var nome = document.getElementById('nome').value;
                        var email = document.getElementById('email').value;
                        var telefone = document.getElementById('telefone').value;
                        var mensagem = document.getElementById('mensagem').value;
                        
                        if (!nome || !email || !telefone || !mensagem) {
                            alert('Por favor, preencha todos os campos obrigatórios.');
                            return false;
                        }
                        
                        // Validação de email simples
                        var emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
                        if (!emailRegex.test(email)) {
                            alert('Por favor, insira um e-mail válido.');
                            return false;
                        }
                        
                        // Aqui você enviaria o formulário
                        alert('Mensagem enviada com sucesso! Entraremos em contato em breve.');
                        contactForm.reset();
                    });
                }
            </script>
        </body>
        </html>
        ";
        
        return $html;
    }
}
