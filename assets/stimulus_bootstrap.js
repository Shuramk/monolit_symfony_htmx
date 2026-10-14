import { startStimulusApp } from '@symfony/stimulus-bundle';
import OrderFormController from './controllers/order_form_controller.js';
import JsonFormController from './controllers/json_form_controller.js';
import OrderSearchController from './controllers/order_search_controller.js';
import ProductTableController from './controllers/product_table_controller.js';
import WarehouseController from './controllers/warehouse_controller.js';
import OrderPaymentController from './controllers/order_payment_controller.js';
import AutoSubmitController from './controllers/auto_submit_controller.js';
import UserPermissionsController from './controllers/user_permissions_controller.js';
import CrmController from './controllers/crm_controller.js';
import CrmBoardController from './controllers/crm_board_controller.js';
import OrderStatusController from './controllers/order_status_controller.js';

const app = startStimulusApp();
app.register('order-form', OrderFormController);
app.register('json-form', JsonFormController);
app.register('order-search', OrderSearchController);
app.register('product-table', ProductTableController);
app.register('warehouse', WarehouseController);
app.register('order-payment', OrderPaymentController);
app.register('auto-submit', AutoSubmitController);
app.register('user-permissions', UserPermissionsController);
app.register('crm', CrmController);
app.register('crm-board', CrmBoardController);
app.register('order-status', OrderStatusController);
// register any custom, 3rd party controllers here
// app.register('some_controller_name', SomeImportedController);
