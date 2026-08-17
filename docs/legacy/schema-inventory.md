# Legacy schema inventory

Этот отчёт сформирован только чтением метаданных MariaDB.
Количество строк является оценкой `information_schema` для InnoDB.

| Таблица | Engine | Collation | Строк (оценка) | Стратегия |
| --- | --- | --- | ---: | --- |
| `sklad_arrears_log` | InnoDB | utf8mb3_general_ci | 968 | ⚠️ explicit conversion |
| `sklad_articles_expenditure` | InnoDB | latin1_swedish_ci | 49 | ⚠️ explicit conversion |
| `sklad_b_chart_of_accounts` | InnoDB | utf8mb3_general_ci | 322 | ⚠️ explicit conversion |
| `sklad_calls_visitors` | InnoDB | utf8mb3_general_ci | 227 | ⚠️ explicit conversion |
| `sklad_carpark` | InnoDB | utf8mb3_general_ci | 21 | ⚠️ explicit conversion |
| `sklad_carpark_stats` | InnoDB | utf8mb3_general_ci | 6 | ⚠️ explicit conversion |
| `sklad_carriers` | InnoDB | cp1251_general_ci | 39 | ⚠️ explicit conversion |
| `sklad_car_card` | InnoDB | utf8mb3_general_ci | 27 | ⚠️ explicit conversion |
| `sklad_car_mileage` | InnoDB | latin1_swedish_ci | 554 | ⚠️ explicit conversion |
| `sklad_car_mileage_endpoint` | InnoDB | utf8mb3_general_ci | 486 | ⚠️ explicit conversion |
| `sklad_car_monitor` | InnoDB | utf8mb3_general_ci | 7337 | ⚠️ explicit conversion |
| `sklad_car_refueling` | InnoDB | latin1_swedish_ci | 1723 | ⚠️ explicit conversion |
| `sklad_cashbox` | InnoDB | utf8mb3_general_ci | 6 | ⚠️ explicit conversion |
| `sklad_ca_contracts` | InnoDB | utf8mb3_general_ci | 295 | ⚠️ explicit conversion |
| `sklad_ca_contracts_materials` | InnoDB | cp1251_general_ci | 344 | ⚠️ explicit conversion |
| `sklad_ca_ctrl_points` | InnoDB | utf8mb3_general_ci | 24 | ⚠️ explicit conversion |
| `sklad_ca_groups` | InnoDB | utf8mb3_general_ci | 6 | ⚠️ explicit conversion |
| `sklad_ca_payments` | InnoDB | utf8mb3_general_ci | 2497 | ⚠️ explicit conversion |
| `sklad_ca_persons` | InnoDB | utf8mb3_general_ci | 47 | ⚠️ explicit conversion |
| `sklad_chat` | InnoDB | utf8mb3_general_ci | 0 | ⚠️ explicit conversion |
| `sklad_contacts` | InnoDB | utf8mb3_general_ci | 151 | ⚠️ explicit conversion |
| `sklad_counter_agents` | InnoDB | utf8mb3_general_ci | 298 | ⚠️ explicit conversion |
| `sklad_crm_tasks` | InnoDB | utf8mb3_general_ci | 182 | ⚠️ explicit conversion |
| `sklad_crm_tasks_log` | InnoDB | utf8mb3_general_ci | 466 | ⚠️ explicit conversion |
| `sklad_crm_tasks_notifications` | InnoDB | utf8mb3_general_ci | 23 | ⚠️ explicit conversion |
| `sklad_currency_types` | MyISAM | utf8mb3_general_ci | 4 | ⚠️ explicit conversion |
| `sklad_customers` | InnoDB | utf8mb3_general_ci | 5817 | ⚠️ explicit conversion |
| `sklad_divisions` | InnoDB | utf8mb3_general_ci | 175 | ⚠️ explicit conversion |
| `sklad_documents` | InnoDB | latin1_swedish_ci | 571 | ⚠️ explicit conversion |
| `sklad_ei_cards` | InnoDB | utf8mb3_general_ci | 2505 | ⚠️ explicit conversion |
| `sklad_enterprise` | InnoDB | utf8mb3_general_ci | 1 | ⚠️ explicit conversion |
| `sklad_events` | InnoDB | utf8mb3_general_ci | 328 | ⚠️ explicit conversion |
| `sklad_expcarpark` | InnoDB | utf8mb3_general_ci | 2488 | ⚠️ explicit conversion |
| `sklad_expendable_invoices` | InnoDB | latin1_swedish_ci | 1166 | ⚠️ explicit conversion |
| `sklad_expenses` | InnoDB | utf8mb3_general_ci | 21480 | ⚠️ explicit conversion |
| `sklad_forms` | InnoDB | latin1_swedish_ci | 29 | ⚠️ explicit conversion |
| `sklad_history` | InnoDB | utf8mb3_general_ci | 83142 | ⚠️ explicit conversion |
| `sklad_incomingBills` | InnoDB | latin1_swedish_ci | 2 | ⚠️ explicit conversion |
| `sklad_instruments_cards` | InnoDB | latin1_swedish_ci | 57 | ⚠️ explicit conversion |
| `sklad_instr_category` | InnoDB | utf8mb3_general_ci | 31 | ⚠️ explicit conversion |
| `sklad_instr_history` | InnoDB | utf8mb3_general_ci | 2650 | ⚠️ explicit conversion |
| `sklad_instr_inventory` | InnoDB | utf8mb3_general_ci | 0 | ⚠️ explicit conversion |
| `sklad_instr_posting` | InnoDB | utf8mb3_general_ci | 410 | ⚠️ explicit conversion |
| `sklad_instr_products` | InnoDB | utf8mb3_general_ci | 657 | ⚠️ explicit conversion |
| `sklad_instr_reception` | InnoDB | utf8mb3_general_ci | 280 | ⚠️ explicit conversion |
| `sklad_instr_storage` | InnoDB | utf8mb3_general_ci | 663 | ⚠️ explicit conversion |
| `sklad_instr_transmission` | InnoDB | utf8mb3_general_ci | 648 | ⚠️ explicit conversion |
| `sklad_instr_write_off` | InnoDB | utf8mb3_general_ci | 303 | ⚠️ explicit conversion |
| `sklad_invoices` | InnoDB | utf8mb3_general_ci | 2808 | ⚠️ explicit conversion |
| `sklad_invoices_card` | InnoDB | utf8mb3_general_ci | 8061 | ⚠️ explicit conversion |
| `sklad_madeextdeliveries` | InnoDB | utf8mb3_general_ci | 227 | ⚠️ explicit conversion |
| `sklad_made_deliveries` | InnoDB | utf8mb3_general_ci | 2484 | ⚠️ explicit conversion |
| `sklad_materials` | InnoDB | utf8mb3_general_ci | 45 | ⚠️ explicit conversion |
| `sklad_materials_history` | InnoDB | utf8mb3_general_ci | 46096 | ⚠️ explicit conversion |
| `sklad_material_balances` | InnoDB | latin1_swedish_ci | 30 | ⚠️ explicit conversion |
| `sklad_material_recv` | InnoDB | utf8mb4_unicode_ci | 2730 | совместимо с целевой кодировкой |
| `sklad_measures` | InnoDB | utf8mb3_general_ci | 8 | ⚠️ explicit conversion |
| `sklad_messages` | InnoDB | utf8mb3_general_ci | 4659 | ⚠️ explicit conversion |
| `sklad_MExpenses_BSU` | InnoDB | utf8mb3_general_ci | 2 | ⚠️ explicit conversion |
| `sklad_ordered_carts` | InnoDB | utf8mb3_general_ci | 41610 | ⚠️ explicit conversion |
| `sklad_orders` | InnoDB | utf8mb3_general_ci | 13663 | ⚠️ explicit conversion |
| `sklad_order_editing_log` | InnoDB | cp1251_general_ci | 101376 | ⚠️ explicit conversion |
| `sklad_order_partial_shipment` | InnoDB | utf8mb3_general_ci | 27966 | ⚠️ explicit conversion |
| `sklad_order_returnDraft` | InnoDB | latin1_swedish_ci | 1282 | ⚠️ explicit conversion |
| `sklad_order_returns` | InnoDB | utf8mb3_general_ci | 5354 | ⚠️ explicit conversion |
| `sklad_order_returns_carts` | InnoDB | utf8mb3_general_ci | 6059 | ⚠️ explicit conversion |
| `sklad_order_status` | InnoDB | utf8mb3_general_ci | 7 | ⚠️ explicit conversion |
| `sklad_order_status_changelog` | InnoDB | utf8mb3_general_ci | 227757 | ⚠️ explicit conversion |
| `sklad_payments` | InnoDB | utf8mb3_general_ci | 22786 | ⚠️ explicit conversion |
| `sklad_payments_cashoffice` | InnoDB | utf8mb3_general_ci | 23750 | ⚠️ explicit conversion |
| `sklad_payments_shipment` | InnoDB | cp1251_general_ci | 2888 | ⚠️ explicit conversion |
| `sklad_production_recipes` | InnoDB | utf8mb3_general_ci | 470 | ⚠️ explicit conversion |
| `sklad_production_recipes_off` | InnoDB | utf8mb3_general_ci | 4 | ⚠️ explicit conversion |
| `sklad_production_recipe_archive` | InnoDB | utf8mb3_general_ci | 416 | ⚠️ explicit conversion |
| `sklad_products` | InnoDB | utf8mb3_general_ci | 1677 | ⚠️ explicit conversion |
| `sklad_products_drafts` | InnoDB | utf8mb3_general_ci | 57 | ⚠️ explicit conversion |
| `sklad_products_moving` | InnoDB | utf8mb3_general_ci | 17 | ⚠️ explicit conversion |
| `sklad_product_price_archive` | InnoDB | utf8mb3_general_ci | 6448 | ⚠️ explicit conversion |
| `sklad_requests` | InnoDB | utf8mb3_general_ci | 677 | ⚠️ explicit conversion |
| `sklad_request_cards` | InnoDB | utf8mb3_general_ci | 2191 | ⚠️ explicit conversion |
| `sklad_request_log` | InnoDB | utf8mb3_general_ci | 5441 | ⚠️ explicit conversion |
| `sklad_request_tasks` | InnoDB | utf8mb3_general_ci | 2942 | ⚠️ explicit conversion |
| `sklad_stacker_payments` | InnoDB | utf8mb3_general_ci | 4308 | ⚠️ explicit conversion |
| `sklad_supplies_tools_cats` | InnoDB | utf8mb4_unicode_ci | 2 | совместимо с целевой кодировкой |
| `sklad_supplies_tools_history` | InnoDB | utf8mb4_unicode_ci | 87 | совместимо с целевой кодировкой |
| `sklad_supplies_tools_ones` | InnoDB | utf8mb4_unicode_ci | 3 | совместимо с целевой кодировкой |
| `sklad_tasks` | InnoDB | utf8mb3_general_ci | 22258 | ⚠️ explicit conversion |
| `sklad_tasks_carts` | InnoDB | utf8mb3_general_ci | 55591 | ⚠️ explicit conversion |
| `sklad_tasks_production` | InnoDB | utf8mb3_general_ci | 5502 | ⚠️ explicit conversion |
| `sklad_tasks_shipment_collected` | InnoDB | cp1251_general_ci | 5317 | ⚠️ explicit conversion |
| `sklad_tasks_transport_list` | InnoDB | cp1251_general_ci | 70 | ⚠️ explicit conversion |
| `sklad_task_p_drafts` | InnoDB | utf8mb3_general_ci | 32951 | ⚠️ explicit conversion |
| `sklad_timeTable` | InnoDB | utf8mb3_general_ci | 1162 | ⚠️ explicit conversion |
| `sklad_timeTable_shifts` | InnoDB | utf8mb3_general_ci | 287 | ⚠️ explicit conversion |
| `sklad_tt_add_works` | InnoDB | utf8mb3_general_ci | 3746 | ⚠️ explicit conversion |
| `sklad_tt_presses` | InnoDB | utf8mb3_general_ci | 1127 | ⚠️ explicit conversion |
| `sklad_t_contacts` | InnoDB | utf8mb3_general_ci | 12 | ⚠️ explicit conversion |
| `sklad_users` | MyISAM | utf8mb3_general_ci | 124 | ⚠️ explicit conversion |
| `sklad_user_permissions` | InnoDB | cp1251_general_ci | 101 | ⚠️ explicit conversion |
| `sklad_visits_records` | InnoDB | utf8mb3_general_ci | 2398 | ⚠️ explicit conversion |
| `sklad_wCredits` | InnoDB | utf8mb3_general_ci | 13 | ⚠️ explicit conversion |
| `sklad_wCreditsHistory` | InnoDB | utf8mb3_general_ci | 0 | ⚠️ explicit conversion |
| `sklad_workers` | InnoDB | utf8mb3_general_ci | 726 | ⚠️ explicit conversion |
| `sklad_workers_info` | InnoDB | utf8mb3_general_ci | 160 | ⚠️ explicit conversion |
| `sklad_wPayments` | InnoDB | utf8mb3_general_ci | 12941 | ⚠️ explicit conversion |
