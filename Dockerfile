FROM php:8.2-apache

# Install PDO MySQL and MySQLi extensions
RUN docker-php-ext-install pdo pdo_mysql mysqli

# Enable Apache mod_rewrite for .htaccess routing
RUN a2enmod rewrite

# Enable .htaccess overrides in Apache directory config
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# Configure PHP upload limits (10MB for avatars and craft photos)
RUN echo "upload_max_filesize = 10M" > /usr/local/etc/php/conf.d/uploads.ini && \
    echo "post_max_size = 12M" >> /usr/local/etc/php/conf.d/uploads.ini && \
    echo "memory_limit = 128M" >> /usr/local/etc/php/conf.d/uploads.ini

# Set working directory
WORKDIR /var/www/html

# Copy project files
COPY . /var/www/html/

# Create and set permissions for uploads directory
RUN mkdir -p /var/www/html/uploads && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 775 /var/www/html/uploads

# Expose default HTTP port
EXPOSE 80

# Run Apache foreground process
CMD ["apache2-foreground"]

