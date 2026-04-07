# SR Edit v0.1.0-alpha

A self-hosted, live click-to-edit HTML CMS. Drop it into any folder containing HTML files and start editing.

---

## 📁 File Structure

```
your-project/
├── index.html          ← your pages (scanned automatically)
├── about.html
├── pages/
│   └── contact.html
└── cms/                ← drop this folder here
    ├── index.php       ← main CMS app
    ├── api.php         ← backend API
    ├── cms_data/       ← created automatically
    │   ├── license.json
    │   └── backups/    ← auto-backups on every save
    └── README.md
```

---

## 🚀 Setup

### Requirements
- PHP 7.4+ (with `dom` extension, standard in most hosts)
- A web server (Apache/Nginx) **or** PHP's built-in server for local use

### 1. Upload
Upload the `cms/` folder into your project root (same level as your HTML files).

### 2. Set your password
Open `cms/index.php` and find this line near the top:

```php
define('CMS_PASSWORD_HASH', password_hash('admin123', PASSWORD_DEFAULT));
```

Replace `admin123` with your own password. To generate a proper hash, run:
```bash
php -r "echo password_hash('yourpassword', PASSWORD_DEFAULT);"
```
Then paste the output directly:
```php
define('CMS_PASSWORD_HASH', '$2y$10$...');
```

### 3. Access
Navigate to: `https://yoursite.com/cms/`

Default login: **admin** / **admin123**

---

## ✏️ Editing Pages

1. Click any `.html` file in the sidebar
2. The page loads in a live preview
3. **Click any text** on the preview to open the edit popup
4. Type your new content → click **Apply** or press **Ctrl+Enter**
5. Click **↑ Save** to write changes to disk
6. Use the **Elements panel** (right side) to see all editable elements and jump to them

---

## ⚙️ Auto-Tag

If a page has no `data-cms` attributes yet, click **Auto-Tag** in the top bar. This injects `data-cms` identifiers into all text elements automatically.

**Tagged elements look like:**
```html
<h1 data-cms="h1-1">Hello World</h1>
<p data-cms="p-1">Some paragraph text</p>
```

---

## 🧩 Modules

The Module Manager (sidebar → Modules) lists all available modules. Toggle them on/off. To add a new module:

1. Add your module definition to the `state.modules` array in `index.php`
2. Add a corresponding panel `<div id="panel-yourmodule">` in the HTML
3. Handle it in the `showModule()` function

For backend modules, add a new `case` in `api.php`'s switch router.

---

## 🔐 License

SR Edit is commercial software. License verification happens monthly against your license server.

**Demo mode:** Any key starting with `SREDIT-` and at least 14 characters is accepted (e.g. `SREDIT-DEMO-1234`).

**Production licensing:** In `api.php`, replace the `verifyKeyOnline()` function stub with a real HTTP call to your license server.

```php
function verifyKeyOnline(string $key): array {
    $ch = curl_init('https://license.yourserver.com/verify');
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['key' => $key, 'product' => 'sr-edit']));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    $response = curl_exec($ch);
    curl_close($ch);
    $data = json_decode($response, true);
    return ['valid' => $data['valid'] ?? false, 'message' => $data['message'] ?? ''];
}
```

---

## 💾 Backups

Every time you save a file, the previous version is automatically backed up to `cms/cms_data/backups/`. Up to 10 backups per file are kept.

---

## 🔒 Security Notes

- Change the default password before deploying publicly
- The `cms_data/` folder should not be publicly accessible — add to `.htaccess`:
  ```apache
  <Directory "cms/cms_data">
      Deny from all
  </Directory>
  ```
- All file paths are sanitized to prevent directory traversal

---

## 📦 Roadmap / Planned Modules

| Module | Status |
|---|---|
| Page Editor | ✅ Built-in |
| SEO Analyzer | 🔜 Coming |
| Media Manager | 🔜 Coming |
| HTML Snippets | 🔜 Coming |
| Change History | 🔜 Coming |
| Export & Deploy | 🔜 Coming |

---

*SR Edit © 2025. All rights reserved.*
