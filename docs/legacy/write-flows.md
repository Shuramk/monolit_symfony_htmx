# Legacy write-flow inventory

Этот документ описывает обнаруженные операции записи в `../monolit_old`. Он не является исчерпывающим контрактом: до cutover каждого домена его нужно дополнить тестами на representative data.

## Предприятие

| Операция | Таблицы | Legacy-точка | Риск |
| --- | --- | --- | --- |
| Сохранение реквизитов | `sklad_enterprise` | `modules/enterprise.php:save_config_enterprise` | Поля телефонов сериализованы как JSON; текст и HTML смешаны. |

## Заказы, отгрузка и оплаты

| Операция | Таблицы | Legacy-точка | Риск |
| --- | --- | --- | --- |
| Создание заказа с позициями | `sklad_orders`, `sklad_ordered_carts` | `classes/Order.class.php:create_order` | Несколько INSERT без явной транзакции. |
| Изменение заказа | `sklad_orders`, `sklad_ordered_carts`, `sklad_order_editing_log`, `sklad_order_status_changelog` | `classes/Order.class.php` | Состояние, цены и позиции меняются отдельными запросами. |
| Удаление заказа | `sklad_orders`, `sklad_order_status_changelog`, `sklad_order_editing_log`, `sklad_ordered_carts` | `classes/Order.class.php:delete_order` | Каскад реализован вручную, не внешними ключами. |
| Частичная отгрузка | `sklad_order_partial_shipment`, `sklad_ordered_carts` | `modules/route_orders.php` | Специальный товар `productID=394` меняется напрямую. |
| Оплата и касса | `sklad_payments`, `sklad_cashbox` | `classes/Order.class.php` | Денежная операция должна быть одной транзакцией и иметь reconciliation. |
| Возврат | `sklad_order_returns`, `sklad_order_returns_carts` | `classes/Order.class.php` | Возврат и строки возврата должны быть атомарны. |

## Склад и производство

| Операция | Таблицы | Legacy-точка | Риск |
| --- | --- | --- | --- |
| Движение готовой продукции | `sklad_products`, `sklad_history` | `classes/Products.class.php:chg_quantity_product` | Остаток и история пишутся отдельными запросами. |
| Изменение рецептуры | `sklad_production_recipes`, `sklad_production_recipe_archive` | `modules/route_bk.php` | Архивирование и update должны быть одной транзакцией. |
| Приёмка материала | `sklad_materials_history`, `sklad_material_recv` | `modules/route_materials.php` | Цена/контрагент и статус приёмки обновляются по отдельности. |

## Расходы и транспорт

| Операция | Таблицы | Legacy-точка | Риск |
| --- | --- | --- | --- |
| Учёт входящего счёта/расхода | `sklad_incomingBills`, связанные расходы | `modules/route_expenses.php` | Связь платежа и обязательства не защищена транзакцией. |
| Внешняя доставка | `sklad_madeextdeliveries`, `sklad_tasks`, `sklad_orders` | `modules/route_orders.php` | Стоимость и статус задания связаны неявно. |

## Обязательные правила перед переносом write-flow

1. Описать предусловия, postconditions и audit trail.
2. Перенести операцию единой сервисной командой в транзакции.
3. Добавить reconciliation-запрос до и после переключения.
4. Не держать два write-экрана для одной операции после cutover.
