# Orders: read-model

## Legacy-источники

| Назначение | Таблица | Использование в первом срезе |
| --- | --- | --- |
| Заказ | `sklad_orders` | номер, даты, клиент, адрес, скидка/наценка, предоплата, комментарий, текущий status_id |
| Статус | `sklad_order_status` | название и цвет статуса |
| Клиент | `sklad_customers` | имя и телефоны для журнала и поиска |
| Позиции | `sklad_ordered_carts` | товар, количество, цена |
| Продукция | `sklad_products` | группа, наименование и единица для позиции |
| Оплаты | `sklad_payments`, `sklad_payments_cashoffice`, `sklad_payments_shipment` | суммы и история платежей |
| История статусов | `sklad_order_status_changelog` | дата, статус, комментарий, пользователь |
| Отгрузки | `sklad_order_partial_shipment`, связанные задачи | вычисление полноты отгрузки; точный mapping фиксируется перед добавлением в read-model |

## Legacy-точки чтения

- `classes/Orders.class.php:get_searched_orders_list` — параметры поиска и состав журнала.
- `classes/Order.class.php:get_order` — состав карточки и последовательность вычислений.
- `classes/Order.class.php:get_order_cart` — позиции и отгруженное количество.
- `classes/Order.class.php:get_order_paymentsh` — платежи за отгрузку.

## Правила реализации

1. Symfony читает legacy через `doctrine.dbal.legacy_connection`.
2. В запросах только явно перечисленные колонки.
3. Первый срез не вызывает legacy-классы: они смешивают чтение с изменением статуса.
4. Для карточки не использовать `SELECT *` и не сериализовать PHP-данные отгрузок как доверенные данные.
5. Все денежные значения передавать строками decimal; не складывать через float.
