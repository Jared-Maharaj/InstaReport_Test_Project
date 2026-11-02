# InstaReports

## Project Overview

InstaReports is my solution to the BlackWidow coding assessment. Existing users sign in through a magic link, submit Security or Service Delivery reports, and review their submissions. Area Managers have application-wide visibility and receive email alerts for HIGH severity Security incidents.

This self-contained solution packages a Laravel API and Vue frontend in one project, using SQLite and local photo storage to keep setup simple. I have expanded on the core requirements by including dashboards, report retrieval, manager views, and location lookups.


## Technology Stack

  - PHP 8.5 and Laravel 13.
  - Vue 3, PrimeVue 5 with Aura Theme, and Tailwind CSS 4.
  - Custom auth to generate and verify magic links.
  - Custom rate limiting for login attempts.
  - Custom scheduler for expired magic link cleanup.
  - SQLite, Laravel sessions, and Laravel mail notifications.
  - Leaflet with OpenStreetMap tiles and Geoapify address lookup.
  - PHPUnit 12 feature tests.


## Quick Start

Requires macOS/Linux with Bash, PHP 8.5, Composer, Node.js 22.12+, npm. Port 8000 must also be available.

From the project root, run:

```bash
bash setup.sh
```

The script installs dependencies, copies .env.example to .env, generates an application key, initializes SQLite, seeds users, builds Vue, and starts the application and scheduler. Open **http://127.0.0.1:8000** when complete/ press **Ctrl+C** to stop.

**NOTE:** Mailing is setup with my own API key - meaning emails will come to my mailtrap sandbox. To test this application in ones own environment, navigate to https://mailtrap.io/ and signup. Setup a sandbox environment, generate username & password, and replace the following in .env:
MAIL_USERNAME={your_mailtrap_username}
MAIL_PASSWORD={your_mailtrap_password}

## Features

 - **Passwordless sign-in:** request a single-use magic link sent to a registered email address. Open it to access the dashboard. Only existing users can sign in.

 - **Guided reporting:** enter incident details, location and time, optional photos, then review before submitting. Confirmation includes the tracking reference and a copy action.

 - **Location assistance:** search an address, select a match, and refine coordinates by dragging the map marker. A street name may not be enough ;D

 - **Personal dashboard:** view submitted reports with pagination, open full details, and download individual attachments.

 - **Area Reports:**  only managers can view all reports with reporter details. HIGH severity reports appear first.

 - **High-severity alerts:** every Area Manager receives an email when a HIGH severity Security report is submitted.


## Key Implementation Decisions

**Custom authentication logic:** token issuance/verification and login throttling are implemented through custom logic, with no use of Laravel's built-in functions. Sessions, cryptographic, and notification utilities for simplicity, as they are not prohibited by the spec. CSRF middleware protects against state-changing requests

**Secure tokens:** Tokens contain 32 cryptographically random bytes, are stored only as SHA256 hashes, and expire after 15 minutes. Successful authentication invalidates outstanding links and regenerates the session and CSRF.

**Scheduled token cleanup:** a daily task deletes magic-link tokens that expired at least 24 hours earlier, keeping the token table small. Production environment should have a larger window, but 24hs is just for demonstration.

**Same-origin application:** Laravel backend and Vue3 frontend packed as one complete application with a setup script to boot!

**Minimal operational requirements:** SQLite and local media storage purposefully avoid additional infrastructure. I've tried to keep the implementation lightweight while addressing the requirements and considering high-impact quality-of-life inclusions.

**Additional packages:** I have added PrimeVue support with Aura Theme for frontend development support and component consistency. MailTrap is used as a mail host, with a limited api key. LeafletJs with OpenStreetMap tiles and GeoApify are used for location handling, backed by a single endpoint for lookups.

**Explicit boundaries:** Ordinary case endpoints always enforce ownership, I created a dedicated manager endpoint that returns all cases after verifying identity and role. Additionally, ONLY area managers will recieve email notifications regarding HIGH severity cases.

**Backend-owned validation:**  incident report categories depend on type and severity is required only for Security. Photos and latitude/ longitude coordinates are optional, as they provide useful detail but may not always be available.

**Shared UI:** The frontend reuses alot of the same code as many components like the DataTable are reused accross different forms. This keeps things clean and modular while reducing duplication.



## API Overview

All endpoints use Laravel's `web` middleware. Authenticated endpoints require session cookies, and POST requests require CSRF protection.

- `POST /api/auth/magiclink` requests a sign-in email.
- `GET /api/auth/magiclink/verify?token=...` consumes the link and signs in.
- `GET /api/auth/user` returns the current user;
- `POST /api/auth/logout` ends the session.
- `POST /api/cases/incident/report` submits a report, with optional multipart photos.
- `GET /api/cases` returns the users submitted reports
- `GET /api/cases/{reference}` returns submitted report details
- `GET /api/cases/{reference}/photos/{photo}` retrieves a submitted photo.
- `GET /api/area/reports` is accessible only by managers and has filters. It has `/{reference}` and `/{reference}/photos/{photo}` routes that provide detail/photo access. 
- `GET /api/locations?address=...` returns address matches and coordinates for location handling.


## Tests

```bash
php artisan test --compact
```

Seven focused feature tests cover the core responsibilities:

1. **Magic-link:** existing users only, generic responses, hash-only storage.
`test_magic_link_issuance_is_secure_and_limited_to_existing_users()`

2. **Successful authentication:** correct user/session, token consumption, session/CSRF rotation, and replay rejection.  
`test_a_valid_link_establishes_a_session_and_cannot_be_reused()`

3. **Invalid links:** expired tokens and forged tokens cannot authenticate or consume an unrelated valid link.  
   `test_expired_and_forged_links_fail_without_consuming_a_valid_link()`

4. **Valid reports:** Security with multiple photos and Service Delivery without photos/severity persist with correct ownership, reference, timestamps, and OPEN status.  
   `test_security_and_service_delivery_reports_persist_with_the_expected_details()`

5. **Reporter privacy:** owned case/photo access succeeds, wrong user requesting fails.
   `test_reporter_cases_and_photos_remain_private_and_require_authentication()`

6. **Manager authorization:** area managers routes require the role and expose actual reporter details without affecting that users security  
   `test_only_managers_can_access_area_reports_without_bypassing_owned_case_routes()`

7. **Notification targeting:** only HIGH Security incidents notify every area manager and no ordinary users.  
   `test_only_high_security_reports_notify_every_area_manager_and_no_other_users()`


## Trade-offs & Future Improvements

 - **Report updates workflow:** the solution is built to capture the incident report, further functionality needs to be added to give it a full lifecycle of updates and tracking.

 - **Area Managers:** area managers basically just act like an admin role - it would be worthwhile to implement geological assignment so an area manager actually covers an area.

 - **Magic Link sECURITY:** One-click magic links provide immediate access, but they are succeptable to email scanners.

 - **Synchronous Emails:** My email approach keeps the solution simple but this would not work in production where there are thousands of users. We would need an async solution with parallel processing.

 - **Storage:** I used SQLite because it is packaged natively with Laravel. Production may need a better storage mechanism, as well as a dedicated disk for uploaded media and backups.

 - **php.ini** uploads are restricted to 10mb at a framework level, but `upload_max_filesize` and `post_max_size` would need to be updated in php.ini.