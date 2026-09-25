# 100 Hearts 💗

A private two-player question game with a live chat. Plain PHP + SQLite, no framework and no build step.

## 1. Set it up (do this first)

Edit `config.php`:

- `name`: the name shown for each player
- `code`: the login code you give each person. Make them hard to guess.
- `color`: each player's color (used for their avatar and the hearts they answered)
- `secret`: any long random string

Edit `questions.php` to change the 100 questions. There are two question types:

- `choice`: multiple choice. The answer is picked from options. A **Custom** option is added automatically as the last choice, so you can write your own answer instead.
- `open`: a write-it question. The game moves to the chat, where the question is pinned, and the answer is typed there.

## 2. Requirements

- PHP 8.0 or newer with `pdo_sqlite` (it comes with PHP on most systems: `php -m | grep sqlite`)
- nginx or Apache
- HTTPS is strongly recommended (free with certbot)

## 3. Quick hosting with `host.sh` (no nginx needed)

Upload the folder to the VPS, then run:

```bash
cd DA-GAME
chmod +x host.sh
./host.sh            # installs PHP if needed, starts the game on port 2027
```

The game is then at `http://YOUR-VPS-IP:2027/`. It starts on boot and restarts if it crashes. `router.php` keeps `data/`, `inc/` and `config.php` private.

Other commands: `./host.sh status`, `./host.sh logs`, `./host.sh restart` (after editing files), `./host.sh stop`, and `./host.sh uninstall`. To use a different port: `PORT=2028 ./host.sh`.

If the page doesn't open, allow TCP port 2027 in your VPS provider's firewall panel as well.

## 3b. Deploy with nginx instead (Ubuntu example)

```bash
sudo apt install php-fpm php-sqlite3            # if not installed yet
sudo mkdir -p /var/www/hearts
# upload all files into /var/www/hearts (scp, rsync, git…)
sudo chown -R www-data:www-data /var/www/hearts/data
sudo chmod 775 /var/www/hearts/data
```

nginx site (`/etc/nginx/sites-available/hearts`):

```nginx
server {
    server_name hearts.example.com;
    root /var/www/hearts;
    index index.php;

    # keep the database and internals private
    location ~ ^/(data|inc)/ { deny all; return 404; }
    location ~ ^/(config|questions)\.php$ { deny all; return 404; }
    location ~ \.(sqlite|md)$ { deny all; return 404; }

    location / { try_files $uri $uri/ /index.php; }

    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;   # match your PHP version
    }
}
```

```bash
sudo ln -s /etc/nginx/sites-available/hearts /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
sudo certbot --nginx -d hearts.example.com
```

On **Apache**, the included `.htaccess` files already block the private files.

Even safer: set `data_dir` in `config.php` to a folder outside the web root, such as `/var/lib/hearts`, and make it owned by `www-data`.

## 4. Play

1. Open the site on both phones and enter your codes. Each phone stays logged in for a year.
2. When you're both online, either of you taps **Roll the dice** to decide who picks first.
3. On your turn, tap a heart:
   - **Multiple choice:** you tap your answer. The other person sees your selection live. Tap **Finish** to pass the turn.
   - **Write it:** the question is pinned in the chat. Write your answer there. The other person can reply with their thoughts. Tap **Finish** to pass the turn.
4. **Questions tab:** each of you can write your own questions, either multiple choice with your suggested answers or write-it. Your partner's questions stay hidden until they come up in a game. Under **Play them** you can start a new game with **Mix & shuffle** (your questions plus classics, 100 hearts), **Only ours**, or **Classic 100**. Your questions are saved, so they're kept for every future game.
5. Opened hearts take the answerer's color. Tap an opened heart to read that answer again.
6. The **⋯** menu has sounds on/off, **Start a new game** (opens the Play options), and **Log out**.

Tip: on the phone, use "Add to Home Screen" so it opens like an app.

## How "live" works

Each open page checks the server about once a second and only downloads what changed. For two players this feels instant. It also needs no extra WebSocket daemon, so it runs on any normal PHP host. A player shows as **online** while their page is open, **away** while the tab is in the background, and **offline** after about 8 seconds without contact.

## Files

| File | Purpose |
|---|---|
| `config.php` | names, codes, colors, secret |
| `questions.php` | the 100 questions |
| `index.php` | login page and app page |
| `api.php` | game rules and chat (JSON) |
| `inc/` | shared PHP and HTML views |
| `assets/` | CSS and JavaScript |
| `data/` | the SQLite database (created automatically) |

To wipe everything, including the chat, delete `data/game.sqlite*`.
