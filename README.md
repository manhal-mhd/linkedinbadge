# LinkedIn Badge Share Plugin for Moodle

A Moodle local plugin that enables users to share their earned badges directly to LinkedIn through secure OAuth2 authentication.

## Structure
```
linkedinbadge/
├── classes/
│   ├── linkedin_oauth.php    # LinkedIn OAuth2 implementation
│   └── logger.php           # Logging functionality
├── db/
│   ├── hooks.php           # Moodle hooks configuration
│   └── install.xml        # Database schema
├── lang/
│   └── en/
│       └── local_linkedinbadge.php  # Language strings
├── lib.php                # Core plugin functions
├── linkedin_callback.php  # OAuth callback handler
├── post_badge.php        # Badge posting handler
├── settings.php         # Plugin settings
├── share_badge.php     # Badge sharing interface
└── version.php        # Plugin version info
```
## Prerequisites

### Database Adjustment
Before installation, you need to modify your Moodle database to support LinkedIn tokens:

```sql
ALTER TABLE mdl_user_preferences MODIFY value TEXT;
```

This modification is necessary because LinkedIn tokens can exceed the default field length.

## Requirements
- Moodle 4.0 or higher
- PHP 7.4 or higher
- LinkedIn Developer Account
- SSL enabled site (https)
- Curl PHP extension

## Installation

1. Clone or download the plugin to your Moodle local directory:
```bash
cd /path/to/moodle/local
git clone [repository-url] linkedinbadge
```

2. Set proper permissions:
```bash
chown -R www-data:www-data linkedinbadge
chmod -R 755 linkedinbadge
```

3. Install via Moodle UI:
   - Login as admin
   - Go to Site Administration → Notifications
   - Follow the installation prompts

## Configuration

### LinkedIn Application Setup

1. Create LinkedIn Application:
   - Go to https://www.linkedin.com/developers/
   - Click "Create App"
   - Fill required information:
     ```
     App Name: [Your App Name]
     Company: [Your Company]
     Privacy Policy URL: https://your-moodle-domain/privacy
     ```

2. Configure OAuth 2.0:
   - Add Redirect URL:
     ```
     https://your-moodle-domain/local/linkedinbadge/linkedin_callback.php
     ```
    Required Scopes:
     - openid
     - profile
     - email
     - w_member_social
     ```
3. Get Credentials:
   - Note your Client ID
   - Note your Client Secret

### Plugin Configuration

1. Navigate to:
   ```
   Site Administration → Plugins → Local plugins → LinkedIn Badge Share
   ```

2. Enter LinkedIn credentials:
   - Client ID
   - Client Secret

3. Save settings

## Usage

### For Users

1. Connect LinkedIn Account:
   - Go to profile settings
   - Click "Connect LinkedIn Account"
   - Authorize the application

2. Share Badges:
   - Navigate to your badges
   - Select a badge
   - Click "Share on LinkedIn"
   - Customize message (optional)
   - Submit

### For Administrators

1. Monitor Usage:
   - Check logs in moodledata/linkedin_logs/
   - Monitor connections
   - Track sharing activity

2. Manage Settings:
   - Update LinkedIn credentials
   - Configure logging
   - Manage permissions

## Development

### File Purposes

- `linkedin_oauth.php`: Handles OAuth2 authentication with LinkedIn
- `logger.php`: Manages logging functionality
- `hooks.php`: Configures Moodle hooks and events
- `install.xml`: Defines database schema
- `lib.php`: Contains core plugin functions
- `linkedin_callback.php`: Processes OAuth callbacks
- `post_badge.php`: Handles badge posting to LinkedIn
- `settings.php`: Manages plugin settings
- `share_badge.php`: Provides sharing interface

### Adding Features

1. Add new functionality:
   - Create necessary classes in `classes/`
   - Update language strings in `lang/en/local_linkedinbadge.php`
   - Add settings if needed in `settings.php`

2. Testing:
   - Test with different badge types
   - Verify LinkedIn integration
   - Check error handling

## Troubleshooting

Common issues and solutions:

1. Connection Issues:
   - Verify LinkedIn credentials
   - Check SSL certificate
   - Validate redirect URI

2. Sharing Problems:
   - Check user permissions
   - Verify OAuth scopes
   - Review error logs

## Support

- Documentation: [Documentation URL]
- Issue Tracker: [Issues URL]
- Contact: [Contact Information]

## License

GPL v3 or later
http://www.gnu.org/copyleft/gpl.html

## Credits

Developed by: [Manhal.Mohammed]
Version: 1.0.0

## Version History

- 1.0.0 (2024-11-08)
  - Initial release
  - Basic LinkedIn sharing functionality
  - OAuth2 integration
  - Badge sharing interface
