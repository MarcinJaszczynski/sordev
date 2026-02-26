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
REPORT_DIR="$(dirname "$TARGET")/merge_reports"
mkdir -p "$REPORT_DIR"
REPORT="$REPORT_DIR/map_mismatched_report_$TIMESTAMP.txt"

cp -p "$TARGET" "$TARGET.mapbackup-$TIMESTAMP"

echo "Map mismatched report" > "$REPORT"
echo "Timestamp: $TIMESTAMP" >> "$REPORT"
echo "Target: $TARGET" >> "$REPORT"
echo "Source: $SOURCE" >> "$REPORT"
echo "" >> "$REPORT"

echo "Starting mapping of mismatched tables..." | tee -a "$REPORT"

# get list of tables present in both DBs (exclude sqlite_*)
TABLES=$(sqlite3 "$SOURCE" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;")

printf '%s\n' "$TABLES" | while IFS= read -r tbl || [ -n "$tbl" ]; do
  # check target has table
  tgt_exists=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='"$tbl"';")
  if [ "$tgt_exists" -eq 0 ]; then
    echo "- $tbl: target missing, skipping" | tee -a "$REPORT"
    continue
  fi

  # get column names (one per line)
  src_cols=$(sqlite3 "$SOURCE" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}')
  tgt_cols=$(sqlite3 "$TARGET" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}')

  # produce intersection (preserve order of source)
  common_cols=""
  common_cols_quoted=""
  while IFS= read -r col; do
    if echo "$tgt_cols" | grep -Fxq "$col"; then
      if [ -z "$common_cols" ]; then
        common_cols="$col"
        common_cols_quoted="\"$col\""
      else
        common_cols+=","$col
        common_cols_quoted+=",\"$col\""
      fi
    fi
  done <<< "$src_cols"

  if [ -z "$common_cols" ]; then
    echo "- $tbl: no common columns, skipped" | tee -a "$REPORT"
    continue
  fi

  # count before
  before=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")

  # build SQL to insert only common columns, quoting column names and prefixing src.
  sqlite3 "$TARGET" <<SQL
ATTACH '$SOURCE' AS src;
PRAGMA foreign_keys=OFF;
BEGIN TRANSACTION;
INSERT OR IGNORE INTO "$tbl" ($common_cols_quoted) SELECT ${common_cols_quoted//"/src."} FROM src."$tbl";
COMMIT;
DETACH src;
SQL

  after=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")
  inserted=$((after - before))
  echo "- $tbl: inserted $inserted rows (common cols: $common_cols)" | tee -a "$REPORT"
done


echo "Mapping finished." | tee -a "$REPORT"

echo "Report: $REPORT" | tee -a "$REPORT"

cat "$REPORT"

exit 0
