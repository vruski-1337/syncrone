#!/usr/bin/env bash
set -euo pipefail



if [[ "${EUID}" -ne 0 ]]; then
  echo "Run as root: sudo bash $0"
  exit 1
fi

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_SOURCE_DEFAULT="$(cd "${SCRIPT_DIR}/.." && pwd)"

APP_SOURCE="${APP_SOURCE:-${APP_SOURCE_DEFAULT}}"
WEB_ROOT="${WEB_ROOT:-/var/www/html}"
APP_LINK_NAME="${APP_LINK_NAME:-pharma-care}"
APP_LINK_PATH="${WEB_ROOT}/${APP_LINK_NAME}"

DB_HOST="${DB_HOST:-127.0.0.1}"
DB_PORT="${DB_PORT:-3306}"
DB_NAME="${DB_NAME:-pharma_care}"
DB_USER="${DB_USER:-pharma_user}"
DB_PASS="${DB_PASS:-ChangeMe_$(date +%s)}"

INSTALL_REDIS="${INSTALL_REDIS:-true}"
PHP_VERSION="${PHP_VERSION:-}"

log() {
  echo "[setup] $*"
}

require_file() {
  local file_path="$1"
  if [[ ! -f "${file_path}" ]]; then
    echo "Required file not found: ${file_path}"
    exit 1
  fi
}

install_packages() {
  export DEBIAN_FRONTEND=noninteractive
  log "Updating apt indexes..."
  apt-get update -y

  local php_pkg="php"
  local php_mysql_pkg="php-mysql"
  local php_xml_pkg="php-xml"
  local php_mbstring_pkg="php-mbstring"
  local php_curl_pkg="php-curl"
  local php_zip_pkg="php-zip"

  if [[ -n "${PHP_VERSION}" ]]; then
    php_pkg="php${PHP_VERSION}"
    php_mysql_pkg="php${PHP_VERSION}-mysql"
    php_xml_pkg="php${PHP_VERSION}-xml"
    php_mbstring_pkg="php${PHP_VERSION}-mbstring"
    php_curl_pkg="php${PHP_VERSION}-curl"
    php_zip_pkg="php${PHP_VERSION}-zip"
  fi

  log "Installing Apache, MariaDB, PHP, and tools..."
  apt-get install -y \
    apache2 \
    mariadb-server \
    "${php_pkg}" \
    libapache2-mod-php \
    "${php_mysql_pkg}" \
    "${php_xml_pkg}" \
    "${php_mbstring_pkg}" \
    "${php_curl_pkg}" \
    "${php_zip_pkg}" \
    unzip

  if [[ "${INSTALL_REDIS}" == "true" ]]; then
    log "Installing Redis packages..."
    apt-get install -y redis-server php-redis
  fi
}

configure_services() {
  log "Enabling and starting MariaDB and Apache..."
  systemctl enable --now mariadb || systemctl enable --now mysql
  systemctl enable --now apache2

  if [[ "${INSTALL_REDIS}" == "true" ]]; then
    systemctl enable --now redis-server
  fi

  a2enmod rewrite >/dev/null
}

link_application() {
  log "Preparing web root link at ${APP_LINK_PATH}..."
  mkdir -p "${WEB_ROOT}"

  if [[ -e "${APP_LINK_PATH}" && ! -L "${APP_LINK_PATH}" ]]; then
    local backup="${APP_LINK_PATH}.bak.$(date +%Y%m%d%H%M%S)"
    log "Existing path is not symlink. Backing up to ${backup}"
    mv "${APP_LINK_PATH}" "${backup}"
  fi

  ln -sfn "${APP_SOURCE}" "${APP_LINK_PATH}"

  mkdir -p "${APP_SOURCE}/uploads"
  chown -R www-data:www-data "${APP_SOURCE}/uploads"
  chmod -R 775 "${APP_SOURCE}/uploads"
}

configure_database() {
  log "Configuring MariaDB database and app user..."

  mysql --protocol=socket -u root <<SQL
CREATE DATABASE IF NOT EXISTS ${DB_NAME}
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS '${DB_USER}'@'${DB_HOST}' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON ${DB_NAME}.* TO '${DB_USER}'@'${DB_HOST}';
FLUSH PRIVILEGES;
SQL

  local schema_file="${APP_SOURCE}/sql/pharma_care.sql"
  require_file "${schema_file}"

  local users_table_count
  users_table_count=$(mysql -N -s --protocol=socket -u root -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='${DB_NAME}' AND table_name='users';")

  if [[ "${users_table_count}" == "0" ]]; then
    log "Importing initial schema from ${schema_file}..."
    mysql -u root "${DB_NAME}" < "${schema_file}"
  else
    log "Schema appears initialized; skipping SQL import."
  fi
}

write_apache_env() {
  local env_conf="/etc/apache2/conf-available/${APP_LINK_NAME}-env.conf"
  log "Writing Apache env config: ${env_conf}"

  cat > "${env_conf}" <<EOF
# Syncrone runtime env
SetEnv DB_HOST ${DB_HOST}
SetEnv DB_PORT ${DB_PORT}
SetEnv DB_NAME ${DB_NAME}
SetEnv DB_USER ${DB_USER}
SetEnv DB_PASS ${DB_PASS}
EOF

  a2enconf "${APP_LINK_NAME}-env" >/dev/null
}

main() {
  log "App source: ${APP_SOURCE}"
  require_file "${APP_SOURCE}/index.php"

  install_packages
  configure_services
  configure_database
  link_application
  write_apache_env

  systemctl restart apache2

  if [[ "${INSTALL_REDIS}" == "true" ]]; then
    systemctl restart redis-server
  fi

  cat <<SUMMARY

Setup complete.

Application URL:
- http://<server-ip>/${APP_LINK_NAME}

Database credentials configured for Apache env:
- DB_HOST=${DB_HOST}
- DB_PORT=${DB_PORT}
- DB_NAME=${DB_NAME}
- DB_USER=${DB_USER}
- DB_PASS=${DB_PASS}

Next steps:
1. Open the URL above in browser.
2. Optionally rotate DB_PASS and update Apache env config.
3. If using UFW: allow HTTP with 'ufw allow 80/tcp'.
SUMMARY
}

main "$@"
