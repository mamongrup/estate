CREATE TABLE IF NOT EXISTS region_translations (
  region_id bigint NOT NULL REFERENCES regions(id) ON DELETE CASCADE,
  language char(2) NOT NULL CHECK (language IN ('en','de','ru','ar','fr')),
  content_title varchar(220) NOT NULL,
  description text NOT NULL,
  attractions jsonb NOT NULL DEFAULT '[]'::jsonb,
  seo_title varchar(70),
  seo_description varchar(170),
  seo_keywords text,
  ai_model varchar(80),
  translated_at timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY(region_id, language)
);
CREATE INDEX IF NOT EXISTS region_translations_language_idx ON region_translations(language, region_id);
