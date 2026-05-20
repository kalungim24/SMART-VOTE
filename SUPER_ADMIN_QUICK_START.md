# Super Admin Panel - Quick Start Guide

## What's New?

You now have a dedicated **Super Admin Panel** for creating and managing administrator accounts with enhanced security and role-based access control.

## Installation (2 Steps)

### Step 1: Update Database Schema
Run this SQL migration to add the role column:

```sql
-- Add 'role' column to admins table
ALTER TABLE `admins` 
ADD COLUMN `role` VARCHAR(50) NOT NULL DEFAULT 'admin' AFTER `fullname',
ADD INDEX `idx_role` (`role`);

-- Set your account as super_admin
UPDATE `admins` SET `role` = 'super_admin' WHERE `id` = 1;
```

**Or execute the migration file:**
```bash
mysql -u root -p smart_vote < database/migration_add_admin_roles.sql
```

### Step 2: Access the Panel
1. Log in to SmartLonda Admin
2. Click **Super Admin Panel** in the sidebar (under "Security Dashboard")
3. Start creating admin accounts!

## How to Create an Admin Account

1. **Fill the form:**
   - Username (3+ characters, unique)
   - Full Name
   - Password (8+ characters, use strong password)
   - Select Role (Regular Admin or Super Admin)

2. **Click "Create Admin Account"**

3. **Done!** New admin can login immediately

## Admin Roles Explained

| Feature | Regular Admin | Super Admin |
|---------|---------------|-------------|
| Manage Voters | ✅ | ✅ |
| Manage Candidates | ✅ | ✅ |
| Manage Positions | ✅ | ✅ |
| Manage Elections | ✅ | ✅ |
| View Results | ✅ | ✅ |
| **Create Admins** | ❌ | ✅ |
| **Delete Admins** | ❌ | ✅ |
| **System Settings** | ❌ | ✅ |

## Features

✨ **Dashboard Statistics** - See total admins at a glance
🔐 **Security Logging** - All actions are logged and auditable
🛡️ **CSRF Protection** - Secure form submissions
👥 **Role-Based Access** - Control what admins can do
📊 **Admin List** - Quick view of recent accounts

## File Structure

```
admin/
├── super_admin.php              ← New Super Admin Panel
├── manage_admins.php            ← Traditional admin management
└── includes/
    └── sidebar_config.php       ← Updated with new menu item

database/
├── smart_vote.sql               ← Original schema
└── migration_add_admin_roles.sql ← Run this first!
```

## First Time Setup Checklist

- [ ] Run the SQL migration script
- [ ] Log in to admin account
- [ ] Navigate to "Super Admin Panel"
- [ ] Create your first regular admin account
- [ ] Test login with new account
- [ ] Promote account to super admin if needed

## Password Requirements

- **Minimum 8 characters**
- Should include uppercase letters
- Should include lowercase letters  
- Should include numbers
- Example: `SecureAdm!n2026`

## FAQ

**Q: Can I create super admins?**
A: Yes, during creation select "Super Admin" role

**Q: Can I delete accounts?**
A: Yes, but not your own or the system admin (ID: 1)

**Q: What if I forget an admin password?**
A: You need to edit their account in "Manage Admins" page and reset it

**Q: Are actions logged?**
A: Yes! Check "Security Dashboard" for activity logs

**Q: Can a regular admin access this panel?**
A: No, only admin accounts can access it

## Security Tips

1. 🔒 Use strong, unique passwords
2. 👤 Only create admins you trust
3. 🗑️ Delete unused admin accounts
4. 📋 Review audit logs monthly
5. ⏰ Change passwords every 90 days

## Need Help?

1. Check SUPER_ADMIN_PANEL_README.md for detailed documentation
2. Review error logs in `admin/error_log`
3. Check security dashboard for activity logs
4. Verify database migration was successful

## Support Files

- 📖 Full documentation: `SUPER_ADMIN_PANEL_README.md`
- 🗄️ Database migration: `database/migration_add_admin_roles.sql`
- ⚙️ Configuration: `admin/includes/sidebar_config.php`

---

**Version:** 1.0  
**Date:** May 6, 2026  
**Status:** Ready for Production
