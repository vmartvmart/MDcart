<?php
// Heading
$_['heading_title'] = 'مدیریت قالب‌ها';

// Text
$_['text_home'] = 'داشبورد';
$_['text_help'] = 'فقط حساب سوپرادمین (Administrator) می‌تواند از این‌جا قالب اضافه یا حذف کند. یک بسته قالب یک فایل zip است که دقیقاً شامل ۳ فایل با یک نام مشترک باشد: {name}_header.twig، {name}_home.twig و theme-{name}.css (یک فایل اختیاری {name}.json با فیلد "label"/"label_fa" نام نمایشی قالب را تعیین می‌کند). پس از افزودن، بلافاصله در «طراحی > قالب فروشگاه» ظاهر می‌شود.';
$_['text_upload'] = 'آپلود بسته قالب (zip.)';
$_['text_choose_file'] = 'انتخاب فایل...';
$_['text_installed'] = 'قالب‌های نصب‌شده';
$_['text_active'] = 'فعال';
$_['text_loading'] = 'در حال انجام...';
$_['text_upload_success'] = 'قالب نصب شد! الان در سوییچر قالب موجوده.';
$_['text_delete_success'] = 'قالب حذف شد.';
$_['text_confirm_delete'] = 'این قالب حذف بشه؟ این کار قابل بازگشت نیست.';

// Button
$_['button_upload'] = 'آپلود';
$_['button_delete'] = 'حذف';
$_['button_back'] = 'بازگشت به سوییچر قالب';

// Error
$_['error_permission'] = 'هشدار: فقط حساب سوپرادمین می‌تواند قالب‌ها را مدیریت کند.';
$_['error_upload'] = 'لطفاً یک فایل zip. انتخاب کنید.';
$_['error_not_zip'] = 'فایل آپلودشده یک آرشیو zip. معتبر نیست.';
$_['error_too_large'] = 'حجم فایل زیاد است (حداکثر ۵ مگابایت).';
$_['error_package_invalid'] = 'بسته قالب نامعتبر است: باید دقیقاً شامل {name}_header.twig، {name}_home.twig و theme-{name}.css با یک نام مشترک باشد.';
$_['error_theme_exists'] = 'قالبی با این نام از قبل وجود دارد. اگر می‌خواهید جایگزین کنید، اول آن را حذف کنید.';
$_['error_invalid_theme'] = 'قالب نامعتبر است.';
$_['error_theme_active'] = 'قالب فعال فعلی قابل حذف نیست. اول قالب دیگری را فعال کنید.';
