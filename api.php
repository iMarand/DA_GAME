<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/** Every write returns the fresh state so the screen updates instantly. */
function out_state(): void
{
    $s = state_row();
    out(['ok' => true, 'v' => (int) $s['version'], 'state' => build_state($s)]);
}

$me = current_player();
if (!$me) out(['error' => 'Please log in again.'], 401);

$action = (string) ($_GET['action'] ?? '');
$isPost = $_SERVER['REQUEST_METHOD'] === 'POST';

// Custom header = the browser must be our own page (blocks cross-site form posts).
// 'bye' is sent with navigator.sendBeacon, which cannot set headers; it only marks you offline.
if ($isPost && $action !== 'bye' && ($_SERVER['HTTP_X_HEARTS'] ?? '') !== '1') {
    out(['error' => 'Bad request.'], 400);
}
if (!$isPost && !in_array($action, ['poll', 'history', 'questions'], true)) {
    out(['error' => 'Use POST.'], 405);
}

$in = [];
if ($isPost) {
    $raw = file_get_contents('php://input') ?: '';
    $in = json_decode($raw, true);
    if (!is_array($in)) $in = [];
}

try {
    switch ($action) {

        case 'poll': {
            $away = !empty($_GET['h']) ? 1 : 0;
            q(
                'INSERT INTO presence (player, last_seen, away) VALUES (?, ?, ?)
                 ON CONFLICT(player) DO UPDATE SET last_seen = excluded.last_seen, away = excluded.away',
                [$me, time(), $away]
            );
            $s = state_row();
            $res = [
                'v'        => (int) $s['version'],
                'now'      => time(),
                'presence' => presence(),
                'msgs'     => messages_after((int) ($_GET['after'] ?? 0)),
                'qv'       => bank_version(),
            ];
            if ((int) ($_GET['v'] ?? 0) !== (int) $s['version']) {
                $res['state'] = build_state($s);
            }
            out($res);
        }

        case 'roll': {
            tx(function () use ($me) {
                $s = state_row();
                if ($s['status'] !== 'lobby') fail('The dice were already rolled.');
                $them = other_player($me);
                if (!is_present($them)) fail('Wait for ' . player_name($them) . ' to come online 💭');

                $ids = array_keys(players());
                do {
                    $dice = [$ids[0] => random_int(1, 6), $ids[1] => random_int(1, 6)];
                } while ($dice[$ids[0]] === $dice[$ids[1]]);
                $starter = $dice[$ids[0]] > $dice[$ids[1]] ? $ids[0] : $ids[1];
                $roll = ['dice' => $dice, 'starter' => $starter, 'by' => $me, 'at' => time()];

                q(
                    "UPDATE state SET status = 'playing', turn = ?, phase = 'pick', question = NULL, choice = NULL, roll = ? WHERE id = 1",
                    [$starter, json_encode($roll)]
                );
                add_message((int) $s['game'], null, 'system', null, sprintf(
                    '🎲 %s rolled %d · %s rolled %d — %s picks first!',
                    player_name($ids[0]), $dice[$ids[0]], player_name($ids[1]), $dice[$ids[1]], player_name($starter)
                ));
                bump();
            });
            out_state();
        }

        case 'pick': {
            tx(function () use ($me, $in) {
                $s = state_row();
                if ($s['status'] !== 'playing') fail('Roll the dice first 🎲');
                if ($s['turn'] !== $me) fail("It's " . player_name($s['turn']) . "'s turn to pick.");
                if ($s['phase'] !== 'pick') fail('Finish heart #' . $s['question'] . ' first.');
                $qid = (int) ($in['q'] ?? 0);
                if (!question($qid)) fail('That heart does not exist.');
                if (row('SELECT 1 FROM answers WHERE game = ? AND question = ?', [$s['game'], $qid])) {
                    fail('That heart is already open.');
                }
                q("UPDATE state SET phase = 'answer', question = ?, choice = NULL WHERE id = 1", [$qid]);
                add_message((int) $s['game'], $me, 'question', $qid, question($qid)['q']);
                bump();
            });
            out_state();
        }

        case 'choose': {
            tx(function () use ($me, $in) {
                $s = state_row();
                if ($s['status'] !== 'playing' || $s['phase'] !== 'answer' || $s['turn'] !== $me) fail("It's not your question.");
                $pq = public_question((int) $s['question']);
                if ($pq['type'] !== 'choice') fail('Answer this one in the chat 💬');
                $choice = (int) ($in['choice'] ?? -1);
                if (!isset($pq['options'][$choice]) && $choice !== custom_index($pq)) fail('Pick one of the options.');
                q('UPDATE state SET choice = ? WHERE id = 1', [$choice]);
                bump();
            });
            out_state();
        }

        case 'finish': {
            tx(function () use ($me, $in) {
                $s = state_row();
                if ($s['status'] !== 'playing' || $s['phase'] !== 'answer' || $s['turn'] !== $me) fail("It's not your question.");
                $game = (int) $s['game'];
                $qid = (int) $s['question'];
                $pq = public_question($qid);
                $custom = null;

                if ($pq['type'] === 'choice') {
                    if ($s['choice'] === null) fail('Choose an answer first.');
                    if ((int) $s['choice'] === custom_index($pq)) {
                        $custom = trim(str_replace("\r\n", "\n", (string) ($in['text'] ?? '')));
                        if ($custom === '') fail('Write your custom answer first ✍️');
                        if (strlen($custom) > 4000) fail('That answer is a little too long.');
                    }
                    add_message($game, $me, 'choice', $qid, $custom ?? $pq['options'][(int) $s['choice']]);
                } else {
                    $written = row(
                        "SELECT 1 FROM messages WHERE game = ? AND question = ? AND player = ? AND kind = 'answer'",
                        [$game, $qid, $me]
                    );
                    if (!$written) fail('Write your answer in the chat first 💬');
                }
                q(
                    'INSERT INTO answers (game, question, player, choice, custom, created_at) VALUES (?, ?, ?, ?, ?, ?)',
                    [$game, $qid, $me, $s['choice'], $custom, time()]
                );

                $opened = (int) row('SELECT COUNT(*) AS n FROM answers WHERE game = ?', [$game])['n'];
                if ($opened >= count(questions())) {
                    q("UPDATE state SET status = 'done', turn = NULL, phase = NULL, question = NULL, choice = NULL WHERE id = 1");
                    add_message($game, null, 'system', null, '💞 All hearts are open. What a journey!');
                } else {
                    q(
                        "UPDATE state SET turn = ?, phase = 'pick', question = NULL, choice = NULL WHERE id = 1",
                        [other_player($me)]
                    );
                }
                bump();
            });
            out_state();
        }

        case 'send': {
            $text = trim(str_replace("\r\n", "\n", (string) ($in['text'] ?? '')));
            if ($text === '') fail('Write something first.');
            if (strlen($text) > 8000) fail('That message is a little too long.');
            tx(function () use ($me, $text) {
                $s = state_row();
                $kind = 'text';
                $qid = null;
                if ($s['status'] === 'playing' && $s['phase'] === 'answer' && $s['question']) {
                    if (public_question((int) $s['question'])['type'] === 'open') {
                        $qid = (int) $s['question'];
                        $kind = $s['turn'] === $me ? 'answer' : 'text';
                    }
                }
                add_message((int) $s['game'], $me, $kind, $qid, $text);
                q('UPDATE presence SET typing_until = 0 WHERE player = ?', [$me]);
            });
            out(['ok' => true]);
        }

        case 'typing': {
            q('UPDATE presence SET typing_until = ? WHERE player = ?', [time() + 4, $me]);
            out(['ok' => true]);
        }

        case 'bye': {
            q('UPDATE presence SET last_seen = 0, typing_until = 0 WHERE player = ?', [$me]);
            out(['ok' => true]);
        }

        case 'reset': {
            // deck: classic = the built-in questions in order
            //       ours    = only the questions you wrote, shuffled
            //       mix     = your questions + random classics (up to 100 hearts), shuffled
            $mode = in_array($in['deck'] ?? '', ['classic', 'ours', 'mix'], true) ? $in['deck'] : 'classic';
            tx(function () use ($me, $mode) {
                $ours = array_map(fn($c) => [
                    'type' => $c['type'], 'q' => $c['q'], 'options' => $c['options'], 'author' => $c['author'],
                ], custom_questions());
                if ($mode !== 'classic' && !$ours) fail('Write some questions in the Questions tab first ✍️');

                $deck = null;
                if ($mode === 'ours') {
                    $deck = $ours;
                    shuffle($deck);
                } elseif ($mode === 'mix') {
                    $fill = builtin_questions();
                    shuffle($fill);
                    $deck = $ours;
                    while (count($deck) < 100 && $fill) $deck[] = array_shift($fill);
                    shuffle($deck);
                }

                q(
                    "UPDATE state SET game = game + 1, status = 'lobby', turn = NULL, phase = NULL, question = NULL,
                     choice = NULL, roll = NULL, deck = ?, deck_mode = ? WHERE id = 1",
                    [$deck === null ? null : json_encode($deck, JSON_UNESCAPED_UNICODE), $mode]
                );
                $s = state_row();
                $n = count($ours);
                $label = match ($mode) {
                    'ours'  => " with our $n questions, shuffled 🔀",
                    'mix'   => " — our $n questions shuffled in with the classics 🔀",
                    default => ' with the classic questions',
                };
                add_message((int) $s['game'], null, 'system', null, '💞 ' . player_name($me) . ' started a new game' . $label);
                bump();
            });
            out_state();
        }

        case 'questions': {
            // Your own questions in full; your partner's stay secret until they come up in a game.
            $all = custom_questions();
            $counts = array_fill_keys(array_keys(players()), 0);
            foreach ($all as $c) {
                if (isset($counts[$c['author']])) $counts[$c['author']]++;
            }
            out([
                'mine'   => array_values(array_filter($all, fn($c) => $c['author'] === $me)),
                'counts' => $counts,
                'qv'     => bank_version(),
            ]);
        }

        case 'add_question': {
            $clean = fn($s) => trim(preg_replace('/\s+/u', ' ', (string) $s) ?? '');
            $type = ($in['type'] ?? '') === 'choice' ? 'choice' : 'open';
            $text = $clean($in['text'] ?? '');
            if (strlen($text) < 3) fail('Write your question first ✍️');
            if (strlen($text) > 600) fail('That question is a little too long.');
            $options = [];
            if ($type === 'choice') {
                foreach ((array) ($in['options'] ?? []) as $o) {
                    $o = $clean($o);
                    if ($o === '' || in_array($o, $options, true)) continue;
                    if (strlen($o) > 240) fail('One of the answers is too long.');
                    $options[] = $o;
                }
                if (count($options) < 2) fail('Add at least 2 answers — a Custom option is added automatically.');
                if (count($options) > 6) fail('Up to 6 answers please.');
            }
            q(
                'INSERT INTO custom_questions (author, type, text, options, created_at) VALUES (?, ?, ?, ?, ?)',
                [$me, $type, $text, json_encode($options, JSON_UNESCAPED_UNICODE), time()]
            );
            out(['ok' => true, 'id' => (int) db()->lastInsertId()]);
        }

        case 'delete_question': {
            $st = q('DELETE FROM custom_questions WHERE id = ? AND author = ?', [(int) ($in['id'] ?? 0), $me]);
            if ($st->rowCount() === 0) fail('You can only delete your own questions.');
            out(['ok' => true]);
        }

        case 'history': {
            $s = state_row();
            $qid = (int) ($_GET['q'] ?? 0);
            if (!question($qid)) fail('That heart does not exist.');
            $ans = row('SELECT player, choice, custom FROM answers WHERE game = ? AND question = ?', [$s['game'], $qid]);
            $isCurrent = $s['phase'] === 'answer' && (int) $s['question'] === $qid;
            if (!$ans && !$isCurrent) fail('This heart is still closed 🔒');
            $thread = q(
                "SELECT * FROM messages WHERE game = ? AND question = ? AND kind IN ('text', 'answer') ORDER BY id",
                [$s['game'], $qid]
            )->fetchAll();
            out([
                'question' => public_question($qid),
                'by'       => $ans['player'] ?? $s['turn'],
                'choice'   => $ans ? ($ans['choice'] === null ? null : (int) $ans['choice']) : null,
                'custom'   => $ans['custom'] ?? null,
                'thread'   => array_map('public_message', $thread),
            ]);
        }

        default:
            out(['error' => 'Unknown action.'], 404);
    }
} catch (GameError $e) {
    out(['error' => $e->getMessage()], 409);
} catch (Throwable $e) {
    error_log('[100hearts] ' . $e);
    out(['error' => 'Server error — try again.'], 500);
}
