#!/usr/bin/env bash

if [ $# -lt 3 ]; then
    echo "usage: $0 <db-name> <db-user> <db-pass> [db-host] [wp-version] [skip-database-creation]"
    exit 1
fi

DB_NAME=$1
DB_USER=${2-root}
DB_PASS=${3-''}
DB_HOST=${4-localhost}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

# Base conf for MySQL
MYSQL_ROOT_PASSWORD=${MYSQL_ROOT_PASSWORD-'root'}

TMPDIR=${TMPDIR-/tmp}
TMPDIR=$(echo $TMPDIR | sed -e "s/\/$//")
WP_TESTS_DIR=${WP_TESTS_DIR-$TMPDIR/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-$TMPDIR/wordpress/}

download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

if [[ $WP_VERSION =~ ^[0-9]+\.[0-9]+\$ ]]; then
    WP_TESTS_TAG="branches/$WP_VERSION"
elif [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0-9]+ ]]; then
    if [[ $WP_VERSION =~ [0-9]+\.[0-9]+\.[0] ]]; then
        # version x.x.0 means the first release of the major version, so strip off the .0 and download version x.x
        WP_TESTS_TAG="tags/${WP_VERSION%??}"
    else
        WP_TESTS_TAG="tags/$WP_VERSION"
    fi
elif [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
    WP_TESTS_TAG="trunk"
else
    # http serves a single offer, whereas https serves multiple. we only want one
    download http://api.wordpress.org/core/version-check/1.7/ /tmp/wp-latest.json
    grep '[0-9]+\.[0-9]+(\.[0-9]+)?' /tmp/wp-latest.json
    LATEST_VERSION=$(grep -o '"version":"[^"]*' /tmp/wp-latest.json | sed 's/"version":"//')
    if [[ -z "$LATEST_VERSION" ]]; then
        echo "Latest WordPress version could not be found"
        exit 1
    fi
    WP_TESTS_TAG="tags/$LATEST_VERSION"
fi

echo "Using WordPress version: $WP_TESTS_TAG"

# Function to run MySQL commands
run_mysql_command() {
    sudo mysql --host="$DB_HOST" --user="root" --password="$MYSQL_ROOT_PASSWORD" -e "$1"
}

# Function to reset database and user
reset_db_and_user() {
    echo "Resetting database and user..."

    # Drop database if it exists
    run_mysql_command "DROP DATABASE IF EXISTS \`$DB_NAME\`;"
    echo "Database $DB_NAME dropped (if it existed)."

    # Drop user if it exists
    run_mysql_command "DROP USER IF EXISTS '$DB_USER'@'localhost';"
    echo "User $DB_USER dropped (if it existed)."

    # Create database
    run_mysql_command "CREATE DATABASE \`$DB_NAME\`;"
    echo "Database $DB_NAME created."

    # Create user and grant privileges
    run_mysql_command "CREATE USER '$DB_USER'@'localhost' IDENTIFIED BY '$DB_PASS';"
    run_mysql_command "GRANT ALL PRIVILEGES ON \`$DB_NAME\`.* TO '$DB_USER'@'localhost';"
    run_mysql_command "FLUSH PRIVILEGES;"
    echo "User $DB_USER created and granted privileges on $DB_NAME."
}

# Function to install WordPress
install_wp() {
    if [ -d $WP_CORE_DIR ]; then
        rm -rf $WP_CORE_DIR
    fi

    mkdir -p $WP_CORE_DIR

    if [[ $WP_VERSION == 'nightly' || $WP_VERSION == 'trunk' ]]; then
        mkdir -p $TMPDIR/wordpress-nightly
        download https://wordpress.org/nightly-builds/wordpress-latest.zip  $TMPDIR/wordpress-nightly/wordpress-nightly.zip
        unzip -q $TMPDIR/wordpress-nightly/wordpress-nightly.zip -d $TMPDIR/wordpress-nightly/
        mv $TMPDIR/wordpress-nightly/wordpress/* $WP_CORE_DIR
    else
        if [ $WP_VERSION == 'latest' ]; then
            local ARCHIVE_NAME='latest'
        else
            local ARCHIVE_NAME="wordpress-$WP_VERSION"
        fi
        download https://wordpress.org/${ARCHIVE_NAME}.tar.gz  $TMPDIR/wordpress.tar.gz
        tar --strip-components=1 -zxmf $TMPDIR/wordpress.tar.gz -C $WP_CORE_DIR
    fi

    download https://raw.github.com/markoheijnen/wp-mysqli/master/db.php $WP_CORE_DIR/wp-content/db.php

    if [ ! -f "$WP_CORE_DIR/wp-includes/version.php" ]; then
        echo "WordPress installation failed."
        exit 1
    fi
}

# Function to install test suite
install_test_suite() {
    # portable in-place argument for both GNU sed and Mac OSX sed
    if [[ $(uname -s) == 'Darwin' ]]; then
        local ioption='-i .bak'
    else
        local ioption='-i'
    fi

    # set up testing suite if it doesn't yet exist
    if [ ! -d $WP_TESTS_DIR ]; then
        # set up testing suite
        mkdir -p $WP_TESTS_DIR
        svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
        svn co --quiet https://develop.svn.wordpress.org/${WP_TESTS_TAG}/tests/phpunit/data/ $WP_TESTS_DIR/data
    fi

    if [ ! -f wp-tests-config.php ]; then
        download https://develop.svn.wordpress.org/${WP_TESTS_TAG}/wp-tests-config-sample.php "$WP_TESTS_DIR"/wp-tests-config.php
        # remove all forward slashes in the end
        WP_CORE_DIR=$(echo $WP_CORE_DIR | sed "s:/\+$::")
        sed $ioption "s:dirname( __FILE__ ) . '/src/':'$WP_CORE_DIR/':" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s/youremptytestdbnamehere/$DB_NAME/" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s/yourusernamehere/$DB_USER/" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s/yourpasswordhere/$DB_PASS/" "$WP_TESTS_DIR"/wp-tests-config.php
        sed $ioption "s|localhost|${DB_HOST}|" "$WP_TESTS_DIR"/wp-tests-config.php
    fi
}

create_wp_tests_config() {
    # Check if the file already exists
    if [ -f "$WP_TESTS_DIR/wp-tests-config.php" ]; then
        echo "wp-tests-config.php already exists."
        return
    fi

    echo "Creating wp-tests-config.php..."
    cat > "$WP_TESTS_DIR/wp-tests-config.php" << EOF
<?php

/* Path to the WordPress codebase you'd like to test. Add a forward slash in the end. */
define( 'ABSPATH', '$WP_CORE_DIR/' );

define( 'WP_DEFAULT_THEME', 'default' );

// Test with WordPress debug mode (default).
define( 'WP_DEBUG', true );

// ** Database settings ** //
define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASS' );
define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8' );
define( 'DB_COLLATE', '' );

\$table_prefix = 'wptests_';

define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );

define( 'WP_PHP_BINARY', 'php' );

define( 'WPLANG', '' );
EOF
    echo "wp-tests-config.php created successfully."
}

# Main execution
echo "Starting WordPress test environment setup..."

# Reset database and user
reset_db_and_user

# Install WordPress
install_wp

# Install test suite
install_test_suite

echo "WordPress test environment setup completed successfully."

