# Целевая схема: предприятие

## Цель

Заменить одиночную legacy-запись `sklad_enterprise.eID = 1` на UTF-8 модель без JSON-телефонов. Legacy остаётся неизменным источником до явного cutover.

## Таблицы

`enterprise_profiles`

- `id` bigint PK — равен legacy `eID`;
- `legal_name_vat`, `legal_name_pe`, `display_name`, `short_name` text nullable;
- `edrpou_vat`, `edrpou_pe`, `payment_account_vat`, `payment_account_pe` varchar nullable;
- `address`, `website`, `email`, `logo_path` varchar nullable;
- `created_at`, `updated_at` datetime.

`enterprise_phones`

- `id` bigint PK;
- `enterprise_profile_id` FK;
- `kind` varchar(20): `sales` or `accounting`;
- `position` smallint;
- `number` varchar(32);
- unique (`enterprise_profile_id`, `kind`, `position`).

`enterprise_warranty_texts`

- `enterprise_profile_id` unique FK;
- `source_html` longtext — исходный HTML из `wText`, без изменения источника;
- `created_at`, `updated_at` datetime.

Все текстовые поля — `utf8mb4`. В HTTP-ответ гарантийный HTML попадает только после `EnterpriseWarrantyHtmlSanitizer`.

## Импорт и откат

1. Add-only миграции создают новые таблицы; `sklad_enterprise` не меняется.
2. Importer читает `eID=1`, сохраняет его как `enterprise_profiles.id`, декодирует телефоны и отдельным upsert переносит `wText`.
3. Upsert по `id` делает повторный запуск идемпотентным.
4. Перед cutover сравниваются реквизиты, телефоны и гарантийный текст; новая write-форма не включается до сверки.
5. Откат — отключить новые маршруты: legacy-данные не затрагивались.
