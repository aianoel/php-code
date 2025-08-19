# SchoolEnroll-1 Hostinger Deployment Guide

## Database Configuration
```
MySQL Database Name: u870495195_admission
MySQL Username: u870495195_admission
MySQL Password: 8uJs293cjJB
```

## Deployment Steps

### 1. Prepare Your Files
- Make sure your GitHub repository is up to date
- Download a ZIP of your repository or use Git to clone it locally

### 2. Database Setup
- Log in to your Hostinger control panel
- Navigate to the MySQL Databases section
- The database has already been created with the credentials above
- Import your database schema using phpMyAdmin:
  - Go to phpMyAdmin in your Hostinger control panel
  - Select the `u870495195_admission` database
  - Click on the "Import" tab
  - Upload and import the `database/schema.sql` file

### 3. File Upload
- Use FTP or the Hostinger File Manager to upload all files to your hosting account
- Upload to the public_html directory or a subdirectory if you want the system in a subfolder

### 4. Configuration
- Rename `includes/config.hostinger.php` to `includes/config.php` on the server
  (or update the existing config.php with the Hostinger credentials)
- Set proper file permissions:
  - Directories: 755 (`chmod 755 directory_name`)
  - Files: 644 (`chmod 644 file_name`)

### 5. Testing
- Access your website at your Hostinger domain
- Verify all pages load correctly
- Test login functionality for different user roles
- Check database connectivity

### 6. Troubleshooting
- Check PHP error logs in your Hostinger control panel
- Verify database connection settings
- Ensure all required PHP extensions are enabled
- Check file permissions if you encounter access issues

## Maintenance
- Regularly backup your database through the Hostinger control panel
- Keep your code up to date by pushing changes to GitHub and then updating the files on Hostinger
- Monitor disk space and database size through the Hostinger control panel

## Security Notes
- Change the default admin password immediately after deployment
- Remove any test/sample user accounts
- Consider enabling HTTPS if not automatically enabled
- Regularly update your PHP version to the latest supported by Hostinger
