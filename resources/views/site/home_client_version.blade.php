<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TG Support Bot | Ваш бот успешно клонирован</title>
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
            --success-green: #4CAF50;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background: var(--telegram-darker);
            color: var(--telegram-light);
            line-height: 1.6;
            min-height: 100vh;
            padding: 20px;
        }

        .container {
            max-width: 800px;
            width: 100%;
            background: var(--telegram-dark);
            border-radius: 24px;
            padding: 60px 40px;
            margin: 40px auto;
            text-align: center;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(0, 136, 204, 0.2);
        }

        .success-icon {
            width: 100px;
            height: 100px;
            background: rgba(76, 175, 80, 0.1);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 30px;
            border: 2px solid var(--success-green);
        }

        .success-icon i {
            font-size: 48px;
            color: var(--success-green);
        }

        h1 {
            font-size: 2.5rem;
            font-weight: 800;
            margin-bottom: 20px;
            color: var(--telegram-light-blue);
        }

        .subtitle {
            font-size: 1.25rem;
            color: var(--telegram-gray);
            margin-bottom: 40px;
            line-height: 1.7;
        }

        .project-info {
            background: rgba(0, 136, 204, 0.1);
            border-radius: 16px;
            padding: 30px;
            margin: 40px 0;
            border: 1px solid rgba(0, 136, 204, 0.2);
        }

        .project-info p {
            margin-bottom: 20px;
            line-height: 1.7;
        }

        .project-info strong {
            color: var(--telegram-light-blue);
        }

        .original-project {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            background: rgba(255, 255, 255, 0.1);
            padding: 16px 24px;
            border-radius: 12px;
            margin: 30px 0;
            text-decoration: none;
            color: white;
            transition: all 0.3s ease;
        }

        .original-project:hover {
            background: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }

        .original-project i {
            color: var(--telegram-blue);
            font-size: 24px;
        }

        .next-steps {
            text-align: left;
            margin: 40px 0;
        }

        .next-steps h2 {
            font-size: 1.5rem;
            margin-bottom: 20px;
            text-align: center;
        }

        .steps-list {
            display: flex;
            flex-direction: column;
            gap: 20px;
        }

        .step {
            display: flex;
            gap: 20px;
            align-items: flex-start;
            padding: 20px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .step-number {
            background: var(--telegram-blue);
            width: 36px;
            height: 36px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            flex-shrink: 0;
        }

        .step-content h3 {
            margin-bottom: 8px;
            color: var(--telegram-light-blue);
        }

        .step-content p {
            color: var(--telegram-gray);
            font-size: 0.95rem;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            background: var(--telegram-blue);
            color: white;
            padding: 16px 32px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 600;
            font-size: 16px;
            transition: all 0.3s ease;
            margin-top: 20px;
            border: none;
            cursor: pointer;
        }

        .btn:hover {
            background: var(--telegram-light-blue);
            transform: translateY(-3px);
            box-shadow: 0 10px 25px rgba(0, 136, 204, 0.3);
        }

        .btn-secondary {
            background: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.2);
        }

        .buttons {
            display: flex;
            gap: 16px;
            justify-content: center;
            flex-wrap: wrap;
            margin-top: 30px;
        }

        .footer {
            margin-top: 40px;
            color: var(--telegram-gray);
            font-size: 0.9rem;
            text-align: center;
        }

        @media (max-width: 768px) {
            .container {
                padding: 40px 20px;
                margin: 20px auto;
            }
            
            h1 {
                font-size: 2rem;
            }
            
            .subtitle {
                font-size: 1.1rem;
            }
            
            .buttons {
                flex-direction: column;
                align-items: center;
            }
            
            .btn {
                width: 100%;
                justify-content: center;
            }
            
            .step {
                flex-direction: column;
                text-align: center;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="success-icon">
            <i class="fas fa-check"></i>
        </div>
        
        <h1>Поздравляем!</h1>
        <p class="subtitle">Вы успешно клонировали TG Support Bot</p>
        
        <div class="project-info">
            <p>Этот бот является копией проекта <strong>TG Support Bot</strong> - бесплатного open-source решения для организации технической поддержки в Telegram и ВКонтакте.</p>
            <p>Теперь вы можете настроить и использовать бота под свои нужды.</p>
            
            <a href="{{ env('AUTHOR_GITHUB_PROJECT', 'https://github.com/prog-time') }}" target="_blank" class="original-project">
                <div>
                    <div>Оригинальный проект</div>
                    <div style="font-size: 0.8rem; color: var(--telegram-gray);">github.com/prog-time/tg-support-bot</div>
                </div>
            </a>
        </div>
        
        <div class="next-steps">
            <h2>Следующие шаги</h2>
            <div class="steps-list">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h3>Настройка конфигурации</h3>
                        <p>Откройте файл конфигурации и внесите необходимые параметры для подключения к Telegram API и базе данных</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h3>Запуск бота</h3>
                        <p>Используйте Docker Compose или запустите приложение напрямую согласно документации</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h3>Интеграция с Telegram</h3>
                        <p>Создайте своего бота через @BotFather и добавьте токен в конфигурацию</p>
                    </div>
                </div>
                
                <div class="step">
                    <div class="step-number">4</div>
                    <div class="step-content">
                        <h3>Настройка группы поддержки</h3>
                        <p>Создайте приватную группу в Telegram и добавьте туда своего бота как администратора</p>
                    </div>
                </div>
            </div>
        </div>
        
        <div class="buttons">
            <a href="{{ env('AUTHOR_WIKI_PAGE', 'https://github.com/prog-time') }}" target="_blank" class="btn">
                <i class="fas fa-book"></i>
                Документация
            </a>
            <a href="{{ env('AUTHOR_TG_GROUP', 'https://github.com/prog-time') }}" target="_blank" class="btn btn-secondary">
                <i class="fab fa-telegram-plane"></i>
                Сообщество поддержки
            </a>
        </div>
        
        <div class="footer">
            <p>Open Source проект под лицензией MIT</p>
        </div>
    </div>
</body>
</html>