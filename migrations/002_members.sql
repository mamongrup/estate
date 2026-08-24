CREATE EXTENSION IF NOT EXISTS pgcrypto;
CREATE TYPE user_role AS ENUM ('member','admin');
CREATE TABLE users (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,name varchar(160) NOT NULL,email varchar(254) NOT NULL,phone varchar(30),password_hash text NOT NULL,role user_role NOT NULL DEFAULT 'member',email_verified_at timestamptz,created_at timestamptz NOT NULL DEFAULT now(),updated_at timestamptz NOT NULL DEFAULT now());
CREATE UNIQUE INDEX users_email_unique ON users (lower(email));
CREATE TABLE password_reset_tokens (id bigint GENERATED ALWAYS AS IDENTITY PRIMARY KEY,user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,token_hash text NOT NULL UNIQUE,expires_at timestamptz NOT NULL,used_at timestamptz,created_at timestamptz NOT NULL DEFAULT now());
CREATE INDEX password_reset_user_idx ON password_reset_tokens(user_id,expires_at DESC);
CREATE TABLE user_sessions (id uuid PRIMARY KEY DEFAULT gen_random_uuid(),user_id bigint NOT NULL REFERENCES users(id) ON DELETE CASCADE,token_hash text NOT NULL UNIQUE,expires_at timestamptz NOT NULL,created_at timestamptz NOT NULL DEFAULT now());
CREATE INDEX user_sessions_user_idx ON user_sessions(user_id,expires_at DESC);
