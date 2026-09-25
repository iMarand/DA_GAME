/* 100 Hearts — client. No framework, no build step. */
(() => {
    'use strict';

    const B = window.BOOT;
    const P = B.players;
    const ME = B.me;
    const THEM = Object.keys(P).find((p) => p !== ME) || ME;
    const NAME = (pid) => (pid === ME ? 'You' : (P[pid] ? P[pid].name : 'Someone'));
    const LETTERS = 'ABCDEFGH';

    /* ---------- tiny helpers ---------- */

    const $ = (s, r = document) => r.querySelector(s);
    const SVGNS = 'http://www.w3.org/2000/svg';

    function h(tag, props, ...kids) {
        const el = document.createElement(tag);
        for (const [k, v] of Object.entries(props || {})) {
            if (v == null || v === false) continue;
            if (k === 'class') el.className = v;
            else if (k === 'text') el.textContent = v;
            else if (k === 'style') el.style.cssText = v;
            else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
            else el.setAttribute(k, v === true ? '' : v);
        }
        for (const kid of kids.flat()) {
            if (kid == null || kid === false) continue;
            el.append(kid.nodeType ? kid : document.createTextNode(String(kid)));
        }
        return el;
    }

    function heartIcon(withShine) {
        const svg = document.createElementNS(SVGNS, 'svg');
        svg.setAttribute('viewBox', '0 0 24 24');
        svg.setAttribute('aria-hidden', 'true');
        const use = document.createElementNS(SVGNS, 'use');
        use.setAttribute('href', '#heart-shape');
        use.setAttribute('class', 'shape');
        svg.append(use);
        if (withShine) {
            const e = document.createElementNS(SVGNS, 'ellipse');
            e.setAttribute('class', 'shine');
            e.setAttribute('cx', '7.4'); e.setAttribute('cy', '7.6');
            e.setAttribute('rx', '2.4'); e.setAttribute('ry', '1.4');
            e.setAttribute('transform', 'rotate(-38 7.4 7.6)');
            svg.append(e);
        }
        return svg;
    }

    function avatar(pid, size = '', dot = false) {
        const p = P[pid] || { initial: '♥', color: '#ff86a6' };
        return h('span', { class: `avatar ${size}`, style: `--pc:${p.color}`, 'data-avatar': pid },
            p.initial, dot ? h('i', { class: 'dot' }) : null);
    }

    const store = {
        get(k, d) { try { const v = localStorage.getItem(k); return v === null ? d : v; } catch { return d; } },
        set(k, v) { try { localStorage.setItem(k, v); } catch { /* private mode */ } },
    };
    const session = {
        has(k) { try { return sessionStorage.getItem(k) === '1'; } catch { return false; } },
        add(k) { try { sessionStorage.setItem(k, '1'); } catch { /* ignore */ } },
    };

    const mqDesktop = matchMedia('(min-width: 900px)');
    const mqFinePointer = matchMedia('(pointer: fine)');
    const reducedMotion = matchMedia('(prefers-reduced-motion: reduce)').matches;
    const isDesktop = () => mqDesktop.matches;

    const fmtTime = (ts) => new Date(ts * 1000).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
    function dayLabel(ts) {
        const d = new Date(ts * 1000), t = new Date();
        const y = new Date(); y.setDate(t.getDate() - 1);
        if (d.toDateString() === t.toDateString()) return 'Today';
        if (d.toDateString() === y.toDateString()) return 'Yesterday';
        return d.toLocaleDateString([], { weekday: 'short', month: 'short', day: 'numeric' });
    }

    /* ---------- player colors ---------- */

    const rootStyle = document.documentElement.style;
    for (const [pid, p] of Object.entries(P)) rootStyle.setProperty(`--c-${pid}`, p.color);
    rootStyle.setProperty('--c-me', P[ME].color);
    rootStyle.setProperty('--c-them', P[THEM].color);

    /* ---------- elements ---------- */

    const app = $('#app');
    const el = {
        couple: $('#couple'), conn: $('#conn'),
        status: $('#status'), progressText: $('#progressText'), progressFill: $('#progressFill'),
        lobby: $('#lobby'), done: $('#done'), hearts: $('#hearts'), hint: $('#heartsHint'),
        pinned: $('#pinned'), messages: $('#messages'), typing: $('#typing'), typingText: $('#typingText'),
        composer: $('#composer'), input: $('#input'), chatSub: $('#chatSub'), badge: $('#badge'),
        scrim: $('#scrim'), qSheet: $('#qSheet'), menuSheet: $('#menuSheet'),
        dice: $('#dice'), diceRow: $('#diceRow'), diceResult: $('#diceResult'), toasts: $('#toasts'),
    };

    /* ---------- state ---------- */

    let S = null;          // game state from server
    let V = 0;             // state version
    let presence = {};     // { pid: {online, away, typing} }
    let lastMsg = 0;
    const msgs = [];
    let unread = 0;
    let serverSkew = 0;
    let diceBusy = false;
    let sheetModel = null; // what the question sheet is showing

    /* ---------- network ---------- */

    async function api(action, { method = 'GET', body = null, params = {} } = {}) {
        const qs = new URLSearchParams({ action, ...params });
        const opts = { method, credentials: 'same-origin', cache: 'no-store', headers: {} };
        if (method === 'POST') {
            opts.headers = { 'Content-Type': 'application/json', 'X-Hearts': '1' };
            opts.body = JSON.stringify(body || {});
        }
        const res = await fetch(`api.php?${qs}`, opts);
        if (res.status === 401) { location.reload(); throw new Error('auth'); }
        const data = await res.json().catch(() => ({}));
        if (!res.ok) throw new Error(data.error || 'Something went wrong');
        return data;
    }

    async function act(action, body) {
        try {
            const r = await api(action, { method: 'POST', body });
            if (r.state) applyState(r.state, r.v);
            pollNow();
            return r;
        } catch (e) {
            if (e.message !== 'auth') toast(e.message, 'error');
            return null;
        }
    }

    let pollTimer = null, polling = false, pollAgain = false, fails = 0, firstPoll = true;

    async function poll() {
        if (polling) { pollAgain = true; return; }
        polling = true;
        clearTimeout(pollTimer);
        try {
            const r = await api('poll', { params: { v: V, after: lastMsg, h: document.hidden ? 1 : 0 } });
            fails = 0;
            el.conn.hidden = true;
            serverSkew = r.now * 1000 - Date.now();
            presence = r.presence || {};
            if (r.msgs && r.msgs.length) addMessages(r.msgs, firstPoll);
            if (r.state) applyState(r.state, r.v);
            else renderPresence();
            if (r.qv && r.qv !== bank.qv) loadBank();
            if (firstPoll) { firstPoll = false; scrollChatToBottom(); }
        } catch (e) {
            if (++fails >= 3) el.conn.hidden = false;
        }
        polling = false;
        if (pollAgain) { pollAgain = false; poll(); return; }
        pollTimer = setTimeout(poll, document.hidden ? 4000 : 1000);
    }
    const pollNow = () => { clearTimeout(pollTimer); poll(); };

    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) { restoreTitle(); pollNow(); }
    });
    window.addEventListener('pagehide', () => {
        try { navigator.sendBeacon && navigator.sendBeacon('api.php?action=bye'); } catch { /* ignore */ }
    });
    window.addEventListener('online', pollNow);

    /* ---------- state changes ---------- */

    function applyState(ns, v) {
        if (v != null && v < V) return; // stale response
        const prev = S;
        S = ns;
        if (v != null) V = v;
        render();
        transitions(prev, S);
    }

    function transitions(prev, cur) {
        const curQ = cur.question ? cur.question.id : null;

        if (!prev) {
            if (cur.roll) showDice(cur.roll);
            if (curQ && cur.question.type === 'choice') openQuestionSheet(liveModel());
            if (curQ && cur.question.type === 'open' && !isDesktop()) setTab('chat');
            return;
        }

        if (prev.game !== cur.game) {
            closeSheets();
            toast('💞 A new game has started', 'love');
        }

        if (cur.roll && (!prev.roll || prev.roll.at !== cur.roll.at)) showDice(cur.roll);

        const prevQ = prev.question ? prev.question.id : null;

        // A heart was opened
        if (curQ && curQ !== prevQ) {
            const mine = cur.turn === ME;
            if (!mine) {
                notify(`${P[THEM].name} opened heart #${curQ} 💗`, 'open');
            }
            if (cur.question.type === 'choice') {
                openQuestionSheet(liveModel());
            } else {
                closeSheets();
                setTab('chat');
                if (mine) setTimeout(() => el.input.focus(), 350);
                else toast(`#${curQ} is a write-it question — answer is coming in the chat 💬`, 'love');
            }
        }

        // A heart was answered. The server sends exactly what was answered (state.last),
        // so this works even if the pick was never seen live between two polls.
        const lastKey = (s) => (s && s.last ? `${s.game}:${s.last.q.id}` : '');
        if (cur.last && prev.game === cur.game && lastKey(cur) !== lastKey(prev)) {
            const a = cur.last;
            if (a.by === ME) {
                burst();
                if (sheetModel && sheetModel.mode === 'live') closeSheets();
                if (cur.status === 'playing') toast(`Sent 💗 Now it's ${P[THEM].name}'s turn`, 'love');
            } else if (a.q.type === 'choice' && a.custom != null) {
                // their own words: read them in the chat
                closeSheets();
                setTab('chat');
                flashAnswer(a.q.id);
                toast(`${P[a.by].name} wrote their own answer to #${a.q.id} — it's in the chat 💬`, 'love');
                burst();
            } else if (a.q.type === 'choice') {
                openQuestionSheet({ q: a.q, by: a.by, choice: a.choice, mode: 'final' });
                burst();
            } else {
                toast(`${P[a.by].name} finished heart #${a.q.id} 💗`, 'love');
            }
        }

        // It became my turn to pick
        const myPick = (s) => s && s.status === 'playing' && s.phase === 'pick' && s.turn === ME;
        if (myPick(cur) && !myPick(prev)) {
            setTimeout(() => {
                notify('Your turn! Pick a heart 💗', 'turn', !isDesktop() && app.dataset.tab !== 'game'
                    ? { label: 'Go', fn: () => setTab('game') } : null);
                if (userTapped() && navigator.vibrate) navigator.vibrate([60, 60, 60]);
            }, diceBusy ? 2600 : 250);
        }

        // Everything opened
        if (prev.status !== 'done' && cur.status === 'done') {
            setTab('game');
            setTimeout(() => { burst(innerWidth / 2, innerHeight / 2, 34); notify('All 100 hearts are open 💞', 'turn'); }, 500);
        }

        // Live update of the open sheet
        // (only when the pick changed, so a custom answer being typed isn't disturbed)
        if (sheetModel && sheetModel.mode === 'live' && curQ === sheetModel.q.id && sheetModel.choice !== cur.choice) {
            sheetModel.choice = cur.choice;
            renderSheet();
        }
    }

    const liveModel = () => ({ q: S.question, by: S.turn, choice: S.choice, mode: 'live' });

    /* ---------- render ---------- */

    function render() {
        if (!S) return;
        renderPresence();
        renderStatus();
        renderHearts();
        renderCards();
        renderPinned();
        renderComposer();
        if (bank.loaded) renderPlay();
    }

    function presenceOf(pid) { return presence[pid] || { online: false, away: false, typing: false }; }
    function statusText(pid) {
        const p = presenceOf(pid);
        if (p.typing) return ['typing…', 'typing'];
        if (p.away) return ['away', 'away'];
        if (p.online) return ['online', 'on'];
        return ['offline', ''];
    }

    function buildCouple() {
        const ids = Object.keys(P);
        const chip = (pid, right) => h('div', { class: `pchip${right ? ' right' : ''}`, id: `chip-${pid}`, style: `--pc:${P[pid].color}` },
            h('i', { class: 'pdot' }),
            h('span', { class: 'pchip-name', text: P[pid].name }));
        el.couple.replaceChildren(chip(ids[0], false), h('span', { class: 'couple-link', text: '♥' }), chip(ids[1], true));
    }

    function renderPresence() {
        for (const pid of Object.keys(P)) {
            const chip = document.getElementById(`chip-${pid}`);
            if (!chip) continue;
            // just a small dot: green = online, amber = away, grey = offline
            const [txt, cls] = pid === ME ? ['you', 'on'] : statusText(pid);
            chip.querySelector('.pdot').className = `pdot ${cls}`;
            chip.title = `${P[pid].name} · ${txt}`;
            chip.classList.toggle('turn', !!S && S.status === 'playing' && S.turn === pid);
        }
        const them = presenceOf(THEM);
        el.typing.hidden = !them.typing;
        el.typingText.textContent = `${P[THEM].name} is typing…`;
        el.chatSub.textContent = them.typing ? `${P[THEM].name} is typing…`
            : them.online ? `${P[THEM].name} is ${them.away ? 'away' : 'here'} 💬` : `${P[THEM].name} is offline`;
        if (S && S.status === 'lobby') renderCards();
    }

    function renderStatus() {
        const s = S;
        el.status.className = 'status';
        if (s.status !== 'playing') { el.status.replaceChildren(); return; }

        const mine = s.turn === ME;
        const them = P[THEM].name;
        let icon, title, sub, btn = null, cls = '';

        if (s.phase === 'pick') {
            if (mine) {
                icon = '💗'; title = 'Your turn!'; sub = 'Pick any heart to open a question.'; cls = 'mine';
            } else {
                icon = '💭'; title = `${them} is picking…`;
                sub = presenceOf(THEM).online ? 'The question will show up here as soon as a heart opens.'
                    : `${them} is offline right now — you'll be notified.`;
            }
        } else if (s.phase === 'answer' && s.question) {
            const q = s.question;
            cls = 'gold';
            if (q.type === 'choice') {
                icon = mine ? '✍️' : '👀';
                title = mine ? `Answer heart #${q.id}` : `${them} is answering #${q.id}`;
                sub = q.text;
                btn = h('button', { class: 'btn btn-gold btn-sm', type: 'button', onclick: () => openQuestionSheet(liveModel()) }, mine ? 'Answer' : 'Watch');
            } else {
                icon = '💬';
                title = mine ? `Answer #${q.id} in the chat` : `${them} is writing #${q.id}`;
                sub = q.text;
                btn = h('button', {
                    class: 'btn btn-gold btn-sm', type: 'button',
                    onclick: () => { setTab('chat'); el.input.focus(); },
                }, isDesktop() ? 'Write' : 'Open chat');
            }
        }
        if (cls) el.status.classList.add(cls);
        el.status.replaceChildren(...[
            h('div', { class: 'status-icon', text: icon }),
            h('div', { class: 'status-body' }, h('div', { class: 'status-title', text: title }), h('div', { class: 'status-sub', text: sub })),
            btn,
        ].filter(Boolean));
    }

    const heartBtns = [];
    function buildHearts(total) {
        if (heartBtns.length === total) return;
        el.hearts.replaceChildren();
        heartBtns.length = 0;
        for (let i = 1; i <= total; i++) {
            const b = h('button', { class: 'heart', type: 'button', 'data-q': i, style: `--i:${i}`, 'aria-label': `Heart ${i}` },
                heartIcon(true), h('span', { class: 'num', text: i }), h('span', { class: 'by' }));
            b.addEventListener('animationend', (e) => { if (e.animationName === 'opening') b.classList.remove('opening'); });
            heartBtns.push(b);
        }
        el.hearts.append(...heartBtns);
    }

    function renderHearts() {
        buildHearts(S.total);
        const opened = new Map(S.answered.map((a) => [a.q, a.by]));
        const activeId = S.phase === 'answer' && S.question ? S.question.id : null;
        heartBtns.forEach((b, idx) => {
            const q = idx + 1;
            const by = opened.get(q);
            b.classList.toggle('open', !!by);
            b.classList.toggle('active', q === activeId);
            if (by) {
                b.style.setProperty('--pc', P[by] ? P[by].color : '#fff');
                b.querySelector('.by').textContent = P[by] ? P[by].initial : '';
                b.setAttribute('aria-label', `Heart ${q}, answered by ${NAME(by)}`);
            } else {
                b.setAttribute('aria-label', q === activeId ? `Heart ${q}, open now` : `Heart ${q}, closed`);
            }
        });
        const pickable = S.status === 'playing' && S.phase === 'pick' && S.turn === ME;
        el.hearts.classList.toggle('pickable', pickable);
        el.hearts.classList.toggle('locked', !pickable && S.status !== 'done');

        const n = S.answered.length;
        el.progressText.textContent = `${n} of ${S.total} hearts opened`;
        el.progressFill.style.width = `${(n / Math.max(1, S.total)) * 100}%`;
        el.hint.hidden = n === 0;
    }

    let cardsKey = '';
    function renderCards() {
        if (!S) return;
        // Only rebuild when something visible changed (keeps taps from being lost mid-rebuild)
        const key = [S.game, S.status, presenceOf(THEM).online, S.answered.length].join('|');
        if (key === cardsKey) return;
        cardsKey = key;

        // Lobby
        if (S.status === 'lobby') {
            const themOn = presenceOf(THEM).online;
            const person = (pid) => {
                const on = pid === ME || presenceOf(pid).online;
                const av = avatar(pid, 'lg', true);
                av.querySelector('.dot').className = `dot ${on ? 'on' : ''}`;
                return h('div', { class: 'lobby-person' }, av, h('b', { text: P[pid].name }),
                    h('small', { class: on ? 'on' : '', text: on ? 'here' : 'not here yet' }));
            };
            const ids = Object.keys(P);
            el.lobby.replaceChildren(
                h('div', { class: 'lobby-duo' }, person(ids[0]), h('span', { class: 'lobby-heart', text: '♥' }), person(ids[1])),
                h('h2', { text: themOn ? "You're both here 💞" : `Waiting for ${P[THEM].name}…` }),
                h('p', { text: themOn ? 'Roll the dice to see who picks the first heart.' : 'Send them their code.' }),
                h('button', { class: 'btn btn-primary btn-block', type: 'button', disabled: !themOn, onclick: () => act('roll') }, '🎲 Roll the dice'));
            el.lobby.hidden = false;
        } else {
            el.lobby.hidden = true;
        }

        // Finished
        if (S.status === 'done') {
            const count = (pid) => S.answered.filter((a) => a.by === pid).length;
            const again = h('button', { class: 'btn btn-primary btn-block', type: 'button', onclick: goToPlay }, '💞 Play again');
            el.done.replaceChildren(
                h('div', { style: 'font-size:46px' }, '💞'),
                h('h2', { text: 'All hearts are open' }),
                h('p', { text: 'Tap any heart below to relive the answers.' }),
                h('div', { class: 'done-stats' }, ...Object.keys(P).map((pid) =>
                    h('div', { class: 'done-stat', style: `--pc:${P[pid].color}` }, h('b', { text: count(pid) }), h('span', { text: `answered by ${P[pid].name}` })))),
                again);
            if (el.done.hidden) el.done.hidden = false;
        } else {
            el.done.hidden = true;
        }
    }

    function openAnswerFor(qid) {
        return msgs.some((m) => m.kind === 'answer' && m.by === ME && m.q === qid && m.game === S.game);
    }

    let pinnedKey = '';
    function renderPinned() {
        if (!S) return;
        const q = S.question;
        if (!(S.status === 'playing' && S.phase === 'answer' && q && q.type === 'open')) {
            el.pinned.hidden = true;
            pinnedKey = '';
            return;
        }
        const mine = S.turn === ME;
        const ready = mine && openAnswerFor(q.id);
        const key = [S.game, q.id, S.turn, ready].join('|');
        if (key === pinnedKey) return;
        pinnedKey = key;
        const kids = [
            h('div', { class: 'pinned-top' }, h('span', { text: `💗 Heart #${q.id} · write it` }), avatar(S.turn, 'xs')),
            authorTag(q),
            h('div', { class: 'pinned-q', text: q.text }),
            h('div', {
                class: 'pinned-sub',
                text: mine
                    ? (ready ? 'Add more if you like — then tap Finish to pass the turn.' : 'Write your answer below. You can send as many messages as you want.')
                    : `${P[S.turn].name} is answering. Share what you think too 💭`,
            }),
        ];
        if (mine) {
            const fin = h('button', { class: 'btn btn-gold btn-block', type: 'button', disabled: !ready }, 'Finish & pass the turn ✓');
            fin.addEventListener('click', () => finish(fin));
            kids.push(fin);
        }
        el.pinned.replaceChildren(...kids);
        el.pinned.hidden = false;
    }

    function renderComposer() {
        const q = S && S.question;
        const open = S && S.status === 'playing' && S.phase === 'answer' && q && q.type === 'open';
        const answering = open && S.turn === ME;
        el.composer.classList.toggle('answering', !!answering);
        el.input.placeholder = answering ? `Your answer to #${q.id}…`
            : open ? `Your thoughts on #${q.id}…` : 'Write a message…';
    }

    /** "Written by …" chip for questions from the Questions tab (null for classic ones). */
    function authorTag(q) {
        if (!q || !q.author || !P[q.author]) return null;
        return h('div', { class: 'q-author', style: `--pc:${P[q.author].color}` },
            q.author === ME ? '✍️ Your own question' : `✍️ Written by ${P[q.author].name}`);
    }

    async function finish(btn, body = {}) {
        btn.disabled = true;
        if (!(await act('finish', body))) btn.disabled = false;
    }

    /* ---------- hearts interaction ---------- */

    el.hearts.addEventListener('click', (ev) => {
        const b = ev.target.closest('.heart');
        if (!b || !S) return;
        const qid = +b.dataset.q;

        if (b.classList.contains('open')) return showHistory(qid);
        if (b.classList.contains('active')) {
            if (S.question.type === 'choice') openQuestionSheet(liveModel());
            else setTab('chat');
            return;
        }
        if (S.status === 'lobby') return toast('Roll the dice first 🎲');
        if (S.status !== 'playing') return;
        if (S.turn !== ME) return toast(`It's ${P[S.turn].name}'s turn 💭`);
        if (S.phase !== 'pick') return toast(`Finish heart #${S.question.id} first`);

        b.classList.add('opening');
        act('pick', { q: qid }).then((r) => { if (!r) b.classList.remove('opening'); });
    });

    async function showHistory(qid) {
        try {
            const r = await api('history', { params: { q: qid } });
            openQuestionSheet({ q: r.question, by: r.by, choice: r.choice, custom: r.custom, mode: 'history', thread: r.thread });
        } catch (e) {
            toast(e.message, 'error');
        }
    }

    /* ---------- question sheet ---------- */

    function openQuestionSheet(model) {
        sheetModel = model;
        renderSheet();
        openSheet(el.qSheet);
    }

    function renderSheet() {
        const m = sheetModel;
        if (!m) return;
        const q = m.q;
        const mine = m.by === ME;
        const live = m.mode === 'live';
        const canAnswer = live && mine;
        const who = P[m.by] ? P[m.by].name : 'Someone';

        const badge = h('span', { class: 'q-badge' }, heartIcon(false), `#${q.id}`);
        const head = h('div', { class: 'q-head' }, badge,
            h('span', { class: 'q-type', text: q.type === 'choice' ? 'Multiple choice' : 'Write it' }),
            h('button', { class: 'icon-btn', type: 'button', 'aria-label': 'Close', 'data-close': true, text: '✕' }));

        const byLine = h('div', { class: 'q-by' }, avatar(m.by, 'sm'),
            m.mode === 'history' ? `Answered by ${mine ? 'you' : who}` : mine ? 'You opened this heart' : `${who} opened this heart`);

        const parts = [h('div', { class: 'sheet-grab' }), head, byLine, authorTag(q), h('h2', { class: 'q-text', id: 'qText', text: q.text })];

        let customInput = null;
        let fin = null;
        const customIdx = q.options.length; // the extra "Custom" option always comes last
        const isCustom = m.choice === customIdx;
        const customReady = () => !isCustom || !!(m.customText || '').trim();

        if (q.type === 'choice') {
            const option = (i, text, sub) => {
                const chosen = m.choice === i;
                let cls = `opt${i === customIdx ? ' custom' : ''}`;
                if (chosen) cls += live ? ' selected' : ' final';
                else if (!live && m.choice != null) cls += ' dim';
                const b = h('button', { class: cls, type: 'button', disabled: !canAnswer, 'aria-pressed': chosen ? 'true' : 'false' },
                    h('span', { class: 'opt-letter', text: LETTERS[i] || i + 1 }),
                    h('span', { class: 'opt-body' }, h('span', { class: 'opt-text', text }), sub ? h('span', { class: 'opt-sub', text: sub }) : null));
                if (canAnswer) {
                    b.addEventListener('click', () => {
                        if (m.choice === i) return;
                        m.choice = i;
                        m.focusCustom = i === customIdx;
                        renderSheet();
                        act('choose', { choice: i });
                    });
                }
                return b;
            };
            const opts = q.options.map((text, i) => option(i, text));
            const customText = !live && isCustom && m.custom ? m.custom : 'Custom';
            opts.push(option(customIdx, customText, live && !(isCustom && !mine) ? '✍️ Write your own answer' : null));
            parts.push(h('div', { class: 'options' }, opts));

            if (canAnswer && isCustom) {
                customInput = h('textarea', {
                    class: 'custom-input', id: 'customInput', rows: 3, maxlength: 2000,
                    placeholder: 'Write your own answer…', 'aria-label': 'Your custom answer',
                });
                customInput.value = m.customText || '';
                customInput.addEventListener('input', () => {
                    m.customText = customInput.value;
                    if (fin) fin.disabled = !customReady();
                });
                parts.push(customInput);
            }

            let note = null;
            const dots = () => h('span', { class: 'dots' }, h('i'), h('i'), h('i'));
            if (live && !mine) {
                note = m.choice == null ? h('div', { class: 'q-note' }, dots(), `${who} is choosing…`)
                    : isCustom ? h('div', { class: 'q-note' }, dots(), `${who} is writing their own answer ✍️`)
                    : h('div', { class: 'q-note' }, dots(), `${who} is leaning towards ${LETTERS[m.choice]}…`);
            } else if (live && mine) {
                note = h('div', {
                    class: 'q-note',
                    text: m.choice == null ? 'Tap your answer — you can change it until you finish.'
                        : isCustom ? `${P[THEM].name} will see your words when you finish 💌`
                        : `${P[THEM].name} can see your pick live 👀`,
                });
            } else if (m.mode === 'final') {
                note = h('div', { class: 'q-note gold', text: `${who}'s final answer 💗` });
            }
            if (note) parts.push(note);
        } else {
            // open question: show the conversation around it
            const thread = m.thread || msgs.filter((x) => x.game === S.game && x.q === q.id && (x.kind === 'answer' || x.kind === 'text'));
            parts.push(thread.length
                ? h('div', { class: 'thread' }, thread.map(renderBubble))
                : h('p', { class: 'thread-empty', text: 'No words here yet.' }));
        }

        const actions = h('div', { class: 'q-actions' });
        if (canAnswer && q.type === 'choice') {
            fin = h('button', { class: 'btn btn-primary btn-block', type: 'button', disabled: m.choice == null || !customReady() }, 'Finish & pass the turn 💗');
            fin.addEventListener('click', () => finish(fin, isCustom ? { text: m.customText } : {}));
            actions.append(fin);
        } else if (m.mode === 'final') {
            actions.append(h('button', {
                class: 'btn btn-gold btn-block', type: 'button', 'data-close': true,
                text: S && S.status === 'playing' && S.turn === ME ? 'My turn now ✨' : 'Close',
            }));
        } else if (!live) {
            actions.append(h('button', { class: 'btn btn-ghost btn-block', type: 'button', 'data-close': true, text: 'Close' }));
        } else {
            actions.append(h('button', { class: 'btn btn-ghost btn-block', type: 'button', 'data-close': true, text: 'Hide (keeps updating)' }));
        }
        parts.push(actions);

        const typingCustom = document.activeElement && document.activeElement.id === 'customInput';
        el.qSheet.replaceChildren(...parts);
        if (customInput && (typingCustom || m.focusCustom)) {
            m.focusCustom = false;
            customInput.focus();
            customInput.setSelectionRange(customInput.value.length, customInput.value.length);
        }
    }

    /* ---------- sheets ---------- */

    let openSheetEl = null;
    function openSheet(sheet) {
        if (openSheetEl && openSheetEl !== sheet) openSheetEl.classList.remove('open');
        openSheetEl = sheet;
        sheet.classList.add('open');
        el.scrim.classList.add('open');
    }
    function closeSheets() {
        document.querySelectorAll('.sheet.open').forEach((s) => s.classList.remove('open'));
        el.scrim.classList.remove('open');
        openSheetEl = null;
        sheetModel = null;
    }
    el.scrim.addEventListener('click', closeSheets);
    document.addEventListener('click', (e) => { if (e.target.closest('[data-close]')) closeSheets(); });
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape') { if (!el.dice.hidden) closeDice(); else closeSheets(); }
    });

    /* ---------- chat ---------- */

    let lastDay = '';

    function renderBubble(m) {
        const mine = m.by === ME;
        let label = null;
        let cls = `msg ${mine ? 'me' : 'them'}`;
        if (m.kind === 'answer') { cls += ' answer'; label = `💗 ${mine ? 'Your' : `${P[m.by].name}'s`} answer · #${m.q}`; }
        else if (m.kind === 'choice') { cls += ' choice'; label = `💗 ${mine ? 'Your' : `${P[m.by].name}'s`} final answer · #${m.q}`; }
        else if (m.q) { label = `💭 on #${m.q}`; }
        return h('div', { class: cls, 'data-id': m.id },
            label ? h('div', { class: 'msg-label', text: label }) : null,
            h('div', { class: 'bubble', text: m.body }),
            h('div', { class: 'msg-time', text: fmtTime(m.at) }));
    }

    function renderMessage(m) {
        if (m.kind === 'system') return h('div', { class: 'sys', text: m.body });
        if (m.kind === 'question') {
            return h('div', { class: 'qcard' },
                h('div', { class: 'qcard-top' }, avatar(m.by, 'xs'), `${NAME(m.by)} opened heart`, h('b', { text: `#${m.q}` })),
                h('p', { class: 'qcard-text', text: m.body }));
        }
        return renderBubble(m);
    }

    function nearBottom() {
        const m = el.messages;
        return m.scrollHeight - m.scrollTop - m.clientHeight < 140;
    }
    function scrollChatToBottom(smooth) {
        const m = el.messages;
        m.scrollTo({ top: m.scrollHeight, behavior: smooth && !reducedMotion ? 'smooth' : 'auto' });
    }
    const chatVisible = () => !document.hidden && (isDesktop() || app.dataset.tab === 'chat');

    // Stay glued to the newest message when the pinned card, keyboard or window resizes the chat
    let stuckToBottom = true;
    el.messages.addEventListener('scroll', () => { stuckToBottom = nearBottom(); }, { passive: true });
    if (window.ResizeObserver) {
        new ResizeObserver(() => { if (stuckToBottom) scrollChatToBottom(); }).observe(el.messages);
    }

    function addMessages(list, initial) {
        const stick = initial || nearBottom();
        let fromThem = 0;
        const empty = el.messages.querySelector('.messages-empty');
        for (const m of list) {
            if (m.id <= lastMsg) continue;
            lastMsg = m.id;
            msgs.push(m);
            if (empty) empty.remove();
            const day = dayLabel(m.at);
            if (day !== lastDay) { lastDay = day; el.messages.append(h('div', { class: 'day', text: day })); }
            el.messages.append(renderMessage(m));
            if (!initial && m.by === THEM && m.kind !== 'question') fromThem++;
        }
        if (msgs.length > 600) msgs.splice(0, msgs.length - 600);

        if (stick || list.some((m) => m.by === ME)) scrollChatToBottom(!initial);
        if (fromThem) {
            if (!chatVisible()) setUnread(unread + fromThem);
            if (document.hidden || !chatVisible()) chime('msg');
            if (document.hidden) flashTitle(`💬 ${P[THEM].name} wrote`);
        }
        renderPinned();
        if (sheetModel && sheetModel.mode === 'live' && sheetModel.q.type === 'open') renderSheet();
    }

    /** Scroll to and glow the final-answer bubble for heart #qid. */
    function flashAnswer(qid) {
        let m = null;
        for (let i = msgs.length - 1; i >= 0; i--) {
            if (msgs[i].kind === 'choice' && msgs[i].q === qid && msgs[i].game === S.game) { m = msgs[i]; break; }
        }
        const node = m && el.messages.querySelector(`[data-id="${m.id}"]`);
        if (!node) return;
        setTimeout(() => {
            node.scrollIntoView({ block: 'center', behavior: reducedMotion ? 'auto' : 'smooth' });
            node.classList.remove('flash');
            void node.offsetWidth; // restart the animation
            node.classList.add('flash');
        }, 150);
    }

    function setUnread(n) {
        unread = n;
        el.badge.hidden = n <= 0;
        el.badge.textContent = n > 99 ? '99+' : n;
    }

    el.composer.addEventListener('submit', (e) => {
        e.preventDefault();
        send();
    });
    el.input.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !e.shiftKey && mqFinePointer.matches) {
            e.preventDefault();
            send();
        }
    });

    let sending = false, lastTyping = 0;
    async function send() {
        const text = el.input.value.trim();
        if (!text || sending) return;
        sending = true;
        el.input.value = '';
        autosize();
        const r = await act('send', { text });
        if (!r) el.input.value = text;
        sending = false;
        el.input.focus();
    }

    // Grow with the text; only show a scrollbar once it hits the max height
    function autosize() {
        const t = el.input;
        t.style.height = 'auto';
        const full = t.scrollHeight + (t.offsetHeight - t.clientHeight);
        t.style.height = `${Math.min(full, 150)}px`;
        t.style.overflowY = full > 150 ? 'auto' : 'hidden';
    }
    el.input.addEventListener('input', () => {
        autosize();
        const now = Date.now();
        if (el.input.value.trim() && now - lastTyping > 2500) {
            lastTyping = now;
            api('typing', { method: 'POST' }).catch(() => {});
        }
    });
    // Hide the tab bar while the phone keyboard is up
    el.input.addEventListener('focus', () => { if (!isDesktop()) app.classList.add('kb'); setTimeout(() => scrollChatToBottom(), 250); });
    el.input.addEventListener('blur', () => app.classList.remove('kb'));

    /* ---------- tabs ---------- */

    function setTab(tab) {
        // on desktop the chat is always on screen, so "go to chat" leaves the left side alone
        if (isDesktop() && tab === 'chat') { setUnread(0); return; }
        app.dataset.tab = tab;
        document.querySelectorAll('.tab').forEach((t) => t.setAttribute('aria-selected', String(t.dataset.tab === tab)));
        if (tab === 'chat') {
            setUnread(0);
            requestAnimationFrame(() => scrollChatToBottom());
        }
    }
    document.querySelectorAll('.tab').forEach((t) => t.addEventListener('click', () => setTab(t.dataset.tab)));
    mqDesktop.addEventListener('change', () => { setUnread(isDesktop() ? 0 : unread); render(); });

    /* ---------- questions tab (write your own) ---------- */

    const bank = { qv: null, loaded: false, loading: false, mine: [], counts: {}, type: 'choice' };
    const qb = {
        form: $('#qbForm'), text: $('#qbText'), choice: $('#qbChoice'), openNote: $('#qbOpenNote'),
        options: $('#qbOptions'), addOpt: $('#qbAddOpt'), save: $('#qbSave'),
        counts: $('#qbCounts'), play: $('#qbPlay'), list: $('#qbList'), mineCount: $('#qbMineCount'),
    };
    const MAX_OPTS = 6;

    function qbAddOption(value = '') {
        if (qb.options.children.length >= MAX_OPTS) return;
        const input = h('input', { class: 'qb-opt-input', type: 'text', maxlength: 120, 'aria-label': 'Answer' });
        input.value = value;
        const row = h('div', { class: 'qb-opt' },
            h('span', { class: 'opt-letter' }),
            input,
            h('button', { class: 'qb-opt-remove', type: 'button', 'aria-label': 'Remove answer', text: '✕', onclick: () => { row.remove(); qbSyncOptions(); } }));
        qb.options.append(row);
        qbSyncOptions();
        return input;
    }
    function qbSyncOptions() {
        const rows = [...qb.options.children];
        rows.forEach((r, i) => {
            r.querySelector('.opt-letter').textContent = LETTERS[i];
            r.querySelector('input').placeholder = `Answer ${LETTERS[i]}`;
            r.querySelector('.qb-opt-remove').disabled = rows.length <= 2;
        });
        qb.addOpt.hidden = rows.length >= MAX_OPTS;
    }
    function qbResetForm() {
        qb.text.value = '';
        qb.options.replaceChildren();
        for (let i = 0; i < 3; i++) qbAddOption();
    }
    function qbSetType(type) {
        bank.type = type;
        document.querySelectorAll('.seg-btn').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.type === type)));
        qb.choice.hidden = type !== 'choice';
        qb.openNote.hidden = type === 'choice';
    }
    document.querySelectorAll('.seg-btn').forEach((b) => b.addEventListener('click', () => qbSetType(b.dataset.type)));
    qb.addOpt.addEventListener('click', () => { const i = qbAddOption(); if (i) i.focus(); });

    qb.form.addEventListener('submit', async (e) => {
        e.preventDefault();
        const body = {
            type: bank.type,
            text: qb.text.value,
            options: [...qb.options.querySelectorAll('input')].map((i) => i.value),
        };
        qb.save.disabled = true;
        const r = await act('add_question', body);
        qb.save.disabled = false;
        if (!r) return;
        toast(`Saved 💌 ${P[THEM].name} won't see it until it comes up in a game`, 'love');
        qbResetForm();
        loadBank();
    });

    async function loadBank() {
        if (bank.loading) return;
        bank.loading = true;
        try {
            const r = await api('questions');
            const before = bank.counts[THEM];
            bank.mine = r.mine;
            bank.counts = r.counts;
            bank.qv = r.qv;
            if (bank.loaded && before != null && r.counts[THEM] > before) {
                toast(`${P[THEM].name} wrote a new question for you 🔒`, 'love');
            }
            bank.loaded = true;
            renderBank();
        } catch { /* try again on the next change */ }
        bank.loading = false;
    }

    function renderBank() {
        const ids = Object.keys(P);
        qb.counts.replaceChildren(...ids.map((pid) => h('span', { class: 'qb-count', style: `--pc:${P[pid].color}` },
            h('i', { class: 'pdot on' }),
            pid === ME ? `You wrote ${bank.counts[pid] || 0}` : `${P[pid].name} wrote ${bank.counts[pid] || 0} 🔒`)));

        qb.mineCount.textContent = bank.mine.length ? `(${bank.mine.length})` : '';
        qb.list.replaceChildren(...(bank.mine.length ? bank.mine.slice().reverse().map((c) => {
            const del = h('button', { class: 'qb-del', type: 'button', text: 'Delete' });
            del.addEventListener('click', () => confirmTap(del, 'Sure?', async () => {
                if (await act('delete_question', { id: c.id })) loadBank();
            }));
            return h('div', { class: 'qb-item' },
                h('div', { class: 'qb-item-top' },
                    h('span', { class: 'q-type', text: c.type === 'choice' ? 'Multiple choice' : 'Write it' }), del),
                h('div', { class: 'qb-item-q', text: c.q }),
                c.type === 'choice' ? h('div', { class: 'qb-chips' },
                    ...c.options.map((o, i) => h('span', { class: 'qb-chip' }, h('b', { text: LETTERS[i] }), o)),
                    h('span', { class: 'qb-chip custom' }, h('b', { text: LETTERS[c.options.length] }), 'Custom')) : null);
        }) : [h('p', { class: 'qb-empty', text: "You haven't written any questions yet. Your first one is waiting ✨" })]));

        renderPlay();
    }

    let playKey = '';
    function renderPlay() {
        const ours = Object.values(bank.counts).reduce((a, b) => a + b, 0);
        const cur = S ? S.deck : null;
        const key = `${ours}|${cur}|${S ? S.game : 0}`;
        if (key === playKey) return;
        playKey = key;
        const deck = (mode, icon, title, sub, disabled) => {
            const b = h('button', { class: `deck-btn${cur === mode ? ' current' : ''}`, type: 'button', disabled },
                h('span', { class: 'deck-icon', text: icon }),
                h('span', { class: 'deck-body' }, h('b', { text: title }), h('small', { text: sub })),
                cur === mode ? h('span', { class: 'deck-now', text: 'now' }) : null);
            b.addEventListener('click', () => confirmTap(b, 'Tap again to start — this ends the current game', async () => {
                if (await act('reset', { deck: mode })) {
                    setTab('game');
                    toast('New game ready 💞 Roll the dice!', 'love');
                }
            }));
            return b;
        };
        const fill = Math.max(0, 100 - ours);
        qb.play.replaceChildren(
            deck('mix', '🔀', 'Mix & shuffle', ours ? `Your ${ours} question${ours === 1 ? '' : 's'} + ${fill} classics, all shuffled` : 'Write some questions first', !ours),
            deck('ours', '💞', 'Only ours', ours ? `Just the ${ours} question${ours === 1 ? '' : 's'} you both wrote, shuffled` : 'Write some questions first', !ours),
            deck('classic', '📖', 'Classic 100', 'The original questions, in order', false),
            h('p', { class: 'qb-note', text: 'Starting a new game clears the hearts. Your questions and the chat are kept.' }));
    }

    function goToPlay() {
        setTab('questions');
        requestAnimationFrame(() => qb.play.scrollIntoView({ behavior: reducedMotion ? 'auto' : 'smooth', block: 'center' }));
    }

    qbResetForm();
    qbSetType('choice');

    /* ---------- dice ---------- */

    const PIPS = { 1: [4], 2: [0, 8], 3: [0, 4, 8], 4: [0, 2, 6, 8], 5: [0, 2, 4, 6, 8], 6: [0, 2, 3, 5, 6, 8] };
    function setDie(die, n) {
        die.replaceChildren(...Array.from({ length: 9 }, (_, i) => h('i', { class: PIPS[n].includes(i) ? 'on' : '' })));
    }

    let diceTimers = [];
    function showDice(roll) {
        const age = (Date.now() + serverSkew) / 1000 - roll.at;
        const key = `hearts_roll_${roll.at}`;
        if (age > 25 || session.has(key)) return;
        session.add(key);

        diceBusy = true;
        const ids = Object.keys(P);
        const dice = {};
        el.diceRow.replaceChildren(...ids.map((pid) => {
            dice[pid] = h('div', { class: 'die rolling' });
            setDie(dice[pid], 1 + Math.floor(Math.random() * 6));
            return h('div', { class: 'die-wrap' }, dice[pid], h('b', { text: pid === ME ? 'You' : P[pid].name }));
        }));
        el.diceResult.innerHTML = '&nbsp;';
        el.dice.hidden = false;

        const spin = setInterval(() => ids.forEach((pid) => setDie(dice[pid], 1 + Math.floor(Math.random() * 6))), 95);
        diceTimers.push(setTimeout(() => {
            clearInterval(spin);
            ids.forEach((pid) => {
                setDie(dice[pid], roll.dice[pid]);
                dice[pid].classList.remove('rolling');
                dice[pid].parentElement.classList.add(pid === roll.starter ? 'winner' : 'loser');
            });
            el.diceResult.textContent = roll.starter === ME ? 'You pick first! 💗' : `${P[roll.starter].name} picks first 💗`;
            burst(innerWidth / 2, innerHeight / 2 - 40, 18);
            chime('turn');
        }, 1900));
        diceTimers.push(spin);
        diceTimers.push(setTimeout(closeDice, 4800));
    }
    function closeDice() {
        diceTimers.forEach((t) => { clearTimeout(t); clearInterval(t); });
        diceTimers = [];
        el.dice.hidden = true;
        diceBusy = false;
    }
    el.dice.addEventListener('click', () => { if (el.diceResult.textContent.trim()) closeDice(); });

    /* ---------- feedback: toasts, sound, title, hearts burst ---------- */

    function toast(text, type = '', action = null) {
        const t = h('div', { class: `toast ${type}`, role: 'status' }, h('span', { text }),
            action ? h('button', { class: 'btn btn-primary btn-sm', type: 'button', onclick: () => { action.fn(); t.remove(); } }, action.label) : null);
        el.toasts.append(t);
        while (el.toasts.children.length > 3) el.toasts.firstChild.remove();
        setTimeout(() => { t.classList.add('out'); setTimeout(() => t.remove(), 320); }, action ? 5000 : 3200);
    }

    function notify(text, sound, action) {
        toast(text, 'love', action);
        chime(sound);
        if (document.hidden) flashTitle(text);
    }

    let soundOn = store.get('hearts_sound', '1') === '1';
    let audio = null;
    // Browsers block sound and vibration until the person has tapped the page once;
    // trying earlier only fills the console with errors.
    const userTapped = () => !navigator.userActivation || navigator.userActivation.hasBeenActive;

    function chime(kind) {
        if (!soundOn || !kind || !userTapped()) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
            const notes = kind === 'turn' ? [659.3, 880, 1046.5] : kind === 'open' ? [523.3, 784] : [880];
            notes.forEach((f, i) => {
                const o = audio.createOscillator();
                const g = audio.createGain();
                const t = audio.currentTime + i * 0.11;
                o.type = 'sine';
                o.frequency.value = f;
                g.gain.setValueAtTime(0, t);
                g.gain.linearRampToValueAtTime(0.13, t + 0.02);
                g.gain.exponentialRampToValueAtTime(0.0001, t + 0.6);
                o.connect(g).connect(audio.destination);
                o.start(t);
                o.stop(t + 0.65);
            });
        } catch { /* audio not available */ }
    }
    // Browsers only allow sound after the first tap
    document.addEventListener('pointerdown', () => {
        if (!soundOn) return;
        try {
            audio = audio || new (window.AudioContext || window.webkitAudioContext)();
            if (audio.state === 'suspended') audio.resume();
        } catch { /* ignore */ }
    }, { passive: true });

    const baseTitle = document.title;
    function flashTitle(text) { document.title = text; }
    function restoreTitle() { document.title = baseTitle; }

    function burst(x = innerWidth / 2, y = innerHeight / 2, n = 16) {
        if (reducedMotion) return;
        const glyphs = ['💗', '💖', '💕', '❤️', '✨'];
        for (let i = 0; i < n; i++) {
            const s = h('span', { class: 'float-heart', text: glyphs[i % glyphs.length] });
            const angle = Math.random() * Math.PI * 2;
            const dist = 90 + Math.random() * 180;
            s.style.left = `${x}px`;
            s.style.top = `${y}px`;
            s.style.fontSize = `${16 + Math.random() * 18}px`;
            s.style.setProperty('--dx', `${Math.cos(angle) * dist}px`);
            s.style.setProperty('--dy', `${Math.sin(angle) * dist - 80}px`);
            s.style.setProperty('--r', `${Math.random() * 80 - 40}deg`);
            s.style.setProperty('--d', `${0.9 + Math.random() * 0.8}s`);
            document.body.append(s);
            setTimeout(() => s.remove(), 1900);
        }
    }

    function confirmTap(btn, armedLabel, fn) {
        if (btn.dataset.armed) {
            delete btn.dataset.armed;
            btn.classList.remove('armed');
            btn.innerHTML = btn.dataset.orig;
            fn();
            return;
        }
        btn.dataset.orig = btn.innerHTML;
        btn.dataset.armed = '1';
        btn.classList.add('armed');
        btn.textContent = armedLabel;
        setTimeout(() => {
            if (!btn.dataset.armed) return;
            delete btn.dataset.armed;
            btn.classList.remove('armed');
            btn.innerHTML = btn.dataset.orig;
        }, 3500);
    }

    /* ---------- menu ---------- */

    const soundSwitch = $('#soundSwitch');
    const syncSound = () => soundSwitch.classList.toggle('on', soundOn);
    $('#menuBtn').addEventListener('click', () => {
        $('#menuMe').replaceChildren(avatar(ME, 'lg'),
            h('div', {}, P[ME].name, h('small', { text: `Playing with ${P[THEM].name} 💗` })));
        syncSound();
        openSheet(el.menuSheet);
    });
    $('#soundBtn').addEventListener('click', () => {
        soundOn = !soundOn;
        store.set('hearts_sound', soundOn ? '1' : '0');
        syncSound();
        if (soundOn) chime('msg');
    });
    $('#resetBtn').addEventListener('click', () => { closeSheets(); goToPlay(); });

    /* ---------- start ---------- */

    buildCouple();
    autosize();
    loadBank();
    if (document.fonts) document.fonts.ready.then(() => { if (stuckToBottom) scrollChatToBottom(); });
    el.messages.append(h('div', { class: 'messages-empty' }, h('b', { text: '💌' }), 'No messages yet. Say hi!'));
    poll();
})();
