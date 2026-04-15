# Правила участия в проекте (Contributing Guidelines)

Спасибо за интерес к проекту **TG Support Bot**! 🎉

Мы приветствуем вклад сообщества и будем рады вашим идеям, исправлениям и улучшениям.

Пожалуйста, внимательно ознакомьтесь с данным документом перед началом работы.

---

## Содержание

- [Кодекс поведения](#-кодекс-поведения)
- [С чего начать](#-с-чего-начать)
- [Как внести вклад](#-как-внести-вклад)
- [Процесс разработки](#-процесс-разработки)
- [Стандарты кода](#-стандарты-кода)
- [Тестирование](#-тестирование)
- [Правила коммитов](#-правила-коммитов)
- [Pull Request процесс](#-pull-request-процесс)
- [Сообщения об ошибках](#-сообщения-об-ошибках)
- [Предложения и идеи](#-предложения-и-идеи)
- [Документация](#-документация)
- [Вопросы и поддержка](#-вопросы-и-поддержка)

---

## Кодекс поведения

### Наши принципы

Участвуя в этом проекте, вы соглашаетесь соблюдать следующие принципы:

- **Уважение**: Уважайте других участников проекта, их мнения и точки зрения
- **Конструктивность**: Давайте конструктивную критику и будьте готовы принимать её
- **Профессионализм**: Общайтесь профессионально, избегайте оскорблений и личных нападок
- **Инклюзивность**: Проект открыт для всех, независимо от уровня опыта, происхождения или убеждений
- **Открытость**: Будьте открыты к обсуждению и компромиссам

### Недопустимое поведение

- Оскорбления, троллинг, провокации
- Дискриминация по любому признаку
- Публикация личной информации других людей без их согласия
- Спам и реклама, не связанная с проектом
- Любые действия, которые можно расценить как домогательства

Нарушение кодекса поведения может привести к блокировке участника.

---

## С чего начать

### Для новичков в Open Source

Если вы впервые участвуете в open source проекте, начните с:

1. **Исправление багов из Issues**
2. **Документация** — исправление опечаток, улучшение примеров, добавление пояснений
3. **Тестирование** — написание тестов для существующего функционала
4. **Код-ревью Pull Request'ов**

---

## Как внести вклад

### 1. Fork репозитория

Создайте fork репозитория в свой аккаунт GitHub:

```bash
# Клонируйте ваш fork
git clone https://github.com/YOUR_USERNAME/tg-support-bot.git
cd tg-support-bot

# Добавьте upstream репозиторий
git remote add upstream https://github.com/prog-time/tg-support-bot.git

# Проверьте удалённые репозитории
git remote -v
```

### 2. Синхронизация с основным репозиторием

Перед началом работы всегда синхронизируйте ваш fork:

```bash
git fetch upstream
git checkout main
git merge upstream/main
git push origin main
```

### 3. Создание ветки

Используйте отдельную ветку для каждой задачи. Название ветки должно содержать номер Issue:

```bash
# Формат: issues-{номер}
git checkout -b issues-123

# Примеры хороших названий веток:
# issues-45-fix-telegram-webhook
# issues-78-add-vk-stickers
# issues-102-update-readme
```

**Правила именования веток:**

- Всегда указывайте номер Issue
- Используйте kebab-case (слова через дефис)
- Будьте краткими и понятными
- Используйте английский язык для описания

### 4. Настройка Git Hooks (обязательно)

Для локальной проверки кода необходимо настроить git hooks.

**Pre-commit hook** (проверка перед каждым коммитом):

```bash
# Создайте файл .git/hooks/pre-commit
cat > .git/hooks/pre-commit << 'EOF'
#!/bin/bash
set -e

bash linting/pre-commit-check.sh
EOF

# Сделайте файл исполняемым
chmod +x .git/hooks/pre-commit
```

**Pre-push hook** (проверка перед push):

```bash
# Создайте файл .git/hooks/pre-push
cat > .git/hooks/pre-push << 'EOF'
#!/bin/bash
set -e

bash linting/pre-push-check.sh
EOF

# Сделайте файл исполняемым
chmod +x .git/hooks/pre-push
```

**Что проверяют хуки:**

- `pre-commit`: Laravel Pint (форматирование кода)
- `pre-push`: PHPStan (статический анализ), PHPUnit (тесты)

---

## Процесс разработки

### 1. Локальная разработка

```bash
# Установите зависимости
docker compose up -d
docker exec -it pet composer install

# Скопируйте конфигурацию для тестов
cp .env.example .env.testing

# Настройте тестовую базу данных
docker exec -it pet php artisan migrate --env=testing

# Запустите проект
docker compose up -d
```

### 2. Внесение изменений

Убедитесь, что ваши изменения:

- **Работают корректно**: Проект запускается без ошибок
- **Покрыты тестами**: Новый функционал имеет unit/feature тесты
- **Проходят проверки**: PHPStan не выдаёт ошибок
- **Соответствуют стандартам**: Код отформатирован по PSR-12

### 3. Запуск проверок вручную

```bash
# Форматирование кода (Laravel Pint)
docker exec -it pet ./vendor/bin/pint

# Статический анализ (PHPStan)
docker exec -it pet ./vendor/bin/phpstan analyse

# Запуск тестов
docker exec -it pet php artisan test

# Или через PHPUnit напрямую
docker exec -it pet ./vendor/bin/phpunit
```

---

## Стандарты кода

### PHP (Laravel)

Проект следует стандартам **PSR-12** с дополнительными правилами Laravel.

**Основные правила:**

1. **Отступы**: 4 пробела (не табы)
2. **Длина строки**: Не более 120 символов (мягкое ограничение)
3. **Скобки**: Opening brace на той же строке для методов и функций
4. **Импорты**: Группируйте use-выражения (сначала классы, потом функции)
5. **Именование**:
   - Классы: `PascalCase`
   - Методы и переменные: `camelCase`
   - Константы: `UPPER_SNAKE_CASE`
   - Файлы миграций: `snake_case`

**Пример правильного кода:**

```php
<?php

namespace App\Services;

use App\Models\User;
use App\DTOs\MessageDto;
use Illuminate\Support\Facades\Log;

class MessageService
{
    private const MAX_MESSAGE_LENGTH = 4096;

    public function __construct(
        private readonly User $user,
    ) {}

    public function sendMessage(MessageDto $messageDto): bool
    {
        if (strlen($messageDto->text) > self::MAX_MESSAGE_LENGTH) {
            Log::warning('Message too long', ['user_id' => $this->user->id]);
            return false;
        }

        // Логика отправки сообщения
        return true;
    }
}
```

### Комментарии и документация

- **PHPDoc**: Обязательно для public методов и классов
- **Inline комментарии**: Используйте только для сложной логики
- **TODO/FIXME**: Допустимы, но лучше создать Issue

**Пример PHPDoc:**

```php
/**
 * Send message to Telegram user.
 *
 * @param MessageDto $messageDto Message data transfer object
 * @return bool True if message was sent successfully
 * @throws TelegramException If Telegram API returns error
 */
public function sendMessage(MessageDto $messageDto): bool
{
    // Implementation
}
```

### Laravel Best Practices

- **Controllers**: Тонкие, только обработка запросов/ответов
- **Services**: Бизнес-логика находится в сервисах
- **Models**: Только работа с данными, без бизнес-логики
- **Jobs**: Используйте для асинхронных задач
- **DTOs**: Для передачи данных между слоями
- **Validation**: В Form Requests, не в контроллерах

---

## Тестирование

### Требования к тестам

Весь новый функционал должен быть покрыт тестами:

- **Unit тесты**: Для сервисов, хелперов, утилит
- **Feature тесты**: Для HTTP endpoints, интеграций
- **Покрытие**: Стремитесь к >80% покрытию нового кода

### Структура тестов

```
tests/
├── Feature/           # Feature тесты (HTTP, интеграции)
│   ├── Api/
│   ├── Telegram/
│   └── Vk/
└── Unit/              # Unit тесты (сервисы, модели)
    ├── Services/
    ├── DTOs/
    └── Helpers/
```

### Пример Unit теста

```php
<?php

namespace Tests\Unit\Services;

use Tests\TestCase;
use App\Services\MessageService;
use App\DTOs\MessageDto;

class MessageServiceTest extends TestCase
{
    public function test_can_send_valid_message(): void
    {
        $messageDto = new MessageDto(
            text: 'Hello, World!',
            userId: 123,
        );

        $service = new MessageService();
        $result = $service->sendMessage($messageDto);

        $this->assertTrue($result);
    }

    public function test_rejects_too_long_message(): void
    {
        $messageDto = new MessageDto(
            text: str_repeat('a', 5000),
            userId: 123,
        );

        $service = new MessageService();
        $result = $service->sendMessage($messageDto);

        $this->assertFalse($result);
    }
}
```

### Пример Feature теста

```php
<?php

namespace Tests\Feature\Api;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ExternalMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_can_send_message_via_api(): void
    {
        $response = $this->postJson('/api/external/message', [
            'source' => 'test-source',
            'external_user_id' => '12345',
            'first_name' => 'John',
            'last_name' => 'Doe',
            'message' => 'Test message',
            'message_type' => 'text',
        ], [
            'Authorization' => 'Bearer ' . config('app.api_token'),
        ]);

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                 ]);
    }
}
```

### Запуск тестов

```bash
# Все тесты
docker exec -it pet php artisan test

# Конкретный тест
docker exec -it pet php artisan test --filter=MessageServiceTest

# С покрытием кода (требует Xdebug)
docker exec -it pet php artisan test --coverage

# Только Unit тесты
docker exec -it pet ./vendor/bin/phpunit tests/Unit

# Только Feature тесты
docker exec -it pet ./vendor/bin/phpunit tests/Feature
```

---

## Правила коммитов

### Формат commit message

Каждый коммит должен иметь следующий формат:

```
issues-{номер} | {краткое описание изменений}
```

**Примеры:**

```
issues-123 | add VK sticker support
issues-45 | fix telegram webhook error handling
issues-78 | update README with installation guide
issues-102 | refactor message service
```

### Правила сообщений коммитов

1. **Номер Issue обязателен**: Всегда указывайте номер задачи
2. **Краткость**: Не более 72 символов
3. **Глагол в повелительном наклонении**: "add", "fix", "update", а не "added", "fixed"
4. **Английский язык**: Сообщения на английском
5. **Без точки в конце**: `issues-123 | add feature` ✅ (не `add feature.` ❌)

### Типы изменений

Используйте префиксы для типа изменений:

- `add` — добавление нового функционала
- `fix` — исправление бага
- `update` — обновление существующего функционала
- `refactor` — рефакторинг без изменения функционала
- `remove` — удаление кода/функционала
- `docs` — изменения в документации
- `test` — добавление или изменение тестов
- `style` — форматирование кода (без логических изменений)
- `chore` — рутинные задачи (обновление зависимостей и т.д.)

**Примеры:**

```
issues-123 | add AI auto-reply feature
issues-124 | fix error in VK message handler
issues-125 | update message validation rules
issues-126 | refactor telegram service
issues-127 | remove deprecated methods
issues-128 | docs: update API documentation
issues-129 | test: add tests for message service
issues-130 | style: format code with pint
issues-131 | chore: update dependencies
```

### Атомарные коммиты

- **Один коммит = одно логическое изменение**
- Если вы фиксите баг и добавляете тесты — это **один коммит**
- Если вы добавляете две независимые фичи — это **два коммита**

**Плохо:**

```bash
git commit -m "issues-123 | fix bug, update tests, refactor code, update docs"
```

**Хорошо:**

```bash
git commit -m "issues-123 | fix message encoding bug"
git commit -m "issues-123 | add tests for message encoding"
git commit -m "issues-123 | update documentation"
```

---

## Pull Request процесс

### Создание Pull Request

1. **Убедитесь, что все проверки прошли**:
   - ✅ Все тесты зелёные
   - ✅ PHPStan не выдаёт ошибок
   - ✅ Код отформатирован

2. **Push в ваш fork**:
```bash
git push origin issues-123
```

3. **Создайте PR на GitHub**:
   - Перейдите на страницу вашего fork
   - Нажмите "Compare & pull request"
   - Заполните шаблон PR

### Шаблон Pull Request

```markdown
## Описание
Краткое описание изменений и причин их внесения.

## Связанные Issue
Closes #123

## Тип изменений
- [ ] Bug fix (исправление бага)
- [ ] New feature (новый функционал)
- [ ] Breaking change (изменения, ломающие обратную совместимость)
- [ ] Documentation update (обновление документации)

## Чек-лист
- [ ] Код соответствует стандартам проекта (PSR-12)
- [ ] Изменения покрыты тестами
- [ ] Все тесты проходят (`php artisan test`)
- [ ] PHPStan не выдаёт ошибок
- [ ] Документация обновлена (если необходимо)
- [ ] Git hooks настроены и проверки прошли

## Скриншоты (если применимо)
Добавьте скриншоты для визуальных изменений.

## Дополнительные заметки
Любая дополнительная информация для ревьюеров.
```

### Code Review

Ваш PR будет проверен мантейнерами проекта.

**Что проверяется:**

- ✅ Соответствие кода стандартам
- ✅ Наличие и качество тестов
- ✅ Логика и архитектура решения
- ✅ Безопасность (SQL injection, XSS, и т.д.)
- ✅ Производительность
- ✅ Документация

**Ожидаемое время ответа:**

- Первый отклик: 1-3 дня
- Code review: 3-7 дней

**Будьте готовы:**

- Ответить на вопросы ревьюеров
- Внести изменения по результатам ревью
- Обсудить альтернативные подходы

### После мержа

После того, как ваш PR будет принят:

1. **Синхронизируйте ваш fork**:
```bash
git checkout main
git pull upstream main
git push origin main
```

2. **Удалите старую ветку** (опционально):
```bash
git branch -d issues-123
git push origin --delete issues-123
```

---

## Сообщения об ошибках

### Перед созданием Issue

1. **Проверьте существующие Issues**: Возможно, баг уже описан
2. **Убедитесь, что это баг**: Проверьте документацию и FAQ
3. **Попробуйте воспроизвести**: Баг должен стабильно воспроизводиться

### Шаблон Bug Report

```markdown
## Описание бага
Краткое и ясное описание проблемы.

## Шаги для воспроизведения
1. Перейти в '...'
2. Нажать на '...'
3. Прокрутить до '...'
4. Увидеть ошибку

## Ожидаемое поведение
Что должно было произойти.

## Фактическое поведение
Что произошло на самом деле.

## Скриншоты
Если применимо, добавьте скриншоты.

## Окружение
- OS: [например, Ubuntu 22.04]
- Docker version: [например, 20.10.21]
- PHP version: [например, 8.2.12]
- Laravel version: [например, 12.0]

## Логи
```
Вставьте релевантные логи из storage/logs/laravel.log
```

## Дополнительная информация
Любая дополнительная информация о проблеме.
```

---

## Предложения и идеи

### Feature Request

Идеи по улучшению проекта приветствуются!

**Перед созданием Feature Request:**

1. Проверьте, нет ли похожих предложений
2. Убедитесь, что функция соответствует целям проекта
3. Подумайте о реализации и возможных проблемах

### Шаблон Feature Request

```markdown
## Описание функции
Краткое описание предлагаемой функции.

## Проблема, которую решает
Какую проблему решит эта функция?

## Предлагаемое решение
Как вы видите реализацию?

## Альтернативы
Рассматривали ли вы альтернативные решения?

## Дополнительный контекст
Скриншоты, примеры из других проектов и т.д.

## Готовность к реализации
- [ ] Я готов реализовать эту функцию сам
- [ ] Мне нужна помощь с реализацией
- [ ] Я только предлагаю идею
```

---

## Документация

### Обновление документации

При добавлении новых функций обязательно обновите:

1. **README.md**: Если меняется установка или основной функционал
2. **Wiki**: Для детальных инструкций и туториалов
3. **PHPDoc**: Для классов и публичных методов
4. **API Documentation**: Для новых endpoints (Swagger)

### Улучшение существующей документации

Если вы нашли ошибку или неточность в документации:

1. Создайте Issue с меткой `documentation`
2. Или сразу создайте PR с исправлением
3. Для мелких правок (опечатки) можно сразу делать PR

---

## Вопросы и поддержка

### Где задать вопрос

- **GitHub Discussions**: Для общих вопросов о проекте
- **GitHub Issues**: Для багов и feature requests
- **Telegram группа**: [https://t.me/pt_tg_support](https://t.me/pt_tg_support) — для быстрой помощи

### Перед тем как спросить

1. Проверьте [Wiki](https://github.com/prog-time/tg-support-bot/wiki/)
2. Поищите в существующих Issues и Discussions
3. Прочитайте документацию

---

## Лицензия

Внося вклад в этот проект, вы соглашаетесь с тем, что ваш код будет распространяться на условиях **MIT License**.

---

## Благодарность

Спасибо, что помогаете сделать **TG Support Bot** лучше!

Каждый вклад важен и ценен для проекта. ❤️

**Особая благодарность всем контрибьюторам:**

Список участников доступен на [GitHub Contributors](https://github.com/prog-time/tg-support-bot/graphs/contributors)

---

## Полезные ссылки

- **Проект**: [https://github.com/prog-time/tg-support-bot](https://github.com/prog-time/tg-support-bot)
- **Wiki**: [https://github.com/prog-time/tg-support-bot/wiki/](https://github.com/prog-time/tg-support-bot/wiki/)
- **Issues**: [https://github.com/prog-time/tg-support-bot/issues](https://github.com/prog-time/tg-support-bot/issues)
- **Telegram**: [https://t.me/pt_tg_support](https://t.me/pt_tg_support)
- **Laravel Docs**: [https://laravel.com/docs](https://laravel.com/docs)
- **PSR-12**: [https://www.php-fig.org/psr/psr-12/](https://www.php-fig.org/psr/psr-12/)

---

Сделано с ❤️ для open source сообщества
