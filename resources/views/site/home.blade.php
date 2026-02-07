<?php

$schemaData = [
    '@context' => 'https://schema.org',
    '@type' => 'SoftwareApplication',
    'name' => 'TG Support Bot',
    'description' => 'Бесплатный Telegram бот для технической поддержки. Open Source решение для организации службы поддержки в Telegram и ВКонтакте.',
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
        'Интеграция с Telegram',
        'Интеграция с ВКонтакте',
        'Безопасность',
        'Мгновенные ответы',
        'Командная работа',
        'Умная автоматизация',
    ],
];

?>

<!DOCTYPE html>
<html lang="ru" itemscope itemtype="https://schema.org/WebPage">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title itemprop="name">TG Support Bot - Telegram бот для технической поддержки | Open Source решение</title>

    <link rel="icon" type="image/svg" sizes="32x32" href="{{ asset('storage/favicon.svg') }}">

    @include('site.hide.metrika')

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

    <link rel="stylesheet" href="../src/css/basic.css">
</head>
<body itemscope itemtype="https://schema.org/WebPage">
    <!-- JSON-LD Schema.org markup -->
    <script type="application/ld+json"><?= json_encode($schemaData) ?></script>

    <!-- Header -->
    <header itemscope itemtype="https://schema.org/WPHeader">
        <div class="container">
            <div class="header-content">
                <a href="#" class="logo" itemprop="url">
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
                        <span itemprop="ratingValue">170</span>+
                        <meta itemprop="ratingCount" content="170">
                    </div>
                    <div class="stat-label">Звезд</div>
                </div>
                <div class="stat-item">
                    <div class="stat-icon">
                        <i class="fas fa-code-branch"></i>
                    </div>
                    <div class="stat-value">
                        37+
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
                <a href="https://rutube.ru/video/70b393db88e0a92ab902a9b51e78c7e3/" class="btn-video btn-rutube">
                    <i class="fas fa-play-circle"></i>
                    Rutube
                </a>
                <a href="https://youtu.be/ZAtP9qJ5q9M" class="btn-video btn-youtube">
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
