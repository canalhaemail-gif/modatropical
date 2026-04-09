USE cardapio_digital;

SET @has_stock_column := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'estoque'
);

SET @add_stock_column_sql := IF(
    @has_stock_column = 0,
    'ALTER TABLE produtos ADD COLUMN estoque INT UNSIGNED NOT NULL DEFAULT 0 AFTER preco',
    'SELECT 1'
);
PREPARE add_stock_column_stmt FROM @add_stock_column_sql;
EXECUTE add_stock_column_stmt;
DEALLOCATE PREPARE add_stock_column_stmt;

SET @has_flavors_column := (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'produtos'
      AND COLUMN_NAME = 'sabores'
);

SET @add_flavors_column_sql := IF(
    @has_flavors_column = 0,
    'ALTER TABLE produtos ADD COLUMN sabores TEXT NULL AFTER estoque',
    'SELECT 1'
);
PREPARE add_flavors_column_stmt FROM @add_flavors_column_sql;
EXECUTE add_flavors_column_stmt;
DEALLOCATE PREPARE add_flavors_column_stmt;
