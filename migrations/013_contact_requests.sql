CREATE TABLE IF NOT EXISTS contact_requests (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  name varchar(160) NOT NULL,
  phone varchar(30),
  email varchar(254),
  message text NOT NULL,
  handled boolean NOT NULL DEFAULT false,
  created_at timestamptz NOT NULL DEFAULT now()
);

CREATE INDEX IF NOT EXISTS contact_requests_created_idx ON contact_requests(created_at DESC);
