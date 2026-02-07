<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Live Chat - Виджет живого чата для TG Support Bot</title>
    <meta name="description" content="Бесплатный виджет живого чата для вашего сайта с интеграцией в Telegram. Мгновенные ответы клиентам прямо из Telegram.">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        :root {
            --bg-primary: #0a0a0f;
            --bg-secondary: #12121a;
            --bg-card: #16161f;
            --border-color: #2a2a3a;
            --text-primary: #f5f5f7;
            --text-secondary: #8b8b9e;
            --accent: #3b82f6;
            --accent-hover: #2563eb;
            --accent-glow: rgba(59, 130, 246, 0.15);
            --success: #22c55e;
            --gradient-start: #3b82f6;
            --gradient-end: #8b5cf6;
        }

        html {
            scroll-behavior: smooth;
        }

        body {
            font-family: 'Inter', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-primary);
            color: var(--text-primary);
            line-height: 1.6;
            overflow-x: hidden;
        }

        a {
            color: inherit;
            text-decoration: none;
        }

        .container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 24px;
        }

        /* Header */
        .header {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            z-index: 100;
            background: rgba(10, 10, 15, 0.8);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid var(--border-color);
        }

        .header-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            height: 72px;
        }

        .logo {
            display: flex;
            align-items: center;
            gap: 12px;
            font-weight: 700;
            font-size: 18px;
        }

        .logo-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .logo-icon svg {
            width: 24px;
            height: 24px;
            color: white;
        }

        .header-nav {
            display: flex;
            align-items: center;
            gap: 32px;
        }

        .header-nav a {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 500;
            transition: color 0.2s;
        }

        .header-nav a:hover {
            color: var(--text-primary);
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.2s;
            cursor: pointer;
            border: none;
        }

        .btn-primary {
            background: var(--accent);
            color: white;
        }

        .btn-primary:hover {
            background: var(--accent-hover);
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(59, 130, 246, 0.3);
        }

        .btn-secondary {
            background: var(--bg-card);
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-secondary:hover {
            background: var(--bg-secondary);
            border-color: var(--accent);
        }

        .btn-outline {
            background: transparent;
            color: var(--text-primary);
            border: 1px solid var(--border-color);
        }

        .btn-outline:hover {
            border-color: var(--accent);
            background: var(--accent-glow);
        }

        /* Hero Section */
        .hero {
            padding: 160px 0 100px;
            position: relative;
            overflow: hidden;
        }

        .hero::before {
            content: '';
            position: absolute;
            top: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 600px;
            background: radial-gradient(ellipse, var(--accent-glow) 0%, transparent 70%);
            pointer-events: none;
        }

        .hero-content {
            position: relative;
            text-align: center;
            max-width: 800px;
            margin: 0 auto;
        }

        .hero-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 8px 16px;
            background: var(--accent-glow);
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 100px;
            font-size: 13px;
            font-weight: 500;
            color: var(--accent);
            margin-bottom: 24px;
        }

        .hero-badge svg {
            width: 16px;
            height: 16px;
        }

        .hero-title {
            font-size: clamp(40px, 6vw, 64px);
            font-weight: 800;
            line-height: 1.1;
            margin-bottom: 24px;
            letter-spacing: -0.02em;
        }

        .hero-title span {
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .hero-description {
            font-size: 18px;
            color: var(--text-secondary);
            margin-bottom: 40px;
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
        }

        .hero-buttons {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 16px;
            flex-wrap: wrap;
        }

        .hero-demo {
            margin-top: 80px;
            position: relative;
        }

        .demo-window {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 40px 80px rgba(0, 0, 0, 0.4);
        }

        .demo-header {
            display: flex;
            align-items: center;
            gap: 8px;
            padding: 16px 20px;
            background: var(--bg-secondary);
            border-bottom: 1px solid var(--border-color);
        }

        .demo-dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
        }

        .demo-dot.red { background: #ff5f56; }
        .demo-dot.yellow { background: #ffbd2e; }
        .demo-dot.green { background: #27c93f; }

        .demo-content {
            display: flex;
            height: 400px;
        }

        .demo-site {
            flex: 1;
            background: var(--bg-primary);
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }

        .demo-site-text {
            color: var(--text-secondary);
            font-size: 14px;
        }

        .demo-chat {
            width: 320px;
            background: var(--bg-card);
            border-left: 1px solid var(--border-color);
            display: flex;
            flex-direction: column;
        }

        .demo-chat-header {
            padding: 16px;
            border-bottom: 1px solid var(--border-color);
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .demo-avatar {
            width: 40px;
            height: 40px;
            background: linear-gradient(135deg, var(--gradient-start), var(--gradient-end));
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .demo-avatar svg {
            width: 20px;
            height: 20px;
            color: white;
        }

        .demo-chat-title {
            font-weight: 600;
            font-size: 14px;
        }

        .demo-chat-status {
            font-size: 12px;
            color: var(--success);
            display: flex;
            align-items: center;
            gap: 4px;
        }

        .demo-chat-status::before {
            content: '';
            width: 6px;
            height: 6px;
            background: var(--success);
            border-radius: 50%;
        }

        .demo-messages {
            flex: 1;
            padding: 16px;
            overflow-y: auto;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        .demo-message {
            max-width: 80%;
            padding: 10px 14px;
            border-radius: 12px;
            font-size: 13px;
            line-height: 1.4;
            animation: fadeInUp 0.3s ease;
        }

        .demo-message.user {
            background: var(--accent);
            color: white;
            align-self: flex-end;
            border-bottom-right-radius: 4px;
        }

        .demo-message.support {
            background: var(--bg-secondary);
            color: var(--text-primary);
            align-self: flex-start;
            border-bottom-left-radius: 4px;
        }

        .demo-input {
            padding: 16px;
            border-top: 1px solid var(--border-color);
            display: flex;
            gap: 8px;
        }

        .demo-input input {
            flex: 1;
            padding: 10px 14px;
            background: var(--bg-secondary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            color: var(--text-primary);
            font-size: 13px;
            outline: none;
        }

        .demo-input input:focus {
            border-color: var(--accent);
        }

        .demo-input button {
            width: 40px;
            height: 40px;
            background: var(--accent);
            border: none;
            border-radius: 8px;
            color: white;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.2s;
        }

        .demo-input button:hover {
            background: var(--accent-hover);
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Features Section */
        .section {
            padding: 100px 0;
        }

        .section-header {
            text-align: center;
            max-width: 600px;
            margin: 0 auto 60px;
        }

        .section-label {
            font-size: 13px;
            font-weight: 600;
            color: var(--accent);
            text-transform: uppercase;
            letter-spacing: 0.1em;
            margin-bottom: 16px;
        }

        .section-title {
            font-size: clamp(32px, 4vw, 42px);
            font-weight: 700;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .section-description {
            font-size: 16px;
            color: var(--text-secondary);
        }

        .features-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
            gap: 24px;
        }

        .feature-card {
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 16px;
            padding: 32px;
            transition: all 0.3s;
        }

        .feature-card:hover {
            border-color: var(--accent);
            transform: translateY(-4px);
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
        }

        .feature-icon {
            width: 48px;
            height: 48px;
            background: var(--accent-glow);
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 20px;
        }

        .feature-icon svg {
            width: 24px;
            height: 24px;
            color: var(--accent);
        }

        .feature-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 12px;
        }

        .feature-description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        /* How It Works Section */
        .how-it-works {
            background: var(--bg-secondary);
        }

        .steps {
            display: flex;
            flex-direction: column;
            gap: 0;
            max-width: 700px;
            margin: 0 auto;
        }

        .step {
            display: flex;
            gap: 24px;
            position: relative;
        }

        .step-line {
            display: flex;
            flex-direction: column;
            align-items: center;
        }

        .step-number {
            width: 48px;
            height: 48px;
            background: var(--accent);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 18px;
            flex-shrink: 0;
        }

        .step-connector {
            width: 2px;
            flex: 1;
            background: var(--border-color);
            min-height: 40px;
        }

        .step:last-child .step-connector {
            display: none;
        }

        .step-content {
            padding-bottom: 40px;
        }

        .step:last-child .step-content {
            padding-bottom: 0;
        }

        .step-title {
            font-size: 18px;
            font-weight: 600;
            margin-bottom: 8px;
        }

        .step-description {
            font-size: 14px;
            color: var(--text-secondary);
            line-height: 1.6;
        }

        .step-code {
            margin-top: 16px;
            background: var(--bg-primary);
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 16px;
            overflow-x: auto;
        }

        .step-code code {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            color: var(--text-secondary);
            white-space: pre;
        }

        .step-code code .highlight {
            color: var(--accent);
        }

        .step-code code .string {
            color: #22c55e;
        }

        /* Integration Section */
        .integration-visual {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 40px;
            flex-wrap: wrap;
            margin-top: 60px;
        }

        .integration-item {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 12px;
        }

        .integration-icon {
            width: 80px;
            height: 80px;
            background: var(--bg-card);
            border: 1px solid var(--border-color);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
        }

        .integration-icon:hover {
            border-color: var(--accent);
            transform: scale(1.1);
        }

        .integration-icon svg {
            width: 40px;
            height: 40px;
        }

        .integration-arrow {
            color: var(--text-secondary);
        }

        .integration-arrow svg {
            width: 32px;
            height: 32px;
        }

        .integration-label {
            font-size: 13px;
            color: var(--text-secondary);
            font-weight: 500;
        }

        /* CTA Section */
        .cta {
            text-align: center;
            padding: 120px 0;
            position: relative;
        }

        .cta::before {
            content: '';
            position: absolute;
            bottom: 0;
            left: 50%;
            transform: translateX(-50%);
            width: 800px;
            height: 400px;
            background: radial-gradient(ellipse, var(--accent-glow) 0%, transparent 70%);
            pointer-events: none;
        }

        .cta-content {
            position: relative;
        }

        .cta-title {
            font-size: clamp(32px, 4vw, 48px);
            font-weight: 700;
            margin-bottom: 16px;
            letter-spacing: -0.02em;
        }

        .cta-description {
            font-size: 18px;
            color: var(--text-secondary);
            margin-bottom: 40px;
        }

        /* Footer */
        .footer {
            padding: 40px 0;
            border-top: 1px solid var(--border-color);
        }

        .footer-inner {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: 20px;
        }

        .footer-text {
            font-size: 14px;
            color: var(--text-secondary);
        }

        .footer-links {
            display: flex;
            align-items: center;
            gap: 24px;
        }

        .footer-links a {
            color: var(--text-secondary);
            font-size: 14px;
            transition: color 0.2s;
        }

        .footer-links a:hover {
            color: var(--text-primary);
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: var(--text-primary);
            cursor: pointer;
            padding: 8px;
        }

        @media (max-width: 768px) {
            .header-nav {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .demo-content {
                flex-direction: column;
                height: auto;
            }

            .demo-site {
                padding: 24px;
                min-height: 150px;
            }

            .demo-chat {
                width: 100%;
                border-left: none;
                border-top: 1px solid var(--border-color);
            }

            .integration-visual {
                gap: 24px;
            }

            .integration-arrow {
                transform: rotate(90deg);
            }

            .footer-inner {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
<!-- Header -->
<header class="header">
    <div class="container header-inner">
        <a href="/" class="logo">
            <div class="logo-icon">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                </svg>
            </div>
            TG Support Bot
        </a>
        <nav class="header-nav">
            <a href="#features">Возможности</a>
            <a href="#how-it-works">Установка</a>
            <a href="https://github.com/prog-time/tg-support-bot" target="_blank" class="btn btn-outline">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                    <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                </svg>
                GitHub
            </a>
        </nav>
        <button class="mobile-menu-btn" aria-label="Menu">
            <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <line x1="3" y1="12" x2="21" y2="12"></line>
                <line x1="3" y1="6" x2="21" y2="6"></line>
                <line x1="3" y1="18" x2="21" y2="18"></line>
            </svg>
        </button>
    </div>
</header>

<!-- Hero Section -->
<section class="hero">
    <div class="container">
        <div class="hero-content">
            <div class="hero-badge">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"></polygon>
                </svg>
                Open Source модуль
            </div>
            <h1 class="hero-title">
                <span>Живой чат</span> для вашего сайта с Telegram
            </h1>
            <p class="hero-description">
                Общайтесь с клиентами в реальном времени прямо из Telegram.
                Мгновенная доставка сообщений через WebSocket.
            </p>
            <div class="hero-buttons">
                <a href="#how-it-works" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14.5 10c-.83 0-1.5-.67-1.5-1.5v-5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5z"></path>
                        <path d="M20.5 10H19V8.5c0-.83.67-1.5 1.5-1.5s1.5.67 1.5 1.5-.67 1.5-1.5 1.5z"></path>
                        <path d="M9.5 14c.83 0 1.5.67 1.5 1.5v5c0 .83-.67 1.5-1.5 1.5S8 21.33 8 20.5v-5c0-.83.67-1.5 1.5-1.5z"></path>
                        <path d="M3.5 14H5v1.5c0 .83-.67 1.5-1.5 1.5S2 16.33 2 15.5 2.67 14 3.5 14z"></path>
                        <path d="M14 14.5c0-.83.67-1.5 1.5-1.5h5c.83 0 1.5.67 1.5 1.5s-.67 1.5-1.5 1.5h-5c-.83 0-1.5-.67-1.5-1.5z"></path>
                        <path d="M15.5 19H14v1.5c0 .83.67 1.5 1.5 1.5s1.5-.67 1.5-1.5-.67-1.5-1.5-1.5z"></path>
                        <path d="M10 9.5C10 8.67 9.33 8 8.5 8h-5C2.67 8 2 8.67 2 9.5S2.67 11 3.5 11h5c.83 0 1.5-.67 1.5-1.5z"></path>
                        <path d="M8.5 5H10V3.5C10 2.67 9.33 2 8.5 2S7 2.67 7 3.5 7.67 5 8.5 5z"></path>
                    </svg>
                    Инструкция по установке
                </a>
                <a href="https://tg-support-bot.ru/" class="btn btn-secondary">
                    Попробовать демо
                </a>
            </div>
        </div>

        <div class="hero-demo">
            <div class="demo-window">
                <div class="demo-header">
                    <span class="demo-dot red"></span>
                    <span class="demo-dot yellow"></span>
                    <span class="demo-dot green"></span>
                </div>
                <div class="demo-content">
                    <div class="demo-site">
                        <div class="demo-site-text">Ваш сайт</div>
                    </div>
                    <div class="demo-chat">
                        <div class="demo-chat-header">
                            <div class="demo-avatar">
                                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <div class="demo-chat-title">Поддержка</div>
                                <div class="demo-chat-status">Онлайн</div>
                            </div>
                        </div>
                        <div class="demo-messages" id="demoMessages">
                            <div class="demo-message support">Здравствуйте! Чем могу помочь?</div>
                        </div>
                        <div class="demo-input">
                            <input type="text" placeholder="Введите сообщение..." id="demoInput">
                            <button id="demoSend">
                                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                                    <line x1="22" y1="2" x2="11" y2="13"></line>
                                    <polygon points="22 2 15 22 11 13 2 9 22 2"></polygon>
                                </svg>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- Features Section -->
<section class="section" id="features">
    <div class="container">
        <div class="section-header">
            <div class="section-label">Возможности</div>
            <h2 class="section-title">Все для эффективной поддержки</h2>
            <p class="section-description">
                Мощный инструмент для общения с клиентами, интегрированный с Telegram
            </p>
        </div>

        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M13 2L3 14h9l-1 8 10-12h-9l1-8z"></path>
                    </svg>
                </div>
                <h3 class="feature-title">Мгновенная доставка</h3>
                <p class="feature-description">
                    WebSocket соединение через Socket.io обеспечивает мгновенную доставку сообщений в обе стороны без задержек.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path>
                    </svg>
                </div>
                <h3 class="feature-title">Telegram интеграция</h3>
                <p class="feature-description">
                    Для каждого посетителя создается отдельная тема в Telegram. Отвечайте клиентам, не покидая мессенджер.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                    </svg>
                </div>
                <h3 class="feature-title">Уникальные сессии</h3>
                <p class="feature-description">
                    Каждому посетителю присваивается уникальный ключ. История сообщений сохраняется на время сессии.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M21 16V8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16z"></path>
                        <polyline points="3.27 6.96 12 12.01 20.73 6.96"></polyline>
                        <line x1="12" y1="22.08" x2="12" y2="12"></line>
                    </svg>
                </div>
                <h3 class="feature-title">Docker Ready</h3>
                <p class="feature-description">
                    Готовое решение для Docker Compose. Node.js работает в отдельном контейнере для максимальной производительности.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"></path>
                        <polyline points="14 2 14 8 20 8"></polyline>
                        <line x1="16" y1="13" x2="8" y2="13"></line>
                        <line x1="16" y1="17" x2="8" y2="17"></line>
                        <polyline points="10 9 9 9 8 9"></polyline>
                    </svg>
                </div>
                <h3 class="feature-title">Open Source</h3>
                <p class="feature-description">
                    Полностью открытый исходный код. Настраивайте виджет под свои нужды и добавляйте собственную логику.
                </p>
            </div>

            <div class="feature-card">
                <div class="feature-icon">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <polyline points="16 18 22 12 16 6"></polyline>
                        <polyline points="8 6 2 12 8 18"></polyline>
                    </svg>
                </div>
                <h3 class="feature-title">Простая интеграция</h3>
                <p class="feature-description">
                    Подключите виджет к любому сайту всего двумя строками кода. CSS и JS файлы размещены на вашем сервере.
                </p>
            </div>
        </div>
    </div>
</section>

<!-- Integration Visual -->
<section class="section">
    <div class="container">
        <div class="section-header">
            <div class="section-label">Интеграция</div>
            <h2 class="section-title">Как это работает</h2>
            <p class="section-description">
                Сообщения мгновенно синхронизируются между сайтом и Telegram
            </p>
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
            <div class="section-label">Установка</div>
            <h2 class="section-title">Начните за 5 минут</h2>
            <p class="section-description">
                Простая пошаговая инструкция для подключения живого чата
            </p>
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
                        <code><span class="highlight">docker compose exec</span> app bash
                            <span class="highlight">php artisan</span> app:generate-token live_chat https://node.{domain}/push-message</code>
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
                        <code><span class="highlight">npm install</span>
                            <span class="highlight">npm run build</span></code>
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
                        <code><span class="highlight">&lt;!-- В &lt;head&gt; --&gt;</span>
                            &lt;link rel=<span class="string">"stylesheet"</span> href=<span class="string">"https://{домен}/live_chat/css/style.css"</span>&gt;

                            <span class="highlight">&lt;!-- Перед &lt;/body&gt; --&gt;</span>
                            &lt;script src=<span class="string">"https://cdn.socket.io/4.7.2/socket.io.min.js"</span>&gt;&lt;/script&gt;
                            &lt;script src=<span class="string">"https://{домен}/live_chat/dist/widget.js?token={токен}"</span> defer&gt;&lt;/script&gt;</code>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- CTA Section -->
<section class="cta">
    <div class="container">
        <div class="cta-content">
            <h2 class="cta-title">Готовы подключить живой чат?</h2>
            <p class="cta-description">
                Начните общаться с клиентами в реальном времени уже сегодня
            </p>
            <div class="hero-buttons">
                <a href="https://github.com/prog-time/tg-support-bot/wiki" target="_blank" class="btn btn-primary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                        <path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"></path>
                        <path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"></path>
                    </svg>
                    Документация
                </a>
                <a href="https://github.com/prog-time/tg-support-bot" target="_blank" class="btn btn-secondary">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12 0c-6.626 0-12 5.373-12 12 0 5.302 3.438 9.8 8.207 11.387.599.111.793-.261.793-.577v-2.234c-3.338.726-4.033-1.416-4.033-1.416-.546-1.387-1.333-1.756-1.333-1.756-1.089-.745.083-.729.083-.729 1.205.084 1.839 1.237 1.839 1.237 1.07 1.834 2.807 1.304 3.492.997.107-.775.418-1.305.762-1.604-2.665-.305-5.467-1.334-5.467-5.931 0-1.311.469-2.381 1.236-3.221-.124-.303-.535-1.524.117-3.176 0 0 1.008-.322 3.301 1.23.957-.266 1.983-.399 3.003-.404 1.02.005 2.047.138 3.006.404 2.291-1.552 3.297-1.23 3.297-1.23.653 1.653.242 2.874.118 3.176.77.84 1.235 1.911 1.235 3.221 0 4.609-2.807 5.624-5.479 5.921.43.372.823 1.102.823 2.222v3.293c0 .319.192.694.801.576 4.765-1.589 8.199-6.086 8.199-11.386 0-6.627-5.373-12-12-12z"/>
                    </svg>
                    GitHub репозиторий
                </a>
            </div>
        </div>
    </div>
</section>

<!-- Footer -->
<footer class="footer">
    <div class="container footer-inner">
        <p class="footer-text">
            2025 TG Support Bot. Open Source проект от Prog-Time.
        </p>
        <div class="footer-links">
            <a href="https://github.com/prog-time/tg-support-bot" target="_blank">GitHub</a>
            <a href="https://github.com/prog-time/tg-support-bot/wiki" target="_blank">Wiki</a>
            <a href="https://t.me/prog_time_web" target="_blank">Telegram</a>
        </div>
    </div>
</footer>

<script>
    // Demo Chat Animation
    const messages = [
        { text: 'Здравствуйте! Чем могу помочь?', type: 'support' },
        { text: 'Привет! Как подключить живой чат?', type: 'user' },
        { text: 'Отличный вопрос! Следуйте инструкции на этой странице - всего 4 шага.', type: 'support' },
        { text: 'А это работает с любым сайтом?', type: 'user' },
        { text: 'Да! Виджет можно подключить к любому сайту с помощью двух строк кода.', type: 'support' }
    ];

    let currentIndex = 1;
    const messagesContainer = document.getElementById('demoMessages');
    const demoInput = document.getElementById('demoInput');
    const demoSend = document.getElementById('demoSend');

    function addMessage() {
        if (currentIndex >= messages.length) {
            currentIndex = 1;
            messagesContainer.innerHTML = '<div class="demo-message support">Здравствуйте! Чем могу помочь?</div>';
        }

        const msg = messages[currentIndex];
        const msgEl = document.createElement('div');
        msgEl.className = 'demo-message ' + msg.type;
        msgEl.textContent = msg.text;
        messagesContainer.appendChild(msgEl);
        messagesContainer.scrollTop = messagesContainer.scrollHeight;
        currentIndex++;
    }

    setInterval(addMessage, 3000);

    // Manual send
    function sendMessage() {
        const text = demoInput.value.trim();
        if (text) {
            const msgEl = document.createElement('div');
            msgEl.className = 'demo-message user';
            msgEl.textContent = text;
            messagesContainer.appendChild(msgEl);
            messagesContainer.scrollTop = messagesContainer.scrollHeight;
            demoInput.value = '';

            // Auto response
            setTimeout(() => {
                const responseEl = document.createElement('div');
                responseEl.className = 'demo-message support';
                responseEl.textContent = 'Спасибо за сообщение! Наш менеджер скоро ответит.';
                messagesContainer.appendChild(responseEl);
                messagesContainer.scrollTop = messagesContainer.scrollHeight;
            }, 1000);
        }
    }

    demoSend.addEventListener('click', sendMessage);
    demoInput.addEventListener('keypress', (e) => {
        if (e.key === 'Enter') sendMessage();
    });

    // Smooth scroll for anchor links
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            e.preventDefault();
            const target = document.querySelector(this.getAttribute('href'));
            if (target) {
                target.scrollIntoView({ behavior: 'smooth', block: 'start' });
            }
        });
    });
</script>
</body>
</html>
