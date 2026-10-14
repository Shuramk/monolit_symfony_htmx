# Декомпозиция: статусы заказа

ТЗ: .knowledge/spec/order-status/spec.md

## Задача 1: Команда, модель и данные

Файлы: Order.php, OrderRepository.php, ChangeOrderStatus.php,
ChangeOrderStatusDto.php, CancelOrderToCrm.php, CrmRepository.php,
OrderViewRepository.php, Version20261013100000.php.
Done when:
- [ ] Блокировка, версия, права роли и ограничения отмены проверяются сервером.
- [ ] Заказ, копия CRM и история изменяются одной транзакцией.
- [ ] Копия помечена отменённой, исходные записи не удаляются.

## Задача 2: Контроллер и карточка

Файлы: OrderStatusController.php, OrderController.php, OrderDetailsView.php,
OrderDetailsDto.php, show.html.twig, _status_form.html.twig, deal_edit.html.twig.
Done when:
- [ ] Форма работает с DTO и общей отправкой JSON; видима только с правом 229.
- [ ] Недопустимые варианты скрыты, причина обязательна для отмены.
- [ ] CRM-копия содержит обратную ссылку на заказ; переход зависит от права CRM.

## Задача 3: Проверка

Файлы: tests/Controller/OrderStatusControllerTest.php и смежные тесты заказов/CRM.
Done when:
- [ ] Integration tests воспроизводят новые сценарии до реализации (RED).
- [ ] Новые и регрессионные PHP/JS тесты проходят.
- [ ] Миграция применена к app/test, контейнер/Twig/ресурсы/кеш проверены.
