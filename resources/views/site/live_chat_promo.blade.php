<?php

$schemaData = [
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'Live Chat Widget',
    'description' => 'Бесплатный виджет живого чата для сайта с интеграцией Telegram. Общайтесь с клиентами в реальном времени через WebSocket.',
    'applicationCategory' => 'CommunicationSoftware',
    'operatingSystem' => 'Any',
    'offers' => [
        '@type' => 'Offer',
        'price' => '0',
        'priceCurrency' => 'RUB',
    ],
    'softwareVersion' => '1.0',
    'softwareHelp' => 'https://github.com/prog-time/tg-support-bot/wiki',
    'featureList' => [
        'Виджет для сайта',
        'WebSocket соединение',
        'Интеграция с Telegram',
        'Реальное время',
        'Простая установка',
        'Кастомизация дизайна',
    ],
];

?>

<!DOCTYPE html>
<html lang="ru" itemscope itemtype="https://schema.org/WebPage">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title itemprop="name">Live Chat - Виджет живого чата для сайта | Интеграция с Telegram</title>

    <link rel="icon" type="image/svg" sizes="32x32" href="{{ asset('storage/favicon.svg') }}">

    @include('site.hide.metrika')

    <!-- SEO Meta Tags -->
    <meta name="description" content="Бесплатный виджет живого чата для сайта. Общайтесь с посетителями в реальном времени через Telegram. WebSocket, мгновенная доставка сообщений, простая установка.">
    <meta name="keywords" content="live chat, живой чат, виджет чата, онлайн консультант, чат для сайта, telegram chat, websocket chat, бесплатный чат, customer support, real-time chat, виджет поддержки">
    <meta name="author" content="Prog-Time (Илья Лящук)">
    <meta name="robots" content="index, follow">

    <!-- Open Graph Meta Tags -->
    <meta property="og:title" content="Live Chat - Виджет живого чата для сайта">
    <meta property="og:description" content="Бесплатный виджет живого чата с интеграцией Telegram. Общайтесь с клиентами в реальном времени через WebSocket.">
    <meta property="og:type" content="website">
    <meta property="og:url" content="{{ env('AUTHOR_GITHUB_PROJECT', 'https://t.me/pt_tg_support') }}">
    <meta property="og:image" content="https://github.com/prog-time/tg-support-bot/blob/main/storage/app/public/support_bot.png?raw=true">
    <meta property="og:locale" content="ru_RU">

    <!-- Twitter Card Meta Tags -->
    <meta name="twitter:card" content="summary_large_image">
    <meta name="twitter:title" content="Live Chat - Виджет живого чата для сайта">
    <meta name="twitter:description" content="Бесплатный виджет живого чата с интеграцией Telegram. Общайтесь с клиентами в реальном времени.">
    <meta name="twitter:image" content="https://github.com/prog-time/tg-support-bot/blob/main/storage/app/public/support_bot.png?raw=true">

    <!-- Canonical URL -->
    <link rel="canonical" href="https://tg-support-bot.ru/">

    <link rel="stylesheet" href="../src/css/basic.css">

    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
</head>
<body itemscope itemtype="https://schema.org/WebPage">

<!-- JSON-LD Schema.org markup -->
<script type="application/ld+json"><?= json_encode($schemaData) ?></script>

<!-- Header -->
<header itemscope itemtype="https://schema.org/WPHeader">
    <div class="container">
        <div class="header-content">
            <a href="/" class="logo" itemprop="url">
                <div class="logo-icon">
                    <i class="fas fa-comment-dots"></i>
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
        <h1 itemprop="name"><span class="hero-title-telegram">Живой чат</span> для вашего сайта</h1>
        <p itemprop="description">Общайтесь с посетителями сайта в реальном времени прямо из Telegram. Мгновенная доставка сообщений через WebSocket.</p>

        <div class="hero-buttons">
{{--            <a href="{{ env('AUTHOR_GITHUB_PROJECT', 'https://github.com/prog-time') }}" target="_blank" class="btn btn-primary" itemprop="downloadUrl">--}}
{{--                <i class="fas fa-play"></i>--}}
{{--                Демо--}}
{{--            </a>--}}
            <a href="{{ env('AUTHOR_WIKI_PAGE', 'https://github.com/prog-time') }}" class="btn btn-secondary" itemprop="softwareHelp">
                <i class="fas fa-code"></i>
                Инструкция по установке
            </a>
        </div>
    </div>
</section>

<!-- About Section -->
{{--<section class="about" itemscope itemtype="https://schema.org/AboutPage">--}}
{{--    <div class="container">--}}
{{--        <div class="about-content">--}}
{{--            <h2 itemprop="name">Чат прямо на вашем сайте</h2>--}}
{{--            <p itemprop="description">Live Chat — это виджет, который встраивается на ваш сайт за пару строк кода. Посетители пишут вам прямо со страницы, а вы отвечаете из привычного Telegram.</p>--}}

{{--            <!-- Video Section -->--}}
{{--            <div class="video-section">--}}
{{--                <div class="video-wrapper">--}}
{{--                    <iframe src="https://vkvideo.ru/video_ext.php?oid=-141526561&id=456239134&hd=2&autoplay=1" width="853" height="480" style="background-color: #000" allow="autoplay; encrypted-media; fullscreen; picture-in-picture; screen-wake-lock;" frameborder="0" allowfullscreen></iframe>--}}
{{--                </div>--}}
{{--            </div>--}}
{{--        </div>--}}
{{--    </div>--}}
{{--</section>--}}


<!-- Features Section -->
<section class="section" itemscope itemtype="https://schema.org/ItemList">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title" itemprop="name">Возможности</h2>
            <p class="section-subtitle" itemprop="description">Мощный инструмент для общения с клиентами, интегрированный с Telegram</p>
        </div>

        <div class="features-grid">
            <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                <meta itemprop="position" content="3">
                <div class="feature-icon">
                    <i class="fas fa-satellite-dish"></i>
                </div>
                <h3 class="feature-title" itemprop="name">Мгновенная доставка</h3>
                <p class="feature-description" itemprop="description">WebSocket соединение через Socket.io обеспечивает мгновенную доставку сообщений в обе стороны без задержек.</p>
            </div>

            <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                <meta itemprop="position" content="2">
                <div class="feature-icon">
                    <i class="fab fa-telegram-plane"></i>
                </div>
                <h3 class="feature-title" itemprop="name">Telegram интеграция</h3>
                <p class="feature-description" itemprop="description">Сообщения отправляются в Telegram группу, где под каждого клиента создаётся отдельная чат-тема.</p>
            </div>

            <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                <meta itemprop="position" content="5">
                <div class="feature-icon">
                    <i class="fas fa-mobile-alt"></i>
                </div>
                <h3 class="feature-title" itemprop="name">Адаптивность</h3>
                <p class="feature-description" itemprop="description">Отлично работает на любых устройствах — от смартфонов до десктопов</p>
            </div>

            <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                <meta itemprop="position" content="1">
                <div class="feature-icon">
                    <i class="fas fa-plug"></i>
                </div>
                <h3 class="feature-title" itemprop="name">Легкая интеграция</h3>
                <p class="feature-description" itemprop="description">Скопируйте код виджета и вставьте на сайт — чат заработает мгновенно</p>
            </div>

            <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                <meta itemprop="position" content="4">
                <div class="feature-icon">
                    <i class="fas fa-palette"></i>
                </div>
                <h3 class="feature-title" itemprop="name">Настраиваемый дизайн</h3>
                <p class="feature-description" itemprop="description">Адаптируйте внешний вид виджета под стиль вашего сайта</p>
            </div>

            <div class="feature-card" itemprop="itemListElement" itemscope itemtype="https://schema.org/SoftwareApplication">
                <meta itemprop="position" content="6">
                <div class="feature-icon">
                    <i class="fas fa-history"></i>
                </div>
                <h3 class="feature-title" itemprop="name">Open Source</h3>
                <p class="feature-description" itemprop="description">Полностью открытый исходный код. Настраивайте виджет под свои нужды и добавляйте собственную логику.</p>
            </div>
        </div>
    </div>
</section>

<!-- Integration Visual -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title" itemprop="name">Как это работает</h2>
            <p class="section-subtitle" itemprop="description">Сообщения мгновенно синхронизируются между сайтом и Telegram</p>
        </div>

        <div class="integration-visual">
            <div class="integration-item">
                <div class="integration-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="2" y1="12" x2="22" y2="12"></line>
                        <path d="M12 2a15.3 15.3 0 0 1 4 10 15.3 15.3 0 0 1-4 10 15.3 15.3 0 0 1-4-10 15.3 15.3 0 0 1 4-10z"></path>
                    </svg>
                </div>
                <span class="integration-label">Ваш сайт</span>
            </div>

            <div class="integration-arrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </div>

            <div class="integration-item">
                <div class="integration-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="2" y="3" width="20" height="14" rx="2" ry="2"></rect>
                        <line x1="8" y1="21" x2="16" y2="21"></line>
                        <line x1="12" y1="17" x2="12" y2="21"></line>
                    </svg>
                </div>
                <span class="integration-label">Node.js + Socket.io</span>
            </div>

            <div class="integration-arrow">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <line x1="5" y1="12" x2="19" y2="12"></line>
                    <polyline points="12 5 19 12 12 19"></polyline>
                </svg>
            </div>

            <div class="integration-item">
                <div class="integration-icon">
                    <svg viewBox="0 0 24 24" fill="#229ED9">
                        <path d="M11.944 0A12 12 0 0 0 0 12a12 12 0 0 0 12 12 12 12 0 0 0 12-12A12 12 0 0 0 12 0a12 12 0 0 0-.056 0zm4.962 7.224c.1-.002.321.023.465.14a.506.506 0 0 1 .171.325c.016.093.036.306.02.472-.18 1.898-.962 6.502-1.36 8.627-.168.9-.499 1.201-.82 1.23-.696.065-1.225-.46-1.9-.902-1.056-.693-1.653-1.124-2.678-1.8-1.185-.78-.417-1.21.258-1.91.177-.184 3.247-2.977 3.307-3.23.007-.032.014-.15-.056-.212s-.174-.041-.249-.024c-.106.024-1.793 1.14-5.061 3.345-.48.33-.913.49-1.302.48-.428-.008-1.252-.241-1.865-.44-.752-.245-1.349-.374-1.297-.789.027-.216.325-.437.893-.663 3.498-1.524 5.83-2.529 6.998-3.014 3.332-1.386 4.025-1.627 4.476-1.635z"/>
                    </svg>
                </div>
                <span class="integration-label">Telegram</span>
            </div>
        </div>
    </div>
</section>


<!-- How It Works Section -->
<section class="section how-it-works" id="how-it-works">
    <div class="container">
        <div class="section-header">
            <h2 class="section-title" itemprop="name">Начните за 5 минут</h2>
            <p class="section-subtitle" itemprop="description">Простая пошаговая инструкция для подключения живого чата</p>
        </div>

        <div class="steps">
            <div class="step">
                <div class="step-line">
                    <div class="step-number">1</div>
                    <div class="step-connector"></div>
                </div>
                <div class="step-content">
                    <h3 class="step-title">Сгенерируйте API ключ</h3>
                    <p class="step-description">
                        Подключитесь к консоли Docker контейнера и выполните команду для генерации токена.
                    </p>
                    <div class="step-code">
                        <p><span class="highlight">docker compose exec</span> app bash</p>
                        <p><span class="highlight">php artisan</span> app:generate-token live_chat https://node.{domain}/push-message</p>
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-line">
                    <div class="step-number">2</div>
                    <div class="step-connector"></div>
                </div>
                <div class="step-content">
                    <h3 class="step-title">Настройте переменные окружения</h3>
                    <p class="step-description">
                        Добавьте полученный токен и разрешенные домены в файл .env
                    </p>
                    <div class="step-code">
                        <code>API_TOKEN=<span class="string">"ваш_сгенерированный_токен"</span>
                            ALLOWED_ORIGINS=<span class="string">"https://example.com,https://admin.example.com"</span>
                            VITE_APP_NAME=<span class="string">"${APP_NAME}"</span></code>
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-line">
                    <div class="step-number">3</div>
                    <div class="step-connector"></div>
                </div>
                <div class="step-content">
                    <h3 class="step-title">Соберите виджет</h3>
                    <p class="step-description">
                        Установите зависимости и соберите production-версию виджета с помощью Vite.
                    </p>
                    <div class="step-code">
                        <p><span class="highlight">npm install</span></p>
                        <p><span class="highlight">npm run build</span></p>
                    </div>
                </div>
            </div>

            <div class="step">
                <div class="step-line">
                    <div class="step-number">4</div>
                    <div class="step-connector"></div>
                </div>
                <div class="step-content">
                    <h3 class="step-title">Добавьте на сайт</h3>
                    <p class="step-description">
                        Подключите стили в head и скрипты перед закрывающим тегом body.
                    </p>
                    <div class="step-code">
                        <p><span class="highlight">&lt;!-- В &lt;head&gt; --&gt;</span>
                        <p style="padding-bottom: 10px">&lt;link rel=<span class="string">"stylesheet"</span> href=<span class="string">"https://{домен}/live_chat/css/style.css"</span>&gt;</p>
                        <p><span class="highlight">&lt;!-- Перед &lt;/body&gt; --&gt;</span></p>
                        <p>&lt;script src=<span class="string">"https://cdn.socket.io/4.7.2/socket.io.min.js"</span>&gt;&lt;/script&gt;</p>
                        <p>&lt;script src=<span class="string">"https://{домен}/live_chat/dist/widget.js?token={токен}"</span> defer&gt;&lt;/script&gt;</p></p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer itemscope itemtype="https://schema.org/WPFooter">
    <div class="container">
        <div class="footer-content">
            <a href="#" class="footer-logo">
                <div class="logo-icon">
                    <i class="fas fa-comment-dots"></i>
                </div>
                <div class="logo-text" itemprop="name">Live Chat</div>
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
            Open Source проект | Лицензия MIT | Часть TG Support Bot
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
