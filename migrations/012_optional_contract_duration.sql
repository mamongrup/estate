ALTER TABLE listings ADD COLUMN IF NOT EXISTS contract_duration_months smallint;
ALTER TABLE listings DROP CONSTRAINT IF EXISTS valid_contract;
ALTER TABLE listings ADD CONSTRAINT valid_contract CHECK (
  contract_duration_months IS NULL OR contract_duration_months > 0
);

DROP VIEW IF EXISTS published_listings;

CREATE VIEW published_listings AS
SELECT l.*, r.name AS region_name, r.slug AS region_slug
FROM listings l
JOIN regions r ON r.id = l.region_id
WHERE l.status = 'published'
  AND (l.contract_end IS NULL OR l.contract_end >= CURRENT_DATE);
