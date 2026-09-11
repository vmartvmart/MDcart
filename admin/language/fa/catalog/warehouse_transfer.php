<?php
// Heading
$_['heading_title'] = 'انتقال بین انبارها';

// Text
$_['text_home'] = 'خانه';
$_['text_list'] = 'لیست انتقال‌ها';
$_['text_add'] = 'افزودن انتقال';
$_['text_edit'] = 'ویرایش انتقال';
$_['text_form'] = 'فرم انتقال';
$_['text_add_product'] = 'افزودن محصول';
$_['text_status'] = 'وضعیت';
$_['text_success'] = 'موفق: انتقال‌های انبار تغییر یافت!';
$_['text_no_results'] = 'نتیجه‌ای یافت نشد!';
$_['text_status_pending'] = 'در انتظار';
$_['text_status_in_transit'] = 'در حال حمل';
$_['text_status_received'] = 'دریافت شده';
$_['text_status_cancelled'] = 'لغو شده';
$_['text_history_created'] = 'انتقال ایجاد شد.';
$_['text_reserved_online'] = '%s عدد پیش‌فروش آنلاین شده';

// Column
$_['column_transfer_id'] = 'شماره انتقال';
$_['column_from'] = 'از انبار';
$_['column_to'] = 'به انبار';
$_['column_status'] = 'وضعیت';
$_['column_estimated_delivery_date'] = 'تحویل تخمینی';
$_['column_date_added'] = 'تاریخ ثبت';
$_['column_action'] = 'عملیات';
$_['column_product'] = 'محصول';
$_['column_quantity'] = 'تعداد';
$_['column_comment'] = 'توضیحات';

// Entry
$_['entry_from_warehouse'] = 'از انبار';
$_['entry_to_warehouse'] = 'به انبار';
$_['entry_shipping_cost'] = 'هزینه حمل';
$_['entry_estimated_delivery_date'] = 'تاریخ تحویل تخمینی';
$_['entry_actual_delivery_date'] = 'تاریخ تحویل واقعی';
$_['entry_comment'] = 'توضیحات';
$_['entry_status'] = 'وضعیت جدید';
$_['entry_product'] = 'محصول';
$_['entry_presell_online'] = 'قابل پیش‌فروش در فروشگاه آنلاین';

// Help
$_['help_shipping_cost'] = 'هزینه کل حمل این انتقال، به ارز پیش‌فرض فروشگاه. به‌صورت مساوی بین واحدها تقسیم و پس از دریافت به بهای تمام‌شده‌ی انبار مقصد اضافه می‌شود - اگر مقصد انبار فروش باشد، به قیمت فروش لحظه‌ای هم به‌طور خودکار اضافه می‌شود.';
$_['help_estimated_delivery_date'] = 'در هر زمان، حتی پس از شروع حمل، قابل اصلاح است.';
$_['help_presell_online'] = 'اگر فعال باشد، مشتریان فروشگاه آنلاین می‌توانند این کالا را حتی قبل از رسیدن این محموله بخرند (تا سقف تعداد همین انتقال) و در زمان خرید تاریخ تحویل تقریبی به آن‌ها نمایش داده می‌شود. فقط زمانی اثر دارد که انبار مقصد همان «انبار فروش» فروشگاه باشد.';

// Warning
$_['warning_presell_not_selling_warehouse'] = 'انبار مقصد این انتقال، انبار فروش فروشگاه نیست؛ بنابراین این گزینه فعلاً هیچ تاثیری روی فروشگاه آنلاین ندارد.';

// Button
$_['button_add'] = 'افزودن جدید';
$_['button_edit'] = 'ویرایش';
$_['button_save'] = 'ذخیره';
$_['button_back'] = 'بازگشت';
$_['button_add_product'] = 'افزودن';
$_['button_update_status'] = 'به‌روزرسانی وضعیت';

// Error
$_['error_permission'] = 'هشدار: شما اجازه‌ی ویرایش انتقال‌های انبار را ندارید!';
$_['error_warehouse'] = 'لطفاً هم انبار مبدا و هم انبار مقصد را انتخاب کنید!';
$_['error_same_warehouse'] = 'انبار مبدا و مقصد نمی‌توانند یکسان باشند!';
$_['error_products'] = 'لطفاً حداقل یک محصول اضافه کنید!';
$_['error_status'] = 'وضعیت نامعتبر است!';
$_['error_cancel_reserved'] = 'این انتقال دارای واحدهایی است که در حین حمل به مشتریان فروشگاه آنلاین پیش‌فروش شده‌اند و تا زمانی که آن سفارش‌ها رسیدگی نشوند قابل لغو نیست.';
