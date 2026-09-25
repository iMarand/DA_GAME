<?php
declare(strict_types=1);

$CONFIG = require __DIR__ . '/../config.php';

const AUTH_COOKIE = 'hearts_auth';

class GameError extends RuntimeException {}

function cfg(string $key)
{
    global $CONFIG;
    return $CONFIG[$key] ?? null;
}

function players(): array
{
    return cfg('players');
}

function other_player(string $pid): string
{
    foreach (array_keys(players()) as $k) {
        if ($k !== $pid) return $k;
    }
    return $pid;
}

function player_name(?string $pid): string
{
    return $pid !== null && isset(players()[$pid]) ? players()[$pid]['name'] : 'Someone';
}

function first_letter(string $s): string
{
    return preg_match('/\p{L}|\p{N}/u', $s, $m) ? strtoupper($m[0]) : '♥';
}

/* ---------- questions ---------- */

/** The built-in questions from questions.php, normalised. */
function builtin_questions(): array
{
    static $all = null;
    if ($all === null) {
        $all = array_map(fn($q) => [
            'type'    => ($q['type'] ?? '') === 'choice' ? 'choice' : 'open',
            'q'       => (string) $q['q'],
            'options' => array_values($q['options'] ?? []),
            'author'  => null,
        ], array_values(require __DIR__ . '/../questions.php'));
    }
    return $all;
}

/**
 * The questions behind the current game's hearts (heart #n = entry n-1).
 * Each game stores its own shuffled deck; the classic game has none and uses questions.php.
 */
function questions(): array
{
    static $game = null, $deck = null;
    $s = row('SELECT game, deck FROM state WHERE id = 1');
    if ($deck === null || $game !== (int) $s['game']) {
        $game = (int) $s['game'];
        $deck = $s['deck'] ? json_decode($s['deck'], true) : builtin_questions();
    }
    return $deck;
}

/** Questions the two of you wrote in the Questions tab. */
function custom_questions(): array
{
    return array_map(fn($r) => [
        'id'      => (int) $r['id'],
        'author'  => $r['author'],
        'type'    => $r['type'],
        'q'       => $r['text'],
        'options' => json_decode($r['options'], true) ?: [],
        'at'      => (int) $r['created_at'],
    ], q('SELECT * FROM custom_questions ORDER BY id')->fetchAll());
}

function bank_version(): string
{
    $r = row('SELECT COUNT(*) AS n, COALESCE(MAX(id), 0) AS m FROM custom_questions');
    return $r['n'] . ':' . $r['m'];
}

function question(int $id): ?array
{
    return questions()[$id - 1] ?? null;
}

/** Multiple-choice questions get one extra "Custom" option after the listed ones. */
function custom_index(array $publicQuestion): int
{
    return count($publicQuestion['options']);
}

function public_question(int $id): array
{
    $q = question($id);
    return [
        'id'      => $id,
        'type'    => $q['type'] === 'choice' ? 'choice' : 'open',
        'text'    => $q['q'],
        'options' => array_values($q['options'] ?? []),
        'author'  => $q['author'] ?? null,
    ];
}

/* ---------- auth (signed cookie, no accounts) ---------- */

function is_https(): bool
{
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https';
}

function auth_token(string $pid): string
{
    $p = players()[$pid];
    return $pid . '.' . hash_hmac('sha256', $pid . '|' . strtolower(trim($p['code'])), (string) cfg('secret'));
}

function set_auth_cookie(string $value, int $expires): void
{
    setcookie(AUTH_COOKIE, $value, [
        'expires'  => $expires,
        'path'     => '/',
        'secure'   => is_https(),
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

function current_player(): ?string
{
    $c = $_COOKIE[AUTH_COOKIE] ?? '';
    if (!is_string($c) || !str_contains($c, '.')) return null;
    $pid = explode('.', $c, 2)[0];
    if (!isset(players()[$pid])) return null;
    return hash_equals(auth_token($pid), $c) ? $pid : null;
}

function login_with_code(string $code): ?string
{
    $code = strtolower(trim($code));
    if ($code === '') return null;
    foreach (players() as $pid => $p) {
        if (hash_equals(strtolower(trim($p['code'])), $code)) {
            set_auth_cookie(auth_token($pid), time() + 60 * 60 * 24 * 365);
            return $pid;
        }
    }
    return null;
}

function logout(): void
{
    set_auth_cookie('', time() - 3600);
}

/* ---------- database ---------- */

function db(): PDO
{
    static $pdo = null;
    if ($pdo) return $pdo;

    $dir = cfg('data_dir');
    if (!is_dir($dir) && !mkdir($dir, 0775, true) && !is_dir($dir)) {
        throw new RuntimeException("Cannot create data dir: $dir");
    }
    $pdo = new PDO('sqlite:' . $dir . '/game.sqlite', null, null, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA busy_timeout = 5000');
    $pdo->exec('PRAGMA journal_mode = WAL');
    $pdo->exec(<<<SQL
        CREATE TABLE IF NOT EXISTS state (
            id         INTEGER PRIMARY KEY CHECK (id = 1),
            game       INTEGER NOT NULL DEFAULT 1,
            status     TEXT    NOT NULL DEFAULT 'lobby',
            turn       TEXT,
            phase      TEXT,
            question   INTEGER,
            choice     INTEGER,
            roll       TEXT,
            version    INTEGER NOT NULL DEFAULT 1,
            updated_at INTEGER
        );
        INSERT OR IGNORE INTO state (id) VALUES (1);
        CREATE TABLE IF NOT EXISTS answers (
            game       INTEGER NOT NULL,
            question   INTEGER NOT NULL,
            player     TEXT    NOT NULL,
            choice     INTEGER,
            custom     TEXT,
            created_at INTEGER NOT NULL,
            PRIMARY KEY (game, question)
        );
        CREATE TABLE IF NOT EXISTS messages (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            game       INTEGER NOT NULL,
            player     TEXT,
            kind       TEXT    NOT NULL,
            question   INTEGER,
            body       TEXT    NOT NULL,
            created_at INTEGER NOT NULL
        );
        CREATE INDEX IF NOT EXISTS messages_q ON messages (game, question);
        CREATE TABLE IF NOT EXISTS custom_questions (
            id         INTEGER PRIMARY KEY AUTOINCREMENT,
            author     TEXT    NOT NULL,
            type       TEXT    NOT NULL,
            text       TEXT    NOT NULL,
            options    TEXT    NOT NULL DEFAULT '[]',
            created_at INTEGER NOT NULL
        );
        CREATE TABLE IF NOT EXISTS presence (
            player       TEXT PRIMARY KEY,
            last_seen    INTEGER NOT NULL DEFAULT 0,
            away         INTEGER NOT NULL DEFAULT 0,
            typing_until INTEGER NOT NULL DEFAULT 0
        );
    SQL);
    // upgrade databases created by earlier versions
    $addColumn = function (string $table, string $column, string $type) use ($pdo) {
        $cols = array_column($pdo->query("PRAGMA table_info($table)")->fetchAll(), 'name');
        if (!in_array($column, $cols, true)) $pdo->exec("ALTER TABLE $table ADD COLUMN $column $type");
    };
    $addColumn('answers', 'custom', 'TEXT');
    $addColumn('state', 'deck', 'TEXT');
    $addColumn('state', 'deck_mode', "TEXT NOT NULL DEFAULT 'classic'");
    return $pdo;
}

function q(string $sql, array $args = []): PDOStatement
{
    $st = db()->prepare($sql);
    $st->execute($args);
    return $st;
}

function row(string $sql, array $args = []): ?array
{
    $r = q($sql, $args)->fetch();
    return $r ?: null;
}

/** Run $fn inside a write-locked transaction so two taps can never race. */
function tx(callable $fn)
{
    $pdo = db();
    $pdo->exec('BEGIN IMMEDIATE');
    try {
        $result = $fn();
        $pdo->exec('COMMIT');
        return $result;
    } catch (Throwable $e) {
        $pdo->exec('ROLLBACK');
        throw $e;
    }
}

function fail(string $message): void
{
    throw new GameError($message);
}

/* ---------- game helpers ---------- */

function state_row(): array
{
    return row('SELECT * FROM state WHERE id = 1');
}

function bump(): void
{
    q('UPDATE state SET version = version + 1, updated_at = ? WHERE id = 1', [time()]);
}

function add_message(int $game, ?string $player, string $kind, ?int $question, string $body): void
{
    q(
        'INSERT INTO messages (game, player, kind, question, body, created_at) VALUES (?, ?, ?, ?, ?, ?)',
        [$game, $player, $kind, $question, $body, time()]
    );
}

function public_message(array $m): array
{
    return [
        'id'   => (int) $m['id'],
        'game' => (int) $m['game'],
        'by'   => $m['player'],
        'kind' => $m['kind'],
        'q'    => $m['question'] === null ? null : (int) $m['question'],
        'body' => $m['body'],
        'at'   => (int) $m['created_at'],
    ];
}

function messages_after(int $after): array
{
    $rows = $after > 0
        ? q('SELECT * FROM messages WHERE id > ? ORDER BY id ASC LIMIT 300', [$after])->fetchAll()
        : q('SELECT * FROM (SELECT * FROM messages ORDER BY id DESC LIMIT 200) ORDER BY id ASC')->fetchAll();
    return array_map('public_message', $rows);
}

function build_state(?array $s = null): array
{
    $s ??= state_row();
    $answered = q('SELECT question, player FROM answers WHERE game = ? ORDER BY created_at, question', [$s['game']])->fetchAll();
    // The most recent answer, so the other player can always see what was chosen
    // (even if they never caught the pick live between two polls).
    $last = row('SELECT question, player, choice, custom FROM answers WHERE game = ? ORDER BY rowid DESC LIMIT 1', [$s['game']]);
    return [
        'game'     => (int) $s['game'],
        'status'   => $s['status'],
        'turn'     => $s['turn'],
        'phase'    => $s['phase'],
        'question' => $s['question'] ? public_question((int) $s['question']) : null,
        'choice'   => $s['choice'] === null ? null : (int) $s['choice'],
        'roll'     => $s['roll'] ? json_decode($s['roll'], true) : null,
        'answered' => array_map(fn($r) => ['q' => (int) $r['question'], 'by' => $r['player']], $answered),
        'total'    => count(questions()),
        'deck'     => $s['deck_mode'] ?? 'classic',
        'last'     => $last ? [
            'q'      => public_question((int) $last['question']),
            'by'     => $last['player'],
            'choice' => $last['choice'] === null ? null : (int) $last['choice'],
            'custom' => $last['custom'],
        ] : null,
    ];
}

function presence(): array
{
    $now = time();
    $window = (int) cfg('online_window');
    $out = [];
    foreach (array_keys(players()) as $pid) {
        $out[$pid] = ['online' => false, 'away' => false, 'typing' => false];
    }
    foreach (q('SELECT * FROM presence')->fetchAll() as $p) {
        if (!isset($out[$p['player']])) continue;
        $online = (int) $p['last_seen'] >= $now - $window;
        $out[$p['player']] = [
            'online' => $online,
            'away'   => $online && (int) $p['away'] === 1,
            'typing' => $online && (int) $p['typing_until'] >= $now,
        ];
    }
    return $out;
}

function is_present(string $pid): bool
{
    return presence()[$pid]['online'] ?? false;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

function asset(string $path): string
{
    $file = __DIR__ . '/../' . $path;
    return e($path . '?v=' . (is_file($file) ? filemtime($file) : '1'));
}
