<?php
/*
 * 100 Hearts — configuration.
 * Change the names and codes below, then give each person their code.
 * Codes are case-insensitive. Changing a code logs that person out.
 */
return [
    'title' => '100 Hearts',

    // Exactly two players. The keys (p1, p2) are internal ids — keep them short, letters/numbers only.
    'players' => [
        'p1' => ['name' => 'Marand',     'code' => 'OpenHeart@dolce', 'color' => '#7fb4ff'],
        'p2' => ['name' => 'Dolcee', 'code' => 'openheart@marand',   'color' => '#ffa3d1'],
    ],

    // Any long random string. Used to sign the login cookie.
    'secret' => 'replace-this-with-a-long-random-string',

    // Where the SQLite database lives. Must be writable by PHP.
    // Best: a folder OUTSIDE your web root, e.g. '/var/lib/100hearts'.
    'data_dir' => __DIR__ . '/data',

    // Seconds without a heartbeat before someone is shown as offline.
    'online_window' => 8,
];
