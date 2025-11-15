#!/bin/bash
# =============================================================================
# BACKUP DAN MIGRATION SCRIPT - MANUAL EXECUTION GUIDE
# =============================================================================
# File: backup_and_migrate.sh
# Purpose: Backup database and run migration for shift management enhancement
# =============================================================================

echo "=========================================="
echo "🔐 SHIFT MANAGEMENT ENHANCEMENT"
echo "   Backup & Migration Script"
echo "=========================================="
echo ""

# Set variables
BACKUP_DIR="/Applications/XAMPP/xamppfiles/htdocs/aplikasi"
BACKUP_FILE="backup_pre_migration_$(date +%Y%m%d_%H%M%S).sql"
PRE_MIGRATION_FILE="pre_migration_patch.sql"
MIGRATION_FILE="migration_shift_enhancement.sql"
DB_NAME="aplikasi"
DB_USER="root"

# Navigate to directory
cd "$BACKUP_DIR" || exit

echo "📁 Working directory: $BACKUP_DIR"
echo ""

# =============================================================================
# STEP 1: BACKUP DATABASE
# =============================================================================
echo "=========================================="
echo "STEP 1: BACKUP DATABASE"
echo "=========================================="
echo ""
echo "🔄 Creating backup of database '$DB_NAME'..."
echo "📄 Backup file: $BACKUP_FILE"
echo ""
echo "⚠️  You will be prompted for MySQL root password"
echo ""

# Backup command
mysqldump -u "$DB_USER" -p "$DB_NAME" > "$BACKUP_FILE"

# Check if backup was successful
if [ -f "$BACKUP_FILE" ]; then
    BACKUP_SIZE=$(ls -lh "$BACKUP_FILE" | awk '{print $5}')
    echo ""
    echo "✅ Backup successful!"
    echo "📊 File size: $BACKUP_SIZE"
    echo "📍 Location: $BACKUP_DIR/$BACKUP_FILE"
    
    # Verify backup is not empty
    if [ ! -s "$BACKUP_FILE" ]; then
        echo ""
        echo "⚠️  WARNING: Backup file is empty or very small!"
        echo "Please check your MySQL connection and try again."
        exit 1
    fi
else
    echo ""
    echo "❌ Backup failed!"
    echo "Please check your MySQL credentials and try again."
    exit 1
fi

echo ""
read -p "✋ Press Enter to continue with pre-migration patch, or Ctrl+C to abort..." -n1 -s
echo ""
echo ""

# =============================================================================
# STEP 2: RUN PRE-MIGRATION PATCH (Add id_cabang columns)
# =============================================================================
echo "=========================================="
echo "STEP 2: PRE-MIGRATION PATCH"
echo "=========================================="
echo ""
echo "🔄 Running pre-migration patch..."
echo "📄 Patch file: $PRE_MIGRATION_FILE"
echo "   (Adds id_cabang to pegawai_whitelist and register)"
echo ""

# Check if pre-migration file exists
if [ ! -f "$PRE_MIGRATION_FILE" ]; then
    echo "❌ Error: Pre-migration file '$PRE_MIGRATION_FILE' not found!"
    echo "Please make sure the file exists in the current directory."
    exit 1
fi

echo "⚠️  You will be prompted for MySQL root password again"
echo ""

# Run pre-migration patch
mysql -u "$DB_USER" -p "$DB_NAME" < "$PRE_MIGRATION_FILE"

# Check if pre-migration was successful
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Pre-migration patch successful!"
    echo "   - Added id_cabang to pegawai_whitelist"
    echo "   - Added id_cabang to register"
    echo "   - Mapped existing outlet data to cabang IDs"
else
    echo ""
    echo "❌ Pre-migration patch failed!"
    echo ""
    echo "To rollback, run:"
    echo "mysql -u $DB_USER -p $DB_NAME < $BACKUP_FILE"
    exit 1
fi

echo ""
read -p "✋ Press Enter to continue with main migration, or Ctrl+C to abort..." -n1 -s
echo ""
echo ""

# =============================================================================
# STEP 3: RUN MAIN MIGRATION
# =============================================================================
echo "=========================================="
echo "STEP 3: MAIN MIGRATION"
echo "=========================================="
echo ""
echo "🔄 Running main migration script..."
echo "📄 Migration file: $MIGRATION_FILE"
echo ""

# Check if migration file exists
if [ ! -f "$MIGRATION_FILE" ]; then
    echo "❌ Error: Migration file '$MIGRATION_FILE' not found!"
    echo "Please make sure the file exists in the current directory."
    exit 1
fi

echo "⚠️  You will be prompted for MySQL root password again"
echo ""

# Run migration
mysql -u "$DB_USER" -p "$DB_NAME" < "$MIGRATION_FILE"

# Check if migration was successful
if [ $? -eq 0 ]; then
    echo ""
    echo "✅ Main migration successful!"
else
    echo ""
    echo "❌ Main migration failed!"
    echo ""
    echo "To rollback, run:"
    echo "mysql -u $DB_USER -p $DB_NAME < $BACKUP_FILE"
    exit 1
fi

echo ""
echo "=========================================="
echo "🎉 MIGRATION COMPLETE!"
echo "=========================================="
echo ""
echo "📋 Next steps:"
echo ""
echo "1. Verify migration:"
echo "   mysql -u $DB_USER -p $DB_NAME"
echo "   Then run: SHOW TABLES;"
echo ""
echo "2. Check holidays:"
echo "   SELECT COUNT(*) FROM libur_nasional;"
echo "   (Should return 16)"
echo ""
echo "3. Check views:"
echo "   SHOW FULL TABLES WHERE Table_type = 'VIEW';"
echo "   (Should show 3 views)"
echo ""
echo "4. Read salary calculation guide:"
echo "   cat SALARY_CALCULATION_SYSTEM.md"
echo ""
echo "5. Populate salary data:"
echo "   Follow QUICK_START.md Step 4"
echo ""
echo "💾 Backup file saved at:"
echo "   $BACKUP_FILE"
echo ""
echo "⚠️  Keep this backup file safe for rollback if needed!"
echo ""
