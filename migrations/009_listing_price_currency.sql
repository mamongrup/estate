ALTER TABLE listings ADD COLUMN IF NOT EXISTS original_price numeric(15,2);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS price_currency char(3) NOT NULL DEFAULT 'TRY';
ALTER TABLE listings ADD COLUMN IF NOT EXISTS entry_exchange_rate numeric(18,8) NOT NULL DEFAULT 1;
UPDATE listings SET original_price=price_try,price_currency='TRY',entry_exchange_rate=1 WHERE original_price IS NULL;
ALTER TABLE listings ALTER COLUMN original_price SET NOT NULL;
ALTER TABLE listings ADD CONSTRAINT listings_original_price_positive CHECK (original_price >= 0);
ALTER TABLE listings ADD CONSTRAINT listings_price_currency_valid CHECK (price_currency IN ('TRY','EUR','USD','GBP','RUB','AED'));
