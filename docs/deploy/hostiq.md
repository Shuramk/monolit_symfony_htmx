# Первое развёртывание и перенос данных на HOSTiQ

Дата журнала: 2026-10-16. Проект: Monolit → Symfony + HTMX, PHP 8.3.
Это инструкция для отдельного тестового сайта, не переключение рабочего legacy.

## Текущая точка

Выполнено на хостинге: окружение, зависимости, 27 миграций, предприятие,
пользователи/права, товары, клиенты, заказы, склад, перевозчики, готовность отгрузок,
возвраты. Точные результаты ниже.

**Следующий шаг: настроить подтверждённый путь `taskfiles` и выполнить импорт CRM.**
CRM, сверка счётчиков ID и финальная проверка сайта ещё не подтверждены.
Уже выполненные основные импорты не повторять.

## 1. Безопасность и адреса

- Старая база `cifranco_monolit`: новому приложению только `SELECT`.
- Новая база `cifranco_monotitnewtest`: права на создание таблиц и запись.
- Один пользователь с разными правами на эти две базы допустим. В текущей
  установке это `cifranco_monotitnewtest@localhost`: ALL только на новую,
  SELECT только на старую; глобально — USAGE.
- Существующего пользователя старого сайта и его конфигурацию не менять.
- До импорта сохранить резервную копию источника штатными средствами хостинга;
  перед последующими изменениями новой базы сохранять и её копию.
- Не выполнять `doctrine:schema:update`, `doctrine:schema:create`, удаление схемы
  или DDL/DML через `--connection=legacy`.
- Пока идёт перенос, не создавать/редактировать записи через новый сайт.
- Старый сайт продолжает работать: последовательные импорты не являются общим
  согласованным снимком на одну секунду. Для production-cutover нужен отдельный
  план остановки записи, сверки и переключения владельца данных.
- Закрыть тестовый сайт от посторонних: в нём копия реальных данных.
- Неперенесённые пункты меню могут вести в старую админку. Не проверять там
  операции записи под видом теста нового сайта: SELECT-права Symfony не ограничивают
  самостоятельную работу старого приложения.
- Не сохранять пароли, APP_SECRET и дампы в Git или в `public/`.
  Пароль, который передавался в чат, заменить и обновить URL подключения.

Подтверждённые пути:

```text
Проект:  /home2/cifranco/www/monotitnewtest
PHP:     /opt/alt/php83/usr/bin/php
Composer: /usr/local/bin/composer
```

В выводе Doctrine проект также виден как `/home2/cifranco/public_html/monotitnewtest`.
Проверить физический путь командой `pwd -P`: он важен для поиска соседних файлов CRM.
Не считать эти два пути разными установками без проверки.

Команды ниже выполнять из корня проекта. При любой ошибке остановиться;
не запускать следующие импорты и не очищать таблицы для повторной попытки.

## 2. PHP и web root

```bash
cd /home2/cifranco/www/monotitnewtest
/opt/alt/php83/usr/bin/php -v
/opt/alt/php83/usr/bin/php -m
command -v composer
```

Подтверждено: PHP 8.3.35, Composer `/usr/local/bin/composer`, `pdo_mysql` есть.
Версия MariaDB: `10.11.19-MariaDB-cll-lve`. Глобальный PHP старых сайтов не менять.

Корень домена в cPanel должен указывать на **`проект/public`**, не на весь проект.
Правила в `public/.htaccess` (объединить с существующими, не перезаписывать вслепую):

```apache
<FilesMatch "\.php$">
    SetHandler application/x-httpd-alt-php83
</FilesMatch>

DirectoryIndex index.php
RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^ index.php [L]
```

Выбор PHP в `.htaccess` не действует на CLI; `.php-version` работает с локальным
Symfony CLI, не переключает хостинг. SSL должен быть валиден. Проверочный
`version.php` после настройки удалить. `var/` должен быть доступен PHP на запись,
без универсальных прав 777.

## 3. Зависимости

Для первой установки до настройки окружения — без auto-scripts:

```bash
/opt/alt/php83/usr/bin/php /usr/local/bin/composer install --no-dev --optimize-autoloader --no-scripts
/opt/alt/php83/usr/bin/php /usr/local/bin/composer check-platform-reqs --no-dev
```

Проверка на сервере прошла. Использовать сохранённый `composer.lock`,
не `composer update` и не `--ignore-platform-reqs`.

## 4. Серверный .env.local

Создать в корне проекта, не копировать локальные пароли. Пример содержит placeholders:

```dotenv
APP_ENV=prod
APP_DEBUG=0
APP_SECRET="REPLACE_WITH_RANDOM_SECRET"
APP_DATABASE_URL="mysql://NEW_USER:ENCODED_PASSWORD@localhost/NEW_DATABASE?charset=utf8mb4"
LEGACY_DATABASE_URL="mysql://READER:ENCODED_PASSWORD@localhost/OLD_DATABASE?charset=utf8mb4"
DEFAULT_URI="https://REPLACE_WITH_ACTUAL_DOMAIN"
LEGACY_ADMIN_URL="https://REPLACE_WITH_LEGACY_DOMAIN/admin.php"
MAILER_DSN=null://null
MESSENGER_TRANSPORT_DSN=sync://
```

Секрет:

```bash
/opt/alt/php83/usr/bin/php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'
```

В URL использовать полные имена пользователей/баз с префиксом cPanel и URL-кодировать
пароль. Проверка адресов и прав, без чтения самих паролей:

```bash
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=app 'SELECT DATABASE() AS db, CURRENT_USER() AS db_user, VERSION() AS version'
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=legacy 'SELECT DATABASE() AS db, CURRENT_USER() AS db_user'
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=legacy --force-fetch 'SHOW GRANTS'
```

SHOW GRANTS может вывести хеш пароля: такую строку не публиковать.
У старой базы не должно быть прав INSERT/UPDATE/DELETE/CREATE/ALTER/DROP.

Проверить наличие ожидаемых импортёром полей без изменения legacy:

```bash
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=legacy "SELECT COLUMN_NAME FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='sklad_users' AND COLUMN_NAME IN ('password_hash','password_hash_updated_at') ORDER BY COLUMN_NAME"
```

На текущем сервере оба поля есть. Если на другом окружении их нет — адаптировать
импортёр, а не добавлять поля в рабочую старую базу без отдельного решения.

## 5. Миграции новой базы

Default connection проекта — `app`; перепроверить это перед запуском.

```bash
/opt/alt/php83/usr/bin/php bin/console doctrine:migrations:migrate --dry-run --no-interaction
/opt/alt/php83/usr/bin/php bin/console doctrine:migrations:migrate --no-interaction
/opt/alt/php83/usr/bin/php bin/console doctrine:migrations:status
```

Выполнено: 27 миграций до `DoctrineMigrations\Version20261016150000`, New=0,
Executed Unavailable=0, база `cifranco_monotitnewtest`.
Dry-run показал 143 запроса, фактический запуск — 89; число SQL не является критерием
ошибки само по себе, проверять итог и status.
Deprecation об implicit commit при DDL на MariaDB не помешала применению.
Она не означает, что DDL можно откатить общей транзакцией. При реальной ошибке
проверять частично применённую схему, а не повторять миграции вслепую.

## 6. Основной импорт — только первый запуск

Все команды читают `legacy` и пишут в `app`. Запускать последовательно, проверяя
каждый результат. Следующие шаги текущей установки **уже выполнены**:

```bash
/opt/alt/php83/usr/bin/php bin/console legacy:import-enterprise
/opt/alt/php83/usr/bin/php bin/console legacy:import-identity
/opt/alt/php83/usr/bin/php bin/console legacy:import-products
/opt/alt/php83/usr/bin/php bin/console legacy:import-customers
/opt/alt/php83/usr/bin/php bin/console legacy:import-orders
/opt/alt/php83/usr/bin/php bin/console legacy:import-warehouse
/opt/alt/php83/usr/bin/php bin/console legacy:import-shipping-carriers
/opt/alt/php83/usr/bin/php bin/console legacy:import-shipping-readiness
/opt/alt/php83/usr/bin/php bin/console legacy:import-order-returns
```

| Импорт | Подтверждённый результат |
| --- | --- |
| Предприятие | Enterprise profile imported |
| Пользователи | 116 пользователей, 175 legacy-разделов |
| Каталог | 1711 товаров/групп |
| Клиенты | 5891 |
| Заказы | 14403 |
| Позиции заказов | 40647 |
| Отгрузки | 28572 |
| Оплаты | 22134 |
| История статусов | 229209 |
| warehouse_history | 86472 |
| warehouse_shipping_tasks | 23408 |
| warehouse_shipping_items | 56909 |
| production_tasks | 5522 |
| production_entries | 33737 |
| raw_materials | 46 |
| raw_material_history | 48026 |
| raw_material_balances | 30 |
| production_recipes | 470 |
| warehouse_shift_days | 1360 |
| Перевозчики | Добавлено 39 |
| Готовность отгрузок | Обновлено 19 |
| Возвраты | 5507 |
| Позиции возвратов | 6223 |
| Заявки на возврат | 1282 |

Это журнал конкретного переноса, не фиксированные ожидаемые количества для других дат.
Складской импорт переносит историю и задачи, не добавляет остатки повторно;
остатки уже загружены вместе с каталогом. Импорт возвратов также не меняет склад и кассу.

Отчёт возвратов: missingOrders=30, missingProducts=0, orphanItems=4,
missingAcceptors=65, missingProcessors=5174, inconsistentProcessing=0,
invalidDraftItems=23. Это исторические несоответствия, не повод удалять записи.
Некорректные исходные позиции сохранены в payload и отражаются предупреждением.
ID заявок: 1186, 1191, 1242, 1263, 1268, 1269, 1270, 1271, 1272, 1273, 1274,
1275, 1277, 1278, 1279, 1280, 1282, 1283, 1284, 1285, 1287, 1288, 1290.

**Не повторять** основной импорт товаров: он удаляет и пересобирает каталог,
а FK может обнулить ссылки товаров в позициях заказов. Не повторять полный импорт
пользователей: он пересоздаёт пользователей/права и удаляет новые сессии.
Импорт заказов пересобирает заказы и зависимые данные; наличие новых событий
блокирует его, но отсутствие блокировки не делает повтор безопасным.
Однократные маркеры склада/CRM/возвратов не удалять и не обнулять для повторного запуска.

## 7. CRM и вложения — ещё не выполнено

Подтверждённый источник — старый сайт `/monolit`. Обновить код импортёра на сервере
и добавить в `.env.local`:

```dotenv
LEGACY_CRM_FILES_DIR=/home2/cifranco/public_html/monolit/taskfiles
```

Без настройки сохраняется локальный путь `../monolit_old/taskfiles` относительно
проекта. Явно заданный несуществующий/нечитаемый каталог останавливает импорт без
сохранения маркера. Оригинальную папку рабочего сайта не менять.

Из каталога нового проекта проверить путь и обновить prod-кеш. Если ранее
использовался `composer dump-env prod`, сначала пересобрать `.env.local.php`
той же командой, чтобы новая переменная была загружена.

```bash
cd /home2/cifranco/www/monotitnewtest
pwd -P
ls -ld /home2/cifranco/public_html/monolit/taskfiles
/opt/alt/php83/usr/bin/php bin/console cache:clear --env=prod --no-debug
```

**После обновления кода, настройки и проверки пути:**

```bash
/opt/alt/php83/usr/bin/php bin/console legacy:import-crm
```

Импорт копирует вложения в приватную `var/crm-files` и сохраняет метаданные.
Проверить отчёт `missingFiles`, `orphanContacts` и скачивание нескольких вложений.
Отсутствие файлов на диске не обязательно приводит к падению команды: они могут
быть отмечены недоступными и импорт — помечен завершённым. Не запускать заранее
и не сбрасывать маркер; восстановление недоступных вложений — отдельная операция.
Логотип/другие старые ресурсы тоже проверить отдельно: перенос БД не копирует файлы.

## 8. Счётчики ID — ещё не выполнено

Выполнить **после всех импортов, до создания записей**. Сначала сделать копию новой
базы и убедиться, что сайт пока не принимает записи. Ниже команды только для `app`;
они повышают счётчики, не понижают и не меняют существующие сущности.

```bash
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=app "UPDATE product_number_sequence SET last_id=GREATEST(last_id,(SELECT COALESCE(MAX(id),0) FROM products)) WHERE id=1"
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=app "UPDATE customer_number_sequence SET last_id=GREATEST(last_id,(SELECT COALESCE(MAX(id),0) FROM customers)) WHERE id=1"
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=app "INSERT IGNORE INTO identity_user_numbers (id) SELECT GREATEST(COALESCE(MAX(id),0),1) FROM identity_users"
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=app "INSERT IGNORE INTO shipping_shipment_numbers (id) SELECT GREATEST(COALESCE(MAX(id),0),1) FROM order_shipments"
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=app "INSERT IGNORE INTO order_payment_numbers (id) SELECT GREATEST(COALESCE(MAX(source_id),0),1) FROM order_payments WHERE source IN ('application','application_shipment')"
```

После первых двух UPDATE проверить наличие строки id=1, а не только affected rows:
нулевое число изменений может означать как уже актуальный счётчик, так и отсутствие строки.
У новых оплат отдельное пространство ID (`source=application/application_shipment`),
поэтому совпадение числового source_id с legacy-оплатой допустимо.
Номера заказов выдаются через `order_number_sequences` с проверкой MAX за дату;
не заполнять этот счётчик общим максимальным ID заказа.

Read-only проверка:

```bash
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=app "SELECT 'products' AS kind,last_id,(SELECT COALESCE(MAX(id),0) FROM products) AS entity_max FROM product_number_sequence WHERE id=1 UNION ALL SELECT 'customers',last_id,(SELECT COALESCE(MAX(id),0) FROM customers) FROM customer_number_sequence WHERE id=1"
/opt/alt/php83/usr/bin/php bin/console dbal:run-sql --connection=app "SELECT TABLE_NAME,AUTO_INCREMENT FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME IN ('identity_user_numbers','shipping_shipment_numbers','order_payment_numbers','order_returns','order_return_items','order_return_drafts','warehouse_shipping_tasks','crm_deals','crm_tasks') ORDER BY TABLE_NAME"
```

У sequence-таблиц last_id должен быть не меньше соответствующего MAX, next
AUTO_INCREMENT — выше уже выданных ID. Для обычных AUTO_INCREMENT-таблиц импорт
явных положительных ID поднимает следующее значение автоматически; проверить,
а не сбрасывать его на 1. Команды сверены с текущими репозиториями, но на сервере
этот этап ещё не выполнен и результат должен быть проверен по факту.

## 9. Финальная сборка и проверка сайта — ещё не подтверждено

После окружения, импортов и сверки счётчиков:

```bash
/opt/alt/php83/usr/bin/php /usr/local/bin/composer run-script post-install-cmd --no-interaction
/opt/alt/php83/usr/bin/php /usr/local/bin/composer check-platform-reqs --no-dev
/opt/alt/php83/usr/bin/php bin/console asset-map:compile
/opt/alt/php83/usr/bin/php bin/console cache:clear --env=prod --no-debug
```

Проверить вход, роли и меню; предприятие, каталог, клиентов, карточки заказов,
суммы/оплаты/отгрузки, складские остатки, CRM-вложения, возвраты, CSS/JS и печать.
Сверить количества/суммы с источником с учётом явно пропускаемых сиротских данных;
одинаковые количества сами по себе не доказывают правильность денежных сумм.
Почта должна оставаться отключённой, debug — выключенным, секреты и дампы —
недоступными по HTTP. Новые операции сначала проверять только на тестовых данных.
Не запускать PHPUnit на хостинге с этой app-базой.

## 10. Последующие обновления — не повторный перенос данных

1. Сохранить резервную копию новой базы и приватных файлов (`var/crm-files`).
2. Получить новую версию кода, сохранив серверный `.env.local` и пользовательские файлы.
3. Composer install из lock-файла, не update; окружение prod настроить до auto-scripts.
4. Посмотреть план только новых миграций; применять после оценки их обратимости.
5. Собрать ассеты, очистить/прогреть кеш, проверить основные страницы.
6. Основные `legacy:import-*` не запускать повторно. Целевые дополнительные импорты —
   только после проверки их конкретного контракта и резервной копии.

При сбое откат кода не обязательно совместим с уже применённым DDL. Не использовать
массовый migrations down как универсальный откат; восстановление БД — отдельное
контролируемое действие. Старую базу не изменять.
