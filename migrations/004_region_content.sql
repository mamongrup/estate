ALTER TABLE regions ADD COLUMN IF NOT EXISTS content_title varchar(220);
ALTER TABLE regions ADD COLUMN IF NOT EXISTS description text;
ALTER TABLE regions ADD COLUMN IF NOT EXISTS gallery jsonb NOT NULL DEFAULT '[]'::jsonb;
ALTER TABLE regions ADD COLUMN IF NOT EXISTS video_url text;
ALTER TABLE regions ADD COLUMN IF NOT EXISTS attractions jsonb NOT NULL DEFAULT '[]'::jsonb;
