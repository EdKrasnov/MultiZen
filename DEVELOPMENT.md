# Multi Zen v2 — Development Context

> Файл для передачи контекста между сессиями работы над плагином.
> Обновлять при значимых изменениях.

---

## 1. Что это за проект

**Multi Zen** (`multi-rss-for-zen`) — это форк/переписывание плагина
**«RSS for Yandex Zen» v1.28** (автор: Flector), расширенный поддержкой
**нескольких независимых каналов Дзена**.

- **Оригинал** `rss-for-yandex-zen` — остаётся активным, обслуживает
  фид **новостей** для главного канала Дзена: `/feed/zen/`.
- **Наш плагин** `multi-rss-for-zen` v2.0.0 — обслуживает **тематические
  фиды** для отдельных Дзен-каналов: `/feed/<slug>/` (например, `/feed/kino/`).

Оба плагина **работают параллельно** и не конфликтуют.

### Зачем это нужно

Редактор публикует статьи на сайте. Для каждой статьи он вручную (или через
bulk action) выбирает **канал**, в который она должна уйти. Плагин:

1. Формирует отдельный RSS для каждого канала.
2. Не даёт статье попасть в чужой фид.
3. Позволяет отложить публикацию в фид (чтобы сайт проиндексировался первым).
4. Позволяет исключать отдельные записи из RSS.
5. Даёт редактору массовые действия (bulk actions).

---

## 2. Репозиторий и сервер

- **GitHub**: `https://github.com/EdKrasnov/MultiZen`
- **Ветка разработки**: `v2-multi-channels` (tracking `origin/v2-multi-channels`)
- **Ветка master/main**: рабочая v1 (backup)
- **Сервер**: `/home/giport.ru/public_html/wp-content/plugins/multi-rss-for-zen/`
- **Сайт**: `https://giport.ru`
- **PHP**: 8.x (точная версия — уточнить), **WordPress 7.1.2** (по warning в админке)
- **Серверное окружение**: OpenLiteSpeed + LiteSpeed Cache 7.9.1
- **Кэш**: LiteSpeed Cache — `/feed/` в исключениях кэша

### Git на сервере

Папка плагина на сервере — это git-репозиторий, связанный с GitHub.

- `git config --global --add safe.directory /home/giport.ru/public_html/wp-content/plugins/multi-rss-for-zen`
- `git config --global credential.helper store`
- Локальная ветка: `v2-multi-channels`

Команды git выполнять **из папки плагина**:

```bash
cd /home/giport.ru/public_html/wp-content/plugins/multi-rss-for-zen
git status
git add -A
git commit -m "..."
git push origin v2-multi-channels
```

---

## 3. Структура файлов

```
multi-rss-for-zen/
├── multi-rss-for-zen.php              # Bootstrap: константы, автозагрузка, хуки
├── uninstall.php                       # Удаление опций и мета-полей при uninstall
├── .gitignore
├── README.md
├── LICENSE
├── readme.txt                          # Legacy от v1
├── DEVELOPMENT.md                      # ← этот файл
├── includes/
│   ├── class-mzen-plugin.php           # Синглтон, boot() — инициализация сервисов
│   ├── class-mzen-channels.php         # CRUD каналов, поиск, дублирование, JSON
│   ├── class-mzen-migrator.php         # Миграция v1 → v2
│   ├── class-mzen-helpers.php          # Утилиты (mime_type, strip_tags и т.д.)
│   ├── class-mzen-feed.php             # Регистрация фидов и рендер
│   ├── class-mzen-metabox.php          # Метабокс: логика save/enqueue
│   ├── class-mzen-admin-settings.php   # Страница настроек (список/редактирование)
│   └── admin/
│       ├── view-metabox.php            # Шаблон метабокса
│       ├── view-settings-list.php      # Шаблон списка каналов
│       ├── view-settings-edit.php      # Шаблон формы редактирования канала
│       └── class-mzen-admin-columns.php # Колонка «Канал», фильтр, bulk actions
├── templates/
│   └── feed-rss2.php                   # XML-шаблон RSS-ленты
├── assets/
│   ├── admin.css                       # Стили метабокса и настроек
│   ├── admin.js                        # JS: авто-подстановка, валидация
│   ├── animate.2min.css                # Legacy от v1 (можно удалить)
│   ├── jquery.2lettering.js            # Legacy от v1
│   ├── jquery.2textillate.js           # Legacy от v1
│   └── zen-2script.js                  # Legacy от v1 (можно удалить)
├── img/
│   ├── donate.gif                      # Legacy (кнопка доната)
│   ├── icon_coffee.png                 # Legacy
│   └── video.png                       # Используется в шаблоне фида для превью видео
├── inc/                                # Legacy от v1 (можно удалить целиком)
│   ├── animate.2min.css
│   ├── jquery.2lettering.js
│   ├── jquery.2textillate.js
│   └── zen-2script.js
└── languages/                          # Пока пусто
```

---

## 4. Архитектура классов

### `MZEN_Plugin` (синглтон)

Главный класс. Инициализируется на `plugins_loaded` (приоритет 5).

**Публичные свойства**: `$channels`, `$migrator`, `$feed`, `$metabox`, `$settings`.

**`boot()`** — порядок создания сервисов:
1. `MZEN_Migrator` — миграция
2. `MZEN_Channels` — CRUD каналов
3. `MZEN_Feed` — фиды
4. Если `is_admin()`: `MZEN_Metabox`, `MZEN_Admin_Settings`, `MZEN_Admin_Columns`

**`on_activation()`**:
1. `MZEN_Migrator::migrate()` — миграция v1 → v2
2. Если каналов нет — `create_default()`
3. `update_option( 'mzen_flush_needed', 1 )` — отложенный flush rewrite
4. `update_option( 'mzen_db_version', MZEN_VERSION )`

**`on_deactivation()`** — `flush_rewrite_rules()`.

### `MZEN_Channels` — CRUD каналов

- `all()` — все каналы, отсортированные по `order`, потом `title`
- `get($slug)`
- `save($slug, $data)`
- `delete($slug)`
- `duplicate($src, $new)` — копия с автогенерацией уникального slug
- `create_default()` — создаёт `multizen` при первой активации
- `get_default_slug()`, `set_default($slug)`
- `search($term)` — поиск по title/slug/description (UTF-8)
- `export_json()` — JSON со всеми каналами
- `import_json($json, $mode)` — режимы `replace` / `merge`
- `sanitize_slug()`, `unique_slug()`, `normalize()`, `default_channel()`

### `MZEN_Migrator` — миграция v1 → v2

- `meta_map()` — карта старых ключей → новых
- `migrate()` — обёртка с проверкой версии
- `migrate_options_to_channels()` — `mzen_options` → `mzen_channels[<slug>]`
- `migrate_post_meta()` — старые мета-ключи → новые (старые не удаляет)
- `force_migrate()`, `current_version()`, `count_legacy_meta()`, `cleanup_legacy_meta()`

### `MZEN_Helpers` — утилиты

- `mime_type($file)` — MIME по расширению (webp поддерживается)
- `strip_tags_with_content($text, $tags, $invert)` — удалить теги вместе с содержимым
- `strip_tags_without_content($text, $tags)` — удалить теги, сохранить содержимое
- `strip_attributes($html, $allowed)` — оставить у `<img>` только указанные атрибуты
- `the_excerpt_rss($channel)` — `<description>`
- `the_content_feed($channel)` — `<content:encoded>`
- `opt($channel, $key, $default)` — безопасно достать опцию

### `MZEN_Feed` — фиды

- `register_feeds()` — `add_feed($slug, [$this, 'render'])` для каждого канала
- `add_feed_rewrite_rules()` — (в будущем) правило `^feed/<slug>/?$` с приоритетом top
- `render()` — рендер фида: находит канал, строит запрос, подключает шаблон
- `build_query($channel)` — WP_Query с учётом:
  - `_mzen_channel = slug` (обязательно)
  - исключение записей с `_mzen_rss_enabled = yes`
  - `_mzen_publish_at` (если задано и не наступило — не включать)
  - задержка `delay_minutes` (post_date_gmt + delay)
  - таксономии (`taxlist` / `addtaxlist`)
- `fix_content_type()` — `application/rss+xml`
- `send_robots_header()` — `X-Robots-Tag: index, follow`

### `MZEN_Metabox` — метабокс на странице записи

- Регистрируется для типов записей из настроек каналов
- `enqueue_assets()` — подключает `admin.js` + `admin.css`, передаёт `mzenChannels` (карта slug → настройки)
- `save()` — сохранение мета-полей `_mzen_*`
- **Логика**: если значение совпадает с настройкой канала — мета **удаляется**, чтобы фид брал значение из канала

### `MZEN_Admin_Settings` — страница настроек

Расположение: **Настройки → Мульти.Дзен**

- Список каналов с поиском
- Форма редактирования канала (все поля)
- Кнопки: `Изменить`, `Дублировать`, `Сделать по умолчанию`, `Удалить`
- Экспорт / импорт JSON
- Обработчики — через `admin_post_mzen_*`
- После save/delete/duplicate — `flush_rewrite_rules()`

### `MZEN_Admin_Columns` — колонка + фильтр + bulk actions

- Колонка **«Канал»** в списке записей (можно скрыть через Screen Options)
- **Фильтр** по каналу сверху (`mzen_channel_filter`)
- **Bulk actions**:
  - «Назначить канал «<Title>»» — по одной опции на канал
  - «Снять привязку к каналу»
  - «Исключить из RSS» (проставляет `_mzen_rss_enabled = yes`)
  - «Включить в RSS» (удаляет `_mzen_rss_enabled`)
- **При bulk assign** удаляются мета: `_mzen_category`, `_mzen_type_article`, `_mzen_type_platform`, `_mzen_index` — чтобы записи наследовали настройки канала. `_mzen_publish_at` **не трогается**.

---

## 5. Модель данных

### Опции WordPress

| Опция | Тип | Назначение |
|---|---|---|
| `mzen_channels` | array | Массив каналов: `[ slug => [ параметры канала ] ]` |
| `mzen_default_channel` | string | Slug канала по умолчанию |
| `mzen_db_version` | string | Версия миграции (сейчас `2.0.0`) |
| `mzen_flush_needed` | int | Флаг отложенного flush rewrite rules |
| `mzen_options` | array | **Старая опция v1** (не трогаем, backup) |

### Структура канала (в `mzen_channels`)

| Ключ | Тип | Назначение |
|---|---|---|
| `slug` | string | Уникальный, в URL `/feed/<slug>/` |
| `title` | string | Название канала (<title> в фиде, метка в метабоксе) |
| `link` | string | URL сайта (в `<link>`) |
| `description` | string | Описание издания |
| `language` | string | Код языка ISO 639-1 (ru, uk...) |
| `category` | string | Тематика по умолчанию |
| `rating` | string | Контент для взрослых |
| `number` | int | Количество записей в фиде |
| `type` | string | Типы записей через запятую (`post`, `page`...) |
| `author` | string | Автор записей (переопределение) |
| `figcaption` | string | «Использовать подписи» / «Отключить описания» |
| `imgauthorselect` | string | «Автор записи» / «Указать автора» / «Отключить» |
| `imgauthor` | string | Имя автора изображений |
| `thumbnail` | string | `enabled` / `disabled` |
| `selectthumb` | string | Размер миниатюры |
| `seodesc` | string | `enabled` / `disabled` — брать описание из SEO |
| `seoplugin` | string | «Yoast SEO» / «All in One SEO Pack» |
| `excludetags` | string | `enabled` / `disabled` |
| `excludetagslist` | string | `<div>,<span>` |
| `excludetags2` | string | `enabled` / `disabled` |
| `excludetagslist2` | string | `<iframe>,<script>,...` |
| `excludecontent` | string | `enabled` / `disabled` |
| `excludecontentlist` | string | Построчный список для удаления |
| `queryselect` | string | «Все таксономии, кроме исключенных» / «Только указанные таксономии» |
| `taxlist` | string | Список для исключения (`taxonomy:id1,id2`) |
| `addtaxlist` | string | Список для добавления |
| `excerpt` | string | `enabled` / `disabled` |
| `excludedefault` | string | `enabled` / `disabled` — исключать новые записи по умолчанию |
| `typearticle` | string | `true` (новости) / `false` (материалы) |
| `typeplatform` | string | `native-yes` / `native-draft` / `native-no` |
| `index` | string | `index` / `noindex` |
| `delay_minutes` | int | Задержка публикации в фид (0 = сразу) |
| `order` | int | Порядок сортировки в списке |

### Postmeta (мета-поля записи)

| Ключ | Значения | Назначение |
|---|---|---|
| `_mzen_channel` | slug канала | К какому каналу привязана запись |
| `_mzen_category` | строка | Override тематики (если ≠ тематики канала) |
| `_mzen_rating` | «Да...» / «Нет...» | Контент для взрослых |
| `_mzen_type_article` | `true` / `false` | Override типа статьи |
| `_mzen_type_platform` | `native-yes` / `native-draft` / `native-no` | Override публикации |
| `_mzen_index` | `index` / `noindex` | Override индексации |
| `_mzen_rss_enabled` | `yes` | Если `yes` — исключить из RSS. Отсутствие = включить |
| `_mzen_publish_at` | `Y-m-d H:i:s` | Отложенная публикация в RSS (индивидуально) |

**Логика override**: если значение меты совпадает с настройкой канала — мета удаляется при сохранении. Это даёт эффект «запись следует настройкам канала, пока редактор не переопределит».

---

## 6. Что уже сделано (v2.0.0)

### Базовая архитектура
- ✅ Мультиканальность: `mzen_channels` хранит N каналов
- ✅ Автозагрузка классов `MZEN_*` → `class-*.php`
- ✅ Bootstrap + синглтон
- ✅ Миграция v1 → v2 (одиночная опция → массив каналов + перенос мета)
- ✅ uninstall.php — удаление опций и мета

### Фиды
- ✅ `add_feed()` для каждого канала, URL `/feed/<slug>/`
- ✅ Шаблон `templates/feed-rss2.php` с полным набором Дзен-тегов
- ✅ Фильтр по каналу (`_mzen_channel = slug`)
- ✅ Исключение записей с `_mzen_rss_enabled = yes`
- ✅ Задержка публикации `delay_minutes` (per-channel)
- ✅ Отложенная публикация `_mzen_publish_at` (per-post)
- ✅ Таксономии (`taxlist`, `addtaxlist`)
- ✅ Миниатюры `<enclosure>`, авторы, `native-yes`/`native-draft`/`native-no`
- ✅ Content-Type `application/rss+xml`
- ✅ `X-Robots-Tag: index, follow`

### Метабокс на записи
- ✅ Селект канала с пунктом «— Канал НЕ выбран —»
- ✅ Поля: тематика, тип статьи, публикация, индексация
- ✅ Отложенная публикация `datetime-local` + кнопка «Очистить»
- ✅ Чекбоксы: «для взрослых», «Исключить из RSS»
- ✅ JS: авто-подстановка всех полей при смене канала
- ✅ JS: авто-галочка «Исключить из RSS» по `excludedefault`
- ✅ JS: валидация `_mzen_publish_at` — не раньше `post_date + delay_minutes`

### Админ-страница настроек
- ✅ Список каналов с поиском
- ✅ Форма редактирования канала
- ✅ Кнопки: редактировать, дублировать, сделать по умолчанию, удалить
- ✅ Экспорт JSON
- ✅ Импорт JSON (merge / replace)
- ✅ Автоматический `flush_rewrite_rules()` при save/delete/duplicate

### Список записей
- ✅ Колонка «Канал» (можно скрыть в Screen Options)
- ✅ Фильтр по каналу + «— Без канала —»
- ✅ Bulk actions: назначить канал, снять привязку, исключить/включить в RSS

---

## 7. Что в работе / осталось

### Тестирование
- 🔄 **Проверить работу `_mzen_publish_at`** в проде: запись не попадает в фид,
  пока время не наступило (в конце сессии тестировали, значения в БД менялись —
  нужно перепроверить на «чистой» записи)
- ⏳ Проверить **импорт/экспорт JSON** между сайтами end-to-end
- ⏳ Проверить **отложенную публикацию** в разных сценариях
- ⏳ Проверить **bulk assign на большом объёме** (1000+ записей) — сейчас
  цикл `update_post_meta` может быть медленным; при необходимости переписать
  через прямой SQL

### Возможные улучшения
- ⏳ Убрать legacy `inc/` (старые скрипты v1) и `assets/animate.2min.css`, `jquery.2lettering.js`, `jquery.2textillate.js`, `zen-2script.js`
- ⏳ Удалить `img/donate.gif`, `img/icon_coffee.png` (кнопка доната удалена)
- ⏳ Добавить правило `add_rewrite_rule('^feed/<slug>/?$', ..., 'top')` для страховки от конфликтов URL (сейчас flush справляется сам, но на новых каналах может быть редирект)
- ⏳ Оптимизация: `flush_rewrite_rules()` в `handle_save()` вызывается на каждом сохранении — можно оптимизировать, определяя изменение slug
- ⏳ UI: возможно, добавить «скрыть колонку по умолчанию» для пользователей, которым она не нужна
- ⏳ Проверить работу фида без ЧПУ (`?feed=<slug>`)
- ⏳ Возможно: страница «О плагине» с диагностикой (сколько каналов, сколько записей привязано)
- ⏳ Возможно: кнопка «Массовая привязка по рубрике» — все записи рубрики X → канал Y
- ⏳ Возможно: импорт/экспорт **всех** каналов для переноса на другой сайт (уже реализовано через export_json, но нет «мастера» с UI)
- ⏳ I18N: файл `languages/multi-rss-for-zen.pot` пока пустой

---

## 8. Известные особенности и заметки

### Порядок правил rewrite
WordPress обрабатывает правила перезаписи сверху вниз. Иногда `/feed/<slug>/` может
перехватываться правилом страниц и уходить в `redirect_guess_404_permalink()`.
Решение: `flush_rewrite_rules()` после создания канала (сделано) + опционально
`add_rewrite_rule('^feed/<slug>/?$', 'index.php?feed=<slug>', 'top')`.


### LiteSpeed Cache + фиды — КРИТИЧНО

LiteSpeed по умолчанию отдаёт для всего кэшируемого контента заголовок
`x-litespeed-cache-control: public,max-age=604800` (7 дней). Для HTML-страниц
это нормально, **для RSS-фидов — катастрофа**: Дзен/Турбо получают фид один раз,
кэшируют у себя на 7 дней и не запрашивают свежий.

**Симптом:** в `/feed/zen/` отдаются старые записи, при `?nocache=1` — свежие.
Дзен-бот ходит в лог с кодом 200, но свежих публикаций в Дзене нет.

**Решение (сделано 24.09.2026):**
1. `.htaccess` в корне сайта, перед `# BEGIN WordPress`:
   ```apache
   <IfModule LiteSpeed>
       CacheDisable /feed/
       CacheDisable feed/
   </IfModule>

LiteSpeed Cache → Cache → Excludes → Do Not Cache URIs: feed/ (без ведущего слеша)

### Боты Дзена — fail2ban и OpenLiteSpeed
Дзен-бот ходит под User-Agent **`Mail.RU_Bot/Fast/2.0`** (VK владеет Дзеном).
IP-адреса ботов (получены от Дзена 24.09.2026):

**IPv4:**
90.156.236.16 90.156.236.20 90.156.236.22 90.156.236.23
95.163.34.64 95.163.34.68 95.163.34.70 95.163.34.71
217.174.191.96 217.174.191.100 217.174.191.102 217.174.191.103


### Legacy мета-ключи
Старые мета-ключи v1 (`mzencategory_meta_value` и др.) остаются в БД как backup.
`MZEN_Migrator::cleanup_legacy_meta()` может их удалить (не вызывается автоматически).
Проверка: `wp db query "SELECT COUNT(*) FROM wp_postmeta WHERE meta_key LIKE 'mzen%_meta_value'"`.

### Legacy `_mzen_rss_enabled = no`
Значение `no` — невалидное для v2. Значимо только `yes` (исключить) или отсутствие (включить).
В сессии почистили: `DELETE FROM wp_postmeta WHERE meta_key='_mzen_rss_enabled' AND meta_value != 'yes'`.

### Соглашение о префиксах
- **Функции / классы / опции**: `mzen_` / `MZEN_` / `mzen_`
- **Мета-поля записи**: `_mzen_` (с подчёркиванием — скрытые от пользователя)
- **Legacy (не трогаем)**: `yzen_`, `yzen_options`, `yzrssname`

### Оригинальный плагин v1
`rss-for-yandex-zen` (v1.28) продолжает работать. Его slug фида — `zen`.
Не путать с `multizen` — это наш канал по умолчанию.

---

## 9. Рабочий процесс

### На сессии
1. Обсуждаем задачу.
2. Я выдаю патчи/файлы по частям.
3. Заменяем на сервере через `vi` (или `cat > ... << 'EOF'`).
4. Проверяем `php -l`.
5. Тестируем в браузере / через `wp-cli` / через `curl`.
6. При успехе — продолжаем.

### Периодически
1. `git add -A && git commit -m "..."` — промежуточные коммиты.
2. `git push origin v2-multi-channels` — синхронизация с GitHub.

### Полезные команды

```bash
# Логи ошибок
tail -100 /home/giport.ru/public_html/wp-content/debug.log

# Состояние каналов
wp option get mzen_channels --format=json --allow-root | python3 -m json.tool

# Записи в каналах
wp db query "SELECT meta_value, COUNT(*) FROM wp_postmeta WHERE meta_key='_mzen_channel' GROUP BY meta_value" --allow-root

# Мета конкретной записи
wp post meta list <ID> --allow-root | grep _mzen_

# Фид
curl -s "https://giport.ru/feed/<slug>/?nocache=1" | head -40

# Сброс кэша
wp litespeed-purge all --allow-root
wp cache flush --allow-root

# Flush rewrite
wp rewrite flush --allow-root
```

---

## 10. Changelog

### Сессия 2026-09-23/24 (ночь)

1. **Разобрали конфликт** v1 и форка.
2. **Замена префиксов** `yzen_` → `mzen_`.
3. **git-репозиторий** на сервере, ветка `v2-multi-channels`.
4. **Починили структуру ветки** (файлы плагина в корне репозитория).
5. **Полный rewrite v2** — 8 классов, шаблон фида, admin UI.
6. **Успешная активация**, канал `multizen` создан из опций v1.
7. **НЕ назначаем 300k записей** на канал (убрали `assign_orphan_posts`).
8. **Отказ от донат-блока**, переименование в `Мульти.Дзен`.
9. **Логика `excludedefault`** (JS-автогалочка).
10. **Bulk assign** очищает канало-зависимые мета.
11. **Чистка legacy** `_mzen_rss_enabled = no` (6 записей от v1).
12. **Отложенная публикация `_mzen_publish_at`** — реализована (UI + JS + PHP + Feed).
13. **Кэш LiteSpeed** — `/feed/` в исключениях.
14. **Первый git-коммит** v2 → GitHub.

### Сессия 2026-09-24 (день) — инцидент с Дзеном

**Проблема:** Дзен перестал видеть новости в `/feed/zen/`, фид отдавал вчерашние записи,
Дзен-бот в логах отсутствовал.

**Диагностика:**
- Фид при `?nocache=1` отдаёт свежак → БД и логика работают.
- Без `?nocache=1` — вчерашние записи из кэша.
- `x-litespeed-cache-control: public,max-age=604800` — LiteSpeed говорит Дзену «кэшируй 7 дней».
- Дзен-бот ходит как `Mail.RU_Bot/Fast/2.0`, получает 200 OK, но приходит раз в несколько минут.
- Fail2ban **не банит** IP Яндекса.

**Решение:**
1. `CacheDisable /feed/` в `.htaccess`.
2. `feed/` в LiteSpeed → Do Not Cache URIs.
3. `wp litespeed-purge all`.
4. Уменьшили количество записей в фиде с 100 до 20 — ускорило в 35 раз.

**Результат:** Дзен подхватил свежие записи, фид работает быстро.

### Сессия 2026-09-25 (утро)

15. **OpenLiteSpeed Allowed List** — добавили IP-адреса ботов Дзена с суффиксом `T`.
16. **Замеры производительности фида:**
    - 100 записей: 6.65s (первый), 8.6s (повторные)
    - 20 записей: 2.94s (первый), **0.20s** (повторные)

### Известные незакрытые задачи на конец сессии 25.09.2026

- **`_mzen_publish_at` не протестирован end-to-end.** Предыдущий тест был некорректным:
  запись не сохранили перед проверкой, а дата «вчера» была позже `post_date` — alert
  и не должен был сработать. При следующем тесте:
  1. Сохранить запись.
  2. Ставить дату **раньше** `post_date + delay_minutes` — тогда alert должен появиться.
  3. Проверять через `wp post meta get <ID> _mzen_publish_at`.

- **JS-alert при вводе слишком ранней даты** — реализован, но не протестирован.
  Добавить серверный `admin_notice` в следующий раз, чтобы редактор видел причину.

- **`delay_minutes`** — не протестирован в реале (поставить 10 минут, опубликовать
  запись, убедиться, что появится в фиде через 10 минут).

- **Импорт/экспорт JSON** — не протестирован end-to-end.

- **Fail2ban** — IP ботов Дзена **не добавлены** в `ignoreip` (только в OpenLiteSpeed).

- **Чистка legacy** (`inc/`, `img/donate.gif`, `img/icon_coffee.png`) — не сделана.

---

*Обновлено: 2026-09-25*
### Известные на конец сессии

- Тестирование `_mzen_publish_at` не завершено (значения в БД при проверке менялись)
- `delay_minutes` на практике ещё не проверялся на «настоящей» отложенной публикации
- Фид `kino` содержит 3 записи (записи с `_mzen_channel = kino`, без `_mzen_rss_enabled = yes`)
- **Следующий шаг**: продолжить тестирование отложенной публикации либо перейти
  к следующему пункту ТЗ (см. раздел «Что в работе»).

---

*Обновлено: 2026-09-24*