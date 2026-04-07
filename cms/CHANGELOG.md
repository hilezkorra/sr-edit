# SR Edit — Changelog
# Save to: cms_data/CHANGELOG.md
# This document tracks all versions, implementations, bugs, and planned features.
# Used by the development team and AI assistant to understand current state.

---

## VERSION HISTORY

---

## v0.2.0 — Planned (next release)

### New Features Planned
- [ ] Edit / Interactive / Interactive-Edit mode toggle in preview bar
      Implementation: re-render iframe with/without CMS intercept script
      Three states: Edit (full CMS), Interactive-Edit (CMS only on [data-cms] elements),
      Preview (no CMS injection — fully interactive for testing popups/hovers/animations)
- [ ] data-movable drag-to-reorder sections
      Implementation: detect [data-movable] blocks in iframe, inject drag handles,
      postMessage with new order, DOMParser reorders nodes in state.currentHtml
- [ ] Theme editor panel (CSS variables editor)
      Implementation: read css/theme.css via new API action read_css_vars,
      parse :root {} block into labeled controls per token category,
      component-aware grouping: Buttons / Navigation / Headings / Cards / Sections
      Write changes back to theme.css via write_file
- [ ] Global element sync (data-cms-global attributes)
      Implementation: when business name / tagline changes in Settings → Business Info,
      scan all HTML files for data-cms-global="business-name" and update all instances
      API: new sync_global action that takes key+value and updates all files
- [ ] Typed field editing (data-cms-type)
      phone: edits both visible text AND tel: href simultaneously
      email: edits both visible text AND mailto: href
      price: numeric field with currency formatting
      cta: text + URL editor in one popup
      address: multi-line textarea
- [ ] Social links auto-sync (data-cms-social attributes)
      When Settings → Social Media saved, update all data-cms-social="platform" hrefs
- [ ] Visibility toggle panel for data-cms-toggle blocks
      Show/hide seasonal content with a toggle switch in elements panel
- [ ] Module stub activation
      When a module is marked active, inject live rendered content into
      data-cms-module="moduleid" stubs across the site
- [ ] Opening hours auto-sync
      Settings → Hours saves to cms_data/settings.json
      API syncs to all data-cms-module="hours" elements across pages
- [ ] Google Maps auto-inject
      Read data-cms-address attribute, generate Maps iframe, inject into
      data-cms-module="map" stubs
- [ ] Studio module (AI site generator)
      Panel with generation prompt, brief input, Claude API call,
      parse response into files, write to site root via API
      Load studio-prompt.md from cms_data/ as default prompt template
- [ ] Privacy policy generator (module-aware)
      Read active modules from cms_data/settings.json
      Generate datenschutz.html with only relevant sections
      Sections: hosting, contact form, analytics, maps, youtube, newsletter,
      booking, recaptcha, cookies
- [ ] Page manager
      Create new pages, delete pages, rename pages from sidebar
      New page wizard: choose template, name, add to nav automatically
- [ ] Navigation manager
      Visual editor for data-cms-nav contents
      Add/remove/reorder nav links, link to any page or module URL
- [ ] Image alt text editor
      Detect data-cms-alt on img elements, show dedicated accessibility field
- [ ] Cookie-category script management
      data-cms-cookie-category scripts blocked until consent
      Consent banner integrated with cookie settings

### Bugs to Fix in v0.2.0
- [ ] BUG-004: Mobile drawer still uses old .module-item class for Settings link
      Line ~1972: onclick="openSettings();closeDrawer()" inside .module-item wrapper
      Fix: replace with .nav-item class matching rest of drawer nav
- [ ] BUG-005: Module cards in All Modules overview clickable but no visual feedback
      on hover for "SOON" status modules before opening panel
      Fix: add cursor:pointer and slight opacity change, show tooltip "Preview UI"
- [ ] BUG-006: Settings screen back button doesn't restore previous module state
      After openSettings() → closeSettings(), main-area shows but active module
      highlight in sidebar may be lost
      Fix: store state.prevModule before openSettings(), restore on closeSettings()
- [ ] BUG-007: theme.css path hardcoded assumption
      CMS assumes theme.css is always at /css/theme.css
      Fix: add theme_css_path to cms_data/settings.json, configurable

---

## v0.1.1 — Current (uploaded 2025)

### What's In This Version

#### Core Editor (LIVE)
- [x] Login system with bcrypt password hashing
- [x] Session-based authentication
- [x] Settings persisted to cms_data/settings.json
- [x] Display name and username changeable from settings
- [x] Password change with current password verification
- [x] Auto-logout: session destroy on ?logout=1
- [x] Light/dark theme toggle, persisted to localStorage
- [x] Theme applied before first paint (no flash)

#### Page Editor (LIVE)
- [x] File tree scanning — reads all .html files from parent directory
- [x] Directory traversal prevention in sanitizePath()
- [x] File loaded into iframe via srcdoc
- [x] CMS intercept script injected into iframe <head>
- [x] Click any [data-cms] element → edit popup
- [x] Quill 2.0.3 rich text editor for block elements (p, h1-h6, li, etc.)
- [x] Plain text input for inline elements (span, a, button, etc.)
- [x] Attribute editor tab: view/edit/add/remove all HTML attributes
- [x] data-cms attribute protected from deletion
- [x] Apply edit → postMessage to iframe → live preview update
- [x] Edit updates state.currentHtml via DOMParser
- [x] Ctrl+Enter applies edit from Quill editor
- [x] Escape closes edit popup
- [x] Edit popup draggable by header
- [x] Save writes to disk via api.php write_file
- [x] Auto-backup before every save (max 10 backups per file)
- [x] Discard changes → revert to last saved version
- [x] Dirty state tracking — Save button enabled only when changes exist
- [x] Undo/redo (50 step history, stored as HTML snapshots with labels)
- [x] Ctrl+Z / Ctrl+Y / Ctrl+Shift+Z keyboard shortcuts
- [x] Ctrl+S saves
- [x] Undo/redo labels shown in button tooltips and status bar
- [x] Viewport switcher: Desktop / Tablet (768px) / Mobile (375px)
- [x] Refresh preview button
- [x] Left sidebar collapse/expand (animated, persisted to localStorage)
- [x] Right elements panel collapse/expand (animated, persisted)
- [x] Elements panel search filter
- [x] Elements panel shows element tag, data-cms ID, and text preview
- [x] Click element in panel → scroll to it in iframe + open edit popup
- [x] Status bar: current file, dirty indicator, file size (KB), undo count
- [x] Toast notifications for save, error, undo, redo, auto-tag

#### Auto-Tag (LIVE)
- [x] Auto-Tag button injects data-cms attributes into all text elements
- [x] Uses PHP DOMDocument for robust injection
- [x] Regex fallback if DOMDocument fails
- [x] Auto-tagged page immediately loaded into editor

#### Mobile Support (LIVE)
- [x] Hamburger menu replaces sidebar on screens < 1035px
- [x] Mobile drawer slides in from left
- [x] Drawer shows file tree + module links + quick actions
- [x] Tap hint banner shown after file loads on mobile
- [x] "Don't show again" persisted to localStorage

#### New Sidebar Navigation (LIVE — added in our session)
- [x] Categorized nav groups: Editor / Business Modules / Communication /
      Analytics & SEO / Tools
- [x] All 24 modules visible in sidebar with LIVE/SOON badges
- [x] File tree shown in collapsible section at top of sidebar
- [x] Settings and License pinned to bottom of sidebar
- [x] Active module highlighted with accent bar

#### All Modules Overview (LIVE — added in our session)
- [x] Grid of all 24 modules with icon, name, category, description
- [x] LIVE/SOON badge per module
- [x] Clicking any module navigates to its panel

#### Coming Soon Module Panels (LIVE UI — added in our session)
All 23 coming-soon modules have placeholder UIs with:
- [x] Coming Soon banner explaining what the module does
- [x] Realistic disabled form fields showing what inputs will be available
- [x] Placeholder tables, calendars, stats, charts as appropriate

Modules with placeholder UIs:
- [x] Media Library — upload area, file grid, search
- [x] HTML Snippets — snippet list, insert buttons
- [x] Change History — version table with restore actions
- [x] Restaurant Menu — category sidebar + full item form with allergens
- [x] Appointments — calendar grid + pending requests + quick actions
- [x] Product Catalogue — stats + table with filter
- [x] Blog & News — post table with status filters
- [x] Events — add event form + upcoming list
- [x] Team Members — add member form + team grid
- [x] Testimonials — add review form + published list with stars
- [x] FAQ Manager — add FAQ form + entry list
- [x] Job Listings — full job posting form
- [x] Portfolio / Gallery — add project form + thumbnail grid
- [x] Pricing Tables — plan builder form
- [x] Form Builder — field palette + drop canvas
- [x] Form Inbox — stats + submission table
- [x] Newsletter — Brevo connection + campaign archive
- [x] SEO Analyzer — check results with progress bars
- [x] Analytics — bar chart + stats + top pages + device breakdown
- [x] Redirects — add redirect form + active redirects table
- [x] Export & Deploy — options + FTP settings
- [x] Backup & Restore — backup history + restore from file
- [x] Accessibility Checker — issue list with fix buttons

#### Settings Screen (LIVE — added in our session)
Full separate settings screen with 13 settings pages:
- [x] Account & Login — display name, username
- [x] Security — IP whitelist, brute-force protection, 2FA stub
- [x] Business Info — name, type, tagline, VAT, address, phone, email, language, currency
- [x] Design & Branding — color pickers per token, font selectors, logo upload
- [x] Opening Hours — visual day grid with Closed toggles, timezone, auto-banner toggle
- [x] Social Media — all major platforms + WhatsApp
- [x] SEO & Meta — default meta description, OG image, indexing toggles, sitemap
- [x] Email (SMTP) — host, port, credentials, encryption, from name/email, test button
- [x] Analytics — GA4 ID, enable toggle, built-in cookieless analytics config
- [x] API Keys — Brevo, reCAPTCHA v3, chat widget embed code
- [x] Maintenance Mode — enable/disable, custom message, expected back time
- [x] Advanced — auto-save draft, confirm on switch, backup count, custom head/body HTML
- [x] Feedback — bug report, feature request, about/version info

#### Security (LIVE)
- [x] CSRF token per session (bin2hex random_bytes 32)
- [x] CSRF verified on all state-changing API actions
- [x] Read-only actions (list_files, read_file, check_license) exempt from CSRF
- [x] write_file enforces .html extension — cannot write PHP or other files
- [x] sanitizePath() strips directory traversal attempts
- [x] DOMDocument saveHTML() wrapper tags stripped correctly
- [x] hash_equals() used for CSRF comparison (timing-safe)

#### License (LIVE — stub)
- [x] License check on CMS load
- [x] License key stored in cms_data/license.json
- [x] Monthly re-verification logic
- [x] Demo mode: SREDIT-* prefix keys accepted
- [x] promptLicenseKey() dialog
- [x] Masked key display

#### SVG Logos (LIVE — redesigned in our session)
- [x] sr-edit-logo-dark.svg — clean pen nib icon + wordmark, dark variant
- [x] sr-edit-logo-light.svg — same, light variant
- [x] sr-edit-favicon.svg — 32×32 pen nib icon
- [x] Logo click animation (pop/bounce)
- [x] Light/dark logo switching via CSS variables

#### API (LIVE)
- [x] list_files — recursive HTML file scanner, excludes cms/ and common dirs
- [x] read_file — returns file content, path-sanitized
- [x] write_file — writes HTML only, backup before write, checks writability
- [x] inject_attrs — DOMDocument auto-tagger with regex fallback
- [x] check_license — reads license.json, monthly re-verify
- [x] activate_license — validates and stores key

#### Studio Prompt (LIVE — added in our session)
- [x] studio-prompt.md saved to cms/
- [x] Documents all data-* attributes the CMS recognises
- [x] Covers: data-cms, data-movable, data-cms-block, data-cms-type,
      data-cms-global, data-cms-social, data-cms-image, data-cms-alt,
      data-cms-toggle, data-cms-module, data-cms-nav, data-cms-footer-nav,
      data-cms-cookie-category
- [x] Full CSS architecture spec (theme.css / styles.css separation)
- [x] Legal pages spec (impressum.html, datenschutz.html, module-aware)
- [x] Checklist of all required attributes before output

### Known Bugs in v0.1.1
- [x] BUG-001 FIXED: PlateCMS naming throughout (renamed to SR Edit / SREDIT-)
- [x] BUG-002 FIXED: write_file allowed writing non-.html files
- [x] BUG-003 FIXED: DOMDocument saveHTML() added <html><body> wrappers to fragment output
- [!] BUG-004 OPEN: Mobile drawer Settings link uses old .module-item class
- [!] BUG-005 OPEN: Module Manager cards from old codebase showed Active/Disabled
      toggle that didn't correspond to real functionality (screenshot shows this).
      STATUS: Fixed in current uploaded version — new renderModulesGrid() uses
      mod-card that opens module panel instead of toggling. Screenshot is from
      previous build. Confirm by loading uploaded file.
- [!] BUG-006 OPEN: Settings back button loses sidebar active state
- [!] BUG-007 OPEN: theme.css path hardcoded
- [x] BUG-013 FIXED: Can't click anything / entire UI frozen after closing sidebar
      ROOT CAUSE: sidebar-toggle button (‹ tab) was inside preview-wrap which has
        overflow:hidden. When sidebar collapsed, preview-wrap expanded to fill space
        but overflow:hidden clipped the absolutely-positioned toggle button, making
        it invisible and unclickable. With no way to re-open the sidebar, the whole
        UI appeared frozen. localStorage also stored the collapsed state, reproducing
        the bug on every page reload.
      FIX 1: Moved sidebar-toggle button from inside preview-wrap to editor-panel
        (its parent, which has position:relative). No longer clipped by overflow:hidden.
      FIX 2: Added one-time localStorage migration (sr-sidebar-fix-v2) that resets
        any stuck sr-left-panel='0' value from previous buggy builds. Runs once
        per browser, transparently. Affected users auto-recover on next load.

- [x] BUG-008 FIXED: Left sidebar couldn't be closed/toggled at or below 1035px
      Root cause 1: Duplicate .sidebar CSS block overwriting original
      Root cause 2: restoreSidebarState() called toggleLeftSidebar() on mobile
        where sidebar is hidden by @media rule, causing broken state
      Root cause 3: localStorage stored '0' from desktop collapse, triggered
        on mobile load causing the sidebar toggle to misfire
      Fix: Added window.innerWidth <= 1035 guard to toggleLeftSidebar() and
        restoreSidebarState(). Added localStorage.removeItem() on mobile load.
        Raised sidebar-toggle z-index from 10 to 50.
- [x] BUG-009 FIXED: HTML pages not listed in file tree after sidebar bug
      Root cause: When sidebar collapsed on mobile via broken state, the
        iframe pointer-events propagation blocked API calls visually
      Fix: Same as BUG-008 — sidebar state fix resolved this
- [x] BUG-010 FIXED: Page elements unclickable after sidebar close
      Root cause: Collapsed sidebar's pointer-events:none was not the issue —
        the iframe was still receiving clicks. The visual appearance of nothing
        happening was because the edit popup was rendering behind the collapsed
        sidebar overlay state. Resolved with sidebar fix.
- [x] BUG-011 FIXED: cms-hover-highlight class leaking into saved HTML
      When user saves after clicking elements, the CMS adds cms-hover-highlight
        and cms-selected-highlight classes to the live iframe DOM. These were
        being captured by DOMParser into state.currentHtml and written to disk.
      Fix: Strip cms-*-highlight classes from content before api write_file call.
- [x] BUG-012 FIXED: Attribute editor showing cms-hover-highlight in class field
      (See screenshot — class field shows "cms-hover-highlight")
      Fix: Strip cms-* classes from attrs object in cms_click message handler
        before passing to openEditPopup().


---

## v0.1.0-alpha — Initial Build

### Implemented
- [x] Single-file PHP CMS (siterefresh-admin.php + api.php)
- [x] Session login with bcrypt
- [x] Click-to-edit iframe preview
- [x] Quill 1.3.7 rich text editor (upgraded to 2.0.3 in v0.1.1)
- [x] File tree sidebar
- [x] Save with auto-backup
- [x] Undo/redo stack
- [x] Basic settings (password change only)
- [x] Module manager (old — toggled Active/Disabled state with no panel navigation)
- [x] License panel (stub)
- [x] Dark/light theme
- [x] Mobile drawer

### Bugs Fixed Moving to v0.1.1
- [x] Quill 1.3.7 → 2.0.3 (API changes: root.innerHTML → clipboard.dangerouslyPasteHTML,
      getSemanticHTML(), toolbar size → header)
- [x] keyboard.addBinding removed (Quill 2 changed this API)
- [x] PlateCMS naming replaced with SR Edit / SREDIT-
- [x] write_file security hole (extension check added)
- [x] DOMDocument saveHTML() wrapper stripping
- [x] CSRF token added to all state-changing actions
- [x] SVG logos redesigned
- [x] Module manager replaced — old toggle replaced with panel navigation

---

## PLANNED MODULES — Implementation Priority Order

### Priority 1 — Core editor features (prerequisite for everything else)
1. Theme editor (CSS variables from theme.css) — v0.2.0
2. Edit/Interactive/Preview mode toggle — v0.2.0
3. data-movable drag reorder — v0.2.0
4. Global element sync (data-cms-global) — v0.2.0
5. Typed field editing (data-cms-type) — v0.2.0

### Priority 2 — High-value business modules
6. Media Library — v0.2.0
7. Restaurant Menu — v0.2.0
8. Appointments / Booking — v0.3.0
9. Form Builder + Inbox — v0.3.0
10. Blog & News — v0.3.0

### Priority 3 — SEO and analytics
11. SEO Analyzer — v0.3.0
12. Analytics dashboard — v0.3.0
13. Privacy policy generator (module-aware) — v0.3.0
14. Sitemap generator — v0.3.0
15. Redirects manager — v0.3.0

### Priority 4 — Content modules
16. Team Members — v0.4.0
17. Testimonials — v0.4.0
18. Events — v0.4.0
19. FAQ Manager — v0.4.0
20. Portfolio / Gallery — v0.4.0
21. Job Listings — v0.4.0
22. Pricing Tables — v0.4.0

### Priority 5 — Advanced features
23. Studio module (AI generator in-CMS) — v0.4.0
24. Export & Deploy — v0.4.0
25. Backup & Restore (full-site) — v0.4.0
26. Newsletter (Brevo integration) — v0.5.0
27. Multi-user support — v0.5.0
28. White-label mode — v0.5.0

---

## FEATURE IDEAS BACKLOG (not yet scheduled)

From the 200 improvement ideas session:
- Image resize/WebP conversion on upload (PHP GD)
- Image cropper (Cropper.js)
- Bulk image optimizer
- SVG color editor
- Find and replace across all pages
- Spell check (LanguageTool free API)
- Readability score (Flesch-Kincaid)
- WCAG contrast checker on color change
- Auto-save draft to localStorage
- Keyboard shortcut cheat sheet (press ?)
- Page title editor quick button
- Favicon manager
- Head tag manager (meta, link, custom scripts)
- Table editor
- Global search across all pages
- Bulk text export/import (JSON)
- Version diff view
- Zoom control for preview (50-150%)
- QR code generator for menu
- Countdown timer module
- Live chat widget integration (Tawk.to/Crisp embed)
- Membership/access module (page passwords)
- Multi-site manager
- Currency converter widget
- Weather widget (Open-Meteo, no API key)
- Legal page generator wizard (impressum + datenschutz from form)
