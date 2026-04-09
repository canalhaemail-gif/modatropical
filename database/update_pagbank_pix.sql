ALTER TABLE pedidos
    ADD COLUMN IF NOT EXISTS payment_provider VARCHAR(32) NULL AFTER payment_method,
    ADD COLUMN IF NOT EXISTS payment_status VARCHAR(32) NOT NULL DEFAULT 'none' AFTER payment_provider,
    ADD COLUMN IF NOT EXISTS payment_external_order_id VARCHAR(80) NULL AFTER payment_status,
    ADD COLUMN IF NOT EXISTS payment_external_charge_id VARCHAR(80) NULL AFTER payment_external_order_id,
    ADD COLUMN IF NOT EXISTS payment_external_qr_id VARCHAR(80) NULL AFTER payment_external_charge_id,
    ADD COLUMN IF NOT EXISTS payment_pix_text TEXT NULL AFTER payment_external_qr_id,
    ADD COLUMN IF NOT EXISTS payment_pix_image_base64 LONGTEXT NULL AFTER payment_pix_text,
    ADD COLUMN IF NOT EXISTS payment_payload LONGTEXT NULL AFTER payment_pix_image_base64,
    ADD COLUMN IF NOT EXISTS payment_paid_at DATETIME NULL AFTER payment_payload,
    ADD COLUMN IF NOT EXISTS payment_last_webhook_at DATETIME NULL AFTER payment_paid_at;
