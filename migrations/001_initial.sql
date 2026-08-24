CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS unaccent;

CREATE TABLE regions (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name varchar(120) NOT NULL,
  province varchar(120) NOT NULL,
  slug varchar(140) NOT NULL UNIQUE,
  cover_image text,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE TYPE listing_status AS ENUM ('draft','published','archived');
CREATE TYPE contract_kind AS ENUM ('dated','unlimited');
CREATE TABLE listings (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  region_id bigint NOT NULL REFERENCES regions(id),
  title_tr varchar(220) NOT NULL,
  description_tr text NOT NULL,
  property_type varchar(50) NOT NULL,
  sale_type varchar(30) NOT NULL,
  price_try numeric(15,2) NOT NULL CHECK (price_try >= 0),
  rooms varchar(20), bathrooms smallint, gross_area numeric(10,2),
  cover_image text, featured boolean NOT NULL DEFAULT false,
  status listing_status NOT NULL DEFAULT 'draft',
  contract_type contract_kind NOT NULL DEFAULT 'dated',
  contract_start date, contract_end date,
  created_at timestamptz NOT NULL DEFAULT now(), updated_at timestamptz NOT NULL DEFAULT now(),
  CONSTRAINT valid_contract CHECK (contract_type='unlimited' OR (contract_start IS NOT NULL AND contract_end IS NOT NULL AND contract_end >= contract_start))
);

CREATE TABLE listing_translations (
  listing_id bigint NOT NULL REFERENCES listings(id) ON DELETE CASCADE,
  language char(2) NOT NULL CHECK (language IN ('en','de','ru','ar','fr')),
  title varchar(220) NOT NULL, description text NOT NULL,
  seo_title varchar(220), seo_description varchar(320),
  ai_model varchar(80), reviewed_at timestamptz,
  PRIMARY KEY (listing_id, language)
);

CREATE TABLE exchange_rates (currency char(3) PRIMARY KEY, rate numeric(18,8) NOT NULL, updated_at timestamptz NOT NULL DEFAULT now());
CREATE TABLE site_settings (key varchar(100) PRIMARY KEY, value jsonb NOT NULL, updated_at timestamptz NOT NULL DEFAULT now());
CREATE INDEX listings_region_idx ON listings(region_id);
CREATE INDEX listings_search_idx ON listings USING gin (title_tr gin_trgm_ops);
CREATE VIEW published_listings AS SELECT l.*, r.name AS region_name, r.slug AS region_slug FROM listings l JOIN regions r ON r.id=l.region_id WHERE l.status='published' AND (l.contract_type='unlimited' OR l.contract_end >= CURRENT_DATE);
