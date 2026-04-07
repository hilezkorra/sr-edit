# SR Edit — AI Assistant Context
# File: cms/AI_CONTEXT.md
# 
# INSTRUCTIONS FOR AI ASSISTANT
# When starting a new chat about SR Edit, paste this file's contents
# (or upload it) so the assistant knows the full context immediately.
# Then upload the current siterefresh-admin.php for code changes.
# ─────────────────────────────────────────────────────────────────

## WHAT IS SR EDIT

SR Edit is a self-hosted, single-file PHP CMS. Drop the /cms/ folder into
any HTML website and go to /cms/siterefresh-admin.php to manage it.

Owner: Igor Pundzin, Halle (Saale), Germany
Works at: alfahosting GmbH (group.one) — SR Edit is a SEPARATE hobby project
Hosted at: siterefresh.eu
Purpose: Build 365 free websites for small businesses (SiteRefresh project)
Legal: Non-commercial hobby, Impressum under brother Sasa Pecikoza

## PROJECT FILES

    your-site/
    ├── index.html, about.html, etc.  ← HTML files CMS manages
    └── cms/
        ├── siterefresh-admin.php     ← MAIN FILE (entire CMS frontend + auth)
        ├── api.php                   ← Backend API (file ops, license, CSRF)
        ├── studio-prompt.md          ← Site generation prompt for Claude
        ├── CHANGELOG.md              ← Version history, bugs, planned features
        ├── AI_CONTEXT.md             ← This file
        ├── sr-edit-logo-dark.svg
        ├── sr-edit-logo-light.svg
        ├── sr-edit-favicon.svg
        └── cms_data/                 ← Created automatically
            ├── settings.json         ← All CMS settings + password hash
            ├── license.json          ← License key + verification state
            ├── analytics.json        ← Built-in analytics data
            └── backups/              ← Auto-backups before every save

## CURRENT VERSION: v0.1.1

Access URL: https://yourdomain.de/cms/siterefresh-admin.php
Default login: admin / admin123 (change immediately in Settings)

## ARCHITECTURE

**Auth:** PHP session + bcrypt. Settings persisted to cms_data/settings.json.
CSRF token per session (bin2hex random_bytes 32), verified on all write actions.

**Editor:** srcdoc iframe. CMS intercept script injected into <head>.
Clicking [data-cms] elements → postMessage to parent → edit popup (Quill 2.0.3).
Apply → postMessage back → live DOM update → DOMParser updates state.currentHtml.
Save → api.php write_file → backup then disk write.

**API (api.php actions):**
- list_files    — recursive HTML scanner, read-only, no CSRF needed
- read_file     — returns file content, read-only
- write_file    — .html only, pre-write backup, CSRF required
- inject_attrs  — DOMDocument auto-tagger with regex fallback, CSRF required
- check_license — reads license.json, monthly re-verify, read-only
- activate_license — stores key, CSRF required

**Security:** sanitizePath() strips traversal. write_file enforces .html extension.
hash_equals() for CSRF comparison. DOMDocument saveHTML() wrapper stripped.

**Storage:** All data in cms_data/ JSON files. No database. No migrations.
Backup system: max 10 backups per file in cms_data/backups/.

## DATA-* ATTRIBUTES (what generated HTML must have)

All of these are defined in detail in studio-prompt.md.

| Attribute | Purpose |
|---|---|
| data-cms="tag-N" | Editable text element |
| data-cms-type="phone\|email\|price\|cta\|address\|date" | Typed field (special edit popup) |
| data-cms-global="business-name\|tagline\|copyright-year" | Site-wide sync |
| data-cms-social="instagram\|facebook\|..." | Social link auto-sync from Settings |
| data-cms-image="key" + data-placeholder | Image placeholder (Media Library replaces) |
| data-cms-alt | Alt text editable separately |
| data-cms-toggle="key" + data-cms-label | Visibility toggle (show/hide in elements panel) |
| data-cms-block="name" + data-movable | Movable section (drag reorder) |
| data-cms-module="menu\|booking\|blog\|events\|testimonials\|hours\|map" | Module stub |
| data-cms-nav | Navigation element (CMS auto-injects module links here) |
| data-cms-footer-nav | Footer nav (same) |
| data-cms-cookie-category="analytics\|marketing\|functional" | Script consent gating |

## CSS ARCHITECTURE (what generated CSS must do)

Two files only:
- css/theme.css — ONLY :root {} with CSS custom properties
  The CMS reads this to build the theme editor.
  Organized into groups with comment labels:
  Colors, Buttons (--btn-primary-*), Navigation (--nav-*),
  Headings (--h1-size, --h2-size etc.), Cards, Forms, Footer, Shapes, Shadows
- css/styles.css — all rules, uses ONLY var(--...) for theme values

External CSS files load correctly in the iframe (allow-same-origin).
Use root-relative paths in href attributes (/css/styles.css not css/styles.css).

## IMPLEMENTED FEATURES (confirmed working)

- Session login, bcrypt, CSRF tokens on all write actions
- Click-to-edit with Quill 2.0.3 (rich text for block elements, plain input for inline)
- Attribute editor tab (all HTML attributes editable — links/emails/phones work this way)
- Undo/redo (50 steps), Ctrl+Z/Y/S keyboard shortcuts
- Auto-backup before every save (max 10 per file)
- Dirty state, save/discard buttons
- Auto-tag (data-cms injection via DOMDocument + regex fallback)
- Elements panel with search and scroll-to
- Viewport switcher (desktop/tablet 768px/mobile 375px)
- Left sidebar collapse/expand (animated, localStorage persisted)
- Right elements panel collapse/expand
- Mobile drawer (hamburger, <1035px breakpoint)
- Dark/light theme toggle
- All 24 module panels with placeholder UIs (coming soon)
- Full settings screen (13 pages: account, security, business, design,
  hours, social, SEO, email, analytics, API keys, maintenance, advanced, feedback)
- License check (SREDIT-* demo keys, monthly re-verify stub)
- Toast notifications, status bar

## KNOWN BUGS (open)

- BUG-007: theme.css path hardcoded to /css/theme.css
  Fix: add theme_css_path to settings.json, configurable in Advanced settings

## PLANNED NEXT (v0.2.0 priority order)

1. Edit/Interactive/Preview mode toggle
   - Three states: Edit (CMS injected), Interactive-Edit (CMS only on [data-cms]),
     Preview (no injection — fully interactive, popups work)
   - UI: toggle in preview bar
   - Implementation: renderPreview() checks state.editorMode

2. Theme editor (CSS variables from theme.css)
   - New API action: read_theme — reads /css/theme.css, parses :root {}
   - Parse into labeled controls grouped by token prefix
   - Write back via write_file to css/theme.css
   - Component groups: Buttons / Navigation / Headings / Cards / Section / Footer

3. data-movable drag-to-reorder
   - Detect [data-movable] blocks in iframe
   - Inject drag handles via CMS script
   - postMessage with new order → DOMParser reorders → save

4. Global element sync (data-cms-global)
   - New API: sync_global(key, value)
   - Scans all HTML files for data-cms-global="key", updates all
   - Triggered from Settings → Business Info save

5. Media Library
   - New API: list_media, upload_media, delete_media
   - Grid view of /uploads/ directory
   - Click to insert into current page

6. Restaurant Menu module (full implementation)

See CHANGELOG.md for full priority list and all 200+ feature ideas.

## HOW TO DO CODE CHANGES

When asked to fix bugs or add features:

1. ALWAYS read the relevant section of siterefresh-admin.php first
   (use grep to find the exact lines, then view those lines)
2. Use str_replace with exact matching strings — never rewrite whole sections
3. Verify every change with a check script before packaging
4. Update CHANGELOG.md with what was fixed/added
5. Package as cms_vX.X.X.zip containing the cms/ folder
6. Never leave a partially-broken file — complete all changes in one session
   If tool limits are approaching, finish the current atomic fix and package

## HOW TO GENERATE A NEW SITE

Use studio-prompt.md as the generation prompt.
Paste it into a new Claude conversation, then add the client brief after the
"BUSINESS BRIEF" section. The prompt generates complete HTML + CSS files
with all required data-* attributes for SR Edit CMS management.

Key things generated sites must have:
- data-cms on every visible text element
- data-cms-block + data-movable on every major section
- data-cms-nav on the <nav>
- theme.css with ONLY :root {} variables (CMS reads this for theme editor)
- styles.css using ONLY var(--...) — no hardcoded colors
- Schema.org JSON-LD for local SEO
- impressum.html + datenschutz.html (required for German sites)
- Root-relative hrefs (/page.html not page.html)

## HOW FUTURE UPDATES WORK

Drop replacement files into /cms/:
- siterefresh-admin.php → replace (all data in cms_data/, not in this file)
- api.php → replace
- SVG logos → replace (optional)
- studio-prompt.md → replace (optional)
- CHANGELOG.md → replace (cumulative)
- AI_CONTEXT.md → replace with updated version

cms_data/ is NEVER touched by updates — settings, license, backups all preserved.
No database migrations. No version scripts. Just file replacement.

## DEPLOYMENT CHECKLIST (for giving to a customer)

1. Upload /cms/ folder to site root
2. Set permissions: cms_data/ needs to be writable (chmod 755 or 775)
3. Go to /cms/siterefresh-admin.php
4. Login with admin / admin123
5. IMMEDIATELY change password in Settings → Account
6. Add .htaccess to cms/cms_data/ to block public access:
   Deny from all
7. Enter license key (or use SREDIT-DEMO-1234 for demo)
8. Load a page from the file tree and start editing

## TECH STACK

- PHP 7.4+ (dom extension standard on all hosts)
- Apache or Nginx (or PHP built-in server for local)
- No database (JSON file storage)
- No npm, no composer, no build step
- External: Quill 2.0.3 (cdn.jsdelivr.net), Syne + DM Mono (Google Fonts)
- Tested on: Apache/Nginx shared hosting, Hestia Control Panel, Docker PHP
