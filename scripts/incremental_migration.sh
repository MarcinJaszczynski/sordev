#!/usr/bin/env bash
set -euo pipefail

# Incremental Migration Script
# Adds only new data from source to target database

if [ "$#" -ne 2 ]; then
  echo "Usage: $0 TARGET_DB SOURCE_DB"
  echo "Example: $0 database/database.sqlite database/database_serv_2.sqlite"
  exit 2
fi

TARGET="$1"
SOURCE="$2"

if [ ! -f "$TARGET" ]; then
  echo "❌ Target DB not found: $TARGET"
  exit 3
fi
if [ ! -f "$SOURCE" ]; then
  echo "❌ Source DB not found: $SOURCE"
  exit 4
fi

TIMESTAMP=$(date +%Y%m%d_%H%M%S)
REPORT_DIR="$(dirname "$TARGET")/merge_reports"
mkdir -p "$REPORT_DIR"
REPORT="$REPORT_DIR/incremental_migration_$TIMESTAMP.txt"

echo "🔄 Starting Incremental Migration"
echo "📅 Timestamp: $TIMESTAMP"
echo "🎯 Target: $TARGET"
echo "📥 Source: $SOURCE"
echo "📊 Report: $REPORT"
echo ""

# Create backup
echo "📦 Creating backup..."
BACKUP="$TARGET.incremental_backup_$TIMESTAMP"
cp -p "$TARGET" "$BACKUP"
echo "✅ Backup created: $BACKUP"

# Initialize report
{
echo "=== INCREMENTAL MIGRATION REPORT ==="
echo "Timestamp: $TIMESTAMP"
echo "Target: $TARGET"
echo "Source: $SOURCE"
echo "Backup: $BACKUP"
echo ""
} > "$REPORT"

# Function to log and execute
execute_sql() {
  local desc="$1"
  local sql="$2"
  echo "🔄 $desc..."
  echo "🔄 $desc" >> "$REPORT"
  local result
  result=$(sqlite3 "$TARGET" "$sql" 2>&1) || {
    echo "❌ Error in: $desc" >&2
    echo "❌ Error in: $desc" >> "$REPORT"
    echo "$result" >> "$REPORT"
    return 1
  }
  if [ -n "$result" ]; then
    echo "$result" >> "$REPORT"
  fi
  echo "✅ $desc completed"
}

# Function to escape column names for SQL
escape_columns() {
  local cols="$1"
  # Split by comma, escape each column name, join back
  echo "$cols" | tr ',' '\n' | while IFS= read -r col; do
    if [ -n "$col" ]; then
      echo "\"$col\""
    fi
  done | tr '\n' ',' | sed 's/,$//'
}

# Get tables from source
echo "📊 Analyzing tables..."
TABLES=$(sqlite3 "$SOURCE" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;")

{
echo ""
echo "=== INCREMENTAL DATA MIGRATION ==="
} >> "$REPORT"

total_inserted=0

printf '%s\n' "$TABLES" | while IFS= read -r tbl || [ -n "$tbl" ]; do
  # Check if table exists in target
  tgt_exists=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='$tbl';")
  if [ "$tgt_exists" -eq 0 ]; then
    echo "⏭️  Skipping $tbl (not in target)"
    echo "$tbl: SKIPPED (table not in target)" >> "$REPORT"
    continue
  fi

  # Check column compatibility
  src_cols=$(sqlite3 "$SOURCE" "PRAGMA table_info('$tbl');" | wc -l)
  tgt_cols=$(sqlite3 "$TARGET" "PRAGMA table_info('$tbl');" | wc -l)

  before=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")

  if [ "$src_cols" -ne "$tgt_cols" ]; then
    echo "⚠️  $tbl: Column count mismatch ($src_cols vs $tgt_cols), using common columns"
    # Find common columns
    src_col_list=$(sqlite3 "$SOURCE" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}' | sort)
    tgt_col_list=$(sqlite3 "$TARGET" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}' | sort)
    common_cols=$(comm -12 <(echo "$src_col_list") <(echo "$tgt_col_list") | tr '\n' ',' | sed 's/,$//')

    if [ -z "$common_cols" ]; then
      echo "❌ $tbl: No common columns, skipping"
      echo "$tbl: SKIPPED (no common columns)" >> "$REPORT"
      continue
    fi

    # Escape column names for SQL
    escaped_cols=$(escape_columns "$common_cols")

    # Insert only new records using common columns
    sqlite3 "$TARGET" <<EOF
ATTACH '$SOURCE' AS src;
PRAGMA foreign_keys=OFF;
INSERT OR IGNORE INTO "$tbl" ($escaped_cols) SELECT $escaped_cols FROM src."$tbl";
DETACH src;
EOF
  else
    # Insert only new records using all columns
    sqlite3 "$TARGET" <<EOF
ATTACH '$SOURCE' AS src;
PRAGMA foreign_keys=OFF;
INSERT OR IGNORE INTO "$tbl" SELECT * FROM src."$tbl";
DETACH src;
EOF
  fi

  after=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")
  inserted=$((after - before))

  if [ "$inserted" -gt 0 ]; then
    echo "✅ $tbl: Added $inserted new rows"
    echo "$tbl: $inserted new rows added" >> "$REPORT"
    total_inserted=$((total_inserted + inserted))
  else
    echo "⏭️  $tbl: No new rows to add"
    echo "$tbl: 0 new rows (no changes)" >> "$REPORT"
  fi
done

# Final summary
{
echo ""
echo "=== SUMMARY ==="
echo "Total new rows added: $total_inserted"
echo "Backup location: $BACKUP"
echo "Report location: $REPORT"
echo ""
echo "=== INCREMENTAL MIGRATION COMPLETED ==="
} >> "$REPORT"

echo ""
echo "🎉 Incremental migration completed!"
echo "📦 Backup: $BACKUP"
echo "📊 Report: $REPORT"
echo "➕ Total new rows added: $total_inserted"
echo ""
echo "To verify the migration, check the report file above."
