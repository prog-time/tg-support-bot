# Переключение интерфейса менеджеров

> **Аудитория:** операционная команда, DevOps
> **Затрагивает:** только конфигурацию (`MANAGER_INTERFACE` в `.env`) — код, БД и миграции не изменяются

---

## Что такое MANAGER_INTERFACE

Переменная окружения `MANAGER_INTERFACE` определяет, через какой интерфейс менеджеры обрабатывают обращения пользователей:

| Значение | Интерфейс | Описание |
|---|---|---|
| `telegram_group` | Telegram supergroup | Менеджеры работают в Telegram-группе с форум-топиками. Каждый пользователь получает отдельный топик. |
| `admin_panel` | Веб-панель `/admin` | Менеджеры работают через браузер. Сообщения отображаются через polling каждые 5 секунд. |

---

## Переключение с `telegram_group` на `admin_panel`

1. Открыть `.env` на сервере
2. Найти строку `MANAGER_INTERFACE` и изменить значение:
   ```env
   MANAGER_INTERFACE=admin_panel
   ```
3. Перезапустить PHP-FPM контейнер:
   ```bash
   docker compose restart app
   ```
4. Открыть `/admin` в браузере
5. Войти с учётными данными из таблицы `users`

---

## Переключение с `admin_panel` обратно на `telegram_group`

1. Открыть `.env` на сервере
2. Изменить значение:
   ```env
   MANAGER_INTERFACE=telegram_group
   ```
3. Перезапустить PHP-FPM контейнер:
   ```bash
   docker compose restart app
   ```

---

## Важно

- Переключение **не требует** `php artisan migrate`
- Переключение **не изменяет** данные в БД
- Все сообщения сохраняются в таблице `messages` в обоих режимах
- История диалогов в `/admin` доступна в обоих режимах
- При переключении на `admin_panel` новые топики в Telegram-группе создаваться **не будут**
- При переключении обратно на `telegram_group` для пользователей, написавших в режиме `admin_panel` (без топика), необходимо вручную создать топики или они будут созданы автоматически при следующем сообщении

---

## Проверка активного режима

```bash
docker exec pet php artisan tinker --execute="echo config('app.manager_interface');"
```

---

## Добавление пользователей для `/admin`

Пользователи `/admin` хранятся в таблице `users` (стандартная Laravel-аутентификация через Filament).

Создание пользователя:

```bash
docker exec pet php artisan make:filament-user
```

---

## Ссылки

- Бизнес-правила Admin-панели: `rules/domain/admin-panel.md`
- Архитектура: `rules/process/architecture-design.md`
- Тесты совместимости: `tests/Feature/Admin/ManagerInterfaceCompatibilityTest.php`
