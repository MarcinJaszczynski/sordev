#!/usr/bin/env bash
set -euo pipefail

# Migration Verification Script
# Run this after full_migration.sh to verify the results

if [ "$#" -ne 1 ]; then
  echo "Usage: $0 TARGET_DB"
  echo "Example: $0 database/database.sqlite"
  exit 2
fi

TARGET="$1"

if [ ! -f "$TARGET" ]; then
  echo "❌ Target DB not found: $TARGET"
  exit 3
fi

echo "🔍 Verifying Migration Results"
echo "🎯 Target: $TARGET"
echo ""

echo "📊 Table Counts:"
sqlite3 "$TARGET" "SELECT 'blog_posts: ' || COUNT(*) FROM blog_posts;"
sqlite3 "$TARGET" "SELECT 'contractors: ' || COUNT(*) FROM contractors;"
sqlite3 "$TARGET" "SELECT 'events: ' || COUNT(*) FROM events;"
sqlite3 "$TARGET" "SELECT 'event_program_points: ' || COUNT(*) FROM event_program_points;"
sqlite3 "$TARGET" "SELECT 'users: ' || COUNT(*) FROM users;"
echo ""

echo "✅ Default Values Check:"
echo "Blog posts with is_published=1:"
sqlite3 "$TARGET" "SELECT COUNT(*) FROM blog_posts WHERE is_published = '1';"
echo "Contractors with country='Poland':"
sqlite3 "$TARGET" "SELECT COUNT(*) FROM contractors WHERE country = 'Poland';"
echo "Event program points with currency_id=1:"
sqlite3 "$TARGET" "SELECT COUNT(*) FROM event_program_points WHERE currency_id = '1';"
echo ""

echo "📁 Recent Backups:"
ls -la "$(dirname "$TARGET")"/*backup* 2>/dev/null || echo "No backups found"
echo ""

echo "📊 Recent Reports:"
ls -la "$(dirname "$TARGET")/migration_reports/* 2>/dev/null || echo "No reports found"
echo ""

echo "🎉 Verification completed!"
