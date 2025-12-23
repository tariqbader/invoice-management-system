# Security Guidelines

## 🔒 Configuration Security

### Important: Never Commit Sensitive Credentials

The `config.php` file contains sensitive information including:
- Database credentials
- SMTP passwords
- API keys
- Company information

**This file is already in `.gitignore` and should NEVER be committed to version control.**

## Setup Instructions

### For New Installations:

1. **Copy the example configuration:**
   ```bash
   cp config.example.php config.php
   ```

2. **Edit `config.php` with your actual credentials:**
   - Database connection details
   - SMTP server credentials
   - Company information
   - Email settings

3. **Verify `config.php` is ignored:**
   ```bash
   git status
   # config.php should NOT appear in the list
   ```

### For Existing Installations:

If you've already committed `config.php` with real credentials:

1. **Change all exposed credentials immediately:**
   - Database passwords
   - SMTP passwords
   - Any API keys

2. **Remove from Git history (optional but recommended):**
   ```bash
   git filter-branch --force --index-filter \
   "git rm --cached --ignore-unmatch config.php" \
   --prune-empty --tag-name-filter cat -- --all
   
   git push origin --force --all
   ```

## GitGuardian Alert Response

If you receive a GitGuardian alert about exposed credentials:

1. ✅ **Immediately change the exposed credentials**
2. ✅ **Verify `config.php` is in `.gitignore`**
3. ✅ **Use `config.example.php` as a template**
4. ✅ **Never commit real credentials to Git**

## Best Practices

- ✅ Keep `config.php` in `.gitignore`
- ✅ Use `config.example.php` for documentation
- ✅ Use environment variables for production
- ✅ Rotate credentials regularly
- ✅ Use strong, unique passwords
- ✅ Enable 2FA where possible

## Environment Variables (Production)

For production environments, consider using environment variables instead of `config.php`:

```php
define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'database');
define('DB_USER', getenv('DB_USER') ?: 'user');
define('DB_PASS', getenv('DB_PASS') ?: '');
```

## Questions?

If you have security concerns or questions, please contact the repository maintainer.
