FROM tutum/wordpress-stackable
MAINTAINER Steven Jack <stevenmajack@gmail.com>
RUN apt-get update
RUN apt-get install php5-memcached -y
RUN rm -rf /var/www/html
RUN cp -rp /app /var/www
RUN mv /var/www/app /var/www/html
ADD wp-content/themes /var/www/html/wp-content/themes
ADD wp-content/plugins /var/www/html/wp-content/plugins
ADD wp-content/uploads /var/www/html/wp-content/uploads
ADD wp-config.php /var/www/html/wp-config.php
ADD php.ini /etc/php5/apache2/php.ini
RUN chown -R www-data:www-data /var/www/html
RUN touch /.mysql_db_created
