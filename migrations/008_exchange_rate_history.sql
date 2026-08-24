CREATE TABLE IF NOT EXISTS exchange_rate_history (
  id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,
  currency char(3) NOT NULL,
  rate numeric(18,8) NOT NULL,
  provider varchar(80) NOT NULL,
  provider_updated_at timestamptz,
  fetched_at timestamptz NOT NULL DEFAULT now()
);
CREATE INDEX IF NOT EXISTS exchange_rate_history_currency_idx ON exchange_rate_history(currency, fetched_at DESC);
