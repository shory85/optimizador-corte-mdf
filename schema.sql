-- Ejecuta esto en phpMyAdmin (dentro de tu base de datos) para crear la tabla.
CREATE TABLE IF NOT EXISTS modelos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nombre VARCHAR(190) NOT NULL UNIQUE,
  items_json LONGTEXT NOT NULL,
  maquinado_json LONGTEXT NULL,
  actualizado_en DATETIME NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Si ya tenías la tabla creada de antes (sin la columna de maquinado),
-- ejecuta esta línea para agregarla sin perder tus modelos guardados:
-- ALTER TABLE modelos ADD COLUMN maquinado_json LONGTEXT NULL AFTER items_json;

-- Tabla genérica para Órdenes de producción, Bitácora de Enchapado,
-- Configuración de Bloques y Carga de fotografías (comparten esta misma
-- tabla, diferenciadas por la columna "tipo").
CREATE TABLE IF NOT EXISTS app_data (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL,
  clave VARCHAR(190) NOT NULL,
  valor_json LONGTEXT NOT NULL,
  actualizado_en DATETIME NOT NULL,
  UNIQUE KEY tipo_clave (tipo, clave)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
