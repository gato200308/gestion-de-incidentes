FROM php:8.5.6RC3-fpm-alpine3.23

# Copiar todo el código al directorio raíz del servidor web
COPY . /var/www/html/

# Dar permisos de escritura al servidor Apache (para que pueda guardar y modificar los archivos .json)
RUN chown -R www-data:www-data /var/www/html/
RUN chmod -R 775 /var/www/html/

# Exponer el puerto 80 del contenedor
EXPOSE 80
