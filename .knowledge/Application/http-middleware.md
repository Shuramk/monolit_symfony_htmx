# HTTP middleware и автоматические аргументы

## CSRF

`Application/Middleware/CsrfTokenListener` подписан на `kernel.controller` с приоритетом 100: после firewall, но до разрешения аргументов/DTO и вызова action. Он проверяет все изменяющие запросы авторизованных пользователей в сессионном firewall, независимо от маршрута и атрибутов контроллера. Безопасные методы, subrequest, анонимные запросы и stateless firewall не проходят эту проверку.

Единый ID токена — `session`. Формы передают `<input type="hidden" name="_token" value="{{ csrf_token('session') }}">`; JSON может передать `_token` в теле или `X-CSRF-Token` в заголовке. Значение тела имеет приоритет над заголовком. Неверный/отсутствующий токен даёт HTTP 403. Для JSON возвращается объект с `message`.

Login отдельно защищён штатным `form_login.enable_csrf` с токеном `authenticate`. Logout перехватывается firewall раньше `kernel.controller`, поэтому для него включён штатный `logout.enable_csrf` с теми же `_token` и ID `session`; форма выхода содержит токен.

## Resolver-ы

Регистрация и порядок заданы в `config/services.yaml`:

- `IdentityUserResolver`, приоритет 110: аргумент `Domain/Identity/Entities/User` получает текущего пользователя; обязательный пользователь без авторизации даёт отказ, nullable допускает null. Старый Security/IdentityUser заменён Doctrine-сущностью User, название resolver оставлено прежним.
- `RequestDtoResolver`, приоритет 100: все аргументы с `RequestDtoInterface` разбираются и валидируются общим `RequestDtoParser`, включая `OrderCustomerDto` и `OrderDraftDto`. Специального resolver заказа нет.

В action достаточно обычных типизированных аргументов. `CurrentUser`, `ValueResolver`, `IsCsrfTokenValid` для этой политики не нужны; универсальный parser не знает о сессиях и конкретных сценариях.

`Domain/Order/UseCases/CreateOrderFromDraft` собирает доменный `CreateOrderDto` из HTTP DTO заказчика и сессионного черновика, вызывает `CreateOrder` и очищает черновик только после успеха. `OrderDraftSession` находится в `Domain/Order/Services`. Для восстановления вложенных DTO из массива используется `RequestDtoParser::parseArrayToDto()` без искусственного HTTP Request. Сам `CreateOrderDto` больше не реализует RequestDtoInterface: это вход use case, а не тело запроса.

## Права и ошибки

Проверку прав пока не меняли: у заказов остаётся `IsGranted` на классе. Доменные отказы преобразует `DomainExceptionListener` в HTTP 400; внутренние исключения остаются серверными ошибками.

`JsonExceptionListener` оформляет ошибки JSON-запросов единообразно: объект `message` с исходным HTTP-статусом. Для ошибок 500 подробности скрываются. HTML-ошибки остаются у стандартного обработчика Symfony.

Проверки: `CsrfTokenListenerTest`, `IdentityUserResolverTest`, `RequestDtoResolverTest`, `OrderControllerTest`, `LoginControllerTest`. Они покрывают unsafe/safe методы, HTML/JSON/header-токены, анонимные/stateless/subrequest случаи, CSRF до DTO, обе формы заказа, пользователя по типу, права и выход из системы.
