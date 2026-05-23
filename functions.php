<?php
function uid(): ?int  { 
    return isset($_SESSION['uid']) ? (int)$_SESSION['uid'] : null; 
}
function loggedIn(): bool { return uid() !== null; }
function e(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }

function redirect(string $url): never {
    header('Location: ' . $url);
    exit;
}

function flash(string $msg, string $type = 'error'): void {
    $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
}

function getFlash(): ?array {
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function fmtDate(string $dt): string {
    $ts = strtotime($dt);
    return date('d M Y', $ts === false ? time() : $ts);
}