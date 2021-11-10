Setting up the system from scratch
=

- Setup new database instance.
- When dumping the DB from an old instance, ensure to set initial `AUTO_INCREMENT` values to 1.
- Check webuser permissions required (lock tables).
- Check `max_connections` value in the DB configuration (minimum 1024).
- Checkout code from git
- Run `fixperms.sh` script to ensure directory structure and permissions are correct.
- Run `composer install` in includes directory.
- Modify `includes/c_config.php` with the appropriate company name, domain names, email address, database credentials, etc.
- Setup `includes/f_site.php` file.
- Change `HASH_SALT` and `ENCRYPTION_KEY` values in `includes/c_config.php` to randomly-generated strings.
- Change nav and system colors for `table.revenue-report thead td`, `navbar-custom`, `table th`, `.modal-header` in `public_html/assets/css/branding.css`
- Save updated logos in `public_html/leadadmin/images/Q-isolated.jpg` and `public_html/leadadmin/images/logo.png`
- Configure Nginx and setup SSL certificate.
- Populate `divisions` DB table.
- Populate `countries` DB table.
- Populate `configuration` DB table with notify_interval variables.
- Populate `fields` DB table with all non-custom fields.
- Setup cron entries for managing push threads, notifications, jobs, etc.
- Create first user account database.
- Create a new inbound feed to handle outbound popoulations and set as `INBOUND_FEEDID_MANUAL_UPLOAD`.