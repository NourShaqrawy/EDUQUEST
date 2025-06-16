# =============================
# المرحلة 1: تثبيت الحزم بـ PHP 8.2 + Composer
# =============================
FROM php:8.2-cli AS composer-stage

# تثبيت المتطلبات و Composer
RUN apt-get update && apt-get install -y \
    unzip git curl libzip-dev libonig-dev libxml2-dev libpng-dev \
    && docker-php-ext-install zip mbstring xml

# تثبيت Composer يدويًا داخل PHP 8.2
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# إعداد مجلد المشروع
WORKDIR /app

# نسخ ملفات Composer فقط أولًا (للاستفادة من cache)
COPY composer.json composer.lock ./

# تثبيت البكجات بدون dev وبدون scripts لتفادي أخطاء Laravel المبدئية
RUN composer install --no-dev --optimize-autoloader --no-scripts -vvv

# ثم نسخ باقي ملفات المشروع
COPY . .

# =============================
# المرحلة 2: إعداد Laravel + Apache
# =============================
FROM php:8.2-apache

# تثبيت المتطلبات
RUN apt-get update && apt-get install -y \
    git zip unzip curl \
    libpng-dev libonig-dev libzip-dev libxml2-dev libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql mbstring zip xml gd

# تفعيل mod_rewrite
RUN a2enmod rewrite

# نسخ التطبيق من المرحلة الأولى
WORKDIR /var/www/html
COPY --from=composer-stage /app /var/www/html

# تعديل التصاريح
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 775 storage bootstrap/cache

# تغيير DocumentRoot إلى public/
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# نسخ سكربت التشغيل
COPY entrypoint.sh /entrypoint.sh
RUN chmod +x /entrypoint.sh

# إعداد نقطة البداية وتشغيل Apache
ENTRYPOINT ["/entrypoint.sh"]
CMD ["apache2-foreground"]  
