#!/bin/bash

#Site configuration options
SITE_TITLE="Dev Site"
ADMIN_USER=admin
ADMIN_PASS=admin
ADMIN_EMAIL="admin@localhost.com"
#Space-separated list of plugin ID's to install and activate
PLUGINS="forminator"

# URL=http://192.168.1.34:8088
URL=http://localhost:8088

#Set to true to wipe out and reset your wordpress install (on next container rebuild)
WP_RESET=true
my_wp=$(which wp)
my_rm=$(which rm)
my_cp=$(which cp)

wp() {
    sudo runuser -u www-data -- "$my_wp" "$@"
}
rm() {
    sudo runuser -u www-data -- "$my_rm" "$@"
}
cp() {
    sudo runuser -u www-data -- "$my_cp" "$@"
}

echo "Setting up WordPress"
DEVDIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )" >/dev/null 2>&1 && pwd )"
cd /var/www/html;
if $WP_RESET ; then
    if [ -f wp-config.php ]; then 
        echo "Resetting WP"
        wp plugin delete $PLUGINS
        wp db reset --yes
        rm wp-config.php;
    fi
fi

if [ ! -f wp-config.php ]; then 
    echo "Configuring";
    wp config create --dbhost="db" --dbname="wordpress" --dbuser="wp_user" --dbpass="wp_pass" --skip-check;
    wp core install --url="$URL" --title="$SITE_TITLE" --admin_user="$ADMIN_USER" --admin_email="$ADMIN_EMAIL" --admin_password="$ADMIN_PASS" --skip-email;
    wp plugin install $PLUGINS --activate
    #TODO: Only activate plugin if it contains files - i.e. might be developing a theme instead
    wp plugin activate plugin-dev

    #Data import
    cd $DEVDIR/data/
    for f in $(ls *.sql 2>/dev/null); do
        echo "importing SQL $f"
        wp --path=/var/www/html db import $f
    done

    for p in $(ls plugins/* 2>/dev/null); do
        cp -r "$p" /var/www/html/wp-content/plugins/.
        echo "installing plugin $p"
        wp --path=/var/www/html plugin activate $(basename $p)
    done

else
    echo "Already configured"
fi