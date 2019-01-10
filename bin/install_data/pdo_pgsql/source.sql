CREATE TABLE IF NOT EXISTS details (
  id SERIAL NOT NULL PRIMARY KEY,
  app CHAR(32) DEFAULT NULL,
  label CHAR(64) DEFAULT NULL,
  timestamp timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
  perfdata text
);
CREATE INDEX IF NOT EXISTS timestamp_idx ON details (timestamp);
CREATE INDEX IF NOT EXISTS app_idx ON details (app);
CREATE INDEX IF NOT EXISTS label_idx ON details (label);
CREATE INDEX IF NOT EXISTS timestamp_label_idx ON details (timestamp,label);
