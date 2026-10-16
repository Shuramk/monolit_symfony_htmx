# PHP 8.3 — совместимость

Переход 2026-10-16 для размещения нового приложения на HOSTiQ без изменения
глобальной версии PHP старых сайтов.

- Runtime: PHP 8.3; `.php-version` выбирает его для Symfony CLI.
- Composer: `platform.php=8.3.0`, Symfony 7.4, DoctrineBundle 2.19,
  MigrationsBundle 3.7, Stimulus/UX Turbo 2.36, PHPUnit 12.5.
- Удалён `replace` для polyfill-php84: на PHP 8.3 нужен настоящий polyfill.
- Enterprise/EnterprisePhone больше не final, чтобы Doctrine мог генерировать
  ненативные прокси. Бизнес-правила и ORM mapping не менялись.
- `controller_resolver.auto_mapping=false` явно сохраняет прежнюю настройку
  DoctrineBundle 3; у контроллеров остаются собственные DTO/User resolvers.
- В UserRoleAccessTest вызов метода после `new` взят в скобки для PHP 8.3.
- Из controllers.json убран autoimport Mercure-файла из UX Turbo 3, не используемый
  UX Turbo 2. Mercure-контроллер по-прежнему выключен.

Проверены PHP-синтаксис проекта, Composer validate/check-platform-reqs/audit,
стандартные post-install scripts, dev/test/prod cache clear, container lint,
64 Twig-шаблона, production asset-map:compile и 72 JavaScript tests.
Контрольный связанный PHP-набор: 26 tests / 334 assertions — успешно на PHP 8.3.35.

Полный набор на PHP 8.3: 266 tests, те же 3 проблемы, что до смены зависимостей:

- ProductMappingTest::testAvailabilityRulesAndBulkLookup — отсутствующий товар
  в старой фикстуре (`isAvailableForOrder()` вызывается у null).
- LoginControllerTest::testLegacyStartPageRedirectStaysOnTheCurrentHost —
  ожидание legacy URL вместо перенесённого `/shipping`.
- ProductCatalogControllerTest::testCatalogRequiresItsOwnPermissionAndReturnsAnHtmxFragment —
  фиксированное количество 1677 вместо фактических 1678.

Эти тесты не скрывались и не исправлялись в рамках перехода. На локальном PHP 8.3
установлен недостающий php8.3-mysql; системная версия PHP по умолчанию не менялась.
Новых миграций, импорта данных или операций записи в legacy для перехода нет.
