# Перенос CRM без чата

ТЗ: .knowledge/spec/crm/spec.md

## Задача 5: Строки продукции и кнопки сделки

Приоритет: High. Зависит от: 2,4.
Файлы: CrmView.php, CrmRepository.php, SaveDeal.php, deal_edit.html.twig,
templates/crm/_products.html.twig, assets/controllers/order_form_controller.js,
assets/styles/app.css, tests/Controller/CrmControllerTest.php.
Детали: групповой выбор и вариант, расчёт скидки на сервере, общие расчёты
формы заказа, компактные размеры, бюджет/НДС/итоги, кнопка отмены и новый заказ.
Done when:
- [x] Поля строки и все итоги работают, скидка сохраняется без двойной наценки.
- [x] Отключённый выбранный товар сохраняется, последняя строка удаляется.
- [x] HTTP/JS тесты и проверки сборки проходят.

## Задача 6: Документы сделки

Приоритет: High. Зависит от: 5.
Файлы: src/Domain/Crm/ReadModel/DealDocument.php,
src/Application/Controller/Crm/DealDocumentsController.php,
templates/crm/deal_edit.html.twig, tests/Controller/CrmDealDocumentsTest.php.
Детали: DOCX для счёта и коммерческого, app-only реквизиты, Decimal-суммы,
серверные проверки прав/состояния/продукции, безопасный XML.
Done when:
- [x] Счёт и коммерческое скачиваются, ZIP/XML и суммы проверены тестом.
- [x] Нет продукции/отмена/отсутствие прав не дают скачать документ.
- [x] Документы не меняют сделку и не пишут в legacy.

## Задача 1: Хранилище и безопасный импорт

Приоритет: High. Зависимости: нет.
Файлы: migrations/Version20261012090000.php,
src/Application/Command/ImportLegacyCrmCommand.php,
src/Domain/Crm/Import/LegacyCrmImporter.php.
Детали: новые crm_* таблицы, индексы, сохранение исходных ID и сериализованной
истории в JSON; одноразовый маркер внутри транзакции, legacy только SELECT.
Done when:
- [ ] Схема применена app/test, импортированы все сделки/задачи/история/уведомления.
- [ ] Повторный импорт не меняет новые данные; тест импорта проходит.

## Задача 2: Сделки

Приоритет: High. Зависит от: 1.
Файлы: src/Domain/Crm/Entities/Deal.php, Repository/CrmRepository.php,
UseCases/SaveDeal.php, UseCases/DealActions.php, ReadModel/CrmView.php,
src/Application/Controller/Crm/DealController.php, templates/crm/deals*.html.twig.
Детали: CRUD/контакты/Decimal/состояния/конверсия с транзакцией и сохранением истории.
Done when:
- [ ] Все согласованные действия сделок и счётчики работают с проверкой прав.
- [ ] История и отменённые сделки доступны; конверсия идемпотентна.

## Задача 3: Задачи сотрудников и директора

Приоритет: High. Зависит от: 1.
Файлы: src/Domain/Crm/Entities/Task.php, UseCases/SaveTask.php,
UseCases/TaskActions.php, src/Application/Controller/Crm/TaskController.php,
templates/crm/tasks*.html.twig, templates/crm/task_edit.html.twig.
Детали: shared tasks, нормализованные участники, уведомления и серверные ACL,
версии, история, архив, директорские комментарии, прикреплённые файлы.
Done when:
- [ ] Личные/директорские списки, CRUD, состояния, уведомления и файлы работают.
- [ ] Недоступные действия отклоняются сервером, не только скрыты в UI.

## Задача 4: Интеграция интерфейса и проверка

Приоритет: High. Зависит от: 2,3.
Файлы: assets/controllers/crm_controller.js, assets/styles/app.css,
src/Domain/Identity/Repository/IdentityMenuRepository.php, tests/Controller/CrmControllerTest.php.
Детали: legacy-подобный компактный интерфейс, навигация, общая пагинация.
Done when:
- [ ] CRM-ссылки ведут в Symfony, чат не изменён.
- [ ] HTTP/functional/JS тесты, lint, assets и git diff --check проходят.
