-- Se ejecuta automáticamente como parte de /docker-entrypoint-initdb.d al
-- inicializar el volumen de synapse-mysql por primera vez (ver compose.yml),
-- para crear la base de datos de test que usa phpunit.xml (DB_CONNECTION=mysql).
-- Si el volumen ya existe, este script no se re-ejecuta solo — hay que
-- correrlo a mano una vez, o recrear el volumen (`docker compose down -v`).

CREATE DATABASE IF NOT EXISTS synapse_testing
  DEFAULT CHARACTER SET utf8mb4
  DEFAULT COLLATE utf8mb4_unicode_ci;

GRANT ALL PRIVILEGES ON synapse_testing.* TO 'synapse';
FLUSH PRIVILEGES;
