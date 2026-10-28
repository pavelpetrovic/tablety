# Školské tablety – rezervácie

Simple responsive Slovak-language PHP/MySQL reservation system for the school's tablets. See [SPECIFICATION.md](SPECIFICATION.md) for requirements.

## Installation

Requires PHP 8.1+ with pdo_mysql, mbstring and openssl, MySQL 8, Composer and HTTPS. Configure Apache document root to the repository's `web/` directory, not the repository root.

1. Copy `config.example.php` to `config.php` at the repository root, and fill in database credentials, the admin notification address and SMTP settings. Keep it out of Git and the public web directory.
2. Create the MySQL database and user (already prepared on the server), then run:

   ```sh
   mysql -u tablety -p tablety < database/schema.sql
   composer install --no-dev --optimize-autoloader
   php scripts/rotate_view_token.php
   ```

3. Visit the site **immediately** and choose the initial administrator password (minimum 12 characters). This first-time setup is deliberately unprotected, as requested.
4. Under admin settings, choose the shared teacher password, edit the welcome text, and verify capacity (default **25** tablets).
5. Test a booking, cancellation and SMTP email before distributing the shared teacher password. Gmail SMTP normally requires a Google app password.

Set up cron jobs (replace `/srv/tablety` with the real repository directory):

```cron
0 17 * * * /usr/bin/php /srv/tablety/scripts/send_reminders.php
0 3 1 * * /usr/bin/php /srv/tablety/scripts/rotate_view_token.php
```

The reminder script sends an individual email for every reservation **starting tomorrow**, including admin-created bookings. Each reminder is marked sent after successful delivery. Token rotation invalidates links from previous emails.

## Using the website

Log in with the shared teacher password or the administrator password. Enter a teacher's full name, tablet count, start and end date/time and a reservation-specific removal password. A booking is **one continuous interval**, possibly including several days and nights. For recurring use, create separate bookings. Reservations may start from now up to three months ahead. Overbooking is rejected even when only part of an interval overlaps.

The vertical calendar shows 30 days at a time. Green means free, yellow means some tablets are booked, grey means all tablets are booked during at least part of the day. A multi-day booking appears on every affected day.

To change a reservation, cancel and register again. The removal password or admin login is required to cancel. Cancellation permanently deletes the reservation, including its creation timestamp and IP address. Administrators can view previous reservations, export CSV, change passwords, tablet capacity and welcome text.

Every teacher-made booking/cancellation generates an individual email to the administrator. Administrator-made changes generate no immediate email. Emails include a read-only token link to the reservation calendar and a Google Calendar event-creation link. Calendar events are not synchronized with cancellations. The token link grants viewing only; modifications still require login.

## Maintenance

```sh
php scripts/reset_admin.php         # clear admin password; immediately set a new one on the site
php scripts/rotate_view_token.php   # invalidate all previous read-only email links
php scripts/send_reminders.php      # manually run tomorrow's reminder delivery
```

The application uses the Europe/Bratislava time zone. PHP/SMTP errors go to the server error log. If email fails, a successful reservation is **not** rolled back.

**Security:** Do not commit `config.php`. Use HTTPS, limit filesystem access to the configuration, and delete the diagnostic `php.php` file from the public site. The private view links are bearer tokens; do not publish them. The admin setup page is intentionally open until a password is set.
