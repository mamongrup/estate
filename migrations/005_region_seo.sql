ALTER TABLE regions ADD COLUMN IF NOT EXISTS seo_title varchar(70);
ALTER TABLE regions ADD COLUMN IF NOT EXISTS seo_description varchar(170);
ALTER TABLE regions ADD COLUMN IF NOT EXISTS seo_keywords text;
ALTER TABLE regions ADD COLUMN IF NOT EXISTS canonical_url text;
ALTER TABLE regions ADD COLUMN IF NOT EXISTS is_indexable boolean NOT NULL DEFAULT true;
