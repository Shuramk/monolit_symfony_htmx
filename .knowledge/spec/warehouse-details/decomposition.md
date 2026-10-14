# Перенос детализации склада

ТЗ: .knowledge/spec/warehouse-details/spec.md

## Задача 1: Схема и однократный импорт

Файлы: migrations/Version20261008170000.php,
src/Domain/Warehouse/Import/LegacyWarehouseImporter.php,
src/Application/Command/ImportLegacyWarehouseCommand.php.

**Done when:**
- [x] Все складские источники импортированы с сохранением ID и JSON оригинала.
- [x] Повтор импорта не меняет новые операции и существующие остатки.

## Задача 2: Операции и доменные правила

Файлы: Product.php, WarehouseRepository.php, ProductionTask.php,
ProductionRecipe.php, use cases ChangeStock/SetMinimum/ProductionTasks.

**Done when:**
- [x] Приход/списание, выработка, приёмка и сырьё транзакционны.
- [x] Повтор операции и приёмки не удваивает количество.
- [x] Права и CSRF проверены HTTP-тестами.

## Задача 3: Read model, экран, формы и печать

Файлы: WarehouseViewRepository.php, WarehouseView.php, WarehouseController.php,
templates/warehouse, assets/controllers/warehouse_controller.js, app.css.

**Done when:**
- [x] Таблица и popup воспроизводят legacy расчёты и внешний вид.
- [x] Все видимые кнопки работают без обращений на запись к legacy.
- [x] История/min/потребность/производство проверены тестами и браузером.
- [x] Twig/container lint, JavaScript tests и git diff --check проходят.
