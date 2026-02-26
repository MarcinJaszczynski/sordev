#!/usr/bin/env bash
set -euo pipefail

# Complete SQLite Migration Script
# This script performs a full migration from source database to target database
# with proper backups, data transfer, and default value assignments

if [ "$#" -ne 2 ]; then
  echo "Usage: $0 TARGET_DB SOURCE_DB"
  echo "Example: $0 database/database.sqlite database/database_serv.sqlite"
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
REPORT_DIR="$(dirname "$TARGET")/migration_reports"
mkdir -p "$REPORT_DIR"
REPORT="$REPORT_DIR/full_migration_$TIMESTAMP.txt"

echo "🚀 Starting Complete SQLite Migration"
echo "📅 Timestamp: $TIMESTAMP"
echo "🎯 Target: $TARGET"
echo "📥 Source: $SOURCE"
echo "📊 Report: $REPORT"
echo ""

# Create backup
echo "📦 Creating backup..."
BACKUP="$TARGET.backup_$TIMESTAMP"
cp -p "$TARGET" "$BACKUP"
echo "✅ Backup created: $BACKUP"

# Initialize report
{
echo "=== COMPLETE MIGRATION REPORT ==="
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

# Step 1: Migrate data from matching tables
echo "📊 Step 1: Migrating data from matching tables..."
{
echo ""
echo "=== STEP 1: DATA MIGRATION ==="
} >> "$REPORT"

TABLES=$(sqlite3 "$SOURCE" "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%' ORDER BY name;")

printf '%s\n' "$TABLES" | while IFS= read -r tbl || [ -n "$tbl" ]; do
  # Check if table exists in target
  tgt_exists=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='$tbl';")
  if [ "$tgt_exists" -eq 0 ]; then
    echo "⏭️  Skipping $tbl (not in target)"
    continue
  fi

  # Check column compatibility
  src_cols=$(sqlite3 "$SOURCE" "PRAGMA table_info('$tbl');" | wc -l)
  tgt_cols=$(sqlite3 "$TARGET" "PRAGMA table_info('$tbl');" | wc -l)

  if [ "$src_cols" -ne "$tgt_cols" ]; then
    echo "⚠️  $tbl: Column count mismatch ($src_cols vs $tgt_cols), using common columns"
    # Find common columns
    src_col_list=$(sqlite3 "$SOURCE" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}' | sort)
    tgt_col_list=$(sqlite3 "$TARGET" "PRAGMA table_info('$tbl');" | awk -F'|' '{print $2}' | sort)
    common_cols=$(comm -12 <(echo "$src_col_list") <(echo "$tgt_col_list") | tr '\n' ',' | sed 's/,$//')

    if [ -z "$common_cols" ]; then
      echo "❌ $tbl: No common columns, skipping"
      continue
    fi

    before=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")
    sqlite3 "$TARGET" <<EOF
ATTACH '$SOURCE' AS src;
PRAGMA foreign_keys=OFF;
INSERT OR IGNORE INTO "$tbl" ($common_cols) SELECT $common_cols FROM src."$tbl";
DETACH src;
EOF
    after=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")
    inserted=$((after - before))
    echo "✅ $tbl: Inserted $inserted rows using common columns"
    echo "$tbl: $inserted rows inserted (common columns)" >> "$REPORT"
  else
    before=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")
    sqlite3 "$TARGET" <<EOF
ATTACH '$SOURCE' AS src;
PRAGMA foreign_keys=OFF;
INSERT OR IGNORE INTO "$tbl" SELECT * FROM src."$tbl";
DETACH src;
EOF
    after=$(sqlite3 "$TARGET" "SELECT COUNT(*) FROM \"$tbl\";")
    inserted=$((after - before))
    echo "✅ $tbl: Inserted $inserted rows"
    echo "$tbl: $inserted rows inserted" >> "$REPORT"
  fi
done

# Step 2: Apply default values for new columns
echo "🔧 Step 2: Applying default values for new columns..."
{
echo ""
echo "=== STEP 2: DEFAULT VALUES ==="
} >> "$REPORT"

# Blog posts defaults
execute_sql "Setting blog_posts.is_published = 1" "UPDATE blog_posts SET is_published = '1' WHERE is_published IS NULL OR is_published = '';"

# Contractors defaults
execute_sql "Setting contractors.country = 'Poland'" "UPDATE contractors SET country = 'Poland' WHERE country IS NULL OR country = '';"

# Event program points defaults
execute_sql "Setting event_program_points defaults" "UPDATE event_program_points SET currency_id = '1', convert_to_pln = '0', show_title_style = '1', show_description = '1', duration_hours = '0', duration_minutes = '0' WHERE currency_id IS NULL OR currency_id = '';"

# Step 3: Final summary
echo "📈 Step 3: Generating final summary..."
{
echo ""
echo "=== STEP 3: FINAL SUMMARY ==="
echo "Migration completed successfully!"
echo ""
echo "Key table counts:"
} >> "$REPORT"

# Get final counts
sqlite3 "$TARGET" "SELECT 'blog_posts: ' || COUNT(*) FROM blog_posts;" >> "$REPORT"
sqlite3 "$TARGET" "SELECT 'contractors: ' || COUNT(*) FROM contractors;" >> "$REPORT"
sqlite3 "$TARGET" "SELECT 'events: ' || COUNT(*) FROM events;" >> "$REPORT"
sqlite3 "$TARGET" "SELECT 'event_program_points: ' || COUNT(*) FROM event_program_points;" >> "$REPORT"
sqlite3 "$TARGET" "SELECT 'users: ' || COUNT(*) FROM users;" >> "$REPORT"

{
echo ""
echo "Backup location: $BACKUP"
echo "Report location: $REPORT"
echo ""
echo "=== MIGRATION COMPLETED SUCCESSFULLY ==="
} >> "$REPORT"

echo ""
echo "🎉 Migration completed successfully!"
echo "📦 Backup: $BACKUP"
echo "📊 Report: $REPORT"
echo ""
echo "To verify the migration, check the report file above."
