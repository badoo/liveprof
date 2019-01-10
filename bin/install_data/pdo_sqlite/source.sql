CREATE TABLE IF NOT EXISTS details (
  id INTEGER PRIMARY KEY AUTOINCREMENT,
  app TEXT DEFAULT NULL,
  label TEXT DEFAULT NULL,
  timestamp TEXT NOT NULL,
  perfdata BLOB
);
CREATE INDEX IF NOT EXISTS app ON details (app);
CREATE INDEX IF NOT EXISTS label ON details (label);
CREATE INDEX IF NOT EXISTS timestamp_label_idx ON details (timestamp,label);
