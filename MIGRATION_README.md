# SQLite Database Migration Scripts

This directory contains scripts for complete SQLite database migration.

## Files

- `full_migration.sh` - Main migration script
- `verify_migration.sh` - Verification script to check migration results
- `sqlite_merge_data.sh` - Original merge script
- `sqlite_map_mismatched.sh` - Map mismatched tables script
- `sqlite_apply_mappings.sh` - Apply column mappings script
- `column_mappings.conf` - Column mapping configuration

## How to Run Migration

### Step 1: Make scripts executable
```bash
chmod +x scripts/*.sh
```

### Step 2: Run the full migration
```bash
./scripts/full_migration.sh database/database.sqlite database/database_serv.sqlite
```

### Step 3: Verify the results
```bash
./scripts/verify_migration.sh database/database.sqlite
```

## What the Migration Does

1. **Creates backup** of target database
2. **Migrates data** from source to target for matching tables
3. **Handles mismatched tables** by using common columns
4. **Applies default values** for new columns:
   - `blog_posts.is_published = 1`
   - `contractors.country = 'Poland'`
   - `event_program_points` various defaults
5. **Generates detailed report** in `database/migration_reports/`

## Safety Features

- Automatic backup creation
- No database structure changes
- Safe INSERT OR IGNORE operations
- Detailed logging and error handling

## Expected Results

After successful migration:
- All data from source database transferred
- New columns populated with sensible defaults
- Complete migration report generated
- Backup available for rollback if needed

## Troubleshooting

If migration fails:
1. Check the error messages in terminal
2. Review the migration report
3. Restore from backup if necessary:
   ```bash
   cp database/database.sqlite.backup_TIMESTAMP database/database.sqlite
   ```

## Reports Location

All migration reports are saved in:
```
database/migration_reports/
```

Look for files named `full_migration_TIMESTAMP.txt` for complete results.
