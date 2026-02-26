#!/usr/bin/env bash
#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 2 ]; then
  echo "Usage: $0 TARGET_DB SOURCE_DB"
  exit 2
fi

TARGET="$1"
SOURCE="$2"

if [ ! -f "$TARGET" ]; then
  echo "Target DB not found: $TARGET" >&2
  exit 3
fi
if [ ! -f "$SOURCE" ]; then
  echo "Source DB not found: $SOURCE" >&2
  exit 4
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
BACKUP="$TARGET.bak-$TIMESTAMP"
REPORT_DIR="$(dirname "$TARGET")/merge_reports"
mkdir -p "$REPORT_DIR"
REPORT="$REPORT_DIR/merge_report_$TIMESTAMP.txt"

cp -p "$TARGET" "$BACKUP"

echo "Merge report" > "$REPORT"
echo "Timestamp: $TIMESTAMP" >> "$REPORT"
echo "Target: $TARGET" >> "$REPORT"
echo "Backup: $BACKUP" >> "$REPORT"
echo "Source: $SOURCE" >> "$REPORT"
echo "" >> "$REPORT"

echo "Starting merge..." | tee -a "$REPORT"

# get table list from source (exclude sqlite internal tables)
TABLES_LIST=$(sqlite3 "$SOURCE" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;")

# count tables
num_tables=0
if [ -n "$TABLES_LIST" ]; then
  num_tables=$(printf '%s\n' "$TABLES_LIST" | wc -l | tr -d ' ')
fi

echo "Found $num_tables tables in source" | tee -a "$REPORT"

echo "" >> "$REPORT"

echo "Processing tables:" | tee -a "$REPORT"

printf '%s\n' "$TABLES_LIST" | while IFS= read -r tbl || [ -n "$tbl" ]; do
  echo "- Table: $tbl" | tee -a "$REPORT"

  src_cols=$(sqlite3 "$SOURCE" "PRAGMA table_info('$tbl');" | wc -l)
  tgt_cols=$(sqlite3 "$TARGET" "PRAGMA table_info('$tbl');" | wc -l)

  if [ "$tgt_cols" -eq 0 ]; then
    echo "  -> Skipped: table does not exist in target" | tee -a "$REPORT"
    # optionally show create statement from source
    echo "  Source CREATE: " >> "$REPORT"
    sqlite3 "$SOURCE" ".schema $tbl" >> "$REPORT"
    echo "" >> "$REPORT"
    continue
  fi

  if [ "$src_cols" -ne "$tgt_cols" ]; then
    echo "  -> Skipped: column count mismatch (source=$src_cols target=$tgt_cols)" | tee -a "$REPORT"
    echo "  Source columns:" >> "$REPORT"
    sqlite3 "$SOURCE" "PRAGMA table_info('$tbl');" >> "$REPORT"
    echo "  Target columns:" >> "$REPORT"
    sqlite3 "$TARGET" "PRAGMA table_info('$tbl');" >> "$REPORT"
    echo "" >> "$REPORT"
    continue
  fi

  # safe insert using ATTACH; use INSERT OR IGNORE to avoid unique constraint failures
  before=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")

  sqlite3 "$TARGET" <<SQL
ATTACH '$SOURCE' AS src;
PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
INSERT OR IGNORE INTO "$tbl" SELECT * FROM src."$tbl";
COMMIT;
DETACH src;
SQL

  after=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")
  inserted=$((after - before))
  echo "  -> Inserted: $inserted rows (before=$before after=$after)" | tee -a "$REPORT"
  echo "" >> "$REPORT"
done

echo "Merge finished." | tee -a "$REPORT"

# summary
total_target_tables=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%';")
echo "" >> "$REPORT"
echo "Summary:" >> "$REPORT"
echo "Total target tables: $total_target_tables" >> "$REPORT"
echo "Backup created at: $BACKUP" >> "$REPORT"
echo "Report: $REPORT" >> "$REPORT"

cat "$REPORT"

exit 0
