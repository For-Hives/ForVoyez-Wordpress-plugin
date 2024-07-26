#!/usr/bin/env bash

# Load environment variables from .env.testing file
if [ -f ".env.testing" ]; then
    export $(grep -v '^#' .env.testing | xargs)
fi

# Use environment variables, fallback to arguments if not set
DB_NAME=${WP_TESTS_DB_NAME:-$1}
DB_USER=${WP_TESTS_DB_USER:-$2}
DB_PASS=${WP_TESTS_DB_PASSWORD:-$3}
DB_HOST=${WP_TESTS_DB_HOST:-$4}
WP_VERSION=${5-latest}
SKIP_DB_CREATE=${6-false}

WP_TESTS_DIR=${WP_TESTS_DIR-/tmp/wordpress-tests-lib}
WP_CORE_DIR=${WP_CORE_DIR-/tmp/wordpress/}

# Check if required variables are set
if [ -z "$DB_NAME" ] || [ -z "$DB_USER" ] || [ -z "$DB_PASS" ] || [ -z "$DB_HOST" ]; then
    echo "Error: Database configuration is incomplete. Please check your .env.testing file."
    exit 1
fi

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

# Function to download a file
download() {
    if [ `which curl` ]; then
        curl -s "$1" > "$2";
    elif [ `which wget` ]; then
        wget -nv -O "$2" "$1"
    fi
}

# Function to install WordPress
install_wp() {
    if [ -d $WP_CORE_DIR ]; then
        return;
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
        svn co --quiet https://develop.svn.wordpress.org/${WP_VERSION}/tests/phpunit/includes/ $WP_TESTS_DIR/includes
        svn co --quiet https://develop.svn.wordpress.org/${WP_VERSION}/tests/phpunit/data/ $WP_TESTS_DIR/data
    fi

    create_wp_tests_config
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

