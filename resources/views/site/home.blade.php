<!DOCTYPE html>
<html lang="ru" itemscope itemtype="https://schema.org/WebPage">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title itemprop="name">TG Support Bot - Telegram бот для технической поддержки | Open Source решение</title>

    <link rel="icon" type="image/svg" sizes="32x32" href="{{ asset('storage/favicon.svg') }}">

{{--    @include('site.hide.metrika')--}}

    <!-- SEO Meta Tags -->
    <meta name="description" content="Бесплатный Telegram бот для технической поддержки. Open Source решение для организации службы поддержки в Telegram и ВКонтакте. Быстрая установка, автоматизация, командная работа.">
    <meta name="keywords" content="telegram bot, техподдержка, telegram support, бот поддержки, open source, бесплатный бот, техническая поддержка, vkontakte bot, чат-бот, customer support, tg support bot, поддержка клиентов">
    <meta name="author" content="Prog-Time (Илья Лящук)">
    <meta name="robots" content="index, follow">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="TG Support Bot - Telegram бот для технической поддержки">
    <meta property="og:description" content="Бесплатное Open Source решение для организации службы поддержки в Telegram и ВКонтакте. Быстрая установка, автоматизация, командная работа.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ env('AUTHOR_GITHUB_PROJECT', 'https://t.me/pt_tg_support') }}">
    <meta property="og:image" content="https://github.com/prog-time/tg-support-bot/blob/main/storage/app/public/support_bot.png?raw=true">
    <meta property="og:locale" content="ru_RU">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="TG Support Bot - Telegram бот для технической поддержки">
    <meta name="twitter:description" content="Бесплатное Open Source решение для организации службы поддержки в Telegram и ВКонтакте.">
    <meta name="twitter:image" content="https://github.com/prog-time/tg-support-bot/blob/main/storage/app/public/support_bot.png?raw=true">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://tg-support-bot.ru/">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        :root {
            --telegram-blue: #0088cc;
            --telegram-light-blue: #37aee2;
            --telegram-dark: #1d2733;
            --telegram-darker: #17212b;
            --telegram-light: #ffffff;
            --telegram-gray: #8e99a3;
            --telegram-green: #24b455;
            --vk-blue: #4c75a3;
            --youtube-red: #ff0000;
            --rutube-orange: #f68423;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--telegram-darker);
            color: var(--telegram-light);
            line-height: 1.6;
            overflow-x: hidden;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
        }

        /* Header */
        header {
            padding: 24px 0;
            position: fixed;
            width: 100%;
            top: 0;
            z-index: 1000;
            background: rgba(23, 33, 43, 0.95);
            backdrop-filter: blur(10px);
            border-bottom: 1px solid rgba(0, 136, 204, 0.2);
        }

        .header-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .logo-icon {
            background: var(--telegram-blue);
            padding: 8px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon i {
            font-size: 24px;
            color: white;
        }

        .logo-text {
            font-size: 20px;
            font-weight: 700;
            color: white;
        }

        .github-btn {
            display: flex;
            align-items: center;
            gap: 8px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            padding: 12px 20px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 500;
            border: 1px solid rgba(255, 255, 255, 0.2);
            transition: all 0.3s ease;
        }

        .github-btn:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        /* Hero Section */
        .hero {
            padding: 160px 0 80px;
            text-align: center;
            background: var(--telegram-dark);
        }

        .badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: rgba(0, 136, 204, 0.2);
            color: #37aee2;
            padding: 8px 16px;
            border-radius: 50px;
            font-size: 14px;
            font-weight: 500;
            border: 1px solid rgba(0, 136, 204, 0.3);
            margin-bottom: 24px;
        }

        .badge-dot {
            width: 8px;
            height: 8px;
            background: var(--telegram-green);
            border-radius: 50%;
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { opacity: 1; }
            50% { opacity: 0.5; }
            100% { opacity: 1; }
        }

        .hero h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 24px;
            line-height: 1.2;
        }

        .hero-title-telegram {
            color: var(--telegram-light-blue);
        }

        .hero p {
            font-size: 1.25rem;
            color: var(--telegram-gray);
            max-width: 700px;
            margin: 0 auto 40px;
            line-height: 1.7;
        }

        .hero-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-bottom: 60px;
        }

        .btn {
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-primary {
            background: var(--telegram-blue);
            color: white;
            box-shadow: 0 10px 25px rgba(0, 136, 204, 0.3);
        }

        .btn-primary:hover {
            background: var(--telegram-light-blue);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 136, 204, 0.4);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .stats {
            display: flex;
            justify-content: center;
            gap: 40px;
            margin-bottom: 60px;
            flex-wrap: wrap;
        }

        .stat-item {
            text-align: center;
        }

        .stat-icon {
            font-size: 24px;
            color: var(--telegram-blue);
            margin-bottom: 12px;
        }

        .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 4px;
        }

        .stat-label {
            color: var(--telegram-gray);
            font-size: 0.9rem;
        }

        /* Video Section */
        .video-section {
            max-width: 800px;
            margin: 0 auto 60px;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            position: relative;
        }

        .video-wrapper {
            position: relative;
            padding-bottom: 56.25%; /* 16:9 Aspect Ratio */
            height: 0;
            overflow: hidden;
        }

        .video-wrapper iframe {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            border: none;
        }

        /* About Section */
        .about {
            padding: 100px 0;
            background: var(--telegram-dark);
        }

        .about-content {
            max-width: 800px;
            margin: 0 auto;
            text-align: center;
        }

        .about h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 30px;
            color: var(--telegram-light-blue);
        }

        .about p {
            font-size: 1.25rem;
            color: var(--telegram-gray);
            margin-bottom: 25px;
            line-height: 1.8;
        }

        .vision {
            background: rgba(0, 136, 204, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin: 40px 0;
            border: 1px solid rgba(0, 136, 204, 0.2);
        }

        .vision p {
            font-style: italic;
            font-size: 1.3rem;
            color: var(--telegram-light);
            margin: 0;
        }

        /* Features Section */
        .section {
            padding: 100px 0;
        }

        .section-header {
            text-align: center;
            margin-bottom: 60px;
        }

        .section-title {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 16px;
        }

        .section-subtitle {
            font-size: 1.25rem;
            color: var(--telegram-gray);
            max-width: 600px;
            margin: 0 auto;
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: var(--telegram-dark);
            border-radius: 16px;
            padding: 32px 24px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            transition: all 0.3s ease;
        }

        .feature-card:hover {
            transform: translateY(-5px);
            border-color: rgba(0, 136, 204, 0.5);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
        }

        .feature-icon {
            font-size: 32px;
            color: var(--telegram-blue);
            margin-bottom: 20px;
        }

        .feature-title {
            font-size: 1.5rem;
            font-weight: 700;
            margin-bottom: 12px;
        }

        .feature-description {
            color: var(--telegram-gray);
            line-height: 1.7;
        }

        /* How it works Section */
        .how-it-works {
            background: var(--telegram-darker);
        }

        .steps {
            display: flex;
            flex-direction: column;
            gap: 40px;
            max-width: 800px;
            margin: 0 auto;
        }

        .step {
            display: flex;
            gap: 30px;
            align-items: flex-start;
        }

        .step-number {
            background: var(--telegram-blue);
            width: 50px;
            height: 50px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
            font-weight: 700;
            flex-shrink: 0;
        }

        .step-content h3 {
            font-size: 1.5rem;
            margin-bottom: 12px;
            color: var(--telegram-light-blue);
        }

        .step-content p {
            color: var(--telegram-gray);
            line-height: 1.7;
        }

        /* Installation Section */
        .installation {
            background: var(--telegram-dark);
        }

        .code-block {
            background: var(--telegram-darker);
            border-radius: 16px;
            padding: 32px;
            border: 1px solid rgba(255, 255, 255, 0.1);
            max-width: 700px;
            margin: 0 auto 30px;
        }

        .code-line {
            font-family: 'Monaco', 'Consolas', monospace;
            font-size: 16px;
            line-height: 2;
            color: var(--telegram-light);
        }

        .prompt { color: var(--telegram-blue); }
        .command { color: var(--telegram-green); }

        .docs-link {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--telegram-blue);
            text-decoration: none;
            font-weight: 500;
        }

        .docs-link:hover {
            text-decoration: underline;
        }

        .video-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .btn-video {
            padding: 14px 24px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-rutube {
            background: var(--rutube-orange);
            color: white;
        }

        .btn-youtube {
            background: var(--youtube-red);
            color: white;
        }

        .btn-vk {
            background: var(--vk-blue);
            color: white;
        }

        .btn-video:hover {
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
        }

        /* Community Section */
        .community {
            background: rgba(0, 136, 204, 0.1);
            border-radius: 24px;
            padding: 80px 40px;
            text-align: center;
            margin: 60px 0;
        }

        .community h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .community p {
            font-size: 1.25rem;
            color: var(--telegram-gray);
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .btn-community {
            background: var(--telegram-blue);
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            font-weight: 600;
            font-size: 16px;
            text-decoration: none;
            transition: all 0.3s ease;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }

        .btn-community:hover {
            background: var(--telegram-light-blue);
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 136, 204, 0.4);
        }

        /* CTA Section */
        .cta {
            background: rgba(0, 136, 204, 0.1);
            border-radius: 24px;
            padding: 80px 40px;
            text-align: center;
            margin: 60px 0;
        }

        .cta h2 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
        }

        .cta p {
            font-size: 1.25rem;
            color: var(--telegram-gray);
            max-width: 600px;
            margin: 0 auto 40px;
        }

        .cta-buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
        }

        .btn-github {
            background: white;
            color: var(--telegram-darker);
        }

        .btn-github:hover {
            transform: translateY(-3px);
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
        }

        .btn-issue {
            background: transparent;
            border: 2px solid white;
            color: white;
        }

        .btn-issue:hover {
            background: rgba(255, 255, 255, 0.1);
        }

        /* Footer */
        footer {
            padding: 60px 0 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            background: var(--telegram-darker);
        }

        .footer-content {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 30px;
            margin-bottom: 40px;
        }

        .footer-logo {
            display: flex;
            align-items: center;
            gap: 12px;
            text-decoration: none;
        }

        .footer-links {
            display: flex;
            gap: 24px;
        }

        .footer-link {
            color: var(--telegram-gray);
            font-size: 24px;
            transition: color 0.3s ease;
        }

        .footer-link:hover {
            color: white;
        }

        .copyright {
            text-align: center;
            color: var(--telegram-gray);
            padding-top: 40px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 2.5rem;
            }

            .hero p {
                font-size: 1.1rem;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: center;
            }

            .stats {
                gap: 20px;
            }

            .stat-value {
                font-size: 1.5rem;
            }

            .section-title {
                font-size: 2rem;
            }

            .step {
                flex-direction: column;
                text-align: center;
            }

            .footer-content {
                flex-direction: column;
                text-align: center;
            }

            .feature-card {
                display: flex;
                flex-direction: column;
                align-items: center;
                text-align: center;
            }

            .video-buttons {
                flex-direction: column;
            }

            a.btn-video {
                display: block;
                text-align: center;
            }

            .step-number {
                margin: auto;
            }

            .cta-buttons {
                flex-direction: column;
            }

            a.btn {
                display: block;
                text-align: center;
            }

            .footer-links {
                justify-content: center;
            }
        }

        @media (max-width: 480px) {
            .hero {
                padding: 140px 0 60px;
            }

            .section {
                padding: 60px 0;
            }

            .about {
                padding: 60px 0;
            }

            .community, .cta {
                padding: 60px 20px;
            }

            .community h2, .cta h2 {
                font-size: 2rem;
            }
        }
    </style>
</head>
<body itemscope itemtype="https://schema.org/WebPage">
    <!-- JSON-LD Schema.org markup -->
    <script type="application/ld+json">
{{--        {--}}
{{--            "@context": "https://schema.org",--}}
{{--            "@type": "SoftwareApplication",--}}
{{--            "name": "TG Support Bot",--}}
{{--            "description": "Бесплатный Telegram бот для технической поддержки. Open Source решение для организации службы поддержки в Telegram и ВКонтакте.",--}}
{{--            "applicationCategory": "CommunicationSoftware",--}}
{{--            "operatingSystem": "Any",--}}
{{--            "offers": {--}}
{{--                "@type": "Offer",--}}
{{--                "price": "0",--}}
{{--                "priceCurrency": "RUB"--}}
{{--            },--}}
{{--            "softwareVersion": "1.0",--}}
{{--            "softwareHelp": "https://github.com/prog-time/tg-support-bot/wiki",--}}
{{--            "featureList": [--}}
{{--                "Интеграция с Telegram",--}}
{{--                "Интеграция с ВКонтакте",--}}
{{--                "Безопасность",--}}
{{--                "Мгновенные ответы",--}}
{{--                "Командная работа",--}}
{{--                "Умная автоматизация"--}}
{{--            ]--}}
{{--        }--}}
    </script>

    <!-- Header -->
    <header itemscope itemtype="https://schema.org/WPHeader">
        <div class="container">
            <div class="header-content">
                <a href="#" class="logo" itemprop="url">
                    <div class="logo-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="logo-text" itemprop="name">TG Support Bot</div>
                </a>
                <a href="{{ env('AUTHOR_GITHUB_PROJECT', 'https://github.com/prog-time') }}" target="_blank" class="github-btn" itemprop="sameAs">
                    <i class="fab fa-github"></i>
                    GitHub
                </a>
            </div>
        </div>
    </header>

    <!-- Hero Section -->
    <section class="hero" itemscope itemtype="https://schema.org/SoftwareApplication">
        <div class="container">
            <div class="badge">
                <div class="badge-dot"></div>
                <span>Open Source</span>
            </div>
            <h1 itemprop="name"><span class="hero-title-telegram">Telegram бот</span> для технической поддержки</h1>
            <p itemprop="description">Бесплатный инструмент для организации тех. поддержки в Telegram.</p>

            <div class="hero-buttons">
                <a href="{{ env('AUTHOR_GITHUB_PROJECT', 'https://github.com/prog-time') }}" target="_blank" class="btn btn-primary" itemprop="downloadUrl">
                    <i class="fab fa-github"></i>
                    Начать работу
                </a>
                <a href="{{ env('AUTHOR_WIKI_PAGE', 'https://github.com/prog-time') }}" class="btn btn-secondary" itemprop="softwareHelp">
                    <i class="fas fa-book"></i>
                    Документация
                </a>
            </div>

            <div class="stats">
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-star"></i>
                    </div>
                    <div class="stat-value" itemprop="aggregateRating" itemscope itemtype="https://schema.org/AggregateRating">
                        <span itemprop="ratingValue">100</span>+
                        <meta itemprop="ratingCount" content="90">
                    </div>
                    <div class="stat-label">Звезд</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-code-branch"></i>
                    </div>
                    <div class="stat-value">
                        19+
                    </div>
                    <div class="stat-label">Форков</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-download"></i>
                    </div>
                    <div class="stat-value">1000+</div>
                    <div class="stat-label">Клонирований</div>
                </div>
            </div>
        </div>
    </section>

    <!-- About Section -->
    <section class="about" itemscope itemtype="https://schema.org/AboutPage">
        <div class="container">
            <div class="about-content">
                <h2 itemprop="name">О проекте</h2>
                <p itemprop="description">TG Support Bot — это open-source решение для быстрой и удобной организации поддержки в Telegram и ВКонтакте. Начните прямо сейчас — установите бота и подарите вашим клиентам сервис, который они заслуживают.</p>

                <div class="vision">
                    <p>Наше видение простое: каждая команда, независимо от размера, должна иметь доступ к современным инструментам поддержки без сложных интеграций и дорогих лицензий.</p>
                </div>

                <!-- Video Section -->
                <div class="video-section">
                    <div class="video-wrapper">
                        <iframe src="https://vkvideo.ru/video_ext.php?oid=-141526561&id=456239134&hd=2&autoplay=1" width="853" height="480" style="background-color: #000" allow="autoplay; encrypted-media; fullscreen; picture-in-picture; screen-wake-lock;" frameborder="0" allowfullscreen></iframe>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="section" itemscope itemtype="https://schema.org/ItemList">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title" itemprop="name">Почему выбирают TG Support Bot?</h2>
                <p class="section-subtitle" itemprop="description">Создан для команд, которые ценят скорость, простоту и довольных клиентов.</p>
            </div>

            <div class="features-grid">
                <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                    <meta itemprop="position" content="1">
                    <div class="feature-icon">
                        <i class="fas fa-comments"></i>
                    </div>
                    <h3 class="feature-title" itemprop="name">Интеграция с Telegram</h3>
                    <p class="feature-description" itemprop="description">Бесшовная интеграция с Telegram ботами для мгновенной поддержки клиентов</p>
                </div>

                <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                    <meta itemprop="position" content="2">
                    <div class="feature-icon">
                        <i class="fab fa-vk"></i>
                    </div>
                    <h3 class="feature-title" itemprop="name">Интеграция с ВКонтакте</h3>
                    <p class="feature-description" itemprop="description">Объединение чатов из Telegram и ВКонтакте в единую систему поддержки</p>
                </div>

                <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                    <meta itemprop="position" content="3">
                    <div class="feature-icon">
                        <i class="fas fa-shield-alt"></i>
                    </div>
                    <h3 class="feature-title" itemprop="name">Безопасность</h3>
                    <p class="feature-description" itemprop="description">Разработан с учетом требований безопасности, ваши данные и разговоры остаются конфиденциальными</p>
                </div>

                <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                    <meta itemprop="position" content="4">
                    <div class="feature-icon">
                        <i class="fas fa-bolt"></i>
                    </div>
                    <h3 class="feature-title" itemprop="name">Мгновенные ответы</h3>
                    <p class="feature-description" itemprop="description">Оптимизированная производительность для быстрых ответов и плавной работы</p>
                </div>

                <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                    <meta itemprop="position" content="5">
                    <div class="feature-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <h3 class="feature-title" itemprop="name">Командная работа</h3>
                    <p class="feature-description" itemprop="description">Несколько операторов могут вести диалоги параллельно.</p>
                </div>

                <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                    <meta itemprop="position" content="6">
                    <div class="feature-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <h3 class="feature-title" itemprop="name">Умная автоматизация</h3>
                    <p class="feature-description" itemprop="description">Автоответы и маршрутизация заявок.</p>
                </div>
            </div>
        </div>
    </section>

    <!-- How it works Section -->
    <section class="section how-it-works" itemscope itemtype="https://schema.org/HowTo">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title" itemprop="name">Как работает бот?</h2>
                <p class="section-subtitle" itemprop="description">Простая и эффективная система технической поддержки</p>
            </div>

            <div class="steps">
                <div class="step" itemprop="step" itemscope itemtype="https://schema.org/HowToStep">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3 itemprop="name">Установка</h3>
                        <p itemprop="text">Запустите бота с помощью пары команд через Docker Compose</p>
                    </div>
                </div>

                <div class="step" itemprop="step" itemscope itemtype="https://schema.org/HowToStep">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3 itemprop="name">Создайте Telegram бота</h3>
                        <p itemprop="text">Зарегистрируйте нового бота у @BotFather и добавьте полученный токен в проект.</p>
                    </div>
                </div>

                <div class="step" itemprop="step" itemscope itemtype="https://schema.org/HowToStep">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3 itemprop="name">Настройка рабочей группы</h3>
                        <p itemprop="text">Создайте приватную группу в Telegram, куда будут поступать все сообщения от клиентов.</p>
                    </div>
                </div>

                <div class="step" itemprop="step" itemscope itemtype="https://schema.org/HowToStep">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3 itemprop="name">Подключение команды</h3>
                        <p itemprop="text">Добавьте в группу вашего бота и менеджеров поддержки — теперь все обращения будут собраны в одном месте, и ни одно сообщение не потеряется.</p>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <!-- Community Section -->
    <section class="container">
        <div class="community" itemscope itemtype="https://schema.org/Organization">
            <meta itemprop="name" content="TG Support Bot Community">
            <h2>Подключайтесь к сообществу</h2>
            <p>Присоединяйтесь к нашему Telegram-сообществу — задавайте вопросы, делитесь опытом и получайте свежие обновления проекта.</p>
            <a href="https://t.me/pt_tg_support" target="_blank" class="btn-community" itemprop="sameAs">
                <i class="fab fa-telegram-plane"></i>
                Вступить в группу
            </a>
        </div>
    </section>

    <!-- Installation Section -->
    <section class="section installation">
        <div class="container">
            <div class="section-header">
                <h2 class="section-title">Начните за 20 минут</h2>
                <p class="section-subtitle">Выберите удобный формат инструкции для установки</p>
            </div>

            <div class="video-buttons">
                <a href="https://rutube.ru/video/bdd0cc5ab4e13530fd7e0c2413931211/" class="btn-video btn-rutube">
                    <i class="fas fa-play-circle"></i>
                    Rutube
                </a>
                <a href="https://www.youtube.com/watch?v=yNiNtFWOF2w" class="btn-video btn-youtube">
                    <i class="fab fa-youtube"></i>
                    YouTube
                </a>
                <a href="https://vkvideo.ru/video-141526561_456239132" class="btn-video btn-vk">
                    <i class="fab fa-vk"></i>
                    ВК видео
                </a>
            </div>

            <div style="text-align: center; margin-top: 30px;">
                <a href="{{ env('AUTHOR_WIKI_PAGE', 'https://github.com/prog-time') }}" class="docs-link">
                    Посмотреть текстовое руководство
                    <i class="fas fa-arrow-right"></i>
                </a>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="container">
        <div class="cta">
            <h2>Готовы улучшить поддержку клиентов?</h2>
            <p>Проект развивается сообществом и абсолютно бесплатен. Вы можете использовать его «как есть», дополнять под свои задачи или внести вклад в развитие — код открыт для каждого.</p>
            <div class="cta-buttons">
                <a href="{{ env('AUTHOR_GITHUB_PROJECT', 'https://github.com/prog-time') }}" target="_blank" class="btn btn-github">
                    <i class="fas fa-star"></i>
                    Поставить звезду на GitHub
                </a>
                <a href="{{ env('AUTHOR_GITHUB_ISSUES', 'https://github.com/prog-time') }}" class="btn btn-issue">
                    <i class="fas fa-bug"></i>
                    Сообщить о проблеме
                </a>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer itemscope itemtype="https://schema.org/WPFooter">
        <div class="container">
            <div class="footer-content">
                <a href="#" class="footer-logo">
                    <div class="logo-icon">
                        <i class="fas fa-robot"></i>
                    </div>
                    <div class="logo-text" itemprop="name">TG Support Bot</div>
                </a>

                <div class="footer-links">
                    <a href="{{ env('AUTHOR_GITHUB_PROJECT', 'https://github.com/prog-time') }}" target="_blank" class="footer-link" itemprop="sameAs">
                        <i class="fab fa-github"></i>
                    </a>
                    <a href="{{ env('AUTHOR_WIKI_PAGE', 'https://github.com/prog-time') }}" class="footer-link" itemprop="url">
                        <i class="fas fa-book"></i>
                    </a>
                </div>
            </div>

            <div class="copyright">
                Open Source проект • Лицензия MIT • Сделано с ❤️ сообществом
            </div>
        </div>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                document.querySelector(this.getAttribute('href')).scrollIntoView({
                    behavior: 'smooth'
                });
            });
        });

        // Header scroll effect
        window.addEventListener('scroll', function() {
            const header = document.querySelector('header');
            if (window.scrollY > 50) {
                header.style.background = 'rgba(23, 33, 43, 0.98)';
                header.style.boxShadow = '0 5px 20px rgba(0, 0, 0, 0.3)';
            } else {
                header.style.background = 'rgba(23, 33, 43, 0.95)';
                header.style.boxShadow = 'none';
            }
        });

        // Feature card hover effect enhancement
        const featureCards = document.querySelectorAll('.feature-card');
        featureCards.forEach(card => {
            card.addEventListener('mouseenter', function() {
                this.style.transform = 'translateY(-10px)';
            });

            card.addEventListener('mouseleave', function() {
                this.style.transform = 'translateY(0)';
            });
        });

        // Terminal animation
        const terminalLines = document.querySelectorAll('.terminal-line');
        terminalLines.forEach((line, index) => {
            line.style.opacity = '0';
            setTimeout(() => {
                line.style.transition = 'opacity 0.5s ease';
                line.style.opacity = '1';
            }, index * 300);
        });
    </script>
</body>
</html>
