<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

header('Cache-Control: no-store');
header('X-Frame-Options: SAMEORIGIN');
header('Referrer-Policy: same-origin');

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['logout'])) {
        logout();
        header('Location: ./');
        exit;
    }
    if (login_with_code((string) ($_POST['code'] ?? ''))) {
        header('Location: ./');
        exit;
    }
    usleep(900000); // slow down guessing
    $error = "That code doesn't open any heart 💔";
}

$me = current_player();
if (!$me) {
    require __DIR__ . '/inc/view-login.php';
    exit;
}

$boot = [
    'me'      => $me,
    'title'   => cfg('title'),
    'players' => array_map(fn($p) => [
        'name'    => $p['name'],
        'color'   => $p['color'] ?? '#ff8fab',
        'initial' => first_letter($p['name']),
    ], players()),
];

require __DIR__ . '/inc/view-app.php';
