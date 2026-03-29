# TalentSync PRO

TalentSync PRO is a dual-role hiring platform where job seekers and job providers work in one system with profile management, live chat, map-assisted discovery, and unified job feeds.

Core stack:
- PHP 8 + PDO
- MySQL
- jQuery 3.7.1
- TailwindCSS + custom liquid-glass CSS
- Leaflet map stack

This repository is intentionally server-rendered (no React/Vite build pipeline).

## Table of Contents

- Overview
- Key Features
- Technology Stack
- Architecture
- Page-by-Page Breakdown
- API Reference
- External APIs and Integrations
- Database Design
- Security and Validation
- Setup Guide (Windows/XAMPP)
- Email Setup (Gmail + App Password)
- JobSpy Integration
- Testing Flow
- Troubleshooting

## Overview

TalentSync PRO supports three roles:
- seeker: builds profile, discovers jobs, chats with providers
- provider: posts jobs, finds talent, manages hiring pipeline
- admin: views platform metrics and moderation reports

Main experience highlights:
- Dynamic landing page with animated typography and video sections
- Full auth with CAPTCHA, session auth, and role routing
- Resume and image upload workflow with validation
- Real-time style messaging using AJAX polling
- Map and route workflow for location-driven decisions
- Email notifications for registration, reset password, and first chat interest

## Key Features

- Role-based authentication: seeker/provider/admin
- CAPTCHA endpoint with PHP GD image generation
- Profile system:
  - skill, experience, bio, city
  - avatar upload (JPEG/PNG)
  - resume upload (PDF)
  - portfolio project entries and images
- Provider tools:
  - post job
  - dashboard filtering and talent cards
  - company location and hiring metrics
  - company pipeline view
- Seeker tools:
  - live jobs feed
  - advanced filtering and sorting
  - city/company based on-demand fetch
- Chat:
  - conversation panel
  - file attachments
  - unread/read behavior
  - first-conversation email trigger
- Map:
  - list + map coordination
  - routing distance and ETA
  - map-based location picker
- Admin:
  - top-level counts and report table

## Technology Stack

## Frontend

- HTML5 templates rendered by PHP
- TailwindCSS (CDN)
- Custom CSS in assets/css/styles.css
- jQuery 3.7.1 for dynamic UI and AJAX
- Vanilla JS for map, geolocation, and animation helpers
- Google Fonts (Instrument Serif, Barlow)
- Material Symbols
- HLS.js for media fallback support

## Backend

- PHP 8
- PDO with prepared statements
- Session and cookie handling
- Mail transport through PHP mail() with relay
- GD extension for CAPTCHA and image processing
- finfo MIME detection for uploads

## Data Layer

- MySQL relational schema in database.sql
- JSON files for aggregated/fetched job data

## Architecture

## High-level flow

1. User authenticates (register/login + CAPTCHA).
2. Role router sends user to seeker/provider/admin dashboard.
3. Dashboards read from MySQL and dynamic API endpoints.
4. Chat and jobs UI use AJAX requests for near real-time updates.
5. Map views combine stored coordinates with route/geocode services.
6. Mail helper sends transactional notifications.

## Core folders

- index.php and major pages at project root
- api/: AJAX/backend endpoints
- includes/: auth, db, helper functions, shared topbars
- assets/css and assets/js: styling and frontend scripts
- data/: job caches and feed files
- uploads/: user files (avatars/resumes/projects/chat)
- integrations/JobSpy: Python-based fetch integration

## Page-by-Page Breakdown

## index.php

- Purpose: premium landing page and product overview
- Components:
  - glass navbar
  - hero with animated title
  - module/architecture/syllabus sections
- Effects:
  - hero text One Hub for Jobs Freelancers and Hiring uses fade + slide-up style animation
  - top and bottom gradient fade overlays
- Tech:
  - data-blur-text + blur-word animation driven by assets/js/app.js and styles.css

## register.php

- Purpose: create account
- Features:
  - name/email/password/role form
  - CAPTCHA validation
  - duplicate email check
  - auto-login post registration
  - welcome email trigger

## login.php

- Purpose: authenticate user
- Features:
  - CAPTCHA validation
  - password_verify check
  - role-based redirect entry
  - forgot password entry point

## forgot_password.php and reset_password.php

- Purpose: reset flow
- Features:
  - token generation and hash storage
  - expiry checks
  - one-time token consumption
  - password change confirmation email

## seeker_dashboard.php

- Purpose: seeker workspace for job discovery
- Features:
  - live job cards
  - search, type, source, mode, level, date and payout filters
  - sort options
  - on-demand fetch by city/company/role
  - company logo loading with fallback
- APIs consumed:
  - api/live_jobs.php
  - api/fetch_jobs.php
  - api/company_logo.php (image proxy)

## provider_dashboard.php

- Purpose: provider talent discovery dashboard
- Features:
  - talent card rendering
  - multi-filter controls (skill, rating, mode, engagement, resume)
  - search and sort
  - direct portfolio/chat/brief actions

## profile.php

- Purpose: seeker profile management
- Features:
  - profile fields and social links
  - avatar upload + scaling
  - resume upload
  - project portfolio list
  - location fields (lat/lng)

## post_job.php

- Purpose: provider job creation
- Features:
  - role details, budget, mode, level, deadline
  - live preview panel
  - provider job statistics

## provider_location.php

- Purpose: provider location + metrics
- Features:
  - company/workplace save
  - use current location flow
  - link to location picker
  - editable hiring metrics

## location_picker.php

- Purpose: pick coordinates visually
- Features:
  - map click pin
  - Nominatim search suggestions
  - reverse geocode for city/address
  - return selected values to target page

## map.php

- Purpose: map-based talent/provider discovery
- Features:
  - map markers with avatar icons
  - searchable/sortable list
  - selected profile detail panel
  - road route with ETA
  - geolocation-based updates

## chat.php

- Purpose: messaging module
- Features:
  - user list and live conversation panel
  - search contacts
  - file attachment sending
  - map deep-link in chat header
  - mock chat mode with localStorage memory
- APIs consumed:
  - api/get_messages.php
  - api/send_message.php

## company_pipeline.php

- Purpose: provider pipeline view
- Features:
  - stage classification based on age
  - pipeline metrics and cards
  - preview links with context

## aggregator.php and peer_post_details.php

- Purpose: peer hiring mini-market
- Features:
  - create sub-contract opportunities
  - market list and counters
  - post details and ownership checks

## job_preview.php and job_details.php

- Purpose: detailed listing/brief pages
- Features:
  - role-aware topbar behavior
  - similar jobs
  - contextual navigation from hub/pipeline

## admin_dashboard.php

- Purpose: admin control panel
- Features:
  - totals (users/jobs/messages/open reports)
  - recent reports list

## notifications.php

- Purpose: user notifications view
- Features:
  - notification stream
  - unread message highlights

## API Reference

All endpoints live in api/.

## GET api/live_jobs.php
- Purpose: return live merged jobs
- Returns: JSON with jobs array
- Used by: seeker dashboard

## POST api/fetch_jobs.php
- Purpose: trigger JobSpy fetch process with constraints
- Input: csrf_token, city/query fields, sites, country
- Returns: JSON message/details
- Used by: seeker dashboard fetch panel

## GET api/get_messages.php
- Purpose: fetch conversation HTML and mark incoming as read
- Input: receiver_id
- Returns: HTML snippets (chat bubbles)
- Used by: chat polling

## POST api/send_message.php
- Purpose: send message and optional attachment
- Input: receiver_id, message, attachment
- Returns: JSON success/error
- Extra: first-message email notification logic

## GET api/company_logo.php
- Purpose: same-origin logo proxy
- Input: domain
- Returns: image/x-icon or JSON error

## GET api/search_freelancers.php
- Purpose: freelancer search feed
- Input: q
- Returns: JSON freelancers

## GET api/jobs_feed.php
- Purpose: filtered job list
- Input: skill/source/location
- Returns: JSON jobs

## POST api/bookmark.php
- Purpose: bookmark a freelancer profile
- Input: freelancer_id
- Returns: text response

## POST api/report_user.php
- Purpose: report a user
- Input: reported_user_id, reason
- Returns: text response

## GET api/aggregate.php
- Purpose: aggregate jobs from configured data sources
- Returns: JSON merged payload

## External APIs and Integrations

## DuckDuckGo icon API (company logo)

- Upstream endpoint pattern:
  - https://icons.duckduckgo.com/ip3/{domain}.ico
- Used through internal proxy:
  - api/company_logo.php?domain=...
- Why proxy is used:
  - same-origin loading
  - controlled timeout/error behavior
  - cache headers from server

## DiceBear avatar API

- Endpoint pattern:
  - https://api.dicebear.com/9.x/{style}/svg?seed=...
- Used as fallback avatar when no uploaded image exists
- Applied in helper and profile/chat/map/provider cards

## Map stack APIs

- Leaflet JS/CSS for map rendering
- CARTO dark tile basemap with OpenStreetMap attribution
- Nominatim search API for place suggestions
- Nominatim reverse API for lat/lng to address/city
- OSRM routing API for road route distance and ETA
- Browser geolocation API for live user coordinates

## JobSpy integration

- Python script in integrations/JobSpy/fetch_jobs_to_json.py
- Populates data/jobs_by_source and combined JSON output
- Triggered manually or through api/fetch_jobs.php workflow

## Database Design

Main tables:
- users
- freelancers
- provider_locations
- jobs
- messages
- password_resets
- bookmarks
- reports
- notifications

Key constraints/indexes:
- unique email in users
- unique freelancer per user (uniq_freelancers_user_id)
- bookmark uniqueness per seeker/freelancer pair
- routing and chat related indexes
- password reset token uniqueness and expiry index

Schema file:
- database.sql

## Security and Validation

- Role guards via includes/auth.php
- Session-based auth checks on restricted pages/APIs
- CAPTCHA challenge on register/login
- Prepared statements for SQL safety
- Upload restrictions:
  - extension + MIME checks
  - blocked executable extensions in chat uploads
  - size caps for attachments
- CSRF token verification on sensitive actions (example: fetch trigger)
- Input pattern validation using regex in critical flows

## Setup Guide (Windows/XAMPP)

Prerequisites:
- XAMPP with Apache + MySQL
- PHP extensions enabled:
  - pdo_mysql
  - gd
  - openssl

Steps:

1. Place project in web root, example:
   - C:/xampp/htdocs/TalentSyncPro
2. Start Apache and MySQL.
3. Create/import DB:
   - open phpMyAdmin
   - import database.sql
4. Confirm DB credentials in includes/db.php:
   - host, dbname, user, password
5. Open app:
   - http://localhost/TalentSyncPro/index.php

## Email Setup (Gmail + App Password)

This project uses PHP mail() through a relay.

Required environment variables:
- MAIL_FROM_ADDRESS
- MAIL_FROM_NAME
- MAIL_REPLY_TO (optional)
- APP_BASE_URL

Windows relay steps:

1. Enable 2-step verification in Gmail.
2. Generate Gmail app password.
3. Configure sendmail relay (XAMPP sendmail) with:
   - smtp.gmail.com
   - port 587
   - TLS enabled
   - Gmail username + app password
4. Configure php.ini to use sendmail binary.
5. Restart Apache.

Email triggers in app:
- registration success
- forgot password reset link
- password changed confirmation
- first-time chat interest alert

## JobSpy Integration

Install dependency (inside Python environment):
- pip install -U python-jobspy

Run manually:
- python integrations/JobSpy/fetch_jobs_to_json.py

Important environment variables:
- JOBSPY_SEARCH_TERM
- JOBSPY_LOCATION
- JOBSPY_LOCATION_LIST
- JOBSPY_SITES
- JOBSPY_RESULTS_WANTED
- JOBSPY_BATCHES

Seeker-triggered flow:
- open seeker dashboard
- fill fetch panel
- click fetch button
- backend api/fetch_jobs.php executes script and refreshes feed

## Testing Flow

Recommended smoke test sequence:

1. Register one provider and one seeker.
2. Provider posts at least one job.
3. Seeker updates profile, uploads avatar and resume.
4. Confirm seeker dashboard loads jobs and filters work.
5. Confirm company logos load (and fallback icon behavior works).
6. Open chat in both users and send text + file.
7. Open map and verify markers, selection, and routing.
8. Run forgot password and confirm email + reset path.
9. Check admin dashboard totals and reports table.

## Troubleshooting

## App opens but no data
- Confirm database.sql imported.
- Verify includes/db.php credentials.
- Confirm MySQL service is running.

## Emails not delivered
- Confirm sendmail relay config and Apache restart.
- Verify MAIL_FROM_ADDRESS and APP_BASE_URL.
- Check relay logs for SMTP auth errors.

## Map route missing
- OSRM service may fail temporarily; app falls back to straight-line estimate.
- Ensure lat/lng values are present in profile/provider location.

## Upload errors
- Check uploads directory permissions.
- Confirm file type and size constraints.
- Confirm PHP upload settings (upload_max_filesize, post_max_size).

## Job fetch fails
- Verify Python and jobspy dependency installation.
- Check api/fetch_jobs.php error details in response.
- Validate network access for target job sources.

## Notes

- This project is educational and production-inspired.
- Respect terms and legal restrictions of external sources.
- Use only permitted scraping and API practices for deployment scenarios.
