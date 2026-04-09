ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS cash_change_for DECIMAL(10,2) NULL AFTER total,
    ADD COLUMN IF NOT EXISTS cash_change_due DECIMAL(10,2) NULL AFTER cash_change_for;
