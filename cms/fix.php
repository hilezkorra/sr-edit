<?php
// SR Edit — Emergency Session & Cache Reset
// Usage: visit https://yourdomain.de/cms/fix.php
// Delete this file after use.

session_start();
session_destroy();

// Clear all SR Edit localStorage keys via JS
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<title>SR Edit Fix</title>
<style>
body { font-family: monospace; background: #0a0a0f; color: #e8e8f0; 
       display: flex; align-items: center; justify-content: center; 
       height: 100vh; margin: 0; flex-direction: column; gap: 16px; }
.box { background: #111118; border: 1px solid #2a2a38; border-radius: 12px;
       padding: 32px 40px; max-width: 460px; text-align: center; }
h2 { color: #3dffa0; margin-bottom: 8px; }
p { color: #9090a8; font-size: 13px; line-height: 1.6; margin-bottom: 16px; }
a { display: inline-block; margin-top: 8px; padding: 10px 24px;
    background: #7c6cfc; color: #fff; border-radius: 8px; 
    text-decoration: none; font-weight: 700; }
.done { color: #3dffa0; font-size: 13px; }
</style>
</head>
<body>
<div class="box">
  <h2>✓ SR Edit Reset</h2>
  <p>PHP session destroyed.<br>Clearing localStorage keys now…</p>
  <p class="done" id="status">Working…</p>
  <a href="siterefresh-admin.php">→ Go to SR Edit</a>
</div>
<script>
const keys = [
  'sr-left-panel', 'sr-right-panel', 'sr-edit-theme',
  'sr-edit-hint-dismissed', 'sr-sidebar-fix-v2', 'sr-sidebar-fix-v1'
];
keys.forEach(k => localStorage.removeItem(k));
document.getElementById('status').textContent = 
  '✓ Cleared ' + keys.length + ' localStorage keys. Session destroyed.';
</script>
</body>
</html>
