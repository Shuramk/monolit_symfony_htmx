# Monolit → Symfony + HTMX

Изолированный Symfony-контур для постепенной миграции legacy-приложения из `../monolit_old`.

## Границы миграции

- Старое приложение и MySQL-схема не изменяются этим проектом.
- До отдельного решения подключение к legacy-БД отсутствует.
- Первое подключение будет `read-only` через Doctrine DBAL, без автогенерации схемы и без `doctrine:schema:update`.
- Операции записи переносятся по одной, после фиксации их поведения тестами.

## Запуск

Проект рассчитан на PHP 8.3 и Symfony 7.4 LTS. В Composer задана платформа
`8.3.0`: обновление зависимостей даже с более нового локального PHP не должно
добавлять пакеты, требующие PHP 8.4+. Для MySQL/MariaDB нужен `pdo_mysql`,
для тестов также нужны DOM/XML и mbstring. Рекомендуется включить intl.

При установленном Symfony CLI версия выбирается из `.php-version`:

```bash
symfony composer install
symfony php -S 127.0.0.1:8080 -t public
```

Открыть `http://127.0.0.1:8080/`.

Без Symfony CLI указывайте PHP 8.3 явно: `php8.3 composer.phar install`,
`php8.3 bin/console …`, `php8.3 bin/phpunit`. Путь к Composer/CLI PHP на хостинге
может отличаться. Правило PHP в `.htaccess` не переключает консольный PHP.
На сервере выполнять `composer install` из сохранённого `composer.lock`, не `update`.
Не использовать `--ignore-platform-reqs`: проверка реального окружения —
`symfony composer check-platform-reqs` (с `--no-dev` для production-установки).

## Проверка

PHPUnit использует отдельную базу `monolit_test` и отдельного пользователя с правами
только на неё (`.env.test`). Тесты не должны подключаться к `monolit_app`:
`tests/bootstrap.php` блокирует такой запуск до выполнения тестов.

Локальная тестовая база уже создана как копия `monolit_app`. Для нового окружения
создайте `monolit_test`, импортируйте в неё копию схемы и данных новой базы и выдайте
пользователю из `.env.test` права только на `monolit_test.*`. Legacy-подключение
остаётся только для чтения. Миграции новой модели перед тестами применяются отдельно:

```bash
symfony console doctrine:migrations:migrate --env=test --no-interaction
symfony php bin/phpunit
```

PHPUnit использует совместимую с PHP 8.3 ветку 12.5. Doctrine на PHP 8.3
генерирует прокси: ORM-сущности не должны быть `final`. Бизнес-операции и схема БД
при переходе на PHP 8.3 не менялись.

Результаты проверки перехода и известные проблемы тестовых фикстур:
[php83.md](.knowledge/Application/php83.md).
