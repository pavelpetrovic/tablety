# Školské tablety – specification

## Purpose
A Slovak-language responsive reservation website for a shared pool of school tablets, running on Apache/PHP/MySQL 8. Publicly hosted web root is `web/`.

## Authentication and access
- A single shared teacher password and separate administrator password; no per-teacher accounts.
- First deployment and CLI admin reset allow setting the admin password through the website without the old password.
- Only authenticated users can add or cancel reservations. Admin can also manage passwords, capacity, welcome text and past reservations.
- A random 256-bit read-only token in notification links permits calendar viewing without login; it does not permit modifications. CLI rotation invalidates previous links.
- Passwords are hashed with PHP `password_hash`; session cookies are HttpOnly, Secure and SameSite=Lax; POST actions require CSRF tokens.

## Reservations
- Teacher full name, tablet quantity, start and end date/time and per-reservation removal password required.
- A reservation is one continuous interval, including nights; repeating dates require separate entries.
- Starts strictly in the future and within three calendar months; ends after starts.
- Overlapping reservations are permitted only if the sum of reserved tablets never exceeds configurable capacity (initially 25). Writers serialize through a locked settings row.
- Admin or anyone supplying the reservation removal password can cancel; cancellation deletes the record, including its IP address and timestamp.
- No editing: cancel and recreate.

## Calendar and history
- Responsive vertical daily calendar, 30-day paging, green for no bookings, yellow for partially reserved, grey for fully reserved during any part of a day.
- A continuous multi-day booking is displayed on each affected day.
- Current reservations hide those already ended. Admin can view past bookings and export UTF-8 CSV.

## Notifications and calendar
- Non-admin creation/cancellation sends an individual SMTP email to admin.
- Admin creation/cancellation sends no immediate email.
- A daily CLI job sends one reminder for each reservation beginning tomorrow, including admin-created reservations.
- Both types of email contain a read-only calendar link and a Google Calendar event template link.
- Calendar events are not synchronized on cancellation.
- Time zone: Europe/Bratislava.

## Deployment/security
- Credentials are in ignored `config.php` outside the public `web/` document root.
- PHPMailer is installed through Composer; scripts run from CLI.
- No public diagnostic PHP page should remain in production.
