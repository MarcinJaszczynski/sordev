-- Jednorazowa konfiguracja MySQL na hoście (port 3306).
-- Uruchom: sudo mysql < scripts/mysql-bootstrap.sql

CREATE DATABASE IF NOT EXISTS `host378742_sor26` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'sor'@'localhost' IDENTIFIED BY 'sor_secret';
CREATE USER IF NOT EXISTS 'sor'@'127.0.0.1' IDENTIFIED BY 'sor_secret';

GRANT ALL PRIVILEGES ON `host378742_sor26`.* TO 'sor'@'localhost';
GRANT ALL PRIVILEGES ON `host378742_sor26`.* TO 'sor'@'127.0.0.1';

FLUSH PRIVILEGES;
