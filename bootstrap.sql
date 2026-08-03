-- Ejecutar una sola vez con un usuario con privilegios (root) para crear
-- la base de datos de test que usa phpunit.xml (DB_CONNECTION=mysql).

CREATE DATABASE IF NOT EXISTS synapse_testing
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON synapse_testing.* TO 'synapse';
FLUSH PRIVILEGES;
