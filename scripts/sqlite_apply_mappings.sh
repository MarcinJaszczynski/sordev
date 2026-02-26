#!/usr/bin/env bash
set -euo pipefail

if [ "$#" -ne 3 ]; then
    # build SQL: update target set target_col = value_from_source where target empty and source not empty
    if [[ "$src" == lit:* ]]; then
      literal=${src#lit:}
      if [ "$literal" = "NULL" ]; then
        sql="UPDATE \"$table\" SET \"$tgt\" = NULL WHERE (\"$tgt\" IS NULL OR \"$tgt\" = '') AND EXISTS (SELECT 1 FROM \"$table\" WHERE \"$key\" = \"$table\".\"$key\");"
      else
        sql="UPDATE \"$table\" SET \"$tgt\" = '$literal' WHERE (\"$tgt\" IS NULL OR \"$tgt\" = '') AND EXISTS (SELECT 1 FROM \"$table\" WHERE \"$key\" = \"$table\".\"$key\");"
      fi
      before_nonnull=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$table\" WHERE \"$tgt\" IS NOT NULL AND \"$tgt\" <> '';") || true
      sqlite3 "$TARGET" "$sql"
      after_nonnull=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$table\" WHERE \"$tgt\" IS NOT NULL AND \"$tgt\" <> '';") || true
      updated=$((after_nonnull - before_nonnull))
      echo " -> Updated $updated rows for $table.$tgt (literal: $literal)" | tee -a "$REPORT"age: $0 TARGET_DB SOURCE_DB MAPPINGS_CONF"
  exit 2
fi

TARGET="$1"
SOURCE="$2"
CONF="$3"

if [ ! -f "$TARGET" ]; then
  echo "Target DB not found: $TARGET" >&2
  exit 3
fi
if [ ! -f "$SOURCE" ]; then
  echo "Source DB not found: $SOURCE" >&2
  exit 4
fi
if [ ! -f "$CONF" ]; then
  echo "Mappings config not found: $CONF" >&2
  exit 5
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_DIR="$(dirname "$TARGET")/merge_reports"
mkdir -p "$REPORT_DIR"
REPORT="$REPORT_DIR/apply_mappings_report_$TIMESTAMP.txt"

cp -p "$TARGET" "$TARGET.mapbackup2-$TIMESTAMP"

echo "Apply mappings report" > "$REPORT"
echo "Timestamp: $TIMESTAMP" >> "$REPORT"
echo "Target: $TARGET" >> "$REPORT"
echo "Source: $SOURCE" >> "$REPORT"
echo "Config: $CONF" >> "$REPORT"
echo "" >> "$REPORT"

echo "Starting apply mappings..." | tee -a "$REPORT"

# read config
while IFS= read -r line || [ -n "$line" ]; do
  # skip empty/comment
  line=$(echo "$line" | sed -e 's/#.*$//' -e 's/^[[:space:]]*//' -e 's/[[:space:]]*$//')
  [ -z "$line" ] && continue

  # parse: table:key:target=source,...
  IFS=':' read -r table key rest <<< "$line"
  if [ -z "$table" ] || [ -z "$key" ] || [ -z "$rest" ]; then
    echo "Invalid mapping line: $line" | tee -a "$REPORT"
    continue
  fi

  # parse pairs
  IFS=',' read -ra pairs <<< "$rest"
  for pair in "${pairs[@]}"; do
    IFS='=' read -r tgt src <<< "$pair"
    tgt=$(echo "$tgt" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')
    src=$(echo "$src" | sed 's/^[[:space:]]*//;s/[[:space:]]*$//')

    echo "Processing mapping: table=$table key=$key target_col=$tgt source_expr=$src" | tee -a "$REPORT"

    # build SQL: update target set target_col = value_from_source where target empty and source not empty
    if [[ "$src" == lit:* ]]; then
      literal=${src#lit:}
      if [ "$literal" = "NULL" ]; then
        sql="UPDATE \"$table\" SET \"$tgt\" = NULL WHERE (\"$tgt\" IS NULL OR \"$tgt\" = '') AND EXISTS (SELECT 1 FROM \"$table\" WHERE \"$key\" = \"$table\".\"$key\");"
      else
        sql="UPDATE \"$table\" SET \"$tgt\" = '$literal' WHERE (\"$tgt\" IS NULL OR \"$tgt\" = '') AND EXISTS (SELECT 1 FROM \"$table\" WHERE \"$key\" = \"$table\".\"$key\");"
      fi
      before_nonnull=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$table\" WHERE \"$tgt\" IS NOT NULL AND \"$tgt\" <> '';") || true
      sqlite3 "$TARGET" "$sql"
      after_nonnull=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$table\" WHERE \"$tgt\" IS NOT NULL AND \"$tgt\" <> '';") || true
      updated=$((after_nonnull - before_nonnull))
      echo " -> Updated $updated rows for $table.$tgt (literal: $literal)" | tee -a "$REPORT"
    else
      # assume column name; verify it exists in source
      src_col="$src"
      src_has_col=$(sqlite3 "$SOURCE" "PRAGMA table_info('$table');" | awk -F'|' '{print $2}' | grep -Fx -- "$src_col" || true)
      if [ -z "$src_has_col" ]; then
        echo " -> Skipped: source column '$src_col' does not exist in $SOURCE.$table" | tee -a "$REPORT"
        continue
      fi

      # build safe SQL that updates only when target empty and source value present
      sql="ATTACH '$SOURCE' AS src; BEGIN; UPDATE \"$table\" SET \"$tgt\" = (SELECT s.\"$src_col\" FROM src.\"$table\" s WHERE s.\"$key\" = \"$table\".\"$key\" LIMIT 1) WHERE (\"$tgt\" IS NULL OR \"$tgt\" = '') AND EXISTS (SELECT 1 FROM src.\"$table\" s WHERE s.\"$key\" = \"$table\".\"$key\" AND s.\"$src_col\" IS NOT NULL AND s.\"$src_col\" <> ''); COMMIT; DETACH src;"

      before_nonnull=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$table\" WHERE \"$tgt\" IS NOT NULL AND \"$tgt\" <> '';") || true
      sqlite3 "$TARGET" "$sql"
      after_nonnull=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$table\" WHERE \"$tgt\" IS NOT NULL AND \"$tgt\" <> '';") || true
      updated=$((after_nonnull - before_nonnull))
      echo " -> Updated $updated rows for $table.$tgt" | tee -a "$REPORT"
    fi
  done

done < "$CONF"


echo "Apply mappings finished." | tee -a "$REPORT"

echo "Report: $REPORT" | tee -a "$REPORT"

cat "$REPORT"

exit 0
