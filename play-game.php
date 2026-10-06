<?php
/**
 * Serves one admin-uploaded HTML game (see includes/uploaded-games-functions.php)
 * inside a browser sandbox. This is the ONLY way those files are reachable:
 * uploads/games/ denies direct access.
 *
 * The Content-Security-Policy "sandbox" directive (no allow-same-origin)
 * makes the browser run the page as an anonymous "null" origin even when
 * it's opened directly in a tab, so the game's scripts can't read the
 * visitor's teachluma.com cookies, call the site's logged-in pages as them,
 * reach into the page embedding it, or navigate the top window. game.php
 * also puts a sandbox attribute on its <iframe>, belt and braces.
 *
 * Drafts are viewable by admins only (the "Preview" link in Admin > Games).
 */
require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/games-functions.php';

$slug = trim((string)($_GET['slug'] ?? ''));
$row = $slug !== '' ? get_uploaded_game_row_by_slug($slug) : null;

if (!$row || (!$row['is_published'] && !is_admin())) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Game not found.';
    exit;
}

$path = UPLOADED_GAMES_DIR . '/' . basename($row['file_name']);
if (!is_file($path)) {
    http_response_code(404);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'Game file is missing.';
    exit;
}

$html = (string)file_get_contents($path);

// Sandboxed pages have no real localStorage/sessionStorage: touching them
// throws, which would crash a game that saves high scores. Swap in a
// working in-memory version (scores last until the page is closed).
$shim = '<script>(function(){function m(){var d={};return{getItem:function(k){k=String(k);return Object.prototype.hasOwnProperty.call(d,k)?d[k]:null},'
    . 'setItem:function(k,v){d[String(k)]=String(v)},removeItem:function(k){delete d[String(k)]},clear:function(){d={}},'
    . 'key:function(i){return Object.keys(d)[i]||null},get length(){return Object.keys(d).length}}}'
    . '["localStorage","sessionStorage"].forEach(function(n){try{var s=window[n];s.setItem("__tl","1");s.removeItem("__tl")}'
    . 'catch(e){try{Object.defineProperty(window,n,{value:m(),configurable:true})}catch(e2){}}});'
    . 'try{document.cookie}catch(e){try{Object.defineProperty(document,"cookie",{get:function(){return""},set:function(){},configurable:true})}catch(e2){}}'
    . '})();</script>';

// Insert it before the game's own scripts, but after any <meta charset> so
// that tag stays inside the first 1024 bytes where browsers look for it.
if (preg_match('/<meta\s[^>]*charset[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
    $at = $m[0][1] + strlen($m[0][0]);
} elseif (preg_match('/<head\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
    $at = $m[0][1] + strlen($m[0][0]);
} elseif (preg_match('/<html\b[^>]*>/i', $html, $m, PREG_OFFSET_CAPTURE)) {
    $at = $m[0][1] + strlen($m[0][0]);
} else {
    $at = 0;
}
$html = substr($html, 0, $at) . $shim . substr($html, $at);

// Only claim UTF-8 when the file doesn't declare its own charset and is valid UTF-8.
$contentType = 'text/html';
if (!preg_match('/<meta\s[^>]*charset/i', $html) && mb_check_encoding($html, 'UTF-8')) {
    $contentType .= '; charset=utf-8';
}

header('Content-Type: ' . $contentType);
header("Content-Security-Policy: sandbox allow-scripts allow-forms allow-modals allow-popups allow-pointer-lock allow-downloads; frame-ancestors 'self'");
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
header('X-Robots-Tag: noindex'); // the game.php landing page is the one to index
header('Cache-Control: ' . ($row['is_published'] ? 'public, max-age=300' : 'private, no-store'));
header('Content-Length: ' . strlen($html));

echo $html;
