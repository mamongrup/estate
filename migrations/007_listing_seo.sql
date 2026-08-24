ALTER TABLE listings ADD COLUMN IF NOT EXISTS seo_title_tr varchar(70);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS seo_description_tr varchar(170);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS seo_keywords_tr text;
ALTER TABLE listings ADD COLUMN IF NOT EXISTS slug varchar(240);
ALTER TABLE listings ADD COLUMN IF NOT EXISTS canonical_url text;
CREATE UNIQUE INDEX IF NOT EXISTS listings_slug_unique ON listings(slug) WHERE slug IS NOT NULL;
ALTER TABLE listing_translations ADD COLUMN IF NOT EXISTS seo_keywords text;
ALTER TABLE listing_translations ADD COLUMN IF NOT EXISTS slug varchar(240);
