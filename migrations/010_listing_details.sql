ALTER TABLE listings ADD COLUMN IF NOT EXISTS net_area numeric(10,2);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS open_area numeric(10,2);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS province varchar(120);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS district varchar(120);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS neighborhood varchar(160);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS details jsonb NOT NULL DEFAULT '{}'::jsonb;
CREATE INDEX IF NOT EXISTS listings_details_gin_idx ON listings USING gin(details);
