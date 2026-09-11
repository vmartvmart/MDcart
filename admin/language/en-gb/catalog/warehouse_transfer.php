<?php
// Heading
$_['heading_title'] = 'Warehouse Transfers';

// Text
$_['text_home'] = 'Home';
$_['text_list'] = 'Transfer List';
$_['text_add'] = 'Add Transfer';
$_['text_edit'] = 'Edit Transfer';
$_['text_form'] = 'Transfer Form';
$_['text_add_product'] = 'Add Product';
$_['text_status'] = 'Status';
$_['text_success'] = 'Success: You have modified warehouse transfers!';
$_['text_no_results'] = 'No results!';
$_['text_status_pending'] = 'Pending';
$_['text_status_in_transit'] = 'In Transit';
$_['text_status_received'] = 'Received';
$_['text_status_cancelled'] = 'Cancelled';
$_['text_history_created'] = 'Transfer created.';
$_['text_reserved_online'] = '%s unit(s) pre-sold online';

// Column
$_['column_transfer_id'] = 'Transfer';
$_['column_from'] = 'From Warehouse';
$_['column_to'] = 'To Warehouse';
$_['column_status'] = 'Status';
$_['column_estimated_delivery_date'] = 'Estimated Delivery';
$_['column_date_added'] = 'Date Added';
$_['column_action'] = 'Action';
$_['column_product'] = 'Product';
$_['column_quantity'] = 'Quantity';
$_['column_comment'] = 'Comment';

// Entry
$_['entry_from_warehouse'] = 'From Warehouse';
$_['entry_to_warehouse'] = 'To Warehouse';
$_['entry_shipping_cost'] = 'Shipping Cost';
$_['entry_estimated_delivery_date'] = 'Estimated Delivery Date';
$_['entry_actual_delivery_date'] = 'Actual Delivery Date';
$_['entry_comment'] = 'Comment';
$_['entry_status'] = 'New Status';
$_['entry_product'] = 'Product';
$_['entry_presell_online'] = 'Sellable on the online store while in transit';

// Help
$_['help_shipping_cost'] = 'Total freight cost for this transfer, in the store\'s default currency. Split evenly per unit and added to the destination warehouse\'s landed cost when received - automatically added to the live sale price too, if the destination is the selling warehouse.';
$_['help_estimated_delivery_date'] = 'Can be corrected at any time, even after the transfer is in transit.';
$_['help_presell_online'] = 'When enabled, online storefront customers can buy this product before this shipment even arrives (up to this transfer\'s quantity), and are shown the estimated delivery date at purchase time. Only has an effect when the destination warehouse is the store\'s selling warehouse.';

// Warning
$_['warning_presell_not_selling_warehouse'] = 'This transfer\'s destination warehouse is not the store\'s selling warehouse, so this option currently has no effect on the online storefront.';

// Button
$_['button_add'] = 'Add New';
$_['button_edit'] = 'Edit';
$_['button_save'] = 'Save';
$_['button_back'] = 'Back';
$_['button_add_product'] = 'Add';
$_['button_update_status'] = 'Update Status';

// Error
$_['error_permission'] = 'Warning: You do not have permission to modify warehouse transfers!';
$_['error_warehouse'] = 'Please select both a from and to warehouse!';
$_['error_same_warehouse'] = 'From and To warehouse cannot be the same!';
$_['error_products'] = 'Please add at least one product!';
$_['error_status'] = 'Invalid status!';
$_['error_cancel_reserved'] = 'This transfer has units already pre-sold to online customers while in transit and cannot be cancelled until those orders are resolved.';
