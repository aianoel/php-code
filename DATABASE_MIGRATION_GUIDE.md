# SchoolEnroll-1 Database Migration Guide

This guide will help you migrate your local database to Hostinger, combining both databases while preserving data integrity.

## Prerequisites

1. Local XAMPP installation with MySQL
2. Access to Hostinger database (credentials already configured)
3. PHP 7.4+ with PDO extension enabled

## Migration Options

### Option 1: Using the Automated Migration Script (Recommended)

1. **Update Local Database Credentials**
   - Open `migrate-to-hostinger.php`
   - Update the local database connection details if needed:
     ```php
     $local = [
         'host' => 'localhost',
         'db_name' => 'school_enrollment', // Update if different
         'username' => 'root', // Update with your local MySQL username
         'password' => '', // Update with your local MySQL password
         'charset' => 'utf8mb4'
     ];
     ```

2. **Run the Migration Script**
   - Upload the script to your Hostinger server
   - Access it via browser: `https://yourdomain.com/migrate-to-hostinger.php`
   - Alternatively, run it locally if you have remote MySQL access to Hostinger

3. **Verify Migration**
   - Check the output for any errors
   - Verify that all tables and data were migrated successfully
   - Test the application functionality

### Option 2: Manual Database Export/Import

1. **Export Local Database**
   - Open phpMyAdmin on your local XAMPP
   - Select your database
   - Click "Export" at the top menu
   - Choose "Custom" export method
   - Select all tables
   - Enable "Add CREATE TABLE / CREATE VIEW / CREATE PROCEDURE" options
   - Choose SQL format
   - Click "Go" to download the SQL file

2. **Import to Hostinger**
   - Log in to Hostinger control panel
   - Access phpMyAdmin
   - Select the `u870495195_admission` database
   - Click "Import" at the top menu
   - Upload your SQL file
   - Click "Go" to start the import

3. **Resolve Conflicts (if any)**
   - If you encounter duplicate key errors:
     - Edit your SQL file to add `INSERT IGNORE` instead of `INSERT`
     - Or use `REPLACE INTO` instead of `INSERT INTO`
   - For table structure conflicts:
     - Compare table structures and manually adjust as needed

## Data Conflict Resolution

The migration script handles conflicts using these strategies:

1. **For records with primary keys:**
   - If a record with the same primary key exists in Hostinger:
     - The record is updated with local data
   - If no matching record exists:
     - A new record is inserted

2. **For records without primary keys:**
   - Uses `INSERT IGNORE` to skip duplicates
   - Records that cause constraint violations are skipped

## Post-Migration Steps

1. **Update Configuration**
   - Ensure `includes/config.php` on Hostinger points to the correct database

2. **Test Critical Functionality**
   - User authentication
   - Student enrollment
   - Grade management
   - Financial transactions

3. **Backup the Migrated Database**
   - Create a backup of the successfully migrated database

## Troubleshooting

- **Connection Issues:**
  - Verify database credentials
  - Check if remote MySQL connections are allowed

- **Timeout Errors:**
  - For large databases, increase `max_execution_time` in PHP settings
  - Consider splitting the migration into smaller batches

- **Character Set Issues:**
  - Ensure both databases use UTF-8 encoding

- **Missing Tables:**
  - Check for errors in table creation SQL
  - Manually create missing tables using schema.sql

For additional assistance, refer to the Hostinger support documentation or contact their customer support.
