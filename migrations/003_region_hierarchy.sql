ALTER TABLE regions ADD COLUMN IF NOT EXISTS parent_id bigint REFERENCES regions(id) ON DELETE RESTRICT;
ALTER TABLE regions ADD COLUMN IF NOT EXISTS region_type varchar(20) NOT NULL DEFAULT 'district';
ALTER TABLE regions ADD COLUMN IF NOT EXISTS sort_order integer NOT NULL DEFAULT 0;
CREATE INDEX IF NOT EXISTS regions_parent_idx ON regions(parent_id, sort_order, name);
