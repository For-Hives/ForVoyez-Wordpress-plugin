#!/usr/bin/env bash

PLUGIN_DIR="$( cd "$( dirname "${BASH_SOURCE[0]}" )/.." && pwd )"
if [ -f "$PLUGIN_DIR/.env.testing" ]; then
    export $(grep -v '^#' "$PLUGIN_DIR/.env.testing" | xargs)
fi

DB_NAME=${WP_TESTS_DB_NAME:-wordpress_test}
DB_USER=${WP_TESTS_DB_USER:-wp_test_user}
DB_PASS=${WP_TESTS_DB_PASSWORD:-votre_mot_de_passe_test}
DB_HOST=${WP_TESTS_DB_HOST:-localhost}
WP_VERSION=${WP_VERSION:-latest}
SKIP_DB_CREATE=${SKIP_DB_CREATE:-false}

TMPDIR=${TMPDIR:-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR:-$TMPDIR/wordpress-tests-lib}

download() {
    if command -v curl >/dev/null 2>&1; then
        curl -s "$1" > "$2";
    elif command -v wget >/dev/null 2>&1; then
        wget -nv -O "$2" "$1"
    else
        echo "Error: curl or wget is required to download files."
        exit 1
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+$ ]]; then
    WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
    if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
        WP_TESTS_TAG="tags/${WP_VERSION%??}"
    else
        WP_TESTS_TAG="tags/$WP_VERSION"
    fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
    WP_TESTS_TAG="trunk"
else
    download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
    LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
    if [[ -z "$LATEST_VERSION" ]]; then
        echo "Error: Unable to determine the latest WordPress version."
        exit 1
    fi
    WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

install_wp() {
    echo "WordPress is already installed via Composer at $WP_CORE_DIR"
}

install_test_suite() {
    local ioption='-i'

    if [[ $(uname -s) == 'Darwin' ]]; then
        local ioption='-i .bak'
    fi

    if [ ! -d $WP_TESTS_DIR ]; then
        mkdir -p $WP_TESTS_DIR
        svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
        svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
    fi

    if [ ! -f "$WP_TESTS_DIR"/wp-tests-config.php ]; then
        download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
        # Remove trailing slashes
        WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
        sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s:youremptytestdbnamehere:$DB_NAME:" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s:yourusernamehere:$DB_USER:" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s:yourpasswordhere:$DB_PASS:" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
    fi
}

install_db() {
    if [ ${SKIP_DB_CREATE} = "true" ]; then
        return 0
    fi

    # Use the provided DB_HOST instead of parsing it
    EXTRA=" --host=${DB_HOST}"

    # If DB_HOST contains a port, extract it
    if [[ $DB_HOST == *":"* ]]; then
        DB_PORT=${DB_HOST#*:}
        DB_HOST=${DB_HOST%:*}
        EXTRA+=" --port=${DB_PORT}"
    fi

    # Add protocol
    EXTRA+=" --protocol=tcp"

    # Attempt to create the database
    mysqladmin create $DB_NAME --user="$DB_USER" --password="$DB_PASS"$EXTRA

    # If creation fails, it might already exist, so let's not treat this as an error
    if [ $? -ne 0 ]; then
        echo "Note: Database creation failed. It might already exist."
    else
        echo "Database created successfully."
    fi
}

install_wp
install_test_suite
install_db