#!/bin/bash

# تأكيد الصلاحيات داخل الحاوية أثناء التشغيل
chmod -R 775 /var/www/html/storage /var/www/html/bootstrap/cache
chown -R www-data:www-data /var/www/html/storage /var/www/html/bootstrap/cache

# تنظيف الكاشات وتشغيل الهجرات
php artisan cache:clear
php artisan config:clear
php artisan view:clear

# احذر من migrate:fresh لأنه يحذف الجداول ويعيد إنشائها، استخدمه فقط إذا تريد إعادة قاعدة البيانات كاملة
php artisan migrate:fresh --force

# تشغيل Apache في المقدمة
exec apache2-foreground
