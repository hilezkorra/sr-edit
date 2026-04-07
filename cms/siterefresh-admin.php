<?php
session_start();

// ─── CSRF TOKEN ─────────────────────────────────────────────────────────────
if (empty($_SESSION['cms_csrf'])) {
    $_SESSION['cms_csrf'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['cms_csrf'];

// ─── CONFIG ────────────────────────────────────────────────────────────────
define('CMS_VERSION', '0.1.0-alpha');
define('CMS_NAME', 'SR Edit');
define('DATA_DIR', __DIR__ . '/cms_data/');
define('LICENSE_FILE', __DIR__ . '/cms_data/license.json');
define('SETTINGS_FILE', __DIR__ . '/cms_data/settings.json');

if (!is_dir(DATA_DIR)) mkdir(DATA_DIR, 0755, true);

// Load settings (name + password hash) — falls back to hardcoded defaults
function loadSettings(): array {
    if (file_exists(SETTINGS_FILE)) {
        $s = json_decode(file_get_contents(SETTINGS_FILE), true);
        if (is_array($s) && !empty($s['password_hash'])) return $s;
    }
    // First boot: generate a stable hash and persist it so it never changes
    $defaults = [
        'username'      => 'admin',
        'display_name'  => 'Admin',
        'password_hash' => password_hash('admin123', PASSWORD_BCRYPT, ['cost' => 10]),
    ];
    // Persist so verify always works against the same hash
    $dir = dirname(SETTINGS_FILE);
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    if (is_writable($dir)) {
        @file_put_contents(SETTINGS_FILE, json_encode($defaults, JSON_PRETTY_PRINT));
    }
    return $defaults;
}

$settings = loadSettings();
define('CMS_USERNAME',      $settings['username']);
define('CMS_DISPLAY_NAME',  $settings['display_name'] ?? $settings['username']);
define('CMS_PASSWORD_HASH', $settings['password_hash']);

// ─── AUTH ───────────────────────────────────────────────────────────────────
$isLoggedIn = isset($_SESSION['cms_auth']) && $_SESSION['cms_auth'] === true;

if (isset($_POST['cms_login'])) {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';
    if ($user === CMS_USERNAME && password_verify($pass, CMS_PASSWORD_HASH)) {
        $_SESSION['cms_auth'] = true;
        $_SESSION['cms_user'] = CMS_DISPLAY_NAME;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $loginError = 'Invalid credentials.';
    }
}

// Settings save (AJAX POST from JS — must run before any HTML output)
if (isset($_POST['cms_save_settings'])) {
    // Must be logged in
    if (!isset($_SESSION['cms_auth']) || $_SESSION['cms_auth'] !== true) {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Not authenticated.']);
        exit;
    }

    header('Content-Type: application/json');

    $curPass     = $_POST['current_password'] ?? '';
    $newPass     = $_POST['new_password'] ?? '';
    $newDisplay  = trim($_POST['display_name'] ?? '');
    $newUsername = trim($_POST['new_username'] ?? '');

    $s      = loadSettings();
    $errors = [];

    // Verify current password
    if (!password_verify($curPass, $s['password_hash'])) {
        echo json_encode(['success' => false, 'error' => 'Current password is incorrect.']);
        exit;
    }

    // Apply changes
    if ($newDisplay !== '')  $s['display_name'] = htmlspecialchars($newDisplay, ENT_QUOTES);
    if ($newUsername !== '') $s['username']      = preg_replace('/[^a-z0-9_\-]/i', '', $newUsername) ?: $s['username'];
    if ($newPass !== '') {
        if (strlen($newPass) < 6) {
            echo json_encode(['success' => false, 'error' => 'New password must be at least 6 characters.']);
            exit;
        }
        $s['password_hash'] = password_hash($newPass, PASSWORD_DEFAULT);
    }

    // Check DATA_DIR is writable
    if (!is_dir(DATA_DIR)) {
        @mkdir(DATA_DIR, 0755, true);
    }
    if (!is_writable(DATA_DIR)) {
        echo json_encode(['success' => false, 'error' => 'Cannot write to cms_data/ — check folder permissions (chmod 755).']);
        exit;
    }

    $written = file_put_contents(SETTINGS_FILE, json_encode($s, JSON_PRETTY_PRINT));
    if ($written === false) {
        echo json_encode(['success' => false, 'error' => 'Failed to write settings.json — check file permissions.']);
        exit;
    }

    // Update session display name
    $_SESSION['cms_user'] = $s['display_name'];
    echo json_encode(['success' => true, 'display_name' => $s['display_name']]);
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>SR Edit <?= CMS_VERSION ?></title>
<?php
// Load SVG logos (preferred) — fall back to PNG if SVG not present
$faviconSvg   = file_get_contents(__DIR__ . '/sr-edit-favicon.svg') ?: '';
$logoDarkSvg  = file_get_contents(__DIR__ . '/sr-edit-logo-dark.svg') ?: '';
$logoLightSvg = file_get_contents(__DIR__ . '/sr-edit-logo-light.svg') ?: '';
// PNG fallbacks
$faviconB64   = $faviconSvg   ? '' : base64_encode(file_get_contents(__DIR__ . '/sr-edit-favicon.png') ?: '');
$logoDarkB64  = $logoDarkSvg  ? '' : base64_encode(file_get_contents(__DIR__ . '/sr-edit-logo-dark.png') ?: '');
$logoLightB64 = $logoLightSvg ? '' : base64_encode(file_get_contents(__DIR__ . '/sr-edit-logo-light.png') ?: '');
?>
<?php if ($faviconSvg): ?>
<link rel="icon" type="image/svg+xml" href="data:image/svg+xml;base64,<?= base64_encode($faviconSvg) ?>">
<?php elseif ($faviconB64): ?>
<link rel="icon" type="image/png" href="data:image/png;base64,<?= $faviconB64 ?>">
<?php endif; ?>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Syne:wght@400;600;700;800&family=DM+Mono:wght@300;400;500&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
<style>
/* ═══════════════════════════════════════════════
   DESIGN TOKENS — DARK (default)
═══════════════════════════════════════════════ */
:root {
  --bg:        #0a0a0f;
  --bg2:       #111118;
  --bg3:       #18181f;
  --bg4:       #1e1e28;
  --border:    #2a2a38;
  --border2:   #363648;
  --accent:    #7c6cfc;
  --accent2:   #a594fd;
  --accent-glow: rgba(124,108,252,0.25);
  --green:     #3dffa0;
  --red:       #ff5f7e;
  --yellow:    #ffd060;
  --text:      #e8e8f0;
  --text2:     #9090a8;
  --text3:     #5a5a72;
  --radius:    10px;
  --radius-lg: 16px;
  --shadow:    0 4px 24px rgba(0,0,0,0.5);
  --transition: 0.2s cubic-bezier(0.4,0,0.2,1);
  --sidebar-w: 260px;
  --topbar-h:  64px;
  --logo-show-dark:  block;
  --logo-show-light: none;
}

/* ═══════════════════════════════════════════════
   DESIGN TOKENS — LIGHT
═══════════════════════════════════════════════ */
[data-theme="light"] {
  --bg:        #f0f4f8;
  --bg2:       #ffffff;
  --bg3:       #f5f7fa;
  --bg4:       #eaeef3;
  --border:    #dde2ea;
  --border2:   #c8d0db;
  --accent:    #5b4de8;
  --accent2:   #7c6cfc;
  --accent-glow: rgba(91,77,232,0.18);
  --green:     #16a05c;
  --red:       #e5364f;
  --yellow:    #c98a00;
  --text:      #1a1a2e;
  --text2:     #4a4a68;
  --text3:     #8888a8;
  --shadow:    0 4px 24px rgba(0,0,0,0.10);
  --logo-show-dark:  none;
  --logo-show-light: block;
}

/* THEME TOGGLE BUTTON */
.theme-toggle {
  width: 32px; height: 32px;
  border-radius: 8px; border: 1px solid var(--border);
  background: var(--bg3); cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 15px; transition: all var(--transition);
  flex-shrink: 0;
}
.theme-toggle:hover { border-color: var(--border2); background: var(--bg4); }

/* LOGO IMAGE VARIANTS */
.logo-img-dark  { display: var(--logo-show-dark);  height: 54px; width: auto; max-width: 185px; object-fit: contain; }
.logo-img-light { display: var(--logo-show-light); height: 54px; width: auto; max-width: 185px; object-fit: contain; }
.login-logo-img-dark  { display: var(--logo-show-dark);  height: 62px; width: auto; margin-bottom: 24px; }
.login-logo-img-light { display: var(--logo-show-light); height: 62px; width: auto; margin-bottom: 24px; }

*, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

html, body {
  height: 100%;
  background: var(--bg);
  color: var(--text);
  font-family: 'Syne', sans-serif;
  overflow: hidden;
}

/* ═══════════════════════════════════════════════
   LOGIN
═══════════════════════════════════════════════ */
.login-wrap {
  height: 100vh;
  display: flex;
  align-items: center;
  justify-content: center;
  background: var(--bg);
  position: relative;
  overflow: hidden;
}
.login-bg {
  position: absolute; inset: 0;
  background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(124,108,252,0.15) 0%, transparent 70%),
              radial-gradient(ellipse 50% 40% at 80% 80%, rgba(61,255,160,0.06) 0%, transparent 60%);
  pointer-events: none;
}
.login-grid {
  position: absolute; inset: 0;
  background-image: linear-gradient(var(--border) 1px, transparent 1px),
                    linear-gradient(90deg, var(--border) 1px, transparent 1px);
  background-size: 40px 40px;
  opacity: 0.3;
  pointer-events: none;
}
.login-box {
  position: relative;
  width: 380px;
  background: var(--bg2);
  border: 1px solid var(--border2);
  border-radius: var(--radius-lg);
  padding: 44px 40px;
  box-shadow: 0 0 0 1px rgba(124,108,252,0.1), var(--shadow), 0 0 80px rgba(124,108,252,0.08);
  animation: loginIn 0.5s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes loginIn {
  from { opacity: 0; transform: translateY(24px) scale(0.97); }
  to   { opacity: 1; transform: none; }
}
.login-logo {
  display: flex; align-items: center; gap: 10px;
  margin-bottom: 32px;
}
.login-logo-icon {
  width: 36px; height: 36px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 8px;
  display: flex; align-items: center; justify-content: center;
  font-size: 18px; font-weight: 800;
  color: #fff;
  box-shadow: 0 0 20px var(--accent-glow);
}
.login-logo-name {
  font-size: 20px; font-weight: 800; letter-spacing: -0.5px;
}
.login-logo-name span { color: var(--accent2); }
.login-title { font-size: 24px; font-weight: 700; margin-bottom: 6px; }
.login-sub   { color: var(--text2); font-size: 13px; margin-bottom: 28px; font-family: 'DM Mono', monospace; }
.form-field  { margin-bottom: 16px; }
.form-label  { display: block; font-size: 11px; font-weight: 600; letter-spacing: 1px; text-transform: uppercase; color: var(--text3); margin-bottom: 6px; }
.form-input  {
  width: 100%; padding: 11px 14px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text);
  font-family: 'DM Mono', monospace; font-size: 14px;
  outline: none; transition: border-color var(--transition), box-shadow var(--transition);
}
.form-input:focus {
  border-color: var(--accent);
  box-shadow: 0 0 0 3px var(--accent-glow);
}
.btn-primary {
  width: 100%; padding: 12px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border: none; border-radius: var(--radius);
  color: #fff; font-family: 'Syne', sans-serif;
  font-size: 14px; font-weight: 700; letter-spacing: 0.5px;
  cursor: pointer; margin-top: 8px;
  transition: opacity var(--transition), transform var(--transition), box-shadow var(--transition);
  box-shadow: 0 4px 20px var(--accent-glow);
}
.btn-primary:hover { opacity: 0.9; transform: translateY(-1px); box-shadow: 0 8px 28px var(--accent-glow); }
.btn-primary:active { transform: translateY(0); }
.login-error {
  background: rgba(255,95,126,0.1); border: 1px solid rgba(255,95,126,0.3);
  border-radius: var(--radius); padding: 10px 14px;
  color: var(--red); font-size: 13px; margin-bottom: 16px;
}
.login-hint {
  margin-top: 20px; text-align: center;
  color: var(--text3); font-size: 12px; font-family: 'DM Mono', monospace;
}

/* ═══════════════════════════════════════════════
   APP SHELL
═══════════════════════════════════════════════ */
.app { display: flex; flex-direction: column; height: 100vh; }

/* TOPBAR */
.topbar {
  height: var(--topbar-h); flex-shrink: 0;
  background: var(--bg2);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 12px;
  padding: 0 16px 0 0;
  z-index: 100;
}
.topbar-logo {
  flex-shrink: 0;
  display: flex; align-items: center; gap: 8px;
  padding: 0 16px 0 12px;
  border-right: 1px solid var(--border);
  height: 100%;
}
/* Logo image wrapper — hover glow, click pop, alpha badge inside */
.logo-img-wrap {
  display: inline-flex; align-items: center; flex-direction: row;
  cursor: pointer; border-radius: 10px;
  padding: 5px 10px 5px 8px;
  border: 1px solid rgba(56,189,248,0.2);
  background: rgba(56,189,248,0.04);
  position: relative;
  transition: border-color 0.25s ease, box-shadow 0.25s ease, transform 0.15s ease, background 0.25s ease;
  user-select: none; gap: 8px;
}
.logo-img-wrap:hover {
  border-color: rgba(56,189,248,0.45);
  background: rgba(56,189,248,0.08);
  box-shadow: 0 0 20px rgba(56,189,248,0.18), inset 0 0 16px rgba(56,189,248,0.06);
}
.logo-img-wrap:active { transform: scale(0.95); }
/* Alpha tag inside the box — sits inline, aligned to baseline */
.logo-img-wrap .alpha-badge {
  font-size: 8px; padding: 2px 6px; letter-spacing: 1.5px;
  margin: 0; align-self: flex-end; margin-bottom: 2px;
}
@keyframes logoPop {
  0%   { transform: scale(1) rotate(0deg); }
  20%  { transform: scale(1.1) rotate(-4deg); }
  45%  { transform: scale(1.06) rotate(3deg); }
  70%  { transform: scale(1.03) rotate(-1.5deg); }
  100% { transform: scale(1) rotate(0deg); }
}
.logo-img-wrap.pop { animation: logoPop 0.5s cubic-bezier(0.34,1.56,0.64,1) both; }
.topbar-logo-icon {
  width: 28px; height: 28px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 6px;
  display: flex; align-items: center; justify-content: center;
  font-size: 13px; font-weight: 800; color: #fff;
  box-shadow: 0 0 12px var(--accent-glow);
}
.topbar-logo-name { font-size: 16px; font-weight: 800; letter-spacing: -0.5px; }
.topbar-logo-name span { color: var(--accent2); }
.topbar-breadcrumb {
  flex: 1; display: flex; align-items: center; gap: 6px;
  font-size: 13px; color: var(--text2); font-family: 'DM Mono', monospace;
}
.topbar-breadcrumb .sep { color: var(--text3); }
.topbar-breadcrumb .current { color: var(--text); font-weight: 500; }
.topbar-actions { display: flex; align-items: center; gap: 8px; }
.tb-btn {
  padding: 7px 14px; border-radius: 8px;
  border: 1px solid var(--border); background: var(--bg3);
  color: var(--text2); font-size: 12px; font-weight: 600;
  font-family: 'Syne', sans-serif; cursor: pointer;
  transition: all var(--transition); white-space: nowrap;
}
.tb-btn:hover { border-color: var(--border2); color: var(--text); background: var(--bg4); }
.tb-btn.save {
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-color: transparent; color: #fff;
  box-shadow: 0 2px 12px var(--accent-glow);
}
.tb-btn.save:hover { box-shadow: 0 4px 20px var(--accent-glow); transform: translateY(-1px); }
.tb-btn.danger { border-color: rgba(255,95,126,0.3); color: var(--red); }
.tb-btn.danger:hover { background: rgba(255,95,126,0.1); border-color: var(--red); }
.topbar-avatar {
  width: 30px; height: 30px;
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-radius: 50%; display: flex; align-items: center;
  justify-content: center; font-size: 12px; font-weight: 700;
  color: #fff; cursor: pointer; margin-left: 4px;
}

/* BODY */
.app-body { display: flex; flex: 1; overflow: hidden; }

/* SIDEBAR */
.sidebar {
  width: var(--sidebar-w); flex-shrink: 0;
  background: var(--bg2);
  border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  overflow: hidden;
  /* Animated collapse */
  transition: width 0.28s cubic-bezier(0.4,0,0.2,1),
              opacity 0.22s ease,
              border-color 0.28s ease;
}
.sidebar.collapsed {
  width: 0;
  opacity: 0;
  border-right-color: transparent;
  pointer-events: none;
}

/* Sidebar toggle button — left edge */
.sidebar-toggle {
  position: absolute; left: 0; top: 50%;
  transform: translateY(-50%);
  width: 18px; height: 48px;
  background: var(--bg2); border: 1px solid var(--border);
  border-left: none; border-radius: 0 8px 8px 0;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: var(--text3); font-size: 10px;
  transition: background var(--transition), color var(--transition), width var(--transition);
  z-index: 50;
  /* Sits on editor-panel (position:relative), NOT clipped by preview-wrap overflow:hidden */
}
.sidebar-toggle:hover { background: var(--bg3); color: var(--text); width: 22px; }

/* Elements panel toggle — right edge of preview */
.elements-toggle {
  position: absolute; right: 0; top: 50%;
  transform: translateY(-50%);
  width: 18px; height: 48px;
  background: var(--bg2); border: 1px solid var(--border);
  border-right: none; border-radius: 8px 0 0 8px;
  cursor: pointer; display: flex; align-items: center; justify-content: center;
  color: var(--text3); font-size: 10px;
  transition: background var(--transition), color var(--transition), width var(--transition);
  z-index: 10;
}
.elements-toggle:hover { background: var(--bg3); color: var(--text); width: 22px; }

.elements-panel {
  width: 240px; flex-shrink: 0;
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  display: flex; flex-direction: column;
  overflow: hidden;
  box-shadow: var(--shadow);
  /* Animated collapse */
  transition: width 0.28s cubic-bezier(0.4,0,0.2,1),
              opacity 0.22s ease,
              margin 0.28s ease,
              border-color 0.28s ease;
}
.elements-panel.collapsed {
  width: 0;
  opacity: 0;
  margin-left: 0;
  border-color: transparent;
  box-shadow: none;
  pointer-events: none;
}

/* Preview wrap needs relative so toggles can anchor to it */
.preview-wrap { position: relative; }
.sidebar-section {
  padding: 14px 14px 6px;
  font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
  text-transform: uppercase; color: var(--text3);
}
.sidebar-scroll { flex: 1; overflow-y: auto; padding-bottom: 12px; }
.sidebar-scroll::-webkit-scrollbar { width: 3px; }
.sidebar-scroll::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

.file-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 16px; cursor: pointer;
  transition: background var(--transition), color var(--transition);
  border-radius: 0; color: var(--text2); font-size: 13px;
  position: relative;
}
.file-item:hover { background: var(--bg3); color: var(--text); }
.file-item.active {
  background: rgba(124,108,252,0.1);
  color: var(--accent2);
}
.file-item.active::before {
  content: '';
  position: absolute; left: 0; top: 0; bottom: 0;
  width: 3px; background: var(--accent);
  border-radius: 0 2px 2px 0;
}
.file-icon { font-size: 14px; opacity: 0.7; flex-shrink: 0; }
.file-name { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-family: 'DM Mono', monospace; font-size: 12px; }
.file-folder { color: var(--text3); font-size: 11px; font-family: 'DM Mono', monospace; }

.folder-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 16px; cursor: pointer;
  color: var(--text3); font-size: 12px;
  font-family: 'DM Mono', monospace;
  transition: color var(--transition);
}
.folder-item:hover { color: var(--text2); }
.folder-toggle { transition: transform var(--transition); }
.folder-toggle.open { transform: rotate(90deg); }

.sidebar-modules { border-top: 1px solid var(--border); }
.module-item {
  display: flex; align-items: center; gap: 8px;
  padding: 8px 16px; cursor: pointer;
  color: var(--text2); font-size: 13px;
  transition: background var(--transition), color var(--transition);
}
.module-item:hover { background: var(--bg3); color: var(--text); }
.module-item.active { color: var(--accent2); }
.module-dot {
  width: 6px; height: 6px; border-radius: 50%;
  background: var(--accent); flex-shrink: 0;
  box-shadow: 0 0 6px var(--accent-glow);
}

/* MAIN CONTENT */
.main { flex: 1; display: flex; flex-direction: column; overflow: hidden; }

/* EDITOR PANEL */
.editor-panel {
  flex: 1; display: flex; overflow: hidden;
  padding: 16px; gap: 16px;
  position: relative;
}

/* PREVIEW */
.preview-wrap {
  flex: 1; display: flex; flex-direction: column;
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  overflow: hidden;
  box-shadow: var(--shadow);
}
.preview-bar {
  height: 40px; flex-shrink: 0;
  background: var(--bg3);
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; gap: 8px;
  padding: 0 14px;
}
.preview-dots { display: flex; gap: 6px; }
.preview-dot { width: 10px; height: 10px; border-radius: 50%; }
.preview-dot:nth-child(1) { background: var(--red); }
.preview-dot:nth-child(2) { background: var(--yellow); }
.preview-dot:nth-child(3) { background: var(--green); }
.preview-url {
  flex: 1; background: var(--bg4); border: 1px solid var(--border);
  border-radius: 20px; padding: 4px 14px;
  font-size: 11px; color: var(--text3); font-family: 'DM Mono', monospace;
  text-overflow: ellipsis; overflow: hidden; white-space: nowrap;
}
.preview-frame-wrap { flex: 1; position: relative; overflow: hidden; }
.preview-frame {
  width: 100%; height: 100%; border: none;
  background: #fff;
}
.preview-overlay {
  position: absolute; inset: 0;
  pointer-events: none;
  z-index: 10;
}
.preview-loading {
  position: absolute; inset: 0;
  background: var(--bg2);
  display: flex; align-items: center; justify-content: center;
  flex-direction: column; gap: 12px;
  z-index: 20;
}
.spinner {
  width: 32px; height: 32px;
  border: 2px solid var(--border2);
  border-top-color: var(--accent);
  border-radius: 50%;
  animation: spin 0.7s linear infinite;
}
@keyframes spin { to { transform: rotate(360deg); } }
.preview-empty {
  display: flex; align-items: center; justify-content: center;
  height: 100%; flex-direction: column; gap: 12px;
  color: var(--text3);
}
.preview-empty-icon { font-size: 48px; opacity: 0.3; }
.preview-empty-text { font-size: 14px; }

/* ELEMENTS PANEL */
.elements-header {
  padding: 14px 16px;
  border-bottom: 1px solid var(--border);
  font-size: 11px; font-weight: 700; letter-spacing: 1px;
  text-transform: uppercase; color: var(--text3);
  display: flex; align-items: center; justify-content: space-between;
}
.elements-count {
  background: var(--bg4); border: 1px solid var(--border);
  border-radius: 20px; padding: 2px 8px;
  font-size: 10px; color: var(--accent2);
  font-family: 'DM Mono', monospace;
}
.elements-search {
  padding: 10px 12px;
  border-bottom: 1px solid var(--border);
}
.elements-search input {
  width: 100%; background: var(--bg3); border: 1px solid var(--border);
  border-radius: 6px; padding: 6px 10px;
  color: var(--text); font-size: 12px; font-family: 'DM Mono', monospace;
  outline: none; transition: border-color var(--transition);
}
.elements-search input:focus { border-color: var(--accent); }
.elements-list { flex: 1; overflow-y: auto; padding: 6px; }
.elements-list::-webkit-scrollbar { width: 3px; }
.elements-list::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

.el-item {
  display: flex; align-items: flex-start; gap: 8px;
  padding: 8px 10px; border-radius: 8px;
  cursor: pointer; transition: background var(--transition);
  margin-bottom: 2px;
}
.el-item:hover { background: var(--bg3); }
.el-item.active { background: rgba(124,108,252,0.12); }
.el-tag {
  font-size: 9px; font-weight: 700; letter-spacing: 0.5px;
  padding: 2px 6px; border-radius: 4px; flex-shrink: 0;
  font-family: 'DM Mono', monospace; margin-top: 1px;
  text-transform: uppercase;
}
.el-tag.h { background: rgba(124,108,252,0.2); color: var(--accent2); }
.el-tag.p { background: rgba(61,255,160,0.1); color: var(--green); }
.el-tag.a { background: rgba(255,208,96,0.1); color: var(--yellow); }
.el-tag.span { background: rgba(255,95,126,0.1); color: var(--red); }
.el-tag.btn { background: rgba(124,108,252,0.15); color: var(--accent); }
.el-tag.li { background: rgba(144,144,168,0.1); color: var(--text3); }
.el-content {
  flex: 1; overflow: hidden;
  font-size: 12px; color: var(--text2);
  font-family: 'DM Mono', monospace;
  line-height: 1.4;
}
.el-content .el-id { font-size: 10px; color: var(--text3); }
.el-content .el-text { overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

/* EDIT POPUP */
.edit-popup {
  position: fixed; z-index: 9999;
  background: var(--bg2);
  border: 1px solid var(--border2);
  border-radius: var(--radius-lg);
  padding: 16px;
  min-width: 360px; max-width: 480px;
  box-shadow: var(--shadow), 0 0 0 1px rgba(124,108,252,0.15), 0 0 40px rgba(124,108,252,0.1);
  animation: popIn 0.18s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes popIn {
  from { opacity: 0; transform: scale(0.92) translateY(-8px); }
  to   { opacity: 1; transform: none; }
}
.edit-popup-header {
  display: flex; align-items: center; justify-content: space-between;
  margin-bottom: 12px;
}
.edit-popup-tag {
  font-size: 10px; font-weight: 700; letter-spacing: 1px;
  text-transform: uppercase; color: var(--accent2);
  font-family: 'DM Mono', monospace;
}
.edit-popup-id { font-size: 10px; color: var(--text3); font-family: 'DM Mono', monospace; }
.edit-popup-close {
  width: 24px; height: 24px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: 6px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; color: var(--text3);
  transition: all var(--transition);
}
.edit-popup-close:hover { background: var(--bg4); color: var(--text); }
/* Quill editor dark theme */
.ql-toolbar.ql-snow {
  background: var(--bg4);
  border: 1px solid var(--border) !important;
  border-bottom: none !important;
  border-radius: var(--radius) var(--radius) 0 0;
  padding: 6px 8px;
  display: flex; flex-wrap: wrap; gap: 2px;
}
.ql-container.ql-snow {
  background: var(--bg3);
  border: 1px solid var(--border) !important;
  border-radius: 0 0 var(--radius) var(--radius);
  font-family: 'Syne', sans-serif;
  font-size: 13px;
  color: var(--text);
  min-height: 90px;
}
.ql-editor { min-height: 90px; padding: 10px 12px; line-height: 1.6; }
.ql-editor.ql-blank::before { color: var(--text3); font-style: normal; }
.ql-snow .ql-stroke { stroke: var(--text2) !important; }
.ql-snow .ql-fill  { fill:   var(--text2) !important; }
.ql-snow .ql-picker-label { color: var(--text2) !important; }
.ql-snow .ql-picker-options {
  background: var(--bg4) !important;
  border: 1px solid var(--border2) !important;
  border-radius: var(--radius);
  box-shadow: var(--shadow);
}
.ql-snow .ql-picker-item { color: var(--text2) !important; }
.ql-snow .ql-picker-item:hover,
.ql-snow button:hover .ql-stroke,
.ql-snow button.ql-active .ql-stroke { stroke: var(--accent2) !important; }
.ql-snow button:hover .ql-fill,
.ql-snow button.ql-active .ql-fill  { fill: var(--accent2) !important; }
.ql-snow .ql-picker-label:hover,
.ql-snow .ql-picker-item:hover { color: var(--accent2) !important; }
.ql-snow .ql-picker-item.ql-selected { color: var(--accent) !important; }
.ql-color-picker .ql-picker-options { width: 152px !important; padding: 6px !important; }
.ql-color-picker .ql-picker-item {
  width: 18px !important; height: 18px !important;
  border-radius: 3px; margin: 2px;
}
.ql-toolbar.ql-snow .ql-formats { margin-right: 6px; }
.edit-actions {
  display: flex; gap: 8px; margin-top: 10px;
}
.edit-btn {
  flex: 1; padding: 8px 12px;
  border-radius: 8px; border: 1px solid var(--border);
  font-family: 'Syne', sans-serif; font-size: 12px; font-weight: 600;
  cursor: pointer; transition: all var(--transition);
}
.edit-btn.confirm {
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border-color: transparent; color: #fff;
  box-shadow: 0 2px 12px var(--accent-glow);
}
.edit-btn.confirm:hover { box-shadow: 0 4px 20px var(--accent-glow); }
.edit-btn.cancel {
  background: var(--bg3); color: var(--text2);
}
.edit-btn.cancel:hover { background: var(--bg4); color: var(--text); }

/* EDIT POPUP TABS */
.popup-tabs {
  display: flex; gap: 4px; margin-bottom: 12px;
  background: var(--bg3); border-radius: 8px; padding: 3px;
}
.popup-tab {
  flex: 1; padding: 5px 10px;
  border: none; border-radius: 6px;
  background: transparent; color: var(--text3);
  font-family: 'Syne', sans-serif; font-size: 11px; font-weight: 700;
  letter-spacing: 0.5px; cursor: pointer;
  transition: all var(--transition);
}
.popup-tab.active { background: var(--bg4); color: var(--text); box-shadow: 0 1px 4px rgba(0,0,0,0.3); }
.popup-tab-panel { display: none; }
.popup-tab-panel.active { display: block; }

/* ATTRIBUTE EDITOR */
.attr-list { display: flex; flex-direction: column; gap: 6px; max-height: 260px; overflow-y: auto; margin-bottom: 10px; }
.attr-list::-webkit-scrollbar { width: 3px; }
.attr-list::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }
.attr-row {
  display: flex; align-items: center; gap: 6px;
}
.attr-name {
  flex-shrink: 0; width: 100px;
  padding: 6px 8px;
  background: var(--bg4); border: 1px solid var(--border);
  border-radius: 6px; color: var(--text3);
  font-family: 'DM Mono', monospace; font-size: 11px;
  overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
  cursor: default;
}
.attr-name.custom {
  background: var(--bg3);
  border: 1px solid var(--border2); color: var(--text2);
  cursor: text; outline: none;
  transition: border-color var(--transition);
}
.attr-name.custom:focus { border-color: var(--accent); color: var(--text); }
.attr-val {
  flex: 1; padding: 6px 8px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: 6px; color: var(--text);
  font-family: 'DM Mono', monospace; font-size: 11px;
  outline: none; transition: border-color var(--transition), box-shadow var(--transition);
}
.attr-val:focus { border-color: var(--accent); box-shadow: 0 0 0 2px var(--accent-glow); }
.attr-del {
  flex-shrink: 0; width: 24px; height: 24px;
  background: transparent; border: 1px solid transparent;
  border-radius: 5px; cursor: pointer;
  color: var(--text3); font-size: 14px; line-height: 1;
  display: flex; align-items: center; justify-content: center;
  transition: all var(--transition);
}
.attr-del:hover { background: rgba(255,95,126,0.12); border-color: rgba(255,95,126,0.3); color: var(--red); }
.attr-add-row {
  display: flex; gap: 6px; margin-top: 2px;
}
.attr-add-btn {
  padding: 5px 12px; border-radius: 6px;
  border: 1px dashed var(--border2); background: transparent;
  color: var(--text3); font-family: 'Syne', sans-serif;
  font-size: 11px; font-weight: 600; cursor: pointer;
  transition: all var(--transition); white-space: nowrap;
}
.attr-add-btn:hover { border-color: var(--accent); color: var(--accent2); background: var(--accent-glow); }
.attr-protected { font-size: 10px; color: var(--text3); font-family: 'DM Mono', monospace; margin-top: 6px; }

/* HIGHLIGHT ON HOVER */
.cms-hover-highlight {
  outline: 2px dashed rgba(124,108,252,0.6) !important;
  outline-offset: 2px !important;
  cursor: pointer !important;
  transition: outline 0.1s !important;
}
.cms-selected-highlight {
  outline: 2px solid var(--accent) !important;
  outline-offset: 2px !important;
}

/* STATUS BAR */
.statusbar {
  height: 26px; flex-shrink: 0;
  background: var(--bg2);
  border-top: 1px solid var(--border);
  display: flex; align-items: center;
  padding: 0 16px; gap: 16px;
  font-size: 11px; color: var(--text3); font-family: 'DM Mono', monospace;
}
.status-item { display: flex; align-items: center; gap: 5px; }
.status-dot { width: 5px; height: 5px; border-radius: 50%; background: var(--green); }
.status-dot.warn { background: var(--yellow); }
.status-dot.err  { background: var(--red); }

/* MODULES PANEL */
.modules-panel {
  flex: 1; padding: 24px; overflow-y: auto;
}
.modules-panel::-webkit-scrollbar { width: 4px; }
.modules-panel::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }
.modules-grid {
  display: grid; grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
  gap: 16px; margin-top: 20px;
}
.module-card {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 20px;
  transition: border-color var(--transition), box-shadow var(--transition), transform var(--transition);
  cursor: pointer;
}
.module-card:hover {
  border-color: var(--accent);
  box-shadow: 0 0 0 1px var(--accent-glow), var(--shadow);
  transform: translateY(-2px);
}
.module-card-icon { font-size: 28px; margin-bottom: 12px; }
.module-card-name { font-size: 15px; font-weight: 700; margin-bottom: 4px; }
.module-card-desc { font-size: 12px; color: var(--text2); line-height: 1.5; }
.module-card-badge {
  display: inline-block; margin-top: 10px;
  padding: 3px 8px; border-radius: 20px; font-size: 10px; font-weight: 700;
  font-family: 'DM Mono', monospace; letter-spacing: 0.5px;
}
.module-card-badge.active { background: rgba(61,255,160,0.1); color: var(--green); border: 1px solid rgba(61,255,160,0.2); }
.module-card-badge.disabled { background: var(--bg4); color: var(--text3); border: 1px solid var(--border); }

.section-title {
  font-size: 20px; font-weight: 800; margin-bottom: 4px;
}
.section-sub { font-size: 13px; color: var(--text2); }

/* TOAST */
.toast-container {
  position: fixed; bottom: 36px; right: 20px;
  display: flex; flex-direction: column; gap: 8px;
  z-index: 99999;
}
.toast {
  padding: 12px 18px;
  background: var(--bg2); border: 1px solid var(--border2);
  border-radius: var(--radius); font-size: 13px; color: var(--text);
  box-shadow: var(--shadow);
  animation: toastIn 0.25s cubic-bezier(0.34,1.56,0.64,1) both;
  display: flex; align-items: center; gap: 10px;
  min-width: 220px;
}
.toast.success { border-left: 3px solid var(--green); }
.toast.error   { border-left: 3px solid var(--red); }
.toast.info    { border-left: 3px solid var(--accent); }
@keyframes toastIn { from { opacity: 0; transform: translateX(20px); } to { opacity: 1; transform: none; } }

/* DIALOG */
.dialog-overlay {
  position: fixed; inset: 0; z-index: 9998;
  background: rgba(0,0,0,0.6); backdrop-filter: blur(4px);
  display: flex; align-items: center; justify-content: center;
  animation: fadeIn 0.15s ease;
}
@keyframes fadeIn { from { opacity: 0; } to { opacity: 1; } }
.dialog-box {
  background: var(--bg2); border: 1px solid var(--border2);
  border-radius: var(--radius-lg); padding: 28px; width: 440px;
  box-shadow: var(--shadow), 0 0 40px rgba(124,108,252,0.1);
  animation: popIn 0.2s cubic-bezier(0.34,1.56,0.64,1) both;
}
.dialog-title { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
.dialog-body  { color: var(--text2); font-size: 13px; margin-bottom: 20px; line-height: 1.6; }
.dialog-actions { display: flex; gap: 10px; justify-content: flex-end; }
.dialog-input {
  width: 100%; background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 10px 14px;
  color: var(--text); font-family: 'DM Mono', monospace; font-size: 14px;
  outline: none; margin-bottom: 16px;
  transition: border-color var(--transition), box-shadow var(--transition);
}
.dialog-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }

/* LICENSE PANEL */
.license-panel { padding: 28px; max-width: 560px; }
.license-status {
  padding: 16px 20px; border-radius: var(--radius-lg);
  background: var(--bg2); border: 1px solid var(--border);
  margin-bottom: 20px; display: flex; align-items: center; gap: 14px;
}
.license-icon { font-size: 28px; }
.license-key-display {
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 10px 14px;
  font-family: 'DM Mono', monospace; font-size: 12px; color: var(--text2);
  word-break: break-all; margin-bottom: 16px;
}

/* VIEWPORT SWITCHER */
.vp-switcher {
  display: flex; gap: 2px;
  background: var(--bg4); border-radius: 7px; padding: 2px;
}
.vp-btn {
  padding: 3px 9px; border-radius: 5px; border: none;
  background: transparent; color: var(--text3);
  font-size: 11px; font-weight: 600; cursor: pointer;
  font-family: 'Syne', sans-serif; transition: all var(--transition);
  white-space: nowrap;
}
.vp-btn:hover { color: var(--text2); }
.vp-btn.active { background: var(--bg2); color: var(--text); box-shadow: 0 1px 4px rgba(0,0,0,0.4); }

/* PREVIEW FRAME VIEWPORT MODES */
.preview-frame-wrap.vp-mobile  { display: flex; justify-content: center; background: var(--bg); }
.preview-frame-wrap.vp-tablet  { display: flex; justify-content: center; background: var(--bg); }
.preview-frame-wrap.vp-mobile  .preview-frame { max-width: 375px; border-left: 1px solid var(--border); border-right: 1px solid var(--border); }
.preview-frame-wrap.vp-tablet  .preview-frame { max-width: 768px; border-left: 1px solid var(--border); border-right: 1px solid var(--border); }

/* UNDO/REDO BUTTONS */
.tb-btn.icon-btn {
  padding: 7px 9px; font-size: 14px; line-height: 1;
  min-width: 0;
}
.tb-btn:disabled { opacity: 0.35; cursor: not-allowed; transform: none !important; }

/* ALPHA BADGE */
.alpha-badge {
  display: inline-flex; align-items: center;
  padding: 2px 7px; border-radius: 20px;
  background: rgba(255,208,96,0.12); border: 1px solid rgba(255,208,96,0.3);
  color: var(--yellow); font-size: 9px; font-weight: 700;
  letter-spacing: 1px; text-transform: uppercase;
  font-family: 'DM Mono', monospace;
  flex-shrink: 0; white-space: nowrap;
}

/* FEEDBACK BUTTON */
.feedback-btn {
  padding: 5px 11px; border-radius: 8px;
  border: 1px solid var(--border); background: var(--bg3);
  color: var(--text3); font-size: 11px; font-weight: 600;
  font-family: 'Syne', sans-serif; cursor: pointer;
  transition: all var(--transition); white-space: nowrap;
  display: flex; align-items: center; gap: 5px;
}
.feedback-btn:hover { border-color: var(--yellow); color: var(--yellow); background: rgba(255,208,96,0.06); }

/* SETTINGS PANEL */
.settings-panel {
  flex: 1; padding: 28px 32px; overflow-y: auto; max-width: 640px;
}
.settings-panel::-webkit-scrollbar { width: 4px; }
.settings-panel::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }
.settings-section {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 24px;
  margin-bottom: 20px;
}
.settings-section-title {
  font-size: 13px; font-weight: 700; letter-spacing: 0.5px;
  color: var(--text); margin-bottom: 4px;
}
.settings-section-sub {
  font-size: 12px; color: var(--text3); font-family: 'DM Mono', monospace;
  margin-bottom: 20px;
}
.settings-row { margin-bottom: 14px; }
.settings-label {
  display: block; font-size: 11px; font-weight: 600;
  letter-spacing: 1px; text-transform: uppercase;
  color: var(--text3); margin-bottom: 6px;
}
.settings-input {
  width: 100%; padding: 10px 13px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text);
  font-family: 'DM Mono', monospace; font-size: 13px;
  outline: none; transition: border-color var(--transition), box-shadow var(--transition);
}
.settings-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.settings-input::placeholder { color: var(--text3); }
.settings-save-btn {
  padding: 9px 20px; border-radius: var(--radius);
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border: none; color: #fff;
  font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700;
  cursor: pointer; transition: all var(--transition);
  box-shadow: 0 2px 12px var(--accent-glow); margin-top: 4px;
}
.settings-save-btn:hover { box-shadow: 0 4px 20px var(--accent-glow); transform: translateY(-1px); }
.settings-msg {
  margin-top: 10px; padding: 9px 13px; border-radius: var(--radius);
  font-size: 12px; font-family: 'DM Mono', monospace; display: none;
}
.settings-msg.ok  { background: rgba(61,255,160,0.1); border: 1px solid rgba(61,255,160,0.25); color: var(--green); }
.settings-msg.err { background: rgba(255,95,126,0.1); border: 1px solid rgba(255,95,126,0.25); color: var(--red); }

/* FEEDBACK DROPDOWN */
.feedback-menu {
  position: absolute; top: 44px; right: 0;
  background: var(--bg2); border: 1px solid var(--border2);
  border-radius: var(--radius); min-width: 200px;
  box-shadow: var(--shadow); z-index: 500; overflow: hidden;
  animation: popIn 0.15s cubic-bezier(0.34,1.56,0.64,1) both;
}
.feedback-menu-item {
  padding: 11px 16px; cursor: pointer; font-size: 13px;
  color: var(--text2); display: flex; align-items: center; gap: 10px;
  transition: background var(--transition), color var(--transition);
}
.feedback-menu-item:hover { background: var(--bg3); color: var(--text); }
.feedback-menu-sep { height: 1px; background: var(--border); margin: 4px 0; }

/* ═══════════════════════════════════════════════
   MOBILE HAMBURGER DRAWER
═══════════════════════════════════════════════ */
.hamburger {
  display: none;
  width: 36px; height: 36px;
  border: 1px solid var(--border); border-radius: 8px;
  background: var(--bg3); cursor: pointer;
  flex-direction: column; align-items: center; justify-content: center; gap: 5px;
  flex-shrink: 0; transition: all var(--transition);
}
.hamburger:hover { background: var(--bg4); border-color: var(--border2); }
.hamburger span {
  display: block; width: 16px; height: 1.5px;
  background: var(--text2); border-radius: 2px;
  transition: all 0.25s ease;
}
.hamburger.open span:nth-child(1) { transform: translateY(6.5px) rotate(45deg); }
.hamburger.open span:nth-child(2) { opacity: 0; transform: scaleX(0); }
.hamburger.open span:nth-child(3) { transform: translateY(-6.5px) rotate(-45deg); }

/* Drawer overlay */
.mobile-drawer-overlay {
  display: none;
  position: fixed; inset: 0; z-index: 300;
  background: rgba(0,0,0,0.55); backdrop-filter: blur(3px);
  animation: fadeIn 0.2s ease;
}
.mobile-drawer-overlay.open { display: block; }

/* Drawer panel */
.mobile-drawer {
  position: fixed; top: 0; left: 0; bottom: 0; z-index: 301;
  width: min(320px, 88vw);
  background: var(--bg2); border-right: 1px solid var(--border);
  display: flex; flex-direction: column;
  transform: translateX(-100%);
  transition: transform 0.28s cubic-bezier(0.4,0,0.2,1);
  overflow: hidden;
}
.mobile-drawer.open { transform: translateX(0); }

.drawer-header {
  padding: 16px 20px;
  border-bottom: 1px solid var(--border);
  display: flex; align-items: center; justify-content: space-between;
  flex-shrink: 0;
}
.drawer-close {
  width: 30px; height: 30px; border-radius: 8px;
  border: 1px solid var(--border); background: var(--bg3);
  color: var(--text2); font-size: 16px; cursor: pointer;
  display: flex; align-items: center; justify-content: center;
  transition: all var(--transition);
}
.drawer-close:hover { background: var(--bg4); color: var(--text); }

.drawer-section-label {
  padding: 14px 20px 6px;
  font-size: 10px; font-weight: 700; letter-spacing: 1.5px;
  text-transform: uppercase; color: var(--text3);
}

.drawer-scroll { flex: 1; overflow-y: auto; padding-bottom: 16px; }
.drawer-scroll::-webkit-scrollbar { width: 3px; }
.drawer-scroll::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

.drawer-divider { height: 1px; background: var(--border); margin: 8px 0; }

/* Drawer action row */
.drawer-actions {
  padding: 12px 16px;
  display: flex; flex-wrap: wrap; gap: 8px;
  border-top: 1px solid var(--border);
  flex-shrink: 0;
}

/* TAP HINT BANNER */
.tap-hint {
  position: absolute; bottom: 20px; left: 50%;
  transform: translateX(-50%);
  background: var(--bg2); border: 1px solid var(--border2);
  border-radius: 14px; padding: 14px 16px;
  display: flex; flex-direction: column; gap: 10px;
  align-items: flex-start;
  box-shadow: var(--shadow);
  z-index: 50;
  width: calc(100% - 32px); max-width: 320px;
  animation: hintSlideUp 0.4s cubic-bezier(0.34,1.56,0.64,1) both;
}
@keyframes hintSlideUp {
  from { opacity: 0; transform: translateX(-50%) translateY(16px); }
  to   { opacity: 1; transform: translateX(-50%) translateY(0); }
}
.tap-hint-top { display: flex; align-items: center; gap: 10px; }
.tap-hint-icon { font-size: 22px; flex-shrink: 0; }
.tap-hint-text { font-size: 13px; color: var(--text2); line-height: 1.5; }
.tap-hint-text strong { color: var(--text); font-weight: 600; }
.tap-hint-actions { display: flex; gap: 8px; width: 100%; }
.tap-hint-btn {
  flex: 1; padding: 7px 10px; border-radius: 8px;
  font-size: 12px; font-weight: 600; cursor: pointer;
  border: 1px solid var(--border); background: var(--bg3);
  color: var(--text2); font-family: 'Syne', sans-serif;
  transition: all var(--transition); text-align: center;
}
.tap-hint-btn:hover { background: var(--bg4); color: var(--text); }
.tap-hint-btn.dismiss {
  background: rgba(124,108,252,0.1); border-color: rgba(124,108,252,0.3);
  color: var(--accent2);
}
.tap-hint-btn.dismiss:hover { background: rgba(124,108,252,0.2); }

/* ═══════════════════════════════════════════════
   RESPONSIVE BREAKPOINTS
═══════════════════════════════════════════════ */
@media (max-width: 1035px) {
  /* Show hamburger, hide desktop sidebar */
  .hamburger { display: flex; }
  .sidebar { display: none; }
  /* Hide sidebar toggle tabs — not needed on mobile/tablet */
  .sidebar-toggle, .elements-toggle { display: none; }

  /* Topbar: only hamburger, logo, save, avatar visible */
  .topbar-breadcrumb { display: none; }
  .topbar-actions { gap: 6px; }

  /* Hide everything in topbar actions except save + avatar */
  #feedback-toggle { display: none; }
  #btn-theme       { display: none; }
  #btn-undo        { display: none; }
  #btn-redo        { display: none; }
  #btn-discard     { display: none; }
  #btn-inject      { display: none; }
  .tb-sep          { display: none; }

  /* Editor panel: full width, no elements panel */
  .editor-panel { padding: 8px; gap: 0; }
  .elements-panel { display: none; }
  .preview-wrap { border-radius: 10px; }

  /* Modules/settings/license panels get padding fix */
  .modules-panel, .settings-panel, .license-panel { padding: 16px; }

  /* Viewport switcher hidden on mobile */
  .vp-switcher { display: none; }

  /* Topbar logo area */
  .topbar-logo { border-right: none; padding: 0 0 0 4px; }
  .logo-img-dark, .logo-img-light { height: 40px; }

  /* Status bar compact */
  .statusbar { font-size: 10px; padding: 0 10px; gap: 8px; }
  #status-history, #status-chars { display: none; }
}

@media (max-width: 600px) {
  .logo-img-dark, .logo-img-light { height: 34px; }
  .preview-bar { padding: 0 8px; gap: 6px; }
  #preview-url { display: none; }
}

/* SCROLLBAR GLOBAL */
::-webkit-scrollbar { width: 6px; height: 6px; }
::-webkit-scrollbar-track { background: transparent; }
::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

/* ═══════════════════════════════════════════════
   NEW SIDEBAR NAVIGATION
═══════════════════════════════════════════════ */
/* .sidebar styles kept in original block above — no duplicate */

.sidebar-nav {
  display: flex;
  flex-direction: column;
  height: 100%;
  overflow: hidden;
}
.sidebar-nav-top {
  flex: 1;
  overflow-y: auto;
  overflow-x: hidden;
  padding-bottom: 8px;
}
.sidebar-nav-top::-webkit-scrollbar { width: 3px; }
.sidebar-nav-top::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }
.sidebar-nav-bottom {
  flex-shrink: 0;
  border-top: 1px solid var(--border);
  padding: 6px 0;
}

.nav-group-label {
  padding: 16px 16px 5px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 1.8px;
  text-transform: uppercase;
  color: var(--text3);
  font-family: 'DM Mono', monospace;
  display: flex;
  align-items: center;
  gap: 6px;
}
.nav-group-label::after {
  content: '';
  flex: 1;
  height: 1px;
  background: var(--border);
  margin-left: 2px;
}

.nav-item {
  display: flex;
  align-items: center;
  gap: 9px;
  padding: 8px 14px;
  cursor: pointer;
  transition: background var(--transition), color var(--transition);
  border-radius: 0;
  color: var(--text2);
  font-size: 13px;
  position: relative;
  user-select: none;
  min-height: 36px;
  white-space: nowrap;
}
.nav-item:hover { background: var(--bg3); color: var(--text); }
.nav-item.active {
  background: rgba(124,108,252,0.1);
  color: var(--accent2);
}
.nav-item.active::before {
  content: '';
  position: absolute;
  left: 0; top: 4px; bottom: 4px;
  width: 3px;
  background: var(--accent);
  border-radius: 0 2px 2px 0;
}
.nav-item-icon { font-size: 15px; flex-shrink: 0; width: 20px; text-align: center; }
.nav-item-label { flex: 1; font-size: 13px; }
.nav-item-badge {
  font-size: 8px;
  padding: 2px 6px;
  border-radius: 10px;
  font-family: 'DM Mono', monospace;
  letter-spacing: 0.5px;
  font-weight: 700;
  flex-shrink: 0;
}
.badge-soon { background: rgba(255,208,96,0.12); color: var(--yellow); border: 1px solid rgba(255,208,96,0.25); }
.badge-new  { background: rgba(61,255,160,0.1);  color: var(--green);  border: 1px solid rgba(61,255,160,0.2); }
.badge-live { background: rgba(124,108,252,0.12); color: var(--accent2); border: 1px solid rgba(124,108,252,0.2); }

/* File tree section */
.file-tree-section {
  flex-shrink: 0;
  max-height: 260px;
  overflow-y: auto;
  border-bottom: 1px solid var(--border);
}
.file-tree-section::-webkit-scrollbar { width: 3px; }
.file-tree-section::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

/* ═══════════════════════════════════════════════
   SETTINGS SCREEN (full separate screen)
═══════════════════════════════════════════════ */
.settings-screen {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: none;
  flex-direction: column;
  overflow: hidden;
  background: var(--bg);
}
.settings-screen.active { display: flex; }
.settings-topbar {
  height: 48px;
  flex-shrink: 0;
  background: var(--bg2);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 0 20px;
}
.settings-back-btn {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 12px;
  color: var(--text2);
  cursor: pointer;
  padding: 5px 10px;
  border-radius: 6px;
  border: 1px solid var(--border);
  background: var(--bg3);
  font-family: 'Syne', sans-serif;
  font-weight: 600;
  transition: all var(--transition);
}
.settings-back-btn:hover { color: var(--text); background: var(--bg4); }
.settings-screen-title { font-size: 14px; font-weight: 700; }

.settings-layout {
  display: grid;
  grid-template-columns: 220px 1fr;
  flex: 1;
  overflow: hidden;
}
.settings-sidebar {
  background: var(--bg2);
  border-right: 1px solid var(--border);
  overflow-y: auto;
  padding: 12px 0;
}
.settings-sidebar::-webkit-scrollbar { width: 3px; }
.settings-nav-item {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 9px 16px;
  cursor: pointer;
  font-size: 13px;
  color: var(--text2);
  transition: all var(--transition);
  border-left: 3px solid transparent;
}
.settings-nav-item:hover { background: var(--bg3); color: var(--text); }
.settings-nav-item.active {
  background: rgba(124,108,252,0.08);
  color: var(--accent2);
  border-left-color: var(--accent);
}
.settings-nav-icon { font-size: 14px; width: 18px; text-align: center; flex-shrink: 0; }
.settings-nav-group {
  padding: 14px 16px 5px;
  font-size: 9px;
  font-weight: 700;
  letter-spacing: 1.5px;
  text-transform: uppercase;
  color: var(--text3);
  font-family: 'DM Mono', monospace;
}
.settings-content {
  overflow-y: auto;
  padding: 28px 32px;
  max-width: 760px;
}
.settings-content::-webkit-scrollbar { width: 4px; }
.settings-content::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

.settings-page { display: none; }
.settings-page.active { display: block; }

.settings-page-title { font-size: 20px; font-weight: 800; margin-bottom: 4px; }
.settings-page-sub { font-size: 13px; color: var(--text2); margin-bottom: 24px; font-family: 'DM Mono', monospace; }

.set-section {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  padding: 22px;
  margin-bottom: 18px;
}
.set-section-title {
  font-size: 13px; font-weight: 700; margin-bottom: 3px;
}
.set-section-sub {
  font-size: 11px; color: var(--text3); font-family: 'DM Mono', monospace;
  margin-bottom: 18px;
}
.set-row { margin-bottom: 14px; }
.set-row:last-child { margin-bottom: 0; }
.set-label {
  display: flex; align-items: center; gap: 6px;
  font-size: 11px; font-weight: 600; letter-spacing: 1px;
  text-transform: uppercase; color: var(--text3);
  margin-bottom: 6px;
}
.set-input {
  width: 100%; padding: 10px 13px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text);
  font-family: 'DM Mono', monospace; font-size: 13px;
  outline: none;
  transition: border-color var(--transition), box-shadow var(--transition);
}
.set-input:focus { border-color: var(--accent); box-shadow: 0 0 0 3px var(--accent-glow); }
.set-input::placeholder { color: var(--text3); }
.set-input-row { display: flex; gap: 10px; }
.set-input-row .set-input { flex: 1; }
.set-select {
  width: 100%; padding: 10px 13px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text);
  font-family: 'DM Mono', monospace; font-size: 13px;
  outline: none; cursor: pointer;
  transition: border-color var(--transition);
}
.set-select:focus { border-color: var(--accent); }
.set-toggle-row {
  display: flex; align-items: center; justify-content: space-between;
  padding: 10px 0; border-bottom: 1px solid var(--border);
}
.set-toggle-row:last-child { border-bottom: none; padding-bottom: 0; }
.set-toggle-info { flex: 1; }
.set-toggle-info .set-toggle-title { font-size: 13px; font-weight: 600; margin-bottom: 2px; }
.set-toggle-info .set-toggle-desc { font-size: 11px; color: var(--text3); font-family: 'DM Mono', monospace; }
.toggle-switch {
  position: relative; width: 38px; height: 21px; flex-shrink: 0;
}
.toggle-switch input { opacity: 0; width: 0; height: 0; }
.toggle-slider {
  position: absolute; cursor: pointer; inset: 0;
  background: var(--bg4); border: 1px solid var(--border2);
  border-radius: 21px; transition: all 0.2s;
}
.toggle-slider::before {
  content: ''; position: absolute;
  height: 15px; width: 15px; left: 2px; bottom: 2px;
  background: var(--text3); border-radius: 50%;
  transition: all 0.2s;
}
.toggle-switch input:checked + .toggle-slider { background: var(--accent); border-color: var(--accent); }
.toggle-switch input:checked + .toggle-slider::before { transform: translateX(17px); background: #fff; }
.set-save-btn {
  padding: 9px 20px; border-radius: var(--radius);
  background: linear-gradient(135deg, var(--accent), var(--accent2));
  border: none; color: #fff;
  font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700;
  cursor: pointer; transition: all var(--transition);
  box-shadow: 0 2px 12px var(--accent-glow); margin-top: 6px;
}
.set-save-btn:hover { box-shadow: 0 4px 20px var(--accent-glow); transform: translateY(-1px); }
.set-danger-btn {
  padding: 9px 20px; border-radius: var(--radius);
  background: rgba(255,95,126,0.1);
  border: 1px solid rgba(255,95,126,0.3); color: var(--red);
  font-family: 'Syne', sans-serif; font-size: 13px; font-weight: 700;
  cursor: pointer; transition: all var(--transition); margin-top: 6px;
}
.set-danger-btn:hover { background: rgba(255,95,126,0.2); }
.set-hours-grid {
  display: grid;
  grid-template-columns: 90px 1fr 1fr 80px;
  gap: 8px;
  align-items: center;
  margin-bottom: 8px;
}
.set-hours-grid .day-name {
  font-size: 12px; color: var(--text2); font-family: 'DM Mono', monospace;
}
.set-time-input {
  padding: 7px 10px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text);
  font-family: 'DM Mono', monospace; font-size: 12px;
  outline: none; width: 100%;
}
.set-time-input:focus { border-color: var(--accent); }
.color-preview {
  width: 36px; height: 36px; border-radius: var(--radius);
  border: 1px solid var(--border); cursor: pointer; flex-shrink: 0;
}
.set-msg {
  margin-top: 10px; padding: 9px 13px; border-radius: var(--radius);
  font-size: 12px; font-family: 'DM Mono', monospace; display: none;
}
.set-msg.ok  { background: rgba(61,255,160,0.1); border: 1px solid rgba(61,255,160,0.25); color: var(--green); }
.set-msg.err { background: rgba(255,95,126,0.1); border: 1px solid rgba(255,95,126,0.25); color: var(--red); }

/* ═══════════════════════════════════════════════
   MODULE PANELS — COMING SOON STYLE
═══════════════════════════════════════════════ */
.module-panel {
  flex: 1;
  display: none;
  flex-direction: column;
  overflow: hidden;
}
.module-panel.active { display: flex; }

.module-topbar {
  height: 48px;
  flex-shrink: 0;
  background: var(--bg2);
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  padding: 0 20px;
  gap: 12px;
}
.module-topbar-title {
  font-size: 14px;
  font-weight: 700;
  flex: 1;
}
.module-topbar-badge {
  font-size: 10px;
  padding: 3px 10px;
  border-radius: 12px;
  font-family: 'DM Mono', monospace;
  font-weight: 700;
  letter-spacing: 0.5px;
}
.module-body {
  flex: 1;
  overflow-y: auto;
  padding: 24px;
}
.module-body::-webkit-scrollbar { width: 4px; }
.module-body::-webkit-scrollbar-thumb { background: var(--border2); border-radius: 4px; }

/* Coming soon overlay */
.cs-banner {
  display: flex;
  align-items: center;
  gap: 14px;
  background: rgba(255,208,96,0.06);
  border: 1px solid rgba(255,208,96,0.2);
  border-radius: var(--radius-lg);
  padding: 16px 20px;
  margin-bottom: 24px;
}
.cs-banner-icon { font-size: 22px; flex-shrink: 0; }
.cs-banner-text { font-size: 13px; color: var(--text2); line-height: 1.5; }
.cs-banner-text strong { color: var(--yellow); }

/* Placeholder form elements */
.ph-section {
  background: var(--bg2);
  border: 1px solid var(--border);
  border-radius: var(--radius-lg);
  margin-bottom: 18px;
  overflow: hidden;
}
.ph-section-header {
  padding: 14px 18px;
  border-bottom: 1px solid var(--border);
  display: flex;
  align-items: center;
  justify-content: space-between;
  gap: 12px;
}
.ph-section-title { font-size: 14px; font-weight: 700; }
.ph-section-sub { font-size: 12px; color: var(--text3); font-family: 'DM Mono', monospace; margin-top: 2px; }
.ph-section-body { padding: 18px; }
.ph-grid { display: grid; gap: 14px; }
.ph-grid.cols-2 { grid-template-columns: 1fr 1fr; }
.ph-grid.cols-3 { grid-template-columns: 1fr 1fr 1fr; }
.ph-field { display: flex; flex-direction: column; gap: 5px; }
.ph-label {
  font-size: 10px; font-weight: 600; letter-spacing: 1px;
  text-transform: uppercase; color: var(--text3);
}
.ph-input {
  padding: 9px 12px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text3);
  font-family: 'DM Mono', monospace; font-size: 12px;
  cursor: not-allowed; opacity: 0.6;
  pointer-events: none;
}
.ph-textarea {
  padding: 9px 12px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text3);
  font-family: 'DM Mono', monospace; font-size: 12px;
  cursor: not-allowed; opacity: 0.6;
  height: 80px; resize: none;
  pointer-events: none;
}
.ph-select {
  padding: 9px 12px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); color: var(--text3);
  font-family: 'DM Mono', monospace; font-size: 12px;
  cursor: not-allowed; opacity: 0.6;
  pointer-events: none; width: 100%;
}
.ph-btn {
  padding: 8px 16px;
  border-radius: var(--radius);
  border: 1px solid var(--border);
  background: var(--bg3); color: var(--text3);
  font-family: 'Syne', sans-serif; font-size: 12px; font-weight: 600;
  cursor: not-allowed; opacity: 0.5; pointer-events: none;
}
.ph-btn-primary {
  padding: 8px 16px;
  border-radius: var(--radius);
  background: var(--accent); color: #fff;
  font-family: 'Syne', sans-serif; font-size: 12px; font-weight: 700;
  border: none; cursor: not-allowed; opacity: 0.4; pointer-events: none;
}
.ph-table {
  width: 100%; border-collapse: collapse;
  font-size: 12px; font-family: 'DM Mono', monospace;
}
.ph-table th {
  padding: 8px 12px; text-align: left;
  background: var(--bg3); border-bottom: 1px solid var(--border);
  color: var(--text3); font-size: 10px; letter-spacing: 0.8px; text-transform: uppercase;
  font-weight: 700;
}
.ph-table td {
  padding: 10px 12px; border-bottom: 1px solid var(--border);
  color: var(--text2); opacity: 0.5;
}
.ph-table tr:last-child td { border-bottom: none; }
.ph-tag {
  display: inline-flex; align-items: center;
  padding: 2px 8px; border-radius: 10px; font-size: 10px;
  font-family: 'DM Mono', monospace; font-weight: 600;
  background: var(--bg4); color: var(--text3);
  opacity: 0.6; margin: 2px;
}
.ph-color-row { display: flex; gap: 8px; align-items: center; }
.ph-color-chip {
  width: 26px; height: 26px; border-radius: 5px; opacity: 0.4;
  border: 1px solid var(--border);
}
.ph-image-placeholder {
  width: 100%; aspect-ratio: 16/9;
  background: var(--bg3); border: 1px dashed var(--border2);
  border-radius: var(--radius);
  display: flex; align-items: center; justify-content: center;
  color: var(--text3); font-size: 24px; opacity: 0.4;
}
.ph-thumb-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(80px, 1fr));
  gap: 8px;
}
.ph-thumb {
  aspect-ratio: 1;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); opacity: 0.4;
  display: flex; align-items: center; justify-content: center;
  font-size: 20px; color: var(--text3);
}
.ph-calendar {
  display: grid; grid-template-columns: repeat(7, 1fr);
  gap: 4px; margin-top: 12px;
}
.ph-cal-day {
  aspect-ratio: 1; border-radius: 6px;
  background: var(--bg3); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 11px; color: var(--text3); font-family: 'DM Mono', monospace;
  opacity: 0.5;
}
.ph-cal-day.has-event { background: rgba(124,108,252,0.12); border-color: rgba(124,108,252,0.3); color: var(--accent2); opacity: 0.7; }
.ph-cal-day.today { background: var(--accent); color: #fff; opacity: 0.6; }
.ph-stat-row { display: grid; grid-template-columns: repeat(auto-fit, minmax(120px, 1fr)); gap: 12px; margin-bottom: 16px; }
.ph-stat {
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); padding: 14px;
  opacity: 0.5;
}
.ph-stat-val { font-size: 24px; font-weight: 800; color: var(--accent2); font-family: 'DM Mono', monospace; }
.ph-stat-label { font-size: 10px; color: var(--text3); text-transform: uppercase; letter-spacing: 0.8px; margin-top: 4px; }
.ph-progress-row { margin-bottom: 12px; }
.ph-progress-label { display: flex; justify-content: space-between; font-size: 11px; color: var(--text3); margin-bottom: 5px; font-family: 'DM Mono', monospace; }
.ph-progress-bar { height: 6px; background: var(--bg3); border-radius: 3px; overflow: hidden; }
.ph-progress-fill { height: 100%; background: var(--accent); border-radius: 3px; opacity: 0.4; }
.ph-list-item {
  display: flex; align-items: center; gap: 12px;
  padding: 10px 0; border-bottom: 1px solid var(--border);
  opacity: 0.5;
}
.ph-list-item:last-child { border-bottom: none; }
.ph-avatar {
  width: 34px; height: 34px; border-radius: 50%;
  background: var(--bg4); border: 1px solid var(--border);
  display: flex; align-items: center; justify-content: center;
  font-size: 14px; flex-shrink: 0;
}
.ph-list-info { flex: 1; }
.ph-list-title { font-size: 13px; font-weight: 600; color: var(--text); }
.ph-list-sub { font-size: 11px; color: var(--text3); font-family: 'DM Mono', monospace; }

/* star rating placeholder */
.ph-stars { color: var(--yellow); opacity: 0.4; font-size: 14px; letter-spacing: 2px; }

/* map placeholder */
.ph-map {
  width: 100%; height: 180px;
  background: var(--bg3); border: 1px solid var(--border);
  border-radius: var(--radius); position: relative; overflow: hidden; opacity: 0.5;
}
.ph-map-grid {
  position: absolute; inset: 0;
  background-image: linear-gradient(var(--border) 1px, transparent 1px), linear-gradient(90deg, var(--border) 1px, transparent 1px);
  background-size: 30px 30px; opacity: 0.4;
}
.ph-map-pin { position: absolute; top: 40%; left: 50%; transform: translate(-50%,-50%); font-size: 28px; }

/* notification dot */
.nav-item-notif {
  width: 7px; height: 7px; border-radius: 50%;
  background: var(--red); flex-shrink: 0;
}

/* modules overview grid */
.modules-overview-grid {
  display: grid;
  grid-template-columns: repeat(auto-fill, minmax(260px, 1fr));
  gap: 14px;
  padding: 24px;
}
.mod-card {
  background: var(--bg2); border: 1px solid var(--border);
  border-radius: var(--radius-lg); padding: 18px;
  cursor: pointer;
  transition: border-color var(--transition), box-shadow var(--transition), transform var(--transition);
  display: flex; flex-direction: column; gap: 8px;
}
.mod-card:hover {
  border-color: var(--accent);
  box-shadow: 0 0 0 1px var(--accent-glow), var(--shadow);
  transform: translateY(-2px);
}
.mod-card-top { display: flex; align-items: center; gap: 10px; }
.mod-card-icon { font-size: 22px; width: 40px; height: 40px; background: var(--bg3); border-radius: 10px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
.mod-card-name { font-size: 14px; font-weight: 700; flex: 1; }
.mod-card-desc { font-size: 12px; color: var(--text2); line-height: 1.5; }
.mod-card-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 4px; }
.mod-card-category { font-size: 10px; color: var(--text3); font-family: 'DM Mono', monospace; letter-spacing: 0.5px; }

/* responsive for settings screen */
@media (max-width: 768px) {
  .settings-layout { grid-template-columns: 1fr; }
  .settings-sidebar { display: none; border-right: none; border-bottom: 1px solid var(--border); max-height: 200px; }
  .settings-sidebar.mobile-open { display: block; }
  .ph-grid.cols-2 { grid-template-columns: 1fr; }
  .ph-grid.cols-3 { grid-template-columns: 1fr; }
  .set-hours-grid { grid-template-columns: 70px 1fr 1fr 60px; }
  .modules-overview-grid { grid-template-columns: 1fr; padding: 16px; }
  .module-body { padding: 16px; }
}

</style>
<!-- Apply theme before first paint to avoid flash -->
<script>
(function(){
  var t = localStorage.getItem('sr-edit-theme') || 'dark';
  if (t === 'light') document.documentElement.setAttribute('data-theme','light');
})();
</script>
</head>
<body>

<?php if (!$isLoggedIn): ?>
<!-- ═══════════════ LOGIN ═══════════════ -->
<div class="login-wrap">
  <div class="login-bg"></div>
  <div class="login-grid"></div>
  <!-- Theme toggle on login page -->
  <button class="theme-toggle" id="btn-theme-login" onclick="toggleTheme()" title="Toggle light/dark mode" style="position:absolute;top:16px;right:16px;z-index:10">🌙</button>
  <div class="login-box">
    <div class="login-logo">
      <?php if ($logoDarkSvg): ?>
        <img class="login-logo-img-dark" src="data:image/svg+xml;base64,<?= base64_encode($logoDarkSvg) ?>" alt="SR Edit">
      <?php elseif ($logoDarkB64): ?>
        <img class="login-logo-img-dark" src="data:image/png;base64,<?= $logoDarkB64 ?>" alt="SR Edit">
      <?php endif; ?>
      <?php if ($logoLightSvg): ?>
        <img class="login-logo-img-light" src="data:image/svg+xml;base64,<?= base64_encode($logoLightSvg) ?>" alt="SR Edit">
      <?php elseif ($logoLightB64): ?>
        <img class="login-logo-img-light" src="data:image/png;base64,<?= $logoLightB64 ?>" alt="SR Edit">
      <?php endif; ?>
      <?php if (!$logoDarkSvg && !$logoDarkB64 && !$logoLightSvg && !$logoLightB64): ?>
        <div class="login-logo-icon">S</div>
        <div class="login-logo-name">SR<span> Edit</span></div>
      <?php endif; ?>
    </div>
    <div class="login-title">Welcome back</div>
    <div class="login-sub">// sign in to continue</div>
    <?php if (!empty($loginError)): ?>
      <div class="login-error">⚠ <?= htmlspecialchars($loginError) ?></div>
    <?php endif; ?>
    <form method="POST">
      <div class="form-field">
        <label class="form-label">Username</label>
        <input class="form-input" type="text" name="username" autocomplete="username" autofocus placeholder="admin">
      </div>
      <div class="form-field">
        <label class="form-label">Password</label>
        <input class="form-input" type="password" name="password" autocomplete="current-password" placeholder="••••••••">
      </div>
      <button class="btn-primary" type="submit" name="cms_login">Sign In →</button>
    </form>
    <div class="login-hint">SR Edit v<?= CMS_VERSION ?> · Change default credentials after first login</div>
  </div>
</div>

<?php else: ?>
<!-- ═══════════════ CMS APP ═══════════════ -->
<div class="app" id="app">
  <!-- TOPBAR -->
  <div class="topbar">
    <div class="topbar-logo">
      <!-- Hamburger — mobile only -->
      <button class="hamburger" id="hamburger" onclick="toggleDrawer()" aria-label="Menu">
        <span></span><span></span><span></span>
      </button>
      <div class="logo-img-wrap" id="logo-wrap" onclick="logoClick()" title="SR Edit — click for fun ✨">
        <?php if ($logoDarkSvg): ?>
          <img class="logo-img-dark" src="data:image/svg+xml;base64,<?= base64_encode($logoDarkSvg) ?>" alt="SR Edit">
        <?php elseif ($logoDarkB64): ?>
          <img class="logo-img-dark" src="data:image/png;base64,<?= $logoDarkB64 ?>" alt="SR Edit">
        <?php endif; ?>
        <?php if ($logoLightSvg): ?>
          <img class="logo-img-light" src="data:image/svg+xml;base64,<?= base64_encode($logoLightSvg) ?>" alt="SR Edit">
        <?php elseif ($logoLightB64): ?>
          <img class="logo-img-light" src="data:image/png;base64,<?= $logoLightB64 ?>" alt="SR Edit">
        <?php endif; ?>
        <?php if (!$logoDarkSvg && !$logoDarkB64 && !$logoLightSvg && !$logoLightB64): ?>
          <div class="topbar-logo-icon">S</div>
          <div class="topbar-logo-name">SR<span> Edit</span></div>
        <?php endif; ?>
        <span class="alpha-badge">alpha</span>
      </div>
    </div>
    <div class="topbar-breadcrumb" id="breadcrumb">
      <span>Files</span>
      <span class="sep">/</span>
      <span class="current" id="breadcrumb-file">No file selected</span>
    </div>
    <div class="topbar-actions">
      <div style="position:relative">
        <button class="feedback-btn" id="feedback-toggle" onclick="toggleFeedbackMenu()" title="Report a bug or request a feature">
          <span>⚡</span><span class="feedback-label"> Feedback</span>
        </button>
        <div class="feedback-menu" id="feedback-menu" style="display:none">
          <div class="feedback-menu-item" onclick="openFeedback('bug')">🐛 Report a Bug</div>
          <div class="feedback-menu-item" onclick="openFeedback('feature')">💡 Request a Feature</div>
          <div class="feedback-menu-sep"></div>
          <div class="feedback-menu-item" onclick="openFeedback('other')">✉ General Feedback</div>
        </div>
      </div>
      <button class="theme-toggle" id="btn-theme" onclick="toggleTheme()" title="Toggle light/dark mode">🌙</button>
      <button class="tb-btn icon-btn" id="btn-undo" disabled title="Undo (Ctrl+Z)">↩</button>
      <button class="tb-btn icon-btn" id="btn-redo" disabled title="Redo (Ctrl+Y)">↪</button>
      <div style="width:1px;height:20px;background:var(--border);margin:0 2px" class="tb-sep"></div>
      <button class="tb-btn" id="btn-discard" disabled title="Discard all unsaved changes">✕ Discard</button>
      <button class="tb-btn" id="btn-inject" disabled title="Inject data-cms attributes to selected page">⚙ Auto-Tag</button>
      <button class="tb-btn save" id="btn-save" disabled title="Save current changes (Ctrl+S)">↑ Save</button>
      <div class="topbar-avatar" id="user-avatar" title="<?= htmlspecialchars($_SESSION['cms_user'] ?? 'admin') ?> — click to sign out" onclick="confirmLogout()">
        <?= strtoupper(substr($_SESSION['cms_user'] ?? 'A', 0, 1)) ?>
      </div>
    </div>
  </div>

  <!-- MOBILE DRAWER OVERLAY -->
  <div class="mobile-drawer-overlay" id="drawer-overlay" onclick="closeDrawer()"></div>

  <!-- MOBILE DRAWER -->
  <div class="mobile-drawer" id="mobile-drawer">
    <div class="drawer-header">
      <div style="font-size:13px;font-weight:700;color:var(--text)">Menu</div>
      <button class="drawer-close" onclick="closeDrawer()">×</button>
    </div>
    <div class="drawer-scroll">
      <!-- Pages -->
      <div class="drawer-section-label">Pages</div>
      <div id="drawer-file-tree"></div>

      <div class="drawer-divider"></div>

      <!-- Modules -->
      <div class="drawer-section-label">Modules</div>
      <div class="nav-item" onclick="showModule('editor');closeDrawer()"><span class="nav-item-icon">✏️</span>
        <div class="module-dot"></div> Page Editor
      </div>
      <div class="nav-item" onclick="showModule('modules');closeDrawer()"><span class="nav-item-icon">📦</span>
        <span style="width:6px;height:6px;border-radius:50%;background:var(--border2);display:inline-block;flex-shrink:0"></span> Module Manager
      </div>
      <div class="nav-item" onclick="openSettings();closeDrawer()"><span class="nav-item-icon">⚙️</span><span class="nav-item-label">Settings</span></div>
      <div class="nav-item" onclick="showModule('license');closeDrawer()"><span class="nav-item-icon">🔐</span>
        <span style="width:6px;height:6px;border-radius:50%;background:var(--border2);display:inline-block;flex-shrink:0"></span> License
      </div>

      <div class="drawer-divider"></div>

      <!-- Tools -->
      <div class="drawer-section-label">Tools</div>
      <div class="module-item" onclick="document.getElementById('btn-inject').click();closeDrawer()">
        <span style="width:6px;height:6px;border-radius:50%;background:var(--border2);display:inline-block;flex-shrink:0"></span> Auto-Tag Page
      </div>
      <div class="module-item" onclick="document.getElementById('btn-discard').click()">
        <span style="width:6px;height:6px;border-radius:50%;background:var(--border2);display:inline-block;flex-shrink:0"></span> Discard Changes
      </div>
      <div class="module-item" onclick="openFeedback('bug');closeDrawer()">
        <span style="width:6px;height:6px;border-radius:50%;background:var(--border2);display:inline-block;flex-shrink:0"></span> 🐛 Report a Bug
      </div>
    </div>
    <!-- Drawer footer actions -->
    <div class="drawer-actions">
      <button class="tb-btn save" style="flex:1" onclick="document.getElementById('btn-save').click();closeDrawer()" id="drawer-save">↑ Save</button>
      <button class="theme-toggle" onclick="toggleTheme()" id="drawer-theme-btn" title="Toggle theme">🌙</button>
      <div class="topbar-avatar" onclick="confirmLogout()" title="Sign out" style="cursor:pointer">
        <?= strtoupper(substr($_SESSION['cms_user'] ?? 'A', 0, 1)) ?>
      </div>
    </div>
  </div>

  <!-- BODY -->
  <div class="app-body" id="app-body">

    <!-- ═══════════ LEFT SIDEBAR ═══════════ -->
    <div class="sidebar" id="left-sidebar">
      <div class="sidebar-nav">
        <div class="sidebar-nav-top">

          <!-- File tree -->
          <div class="file-tree-section">
            <div class="nav-group-label">Pages</div>
            <div id="file-tree"></div>
          </div>

          <!-- Core -->
          <div class="nav-group-label">Editor</div>
          <div class="nav-item active" id="mod-editor" onclick="showModule('editor')">
            <span class="nav-item-icon">✏️</span>
            <span class="nav-item-label">Page Editor</span>
            <span class="nav-item-badge badge-live">LIVE</span>
          </div>
          <div class="nav-item" id="mod-media" onclick="showModule('media')">
            <span class="nav-item-icon">🖼️</span>
            <span class="nav-item-label">Media Library</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-snippets" onclick="showModule('snippets')">
            <span class="nav-item-icon">🧩</span>
            <span class="nav-item-label">HTML Snippets</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-diff" onclick="showModule('diff')">
            <span class="nav-item-icon">📜</span>
            <span class="nav-item-label">Change History</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>

          <!-- Business -->
          <div class="nav-group-label">Business Modules</div>
          <div class="nav-item" id="mod-menu" onclick="showModule('menu')">
            <span class="nav-item-icon">🍽️</span>
            <span class="nav-item-label">Restaurant Menu</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-booking" onclick="showModule('booking')">
            <span class="nav-item-icon">📅</span>
            <span class="nav-item-label">Appointments</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-products" onclick="showModule('products')">
            <span class="nav-item-icon">🛍️</span>
            <span class="nav-item-label">Product Catalogue</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-blog" onclick="showModule('blog')">
            <span class="nav-item-icon">📝</span>
            <span class="nav-item-label">Blog & News</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-events" onclick="showModule('events')">
            <span class="nav-item-icon">🎪</span>
            <span class="nav-item-label">Events</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-team" onclick="showModule('team')">
            <span class="nav-item-icon">👥</span>
            <span class="nav-item-label">Team Members</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-testimonials" onclick="showModule('testimonials')">
            <span class="nav-item-icon">⭐</span>
            <span class="nav-item-label">Testimonials</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-faq" onclick="showModule('faq')">
            <span class="nav-item-icon">❓</span>
            <span class="nav-item-label">FAQ Manager</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-jobs" onclick="showModule('jobs')">
            <span class="nav-item-icon">💼</span>
            <span class="nav-item-label">Job Listings</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-gallery" onclick="showModule('gallery')">
            <span class="nav-item-icon">🖼️</span>
            <span class="nav-item-label">Portfolio / Gallery</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-pricing" onclick="showModule('pricing')">
            <span class="nav-item-icon">💰</span>
            <span class="nav-item-label">Pricing Tables</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>

          <!-- Communication -->
          <div class="nav-group-label">Communication</div>
          <div class="nav-item" id="mod-forms" onclick="showModule('forms')">
            <span class="nav-item-icon">📋</span>
            <span class="nav-item-label">Form Builder</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-inbox" onclick="showModule('inbox')">
            <span class="nav-item-icon">📬</span>
            <span class="nav-item-label">Form Inbox</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-newsletter" onclick="showModule('newsletter')">
            <span class="nav-item-icon">📧</span>
            <span class="nav-item-label">Newsletter</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>

          <!-- Analytics & SEO -->
          <div class="nav-group-label">Analytics & SEO</div>
          <div class="nav-item" id="mod-seo" onclick="showModule('seo')">
            <span class="nav-item-icon">🔍</span>
            <span class="nav-item-label">SEO Analyzer</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-analytics" onclick="showModule('analytics')">
            <span class="nav-item-icon">📊</span>
            <span class="nav-item-label">Analytics</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-redirects" onclick="showModule('redirects')">
            <span class="nav-item-icon">↪️</span>
            <span class="nav-item-label">Redirects</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>

          <!-- Tools -->
          <div class="nav-group-label">Tools</div>
          <div class="nav-item" id="mod-export" onclick="showModule('export')">
            <span class="nav-item-icon">🚀</span>
            <span class="nav-item-label">Export & Deploy</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-backup" onclick="showModule('backup')">
            <span class="nav-item-icon">💾</span>
            <span class="nav-item-label">Backup & Restore</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
          <div class="nav-item" id="mod-accessibility" onclick="showModule('accessibility')">
            <span class="nav-item-icon">♿</span>
            <span class="nav-item-label">Accessibility</span>
            <span class="nav-item-badge badge-soon">SOON</span>
          </div>
        </div>

        <!-- Bottom nav -->
        <div class="sidebar-nav-bottom">
          <div class="nav-item" onclick="openSettings()">
            <span class="nav-item-icon">⚙️</span>
            <span class="nav-item-label">Settings</span>
          </div>
          <div class="nav-item" id="mod-license" onclick="showModule('license')">
            <span class="nav-item-icon">🔐</span>
            <span class="nav-item-label">License</span>
          </div>
          <div class="nav-item" onclick="showModule('modules')">
            <span class="nav-item-icon">📦</span>
            <span class="nav-item-label">All Modules</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ═══════════ MAIN AREA ═══════════ -->
    <div class="main" id="main-area" style="display:flex;flex-direction:column;flex:1;overflow:hidden">

      <!-- ── EDITOR PANEL ── -->
      <div id="panel-editor" class="editor-panel">
        <button class="sidebar-toggle" id="left-toggle" onclick="toggleLeftSidebar()" title="Toggle pages panel">‹</button>
        <div class="preview-wrap">
          <button class="elements-toggle" id="right-toggle" onclick="toggleRightSidebar()" title="Toggle elements panel">›</button>
          <div class="preview-bar">
            <div class="preview-dots">
              <div class="preview-dot"></div><div class="preview-dot"></div><div class="preview-dot"></div>
            </div>
            <div class="preview-url" id="preview-url">No page loaded</div>
            <div class="vp-switcher">
              <button class="vp-btn" onclick="setViewport('mobile')" title="Mobile (375px)">📱</button>
              <button class="vp-btn" onclick="setViewport('tablet')" title="Tablet (768px)">⬛</button>
              <button class="vp-btn active" id="vp-desktop" onclick="setViewport('desktop')" title="Desktop">🖥</button>
            </div>
            <button class="tb-btn" onclick="refreshPreview()" style="padding:4px 10px;font-size:11px" title="Refresh preview">↺</button>
          </div>
          <div class="preview-frame-wrap" id="preview-frame-wrap">
            <div class="preview-empty" id="preview-empty">
              <div class="preview-empty-icon">📄</div>
              <div class="preview-empty-text">Select a file to start editing</div>
            </div>
            <div class="preview-loading" id="preview-loading" style="display:none">
              <div class="spinner"></div>
              <div style="color:var(--text2);font-size:13px;font-family:'DM Mono',monospace">Loading page…</div>
            </div>
            <iframe id="preview-frame" class="preview-frame" style="display:none" sandbox="allow-same-origin allow-scripts"></iframe>
            <div class="tap-hint" id="tap-hint" style="display:none">
              <div class="tap-hint-top">
                <div class="tap-hint-icon">👆</div>
                <div class="tap-hint-text"><strong>Tap any text</strong> on the page to edit it</div>
              </div>
              <div class="tap-hint-actions">
                <button class="tap-hint-btn dismiss" onclick="dismissHint(true)">Don't show again</button>
                <button class="tap-hint-btn" onclick="dismissHint(false)">✕ Close</button>
              </div>
            </div>
          </div>
        </div>
        <div class="elements-panel">
          <div class="elements-header">Elements<div class="elements-count" id="el-count">0</div></div>
          <div class="elements-search"><input type="text" placeholder="Search elements…" id="el-search" oninput="filterElements()"></div>
          <div class="elements-list" id="el-list">
            <div style="padding:20px 10px;text-align:center;color:var(--text3);font-size:12px;font-family:'DM Mono',monospace">Load a page to see elements</div>
          </div>
        </div>
      </div>

      <!-- ── ALL MODULES OVERVIEW ── -->
      <div id="panel-modules" style="display:none;flex:1;overflow-y:auto;background:var(--bg)">
        <div style="padding:24px 24px 8px">
          <div class="section-title">All Modules</div>
          <div class="section-sub">Click any module to open it. Coming soon modules show a preview of the interface.</div>
        </div>
        <div class="modules-overview-grid" id="modules-grid"></div>
      </div>

      <!-- ── LICENSE ── -->
      <div id="panel-license" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🔐 License</div>
        </div>
        <div class="module-body" style="max-width:560px">
          <div class="license-status" id="license-status">
            <div class="license-icon">⏳</div>
            <div><div style="font-weight:700;margin-bottom:2px">Checking license…</div>
            <div style="font-size:12px;color:var(--text2);font-family:'DM Mono',monospace" id="license-status-text">Please wait</div></div>
          </div>
          <div style="font-size:11px;color:var(--text3);margin-bottom:8px;font-family:'DM Mono',monospace;letter-spacing:1px;text-transform:uppercase">License Key</div>
          <div class="license-key-display" id="license-key-display">—</div>
          <div style="display:flex;gap:10px">
            <button class="tb-btn" onclick="promptLicenseKey()">Enter License Key</button>
            <button class="tb-btn" onclick="verifyLicense()">↺ Verify Now</button>
          </div>
        </div>
      </div>

      <!-- ══════════════════════════════════════
           COMING SOON MODULE PANELS
      ══════════════════════════════════════ -->

      <!-- MEDIA LIBRARY -->
      <div id="panel-media" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🖼️ Media Library</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Upload, organize and insert images directly into your pages. Browse all uploaded files, resize on upload, and manage your media library.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div><div class="ph-section-title">Upload Files</div><div class="ph-section-sub">// drag & drop or click to browse</div></div><div class="ph-btn-primary">+ Upload</div></div>
            <div class="ph-section-body">
              <div class="ph-image-placeholder">📁</div>
              <div style="margin-top:12px;display:flex;gap:10px">
                <div class="ph-input" style="flex:1">Search media…</div>
                <div class="ph-select" style="width:120px">All types</div>
              </div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">All Files (0)</div></div>
            <div class="ph-section-body"><div class="ph-thumb-grid">
              <div class="ph-thumb">🖼️</div><div class="ph-thumb">🖼️</div><div class="ph-thumb">🖼️</div>
              <div class="ph-thumb">📄</div><div class="ph-thumb">🖼️</div><div class="ph-thumb">🖼️</div>
            </div></div>
          </div>
        </div>
      </div>

      <!-- HTML SNIPPETS -->
      <div id="panel-snippets" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🧩 HTML Snippets</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Save reusable HTML blocks and insert them into any page with one click. Perfect for headers, footers, call-to-action sections.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Saved Snippets</div><div class="ph-btn-primary">+ New Snippet</div></div>
            <div class="ph-section-body">
              <div class="ph-list-item"><div class="ph-avatar">🧩</div><div class="ph-list-info"><div class="ph-list-title">Hero Section</div><div class="ph-list-sub">Last used: —</div></div><div class="ph-btn">Insert</div></div>
              <div class="ph-list-item"><div class="ph-avatar">🧩</div><div class="ph-list-info"><div class="ph-list-title">Contact Card</div><div class="ph-list-sub">Last used: —</div></div><div class="ph-btn">Insert</div></div>
              <div class="ph-list-item"><div class="ph-avatar">🧩</div><div class="ph-list-info"><div class="ph-list-title">Footer Banner</div><div class="ph-list-sub">Last used: —</div></div><div class="ph-btn">Insert</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- CHANGE HISTORY -->
      <div id="panel-diff" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">📜 Change History</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Browse every saved version of your pages. Compare changes side by side and restore any previous version with one click.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Version History</div></div>
            <div class="ph-section-body">
              <table class="ph-table"><thead><tr><th>File</th><th>Date</th><th>Size</th><th>Action</th></tr></thead><tbody>
                <tr><td>index.html</td><td>—</td><td>—</td><td>Restore</td></tr>
                <tr><td>index.html</td><td>—</td><td>—</td><td>Restore</td></tr>
                <tr><td>about.html</td><td>—</td><td>—</td><td>Restore</td></tr>
              </tbody></table>
            </div>
          </div>
        </div>
      </div>

      <!-- RESTAURANT MENU -->
      <div id="panel-menu" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🍽️ Restaurant Menu</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn">Preview Menu</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Build and manage your full restaurant menu. Add categories, dishes with photos, prices, allergens, and dietary tags. Auto-renders on your website.</div></div>
          <div style="display:grid;grid-template-columns:220px 1fr;gap:16px">
            <div class="ph-section" style="margin-bottom:0">
              <div class="ph-section-header"><div class="ph-section-title">Categories</div><div class="ph-btn-primary">+</div></div>
              <div class="ph-section-body" style="padding:8px 0">
                <div class="nav-item" style="opacity:.5">🥗 Starters</div>
                <div class="nav-item" style="opacity:.5">🍝 Mains</div>
                <div class="nav-item" style="opacity:.5">🍰 Desserts</div>
                <div class="nav-item" style="opacity:.5">🍷 Drinks</div>
              </div>
            </div>
            <div>
              <div class="ph-section" style="margin-bottom:14px">
                <div class="ph-section-header"><div class="ph-section-title">Add Menu Item</div></div>
                <div class="ph-section-body">
                  <div class="ph-grid cols-2" style="margin-bottom:12px">
                    <div class="ph-field"><div class="ph-label">Dish Name *</div><div class="ph-input">e.g. Grilled Salmon</div></div>
                    <div class="ph-field"><div class="ph-label">Price (€) *</div><div class="ph-input">0.00</div></div>
                  </div>
                  <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Description</div><div class="ph-textarea">Brief description of the dish…</div></div>
                  <div class="ph-grid cols-2" style="margin-bottom:12px">
                    <div class="ph-field"><div class="ph-label">Category</div><div class="ph-select"><option>Mains</option></div></div>
                    <div class="ph-field"><div class="ph-label">Photo</div><div class="ph-input">Upload image…</div></div>
                  </div>
                  <div class="ph-label" style="margin-bottom:6px">Dietary Tags</div>
                  <div style="display:flex;flex-wrap:wrap;gap:6px;margin-bottom:12px">
                    <span class="ph-tag">🌱 Vegan</span><span class="ph-tag">🌾 Gluten-free</span><span class="ph-tag">🌶️ Spicy</span><span class="ph-tag">🥜 Contains nuts</span><span class="ph-tag">☪️ Halal</span><span class="ph-tag">✡️ Kosher</span>
                  </div>
                  <div class="ph-label" style="margin-bottom:6px">EU Allergens (14)</div>
                  <div style="display:flex;flex-wrap:wrap;gap:4px;margin-bottom:12px">
                    <span class="ph-tag">Gluten</span><span class="ph-tag">Crustaceans</span><span class="ph-tag">Eggs</span><span class="ph-tag">Fish</span><span class="ph-tag">Peanuts</span><span class="ph-tag">Soya</span><span class="ph-tag">Milk</span><span class="ph-tag">Nuts</span><span class="ph-tag">Celery</span><span class="ph-tag">Mustard</span><span class="ph-tag">Sesame</span><span class="ph-tag">Sulphites</span><span class="ph-tag">Lupin</span><span class="ph-tag">Molluscs</span>
                  </div>
                  <div style="display:flex;gap:8px"><div class="ph-btn-primary">Save Item</div><div class="ph-btn">Cancel</div></div>
                </div>
              </div>
              <div class="ph-section" style="margin-bottom:0">
                <div class="ph-section-header"><div class="ph-section-title">Items in Mains (0)</div></div>
                <div class="ph-section-body">
                  <table class="ph-table"><thead><tr><th>Photo</th><th>Name</th><th>Price</th><th>Tags</th><th>Actions</th></tr></thead><tbody>
                    <tr><td>🖼</td><td>—</td><td>—</td><td>—</td><td>Edit · Delete</td></tr>
                  </tbody></table>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- APPOINTMENTS / BOOKING -->
      <div id="panel-booking" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">📅 Appointments</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div style="display:flex;gap:8px"><div class="ph-btn">Today</div><div class="ph-btn">Week</div><div class="ph-btn">Month</div></div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Visitors request appointments from your site. You confirm or decline from here. Automatic confirmation emails sent via your configured SMTP.</div></div>
          <div class="ph-stat-row">
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Pending</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Today</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">This week</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Total</div></div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 300px;gap:16px">
            <div class="ph-section" style="margin-bottom:0">
              <div class="ph-section-header"><div class="ph-section-title">Calendar</div></div>
              <div class="ph-section-body">
                <div style="display:grid;grid-template-columns:repeat(7,1fr);gap:4px;margin-bottom:6px">
                  <div style="text-align:center;font-size:10px;color:var(--text3);font-family:'DM Mono',monospace;opacity:.5">Mo</div><div style="text-align:center;font-size:10px;color:var(--text3);font-family:'DM Mono',monospace;opacity:.5">Tu</div><div style="text-align:center;font-size:10px;color:var(--text3);font-family:'DM Mono',monospace;opacity:.5">We</div><div style="text-align:center;font-size:10px;color:var(--text3);font-family:'DM Mono',monospace;opacity:.5">Th</div><div style="text-align:center;font-size:10px;color:var(--text3);font-family:'DM Mono',monospace;opacity:.5">Fr</div><div style="text-align:center;font-size:10px;color:var(--text3);font-family:'DM Mono',monospace;opacity:.5">Sa</div><div style="text-align:center;font-size:10px;color:var(--text3);font-family:'DM Mono',monospace;opacity:.5">Su</div>
                </div>
                <div class="ph-calendar">
                  <div class="ph-cal-day">1</div><div class="ph-cal-day">2</div><div class="ph-cal-day has-event">3</div><div class="ph-cal-day">4</div><div class="ph-cal-day">5</div><div class="ph-cal-day">6</div><div class="ph-cal-day">7</div>
                  <div class="ph-cal-day">8</div><div class="ph-cal-day">9</div><div class="ph-cal-day today">10</div><div class="ph-cal-day has-event">11</div><div class="ph-cal-day">12</div><div class="ph-cal-day has-event">13</div><div class="ph-cal-day">14</div>
                  <div class="ph-cal-day">15</div><div class="ph-cal-day">16</div><div class="ph-cal-day">17</div><div class="ph-cal-day">18</div><div class="ph-cal-day has-event">19</div><div class="ph-cal-day">20</div><div class="ph-cal-day">21</div>
                  <div class="ph-cal-day">22</div><div class="ph-cal-day">23</div><div class="ph-cal-day">24</div><div class="ph-cal-day">25</div><div class="ph-cal-day">26</div><div class="ph-cal-day">27</div><div class="ph-cal-day">28</div>
                </div>
              </div>
            </div>
            <div>
              <div class="ph-section" style="margin-bottom:14px">
                <div class="ph-section-header"><div class="ph-section-title">Pending Requests</div></div>
                <div class="ph-section-body">
                  <div class="ph-list-item"><div class="ph-avatar">👤</div><div class="ph-list-info"><div class="ph-list-title">— —</div><div class="ph-list-sub">Date · Time · Service</div></div></div>
                  <div class="ph-list-item"><div class="ph-avatar">👤</div><div class="ph-list-info"><div class="ph-list-title">— —</div><div class="ph-list-sub">Date · Time · Service</div></div></div>
                </div>
              </div>
              <div class="ph-section">
                <div class="ph-section-header"><div class="ph-section-title">Quick Actions</div></div>
                <div class="ph-section-body" style="display:flex;flex-direction:column;gap:8px">
                  <div class="ph-btn-primary">✓ Confirm selected</div>
                  <div class="ph-btn">✕ Decline selected</div>
                  <div class="ph-btn">📧 Send reminder</div>
                  <div class="ph-btn">📥 Export .ics</div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- PRODUCTS -->
      <div id="panel-products" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🛍️ Product Catalogue</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Add Product</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Manage a product or service catalogue. No payment processing — a professional showcase with inquiry forms and optional stock status.</div></div>
          <div class="ph-stat-row">
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Products</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Categories</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Out of stock</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Inquiries</div></div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header">
              <div class="ph-section-title">Products</div>
              <div style="display:flex;gap:8px"><div class="ph-input" style="width:180px">Search…</div><div class="ph-select" style="width:120px">All categories</div></div>
            </div>
            <div class="ph-section-body">
              <table class="ph-table"><thead><tr><th>Photo</th><th>Name</th><th>Category</th><th>Price</th><th>Stock</th><th>Actions</th></tr></thead><tbody>
                <tr><td>🖼</td><td>Sample Product</td><td>—</td><td>—</td><td><span class="ph-tag">In Stock</span></td><td>Edit · Delete</td></tr>
                <tr><td>🖼</td><td>—</td><td>—</td><td>—</td><td><span class="ph-tag">—</span></td><td>Edit · Delete</td></tr>
              </tbody></table>
            </div>
          </div>
        </div>
      </div>

      <!-- BLOG -->
      <div id="panel-blog" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">📝 Blog & News</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ New Post</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Write and publish blog posts or news articles. Schedule posts, manage categories and tags, auto-generate RSS feed and index pages.</div></div>
          <div class="ph-section">
            <div class="ph-section-header">
              <div class="ph-section-title">Posts (0)</div>
              <div style="display:flex;gap:6px"><div class="ph-btn">All</div><div class="ph-btn">Published</div><div class="ph-btn">Drafts</div><div class="ph-btn">Scheduled</div></div>
            </div>
            <div class="ph-section-body">
              <table class="ph-table"><thead><tr><th>Title</th><th>Author</th><th>Category</th><th>Date</th><th>Status</th><th>Actions</th></tr></thead><tbody>
                <tr><td>Welcome post</td><td>Admin</td><td>News</td><td>—</td><td><span class="ph-tag">Draft</span></td><td>Edit · Delete</td></tr>
                <tr><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td><td>—</td></tr>
              </tbody></table>
            </div>
          </div>
        </div>
      </div>

      <!-- EVENTS -->
      <div id="panel-events" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🎪 Events</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Add Event</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Create and manage events with dates, locations, ticket links and featured images. Past events automatically archive.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Add New Event</div></div>
            <div class="ph-section-body">
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Event Title *</div><div class="ph-input">e.g. Summer Market 2025</div></div>
                <div class="ph-field"><div class="ph-label">Date & Time *</div><div class="ph-input">YYYY-MM-DD HH:MM</div></div>
                <div class="ph-field"><div class="ph-label">Location</div><div class="ph-input">Venue / address</div></div>
                <div class="ph-field"><div class="ph-label">Ticket Link</div><div class="ph-input">https://…</div></div>
              </div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Description</div><div class="ph-textarea">Event description…</div></div>
              <div class="ph-btn-primary">Save Event</div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Upcoming Events</div></div>
            <div class="ph-section-body">
              <div class="ph-list-item"><div class="ph-avatar">🎪</div><div class="ph-list-info"><div class="ph-list-title">No events yet</div><div class="ph-list-sub">Add your first event above</div></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- TEAM -->
      <div id="panel-team" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">👥 Team Members</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Add Member</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Manage your team profiles: name, role, photo, bio, and social links. Drag to reorder. Renders as a team grid on your site.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Add Team Member</div></div>
            <div class="ph-section-body">
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Full Name *</div><div class="ph-input">e.g. Maria Müller</div></div>
                <div class="ph-field"><div class="ph-label">Role / Title *</div><div class="ph-input">e.g. Head Chef</div></div>
                <div class="ph-field"><div class="ph-label">Email (optional)</div><div class="ph-input">member@business.de</div></div>
                <div class="ph-field"><div class="ph-label">Photo</div><div class="ph-input">Upload headshot…</div></div>
              </div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Short Bio</div><div class="ph-textarea">Brief description…</div></div>
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">LinkedIn</div><div class="ph-input">https://linkedin.com/…</div></div>
                <div class="ph-field"><div class="ph-label">Instagram</div><div class="ph-input">https://instagram.com/…</div></div>
              </div>
              <div class="ph-btn-primary">Save Member</div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Team (0 members)</div></div>
            <div class="ph-section-body" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(160px,1fr));gap:12px">
              <div style="text-align:center;opacity:.4"><div style="width:60px;height:60px;border-radius:50%;background:var(--bg3);margin:0 auto 8px;border:1px solid var(--border);display:flex;align-items:center;justify-content:center;font-size:24px">👤</div><div style="font-size:13px;font-weight:600">—</div><div style="font-size:11px;color:var(--text3)">—</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- TESTIMONIALS -->
      <div id="panel-testimonials" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">⭐ Testimonials</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Add Review</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Add and manage customer testimonials. Set star ratings, add photos, and choose which reviews appear on your site.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Add Testimonial</div></div>
            <div class="ph-section-body">
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Customer Name *</div><div class="ph-input">e.g. Klaus Schmidt</div></div>
                <div class="ph-field"><div class="ph-label">Role / Company</div><div class="ph-input">e.g. Owner, Bäckerei XY</div></div>
              </div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Review Text *</div><div class="ph-textarea">What the customer said…</div></div>
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Rating</div><div class="ph-select"><option>⭐⭐⭐⭐⭐ 5 stars</option></div></div>
                <div class="ph-field"><div class="ph-label">Photo</div><div class="ph-input">Upload photo…</div></div>
              </div>
              <div class="ph-btn-primary">Save Review</div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Published Reviews (0)</div></div>
            <div class="ph-section-body">
              <div class="ph-list-item"><div class="ph-avatar">👤</div><div class="ph-list-info"><div class="ph-list-title">—</div><div class="ph-stars">★★★★★</div></div><div class="ph-btn">Hide</div></div>
              <div class="ph-list-item"><div class="ph-avatar">👤</div><div class="ph-list-info"><div class="ph-list-title">—</div><div class="ph-stars">★★★★☆</div></div><div class="ph-btn">Hide</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- FAQ -->
      <div id="panel-faq" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">❓ FAQ Manager</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Add Question</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Create FAQ entries grouped by category. Rendered as an accordion with auto-generated Schema.org structured data for Google rich results.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Add FAQ Entry</div></div>
            <div class="ph-section-body">
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Question *</div><div class="ph-input">e.g. What are your opening hours?</div></div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Answer *</div><div class="ph-textarea">We are open Monday to Friday 9am–6pm…</div></div>
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Category</div><div class="ph-select"><option>General</option></div></div>
                <div class="ph-field"><div class="ph-label">Order</div><div class="ph-input">1</div></div>
              </div>
              <div class="ph-btn-primary">Save FAQ</div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">FAQ Entries (0)</div></div>
            <div class="ph-section-body">
              <div class="ph-list-item"><div style="flex:1"><div class="ph-list-title">What are your opening hours?</div><div class="ph-list-sub">General category</div></div><div style="display:flex;gap:6px"><div class="ph-btn">Edit</div><div class="ph-btn">Delete</div></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- JOBS -->
      <div id="panel-jobs" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">💼 Job Listings</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Post Job</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Post job openings with expiry dates, salary ranges, and application links. Expired listings auto-hide from the site.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">New Job Listing</div></div>
            <div class="ph-section-body">
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Job Title *</div><div class="ph-input">e.g. Sous Chef</div></div>
                <div class="ph-field"><div class="ph-label">Department</div><div class="ph-input">Kitchen</div></div>
                <div class="ph-field"><div class="ph-label">Type</div><div class="ph-select"><option>Full-time</option></div></div>
                <div class="ph-field"><div class="ph-label">Location</div><div class="ph-input">Hamburg, Germany</div></div>
                <div class="ph-field"><div class="ph-label">Salary Range</div><div class="ph-input">€2,800 – €3,400/mo</div></div>
                <div class="ph-field"><div class="ph-label">Listing Expiry</div><div class="ph-input">YYYY-MM-DD</div></div>
              </div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Job Description *</div><div class="ph-textarea">Full description, requirements, benefits…</div></div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Apply Link / Email</div><div class="ph-input">jobs@business.de</div></div>
              <div class="ph-btn-primary">Publish Job</div>
            </div>
          </div>
        </div>
      </div>

      <!-- PORTFOLIO / GALLERY -->
      <div id="panel-gallery" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🖼️ Portfolio / Gallery</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Add Project</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Showcase your work with project cards. Before/after photos, client name, category filter. Each project can have its own detail page.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Add Project</div></div>
            <div class="ph-section-body">
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Project Name *</div><div class="ph-input">e.g. Café Renovation</div></div>
                <div class="ph-field"><div class="ph-label">Client</div><div class="ph-input">Client name</div></div>
                <div class="ph-field"><div class="ph-label">Category</div><div class="ph-select"><option>Interior</option></div></div>
                <div class="ph-field"><div class="ph-label">Year</div><div class="ph-input">2025</div></div>
              </div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Description</div><div class="ph-textarea">Project description and results…</div></div>
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Before Photo</div><div class="ph-input">Upload…</div></div>
                <div class="ph-field"><div class="ph-label">After Photo</div><div class="ph-input">Upload…</div></div>
              </div>
              <div class="ph-btn-primary">Save Project</div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Projects (0)</div></div>
            <div class="ph-section-body"><div class="ph-thumb-grid">
              <div class="ph-thumb">🖼️</div><div class="ph-thumb">🖼️</div><div class="ph-thumb">🖼️</div><div class="ph-thumb">🖼️</div>
            </div></div>
          </div>
        </div>
      </div>

      <!-- PRICING TABLES -->
      <div id="panel-pricing" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">💰 Pricing Tables</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Add Plan</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Build visual pricing tables. Define plans with feature lists, highlight a recommended plan, and link to booking or contact forms.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Add Pricing Plan</div></div>
            <div class="ph-section-body">
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Plan Name *</div><div class="ph-input">e.g. Basic</div></div>
                <div class="ph-field"><div class="ph-label">Price *</div><div class="ph-input">€29/month</div></div>
                <div class="ph-field"><div class="ph-label">CTA Button Text</div><div class="ph-input">Get started</div></div>
                <div class="ph-field"><div class="ph-label">CTA Link</div><div class="ph-input">/contact.html</div></div>
              </div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Features (one per line)</div><div class="ph-textarea">Up to 5 users
10 GB storage
Email support</div></div>
              <div class="ph-grid cols-2">
                <div class="ph-field"><div class="ph-label">Highlighted (Most Popular)</div><div class="ph-select"><option>No</option></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- FORMS -->
      <div id="panel-forms" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">📋 Form Builder</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ New Form</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Build custom forms with drag-and-drop fields. Text, email, phone, file upload, dropdowns, checkboxes. Auto-saves submissions to your inbox.</div></div>
          <div style="display:grid;grid-template-columns:200px 1fr;gap:16px">
            <div class="ph-section" style="margin-bottom:0">
              <div class="ph-section-header"><div class="ph-section-title">Field Types</div></div>
              <div class="ph-section-body" style="padding:8px 0;display:flex;flex-direction:column;gap:4px">
                <div class="nav-item" style="opacity:.5;font-size:12px">📝 Text</div>
                <div class="nav-item" style="opacity:.5;font-size:12px">📧 Email</div>
                <div class="nav-item" style="opacity:.5;font-size:12px">📞 Phone</div>
                <div class="nav-item" style="opacity:.5;font-size:12px">📋 Textarea</div>
                <div class="nav-item" style="opacity:.5;font-size:12px">▼ Select</div>
                <div class="nav-item" style="opacity:.5;font-size:12px">☑ Checkbox</div>
                <div class="nav-item" style="opacity:.5;font-size:12px">📁 File upload</div>
                <div class="nav-item" style="opacity:.5;font-size:12px">📅 Date</div>
                <div class="nav-item" style="opacity:.5;font-size:12px">— Divider</div>
              </div>
            </div>
            <div class="ph-section" style="margin-bottom:0;min-height:320px">
              <div class="ph-section-header"><div class="ph-section-title">Form Canvas</div><div class="ph-section-sub">// drag fields here</div></div>
              <div class="ph-section-body" style="min-height:200px;border:2px dashed var(--border);border-radius:var(--radius);display:flex;align-items:center;justify-content:center;color:var(--text3);font-size:13px;opacity:.4">Drop fields here to build your form</div>
            </div>
          </div>
        </div>
      </div>

      <!-- INBOX -->
      <div id="panel-inbox" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">📬 Form Inbox</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div style="display:flex;gap:8px"><div class="ph-btn">Export CSV</div><div class="ph-btn">Mark all read</div></div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — All form submissions in one inbox. Star important messages, mark as read, reply by email, and export to CSV. No email lost.</div></div>
          <div class="ph-stat-row">
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Total</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Unread</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Starred</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Today</div></div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header">
              <div class="ph-section-title">Submissions</div>
              <div class="ph-input" style="width:200px">Search…</div>
            </div>
            <div class="ph-section-body">
              <table class="ph-table"><thead><tr><th></th><th>Name</th><th>Email</th><th>Form</th><th>Date</th><th>Actions</th></tr></thead><tbody>
                <tr><td>⭐</td><td>—</td><td>—</td><td>Contact</td><td>—</td><td>View · Reply</td></tr>
                <tr><td>☆</td><td>—</td><td>—</td><td>Contact</td><td>—</td><td>View · Reply</td></tr>
              </tbody></table>
            </div>
          </div>
        </div>
      </div>

      <!-- NEWSLETTER -->
      <div id="panel-newsletter" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">📧 Newsletter</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Connect your Brevo mailing list. Visitors sign up from your site. Browse and link past newsletter issues. Requires Brevo API key in Settings.</div></div>
          <div class="ph-stat-row">
            <div class="ph-stat"><div class="ph-stat-val">—</div><div class="ph-stat-label">Subscribers</div></div>
            <div class="ph-stat"><div class="ph-stat-val">—</div><div class="ph-stat-label">Campaigns sent</div></div>
            <div class="ph-stat"><div class="ph-stat-val">—</div><div class="ph-stat-label">Avg. open rate</div></div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Brevo Connection</div></div>
            <div class="ph-section-body">
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Brevo API Key</div><div class="ph-input">Configure in Settings → Integrations</div></div>
              <div class="ph-field"><div class="ph-label">Mailing List ID</div><div class="ph-input">—</div></div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Past Campaigns</div></div>
            <div class="ph-section-body">
              <table class="ph-table"><thead><tr><th>Subject</th><th>Date</th><th>Sent</th><th>Open rate</th></tr></thead><tbody>
                <tr><td>—</td><td>—</td><td>—</td><td>—</td></tr>
              </tbody></table>
            </div>
          </div>
        </div>
      </div>

      <!-- SEO -->
      <div id="panel-seo" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🔍 SEO Analyzer</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn">Run Full Audit</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Analyze every page for SEO issues: title length, meta description, heading structure, image alt texts, broken links, and page speed.</div></div>
          <div class="ph-stat-row">
            <div class="ph-stat"><div class="ph-stat-val" style="color:var(--green)">—</div><div class="ph-stat-label">SEO Score</div></div>
            <div class="ph-stat"><div class="ph-stat-val" style="color:var(--red)">0</div><div class="ph-stat-label">Issues</div></div>
            <div class="ph-stat"><div class="ph-stat-val" style="color:var(--yellow)">0</div><div class="ph-stat-label">Warnings</div></div>
            <div class="ph-stat"><div class="ph-stat-val" style="color:var(--green)">0</div><div class="ph-stat-label">Passed</div></div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Checks — index.html</div></div>
            <div class="ph-section-body">
              <div class="ph-progress-row"><div class="ph-progress-label"><span>Page title (50–60 chars)</span><span>—</span></div><div class="ph-progress-bar"><div class="ph-progress-fill" style="width:60%"></div></div></div>
              <div class="ph-progress-row"><div class="ph-progress-label"><span>Meta description (150–160 chars)</span><span>—</span></div><div class="ph-progress-bar"><div class="ph-progress-fill" style="width:40%"></div></div></div>
              <div class="ph-progress-row"><div class="ph-progress-label"><span>H1 present</span><span>—</span></div><div class="ph-progress-bar"><div class="ph-progress-fill" style="width:100%"></div></div></div>
              <div class="ph-progress-row"><div class="ph-progress-label"><span>Images with alt text</span><span>—</span></div><div class="ph-progress-bar"><div class="ph-progress-fill" style="width:75%"></div></div></div>
              <div class="ph-progress-row"><div class="ph-progress-label"><span>Broken links</span><span>—</span></div><div class="ph-progress-bar"><div class="ph-progress-fill" style="width:0%"></div></div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- ANALYTICS -->
      <div id="panel-analytics" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">📊 Analytics</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-select" style="width:140px;pointer-events:none;opacity:.5">Last 30 days</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Cookieless visitor analytics built in. Page views, top pages, device breakdown, referrers, bounce rate. No GDPR consent needed.</div></div>
          <div class="ph-stat-row">
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Page views</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0</div><div class="ph-stat-label">Visitors</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0%</div><div class="ph-stat-label">Bounce rate</div></div>
            <div class="ph-stat"><div class="ph-stat-val">0s</div><div class="ph-stat-label">Avg. time</div></div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Page Views Over Time</div></div>
            <div class="ph-section-body">
              <div style="height:120px;background:var(--bg3);border-radius:var(--radius);display:flex;align-items:flex-end;gap:4px;padding:12px;opacity:.4">
                <div style="flex:1;background:var(--accent);border-radius:2px 2px 0 0;height:40%"></div><div style="flex:1;background:var(--accent);border-radius:2px 2px 0 0;height:60%"></div><div style="flex:1;background:var(--accent);border-radius:2px 2px 0 0;height:30%"></div><div style="flex:1;background:var(--accent);border-radius:2px 2px 0 0;height:80%"></div><div style="flex:1;background:var(--accent);border-radius:2px 2px 0 0;height:50%"></div><div style="flex:1;background:var(--accent);border-radius:2px 2px 0 0;height:70%"></div><div style="flex:1;background:var(--accent);border-radius:2px 2px 0 0;height:45%"></div>
              </div>
            </div>
          </div>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px">
            <div class="ph-section" style="margin-bottom:0">
              <div class="ph-section-header"><div class="ph-section-title">Top Pages</div></div>
              <div class="ph-section-body">
                <div class="ph-list-item"><div class="ph-list-info"><div class="ph-list-title">/ index.html</div><div class="ph-list-sub">0 views</div></div></div>
                <div class="ph-list-item"><div class="ph-list-info"><div class="ph-list-title">/ about.html</div><div class="ph-list-sub">0 views</div></div></div>
              </div>
            </div>
            <div class="ph-section" style="margin-bottom:0">
              <div class="ph-section-header"><div class="ph-section-title">Devices</div></div>
              <div class="ph-section-body">
                <div class="ph-progress-row"><div class="ph-progress-label"><span>Desktop</span><span>—%</span></div><div class="ph-progress-bar"><div class="ph-progress-fill" style="width:55%"></div></div></div>
                <div class="ph-progress-row"><div class="ph-progress-label"><span>Mobile</span><span>—%</span></div><div class="ph-progress-bar"><div class="ph-progress-fill" style="width:35%"></div></div></div>
                <div class="ph-progress-row"><div class="ph-progress-label"><span>Tablet</span><span>—%</span></div><div class="ph-progress-bar"><div class="ph-progress-fill" style="width:10%"></div></div></div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- REDIRECTS -->
      <div id="panel-redirects" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">↪️ Redirects</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">+ Add Redirect</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Manage 301 redirects without touching .htaccess. Define old URL → new URL, choose redirect type, save and done.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Add Redirect</div></div>
            <div class="ph-section-body">
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">From (old URL) *</div><div class="ph-input">/old-page.html</div></div>
                <div class="ph-field"><div class="ph-label">To (new URL) *</div><div class="ph-input">/new-page.html</div></div>
              </div>
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Type</div><div class="ph-select"><option>301 Permanent</option></div></div>
              <div class="ph-btn-primary">Save Redirect</div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Active Redirects (0)</div></div>
            <div class="ph-section-body">
              <table class="ph-table"><thead><tr><th>From</th><th>To</th><th>Type</th><th>Hits</th><th>Actions</th></tr></thead><tbody>
                <tr><td>/old.html</td><td>/new.html</td><td>301</td><td>0</td><td>Delete</td></tr>
              </tbody></table>
            </div>
          </div>
        </div>
      </div>

      <!-- EXPORT & DEPLOY -->
      <div id="panel-export" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">🚀 Export & Deploy</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Export your entire site as a ZIP, or push directly to a server via FTP. Minify HTML, CSS and JS on export for production-ready output.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Export Options</div></div>
            <div class="ph-section-body">
              <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Minify HTML on export</div><div class="set-toggle-desc">// strips comments and whitespace</div></div><label class="toggle-switch"><input type="checkbox" disabled><span class="toggle-slider"></span></label></div>
              <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Convert images to WebP</div><div class="set-toggle-desc">// reduces image file sizes</div></div><label class="toggle-switch"><input type="checkbox" disabled><span class="toggle-slider"></span></label></div>
              <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Cache buster (append ?v=...)</div><div class="set-toggle-desc">// forces browser reload after deploy</div></div><label class="toggle-switch"><input type="checkbox" disabled><span class="toggle-slider"></span></label></div>
              <div style="margin-top:16px;display:flex;gap:10px">
                <div class="ph-btn-primary">⬇ Download ZIP</div>
                <div class="ph-btn">🔗 Deploy via FTP</div>
              </div>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">FTP / SFTP Settings</div></div>
            <div class="ph-section-body">
              <div class="ph-grid cols-2" style="margin-bottom:12px">
                <div class="ph-field"><div class="ph-label">Host</div><div class="ph-input">ftp.yourhost.de</div></div>
                <div class="ph-field"><div class="ph-label">Port</div><div class="ph-input">21</div></div>
                <div class="ph-field"><div class="ph-label">Username</div><div class="ph-input">ftp-user</div></div>
                <div class="ph-field"><div class="ph-label">Password</div><div class="ph-input">••••••••</div></div>
              </div>
              <div class="ph-field"><div class="ph-label">Remote Path</div><div class="ph-input">/public_html/</div></div>
            </div>
          </div>
        </div>
      </div>

      <!-- BACKUP & RESTORE -->
      <div id="panel-backup" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">💾 Backup & Restore</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn-primary">Create Backup Now</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Full site backups: all HTML pages and CMS data in one ZIP. Download to your computer, or restore from a previous backup.</div></div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Backup History</div></div>
            <div class="ph-section-body">
              <table class="ph-table"><thead><tr><th>Date</th><th>Size</th><th>Files</th><th>Actions</th></tr></thead><tbody>
                <tr><td>Auto-backup (per save)</td><td>—</td><td>—</td><td>Download · Restore</td></tr>
                <tr><td>Manual backup</td><td>—</td><td>—</td><td>Download · Restore</td></tr>
              </tbody></table>
            </div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Restore from File</div></div>
            <div class="ph-section-body">
              <div class="ph-field" style="margin-bottom:12px"><div class="ph-label">Upload Backup ZIP</div><div class="ph-input">Choose file…</div></div>
              <div class="ph-btn" style="color:var(--red);border-color:rgba(255,95,126,0.3)">⚠ Restore (overwrites current site)</div>
            </div>
          </div>
        </div>
      </div>

      <!-- ACCESSIBILITY -->
      <div id="panel-accessibility" class="module-panel" style="display:none">
        <div class="module-topbar">
          <div class="module-topbar-title">♿ Accessibility Checker</div>
          <span class="module-topbar-badge badge-soon">Coming Soon</span>
          <div class="ph-btn">Run Check</div>
        </div>
        <div class="module-body">
          <div class="cs-banner"><div class="cs-banner-icon">🚧</div><div class="cs-banner-text"><strong>Coming Soon</strong> — Scan your pages for accessibility issues: missing alt texts, low contrast, unlabelled form fields, non-descriptive links, and heading hierarchy.</div></div>
          <div class="ph-stat-row">
            <div class="ph-stat"><div class="ph-stat-val" style="color:var(--red)">0</div><div class="ph-stat-label">Errors</div></div>
            <div class="ph-stat"><div class="ph-stat-val" style="color:var(--yellow)">0</div><div class="ph-stat-label">Warnings</div></div>
            <div class="ph-stat"><div class="ph-stat-val" style="color:var(--green)">0</div><div class="ph-stat-label">Passed</div></div>
          </div>
          <div class="ph-section">
            <div class="ph-section-header"><div class="ph-section-title">Issues Found</div></div>
            <div class="ph-section-body">
              <div class="ph-list-item"><div style="width:8px;height:8px;border-radius:50%;background:var(--red);flex-shrink:0;margin-top:5px"></div><div class="ph-list-info"><div class="ph-list-title">Missing alt text on 3 images</div><div class="ph-list-sub">index.html — WCAG 1.1.1</div></div><div class="ph-btn">Fix</div></div>
              <div class="ph-list-item"><div style="width:8px;height:8px;border-radius:50%;background:var(--yellow);flex-shrink:0;margin-top:5px"></div><div class="ph-list-info"><div class="ph-list-title">Low contrast ratio detected</div><div class="ph-list-sub">about.html — WCAG 1.4.3</div></div><div class="ph-btn">Fix</div></div>
            </div>
          </div>
        </div>
      </div>

    </div><!-- /main -->

  </div><!-- /app-body -->

  
  <!-- ═══════════ SETTINGS SCREEN ═══════════ -->
  <div class="settings-screen" id="settings-screen">
    <div class="settings-topbar">
      <button class="settings-back-btn" onclick="closeSettings()">← Back to Editor</button>
      <div class="settings-screen-title">⚙️ Settings</div>
      <div style="margin-left:auto;display:flex;align-items:center;gap:8px">
        <span style="font-size:11px;color:var(--text3);font-family:'DM Mono',monospace">Press ESC to close</span>
        <button onclick="closeSettings()" style="width:28px;height:28px;border-radius:6px;border:1px solid var(--border);background:var(--bg3);color:var(--text2);cursor:pointer;display:flex;align-items:center;justify-content:center;font-size:16px;transition:all var(--transition)" title="Close settings">✕</button>
      </div>
    </div>
    <div class="settings-layout">
      <!-- Settings sidebar nav -->
      <div class="settings-sidebar">
        <div class="settings-nav-group">Account</div>
        <div class="settings-nav-item active" id="snav-account" onclick="showSettingsPage('account')"><span class="settings-nav-icon">👤</span> Account & Login</div>
        <div class="settings-nav-item" id="snav-security" onclick="showSettingsPage('security')"><span class="settings-nav-icon">🔒</span> Security</div>

        <div class="settings-nav-group">Website</div>
        <div class="settings-nav-item" id="snav-business" onclick="showSettingsPage('business')"><span class="settings-nav-icon">🏢</span> Business Info</div>
        <div class="settings-nav-item" id="snav-design" onclick="showSettingsPage('design')"><span class="settings-nav-icon">🎨</span> Design & Branding</div>
        <div class="settings-nav-item" id="snav-hours" onclick="showSettingsPage('hours')"><span class="settings-nav-icon">🕐</span> Opening Hours</div>
        <div class="settings-nav-item" id="snav-social" onclick="showSettingsPage('social')"><span class="settings-nav-icon">📱</span> Social Media</div>
        <div class="settings-nav-item" id="snav-seo" onclick="showSettingsPage('seosettings')"><span class="settings-nav-icon">🔍</span> SEO & Meta</div>

        <div class="settings-nav-group">Integrations</div>
        <div class="settings-nav-item" id="snav-email" onclick="showSettingsPage('email')"><span class="settings-nav-icon">📧</span> Email (SMTP)</div>
        <div class="settings-nav-item" id="snav-analytics" onclick="showSettingsPage('analyticssettings')"><span class="settings-nav-icon">📊</span> Analytics</div>
        <div class="settings-nav-item" id="snav-integrations" onclick="showSettingsPage('integrations')"><span class="settings-nav-icon">🔗</span> API Keys</div>

        <div class="settings-nav-group">System</div>
        <div class="settings-nav-item" id="snav-maintenance" onclick="showSettingsPage('maintenance')"><span class="settings-nav-icon">🔧</span> Maintenance</div>
        <div class="settings-nav-item" id="snav-advanced" onclick="showSettingsPage('advanced')"><span class="settings-nav-icon">⚡</span> Advanced</div>
        <div class="settings-nav-item" id="snav-feedback" onclick="showSettingsPage('feedback')"><span class="settings-nav-icon">💬</span> Feedback</div>
      </div>

      <!-- Settings content area -->
      <div class="settings-content">

        <!-- ACCOUNT -->
        <div class="settings-page active" id="spage-account">
          <div class="settings-page-title">Account & Login</div>
          <div class="settings-page-sub">// manage your CMS login credentials</div>
          <div class="set-section">
            <div class="set-section-title">Profile</div>
            <div class="set-section-sub">// displayed in the status bar and session</div>
            <div class="set-row"><div class="set-label">Display Name</div><input class="set-input" id="set-display-name" type="text" placeholder="<?= htmlspecialchars(CMS_DISPLAY_NAME) ?>" value="<?= htmlspecialchars(CMS_DISPLAY_NAME) ?>"></div>
            <div class="set-row"><div class="set-label">Username (for login)</div><input class="set-input" id="set-username" type="text" placeholder="<?= htmlspecialchars(CMS_USERNAME) ?>" value="<?= htmlspecialchars(CMS_USERNAME) ?>"></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Change Password</div>
            <div class="set-section-sub">// leave new password blank to keep current</div>
            <div class="set-row"><div class="set-label">Current Password <span style="color:var(--red)">*</span></div><input class="set-input" id="set-cur-pass" type="password" placeholder="Required to save any changes"></div>
            <div class="set-row"><div class="set-label">New Password</div><input class="set-input" id="set-new-pass" type="password" placeholder="Leave blank to keep current"></div>
            <div class="set-row"><div class="set-label">Confirm New Password</div><input class="set-input" id="set-new-pass2" type="password" placeholder="Repeat new password"></div>
            <button class="set-save-btn" onclick="saveSettings()">Save Changes →</button>
            <div class="set-msg" id="settings-msg"></div>
          </div>
        </div>

        <!-- SECURITY -->
        <div class="settings-page" id="spage-security">
          <div class="settings-page-title">Security</div>
          <div class="settings-page-sub">// access control and login protection</div>
          <div class="set-section">
            <div class="set-section-title">IP Whitelist</div>
            <div class="set-section-sub">// only allow CMS access from these IPs (leave empty for no restriction)</div>
            <div class="set-row"><div class="set-label">Allowed IP Addresses</div><textarea class="set-input" rows="4" placeholder="One IP per line&#10;e.g. 192.168.1.1&#10;81.200.x.x" style="resize:vertical;font-family:'DM Mono',monospace"></textarea></div>
            <button class="set-save-btn">Save Security Settings →</button>
          </div>
          <div class="set-section">
            <div class="set-section-title">Login Attempts</div>
            <div class="set-section-sub">// block IPs after repeated failed logins</div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Enable brute-force protection</div><div class="set-toggle-desc">// lock after 5 failed attempts for 15 minutes</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="set-row" style="margin-top:12px"><div class="set-label">Max login attempts</div><input class="set-input" type="number" value="5" style="width:80px"></div>
          </div>
          <div class="set-section" style="border-color:rgba(255,208,96,0.2)">
            <div class="set-section-title" style="color:var(--yellow)">Two-Factor Authentication</div>
            <div class="set-section-sub">// TOTP (Google Authenticator compatible) — coming soon</div>
            <div style="opacity:.5"><div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Enable 2FA</div><div class="set-toggle-desc">// scan QR code with your authenticator app</div></div><label class="toggle-switch"><input type="checkbox" disabled><span class="toggle-slider"></span></label></div></div>
          </div>
        </div>

        <!-- BUSINESS INFO -->
        <div class="settings-page" id="spage-business">
          <div class="settings-page-title">Business Information</div>
          <div class="settings-page-sub">// used across your website in snippets and modules</div>
          <div class="set-section">
            <div class="set-section-title">Basic Information</div>
            <div class="set-section-sub">// appears in footer, contact page, and module snippets</div>
            <div class="set-row"><div class="set-label">Business Name</div><input class="set-input" type="text" placeholder="e.g. Müllers Bäckerei GmbH"></div>
            <div class="set-row"><div class="set-label">Tagline / Slogan</div><input class="set-input" type="text" placeholder="e.g. Fresh every morning since 1987"></div>
            <div class="set-row"><div class="set-label">Business Type</div>
              <select class="set-select">
                <option>Bakery / Food</option><option>Restaurant / Café</option><option>Retail</option>
                <option>Beauty / Wellness</option><option>Medical</option><option>Trades / Craftsman</option>
                <option>Photography / Creative</option><option>Legal / Consulting</option><option>Other</option>
              </select>
            </div>
            <div class="set-row"><div class="set-label">VAT Number</div><input class="set-input" type="text" placeholder="DE123456789 (optional)"></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Contact Details</div>
            <div class="set-section-sub">// shown on your website (separate from your CMS login)</div>
            <div class="set-row"><div class="set-label">Business Address</div><input class="set-input" type="text" placeholder="Marktplatz 1, 06108 Halle"></div>
            <div class="set-input-row" style="margin-bottom:14px">
              <div style="flex:1"><div class="set-label">Phone</div><input class="set-input" type="tel" placeholder="+49 345 ..."></div>
              <div style="flex:1"><div class="set-label">Email (public)</div><input class="set-input" type="email" placeholder="info@business.de"></div>
            </div>
            <div class="set-input-row">
              <div style="flex:1"><div class="set-label">WhatsApp Number</div><input class="set-input" type="tel" placeholder="+49 ..."></div>
              <div style="flex:1"><div class="set-label">Website URL</div><input class="set-input" type="url" placeholder="https://mybusiness.de"></div>
            </div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Site Language & Region</div>
            <div class="set-input-row">
              <div style="flex:1"><div class="set-label">Primary Language</div><select class="set-select"><option>German (DE)</option><option>English (EN)</option><option>French (FR)</option><option>Polish (PL)</option><option>Turkish (TR)</option></select></div>
              <div style="flex:1"><div class="set-label">Currency</div><select class="set-select"><option>€ Euro</option><option>£ GBP</option><option>$ USD</option><option>CHF</option></select></div>
            </div>
            <button class="set-save-btn" style="margin-top:16px">Save Business Info →</button>
          </div>
        </div>

        <!-- DESIGN -->
        <div class="settings-page" id="spage-design">
          <div class="settings-page-title">Design & Branding</div>
          <div class="settings-page-sub">// site-wide color scheme and typography</div>
          <div class="set-section">
            <div class="set-section-title">Brand Colors</div>
            <div class="set-section-sub">// injected as CSS custom properties into all pages</div>
            <div class="set-row"><div class="set-label">Primary Color</div><div class="ph-color-row"><input type="color" class="color-preview" value="#7c6cfc"><input class="set-input" style="flex:1" value="#7c6cfc" placeholder="#rrggbb"><span style="font-size:12px;color:var(--text3);white-space:nowrap">Buttons, accents</span></div></div>
            <div class="set-row"><div class="set-label">Secondary Color</div><div class="ph-color-row"><input type="color" class="color-preview" value="#3dffa0"><input class="set-input" style="flex:1" value="#3dffa0" placeholder="#rrggbb"><span style="font-size:12px;color:var(--text3);white-space:nowrap">Highlights</span></div></div>
            <div class="set-row"><div class="set-label">Background Color</div><div class="ph-color-row"><input type="color" class="color-preview" value="#ffffff"><input class="set-input" style="flex:1" value="#ffffff" placeholder="#rrggbb"><span style="font-size:12px;color:var(--text3);white-space:nowrap">Page background</span></div></div>
            <div class="set-row"><div class="set-label">Text Color</div><div class="ph-color-row"><input type="color" class="color-preview" value="#1a1a1a"><input class="set-input" style="flex:1" value="#1a1a1a" placeholder="#rrggbb"><span style="font-size:12px;color:var(--text3);white-space:nowrap">Body text</span></div></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Typography</div>
            <div class="set-section-sub">// Google Fonts loaded automatically</div>
            <div class="set-row"><div class="set-label">Heading Font</div><select class="set-select"><option>Playfair Display</option><option>Inter</option><option>Syne</option><option>Montserrat</option><option>Oswald</option><option>Merriweather</option><option>Lato</option></select></div>
            <div class="set-row"><div class="set-label">Body Font</div><select class="set-select"><option>DM Sans</option><option>Inter</option><option>Roboto</option><option>Open Sans</option><option>Source Sans 3</option><option>Nunito</option></select></div>
            <div class="set-row"><div class="set-label">Base Font Size</div><select class="set-select"><option>14px (compact)</option><option selected>16px (default)</option><option>18px (large)</option></select></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Logo</div>
            <div class="set-row"><div class="set-label">Upload Logo</div><input class="set-input" type="text" placeholder="Upload logo file (PNG, SVG)…" style="cursor:not-allowed"></div>
            <button class="set-save-btn" style="margin-top:4px">Apply Design Settings →</button>
          </div>
        </div>

        <!-- OPENING HOURS -->
        <div class="settings-page" id="spage-hours">
          <div class="settings-page-title">Opening Hours</div>
          <div class="settings-page-sub">// used in booking module, auto-banners, and site snippets</div>
          <div class="set-section">
            <div class="set-section-title">Weekly Schedule</div>
            <div class="set-section-sub">// leave closed checked for days you don't operate</div>
            <div id="hours-grid">
              <!-- Built by JS -->
            </div>
            <div class="set-row" style="margin-top:16px"><div class="set-label">Timezone</div><select class="set-select"><option>Europe/Berlin (CET/CEST)</option><option>Europe/London</option><option>Europe/Paris</option><option>UTC</option></select></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Show "Kitchen closed" banner automatically</div><div class="set-toggle-desc">// displays a site banner 30 min before closing</div></div><label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label></div>
            <button class="set-save-btn" style="margin-top:16px">Save Opening Hours →</button>
          </div>
        </div>

        <!-- SOCIAL MEDIA -->
        <div class="settings-page" id="spage-social">
          <div class="settings-page-title">Social Media</div>
          <div class="settings-page-sub">// one place to manage all your profiles</div>
          <div class="set-section">
            <div class="set-section-title">Profiles</div>
            <div class="set-section-sub">// used in social links module and footer snippets</div>
            <div class="set-row"><div class="set-label">Facebook</div><input class="set-input" type="url" placeholder="https://facebook.com/yourpage"></div>
            <div class="set-row"><div class="set-label">Instagram</div><input class="set-input" type="url" placeholder="https://instagram.com/yourhandle"></div>
            <div class="set-row"><div class="set-label">LinkedIn</div><input class="set-input" type="url" placeholder="https://linkedin.com/company/..."></div>
            <div class="set-row"><div class="set-label">YouTube</div><input class="set-input" type="url" placeholder="https://youtube.com/@channel"></div>
            <div class="set-row"><div class="set-label">TikTok</div><input class="set-input" type="url" placeholder="https://tiktok.com/@handle"></div>
            <div class="set-row"><div class="set-label">X (Twitter)</div><input class="set-input" type="url" placeholder="https://x.com/handle"></div>
            <div class="set-row"><div class="set-label">Pinterest</div><input class="set-input" type="url" placeholder="https://pinterest.com/profile"></div>
            <div class="set-row"><div class="set-label">WhatsApp</div><input class="set-input" type="tel" placeholder="+49 ..."></div>
            <button class="set-save-btn">Save Social Links →</button>
          </div>
        </div>

        <!-- SEO SETTINGS -->
        <div class="settings-page" id="spage-seosettings">
          <div class="settings-page-title">SEO & Meta</div>
          <div class="settings-page-sub">// site-wide defaults for search engine visibility</div>
          <div class="set-section">
            <div class="set-section-title">Default Meta Tags</div>
            <div class="set-section-sub">// applied to pages that don't have custom meta tags</div>
            <div class="set-row"><div class="set-label">Default Meta Description</div><textarea class="set-input" rows="3" placeholder="Short description of your business for search results (150–160 chars)…" style="resize:vertical"></textarea></div>
            <div class="set-row"><div class="set-label">Default Open Graph Image</div><input class="set-input" type="text" placeholder="Upload OG image (1200×630px)…"></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Indexing</div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Allow search engines to index this site</div><div class="set-toggle-desc">// controls robots.txt and meta robots tags</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Auto-generate sitemap.xml</div><div class="set-toggle-desc">// regenerated on every page save</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="set-row" style="margin-top:12px"><div class="set-label">Google Search Console Verification</div><input class="set-input" type="text" placeholder="google-site-verification=..."></div>
            <button class="set-save-btn" style="margin-top:4px">Save SEO Settings →</button>
          </div>
        </div>

        <!-- EMAIL / SMTP -->
        <div class="settings-page" id="spage-email">
          <div class="settings-page-title">Email (SMTP)</div>
          <div class="settings-page-sub">// used for appointment confirmations, form notifications, auto-replies</div>
          <div class="set-section">
            <div class="set-section-title">SMTP Configuration</div>
            <div class="set-input-row" style="margin-bottom:14px">
              <div style="flex:2"><div class="set-label">SMTP Host</div><input class="set-input" type="text" placeholder="smtp.brevo.com"></div>
              <div style="flex:1"><div class="set-label">Port</div><input class="set-input" type="number" placeholder="587" value="587"></div>
            </div>
            <div class="set-input-row" style="margin-bottom:14px">
              <div style="flex:1"><div class="set-label">Username</div><input class="set-input" type="text" placeholder="your@email.com"></div>
              <div style="flex:1"><div class="set-label">Password / API Key</div><input class="set-input" type="password" placeholder="••••••••"></div>
            </div>
            <div class="set-row"><div class="set-label">Encryption</div><select class="set-select"><option>TLS (recommended)</option><option>SSL</option><option>None</option></select></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Sender Identity</div>
            <div class="set-input-row">
              <div style="flex:1"><div class="set-label">From Name</div><input class="set-input" type="text" placeholder="My Business"></div>
              <div style="flex:1"><div class="set-label">From Email</div><input class="set-input" type="email" placeholder="hello@mybusiness.de"></div>
            </div>
            <div class="set-row" style="margin-top:14px"><div class="set-label">Reply-To Email</div><input class="set-input" type="email" placeholder="Same as From (optional)"></div>
          </div>
          <div style="display:flex;gap:10px;margin-top:4px">
            <button class="set-save-btn">Save Email Settings →</button>
            <button class="set-save-btn" style="background:var(--bg3);border:1px solid var(--border);color:var(--text2);box-shadow:none">📧 Send Test Email</button>
          </div>
        </div>

        <!-- ANALYTICS SETTINGS -->
        <div class="settings-page" id="spage-analyticssettings">
          <div class="settings-page-title">Analytics</div>
          <div class="settings-page-sub">// connect tracking tools and configure built-in analytics</div>
          <div class="set-section">
            <div class="set-section-title">Google Analytics</div>
            <div class="set-section-sub">// paste your GA4 Measurement ID to enable</div>
            <div class="set-row"><div class="set-label">Measurement ID</div><input class="set-input" type="text" placeholder="G-XXXXXXXXXX"></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Enable Google Analytics</div><div class="set-toggle-desc">// injects gtag.js into all pages</div></div><label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Built-in Cookieless Analytics</div>
            <div class="set-section-sub">// no consent needed — privacy-first visitor stats</div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Enable built-in analytics</div><div class="set-toggle-desc">// stores data in cms_data/analytics.json</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Track page views</div><div class="set-toggle-desc">// log every page visit</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Track referrers</div><div class="set-toggle-desc">// where visitors come from</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <button class="set-save-btn" style="margin-top:12px">Save Analytics Settings →</button>
          </div>
        </div>

        <!-- API KEYS / INTEGRATIONS -->
        <div class="settings-page" id="spage-integrations">
          <div class="settings-page-title">API Keys & Integrations</div>
          <div class="settings-page-sub">// configure external services used by modules</div>
          <div class="set-section">
            <div class="set-section-title">Brevo (Email Marketing)</div>
            <div class="set-row"><div class="set-label">Brevo API Key</div><input class="set-input" type="password" placeholder="xkeysib-..."></div>
            <div class="set-row"><div class="set-label">Default Mailing List ID</div><input class="set-input" type="text" placeholder="e.g. 3"></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">reCAPTCHA v3</div>
            <div class="set-row"><div class="set-label">Site Key (public)</div><input class="set-input" type="text" placeholder="6Lc..."></div>
            <div class="set-row"><div class="set-label">Secret Key (private)</div><input class="set-input" type="password" placeholder="6Lc..."></div>
            <div class="set-row"><div class="set-label">Score Threshold (0.0–1.0)</div><input class="set-input" type="number" placeholder="0.5" min="0" max="1" step="0.1"></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Other</div>
            <div class="set-row"><div class="set-label">Live Chat Embed Code</div><textarea class="set-input" rows="3" placeholder="Paste Tawk.to or Crisp embed snippet here…" style="resize:vertical;font-family:'DM Mono',monospace;font-size:11px"></textarea></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Inject chat widget on all pages</div><div class="set-toggle-desc">// auto-adds snippet before &lt;/body&gt;</div></div><label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label></div>
            <button class="set-save-btn" style="margin-top:12px">Save API Keys →</button>
          </div>
        </div>

        <!-- MAINTENANCE -->
        <div class="settings-page" id="spage-maintenance">
          <div class="settings-page-title">Maintenance</div>
          <div class="settings-page-sub">// site visibility and server-level controls</div>
          <div class="set-section">
            <div class="set-section-title">Maintenance Mode</div>
            <div class="set-section-sub">// hides the site from visitors while you work</div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Enable maintenance mode</div><div class="set-toggle-desc">// shows "We'll be back soon" page to visitors</div></div><label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label></div>
            <div class="set-row" style="margin-top:12px"><div class="set-label">Maintenance Message</div><input class="set-input" type="text" placeholder="We're updating the site. Back shortly!"></div>
            <div class="set-row"><div class="set-label">Expected Back At (optional)</div><input class="set-input" type="datetime-local"></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Cache & Performance</div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Auto-append cache buster on save</div><div class="set-toggle-desc">// adds ?v=timestamp to CSS/JS links</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Auto-add loading="lazy" to images</div><div class="set-toggle-desc">// improves page speed on save</div></div><label class="toggle-switch"><input type="checkbox"><span class="toggle-slider"></span></label></div>
            <button class="set-save-btn" style="margin-top:12px">Save Maintenance Settings →</button>
          </div>
          <div class="set-section" style="border-color:rgba(255,95,126,0.2)">
            <div class="set-section-title" style="color:var(--red)">Danger Zone</div>
            <div class="set-section-sub">// irreversible actions</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap">
              <button class="set-danger-btn">🗑 Clear all auto-backups</button>
              <button class="set-danger-btn">⚠ Reset CMS data</button>
            </div>
          </div>
        </div>

        <!-- ADVANCED -->
        <div class="settings-page" id="spage-advanced">
          <div class="settings-page-title">Advanced</div>
          <div class="settings-page-sub">// technical settings for power users</div>
          <div class="set-section">
            <div class="set-section-title">Editor Preferences</div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Auto-save draft to browser</div><div class="set-toggle-desc">// saves unsaved work to localStorage every 60s</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Confirm before switching pages</div><div class="set-toggle-desc">// warns if you have unsaved changes</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Show file size in status bar</div><div class="set-toggle-desc">// current page HTML size in KB</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Backup Settings</div>
            <div class="set-row"><div class="set-label">Backups to keep per file</div><input class="set-input" type="number" value="10" style="width:80px"></div>
            <div class="set-toggle-row"><div class="set-toggle-info"><div class="set-toggle-title">Auto-backup on every save</div><div class="set-toggle-desc">// current behaviour — saves previous version</div></div><label class="toggle-switch"><input type="checkbox" checked><span class="toggle-slider"></span></label></div>
          </div>
          <div class="set-section">
            <div class="set-section-title">Custom Code</div>
            <div class="set-section-sub">// injected into &lt;head&gt; of all pages</div>
            <div class="set-row"><div class="set-label">Custom &lt;head&gt; HTML</div><textarea class="set-input" rows="5" placeholder="<!-- e.g. analytics, fonts, custom styles -->" style="resize:vertical;font-family:'DM Mono',monospace;font-size:11px"></textarea></div>
            <div class="set-row"><div class="set-label">Custom &lt;/body&gt; HTML</div><textarea class="set-input" rows="5" placeholder="<!-- e.g. chat widgets, tracking pixels -->" style="resize:vertical;font-family:'DM Mono',monospace;font-size:11px"></textarea></div>
            <button class="set-save-btn">Save Advanced Settings →</button>
          </div>
        </div>

        <!-- FEEDBACK -->
        <div class="settings-page" id="spage-feedback">
          <div class="settings-page-title">Feedback</div>
          <div class="settings-page-sub">// SR Edit <?= CMS_VERSION ?> — help shape the product</div>
          <div class="set-section" style="border-color:rgba(255,208,96,0.2)">
            <div class="set-section-title" style="color:var(--yellow)">⚡ Alpha Feedback</div>
            <div class="set-section-sub">// your feedback directly shapes what gets built next</div>
            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
              <button class="set-save-btn" style="background:rgba(255,95,126,0.15);border:1px solid rgba(255,95,126,0.3);color:var(--red);box-shadow:none" onclick="openFeedback('bug')">🐛 Report a Bug</button>
              <button class="set-save-btn" style="background:rgba(61,255,160,0.1);border:1px solid rgba(61,255,160,0.2);color:var(--green);box-shadow:none" onclick="openFeedback('feature')">💡 Request a Feature</button>
              <button class="set-save-btn" style="background:var(--bg3);border:1px solid var(--border);color:var(--text2);box-shadow:none" onclick="openFeedback('other')">✉ General Feedback</button>
            </div>
          </div>
          <div class="set-section">
            <div class="set-section-title">About</div>
            <div class="set-section-sub">// SR Edit <?= CMS_VERSION ?></div>
            <div style="font-size:12px;color:var(--text3);font-family:'DM Mono',monospace;line-height:2">
              Version: SR Edit <?= CMS_VERSION ?><br>
              PHP: <?= PHP_VERSION ?><br>
              Server: <?= $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown' ?><br>
              License: <span id="about-license-status">checking…</span>
            </div>
          </div>
        </div>

      </div><!-- /settings-content -->
    </div><!-- /settings-layout -->
  </div><!-- /settings-screen -->

  <!-- STATUS BAR -->
  <div class="statusbar">
    <div class="status-item"><div class="status-dot" id="status-dot"></div><span id="status-text">Ready</span></div>
    <div class="status-item" style="margin-left:auto"><span id="status-history" style="color:var(--text3);font-family:'DM Mono',monospace"></span></div>
    <div class="status-item"><span id="status-chars" style="color:var(--text3)"></span></div>
    <div class="status-item"><span style="color:var(--text3)">SR Edit <span class="alpha-badge" style="font-size:8px;padding:1px 5px"><?= CMS_VERSION ?></span></span></div>
    <div class="status-item"><span id="status-user" style="color:var(--accent2)">@ <?= htmlspecialchars($_SESSION['cms_user'] ?? CMS_DISPLAY_NAME) ?></span></div>
  </div>
</div>

<!-- TOAST CONTAINER -->
<div class="toast-container" id="toast-container"></div>

<script>
// ═══════════════════════════════════════════════
//  STATE
// ═══════════════════════════════════════════════
const state = {
  currentFile: null,
  currentHtml: '',
  originalHtml: '',
  elements: [],
  dirty: false,
  editingEl: null,
  quill: null,
  currentModule: 'editor',
  _prevModule: 'editor',
  viewport: 'desktop',
  // Undo/redo history — each entry is { html, label }
  undoStack: [],
  redoStack: [],
  modules: [
    { id: 'editor',        name: 'Page Editor',        icon: '✏️',  cat: 'Editor',          desc: 'Click-to-edit live page editing with instant preview.',                           status: 'live' },
    { id: 'media',         name: 'Media Library',       icon: '🖼️',  cat: 'Editor',          desc: 'Upload, organize and insert images. Resize and optimize on upload.',              status: 'soon' },
    { id: 'snippets',      name: 'HTML Snippets',       icon: '🧩',  cat: 'Editor',          desc: 'Save and reuse HTML blocks across all your pages.',                               status: 'soon' },
    { id: 'diff',          name: 'Change History',      icon: '📜',  cat: 'Editor',          desc: 'Browse every saved version. Compare and restore previous states.',                status: 'soon' },
    { id: 'menu',          name: 'Restaurant Menu',     icon: '🍽️',  cat: 'Business',        desc: 'Build your full menu: categories, dishes, prices, allergens, dietary tags.',      status: 'soon' },
    { id: 'booking',       name: 'Appointments',        icon: '📅',  cat: 'Business',        desc: 'Visitor booking requests with calendar view and email confirmations.',             status: 'soon' },
    { id: 'products',      name: 'Product Catalogue',   icon: '🛍️',  cat: 'Business',        desc: 'Showcase products or services. No cart needed — inquiry forms included.',         status: 'soon' },
    { id: 'blog',          name: 'Blog & News',         icon: '📝',  cat: 'Business',        desc: 'Write posts, schedule them, manage categories. Auto RSS and index pages.',        status: 'soon' },
    { id: 'events',        name: 'Events',              icon: '🎪',  cat: 'Business',        desc: 'Create and list events with dates, locations, and ticket links.',                  status: 'soon' },
    { id: 'team',          name: 'Team Members',        icon: '👥',  cat: 'Business',        desc: 'Manage staff profiles: name, role, bio, photo, social links.',                   status: 'soon' },
    { id: 'testimonials',  name: 'Testimonials',        icon: '⭐',  cat: 'Business',        desc: 'Add customer reviews with star ratings. Choose what appears on site.',            status: 'soon' },
    { id: 'faq',           name: 'FAQ Manager',         icon: '❓',  cat: 'Business',        desc: 'FAQ accordion with categories. Auto Schema.org structured data.',                 status: 'soon' },
    { id: 'jobs',          name: 'Job Listings',        icon: '💼',  cat: 'Business',        desc: 'Post job openings with expiry dates, salary, and application links.',             status: 'soon' },
    { id: 'gallery',       name: 'Portfolio / Gallery', icon: '🖼️',  cat: 'Business',        desc: 'Showcase work with before/after photos, client name, category filter.',          status: 'soon' },
    { id: 'pricing',       name: 'Pricing Tables',      icon: '💰',  cat: 'Business',        desc: 'Visual pricing tiers with feature lists and highlighted recommended plans.',      status: 'soon' },
    { id: 'forms',         name: 'Form Builder',        icon: '📋',  cat: 'Communication',   desc: 'Drag-and-drop form builder. Text, email, phone, file, select, checkbox.',        status: 'soon' },
    { id: 'inbox',         name: 'Form Inbox',          icon: '📬',  cat: 'Communication',   desc: 'All form submissions in one inbox. Star, reply, export to CSV.',                  status: 'soon' },
    { id: 'newsletter',    name: 'Newsletter',          icon: '📧',  cat: 'Communication',   desc: 'Brevo mailing list integration. Signup forms and campaign archive.',              status: 'soon' },
    { id: 'seo',           name: 'SEO Analyzer',        icon: '🔍',  cat: 'Analytics & SEO', desc: 'Scan pages for title, meta, heading, alt text, and broken link issues.',         status: 'soon' },
    { id: 'analytics',     name: 'Analytics',           icon: '📊',  cat: 'Analytics & SEO', desc: 'Cookieless visitor stats: page views, devices, referrers. No consent needed.',   status: 'soon' },
    { id: 'redirects',     name: 'Redirects',           icon: '↪️',  cat: 'Analytics & SEO', desc: 'Manage 301/302 redirects without touching .htaccess.',                           status: 'soon' },
    { id: 'export',        name: 'Export & Deploy',     icon: '🚀',  cat: 'Tools',           desc: 'Export site as ZIP or deploy via FTP with optional minification.',               status: 'soon' },
    { id: 'backup',        name: 'Backup & Restore',    icon: '💾',  cat: 'Tools',           desc: 'Full-site backups downloadable as ZIP. Restore from any previous backup.',       status: 'soon' },
    { id: 'accessibility', name: 'Accessibility',       icon: '♿',  cat: 'Tools',           desc: 'Check for WCAG issues: missing alt, low contrast, unlabelled fields.',           status: 'soon' },
  ]
};

// ═══════════════════════════════════════════════
//  API HELPERS
// ═══════════════════════════════════════════════
// Resolve api.php relative to THIS file's directory, regardless of filename
const API_URL = (function() {
  const href = window.location.href.split('?')[0];
  return href.replace(/[^/]+$/, '') + 'api.php';
})();

async function api(action, data = {}) {
  try {
    const r = await fetch(API_URL, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ action, _csrf: CSRF_TOKEN, ...data })
    });
    const text = await r.text();
    try {
      return JSON.parse(text);
    } catch (e) {
      console.error('api.php returned non-JSON (first 500 chars):', text.substring(0, 500));
      toast('API error — check console for details', 'error');
      return { success: false, error: 'Server returned invalid response.' };
    }
  } catch (e) {
    console.error('API fetch failed:', e);
    toast('Network error: ' + e.message, 'error');
    return { success: false, error: e.message };
  }
}

// ═══════════════════════════════════════════════
//  SIDEBAR COLLAPSE TOGGLES
// ═══════════════════════════════════════════════
const sidebarState = {
  left:  true,  // open
  right: true,  // open
};

function toggleLeftSidebar() {
  // On mobile, sidebar is handled by the drawer — ignore desktop toggle
  if (window.innerWidth <= 1035) return;
  sidebarState.left = !sidebarState.left;
  const sb  = document.getElementById('left-sidebar');
  const btn = document.getElementById('left-toggle');
  if (sidebarState.left) {
    sb.classList.remove('collapsed');
    if (btn) { btn.textContent = '‹'; btn.title = 'Hide pages panel'; }
  } else {
    sb.classList.add('collapsed');
    if (btn) { btn.textContent = '›'; btn.title = 'Show pages panel'; }
  }
  localStorage.setItem('sr-left-panel', sidebarState.left ? '1' : '0');
}

function toggleRightSidebar() {
  sidebarState.right = !sidebarState.right;
  const ep  = document.querySelector('.elements-panel');
  const btn = document.getElementById('right-toggle');
  if (sidebarState.right) {
    ep.classList.remove('collapsed');
    btn.textContent = '›';
    btn.title = 'Hide elements panel';
  } else {
    ep.classList.add('collapsed');
    btn.textContent = '‹';
    btn.title = 'Show elements panel';
  }
  localStorage.setItem('sr-right-panel', sidebarState.right ? '1' : '0');
}

function restoreSidebarState() {
  // On mobile (<1035px), sidebar is handled by drawer — don't restore collapsed state
  if (window.innerWidth <= 1035) return;
  const l = localStorage.getItem('sr-left-panel');
  const r = localStorage.getItem('sr-right-panel');
  if (l === '0') toggleLeftSidebar();
  if (r === '0') toggleRightSidebar();
}

// ═══════════════════════════════════════════════
//  MOBILE DRAWER
// ═══════════════════════════════════════════════
function toggleDrawer() {
  const open = document.getElementById('mobile-drawer').classList.contains('open');
  open ? closeDrawer() : openDrawer();
}
function openDrawer() {
  document.getElementById('mobile-drawer').classList.add('open');
  document.getElementById('drawer-overlay').classList.add('open');
  document.getElementById('hamburger').classList.add('open');
  document.body.style.overflow = 'hidden';
}
function closeDrawer() {
  document.getElementById('mobile-drawer').classList.remove('open');
  document.getElementById('drawer-overlay').classList.remove('open');
  document.getElementById('hamburger').classList.remove('open');
  document.body.style.overflow = '';
}
// Sync drawer file tree whenever the sidebar tree is populated
function syncDrawerFileTree() {
  const src = document.getElementById('file-tree');
  const dst = document.getElementById('drawer-file-tree');
  if (src && dst) dst.innerHTML = src.innerHTML;
  // Re-bind click handlers in drawer copy
  dst.querySelectorAll('.file-item').forEach(item => {
    item.onclick = () => {
      const path = item.getAttribute('data-path');
      if (path) { loadFileByPath(path, item); closeDrawer(); }
    };
  });
}
// Sync drawer save button disabled state
function syncDrawerSave() {
  const mainSave = document.getElementById('btn-save');
  const drawerSave = document.getElementById('drawer-save');
  if (drawerSave && mainSave) drawerSave.disabled = mainSave.disabled;
}

// ═══════════════════════════════════════════════
//  TAP HINT
// ═══════════════════════════════════════════════
const HINT_KEY = 'sr-edit-hint-dismissed';

function showTapHint() {
  // Only on touch/small screen, only if not permanently dismissed
  if (localStorage.getItem(HINT_KEY) === 'permanent') return;
  if (window.innerWidth > 900) return;
  const hint = document.getElementById('tap-hint');
  if (hint) { hint.style.display = 'flex'; }
}
function dismissHint(permanent) {
  const hint = document.getElementById('tap-hint');
  if (hint) hint.style.display = 'none';
  if (permanent) localStorage.setItem(HINT_KEY, 'permanent');
  // If just X (not permanent), hint will reappear on next login (no localStorage write)
}

// ═══════════════════════════════════════════════
//  THEME (DARK / LIGHT)
// ═══════════════════════════════════════════════
function applyTheme(theme) {
  document.documentElement.setAttribute('data-theme', theme === 'light' ? 'light' : '');
  const isDark = theme !== 'light';
  document.querySelectorAll('#btn-theme, #btn-theme-login, #drawer-theme-btn').forEach(btn => {
    if (btn) btn.textContent = isDark ? '🌙' : '☀️';
    if (btn) btn.title = isDark ? 'Switch to light mode' : 'Switch to dark mode';
  });
  localStorage.setItem('sr-edit-theme', theme);
}

function toggleTheme() {
  const current = localStorage.getItem('sr-edit-theme') || 'dark';
  applyTheme(current === 'dark' ? 'light' : 'dark');
}

// Apply saved theme immediately
(function() {
  const saved = localStorage.getItem('sr-edit-theme') || 'dark';
  applyTheme(saved);
})();

// ═══════════════════════════════════════════════
//  HISTORY (UNDO / REDO)
// ═══════════════════════════════════════════════
const MAX_HISTORY = 50;

function historyPush(label) {
  // Save current HTML before the change is applied
  state.undoStack.push({ html: state.currentHtml, label });
  if (state.undoStack.length > MAX_HISTORY) state.undoStack.shift();
  state.redoStack = []; // clear redo on new action
  updateHistoryUI();
}

function undo() {
  if (!state.undoStack.length) return;
  const entry = state.undoStack.pop();
  state.redoStack.push({ html: state.currentHtml, label: entry.label });
  state.currentHtml = entry.html;
  renderPreview(state.currentHtml);
  state.dirty = (state.currentHtml !== state.originalHtml);
  document.getElementById('btn-save').disabled = !state.dirty;
  document.getElementById('btn-discard').disabled = !state.dirty;
  updateHistoryUI();
  setStatus('Undo: ' + entry.label, 'warn');
  toast('Undo: ' + entry.label, 'info');
}

function redo() {
  if (!state.redoStack.length) return;
  const entry = state.redoStack.pop();
  state.undoStack.push({ html: state.currentHtml, label: entry.label });
  state.currentHtml = entry.html;
  renderPreview(state.currentHtml);
  state.dirty = (state.currentHtml !== state.originalHtml);
  document.getElementById('btn-save').disabled = !state.dirty;
  document.getElementById('btn-discard').disabled = !state.dirty;
  updateHistoryUI();
  setStatus('Redo: ' + entry.label, 'warn');
  toast('Redo: ' + entry.label, 'info');
}

function updateHistoryUI() {
  const undoBtn = document.getElementById('btn-undo');
  const redoBtn = document.getElementById('btn-redo');
  undoBtn.disabled = !state.undoStack.length;
  redoBtn.disabled = !state.redoStack.length;
  const u = state.undoStack.length, r = state.redoStack.length;
  const hist = document.getElementById('status-history');
  if (hist) hist.textContent = u || r ? `↩${u} ↪${r}` : '';
  // Update tooltips
  const last = state.undoStack[state.undoStack.length - 1];
  undoBtn.title = last ? `Undo: ${last.label} (Ctrl+Z)` : 'Undo (Ctrl+Z)';
  const next = state.redoStack[state.redoStack.length - 1];
  redoBtn.title = next ? `Redo: ${next.label} (Ctrl+Y)` : 'Redo (Ctrl+Y)';
}

// Keyboard shortcuts
document.addEventListener('keydown', e => {
  const mod = e.ctrlKey || e.metaKey;
  if (mod && e.key === 'z' && !e.shiftKey) { e.preventDefault(); undo(); }
  if (mod && (e.key === 'y' || (e.key === 'z' && e.shiftKey))) { e.preventDefault(); redo(); }
  if (mod && e.key === 's') { e.preventDefault(); document.getElementById('btn-save').click(); }
  if (e.key === 'Escape') {
    const ss = document.getElementById('settings-screen');
    if (ss && ss.classList.contains('active')) closeSettings();
  }
});

document.getElementById('btn-undo').addEventListener('click', undo);
document.getElementById('btn-redo').addEventListener('click', redo);

// ═══════════════════════════════════════════════
//  VIEWPORT SWITCHER
// ═══════════════════════════════════════════════
function setViewport(vp) {
  state.viewport = vp;
  const wrap = document.getElementById('preview-frame-wrap');
  wrap.classList.remove('vp-mobile', 'vp-tablet', 'vp-desktop');
  if (vp !== 'desktop') wrap.classList.add('vp-' + vp);
  document.querySelectorAll('.vp-btn').forEach(b => b.classList.remove('active'));
  // match by title keyword
  document.querySelectorAll('.vp-btn').forEach(b => {
    if ((vp === 'mobile' && b.title.includes('Mobile')) ||
        (vp === 'tablet' && b.title.includes('Tablet')) ||
        (vp === 'desktop' && b.title.includes('Desktop'))) {
      b.classList.add('active');
    }
  });
  const labels = { mobile: '375px', tablet: '768px', desktop: 'Desktop' };
  setStatus('Viewport: ' + labels[vp]);
}


// CSRF token from PHP session
const CSRF_TOKEN = '<?= $csrf_token ?>';

document.addEventListener('DOMContentLoaded', async () => {
  // Reset sidebar localStorage state on mobile to prevent stuck collapsed state
  if (window.innerWidth <= 1035) {
    localStorage.removeItem('sr-left-panel');
    localStorage.removeItem('sr-right-panel');
  }
  // One-time migration: clear any stuck collapsed state from previous buggy builds
  // Users who hit the collapse bug had sr-left-panel='0' stuck in localStorage
  // They couldn't recover because the toggle was clipped. Reset once via version key.
  const sidebarFixVer = 'sr-sidebar-fix-v2';
  if (!localStorage.getItem(sidebarFixVer)) {
    localStorage.removeItem('sr-left-panel');
    localStorage.setItem(sidebarFixVer, '1');
  }
  restoreSidebarState();
  await loadFileTree();
  renderModulesGrid();
  verifyLicense();
  buildHoursGrid();
});

function buildHoursGrid() {
  const grid = document.getElementById('hours-grid');
  if (!grid) return;
  const days = ['Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'];
  grid.innerHTML = days.map(day => {
    const k = day.toLowerCase();
    return `<div class="set-hours-grid">
      <span class="day-name">${day.slice(0,3)}</span>
      <input class="set-time-input" type="time" name="hours_${k}_open" value="09:00">
      <input class="set-time-input" type="time" name="hours_${k}_close" value="18:00">
      <label style="display:flex;align-items:center;gap:5px;font-size:11px;cursor:pointer;font-family:'DM Mono',monospace;color:var(--text3)">
        <input type="checkbox" name="hours_${k}_closed" style="accent-color:var(--red)" onchange="toggleHourDay(this)"> Closed
      </label>
    </div>`;
  }).join('');
}

function toggleHourDay(cb) {
  const row = cb.closest('.set-hours-grid');
  row.querySelectorAll('input[type=time]').forEach(i => {
    i.disabled = cb.checked;
    i.style.opacity = cb.checked ? '0.3' : '1';
  });
}

// ═══════════════════════════════════════════════
//  FILE TREE
// ═══════════════════════════════════════════════
async function loadFileTree() {
  const tree = document.getElementById('file-tree');
  tree.innerHTML = `<div style="padding:20px 16px;color:var(--text3);font-size:12px;font-family:'DM Mono',monospace">Scanning…</div>`;
  const res = await api('list_files');
  if (!res.success) { tree.innerHTML = `<div style="padding:16px;color:var(--red);font-size:12px">Error loading files</div>`; return; }
  renderTree(res.tree, tree, '');
}

function renderTree(node, container, indent) {
  container.innerHTML = '';
  const sectionDiv = document.createElement('div');
  sectionDiv.innerHTML = `<div class="sidebar-section">Pages</div>`;
  container.appendChild(sectionDiv);
  renderNodes(node, container, 0);
  // Sync file list into mobile drawer
  syncDrawerFileTree();
}

function renderNodes(nodes, container, depth) {
  nodes.forEach(n => {
    const d = document.createElement('div');
    if (n.type === 'folder') {
      d.className = 'folder-item';
      d.style.paddingLeft = (16 + depth * 14) + 'px';
      d.innerHTML = `<span class="folder-toggle" id="ft-${n.id}">▶</span><span class="file-icon">📁</span><span class="file-name">${n.name}</span>`;
      const children = document.createElement('div');
      children.id = 'fc-' + n.id;
      children.style.display = 'none';
      d.onclick = () => {
        const open = children.style.display !== 'none';
        children.style.display = open ? 'none' : 'block';
        document.getElementById('ft-' + n.id).classList.toggle('open', !open);
      };
      container.appendChild(d);
      container.appendChild(children);
      renderNodes(n.children || [], children, depth + 1);
    } else {
      d.className = 'file-item';
      d.id = 'fi-' + btoa(n.path).replace(/=/g,'');
      d.dataset.path = n.path;
      d.style.paddingLeft = (16 + depth * 14) + 'px';
      d.innerHTML = `<span class="file-icon">📄</span><span class="file-name">${n.name}</span>`;
      d.onclick = () => loadFile(n.path, d);
      container.appendChild(d);
    }
  });
}

// Helper so drawer can load a file by path
function loadFileByPath(path) {
  const el = document.querySelector(`.file-item[data-path="${CSS.escape(path)}"]`)
          || document.querySelector(`#fi-${btoa(path).replace(/=/g,'')}`);
  if (el) loadFile(path, el);
}

// ═══════════════════════════════════════════════
//  LOAD FILE
// ═══════════════════════════════════════════════
async function loadFile(path, el) {
  if (state.dirty && !confirm('Unsaved changes — discard and load new file?')) return;

  document.querySelectorAll('.file-item.active').forEach(e => e.classList.remove('active'));
  if (el) el.classList.add('active');

  state.currentFile = path;
  state.dirty = false;
  state.undoStack = [];
  state.redoStack = [];
  document.getElementById('btn-save').disabled = true;
  document.getElementById('btn-discard').disabled = true;
  syncDrawerSave();
  updateHistoryUI();
  document.getElementById('btn-inject').disabled = false;

  document.getElementById('breadcrumb-file').textContent = path;
  document.getElementById('preview-url').textContent = path;

  document.getElementById('preview-empty').style.display = 'none';
  document.getElementById('preview-loading').style.display = 'flex';
  document.getElementById('preview-frame').style.display = 'none';
  document.getElementById('tap-hint').style.display = 'none';

  const res = await api('read_file', { path });
  if (!res.success) { toast('Failed to load file', 'error'); return; }

  state.currentHtml = res.content;
  state.originalHtml = res.content;

  renderPreview(res.content);
  updateCharCount();
  setStatus('Loaded: ' + path);
  showModule('editor');
  // Show tap hint on mobile after file loads
  setTimeout(showTapHint, 800);
}

// ═══════════════════════════════════════════════
//  PREVIEW
// ═══════════════════════════════════════════════
function renderPreview(html) {
  const frame = document.getElementById('preview-frame');
  const loading = document.getElementById('preview-loading');

  // Inject CMS interaction script into the iframe
  const cmsScript = `
  <script>
  (function() {
    const TEXT_TAGS = ['H1','H2','H3','H4','H5','H6','P','SPAN','A','LI','TD','TH','LABEL','BUTTON','STRONG','EM','BLOCKQUOTE','FIGCAPTION','CITE','SMALL','MARK','CAPTION'];
    let hovered = null;

    function injectDataAttrs() {
      let count = 0;
      TEXT_TAGS.forEach(tag => {
        document.querySelectorAll(tag).forEach(el => {
          if (!el.getAttribute('data-cms')) {
            el.setAttribute('data-cms', tag.toLowerCase() + '-' + (++count));
          }
        });
      });
      window.parent.postMessage({ type: 'cms_ready', count }, '*');
      rebuildList();
    }

    function rebuildList() {
      const els = [];
      document.querySelectorAll('[data-cms]').forEach(el => {
        els.push({
          id: el.getAttribute('data-cms'),
          tag: el.tagName.toLowerCase(),
          text: (el.textContent || '').trim().substring(0, 80)
        });
      });
      window.parent.postMessage({ type: 'cms_elements', elements: els }, '*');
    }

    document.addEventListener('DOMContentLoaded', injectDataAttrs);
    if (document.readyState !== 'loading') injectDataAttrs();

    document.addEventListener('mouseover', e => {
      const el = e.target.closest('[data-cms]');
      if (el && el !== hovered) {
        if (hovered) hovered.classList.remove('cms-hover-highlight');
        hovered = el;
        el.classList.add('cms-hover-highlight');
      }
    });
    document.addEventListener('mouseout', e => {
      const el = e.target.closest('[data-cms]');
      if (el) el.classList.remove('cms-hover-highlight');
    });
    document.addEventListener('click', e => {
      const el = e.target.closest('[data-cms]');
      if (el) {
        e.preventDefault();
        e.stopPropagation();
        const rect = el.getBoundingClientRect();
        // Collect all attributes
        const attrs = {};
        for (const a of el.attributes) {
          attrs[a.name] = a.value;
        }
        window.parent.postMessage({
          type: 'cms_click',
          id: el.getAttribute('data-cms'),
          tag: el.tagName.toLowerCase(),
          text: el.innerHTML,
          attrs: attrs,
          rect: { top: rect.top, left: rect.left, bottom: rect.bottom, right: rect.right, width: rect.width, height: rect.height }
        }, '*');
        document.querySelectorAll('.cms-selected-highlight').forEach(e => e.classList.remove('cms-selected-highlight'));
        el.classList.add('cms-selected-highlight');
      }
    });

    window.addEventListener('message', e => {
      if (e.data.type === 'cms_update_attrs') {
        const el = document.querySelector('[data-cms="' + e.data.id + '"]');
        if (el) {
          // Remove attrs marked for deletion
          (e.data.remove || []).forEach(name => el.removeAttribute(name));
          // Set updated/new attrs — no HTML allowed (set as text only via setAttribute)
          const attrs = e.data.attrs || {};
          for (const [name, val] of Object.entries(attrs)) {
            if (name) el.setAttribute(name, val);
          }
          rebuildList();
        }
      }
      if (e.data.type === 'cms_update') {
        const el = document.querySelector('[data-cms="' + e.data.id + '"]');
        if (el) {
          el.innerHTML = e.data.text;
          rebuildList();
        }
      }
      if (e.data.type === 'cms_scroll_to') {
        const el = document.querySelector('[data-cms="' + e.data.id + '"]');
        if (el) {
          el.scrollIntoView({ behavior: 'smooth', block: 'center' });
          document.querySelectorAll('.cms-selected-highlight').forEach(e => e.classList.remove('cms-selected-highlight'));
          el.classList.add('cms-selected-highlight');
        }
      }
      if (e.data.type === 'cms_inject') {
        injectDataAttrs();
      }
    });

    const style = document.createElement('style');
    style.textContent = '.cms-hover-highlight { outline: 2px dashed rgba(124,108,252,0.7) !important; outline-offset: 2px !important; cursor: pointer !important; } .cms-selected-highlight { outline: 2px solid #7c6cfc !important; outline-offset: 2px !important; }';
    document.head.appendChild(style);
  })();
  <` + `/script>`;

  const finalHtml = html.includes('</head>')
    ? html.replace('</head>', cmsScript + '</head>')
    : cmsScript + html;

  frame.srcdoc = finalHtml;
  frame.style.display = 'block';
  loading.style.display = 'none';
}

function refreshPreview() {
  if (state.currentFile) renderPreview(state.currentHtml);
}

// ═══════════════════════════════════════════════
//  IFRAME MESSAGES
// ═══════════════════════════════════════════════
window.addEventListener('message', e => {
  if (e.data.type === 'cms_ready') {
    document.getElementById('el-count').textContent = e.data.count;
  }
  if (e.data.type === 'cms_elements') {
    state.elements = e.data.elements;
    renderElementsList(e.data.elements);
  }
  if (e.data.type === 'cms_click') {
    // Strip live CMS editor classes before showing attribute editor
    if (e.data.attrs && e.data.attrs.class) {
      e.data.attrs.class = e.data.attrs.class
        .replace(/cms-hover-highlight/g, '')
        .replace(/cms-selected-highlight/g, '')
        .trim();
      if (!e.data.attrs.class) delete e.data.attrs.class;
    }
    openEditPopup(e.data);
  }
});

// ═══════════════════════════════════════════════
//  EDIT POPUP
// ═══════════════════════════════════════════════
let popup = null;

function openEditPopup(data) {
  closeEditPopup();
  state.editingEl = data;

  // Highlight in elements list
  document.querySelectorAll('.el-item').forEach(e => e.classList.remove('active'));
  const li = document.querySelector(`.el-item[data-id="${data.id}"]`);
  if (li) { li.classList.add('active'); li.scrollIntoView({ block: 'nearest' }); }

  popup = document.createElement('div');
  popup.className = 'edit-popup';

  const tagClass = ['h1','h2','h3','h4','h5','h6'].includes(data.tag) ? 'h'
    : data.tag === 'p' ? 'p'
    : data.tag === 'a' ? 'a'
    : ['button','btn'].some(t => data.tag.includes(t)) ? 'btn'
    : data.tag === 'span' ? 'span'
    : data.tag === 'li' ? 'li' : 'p';

  popup.innerHTML = `
    <div class="edit-popup-header">
      <div style="display:flex;align-items:center;gap:8px">
        <span class="el-tag ${tagClass}">${data.tag}</span>
        <span class="edit-popup-id">${data.id}</span>
      </div>
      <div class="edit-popup-close" onclick="closeEditPopup()">×</div>
    </div>
    <div class="popup-tabs">
      <button class="popup-tab active" id="tab-content" onclick="switchPopupTab('content')">Content</button>
      <button class="popup-tab" id="tab-attrs" onclick="switchPopupTab('attrs')">Attributes</button>
    </div>
    <div class="popup-tab-panel active" id="panel-content">
      <div id="quill-editor"></div>
    </div>
    <div class="popup-tab-panel" id="panel-attrs">
      <div class="attr-list" id="attr-list"></div>
      <div class="attr-add-row">
        <button class="attr-add-btn" onclick="addAttrRow()">+ Add attribute</button>
      </div>
      <div class="attr-protected">⚠ data-cms is protected and cannot be removed.</div>
    </div>
    <div class="edit-actions">
      <button class="edit-btn cancel" onclick="closeEditPopup()">Cancel</button>
      <button class="edit-btn confirm" id="popup-apply-btn" onclick="applyEdit()">Apply ↵</button>
    </div>`;

  // Position popup near the iframe
  const wrap = document.getElementById('preview-frame-wrap');
  const wrapRect = wrap.getBoundingClientRect();
  popup.style.position = 'fixed';
  popup.style.top = (wrapRect.top + 60) + 'px';
  popup.style.left = (wrapRect.left + 20) + 'px';
  popup.style.zIndex = '9999';
  document.body.appendChild(popup);

  // ── CONTENT TAB ──────────────────────────────────────────
  const richTags = ['p','h1','h2','h3','h4','h5','h6','li','blockquote','td','th','figcaption'];
  const isRich = richTags.includes(data.tag);

  if (isRich) {
    state.quill = new Quill('#quill-editor', {
      theme: 'snow',
      modules: {
        toolbar: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline'],
          [{ color: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['clean']
        ]
      },
      placeholder: 'Edit content…'
    });
    // Quill 2: use clipboard.dangerouslyPasteHTML to set initial content
    state.quill.clipboard.dangerouslyPasteHTML(0, data.text);
    state.quill.focus();
    const len = state.quill.getLength();
    state.quill.setSelection(len, len);
  } else {
    const quillDiv = document.getElementById('quill-editor');
    quillDiv.innerHTML = `<input type="text" id="plain-input" value="${escapeHtml(data.text)}"
      style="width:100%;background:var(--bg3);border:1px solid var(--border);border-radius:var(--radius);
      padding:10px 12px;color:var(--text);font-size:13px;font-family:monospace;outline:none;
      transition:border-color var(--transition);"
      onfocus="this.style.borderColor='var(--accent)'"
      onblur="this.style.borderColor='var(--border)'"
    >`;
    const inp = document.getElementById('plain-input');
    inp.focus(); inp.select();
    inp.addEventListener('keydown', e => {
      if (e.key === 'Enter') applyEdit();
      if (e.key === 'Escape') closeEditPopup();
    });
    state.quill = null;
  }

  // ── ATTRIBUTES TAB ───────────────────────────────────────
  renderAttrList(data.attrs || {});

  makeDraggable(popup);
}

function switchPopupTab(tab) {
  ['content','attrs'].forEach(t => {
    document.getElementById('tab-' + t)?.classList.toggle('active', t === tab);
    document.getElementById('panel-' + t)?.classList.toggle('active', t === tab);
  });
  // Update Apply button label hint
  const btn = document.getElementById('popup-apply-btn');
  if (btn) btn.textContent = tab === 'attrs' ? 'Apply Attrs ↵' : 'Apply ↵';
}

// Protected attrs that must not be deleted
const PROTECTED_ATTRS = ['data-cms'];

function renderAttrList(attrs) {
  const list = document.getElementById('attr-list');
  if (!list) return;
  list.innerHTML = '';
  for (const [name, val] of Object.entries(attrs)) {
    list.appendChild(makeAttrRow(name, val, PROTECTED_ATTRS.includes(name)));
  }
}

function makeAttrRow(name, val, isProtected) {
  const row = document.createElement('div');
  row.className = 'attr-row';
  row.dataset.attrName = name;

  if (isProtected) {
    row.innerHTML = `
      <div class="attr-name" data-attr="${escapeHtml(name)}" title="${escapeHtml(name)}">${escapeHtml(name)}</div>
      <input class="attr-val" type="text" value="${escapeHtml(val)}" data-attr="${escapeHtml(name)}"
        oninput="sanitizeAttrInput(this)" placeholder="value…">
      <div class="attr-del" style="opacity:0.2;cursor:not-allowed" title="Protected">🔒</div>`;
  } else {
    row.innerHTML = `
      <div class="attr-name" data-attr="${escapeHtml(name)}" title="${escapeHtml(name)}">${escapeHtml(name)}</div>
      <input class="attr-val" type="text" value="${escapeHtml(val)}" data-attr="${escapeHtml(name)}"
        oninput="sanitizeAttrInput(this)" placeholder="value…">
      <button class="attr-del" title="Remove attribute" onclick="removeAttrRow(this)">×</button>`;
  }
  return row;
}

function addAttrRow() {
  const list = document.getElementById('attr-list');
  if (!list) return;
  const row = document.createElement('div');
  row.className = 'attr-row';
  row.dataset.attrName = '';
  row.innerHTML = `
    <input class="attr-name custom" type="text" placeholder="attr-name" maxlength="64"
      oninput="this.closest('.attr-row').dataset.attrName = this.value.replace(/[^a-zA-Z0-9:_\\-]/g,'').toLowerCase()">
    <input class="attr-val" type="text" placeholder="value…"
      oninput="sanitizeAttrInput(this)">
    <button class="attr-del" title="Remove" onclick="removeAttrRow(this)">×</button>`;
  list.appendChild(row);
  row.querySelector('.attr-name.custom').focus();
}

function removeAttrRow(btn) {
  btn.closest('.attr-row').remove();
}

// Strip any HTML from attribute values
function sanitizeAttrInput(input) {
  const raw = input.value;
  // Remove all < > to prevent HTML injection via attributes
  const clean = raw.replace(/[<>]/g, '');
  if (clean !== raw) input.value = clean;
}

function closeEditPopup() {
  if (popup) { popup.remove(); popup = null; }
  state.quill = null;
  state.editingEl = null;
}

function applyEdit() {
  if (!state.editingEl) return;

  // Detect which tab is active
  const attrsPanel = document.getElementById('panel-attrs');
  const isAttrsTab = attrsPanel && attrsPanel.classList.contains('active');

  if (isAttrsTab) {
    // ── ATTRIBUTES APPLY ─────────────────────────────────
    const rows = document.querySelectorAll('#attr-list .attr-row');
    const newAttrs = {};
    const toRemove = [];

    // Collect what's currently in the DOM on the element (to detect removed rows)
    const originalAttrs = state.editingEl.attrs || {};
    const currentNames = new Set();

    rows.forEach(row => {
      const nameEl = row.querySelector('.attr-name');
      const valEl  = row.querySelector('.attr-val');
      if (!nameEl || !valEl) return;
      // Custom (newly added) row uses an input; existing uses a div
      const name = (nameEl.tagName === 'INPUT' ? nameEl.value : nameEl.dataset.attr || row.dataset.attrName || nameEl.textContent).trim();
      const val  = valEl.value;
      if (!name) return; // skip blank-named rows
      if (PROTECTED_ATTRS.includes(name)) { currentNames.add(name); return; } // don't re-set protected
      newAttrs[name] = val;
      currentNames.add(name);
    });

    // Any attr that was in the original but isn't in currentNames → mark for removal
    for (const name of Object.keys(originalAttrs)) {
      if (!currentNames.has(name) && !PROTECTED_ATTRS.includes(name)) {
        toRemove.push(name);
      }
    }

    historyPush('attrs: ' + state.editingEl.id);
    const id = state.editingEl.id;
    const frame = document.getElementById('preview-frame');
    frame.contentWindow.postMessage({ type: 'cms_update_attrs', id, attrs: newAttrs, remove: toRemove }, '*');

    // Update the HTML in state
    state.currentHtml = updateAttrsInHtml(state.currentHtml, id, newAttrs, toRemove);
    markDirty('Attrs updated: ' + id);
    // Update cached attrs so re-open works correctly
    state.editingEl.attrs = { ...originalAttrs, ...newAttrs };
    toRemove.forEach(n => delete state.editingEl.attrs[n]);

    closeEditPopup();
    toast('Attributes updated', 'success');
  } else {
    // ── CONTENT APPLY ────────────────────────────────────
    let newText;
    if (state.quill) {
      // getSemanticHTML wraps everything in <p> tags - strip outer <p> for non-paragraph tags
      let raw = state.quill.getSemanticHTML
        ? state.quill.getSemanticHTML()
        : state.quill.root.innerHTML;
      // Remove trailing empty paragraph Quill adds
      raw = raw.replace(/<p>(<br>|<br\/>)?<\/p>\s*$/i, '').trim();
      // For heading elements, unwrap the outer <p> Quill wraps content in
      const tag = state.editingEl ? state.editingEl.tag : '';
      const isHeading = /^h[1-6]$/.test(tag);
      const isSingle = (raw.match(/<p>/g) || []).length === 1 && !raw.includes('<br>');
      if (isHeading && isSingle) {
        // Strip single wrapping <p>...</p>
        newText = raw.replace(/^<p[^>]*>([\s\S]*)<\/p>$/, '$1').trim();
      } else {
        newText = raw;
      }
    } else {
      const inp = document.getElementById('plain-input');
      newText = inp ? inp.value : '';
    }

    historyPush('edit: ' + state.editingEl.id);
    const id = state.editingEl.id;
    const frame = document.getElementById('preview-frame');
    frame.contentWindow.postMessage({ type: 'cms_update', id, text: newText }, '*');

    state.currentHtml = updateElementInHtml(state.currentHtml, id, newText);
    markDirty('Modified: ' + id);

    closeEditPopup();
    toast('Element updated', 'success');
  }
}

function updateElementInHtml(html, id, newText) {
  // Simple DOM approach via DOMParser
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, 'text/html');
  const el = doc.querySelector(`[data-cms="${id}"]`);
  if (el) el.innerHTML = newText;
  return '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
}

function updateAttrsInHtml(html, id, newAttrs, toRemove) {
  const parser = new DOMParser();
  const doc = parser.parseFromString(html, 'text/html');
  const el = doc.querySelector(`[data-cms="${id}"]`);
  if (el) {
    (toRemove || []).forEach(name => el.removeAttribute(name));
    for (const [name, val] of Object.entries(newAttrs)) {
      if (name) el.setAttribute(name, val);
    }
  }
  return '<!DOCTYPE html>\n' + doc.documentElement.outerHTML;
}

// ═══════════════════════════════════════════════
//  ELEMENTS LIST
// ═══════════════════════════════════════════════
function renderElementsList(elements) {
  const list = document.getElementById('el-list');
  if (!elements.length) {
    list.innerHTML = `<div style="padding:20px 10px;text-align:center;color:var(--text3);font-size:12px;font-family:monospace">No tagged elements found.<br>Click "Auto-Tag" to inject data-cms attributes.</div>`;
    return;
  }
  list.innerHTML = elements.map(el => {
    const tagClass = ['h1','h2','h3','h4','h5','h6'].includes(el.tag) ? 'h'
      : el.tag === 'p' ? 'p' : el.tag === 'a' ? 'a'
      : ['button'].includes(el.tag) ? 'btn'
      : el.tag === 'span' ? 'span' : el.tag === 'li' ? 'li' : 'p';
    return `<div class="el-item" data-id="${el.id}" onclick="scrollToElement('${el.id}')">
      <span class="el-tag ${tagClass}">${el.tag}</span>
      <div class="el-content">
        <div class="el-id">#${el.id}</div>
        <div class="el-text" title="${escapeHtml(el.text)}">${escapeHtml(el.text) || '<em style="opacity:0.4">empty</em>'}</div>
      </div>
    </div>`;
  }).join('');
}

function filterElements() {
  const q = document.getElementById('el-search').value.toLowerCase();
  document.querySelectorAll('.el-item').forEach(el => {
    const id = el.dataset.id || '';
    const text = el.querySelector('.el-text')?.textContent || '';
    el.style.display = (id.includes(q) || text.toLowerCase().includes(q)) ? '' : 'none';
  });
}

function scrollToElement(id) {
  document.querySelectorAll('.el-item').forEach(e => e.classList.remove('active'));
  const li = document.querySelector(`.el-item[data-id="${id}"]`);
  if (li) li.classList.add('active');
  const frame = document.getElementById('preview-frame');
  frame.contentWindow.postMessage({ type: 'cms_scroll_to', id }, '*');
  // Open edit popup
  const el = state.elements.find(e => e.id === id);
  if (el) openEditPopup(el);
}

// ═══════════════════════════════════════════════
//  DIRTY STATE HELPER
// ═══════════════════════════════════════════════
function markDirty(statusMsg) {
  state.dirty = true;
  document.getElementById('btn-save').disabled = false;
  document.getElementById('btn-discard').disabled = false;
  syncDrawerSave();
  setStatus(statusMsg, 'warn');
  updateCharCount();
}

function updateCharCount() {
  const el = document.getElementById('status-chars');
  if (!el || !state.currentHtml) return;
  const kb = (state.currentHtml.length / 1024).toFixed(1);
  el.textContent = kb + ' KB';
}

// ═══════════════════════════════════════════════
//  AUTO-TAG (inject data-cms)
// ═══════════════════════════════════════════════
document.getElementById('btn-inject').addEventListener('click', async () => {
  if (!state.currentFile) return;
  const res = await api('inject_attrs', { path: state.currentFile });
  if (!res.success) { toast('Injection failed', 'error'); return; }
  state.currentHtml = res.content;
  state.originalHtml = res.content;
  renderPreview(res.content);
  toast('data-cms attributes injected', 'success');
  setStatus('Auto-tagged: ' + state.currentFile);
});

// ═══════════════════════════════════════════════
//  SAVE
// ═══════════════════════════════════════════════
document.getElementById('btn-save').addEventListener('click', async () => {
  if (!state.currentFile || !state.dirty) return;
  const btn = document.getElementById('btn-save');
  btn.textContent = '…';
  btn.disabled = true;
  // Strip any CMS editor classes that may have leaked into the HTML
  const cleanHtml = state.currentHtml
    .replace(/\s*cms-hover-highlight/g, '')
    .replace(/\s*cms-selected-highlight/g, '')
    .replace(/\s+class=""/g, '')
    .replace(/\s+class=''/g, '');
  const res = await api('write_file', { path: state.currentFile, content: cleanHtml });
  btn.textContent = '↑ Save';
  if (!res.success) {
    btn.disabled = false;
    const errMsg = res.error || 'Unknown error';
    toast('Save failed: ' + errMsg, 'error');
    console.error('Save failed:', res);
    return;
  }
  state.dirty = false;
  state.currentHtml = cleanHtml;   // keep state in sync with what was written
  state.originalHtml = cleanHtml;
  document.getElementById('btn-discard').disabled = true;
  syncDrawerSave();
  toast('Saved successfully ✓', 'success');
  setStatus('Saved: ' + state.currentFile);
});

document.getElementById('btn-discard').addEventListener('click', () => {
  if (!state.dirty) return;
  if (!confirm('Discard all unsaved changes and reload from disk?')) return;
  historyPush('before-discard');
  state.currentHtml = state.originalHtml;
  state.dirty = false;
  state.undoStack = [];
  state.redoStack = [];
  renderPreview(state.currentHtml);
  document.getElementById('btn-save').disabled = true;
  document.getElementById('btn-discard').disabled = true;
  updateHistoryUI();
  updateCharCount();
  setStatus('Discarded — reverted to saved version');
  toast('Changes discarded', 'info');
});

// ═══════════════════════════════════════════════
//  MODULES
// ═══════════════════════════════════════════════
function showModule(id) {
  state.currentModule = id;
  // Close settings screen if open
  closeSettings();
  // All panel IDs
  const allPanels = ['editor','modules','license','media','snippets','diff',
    'menu','booking','products','blog','events','team','testimonials','faq','jobs',
    'gallery','pricing','forms','inbox','newsletter','seo','analytics','redirects',
    'export','backup','accessibility'];
  allPanels.forEach(m => {
    const panel = document.getElementById('panel-' + m);
    if (!panel) return;
    const isActive = m === id;
    if (panel.classList.contains('module-panel')) {
      panel.style.display = isActive ? 'flex' : 'none';
    } else if (m === 'editor') {
      panel.style.display = isActive ? 'flex' : 'none';
    } else {
      panel.style.display = isActive ? 'block' : 'none';
    }
  });
  // Update sidebar nav active state
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
  const ni = document.getElementById('mod-' + id);
  if (ni) ni.classList.add('active');
  if (id === 'license') verifyLicense();
  if (id === 'modules') renderModulesGrid();
}

function openSettings() {
  state._prevModule = state.currentModule;
  document.getElementById('settings-screen').classList.add('active');
  document.body.style.overflow = 'hidden';
  // Update sidebar active state
  document.querySelectorAll('.nav-item').forEach(el => el.classList.remove('active'));
}

function closeSettings() {
  document.getElementById('settings-screen').classList.remove('active');
  document.body.style.overflow = '';
  // Restore previous module active state
  if (state._prevModule) {
    const ni = document.getElementById('mod-' + state._prevModule);
    if (ni) ni.classList.add('active');
  }
}

function showSettingsPage(id) {
  document.querySelectorAll('.settings-page').forEach(p => p.classList.remove('active'));
  document.querySelectorAll('.settings-nav-item').forEach(n => n.classList.remove('active'));
  const page = document.getElementById('spage-' + id);
  if (page) page.classList.add('active');
  const nav = document.getElementById('snav-' + id);
  if (nav) nav.classList.add('active');
}

// ═══════════════════════════════════════════════
//  SETTINGS
// ═══════════════════════════════════════════════
async function saveSettings() {
  const curPass  = document.getElementById('set-cur-pass').value;
  const newPass  = document.getElementById('set-new-pass').value;
  const newPass2 = document.getElementById('set-new-pass2').value;
  const display  = document.getElementById('set-display-name').value.trim();
  const username = document.getElementById('set-username').value.trim();

  if (!curPass) { showSettingsMsg('Current password is required.', 'err'); return; }
  if (newPass && newPass !== newPass2) { showSettingsMsg('New passwords do not match.', 'err'); return; }
  if (newPass && newPass === curPass) { showSettingsMsg('New password must differ from current.', 'err'); return; }

  const btn = document.querySelector('.settings-save-btn');
  const origText = btn.textContent;
  btn.textContent = 'Saving…'; btn.disabled = true;

  // Use the bare script path — no query params, no hash
  const scriptUrl = window.location.pathname;

  const body = new URLSearchParams({
    cms_save_settings: '1',
    current_password:  curPass,
    new_password:      newPass,
    display_name:      display,
    new_username:      username,
  });

  try {
    const r = await fetch(scriptUrl, {
      method: 'POST',
      headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
      body: body.toString()
    });

    const text = await r.text();
    let data;
    try {
      data = JSON.parse(text);
    } catch(e) {
      console.error('Settings response was not JSON:', text.substring(0, 300));
      showSettingsMsg('Server error — check console for details.', 'err');
      return;
    }

    if (data.success) {
      showSettingsMsg('✓ Settings saved.' + (newPass ? ' Use your new password next login.' : ''), 'ok');
      // Update status bar display name from server response
      const name = data.display_name || display;
      const su = document.getElementById('status-user');
      if (su) su.textContent = '@ ' + name;
      // Clear password fields only
      document.getElementById('set-cur-pass').value = '';
      document.getElementById('set-new-pass').value = '';
      document.getElementById('set-new-pass2').value = '';
    } else {
      showSettingsMsg(data.error || 'Save failed.', 'err');
    }
  } catch(e) {
    showSettingsMsg('Network error: ' + e.message, 'err');
  } finally {
    btn.textContent = origText; btn.disabled = false;
  }
}

function showSettingsMsg(msg, type) {
  const el = document.getElementById('settings-msg');
  el.textContent = msg;
  el.className = 'settings-msg ' + type;
  el.style.display = 'block';
  setTimeout(() => { el.style.display = 'none'; }, 4000);
}

// ═══════════════════════════════════════════════
//  FEEDBACK / BUG REPORT
// ═══════════════════════════════════════════════
const FEEDBACK_EMAIL = 'bug@siterefresh.eu';

function openFeedback(type) {
  toggleFeedbackMenu(false);
  const subjects = {
    bug:     '[SR Edit Alpha] Bug Report',
    feature: '[SR Edit Alpha] Feature Request',
    other:   '[SR Edit Alpha] Feedback',
  };
  const bodies = {
    bug:     'Describe the bug:\n\n\nSteps to reproduce:\n1. \n2. \n\nExpected:\n\nActual:\n\nSR Edit version: <?= CMS_VERSION ?>',
    feature: 'Feature request:\n\n\nWhy would this be useful?\n\n\nSR Edit version: <?= CMS_VERSION ?>',
    other:   'Your feedback:\n\n\nSR Edit version: <?= CMS_VERSION ?>',
  };
  const subject = encodeURIComponent(subjects[type] || subjects.other);
  const body    = encodeURIComponent(bodies[type]   || bodies.other);
  window.location.href = `mailto:${FEEDBACK_EMAIL}?subject=${subject}&body=${body}`;
}

let feedbackMenuOpen = false;
function toggleFeedbackMenu(forceState) {
  const menu = document.getElementById('feedback-menu');
  feedbackMenuOpen = forceState !== undefined ? forceState : !feedbackMenuOpen;
  menu.style.display = feedbackMenuOpen ? 'block' : 'none';
}
// Close feedback menu on outside click
document.addEventListener('click', e => {
  if (feedbackMenuOpen && !e.target.closest('#feedback-toggle') && !e.target.closest('#feedback-menu')) {
    toggleFeedbackMenu(false);
  }
});

function renderModulesGrid() {
  const grid = document.getElementById('modules-grid');
  if (!grid) return;
  const cats = [...new Set(state.modules.map(m => m.cat))];
  let html = '';
  cats.forEach(cat => {
    html += `<div style="grid-column:1/-1;padding:4px 0 2px"><div style="font-size:10px;font-weight:700;letter-spacing:1.5px;text-transform:uppercase;color:var(--text3);font-family:monospace;border-bottom:1px solid var(--border);padding-bottom:8px;margin-bottom:4px">${cat}</div></div>`;
    state.modules.filter(m => m.cat === cat).forEach(m => {
      const badge = m.status === 'live'
        ? '<span class="nav-item-badge badge-live">LIVE</span>'
        : '<span class="nav-item-badge badge-soon">SOON</span>';
      const tooltip = m.status === 'soon' ? 'title="Preview coming soon UI"' : '';
      html += `<div class="mod-card" onclick="showModule('${m.id}')" ${tooltip}>
        <div class="mod-card-top">
          <div class="mod-card-icon">${m.icon}</div>
          <div class="mod-card-name">${m.name}</div>
          ${badge}
        </div>
        <div class="mod-card-desc">${m.desc}</div>
        <div class="mod-card-footer">
          <span class="mod-card-category">${m.cat}</span>
          ${m.status === 'soon' ? '<span style="font-size:10px;color:var(--text3);font-family:&quot;DM Mono&quot;,monospace">Click to preview UI →</span>' : ''}
        </div>
      </div>`;
    });
  });
  grid.innerHTML = html;
}

// ═══════════════════════════════════════════════
//  LICENSE
// ═══════════════════════════════════════════════
async function verifyLicense() {
  const statusEl = document.getElementById('license-status');
  const textEl   = document.getElementById('license-status-text');
  const keyEl    = document.getElementById('license-key-display');
  if (!statusEl) return;

  const res = await api('check_license');
  keyEl.textContent = res.key || 'No license key entered';

  if (res.valid) {
    statusEl.style.borderColor = 'rgba(61,255,160,0.3)';
    statusEl.querySelector('.license-icon').textContent = '✅';
    statusEl.querySelector('[style]').innerHTML = `<div style="font-weight:700;margin-bottom:2px;color:var(--green)">License Active</div>`;
    textEl.textContent = 'Next verification: ' + (res.next_check || 'within 30 days');
  } else {
    statusEl.style.borderColor = 'rgba(255,95,126,0.3)';
    statusEl.querySelector('.license-icon').textContent = '🔒';
    statusEl.querySelector('[style]').innerHTML = `<div style="font-weight:700;margin-bottom:2px;color:var(--red)">No Valid License</div>`;
    textEl.textContent = res.message || 'Enter a license key (SREDIT-...) to activate SR Edit';
  }
}

async function promptLicenseKey() {
  const key = prompt('Enter your SR Edit license key:');
  if (!key) return;
  const res = await api('activate_license', { key });
  if (res.success) { toast('License activated!', 'success'); verifyLicense(); }
  else toast('Activation failed: ' + (res.message || 'Invalid key'), 'error');
}

// ═══════════════════════════════════════════════
//  UTILITIES
// ═══════════════════════════════════════════════
function escapeHtml(str) {
  return String(str || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

function setStatus(msg, type = 'ok') {
  document.getElementById('status-text').textContent = msg;
  const dot = document.getElementById('status-dot');
  dot.className = 'status-dot' + (type === 'warn' ? ' warn' : type === 'err' ? ' err' : '');
}

function toast(msg, type = 'info') {
  const c = document.getElementById('toast-container');
  const t = document.createElement('div');
  t.className = `toast ${type}`;
  const icons = { success: '✓', error: '✗', info: 'i' };
  t.innerHTML = `<span style="font-weight:700;color:var(--${type==='success'?'green':type==='error'?'red':'accent2'})">${icons[type]||'i'}</span>${escapeHtml(msg)}`;
  c.appendChild(t);
  setTimeout(() => { t.style.opacity = '0'; t.style.transform = 'translateX(20px)'; t.style.transition = '0.3s'; setTimeout(() => t.remove(), 300); }, 2800);
}

function confirmLogout() {
  if (confirm('Sign out of SR Edit?')) window.location = '?logout=1';
}

function logoClick() {
  const wrap = document.getElementById('logo-wrap');
  if (!wrap) return;
  wrap.classList.remove('pop');
  void wrap.offsetWidth; // force reflow to restart animation
  wrap.classList.add('pop');
  wrap.addEventListener('animationend', () => wrap.classList.remove('pop'), { once: true });
}

function makeDraggable(el) {
  let ox, oy, dragging = false;
  el.querySelector('.edit-popup-header').addEventListener('mousedown', e => {
    dragging = true; ox = e.clientX - el.offsetLeft; oy = e.clientY - el.offsetTop;
    document.addEventListener('mousemove', onMove);
    document.addEventListener('mouseup', () => { dragging = false; document.removeEventListener('mousemove', onMove); }, { once: true });
  });
  function onMove(e) {
    if (!dragging) return;
    el.style.left = (e.clientX - ox) + 'px';
    el.style.top  = (e.clientY - oy) + 'px';
  }
}
</script>

<?php endif; ?>
<script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
</body>
</html>
