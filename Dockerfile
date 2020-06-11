FROM wordpress:php7.2-apache

RUN sed -ex; \
     rm -rf /var/www/html/wp-content
COPY wp-content /var/www/html/wp-content
      

EXPOSE 80 443
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
