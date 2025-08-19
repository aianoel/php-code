# Admin Student Management Features Documentation

## Overview

This document provides information about the enhanced admin features for student management in the SchoolEnroll-1 system. These features include separate management interfaces for students and users, as well as secure student password management capabilities.

## Features

### 1. Student Management Page (`student-management.php`)

The Student Management page provides a dedicated interface for administrators to manage student records separately from other system users.

#### Key Features:
- **Tabbed Interface**: Separate tabs for Students and Users
- **Students Tab**: Displays all students with their details
  - Student ID
  - Name
  - Email
  - Grade Level
  - Section
  - Status
  - Actions (View Details, Reset Password)
- **Users Tab**: Displays all non-student users with their details
  - Name
  - Email
  - Role
  - Status
  - Creation Date
  - Actions
- **Statistics Cards**: Shows total students, total users, and student counts by grade level
- **Search and Filter**: Ability to search and filter student and user lists
- **Modal Dialogs**: For viewing detailed information and performing actions

### 2. Student Password Management (`student-passwords.php`)

The Student Password Management page allows administrators to securely manage student passwords for all enrolled students in the system.

#### Key Features:
- **Comprehensive Student Coverage**: Displays all enrolled students from both sources:
  - Students from the `students` table with `status = 'enrolled'`
  - Students from the `enrollment_applications` table with `status IN ('approved', 'enrolled')`
- **Password Reset**: Ability to reset student passwords with a secure random password generator
- **Password Viewing**: One-time password viewing capability (generates a temporary password)
- **Security Measures**:
  - All password-related actions are logged in the `activity_logs` table with detailed student information
  - Passwords are properly hashed before storage
  - Password visibility toggle for better security
- **Advanced Search and Filter**: Filter students by grade level, section, strand, or search by name/ID/email
- **Source Identification**: Clear labeling of student records by source (student_record or enrollment_application)
- **User Experience**: Modern UI with responsive design and intuitive controls

### 3. Activity Logging

All sensitive actions related to student password management are logged in the `activity_logs` table.

#### Logged Actions:
- Password viewing
- Password resetting
- Each log entry includes:
  - User ID (admin who performed the action)
  - Action type
  - Details
  - IP address
  - Timestamp

## Security Considerations

1. **Password Handling**:
   - Passwords are never stored in plaintext
   - All passwords are hashed using PHP's `password_hash()` function
   - Password resets generate secure random passwords

2. **Access Control**:
   - Only users with admin role can access these pages
   - Role verification is performed on every page load

3. **Audit Trail**:
   - All sensitive actions are logged for security auditing
   - Logs include user ID, action type, details, IP address, and timestamp

## Technical Implementation

1. **Database Tables**:
   - `users`: Stores user credentials and basic information
   - `students`: Stores legacy student information linked to users
   - `enrollment_applications`: Stores new student enrollment information
   - `activity_logs`: Tracks sensitive admin actions

2. **Key Files**:
   - `admin/student-management.php`: Main student management interface
   - `admin/student-passwords.php`: Password management interface for all enrolled students
   - `admin/get_student_password.php`: Backend handler for password viewing and resetting
   - `database/create_activity_logs.sql`: SQL for creating the activity logs table
   - `setup_activity_logs.php`: Script to set up the activity logs table

3. **Data Integration**:
   - The system combines student data from two sources:
     - Legacy students from the `students` table with status='enrolled'
     - New students from the `enrollment_applications` table with status IN ('approved', 'enrolled')
   - Each student record is tagged with its source for proper handling
   - Password management works seamlessly across both student sources

## Usage Instructions

### Accessing Student Management:
1. Log in as an administrator
2. Click on "Student Management" in the admin sidebar
3. Use the tabs to switch between Students and Users views

### Managing Student Passwords:
1. Log in as an administrator
2. Click on "Student Passwords" in the admin sidebar
3. Use the search box and filters (grade level, section, strand) to find specific students
4. To reset a password: Click the "Reset" button next to a student, enter or generate a new password, and confirm
5. To view a password: Click the eye icon next to a student's password field
6. Note the source of each student record (student_record or enrollment_application) displayed next to their name

## Troubleshooting

### Common Issues:
1. **Activity Logs Table Missing**: Run the `setup_activity_logs.php` script to create the table
2. **Password Reset Failure**: 
   - For student_record source: Ensure the student's user account exists and is properly linked in the students table
   - For enrollment_application source: Verify that the email in enrollment_applications matches a user account with role='student'
3. **Missing Students**: Check that students have either:
   - A record in the students table with status='enrolled' OR
   - A record in the enrollment_applications table with status IN ('approved', 'enrolled')
4. **Filter Issues**: If filters show no options, verify that grade levels, sections, and strands are properly set in both student sources
5. **Access Denied**: Verify that the user has admin role permissions
