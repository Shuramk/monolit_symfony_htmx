# Декомпозиция: отгрузки

## Задача 1: Схема и перевозчики
Файлы: Version20261015100000.php, ShippingImportRepository.php,
ImportLegacyShippingCarriersCommand.php.
Done when:
- [x] Add-only миграция применена в app/test.
- [x] Перенос перевозчиков повторяемый и не перезаписывает app.

## Задача 2: Команды
Файлы: ShippingRepository.php, ShippingTasks.php, ShippingTaskDto.php,
ShippingActionDto.php, Order.php, ApproveShipment.php.
Done when:
- [x] Создание/изменение/сборка/удаление и выполнение транзакционны.
- [x] Версии, права, допустимые количества проверяются сервером.
- [x] Склад, факт отгрузки, история и статусы меняются ровно один раз.

## Задача 3: Интерфейс
Файлы: ShippingView.php, Dtos/ShippingPageDto.php, ShippingController.php,
templates/shipping/*.twig, order/show.html.twig, IdentityMenuRepository.php.
Done when:
- [x] Список, архив, форма создания/редактирования и действия доступны по правам.
- [x] CRM, меню и карточка заказа ведут на новые страницы.
- [x] Сквозные тесты и регрессия проходят; Twig/контейнер проверены.

Проверка: 146 PHP tests / 4453 assertions, 54 JS tests; Twig и контейнер — OK.
Перенесено 39 перевозчиков, повторный импорт — 0. Legacy не изменялся.
Ревью: согласование использует repository вместо SQL в use case;
проверен единый порядок блокировок заказ → задача → товары по ID;
проверены откат второй позиции, no-op, версии и повторное выполнение.
Миграция, импорт, repository, ReadModel/DTO, controller, Twig и tests завершены.
