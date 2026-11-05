# ==============================================================================
# Dockerfile para Clínica Vida Salud
# Entorno: PHP 8.2 con Apache y soporte para PostgreSQL (PDO pdo_pgsql)
# Compatible con Render Web Service (Docker) y despliegue local
# ==============================================================================

FROM php:8.2-apache

# 1. Instalar dependencias del sistema y bibliotecas para PostgreSQL y Dompdf (GD y zip)
RUN apt-get update && apt-get install -y --no-install-recommends \
    libpq-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    unzip \
    curl \
    tzdata \
    && rm -rf /var/lib/apt/lists/*

# Configuración de zona horaria oficial a nivel sistema operativo
ENV TZ="America/La_Paz"
RUN ln -snf /usr/share/zoneinfo/$TZ /etc/localtime && echo $TZ > /etc/timezone

# 2. Configurar e instalar extensiones oficiales de PHP (PostgreSQL, GD con FreeType/JPEG, Zip)
RUN docker-php-ext-configure pgsql -with-pgsql=/usr/local/pgsql \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) pdo pdo_pgsql pgsql gd zip

# Instalar Composer para gestionar dependencias PHP (Dompdf) en Render
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

# 3. Habilitar módulos esenciales de Apache (rewrite y alias)
RUN a2enmod rewrite alias headers

# 4. Configurar puerto dinámico para Render (Render inyecta PORT=10000 o usa 80)
ENV PORT=80
ENV APACHE_DOCUMENT_ROOT=/var/www/html

# 5. Configuración de Apache VirtualHost con soporte para la ruta raíz y /clinicaVidaSalud/
RUN echo '<VirtualHost *:${PORT}>\n\
    ServerAdmin webmaster@localhost\n\
    DocumentRoot /var/www/html\n\
\n\
    # Alias para soportar rutas absolutas /clinicaVidaSalud/ existentes en la aplicación\n\
    Alias /clinicaVidaSalud /var/www/html\n\
\n\
    <Directory /var/www/html>\n\
        Options -Indexes +FollowSymLinks\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
\n\
    ErrorLog ${APACHE_LOG_DIR}/error.log\n\
    CustomLog ${APACHE_LOG_DIR}/access.log combined\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf \
    && echo 'Listen ${PORT}' > /etc/apache2/ports.conf

# 6. Directivas recomendadas de PHP para producción
RUN echo "date.timezone = America/La_Paz" > /usr/local/etc/php/conf.d/timezone.ini \
    && echo "display_errors = Off" > /usr/local/etc/php/conf.d/security.ini \
    && echo "log_errors = On" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "error_reporting = E_ALL & ~E_DEPRECATED & ~E_STRICT" >> /usr/local/etc/php/conf.d/security.ini \
    && echo "upload_max_filesize = 20M" >> /usr/local/etc/php/conf.d/uploads.ini \
    && echo "post_max_size = 25M" >> /usr/local/etc/php/conf.d/uploads.ini

# 7. Establecer directorio de trabajo
WORKDIR /var/www/html

# 8. Copiar los archivos del proyecto al contenedor
COPY . /var/www/html/

# Instalar dependencias PHP (Dompdf) optimizadas para producción
RUN composer install --no-dev --optimize-autoloader --no-interaction

# 9. Asignar propiedad y permisos de archivos al usuario www-data de Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# 10. Crear enlace simbólico de respaldo para compatibilidad de rutas locales (/clinicaVidaSalud)
RUN ln -s /var/www/html /var/www/html/clinicaVidaSalud \
    && chown -h www-data:www-data /var/www/html/clinicaVidaSalud

# 11. Exponer el puerto del contenedor
EXPOSE 80

# 12. Comando de arranque de Apache en primer plano
CMD ["apache2-foreground"]
