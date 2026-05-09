# Super Admin Panel - Documentation

## Overview

The Super Admin Panel is a dedicated interface for creating and managing administrator accounts in the SmartVote system. This panel provides enhanced security, role-based access control, and a streamlined user experience for system administrators.

## Features

✅ **Quick Admin Creation** - Create new admin accounts with one-click form submission
✅ **Role-Based Access Control** - Distinguish between regular admins and super admins
✅ **Admin Statistics Dashboard** - View total admins, super admins, and regular admins at a glance
✅ **Security Logging** - All admin creation/deletion events are logged for audit trails
✅ **Enhanced Security** - CSRF protection, password validation, and activity tracking
✅ **Dark Theme UI** - Modern gradient design with Tailwind CSS
✅ **Recent Admin List** - Quick view of the 10 most recently created admins

## Installation & Setup

### Step 1: Database Migration

Execute the migration script to add role-based access control to the admins table:

```bash
mysql -u root -p smart_vote < database/migration_add_admin_roles.sql
```

**SQL Migration Details:**
- Adds `role` column to the `admins` table
- Sets default role as `'admin'` for existing and new accounts
- Promotes the system administrator (ID: 1) to `'super_admin'` role
- Creates an index on the role column for performance

### Step 2: Access the Super Admin Panel

1. Log in to your SmartVote admin account
2. Navigate to **Admin > Super Admin Panel** in the sidebar
3. You'll see the admin management dashboard

## User Roles

### Regular Admin
- Can manage voters, candidates, positions, and elections
- Can view voting results
- Can access security dashboard
- **Cannot** create or manage other admin accounts

### Super Admin
- Has **full system access**
- Can create new admin accounts (regular or super admin)
- Can delete and manage admin accounts
- Can manage voters, candidates, positions, and elections
- Can view all security and audit logs
- Can perform system-wide configuration changes

## Creating a New Admin Account

### Quick Create Form

1. **Username** - Choose a unique username (minimum 3 characters)
2. **Full Name** - Enter the admin's full name
3. **Password** - Set a strong password (minimum 8 characters recommended)
4. **Role** - Select the admin type:
   - **Regular Admin** - For general admin staff
   - **Super Admin** - For system administrators with full access

### Password Requirements

- Minimum 8 characters
- Recommended: Mix of uppercase, lowercase, numbers, and special characters
- Example: `SecurePass123!`

### Example

```
Username: john_admin
Full Name: John Smith
Password: MySecure@Pwd2026
Role: Regular Admin
```

## Managing Admin Accounts

### Viewing Admins

The panel displays a list of the 10 most recent admin accounts with:
- Admin ID
- Username
- Full Name
- Current Role (Regular Admin or Super Admin)
- Creation Date
- Action Buttons

### Deleting an Admin Account

1. Navigate to the admin in the list
2. Click the "Delete" button
3. Confirm the deletion in the prompt
4. The admin account will be permanently removed

**Restrictions:**
- Cannot delete your own account
- Cannot delete the system administrator (ID: 1)
- Cannot delete accounts that are still in use

### Editing Admin Accounts

To edit an existing admin account:
1. Navigate to `manage_admins.php` (traditional admin management page)
2. Click "Edit" on the desired admin
3. Update username, name, or password
4. Click "Update Admin"

## Security Features

### CSRF Protection
- All forms include CSRF tokens
- Prevents cross-site request forgery attacks

### Password Hashing
- Uses PHP's `password_hash()` with PASSWORD_DEFAULT algorithm
- Passwords are never displayed in logs or audit trails

### Activity Logging
- All admin creations are logged to the activity logs
- Admin deletions are tracked with timestamp and responsible user
- Security events are logged with severity levels (high, medium, low)

### Rate Limiting
- Admin creation attempts are monitored
- Suspicious activity triggers security alerts
- All activities are timestamped and IP-tracked

### Audit Trail
- Every admin creation/deletion is recorded in audit logs
- Includes: who created/deleted, when, and IP address
- Useful for compliance and security reviews

## Security Best Practices

1. **Regular Review** - Review admin accounts monthly
   - Identify and remove unused accounts
   - Verify all super admins are authorized

2. **Strong Passwords** - Enforce strong password policies
   - Use uppercase, lowercase, numbers, and symbols
   - Change passwords regularly (every 90 days recommended)

3. **Limited Super Admins** - Restrict super admin access
   - Only assign super admin role to trusted users
   - Document who has super admin access

4. **Monitor Activity** - Check security dashboard regularly
   - Review admin login attempts
   - Check for suspicious activity patterns
   - Monitor rate limit violations

5. **Audit Logs** - Review audit trails
   - Check who created/deleted admins
   - Verify all actions are authorized
   - Keep records for compliance

## Database Schema

### Admins Table
```sql
CREATE TABLE `admins` (
  `id` int(11) NOT NULL PRIMARY KEY AUTO_INCREMENT,
  `username` varchar(100) NOT NULL UNIQUE,
  `password` varchar(255) NOT NULL,
  `fullname` varchar(150) NOT NULL,
  `role` varchar(50) NOT NULL DEFAULT 'admin',
  `created_at` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;
```

**Columns:**
- `id` - Unique admin identifier
- `username` - Login username (unique)
- `password` - Hashed password
- `fullname` - Admin's full name
- `role` - 'admin' or 'super_admin'
- `created_at` - Account creation timestamp

## API/Form Data

### Create Admin Form Data

```php
POST /admin/super_admin.php

Parameters:
- action: 'create_admin'
- csrf_token: '<generated_token>'
- username: 'new_admin'
- fullname: 'Full Name'
- password: 'SecurePassword123'
- role: 'admin' // or 'super_admin'
```

### Delete Admin Form Data

```php
POST /admin/super_admin.php

Parameters:
- action: 'delete_admin'
- csrf_token: '<generated_token>'
- id: 5 // Admin ID to delete
```

## Troubleshooting

### Issue: "Username already exists"
**Solution:** Choose a different username or verify the existing account

### Issue: "Password must be at least 8 characters"
**Solution:** Use a stronger password with at least 8 characters

### Issue: "You cannot delete your own account"
**Solution:** This is a security feature. Ask another super admin to delete your account if needed

### Issue: "Cannot delete system administrator account"
**Solution:** The primary admin (ID: 1) is protected and cannot be deleted

### Issue: Database error when creating admin
**Solution:** 
1. Verify the migration script was executed
2. Check database connection in `includes/db_config.php`
3. Ensure the `admins` table exists with all required columns

## Related Files

- **Main Panel** - `admin/super_admin.php`
- **Traditional Management** - `admin/manage_admins.php`
- **Sidebar Config** - `admin/includes/sidebar_config.php`
- **Database Setup** - `database/smart_vote.sql`
- **Migration** - `database/migration_add_admin_roles.sql`

## Support

For issues or feature requests:
1. Check the error log at `admin/error_log`
2. Review the audit logs in the security dashboard
3. Verify all database migrations have been applied

## Version History

- **v1.0** (May 6, 2026) - Initial super admin panel with role-based access control
  - Added role column to admins table
  - Created super_admin.php panel
  - Enhanced security logging
  - Added admin statistics dashboard
