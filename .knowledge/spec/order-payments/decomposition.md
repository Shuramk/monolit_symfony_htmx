# Декомпозиция: оплата заказа

ТЗ: `.knowledge/spec/order-payments/spec.md`

## Задача 1: Схема и бережный перенос признаков

**Приоритет:** High
**Файлы:** migrations/Version20261009120000.php; src/Domain/Order/Services/LegacyPaymentMetadataImporter.php; src/Application/Command/ImportLegacyPaymentMetadataCommand.php.
**Детали:** nullable признаки + дата переноса; UUID unique; номера новых оплат отдельно;
апдейт только существующих совпадающих по источнику/id/заказу/сумме/дате оплат с ещё не перенесёнными признаками.
**Done when:**
- [x] Миграция применена к app, legacy read-only.
- [x] Перенос завершён, повторный запуск не перезаписывает признаки и суммы.

## Задача 2: Сущность и use case

**Приоритет:** High | **Зависит от:** Задача 1
**Файлы:** Entities/Order.php, Entities/OrderPayment.php, Repository/OrderPaymentRepository.php, UseCases/AddOrderPayment.php, UseCases/Dtos/AddOrderPaymentDto.php (src/Domain/Order).
**Детали:** проверки и изменение prepayment в Order; ORM payments cascade persist;
lock + идемпотентность + changelog в одной транзакции.
**Done when:**
- [x] Оплата учитывается ровно один раз; валидируются отмена, карта и суммы.
- [x] Ошибка откатывает оплату/сумму/журнал; тесты проходят.

## Задача 3: HTTP и форма

**Приоритет:** High | **Зависит от:** Задача 2
**Файлы:** src/Application/Controller/Order/OrderController.php; templates/order/_payment_form.html.twig; templates/order/show.html.twig; assets/controllers/order_payment_controller.js; assets/lib/order_payment.js; assets/styles/app.css.
**Детали:** право 228, общий CSRF, DTO/User автоматически; popup с UUID; JSON; повтор после ошибки использует прежний UUID.
**Done when:**
- [x] Форма открывается, русские ошибки, карта только без БН; успешная оплата обновляет карточку.
- [x] HTTP-проверки прав/CSRF/повтора и браузерная проверка завершены.
