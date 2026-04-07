# SR Edit — Site Generation Prompt
# Version: 1.2
# Save this file to: cms_data/studio-prompt.md
# This prompt is used to generate complete websites for the SR Edit CMS.
# Load it in the Studio module or copy into a new Claude conversation.

---

You are generating a complete, production-ready website for a small business.
The site will be managed by SR Edit CMS. Follow ALL technical requirements exactly.
Do not skip any attribute specifications — they are required for CMS functionality.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
BUSINESS BRIEF
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
[INSERT FULL CLIENT BRIEF HERE]

Include:
- Business name
- Type of business (bakery, restaurant, electrician, dentist, etc.)
- Services or products offered
- Location (city, country)
- Target language (DE / EN / FR / other)
- Brand colors if known (or say "choose appropriately for this business type")
- Tone (warm and friendly / professional / modern / traditional)
- Modules needed: menu / booking / blog / gallery / products / events / team
- Any existing content (tagline, about text, team names, etc.)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
FILE STRUCTURE — generate exactly these files
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Required always:
  index.html              — homepage
  contact.html            — contact form, map, address
  impressum.html          — legal imprint (German law §5 TMG)
  datenschutz.html        — privacy policy (GDPR — module-aware, see spec)
  css/theme.css           — CSS variables ONLY
  css/styles.css          — all other CSS

Generate only if relevant to brief:
  about.html              — if brief mentions team, story, or history
  menu.html               — if restaurant/café (also add module stub)
  booking.html            — if appointments mentioned
  blog/index.html         — if blog mentioned
  leistungen.html         — services page (German sites)
  galerie.html            — gallery page

Do NOT generate:
  - Separate .js files (all JavaScript inline in each HTML file)
  - Build tools, package.json, .gitignore
  - Any PHP, server-side code
  - Separate icon files (use Unicode or inline SVG)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
HTML REQUIREMENTS
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

1. DOCTYPE and language
   <!DOCTYPE html>
   <html lang="de">   ← use actual language code

2. Required <head> content (every page):
   <meta charset="UTF-8">
   <meta name="viewport" content="width=device-width, initial-scale=1.0">
   <title>[Page title] — [Business name]</title>
   <meta name="description" content="[150–160 char description unique per page]">
   <meta property="og:title" content="[Business name]">
   <meta property="og:description" content="[Description]">
   <meta property="og:type" content="website">
   <link rel="canonical" href="[full URL of this page]">
   <link rel="stylesheet" href="/css/theme.css">
   <link rel="stylesheet" href="/css/styles.css">
   [Google Fonts <link> if fonts used]

3. Navigation — every page must have:
   <nav data-cms-nav>
     <div class="nav-logo">
       <img data-cms-image="logo" data-cms-type="logo"
            src="/images/logo-placeholder.svg"
            alt="[Business name] Logo">
     </div>
     <ul class="nav-links">
       <li><a href="/index.html">Home</a></li>
       <li><a href="/about.html">About</a></li>
       <!-- other links -->
       <li><a href="/contact.html">Contact</a></li>
     </ul>
     <button class="nav-hamburger" aria-label="Menu" onclick="toggleNav()">
       <span></span><span></span><span></span>
     </button>
   </nav>
   RULE: data-cms-nav is required. The CMS auto-injects links here when modules
   create new pages. All hrefs must be root-relative (/page.html not page.html).

4. Page sections — wrap every major content block:
   <section data-cms-block="hero" data-movable>
     <!-- content -->
   </section>

   data-cms-block value must be descriptive and unique per page.
   Standard block names (use these exactly so the CMS recognises them):
     hero, services, about-preview, team-preview, testimonials,
     menu-preview, gallery-preview, booking-preview, blog-preview,
     contact-info, cta, stats, partners, faq-preview

   data-movable = this section can be dragged to a new position in the CMS.
   Do NOT add data-movable to: <header>, <nav>, <footer>
   These are structural and must stay fixed.

5. Editable text elements — EVERY visible text node gets data-cms:
   <h1 data-cms="h1-1">Heading</h1>
   <h2 data-cms="h2-1">Subheading</h2>
   <p data-cms="p-1">Paragraph text</p>
   <span data-cms="span-1">Inline text</span>
   <li data-cms="li-1">List item</li>

   Numbering: sequential per tag type per page. h1-1, h1-2 / p-1, p-2 / etc.
   Do NOT add data-cms to: decorative elements, script tags, style tags,
   structural wrappers with no visible text.

6. Typed editable elements — add data-cms-type for special fields:

   Phone numbers (updates both text AND tel: href simultaneously):
   <a data-cms="phone-1" data-cms-type="phone"
      href="tel:+49345123456">+49 345 123456</a>

   Email addresses (updates both text AND mailto: href):
   <a data-cms="email-1" data-cms-type="email"
      href="mailto:info@business.de">info@business.de</a>

   Prices (numeric field, currency-aware):
   <span data-cms="price-1" data-cms-type="price"
         data-currency="EUR">12.50</span>

   Buttons/CTAs (editable text + editable URL):
   <a data-cms="btn-1" data-cms-type="cta"
      href="/contact.html" class="btn-primary">Get in touch</a>

   Addresses (multi-line text area in CMS):
   <address data-cms="addr-1" data-cms-type="address">
     Marktplatz 1<br>06108 Halle (Saale)
   </address>

7. Global elements — sync across ALL pages automatically:

   Business name (changing in Settings updates every instance):
   <span data-cms-global="business-name">Müllers Bäckerei</span>

   Tagline:
   <span data-cms-global="tagline">Fresh every morning since 1987</span>

   Logo (changing logo in Settings/Media updates everywhere):
   <img data-cms-image="logo" data-cms-type="logo" src="..." alt="...">

   Copyright year (auto-updates):
   <span data-cms-global="copyright-year">2025</span>

   Social links (Settings → Social Media auto-updates these):
   <a data-cms-social="instagram" href="https://instagram.com/handle">
     Instagram
   </a>
   <a data-cms-social="facebook" href="#">Facebook</a>
   <a data-cms-social="whatsapp" href="https://wa.me/49345123456">WhatsApp</a>
   Supported values: instagram, facebook, linkedin, youtube, tiktok, x, pinterest, whatsapp

8. Images — use placeholder divs (Media Library replaces these):
   <div data-cms-image="hero-bg"
        data-placeholder="Hero background — 1920×600px, wide landscape"
        data-cms-alt="[Descriptive alt text for accessibility]"
        style="background:var(--color-bg-alt);
               width:100%;aspect-ratio:16/6;
               display:flex;align-items:center;justify-content:center;
               color:var(--color-text-muted);font-size:14px;
               font-family:var(--font-body)">
     [Photo: hero background]
   </div>

   For <img> tags (team photos, product images):
   <img data-cms-image="team-ceo"
        data-cms-alt
        src="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg'..."
        alt="CEO name — business name"
        style="width:100%;aspect-ratio:1;object-fit:cover">

   data-cms-alt makes the alt text editable from the CMS accessibility panel.

9. Visibility toggles — seasonal/conditional content:
   <div data-cms-toggle="holiday-banner"
        data-cms-label="Holiday Banner (show/hide)"
        data-cms-visible="false"
        style="display:none">
     🎄 Special holiday offer — valid until December 31!
   </div>

   The CMS shows a toggle switch in the elements panel for these blocks.
   Owners can show/hide without editing HTML. Use for: banners, notices,
   seasonal offers, temporary closures, special announcements.

10. Module integration stubs — for modules that will be activated later:

    Restaurant menu:
    <div data-cms-module="menu" data-cms-block="menu-section"
         data-movable style="min-height:200px">
      <!-- SR Edit Menu Module renders here when activated -->
      <p style="color:var(--color-text-muted);text-align:center;
                padding:40px;font-style:italic">
        Menu will appear here
      </p>
    </div>

    Appointment booking:
    <div data-cms-module="booking" data-cms-block="booking-section"
         data-movable style="min-height:200px">
      <!-- SR Edit Booking Module renders here when activated -->
    </div>

    Blog latest posts:
    <div data-cms-module="blog" data-cms-block="blog-preview"
         data-movable data-cms-count="3">
      <!-- SR Edit Blog Module renders latest 3 posts here -->
    </div>

    Events upcoming:
    <div data-cms-module="events" data-cms-block="events-preview"
         data-movable data-cms-count="3">
      <!-- SR Edit Events Module renders here -->
    </div>

    Testimonials:
    <div data-cms-module="testimonials" data-cms-block="testimonials-section"
         data-movable>
      <!-- SR Edit Testimonials Module renders here -->
    </div>

    Opening hours display (auto-updates when Settings → Hours changes):
    <ul data-cms-module="hours" data-cms-block="hours-display">
      <li data-cms="hours-mon">Monday: 09:00 – 18:00</li>
      <li data-cms="hours-tue">Tuesday: 09:00 – 18:00</li>
      <li data-cms="hours-wed">Wednesday: 09:00 – 18:00</li>
      <li data-cms="hours-thu">Thursday: 09:00 – 18:00</li>
      <li data-cms="hours-fri">Friday: 09:00 – 18:00</li>
      <li data-cms="hours-sat">Saturday: 10:00 – 14:00</li>
      <li data-cms="hours-sun" data-cms-visible="false">Sunday: Closed</li>
    </ul>

11. Google Maps (settings-driven, no manual embed needed):
    <div data-cms-module="map"
         data-cms-address="Marktplatz 1, 06108 Halle, Germany"
         style="width:100%;height:400px;background:var(--color-bg-alt)">
      <!-- SR Edit injects Google Maps iframe here based on address in Settings -->
    </div>

12. Cookie-gated scripts (loaded only after consent):
    <script data-cms-cookie-category="analytics" type="text/plain">
      // Google Analytics — only runs after cookie consent
      window.dataLayer = window.dataLayer || [];
      // gtag code here
    </script>

    Categories: analytics, marketing, functional
    "necessary" scripts do not need this attribute (they always load).

13. Footer must appear on every page:
    <footer>
      <div class="footer-brand">
        <span data-cms-global="business-name">Business Name</span>
        <span data-cms-global="tagline">Tagline</span>
      </div>
      <nav class="footer-nav" data-cms-footer-nav>
        <a href="/index.html">Home</a>
        <a href="/contact.html">Contact</a>
        <a href="/impressum.html">Impressum</a>
        <a href="/datenschutz.html">Datenschutz</a>
      </nav>
      <div class="footer-contact">
        <a data-cms="phone-footer" data-cms-type="phone"
           href="tel:+49...">+49 ...</a>
        <a data-cms="email-footer" data-cms-type="email"
           href="mailto:...">info@...</a>
        <address data-cms="addr-footer" data-cms-type="address">
          Street<br>City
        </address>
      </div>
      <div class="footer-social">
        <a data-cms-social="instagram" href="#">Instagram</a>
        <a data-cms-social="facebook" href="#">Facebook</a>
      </div>
      <p class="footer-legal">
        © <span data-cms-global="copyright-year">2025</span>
        <span data-cms-global="business-name">Business Name</span>.
        All rights reserved.
      </p>
    </footer>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CSS ARCHITECTURE
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

css/theme.css — ONLY the :root block, nothing else:

:root {
  /* ── Colors ── */
  --color-primary:      #[hex];  /* main brand color — buttons, links, accents */
  --color-primary-dark: #[hex];  /* hover state of primary */
  --color-secondary:    #[hex];  /* secondary accent */
  --color-bg:           #[hex];  /* main page background */
  --color-bg-alt:       #[hex];  /* alternate section background */
  --color-bg-card:      #[hex];  /* card/box backgrounds */
  --color-text:         #[hex];  /* primary body text */
  --color-text-muted:   #[hex];  /* secondary/placeholder text */
  --color-heading:      #[hex];  /* heading color */
  --color-border:       #[hex];  /* borders, dividers, rules */
  --color-cta:          #[hex];  /* call-to-action button background */
  --color-cta-text:     #[hex];  /* text on CTA buttons */
  --color-cta-hover:    #[hex];  /* CTA hover state */
  --color-success:      #27ae60; /* form success messages */
  --color-error:        #e74c3c; /* form error messages */

  /* ── Typography ── */
  --font-heading:    '[Font]', serif;
  --font-body:       '[Font]', sans-serif;
  --font-mono:       'DM Mono', monospace;
  --font-size-base:  16px;
  --font-size-sm:    14px;
  --font-size-lg:    18px;
  --font-size-xl:    24px;
  --font-size-2xl:   32px;
  --font-size-3xl:   48px;
  --font-size-4xl:   64px;
  --line-height-body:    1.7;
  --line-height-heading: 1.15;
  --font-weight-normal:  400;
  --font-weight-medium:  500;
  --font-weight-bold:    700;
  --font-weight-black:   900;

  /* ── Spacing ── */
  --spacing-xs:        8px;
  --spacing-sm:        16px;
  --spacing-md:        24px;
  --spacing-lg:        40px;
  --spacing-xl:        64px;
  --spacing-2xl:       96px;
  --spacing-section:   80px;    /* top/bottom padding for page sections */
  --spacing-container: 1200px;  /* max content width */

  /* ── Shapes ── */
  --border-radius-sm:  4px;
  --border-radius:     8px;
  --border-radius-lg:  16px;
  --border-radius-xl:  24px;
  --border-radius-full: 9999px;

  /* ── Shadows ── */
  --shadow-sm:    0 1px 4px rgba(0,0,0,0.06);
  --shadow:       0 4px 20px rgba(0,0,0,0.08);
  --shadow-lg:    0 8px 40px rgba(0,0,0,0.12);
  --shadow-hover: 0 12px 48px rgba(0,0,0,0.16);

  /* ── Transitions ── */
  --transition-fast:   0.15s ease;
  --transition:        0.25s ease;
  --transition-slow:   0.4s ease;

  /* ── Z-index scale ── */
  --z-base:    1;
  --z-above:   10;
  --z-nav:     100;
  --z-modal:   1000;
  --z-toast:   9999;
}

css/styles.css rules:
  - FIRST LINE must be: @import url('https://fonts.googleapis.com/css2?...');
  - Use ONLY var(--...) for any value that corresponds to a theme variable.
    Never hardcode hex colors, pixel sizes, or font names in styles.css.
  - Use CSS custom properties for any value a business owner might want to
    change: colors, sizes, fonts, radii, spacing.
  - Mobile-first: base styles for mobile, then @media (min-width: 768px)
    and @media (min-width: 1024px) for larger screens.
  - Use CSS Grid for page layout, Flexbox for component layout.
  - Include: html { scroll-behavior: smooth; box-sizing: border-box; }
  - Include: *, *::before, *::after { box-sizing: inherit; }
  - Include focus-visible styles: :focus-visible { outline: 2px solid var(--color-primary); }
  - Navigation: mobile hamburger hidden on desktop, nav-links visible.
    On mobile: nav-links hidden, hamburger visible, toggleNav() shows/hides.
  - Sections: padding: var(--spacing-section) 0 by default.
  - Container: max-width: var(--spacing-container); margin: 0 auto;
    padding: 0 var(--spacing-md);
  - Buttons: two variants — .btn-primary and .btn-secondary.
    .btn-primary uses var(--color-cta) and var(--color-cta-text).
  - Images: all img { max-width: 100%; height: auto; }
  - Contact form: show .form-success / .form-error messages inline.
    Never redirect — keep user on the same page.

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
JAVASCRIPT — inline only, before </body>
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Single <script> tag per HTML file, placed before </body>.

Required to implement:
  function toggleNav() — hamburger mobile menu open/close
  Contact form handling — preventDefault, show inline success/error, reset form
  IntersectionObserver for scroll-reveal animations (if used)
  Any page-specific interactions (accordions, tabs, carousels)

Do NOT implement:
  - Any CMS-related JS (the CMS injects its own script into the iframe)
  - localStorage or sessionStorage
  - External script dependencies not declared in <head>
  - Google Analytics (handled by CMS cookie-category system)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IMPRESSUM (impressum.html)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Required by §5 TMG for any public German website.
Generate in the same language as the rest of the site.
Must include all of these fields with data-cms attributes:

- Verantwortlich / Responsible person:
  Full name, full postal address, email, phone
- If a company: Rechtsform, Registergericht, Handelsregisternummer, Geschäftsführer
- VAT number if applicable: Umsatzsteuer-ID
- EU dispute resolution notice (required since 2016):
  Link to https://ec.europa.eu/consumers/odr
  Statement that you do not participate in dispute resolution proceedings
- Professional regulations if applicable (lawyers, doctors, etc.)

All fields must have data-cms attributes so they can be edited from the CMS.
The impressum.html template should reference global data where possible:
  <span data-cms-global="business-name">...</span>
  <address data-cms="impressum-address" data-cms-type="address">...</address>
  <a data-cms="impressum-email" data-cms-type="email" href="mailto:...">...</a>
  <a data-cms="impressum-phone" data-cms-type="phone" href="tel:...">...</a>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
PRIVACY POLICY (datenschutz.html)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

IMPORTANT: Generate the privacy policy based ONLY on what this specific
site actually does. Do not include boilerplate for services not used.
Include a section for each of the following that applies:

ALWAYS include:
  1. Verantwortlicher (data controller) — name, address, email
  2. Hosting — server location, provider if known, or "selbst gehosteter Server"
  3. Zugriffsdaten / Server logs — IP address, timestamp, pages visited
     Legal basis: Art. 6 Abs. 1 lit. f DSGVO (legitimate interest)
  4. Kontaktformular — if contact.html has a form
     Data collected: name, email, message
     Retention: until request is resolved, max 2 years
     Legal basis: Art. 6 Abs. 1 lit. b DSGVO
  5. Betroffenenrechte (visitor rights):
     Right to access, correction, deletion, restriction, portability, objection
     Right to complain: Landesbeauftragte für Datenschutz [relevant state]
  6. Keine automatisierte Entscheidungsfindung (no automated decisions)

Include ONLY IF the site uses these features:
  Google Analytics:
    - What: page views, device type, approximate location
    - Where data goes: Google LLC, USA (Privacy Shield / SCC)
    - Opt-out: browser plugin, or cookie settings
    - Legal basis: Art. 6 Abs. 1 lit. a DSGVO (consent)

  Google Maps embed:
    - What: IP address sent to Google on map load
    - Legal basis: Art. 6 Abs. 1 lit. f DSGVO (legitimate interest)
    - Google Privacy Policy link

  YouTube embed:
    - What: cookies set by YouTube even before play
    - Recommend using youtube-nocookie.com
    - Legal basis: consent

  Newsletter / Brevo:
    - Data collected: email address, subscription date, IP
    - Processor: Sendinblue SAS, Paris, France
    - Retention: until unsubscription
    - Double opt-in procedure described

  Appointment booking:
    - Data collected: name, email, phone, appointment details
    - Retention: until appointment completed + 6 months
    - Legal basis: Art. 6 Abs. 1 lit. b DSGVO (contract performance)

  reCAPTCHA:
    - Data sent to Google for bot detection
    - Legal basis: Art. 6 Abs. 1 lit. f DSGVO

  Cookies:
    - List all cookies used with name, purpose, duration, provider
    - Necessary cookies (session, CSRF): no consent needed
    - Analytics cookies: require consent
    - Link to cookie settings / consent manager

Write the privacy policy in [SAME LANGUAGE AS SITE — DE/EN/FR].
Use plain language. Avoid legal jargon where possible.
Every editable field must have data-cms attributes.
Include last-updated date: <span data-cms="datenschutz-date">01.01.2025</span>

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONTENT GUIDELINES
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

- Write in [LANGUAGE] throughout, including meta tags
- Every page has exactly ONE <h1>
- Heading hierarchy never skips levels: h1 → h2 → h3
- All decorative elements use aria-hidden="true"
- Interactive elements have aria-label where text is not visible
- Color contrast must meet WCAG AA minimum (4.5:1 for text)
- Write real, believable placeholder content — not "Lorem ipsum"
  Use generic but realistic business names, addresses, phone numbers
  Use placeholder emails like info@[businessname].de
- Taglines and headings should sound natural in the target language
- If German: use Sie-form (formal) by default unless brief says otherwise

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
QUALITY CHECKLIST — verify before output
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

HTML:
  □ Every visible text node has data-cms
  □ Phone numbers have data-cms-type="phone" with matching tel: href
  □ Email addresses have data-cms-type="email" with matching mailto: href
  □ Prices have data-cms-type="price" with data-currency
  □ CTA buttons have data-cms-type="cta"
  □ Every section has data-cms-block with descriptive name
  □ Major sections have data-movable (except header/nav/footer)
  □ <nav> has data-cms-nav
  □ <footer> nav has data-cms-footer-nav
  □ Social links have data-cms-social="[platform]"
  □ Global elements have data-cms-global="[key]"
  □ Image placeholders have data-cms-image and data-placeholder
  □ Module stubs present for all requested modules
  □ Opening hours use data-cms-module="hours"
  □ Map uses data-cms-module="map" with data-cms-address
  □ Visibility toggles have data-cms-toggle and data-cms-label
  □ All hrefs use root-relative paths (/page.html)
  □ impressum.html and datenschutz.html both present
  □ Footer appears on every page
  □ Footer links to impressum.html and datenschutz.html
  □ Copyright year uses data-cms-global="copyright-year"

CSS:
  □ theme.css contains ONLY :root {} block — nothing else
  □ styles.css starts with @import for Google Fonts
  □ No hardcoded colors in styles.css — only var(--color-...)
  □ No hardcoded font names in styles.css — only var(--font-...)
  □ Mobile-first breakpoints at 768px and 1024px
  □ .btn-primary and .btn-secondary defined
  □ Mobile nav hamburger works at 375px width
  □ All sections have padding using var(--spacing-section)

JavaScript:
  □ toggleNav() function defined
  □ Contact form has preventDefault and inline success/error
  □ No external .js file references
  □ No CMS-specific code

Legal pages:
  □ impressum.html covers all §5 TMG requirements
  □ datenschutz.html covers only services actually used
  □ Privacy policy language matches site language
  □ EU dispute resolution notice in impressum.html
  □ Last-updated date in datenschutz.html

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OUTPUT FORMAT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Output each file with a clear separator:

=== index.html ===
[complete file content]

=== about.html ===
[complete file content]

=== contact.html ===
[complete file content]

=== impressum.html ===
[complete file content]

=== datenschutz.html ===
[complete file content]

=== css/theme.css ===
[complete file content]

=== css/styles.css ===
[complete file content]

Do not truncate any file. Every file must be complete and deployable as-is.
