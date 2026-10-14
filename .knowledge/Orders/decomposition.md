# Декомпозиция: Orders read-only

## Задача 1: Чистый расчёт состояния заказа

**Приоритет:** High | **Зависит от:** —

**Что делаем:** Выносим расчёт полной оплаты, полной отгрузки и рекомендованного финального статуса в чистый доменный сервис без записи.

**Файлы:**
- `создать: src/Domain/Order/ReadModel/OrderStateCalculator.php`
- `создать: tests/Domain/Order/ReadModel/OrderStateCalculatorTest.php`

**Done when:**
- [x] Расчёт не использует DBAL и не пишет в legacy.
- [x] Покрыты полностью оплаченный/отгруженный, неполный и финальный статус.

## Задача 2: Журнал заказов

**Приоритет:** High | **Зависит от:** Задача 1

**Что делаем:** Реализуем защищённый `GET /orders` с HTMX-поиском по номеру, клиенту, телефону и статусу.

**Файлы:**
- `создать: src/Domain/Order/Repository/OrderViewRepository.php`
- `создать: src/Domain/Order/ReadModel/OrderListView.php`
- `создать: src/Controller/OrderController.php`
- `создать: templates/order/index.html.twig`
- `создать: templates/order/_rows.html.twig`
- `создать: tests/Controller/OrderControllerTest.php`

**Done when:**
- [x] Маршрут требует legacy permission `did=220`.
- [x] Поиск параметризован, вывод ограничен и не меняет legacy.
- [x] HTMX возвращает фрагмент списка; после добавления фильтров/пагинации — контролы, подписи, страницы и таблицу вместе.

## Задача 3: Карточка заказа

**Приоритет:** High | **Зависит от:** Задача 2

**Что делаем:** Добавляем read-only карточку заказа с позициями, платежами, историей статусов и вычисленным состоянием.

**Файлы:**
- `изменить: src/Domain/Order/Repository/OrderViewRepository.php`
- `изменить: src/Domain/Order/ReadModel/OrderListView.php`
- `изменить: src/Controller/OrderController.php`
- `создать: templates/order/show.html.twig`

**Done when:**
- [x] Карточка не вызывает legacy-классы и не выполняет DML.
- [x] Деньги не проходят через float.
- [x] Неизвестный номер возвращает 404.
