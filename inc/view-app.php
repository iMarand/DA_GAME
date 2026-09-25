<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
    <meta name="theme-color" content="#1c0c18">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <title><?= e(cfg('title')) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💗</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
</head>
<body>
    <!-- shared SVG pieces -->
    <svg class="svg-defs" aria-hidden="true" focusable="false">
        <defs>
            <linearGradient id="gHeart" x1="0" y1="0" x2="0.35" y2="1">
                <stop offset="0" stop-color="#ff86a6"/>
                <stop offset="1" stop-color="#e3124c"/>
            </linearGradient>
            <linearGradient id="gGold" x1="0" y1="0" x2="0.35" y2="1">
                <stop offset="0" stop-color="#ffe6ae"/>
                <stop offset="1" stop-color="#ff9a52"/>
            </linearGradient>
            <symbol id="heart-shape" viewBox="0 0 24 24">
                <path d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/>
            </symbol>
        </defs>
    </svg>

    <div class="app" id="app" data-tab="game">
        <header class="topbar">
            <div class="brand" aria-label="<?= e(cfg('title')) ?>">
                <svg viewBox="0 0 24 24" class="brand-heart"><use href="#heart-shape"/></svg>
            </div>
            <div class="couple" id="couple"></div>
            <button class="icon-btn" id="menuBtn" type="button" aria-label="Menu">
                <svg viewBox="0 0 24 24"><circle cx="5" cy="12" r="2"/><circle cx="12" cy="12" r="2"/><circle cx="19" cy="12" r="2"/></svg>
            </button>
        </header>

        <div class="conn" id="conn" hidden>Reconnecting…</div>

        <main class="panes">
            <!-- GAME -->
            <section class="pane pane-game" id="paneGame" aria-label="Hearts">
                <div class="game-top">
                    <div class="status" id="status"></div>
                    <div class="progress" id="progress">
                        <div class="progress-text"><span id="progressText">0 of 100 hearts opened</span></div>
                        <div class="progress-bar"><i id="progressFill"></i></div>
                    </div>
                </div>

                <div class="card lobby" id="lobby" hidden></div>
                <div class="card done" id="done" hidden></div>

                <div class="hearts" id="hearts" role="grid" aria-label="Question hearts"></div>
                <p class="hint" id="heartsHint">Tap an open heart anytime to see its answer again.</p>
            </section>

            <!-- CHAT -->
            <section class="pane pane-chat" id="paneChat" aria-label="Chat">
                <div class="chat-head">
                    <div>
                        <div class="chat-title">Our little chat</div>
                        <div class="chat-sub" id="chatSub">Say something sweet 💬</div>
                    </div>
                </div>
                <div class="pinned" id="pinned" hidden></div>
                <div class="messages" id="messages" aria-live="polite"></div>
                <div class="typing" id="typing" hidden><span class="dots"><i></i><i></i><i></i></span><span id="typingText"></span></div>
                <form class="composer" id="composer">
                    <textarea id="input" rows="1" placeholder="Write a message…" maxlength="4000" enterkeyhint="send"></textarea>
                    <button class="send" type="submit" aria-label="Send">
                        <svg viewBox="0 0 24 24"><path d="M3.4 20.4 21 12 3.4 3.6 3.4 10.1 15 12 3.4 13.9z"/></svg>
                    </button>
                </form>
            </section>

            <!-- QUESTIONS (write your own) -->
            <section class="pane pane-questions" id="paneQuestions" aria-label="Our questions">
                <div class="qb-hero">
                    <h2>Our questions</h2>
                    <p>Write questions for each other. Your partner's questions stay hidden 🔒 until they come up in a game.</p>
                    <div class="qb-counts" id="qbCounts"></div>
                </div>

                <form class="card qb-form" id="qbForm" autocomplete="off">
                    <div class="seg" role="group" aria-label="Question type">
                        <button type="button" class="seg-btn" data-type="choice" aria-pressed="true">☑️ Multiple choice</button>
                        <button type="button" class="seg-btn" data-type="open" aria-pressed="false">💬 Write it</button>
                    </div>

                    <label class="field-label" for="qbText">Your question</label>
                    <textarea class="qb-input" id="qbText" rows="2" maxlength="300" placeholder="e.g. What's my favorite late-night snack?"></textarea>

                    <div id="qbChoice">
                        <div class="field-label">Suggested answers</div>
                        <div class="qb-options" id="qbOptions"></div>
                        <button type="button" class="btn btn-ghost btn-sm" id="qbAddOpt">+ Add answer</button>
                        <p class="qb-note">A ✍️ <b>Custom</b> option is always added too, so they can write their own answer.</p>
                    </div>
                    <p class="qb-note" id="qbOpenNote" hidden>They'll answer this one in the chat, with your question pinned on top.</p>

                    <button class="btn btn-primary btn-block" type="submit" id="qbSave">Save question 💌</button>
                </form>

                <div class="qb-title">Play them</div>
                <div class="qb-play" id="qbPlay"></div>

                <div class="qb-title">Your questions <span id="qbMineCount"></span></div>
                <div class="qb-list" id="qbList"></div>
            </section>
        </main>

        <nav class="tabbar" aria-label="Sections">
            <button type="button" class="tab" data-tab="game" aria-selected="true">
                <svg viewBox="0 0 24 24"><use href="#heart-shape"/></svg><span>Hearts</span>
            </button>
            <button type="button" class="tab" data-tab="chat" aria-selected="false">
                <svg viewBox="0 0 24 24"><path d="M4 4h16a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H9l-5 4v-4a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2z"/></svg><span>Chat</span>
                <b class="badge" id="badge" hidden>0</b>
            </button>
            <button type="button" class="tab" data-tab="questions" aria-selected="false">
                <svg viewBox="0 0 24 24"><path d="M3 17.25V21h3.75L17.8 9.94l-3.75-3.75L3 17.25zM20.7 7.04a1 1 0 0 0 0-1.41l-2.33-2.33a1 1 0 0 0-1.41 0l-1.83 1.83 3.75 3.75 1.82-1.84z"/></svg><span>Questions</span>
            </button>
        </nav>
    </div>

    <!-- sheets -->
    <div class="scrim" id="scrim"></div>

    <section class="sheet" id="qSheet" role="dialog" aria-modal="true" aria-labelledby="qText"></section>

    <section class="sheet" id="menuSheet" role="dialog" aria-modal="true" aria-label="Menu">
        <div class="sheet-grab"></div>
        <div class="menu-me" id="menuMe"></div>
        <button class="menu-item" type="button" id="soundBtn">
            <span>🔔 Sounds</span><span class="switch" id="soundSwitch"></span>
        </button>
        <button class="menu-item" type="button" id="resetBtn">
            <span>💞 Start a new game</span><span class="menu-note">choose questions</span>
        </button>
        <form method="post" action="./">
            <input type="hidden" name="logout" value="1">
            <button class="menu-item danger" type="submit"><span>🚪 Log out</span></button>
        </form>
        <button class="btn btn-ghost btn-block" type="button" data-close>Close</button>
    </section>

    <div class="dice-overlay" id="dice" hidden>
        <div class="dice-box">
            <p class="dice-kicker">Rolling the dice…</p>
            <h2 class="dice-title">Who picks first?</h2>
            <div class="dice-row" id="diceRow"></div>
            <p class="dice-result" id="diceResult">&nbsp;</p>
        </div>
    </div>

    <div class="toasts" id="toasts" aria-live="assertive"></div>

    <script>window.BOOT = <?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_UNESCAPED_UNICODE) ?>;</script>
    <script src="<?= asset('assets/app.js') ?>"></script>
</body>
</html>
