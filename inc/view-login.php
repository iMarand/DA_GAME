<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1c0c18">
    <title><?= e(cfg('title')) ?></title>
    <link rel="icon" href="data:image/svg+xml,<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 100 100'><text y='.9em' font-size='90'>💗</text></svg>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:ital,opsz,wght@0,9..144,500;0,9..144,600;1,9..144,500&family=Nunito:wght@400;600;700;800&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= asset('assets/app.css') ?>">
</head>
<body class="login-page">
    <div class="floaters" aria-hidden="true">
        <?php for ($i = 0; $i < 16; $i++): ?>
            <span style="--x:<?= random_int(0, 100) ?>%;--s:<?= random_int(10, 34) ?>px;--d:<?= random_int(9, 20) ?>s;--delay:-<?= random_int(0, 20) ?>s">♥</span>
        <?php endfor; ?>
    </div>

    <main class="login">
        <form class="login-card" method="post" autocomplete="off">
            <div class="login-logo" aria-hidden="true">
                <svg viewBox="0 0 24 24"><defs><linearGradient id="lg" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#ff86a6"/><stop offset="1" stop-color="#e3124c"/></linearGradient></defs>
                    <path fill="url(#lg)" d="M12 21.35l-1.45-1.32C5.4 15.36 2 12.28 2 8.5 2 5.42 4.42 3 7.5 3c1.74 0 3.41.81 4.5 2.09C13.09 3.81 14.76 3 16.5 3 19.58 3 22 5.42 22 8.5c0 3.78-3.4 6.86-8.55 11.54L12 21.35z"/></svg>
                <span>100</span>
            </div>
            <h1><?= e(cfg('title')) ?></h1>
            <!-- <p class="login-sub">A little game made for just the two of us.</p> -->

            <!-- <label class="field-label" for="code">Your secret code</label> -->
            <input class="field" id="code" name="code" type="password" required autofocus
                   autocapitalize="off" autocorrect="off" spellcheck="false" placeholder="Type your code">

            <button class="btn btn-primary btn-block" type="submit">Open my heart 💗</button>

            <?php if ($error): ?>
                <p class="login-error" role="alert"><?= e($error) ?></p>
            <?php endif; ?>
        </form>
    </main>
</body>
</html>
