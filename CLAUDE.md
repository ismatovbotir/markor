# Markor — печать этикеток маркировки (Asl Belgisi)

## 1. Цель проекта

Веб-CRM для предприятия Узбекистана, работающего с системой маркировки **Asl Belgisi** (оператор CRPT Turon). Покрывает офлайн-сценарий: получение пула КМ от оператора в виде CSV → нанесение → агрегация → печать → отчётность. Интеграция с API IZKM/OMS заложена архитектурно, реализация — фаза 3.

## 2. Доменный контекст

### Стандарт КМ

GS1 DataMatrix:

```
(01) GTIN(14) (21) Serial(N) [GS] Crypto(M)
```

|Группа                     |Serial (AI 21)|Крипто-хвост|Итого после AI21|
|---------------------------|--------------|------------|----------------|
|Табак, сигареты            |7             |13          |20              |
|Лекарства, алкоголь, прочие|13            |4 + 44      |61              |

Парсер автоопределяет формат по длине строки и/или товарной группе.

### Иерархия агрегации (3 уровня)

```
Уровень 1: КМ (DataMatrix) — потребительская упаковка
Уровень 2: SSCC (1D-штрихкод) — групповая упаковка
Уровень 3: SSCC — транспортная упаковка / паллета
```

### Источник SSCC

Поле `aggregations.sscc_source` (enum: `imported_pool` / `generated`). До получения данных от клиента — внутренняя генерация с временным префиксом для тестов.

## 3. Сущности и схема БД

### `users`

- `id`, `name`, `email`, `password`, `role` (admin/operator)
- `locale` (uz/ru)
- `qr_token` (string, unique, для QR-логина)
- `telegram_chat_id`, `phone` (для уведомлений)
- `is_active`, timestamps

### `product_groups`

- `id`, `name_uz`, `name_ru`
- `code_format` (enum: `tobacco_short` / `standard_long` / custom)
- `serial_length`, `crypto_length`
- `requires_eds` (bool, на будущее)
- `is_active`

### `products`

- `id`, `product_group_id` (FK)
- `gtin` (14, unique), `name_uz`, `name_ru`, `sku`, `barcode_ean`
- `attributes` (JSONB)
- `is_active`

### `import_batches`

- `id`, `uuid`
- `filename`, `original_filename`
- `uploaded_by` (FK users)
- `type` (enum: `codes` / `aggregation` / `mixed` — определяется парсером)
- `product_id` (FK, nullable)
- `status` (pending / processing / completed / failed)
- `total_rows`, `processed_rows`, `failed_rows`
- `error_log` (JSONB)
- `created_at`, `processed_at`

### `marking_codes`

- `id`, `code` (unique), `gtin`, `serial`, `crypto_tail`
- `product_id`, `import_batch_id`, `aggregation_id` (nullable FKs)
- `status` (imported / assigned / printed / aggregated / shipped / cancelled)
- `printed_at`, `aggregated_at`, timestamps
- индексы: `(status)`, `(product_id, status)`, `(aggregation_id)`, `(gtin, serial)`

### `aggregations`

- `id`, `uuid`, `sscc` (unique)
- `sscc_source` (imported_pool / generated)
- `level` (1/2/3), `parent_id` (FK self, nullable)
- `product_id`, `expected_quantity`, `actual_quantity`
- `status` (open / closed / disaggregated)
- `created_by`, `closed_at`, timestamps

### `label_templates`

- `id`, `name`, `product_group_id` (nullable)
- `printer_type` (zpl_zebra / ezpl_godex / sbpl_bixolon / pdf_browser)
- `width_mm`, `height_mm`, `dpi`
- `template` (text, с плейсхолдерами `{datamatrix}`, `{gtin}`, `{name}`, `{sscc}`, `{date}`)
- `is_default`

### `printers`

- `id`, `name`, `type`, `connection_mode` (network / browser)
- `host`, `port` (для network)
- `is_active`

### `print_jobs`

- `id`, `uuid`, `template_id`, `printer_id`
- `created_by`, `code_ids` (JSONB), `aggregation_id` (nullable)
- `status` (queued / rendering / ready / sent / failed)
- `output_path`, `quantity`, timestamps

### `audit_logs`

- `id`, `user_id`, `action`, `entity_type`, `entity_id`
- `payload` (JSONB), `ip`, `user_agent`, `created_at`

### `notifications_log`

- `id`, `user_id`, `channel` (telegram/sms), `event`, `payload`, `status`, `sent_at`

## 4. Роли и права

### Admin

Полный CRUD по всем сущностям, настройки, отчёты, audit log, расформирование агрегаций, аннулирование КМ, перевыпуск QR-бейджей.

### Operator

- Загрузка CSV, привязка к товару, сканирование, агрегация, печать
- Просмотр **только своих** операций
- Без удаления, без расформирования, без настроек

### Аутентификация

- **Admin:** email + пароль (фаза 2: 2FA через Laravel Fortify)
- **Operator:** пароль ИЛИ QR-бейдж (Sanctum-токен, ревокабельный)
- На странице логина — поле автофокуса для сканера QR

## 5. Ключевые сценарии

### 5.1 Импорт CSV

1. Загрузка → `storage/imports/{uuid}.csv`, создание `import_batch` (pending)
1. `ProcessImportBatchJob` в Horizon
1. Стрим-чтение через league/csv, автоопределение типа (коды / агрегация / mixed)
1. Bulk insert чанками по 1000
1. Уведомление оператору (UI + Telegram/SMS)
1. На 10К кодов — < 1 минуты

### 5.2 Воркфлоу: Товар → Печать → Агрегация

```
imported → [bind to product] → assigned → [print] → printed → [aggregate] → aggregated
```

Бизнес-правило: к агрегации нельзя добавить код в статусе ниже `printed`.

### 5.3 Сканирование (печать и агрегация)

**Железо:** USB/BT-сканер как HID-keyboard wedge с постфиксом Enter.

**Поток:**

1. Livewire-компонент с input (autofocus + wire:keydown.enter)
1. Нормализация (strip GS `\u001D`, RS `\u001E`, trim)
1. Парсинг regex → GTIN, Serial
1. Lookup `SELECT … FOR UPDATE` (защита от race в смене)
1. Валидация ожидаемого статуса (для печати → `assigned`, для агрегации → `printed`)
1. Транзакция: UPDATE статуса + audit log
1. Возврат в UI: подсветка ✅/❌, звук, фокус на input
1. Контекст текущей сессии (open print_job / aggregation) — в Redis

### 5.4 Печать — два режима

|Режим          |Реализация                                     |Когда                        |
|---------------|-----------------------------------------------|-----------------------------|
|**Browser PDF**|TCPDF/Browsershot → preview → штатная печать ОС|универсальный, любой принтер |
|**Direct TCP** |ZPL/EZPL/SBPL по сокету на host:9100           |сетевые термопринтеры, быстро|

Режим выбирается на уровне `printers.connection_mode`.

### 5.5 Агрегация

Открытие агрегации уровня N → сканирование дочерних → закрытие при достижении количества. SSCC: из пула или генератор.

### 5.6 Кодирование DataMatrix при печати (FNC1, GS)

GS1 DataMatrix содержит **скрытые служебные символы**, которые отсутствуют в текстовом представлении кода. Их обработка критична: без них сканер не распознает код как GS1, и Aslbelgi его отбракует.

#### Принцип хранения и кодирования

|Этап                                  |FNC1                       |GS (`\x1D`)                                             |
|--------------------------------------|---------------------------|--------------------------------------------------------|
|💾 Хранение в БД (`marking_codes.code`)|❌ нет                      |❌ нет                                                   |
|🖨️ Encoder DataMatrix (payload)        |✅ автоматически в начале   |✅ только для длинного формата, между AI переменной длины|
|👁️ Human-readable текст под штрихкодом |❌ нет                      |❌ нет                                                   |
|📷 Возврат от сканера в UI             |префикс `]d2` (детектируем)|может прийти `\x1D` (стрипаем)                          |

**В БД хранится канонический печатный payload** — то, что пришло в CSV, после `trim()` управляющих символов. Никаких FNC1 / GS в строке.

#### Правила вставки служебных символов

**FNC1 (codeword 232)** ставится в самом начале символа DataMatrix. В коде это **не печатный ASCII** — указываем encoder’у режим GS1 DataMatrix, и он сам кодирует FNC1 в первый codeword.

**GS (`\x1D`)** требуется в payload-строке между AI **переменной длины** и следующим AI:

```
Табачный (38 символов, все поля фиксированы):
   payload:  '0106291071140717217SvU.!KozR5"QSkFJ"/='
   GS:       не нужен (всё фиксированной длины)
   FNC1:     один в начале (вставляет encoder)

Длинный (лекарства/алкоголь):
   payload:  '01' + GTIN(14) + '21' + Serial(13) + \x1D + '91' + Key(4) + \x1D + '92' + Code(44)
   GS:       перед AI(91) и AI(92) — обязательно
   FNC1:     один в начале (вставляет encoder)
```

#### Реализация (псевдокод Laravel)

```php
namespace App\Domain\Printing\Renderers;

use Com\Tecnick\Barcode\Barcode;
use App\Domain\Marking\Models\MarkingCode;

class DataMatrixRenderer
{
    public function render(MarkingCode $code, int $widthMm = 10): string
    {
        $payload = $this->buildGs1Payload($code);

        $barcode = (new Barcode())->getBarcodeObj(
            'DATAMATRIX_GS1',  // ← режим вставляет FNC1 codeword 232
            $payload,
            -10, -10,          // авторазмер модулей
            'black',
            [0, 0, 0, 0]
        );

        return $barcode->getSvgCode();
    }

    private function buildGs1Payload(MarkingCode $code): string
    {
        return match ($code->format) {
            'tobacco_short' => $code->code,        // GS не нужен

            'standard_long' => sprintf(
                "01%s21%s\x1D91%s\x1D92%s",
                $code->gtin,
                $code->serial,
                $code->crypto_key,                  // 4 символа
                $code->crypto_code                  // 44 символа
            ),
        };
    }
}
```

#### Нормализация при сканировании

Когда сканер возвращает прочитанный код в UI, он может прислать `\x1D` (если сканер настроен передавать GS как `Ctrl+]` или вставкой невидимого символа). Перед поиском в БД:

```php
$normalized = str_replace(["\x1D", "\x1E"], '', trim($raw));
$markingCode = MarkingCode::where('code', $normalized)->first();
```

Так совпадение всегда находится, независимо от настроек сканера.

#### Проверочный сценарий (acceptance test)

В админке предусмотреть страницу **«Тест печати»**:

1. Выбираем шаблон и любой код в статусе `assigned`
1. Печатаем 1 этикетку
1. Оператор сканирует своим рабочим сканером
1. Система должна:
- распознать префикс `]d2` (значит FNC1 на месте) → ✅
- найти код в БД → ✅
- извлечь GTIN и Serial корректно → ✅

Если сканер возвращает `]d1` вместо `]d2` — encoder работает в режиме обычного DataMatrix, а не GS1. Это ловится автотестом перед релизом.

## 6. Отчёты

**MVP (фаза 1):**

1. Журнал нанесения (оператор / товар / период)
1. Журнал агрегации (с деревом)
1. Остатки КМ по статусам
1. Жизненный цикл одного КМ (трассировка)

**Фаза 2:**
5. Журнал печати, очереди принтеров
6. Расход кодов по товарам, топы
7. Производительность операторов (кодов/час/смену)
8. Дерево агрегации по SSCC
9. Дневная сводка (Telegram-автоотчёт админу)
10. Расхождения / зависшие коды
11. Audit log с фильтрами

Экспорт: Excel / CSV / PDF.

## 7. Уведомления

**Каналы:** Telegram (irazasyed/telegram-bot-sdk) + SMS UZ (Eskiz.uz / Playmobile через драйвер-абстракцию).

**События:**

- Завершение/ошибка импорта CSV → оператору
- Критические ошибки системы → админу
- Закрытие агрегации (опционально)
- Дневная сводка → админу (Telegram)

Шаблоны уведомлений — БД-управляемые, на UZ/RU.

## 8. Дизайн-система

**Палитра «light birch»:**

- Фон: `stone-50` (#FAFAF9)
- Карточки: `white` + `border-stone-200`
- Сайдбар: `stone-100`
- Акцент CTA: `amber-700`
- Успех: `emerald-600`
- Ошибка: `rose-600`
- Текст основной: `stone-800`
- Текст вторичный: `stone-500`

Шрифт: Inter / системный.
Иконки: Heroicons.
Компоненты: Livewire + Blade Components, без внешней библиотеки UI (свои на Tailwind).

## 9. Технический стек

|Слой                     |Технология                                                   |
|-------------------------|-------------------------------------------------------------|
|Backend                  |Laravel 11, PHP 8.3                                          |
|UI                       |Livewire 3 + Alpine.js + Tailwind                            |
|БД                       |PostgreSQL 16                                                |
|Очереди                  |Laravel Horizon + Redis                                      |
|Кеш / сессии сканирования|Redis                                                        |
|Auth / роли              |Laravel Sanctum + spatie/laravel-permission                  |
|CSV                      |league/csv (стрим)                                           |
|Excel                    |maatwebsite/excel                                            |
|DataMatrix               |tecnickcom/tc-lib-barcode                                    |
|PDF                      |barryvdh/laravel-dompdf или spatie/laravel-pdf               |
|ZPL/EZPL/SBPL            |Blade-шаблоны + TCP-сокет                                    |
|Audit                    |owen-it/laravel-auditing                                     |
|Telegram                 |irazasyed/telegram-bot-sdk                                   |
|SMS UZ                   |свой драйвер (Eskiz/Playmobile)                              |
|i18n                     |Laravel `__()` + JSON-локали (uz/ru)                         |
|Деплой                   |Docker Compose (php-fpm + nginx + postgres + redis + horizon)|
|Среда                    |Linux on-premise у клиента                                   |

## 10. Структура модулей

```
app/
├── Domain/
│   ├── Catalog/         (Product, ProductGroup)
│   ├── Marking/         (MarkingCode, ImportBatch, Parsers, Scanners)
│   ├── Aggregation/     (Aggregation, SsccGenerator)
│   ├── Printing/        (LabelTemplate, PrintJob, Drivers/{Zpl,Ezpl,Sbpl,Pdf})
│   ├── Notifications/   (Channels/{Telegram,Sms})
│   └── Reporting/
├── Http/Livewire/       (UI компоненты по доменам)
├── Jobs/                (ProcessImportBatchJob, RenderPrintJob, SendNotificationJob)
└── Services/Aslbelgi/   (заглушки под IZKM/OMS API)
```

## 11. Нефункциональные требования

|Параметр                |Значение                         |
|------------------------|---------------------------------|
|Объём CSV               |до 10 000 строк                  |
|Время обработки CSV     |< 1 минуты                       |
|Одновременных операторов|5–20                             |
|Уровни агрегации        |3                                |
|Удаление данных         |soft delete + audit              |
|Бэкап БД                |ежедневно, retention 30 дней     |
|Доступ                  |LAN/VPN, без публичного интернета|
|Локализация             |UZ + RU                          |

## 12. Дорожная карта

**Фаза 1 (MVP):**

- Auth (пароль + QR), роли
- Справочники (группы, товары, шаблоны, принтеры, пользователи)
- Импорт CSV + привязка к товару
- Сканирование при печати
- Печать через браузер (PDF)
- Агрегация 1 уровня
- Отчёты 1–4
- Уведомления Telegram

**Фаза 2:**

- Полная агрегация 3 уровней + SSCC
- Direct TCP печать (ZPL/EZPL/SBPL)
- Расширенные отчёты 5–11
- SMS-канал
- 2FA для админа
- Supervisor-роль (опционально)

**Фаза 3:**

- Интеграция API Aslbelgi (IZKM/OMS)
- E-imzo (электронная подпись)
- Ввод в оборот, отчёты в ГИС МТ

## 13. Открытые вопросы (v0.3)

- ⏳ **Образец CSV** от клиента (коды + агрегация) — ожидается
- ⏳ **GS1 Company Prefix** клиента (для генерации SSCC, если нет пула) — ожидается
- ⏳ **Спецификация бейджа оператора** — печатаем сами или через подрядчика?
- ⏳ **SMS-провайдер** — Eskiz / Playmobile / другой?

-----

**Версия:** 0.4
**Дата:** 02.05.2026
**Статус:** проанализирован образец CSV (codes_success.csv, табачный формат, 2520 кодов). Ожидается образец агрегационного файла. Можно стартовать миграции и парсер для табачного формата.

**История изменений:**

- v0.4 — добавлен раздел 5.6 о кодировании DataMatrix (FNC1, GS), правила хранения и нормализации
- v0.3 — финализированы UI/печать/auth/нотификации/локализация
- v0.2 — детализирована архитектура, схема БД, дорожная карта
- v0.1 — начальный черновик с открытыми вопросами
-----

## 14. Правило: новая функция → обновить главную страницу и маркетинг

Markor — SaaS с тремя зонами: `/` (главная) и `/label-design` (публичный конструктор этикеток), `/aslbelgi` (клиенты, коды Asl Belgisi, нужен вход), `/admin` (владелец). Главная страница продаёт сервис и оборудование владельца, поэтому её текст должен совпадать с тем, что реально умеет продукт.

**После каждой новой или изменённой функции, заметной пользователю, в той же задаче:**

1. **Решить, меняет ли она обещание на главной.** Новая возможность, новый лимит, изменённый сценарий, функция стала платной/бесплатной, «скоро» превратилось в «готово» — да. Внутренний рефакторинг — нет.
2. **Обновить тексты** в `lang/uz.json`, `lang/ru.json`, `lang/zh.json` — всегда во всех трёх языках:
   - `home.*` — блоки главной (`resources/views/home.blade.php`): hero, два продукта, шаги, карточки `home.features.*`, раздел Asl Belgisi, FAQ `home.faq.N.q/a`;
   - `seo.home.*`, `seo.studio.*` — title / description, если функция важна для поиска;
   - новая карточка возможностей = ключи `home.features.<имя>.title/text` + строка в массиве `$features` в `home.blade.php`.
3. **Писать только то, что уже работает и проверено.** Незаконченное — только как «Tez orada / Скоро / 即将推出». Без цен, числа клиентов, сертификатов и слов «лучший/№1». Узбекский — латиницей, основное слово «etiketka».
4. **Обновить маркетинговые материалы**, если функция меняет предложение: `docs/marketing/promotion-plan.md` (готовые посты), `docs/marketing/seo.json` (ключевые слова, FAQ).
5. **Проверить страницу** в браузере на трёх языках и сказать владельцу в отчёте, что именно изменилось на главной.

Для большого изменения текстов запускать двух агентов параллельно — маркетинг (тексты uz/ru/zh) и SEO (title, description, H1, FAQ) — с перечнем фактов о функции; результат сводить в `lang/*.json`. Для одной-двух строк — править самому.

Ничего не публиковать во внешних каналах (Telegram, OLX, соцсети, каталоги) без явного согласия владельца на конкретный канал и текст.
