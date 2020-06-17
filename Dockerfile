FROM wordpress:php7.2-apache

RUN sed -ex; \
        rm -rf wp-content
COPY wp-content wp-content
RUN   chown -R www-data:www-data  .   

EXPOSE 80 443
ENTRYPOINT ["docker-entrypoint.sh"]
CMD ["apache2-foreground"]
