# Customers: новая БД и cutover

- `customers.id` сохраняет legacy `sklad_customers.customerID`.
- Команда `legacy:import-customers` делает upsert: повторный импорт не удаляет клиентов, на которых уже ссылаются новые заказы.
- `orders.customer_id` — nullable FK на `customers.id`; исторические поля `customer_name` и `customer_phone` остаются снимком на момент заказа.
- При повторном `legacy:import-orders` ссылка проставляется по исходному `customerID`; неизвестная или удалённая запись остаётся `NULL`.
