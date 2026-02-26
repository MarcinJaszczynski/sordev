#!/usr/bin/env bash
set -euo pipefail

TARGET="$1"
SOURCE="$2"
REPORT_DIR="$(dirname "$TARGET")/merge_reports"
mkdir -p "$REPORT_DIR"
REPORT="$REPORT_DIR/sample_new_columns_$(date +%Y%m%d_%H%M%S).txt"

if [ ! -f "$TARGET" ] || [ ! -f "$SOURCE" ]; then
  echo "Usage: $0 TARGET_DB SOURCE_DB" >&2
  exit 2
fi

echo "Sample new columns report" > "$REPORT"
echo "Timestamp: $(date +%Y-%m-%d_%H:%M:%S)" >> "$REPORT"
echo "Target: $TARGET" >> "$REPORT"
echo "Source: $SOURCE" >> "$REPORT"
echo "" >> "$REPORT"

# get tables present in both
sqlite3 "$TARGET" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;" > /tmp/tgt_tables.$$ 
sqlite3 "$SOURCE" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;" > /tmp/src_tables.$$ 

# iterate tables present in target
while IFS= read -r tbl; do
  [ -z "$tbl" ] && continue
  # get sorted columns
  sqlite3 "$TARGET" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}' | sort > /tmp/tgt_cols.$$.tmp
  sqlite3 "$SOURCE" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}' | sort > /tmp/src_cols.$$.tmp || true

  # find columns present in target but not in source
  added=$(comm -23 /tmp/tgt_cols.$$.tmp /tmp/src_cols.$$.tmp | tr '\n' ',' | sed 's/,$//')
  if [ -n "$added" ]; then
    echo "Table: $tbl" >> "$REPORT"
    echo "New columns in TARGET (missing in SOURCE): $added" >> "$REPORT"
    IFS=',' read -ra cols <<< "$added"
    for col in "${cols[@]}"; do
      echo "  Column: $col" >> "$REPORT"
      # check if source has same column (shouldn't, but user asked to sample from source if exists)
      src_has=$(grep -Fx "$col" /tmp/src_cols.$$.tmp || true)
      if [ -n "$src_has" ]; then
        echo "    Source samples (from $SOURCE.$tbl):" >> "$REPORT"
        sqlite3 "$SOURCE" "SELECT DISTINCT \"$col\" FROM \"$tbl\" WHERE \"$col\" IS NOT NULL AND \"$col\" <> '' LIMIT 10;" >> "$REPORT" || echo "    (query failed)" >> "$REPORT"
      else
        echo "    Source: column not present in source table" >> "$REPORT"
      fi
      # also show sample from target for reference
      echo "    Target samples (from $TARGET.$tbl):" >> "$REPORT"
      sqlite3 "$TARGET" "SELECT DISTINCT \"$col\" FROM \"$tbl\" WHERE \"$col\" IS NOT NULL AND \"$col\" <> '' LIMIT 10;" >> "$REPORT" || echo "    (query failed)" >> "$REPORT"
      echo "" >> "$REPORT"
    done
    echo "" >> "$REPORT"
  fi
done < /tmp/tgt_tables.$$

# cleanup
rm -f /tmp/tgt_tables.$$ /tmp/src_tables.$$ /tmp/tgt_cols.$$.tmp /tmp/src_cols.$$.tmp

echo "Report: $REPORT"
cat "$REPORT"

exit 0
