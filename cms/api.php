<?php
/**
 * SR Edit API
 * Handles all backend operations: file listing, reading, writing,
 * data-cms injection, and license verification.
 */

session_start();
header('Content-Type: application/json');

// ─── AUTH CHECK ─────────────────────────────────────────────────────────────
if (!isset($_SESSION['cms_auth']) || $_SESSION['cms_auth'] !== true) {
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit;
}

// ─── CONFIG ─────────────────────────────────────────────────────────────────
// Use dirname() instead of realpath() to avoid following symlinks
define('ROOT',       dirname(__DIR__) . '/'); // Scans parent directory for HTML
define('CMS_DIR',    __DIR__ . '/');
define('DATA_DIR',   __DIR__ . '/cms_data/');
define('LICENSE_FILE', DATA_DIR . 'license.json');
define('BACKUP_DIR', DATA_DIR . 'backups/');

if (!is_dir(DATA_DIR))   mkdir(DATA_DIR, 0755, true);
if (!is_dir(BACKUP_DIR)) mkdir(BACKUP_DIR, 0755, true);

// ─── TEXT TAGS ───────────────────────────────────────────────────────────────
const TEXT_TAGS = ['h1','h2','h3','h4','h5','h6','p','span','a','li','td','th',
                   'label','button','strong','em','blockquote','figcaption',
                   'cite','small','mark','caption'];

// ─── ROUTER ──────────────────────────────────────────────────────────────────
$input  = json_decode(file_get_contents('php://input'), true) ?? [];
$action = $input['action'] ?? '';


// ─── CSRF CHECK ──────────────────────────────────────────────────────────────
// For state-changing actions, verify CSRF token matches session token
$csrf_token = $_SESSION['cms_csrf'] ?? '';
$sent_token  = $input['_csrf'] ?? '';
$safe_actions = ['list_files', 'read_file', 'check_license']; // read-only, no CSRF needed
if (!in_array($action, $safe_actions) && !hash_equals($csrf_token, $sent_token)) {
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

switch ($action) {
    case 'list_files':      echo json_encode(cms_listFiles());      break;
    case 'read_file':       echo json_encode(cms_readFile($input)); break;
    case 'write_file':      echo json_encode(cms_writeFile($input)); break;
    case 'inject_attrs':    echo json_encode(injectAttrs($input)); break;
    case 'check_license':   echo json_encode(checkLicense());   break;
    case 'activate_license':echo json_encode(activateLicense($input)); break;
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
}

// ─── FILE LISTING ────────────────────────────────────────────────────────────
function cms_listFiles(): array {
    $root   = ROOT;
    $cmsDir = CMS_DIR; // already an absolute path, no realpath needed
    $tree   = scanDirectory($root, $root, rtrim($cmsDir, '/'));
    return ['success' => true, 'tree' => $tree];
}

function scanDirectory(string $dir, string $root, string $exclude, int $depth = 0): array {
    if ($depth > 6) return [];
    $nodes = [];
    $items = @scandir($dir);
    if (!$items) return [];

    // Folders first
    foreach ($items as $item) {
        if ($item[0] === '.') continue;
        $full = $dir . $item;
        if (is_dir($full)) {
            // Skip CMS's own directory and common non-content dirs
            if (rtrim($full, '/') === $exclude) continue;
            $skip = ['node_modules', 'vendor', '.git', '.svn', 'cms_data', '__pycache__'];
            if (in_array($item, $skip)) continue;
            $children = scanDirectory($full . '/', $root, $exclude, $depth + 1);
            if (!empty($children)) {
                $nodes[] = [
                    'type'     => 'folder',
                    'name'     => $item,
                    'id'       => md5($full),
                    'children' => $children
                ];
            }
        }
    }

    // Then files
    foreach ($items as $item) {
        if ($item[0] === '.') continue;
        $full = $dir . $item;
        if (is_file($full) && strtolower(pathinfo($item, PATHINFO_EXTENSION)) === 'html') {
            $rel = ltrim(str_replace($root, '', $full), '/');
            $nodes[] = [
                'type' => 'file',
                'name' => $item,
                'path' => $rel,
            ];
        }
    }

    return $nodes;
}

// ─── READ FILE ───────────────────────────────────────────────────────────────
function cms_readFile(array $input): array {
    $path = sanitizePath($input['path'] ?? '');
    if (!$path) return ['success' => false, 'error' => 'Invalid path'];

    $full = ROOT . $path;
    if (!file_exists($full)) return ['success' => false, 'error' => 'File not found'];

    return ['success' => true, 'content' => file_get_contents($full), 'path' => $path];
}

// ─── WRITE FILE ──────────────────────────────────────────────────────────────
function cms_writeFile(array $input): array {
    $path    = sanitizePath($input['path'] ?? '');
    $content = $input['content'] ?? '';
    if (!$path) return ['success' => false, 'error' => 'Invalid path'];

    // Security: only allow writing .html files
    if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'html') {
        return ['success' => false, 'error' => 'Only .html files may be written'];
    }

    $full = ROOT . $path;
    if (!file_exists($full)) return ['success' => false, 'error' => 'File not found'];

    // Pre-check: is the file writable?
    if (!is_writable($full)) {
        return ['success' => false, 'error' => 'File is not writable: ' . $path . ' (chmod 644 or 666 required)'];
    }

    // Backup before saving (non-fatal — skip silently if backup dir isn't writable)
    if (is_dir(BACKUP_DIR) && is_writable(BACKUP_DIR)) {
        $backupName = BACKUP_DIR . str_replace('/', '_', $path) . '_' . date('Ymd_His') . '.bak';
        @copy($full, $backupName);
        pruneBackups($path);
    }

    if (file_put_contents($full, $content) === false) {
        return ['success' => false, 'error' => 'Write failed for: ' . $path . '. Check file permissions (need 644/666).'];
    }
    return ['success' => true, 'path' => $path];
}

function pruneBackups(string $path): void {
    $prefix  = BACKUP_DIR . str_replace('/', '_', $path) . '_';
    $backups = glob($prefix . '*.bak');
    if ($backups && count($backups) > 10) {
        sort($backups);
        $toDelete = array_slice($backups, 0, count($backups) - 10);
        foreach ($toDelete as $f) unlink($f);
    }
}

// ─── INJECT DATA-CMS ATTRIBUTES ──────────────────────────────────────────────
function injectAttrs(array $input): array {
    $path = sanitizePath($input['path'] ?? '');
    if (!$path) return ['success' => false, 'error' => 'Invalid path'];

    $full = ROOT . $path;
    if (!file_exists($full)) return ['success' => false, 'error' => 'File not found'];

    $html = file_get_contents($full);
    $result = injectDataCmsAttrs($html);

    return ['success' => true, 'content' => $result, 'path' => $path];
}

function injectDataCmsAttrs(string $html): string {
    // Use DOMDocument for robust injection
    libxml_use_internal_errors(true);
    $doc = new DOMDocument('1.0', 'UTF-8');

    // Preserve encoding
    $meta  = '<meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    $loaded = $doc->loadHTML($meta . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

    if (!$loaded) {
        // Fallback: regex-based injection for simple cases
        return injectDataCmsAttrsRegex($html);
    }

    $counter = [];
    $xpath   = new DOMXPath($doc);

    foreach (TEXT_TAGS as $tag) {
        $nodes = $xpath->query('//' . $tag);
        foreach ($nodes as $node) {
            if (!$node->getAttribute('data-cms')) {
                $counter[$tag] = ($counter[$tag] ?? 0) + 1;
                $node->setAttribute('data-cms', $tag . '-' . $counter[$tag]);
            }
        }
    }

    $output = $doc->saveHTML();
    // Remove the injected meta
    $output = str_replace($meta, '', $output);
    // DOMDocument wraps output in <html><body> — strip if input wasn't a full document
    if (stripos($html, '<html') === false) {
        $output = preg_replace('/^<!DOCTYPE[^>]*>\s*/i', '', $output);
        $output = preg_replace('/<html[^>]*>\s*/i', '', $output);
        $output = preg_replace('/<\/html>\s*$/i', '', $output);
        $output = preg_replace('/<body[^>]*>\s*/i', '', $output);
        $output = preg_replace('/<\/body>\s*/i', '', $output);
        $output = preg_replace('/<head[^>]*>.*?<\/head>\s*/is', '', $output);
    }
    libxml_clear_errors();
    return trim($output);
}

function injectDataCmsAttrsRegex(string $html): string {
    $tags    = implode('|', TEXT_TAGS);
    $counter = [];

    return preg_replace_callback(
        '/<(' . $tags . ')(\s[^>]*)?>/i',
        function ($m) use (&$counter) {
            $tag   = strtolower($m[1]);
            $attrs = $m[2] ?? '';
            if (strpos($attrs, 'data-cms') !== false) return $m[0];
            $counter[$tag] = ($counter[$tag] ?? 0) + 1;
            return '<' . $tag . $attrs . ' data-cms="' . $tag . '-' . $counter[$tag] . '">';
        },
        $html
    );
}

// ─── LICENSE ─────────────────────────────────────────────────────────────────
function checkLicense(): array {
    if (!file_exists(LICENSE_FILE)) {
        return ['valid' => false, 'key' => null, 'message' => 'No license key entered'];
    }

    $data = json_decode(file_get_contents(LICENSE_FILE), true);
    if (!$data || empty($data['key'])) {
        return ['valid' => false, 'key' => null, 'message' => 'No license key configured'];
    }

    $key        = $data['key'];
    $lastCheck  = $data['last_check'] ?? 0;
    $verified   = $data['verified']   ?? false;
    $now        = time();
    $oneMonth   = 30 * 24 * 3600;

    // Re-verify if more than 30 days since last check
    if ($verified && ($now - $lastCheck) < $oneMonth) {
        $nextCheck = date('Y-m-d', $lastCheck + $oneMonth);
        return ['valid' => true, 'key' => maskKey($key), 'next_check' => $nextCheck];
    }

    // Attempt online verification
    $result = verifyKeyOnline($key);
    $data['last_check'] = $now;
    $data['verified']   = $result['valid'];
    file_put_contents(LICENSE_FILE, json_encode($data, JSON_PRETTY_PRINT));

    if ($result['valid']) {
        return ['valid' => true, 'key' => maskKey($key), 'next_check' => date('Y-m-d', $now + $oneMonth)];
    }
    return ['valid' => false, 'key' => maskKey($key), 'message' => $result['message'] ?? 'Verification failed'];
}

function activateLicense(array $input): array {
    $key = trim($input['key'] ?? '');
    if (strlen($key) < 8) return ['success' => false, 'message' => 'Key too short'];

    $result = verifyKeyOnline($key);

    $data = [
        'key'        => $key,
        'verified'   => $result['valid'],
        'last_check' => time(),
        'activated'  => date('Y-m-d H:i:s')
    ];
    file_put_contents(LICENSE_FILE, json_encode($data, JSON_PRETTY_PRINT));

    if ($result['valid']) return ['success' => true];
    return ['success' => false, 'message' => $result['message'] ?? 'Invalid license key'];
}

/**
 * Online license verification stub.
 * Replace the URL and logic with your actual licensing server.
 * For now, accepts any key starting with "SREDIT-" as valid (demo mode).
 */
function verifyKeyOnline(string $key): array {
    // ── DEMO MODE ──────────────────────────────────────────────────────────
    // In production, replace this with an actual HTTP call to your license server:
    //
    // $ch = curl_init('https://license.yourserver.com/verify');
    // curl_setopt($ch, CURLOPT_POST, 1);
    // curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['key' => $key, 'product' => 'sr-edit']));
    // curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    // $response = curl_exec($ch);
    // curl_close($ch);
    // $data = json_decode($response, true);
    // return ['valid' => $data['valid'] ?? false, 'message' => $data['message'] ?? ''];
    // ──────────────────────────────────────────────────────────────────────

    // Demo: accept keys prefixed SREDIT- as valid
    if (strpos(strtoupper($key), 'SREDIT-') === 0 && strlen($key) >= 14) {
        return ['valid' => true];
    }
    return ['valid' => false, 'message' => 'Invalid license key. Keys must start with SREDIT-'];
}

// ─── HELPERS ─────────────────────────────────────────────────────────────────
function sanitizePath(string $path): string {
    // Prevent directory traversal
    $path = str_replace('\\', '/', $path);
    $path = preg_replace('/\.\.+/', '', $path);
    $path = ltrim($path, '/');
    return $path;
}

function maskKey(string $key): string {
    $len = strlen($key);
    if ($len <= 8) return str_repeat('*', $len);
    return substr($key, 0, 6) . str_repeat('*', max(0, $len - 10)) . substr($key, -4);
}
