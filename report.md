# TalentSync PRO Detailed Implementation Report

Date: 2026-03-28
Project: TalentSync PRO

This version is written in the requested style: page by page, component by component, with effects, API usage, and full technology explanation.

## 1. Exact Example You Asked (Index Page Animation)

In index.php, the hero text One Hub for Jobs Freelancers and Hiring uses both fade and slide-up behavior.

- Component: h1 with data-blur-text
- JS processing: assets/js/app.js splits words into spans with class blur-word
- CSS effect source: assets/css/styles.css
- Effect mechanics:
	- Initial state: opacity 0 + filter blur(10px) + transform translateY(50px)
	- Animated state via blur-word.in and keyframes blurIn
	- Final state: opacity 1 + blur(0) + translateY(0)
- Result: per-word fade-in + upward movement (slide-up feel) with staggered delays

Additional index transitions:

- Top and bottom section fade overlays: classes fade-top and fade-bottom
- Glass hover feel from liquid-glass and liquid-glass-strong
- Hero and section background motion from autoplay videos

## 2. Page-by-Page Components, Effects, APIs, and Tech

## 2.1 index.php

- Components:
	- Fixed glass navbar
	- Hero video section
	- Animated headline
	- Module cards, architecture cards, syllabus cards
	- Multi-section CTA and footer
- Effects/interactions:
	- Fade + slide-up word animation on hero headline
	- Gradient fade separators (top/bottom)
	- Glass visual depth and border highlights
- APIs used:
	- None directly from this page for data
	- Mentions APIs as product capabilities
- Tech used:
	- TailwindCSS CDN
	- Custom CSS utility classes
	- Google Fonts (Instrument Serif, Barlow)
	- HLS.js loaded for HLS-capable videos in shared JS
	- app.js for animation and enhancements

## 2.2 register.php

- Components:
	- Registration form (name, email, password, role)
	- CAPTCHA image block
	- Submit button and auth navigation links
- Effects/interactions:
	- Liquid glass UI styling
	- Validation feedback text rendering
- APIs used:
	- Internal image endpoint captcha.php
	- Server-side email send via sendAppMail after successful registration
- Tech used:
	- PHP form handling + validation + bcrypt hashing
	- Session and cookie
	- PDO insert/select queries

## 2.3 login.php

- Components:
	- Email/password login form
	- CAPTCHA image
	- Forgot password link
- Effects/interactions:
	- Glass-styled controls and notices
- APIs used:
	- captcha.php image
- Tech used:
	- PHP credential verification (password_verify)
	- Session authentication
	- Cookie persistence
	- PDO select query

## 2.4 forgot_password.php

- Components:
	- Email input form
	- status message region
- Effects/interactions:
	- Server-side success/error notices after submit
- APIs used:
	- Email system (PHP mail via sendAppMail)
- Tech used:
	- Token generation random_bytes
	- SHA-256 token hashing
	- Expiry logic with date/time
	- PDO writes to password_resets

## 2.5 reset_password.php

- Components:
	- New password and confirm password form
	- reset result messaging
- Effects/interactions:
	- Invalid/expired link handling
- APIs used:
	- Email notification after password change
- Tech used:
	- Token validation and expiry check
	- bcrypt rehash
	- PDO update queries

## 2.6 dashboard.php

- Components:
	- Role router only
- Effects/interactions:
	- Redirects based on role
- APIs used:
	- None
- Tech used:
	- Session role branching + HTTP redirect

## 2.7 seeker_dashboard.php

- Components:
	- Sidebar filters
	- Header/global search
	- Filter chips and sort controls
	- Dynamic job card grid
	- Fetch-by-city/company control panel
- Effects/interactions:
	- Live filtering on input/change
	- Dynamic card rerender
	- Active filter highlight class toggles
	- Button disable/enable during fetch
	- Logo load fallback icon on error
- APIs used:
	- Internal AJAX:
		- GET api/live_jobs.php
		- POST api/fetch_jobs.php
	- Internal image API:
		- GET api/company_logo.php?domain=...
	- External image source used behind proxy:
		- DuckDuckGo icon service
- Tech used:
	- jQuery selectors/events/AJAX/DOM updates
	- Fetch-style async behavior via jQuery done/fail/always
	- JSON processing
	- Client-side ranking/filter/sort logic

## 2.8 provider_dashboard.php

- Components:
	- Talent cards with avatar, chips, actions
	- Sidebar and top filters
	- Search and sorting controls
- Effects/interactions:
	- Live card filtering/sorting
	- Active sidebar state class switching
	- Click/keyboard card navigation
	- Empty-state render
- APIs used:
	- No external API call from page JS
	- Data is server-rendered from MySQL
- Tech used:
	- jQuery UI interactivity
	- PHP + PDO query results into JS payload
	- DiceBear avatar fallback links from backend

## 2.9 profile.php

- Components:
	- Profile edit form (skills, bio, city, social links, services, tools)
	- Avatar upload control
	- Resume upload control
	- Project portfolio upload and list
- Effects/interactions:
	- Form submission messages
	- Image preview usage via computed avatar URL
- APIs used:
	- Avatar fallback API (DiceBear) when no uploaded image
- Tech used:
	- File upload handling (image/pdf)
	- GD image scaling and PNG alpha preservation
	- MIME checks
	- PDO updates/inserts/selects

## 2.10 provider_location.php

- Components:
	- Company/workplace location form
	- Hiring metrics form
	- Map picker navigation button
	- Use current location button
- Effects/interactions:
	- Location hint updates in real time
	- Prefill from map return params
- APIs used:
	- Nominatim reverse geocoding in shared JS (app.js)
- Tech used:
	- Geolocation API
	- Fetch API
	- PDO upsert logic

## 2.11 location_picker.php

- Components:
	- Leaflet map canvas
	- Search input + live suggestion list
	- Selected coordinates/address summary
	- Confirm/clear/current-location buttons
- Effects/interactions:
	- Click-to-drop pin
	- Suggestion dropdown show/hide
	- Marker move + map recenter
- APIs used:
	- Leaflet library
	- Basemap tiles (CARTO/OpenStreetMap attribution)
	- Nominatim search API
	- Nominatim reverse geocoding API
- Tech used:
	- Vanilla JS event listeners
	- Fetch API promises
	- URLSearchParams return routing

## 2.12 map.php

- Components:
	- Talent/provider list panel
	- Sort/search controls
	- Route banner + selected profile summary
	- Leaflet interactive map with custom markers
- Effects/interactions:
	- Marker click selects profile
	- Route polyline draw and fit bounds
	- Fallback dashed line if routing unavailable
	- Live list filtering and sorting
- APIs used:
	- Leaflet map library
	- CARTO/OpenStreetMap dark tile basemap
	- OSRM routing API (road distance and ETA)
	- Browser geolocation API
- Tech used:
	- Haversine distance fallback
	- Promise-based routing flow
	- DOM rendering from JS data payload
	- DiceBear avatar fallback links from backend

## 2.13 chat.php

- Components:
	- User list panel
	- Conversation box
	- Chat header with profile/map links
	- Message form with file attachment
	- Mock suggestion chips and mock bot mode
- Effects/interactions:
	- User selection highlights active card
	- Live search in chat user list
	- Attachment meta strip show/hide
	- Auto-scroll to latest message
	- Polling refresh every 2 seconds
- APIs used:
	- Internal AJAX:
		- GET api/get_messages.php
		- POST api/send_message.php (FormData with attachment)
	- Map deep-linking into map.php with focus params
	- DiceBear avatar API fallback links generated backend-side
- Tech used:
	- jQuery events + DOM updates
	- localStorage for mock conversation persistence
	- JSON parsing of error payloads

## 2.14 post_job.php

- Components:
	- Provider job posting form
	- Live preview panel
	- Stats panel
- Effects/interactions:
	- Real-time preview update on input/change
	- Form status banners
- APIs used:
	- None external
- Tech used:
	- PHP form processing + PDO inserts
	- Client-side preview logic in JS

## 2.15 company_pipeline.php

- Components:
	- Stage-based job pipeline view
	- Metrics cards
	- Job cards with preview links
- Effects/interactions:
	- UI filters/summaries based on computed stage
- APIs used:
	- None external
- Tech used:
	- PHP stage classification logic
	- SQL reads and JSON payload generation for UI

## 2.16 job_preview.php

- Components:
	- Unified job/talent preview content
	- Role-aware topbar and navigation
	- Similar jobs section
- Effects/interactions:
	- Adaptive routing based on from context
- APIs used:
	- Reads local JSON and URL-based payloads
- Tech used:
	- PHP rendering + conditional layout logic

## 2.17 job_details.php

- Components:
	- Hero details section
	- Responsibility/requirement lists
	- Similar jobs carousel-like horizontal list
- Effects/interactions:
	- Sticky summary band
	- Scroll-lite visual scrollbar style
- APIs used:
	- None external
- Tech used:
	- Server-side detail and related query rendering

## 2.18 aggregator.php

- Components:
	- Peer hiring post form
	- Open market list
	- My posts section
- Effects/interactions:
	- Form validation and status messaging
	- View/click counters update
- APIs used:
	- Internal DB-driven aggregation and data reads
- Tech used:
	- CSRF token checks
	- PDO insert/select/update

## 2.19 peer_post_details.php

- Components:
	- Detailed post viewer for owner
- Effects/interactions:
	- Access enforcement by poster identity
- APIs used:
	- None external
- Tech used:
	- PDO secure select + ownership checks

## 2.20 notifications.php

- Components:
	- Notification feed
	- Unread messages feed
- Effects/interactions:
	- Read/unread visual separation
- APIs used:
	- None external
- Tech used:
	- SQL join and filter logic

## 2.21 admin_dashboard.php

- Components:
	- KPI cards
	- Reports moderation table
- Effects/interactions:
	- Dashboard stats overview layout
- APIs used:
	- None external
- Tech used:
	- SQL aggregates and joins

## 2.22 captcha.php

- Components:
	- Dynamic CAPTCHA image response endpoint
- Effects/interactions:
	- Numeric code generated per request and stored in session
- APIs used:
	- None external
- Tech used:
	- PHP GD (imagecreate, imagecolorallocate, imagestring, imagepng)
	- SVG fallback when GD unavailable

## 3. Internal API-by-API Explanation

## 3.1 api/live_jobs.php

- Purpose: return merged live job list for seeker dashboard
- Input: GET
- Output: JSON jobs array
- Used by: seeker_dashboard.php

## 3.2 api/fetch_jobs.php

- Purpose: trigger JobSpy Python fetch for requested city/company/role
- Input: POST (csrf_token, city, query_type, field, job_type, country, sites)
- Output: JSON status and details
- Used by: seeker_dashboard.php

## 3.3 api/get_messages.php

- Purpose: fetch chat thread and mark incoming messages as read
- Input: GET receiver_id
- Output: rendered HTML message bubbles
- Used by: chat.php polling

## 3.4 api/send_message.php

- Purpose: store message and optional attachment
- Input: POST receiver_id, message, attachment
- Output: JSON success/error
- Used by: chat.php
- Extra: sends first-conversation interest email alert

## 3.5 api/company_logo.php

- Purpose: same-origin proxy for company logos
- Input: GET domain
- Output: image/x-icon or JSON error
- Used by: seeker_dashboard.php logo rendering

## 3.6 api/search_freelancers.php

- Purpose: search freelancers by name/skill/experience
- Input: GET q
- Output: JSON freelancers array

## 3.7 api/jobs_feed.php

- Purpose: filtered jobs feed from DB
- Input: GET skill/source/location
- Output: JSON jobs array

## 3.8 api/bookmark.php

- Purpose: bookmark freelancer profile
- Input: POST freelancer_id
- Output: text response

## 3.9 api/report_user.php

- Purpose: report suspicious user
- Input: POST reported_user_id, reason
- Output: text response

## 3.10 api/aggregate.php

- Purpose: aggregate jobs from local JSON and remote source
- Input: GET/none
- Output: JSON merged data

## 4. External APIs Used and How

## 4.1 DuckDuckGo Icon API (Company Logo)

- Endpoint pattern: https://icons.duckduckgo.com/ip3/domain.ico
- Why used: fetch company favicon quickly
- Where used:
	- seeker_dashboard.php computes domain and requests logo via api/company_logo.php proxy
	- api/company_logo.php fetches icon server-side
- Benefit:
	- avoids CORS/canvas issues on client and allows controlled fallback

## 4.2 DiceBear Avatar API (Fallback Avatar)

- Endpoint pattern: https://api.dicebear.com/9.x/style/svg?seed=...
- Why used: instant profile avatar when user has no uploaded image
- Where used:
	- includes/functions.php avatarUrl
	- chat.php
	- map.php
	- provider_dashboard.php
	- freelancer_portfolio.php

## 4.3 Map APIs

Map stack is multi-part, not a single API:

- Leaflet JS/CSS:
	- rendering interactive maps in map.php and location_picker.php
- Basemap tiles:
	- CARTO dark tile service with OpenStreetMap attribution
- Nominatim API:
	- forward search for place suggestions
	- reverse geocoding lat/lng to city/address
- OSRM API:
	- road routing distance and ETA in map.php
- Browser Geolocation API:
	- capture device coordinates for user-centric routing

## 5. Every Technology Used and Why

## 5.1 Frontend/UI Technologies

- HTML5: page structure and semantic layout
- CSS3: custom visual system and animations
- TailwindCSS CDN: utility-first rapid layout and styling
- Custom CSS (assets/css/styles.css):
	- liquid glass theme
	- blurIn animation
	- fades and custom control skins
- Google Fonts: Instrument Serif, Barlow typography
- Material Symbols icon font: UI iconography

## 5.2 Frontend Programming Technologies

- JavaScript (vanilla):
	- map interactions
	- location capture
	- animation setup
- jQuery 3.7.1:
	- selectors, events, DOM updates, AJAX
	- dashboards and chat interactivity
- Fetch API:
	- map geocoding/routing operations
- FormData:
	- chat file upload submission
- localStorage:
	- mock chat persistence
- IntersectionObserver:
	- trigger hero word animation when visible
- HLS.js:
	- fallback streaming support for HLS video sources

## 5.3 Backend Technologies

- PHP 8:
	- main server-side language
	- routing, rendering, business logic, validation
- PDO:
	- secure database access with prepared statements
- Sessions:
	- authentication and access control
- Cookies:
	- lightweight user state persistence
- Regex:
	- input and filename validation
- mail function:
	- transactional email notifications via sendAppMail
- GD library:
	- CAPTCHA generation and image processing
- finfo MIME detection:
	- upload content-type validation

## 5.4 Database and Data Technologies

- MySQL:
	- relational storage for users/jobs/messages/etc.
- SQL indexes and constraints:
	- performance and data consistency
- JSON:
	- API payload format and local feed cache files

## 5.5 Integration/Automation Technologies

- JobSpy Python integration:
	- external jobs fetching into JSON cache
- XAMPP/Apache runtime:
	- local execution environment for PHP + MySQL stack

## 6. Module Mapping (3, 4, 5, 6) with Practical Evidence

## 6.1 Module 3 jQuery

- Selectors, events, DOM manipulation, traversal: seeker_dashboard.php, provider_dashboard.php, chat.php
- JSON and AJAX: api/live_jobs.php + api/fetch_jobs.php + chat APIs
- Special effects: class-based transitions and dynamic UI state changes

## 6.2 Module 4 PHP Core

- Variables, control flow, functions, arrays, include/require: all major pages and includes
- Regex, validation, error handling: auth, fetch, messaging, upload handlers
- Form handling and date/time utilities: auth/profile/posting/reset workflows

## 6.3 Module 5 Advanced PHP

- File upload and handling: profile.php and api/send_message.php
- Session authentication and cookies: includes/auth.php, register.php, login.php
- Graphics and scaling: captcha.php and profile.php GD pipeline
- Mail function: includes/functions.php and email-trigger pages

## 6.4 Module 6 PHP with MySQL

- DB schema, indexes, constraints: database.sql
- PDO connection and queries: includes/db.php and all data pages/APIs
- Form-to-database persistence: registration, login, profile, jobs, chat, bookmarks, reports, resets

## 7. Final Note

This report now includes:

- Page-by-page component listing
- Effect-by-effect behavior (including the exact index hero fade + slide-up)
- Internal and external API usage explanation
- DuckDuckGo logo API, DiceBear avatar API, and full map API stack explanation
- Complete technology stack explanation across frontend, backend, database, and integrations