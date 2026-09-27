<?php
/*
 * The 100 hearts = 80 deep questions + 20 Truth-or-Dare hearts.
 *
 * Question types:
 *   ['type' => 'choice', 'q' => 'Question?', 'options' => ['A', 'B', 'C']]   // a "Custom" option is added automatically
 *   ['type' => 'open',   'q' => 'Question answered in the chat']
 *
 * Truth or Dare: whoever opens the heart first chooses Truth or Dare.
 *   Truth = a question (choice or open), Dare = something to do, then tap "I did it".
 *   ['truth' => ['type' => 'open', 'q' => '…'], 'dare' => '…']
 *
 * "Me" / "You" options are from the point of view of whoever is answering.
 * Edit freely — the game shows as many hearts as there are entries.
 */

$deep = [
    // ── who we are with each other ─────────────────────────────
    ['type' => 'choice', 'q' => 'Are you truly comfortable with me — 100% yourself, no filter?', 'options' => ['Completely, always', 'Mostly, a few walls left', "Getting there, slowly", 'Still a little shy with you']],
    ['type' => 'open',   'q' => 'If I died today, what would you wish you had told me?'],
    ['type' => 'choice', 'q' => 'Between money and love — if you could only have one?', 'options' => ['Love, always', 'Money — love needs a stable base', 'Both or nothing', "Honestly, it depends"]],
    ['type' => 'choice', 'q' => 'Hug or kiss?', 'options' => ['A long, tight hug', 'A slow kiss', 'A forehead kiss', 'A hug that turns into a kiss']],
    ['type' => 'open',   'q' => 'What is your very first childhood memory?'],
    ['type' => 'choice', 'q' => 'When I hurt you, what do you usually do?', 'options' => ['Tell you right away', 'Go quiet', "Pretend I'm fine", 'Cry alone, then talk']],
    ['type' => 'open',   'q' => "What is one thing from your past you're still healing from?"],
    ['type' => 'choice', 'q' => 'What scares you most about our future?', 'options' => ['Distance', 'Feelings fading', 'Family pressure', 'Money problems', 'Nothing — not with you']],
    ['type' => 'open',   'q' => 'Describe the version of you that only I get to see.'],
    ['type' => 'choice', 'q' => 'Would you forgive me if I lied to protect your feelings?', 'options' => ["Yes, I'd understand", 'Only once', 'No — I prefer a painful truth', 'Depends on the lie']],

    ['type' => 'open',   'q' => 'What was your first impression of me — the honest version?'],
    ['type' => 'choice', 'q' => 'What makes you feel the most loved?', 'options' => ['Being told', 'Being touched', 'Being chosen in public', 'Being remembered in small things']],
    ['type' => 'open',   'q' => 'Which memory of us do you replay in your head the most?'],
    ['type' => 'choice', 'q' => 'If I had to move to another country for two years…', 'options' => ["I'd come with you", "Long distance — we'd make it", "I'd ask you to stay", "I honestly don't know"]],
    ['type' => 'open',   'q' => 'Who were you before you met me, and how are you different now?'],
    ['type' => 'choice', 'q' => 'At 2am: cuddle or deep talk?', 'options' => ['Cuddle', 'Deep talk', 'Deep talk while cuddling', 'Sleep 😴']],
    ['type' => 'open',   'q' => "What did your parents' relationship teach you about love — good or bad?"],
    ['type' => 'choice', 'q' => 'How often do you think about our future?', 'options' => ['Every single day', 'Sometimes', 'Only when we talk about it', 'I live in the moment']],
    ['type' => 'open',   'q' => 'What is something you have never forgiven yourself for?'],
    ['type' => 'choice', 'q' => 'Which would hurt you more?', 'options' => ['Being ignored', 'Being lied to', 'Being compared', 'Being forgotten']],

    // ── the past ──────────────────────────────────────────────
    ['type' => 'open',   'q' => 'Tell me about a time in your life when you felt really alone.'],
    ['type' => 'choice', 'q' => 'After a big fight, who should text first?', 'options' => ['Me', 'You', 'Whoever was wrong', 'Whoever misses more']],
    ['type' => 'open',   'q' => "What small thing do I do that I don't know means a lot to you?"],
    ['type' => 'choice', 'q' => 'Rich but apart, or a simple life together?', 'options' => ['A simple life together', 'Rich first, then reunite', "We'll find a way to have both", "Don't make me choose…"]],
    ['type' => 'open',   'q' => 'If you could go back and change one moment of your past, which one?'],
    ['type' => 'choice', 'q' => 'Do you believe in soulmates?', 'options' => ['Yes — and I found mine', "Yes, but you build it together", 'Not really', "I'm starting to"]],
    ['type' => 'open',   'q' => "What does feeling 'safe' with someone feel like to you?"],
    ['type' => 'choice', 'q' => 'What do you want more of from me?', 'options' => ['Time', 'Words', 'Touch', 'Surprises', 'Patience']],
    ['type' => 'open',   'q' => 'Describe your perfect day with me — from waking up to falling asleep.'],
    ['type' => 'choice', 'q' => 'Which kiss means the most?', 'options' => ['On the cheek', 'On the forehead', 'On the hand', 'On the lips']],

    ['type' => 'open',   'q' => 'What was the hardest period of your life, and who helped you through it?'],
    ['type' => 'choice', 'q' => 'Would you tell me if you started losing feelings?', 'options' => ['Immediately', "After trying to fix it first", "I'd be scared to", "You'd see it before I said it"]],
    ['type' => 'open',   'q' => 'What is a dream you gave up on — and would you pick it up again with me?'],
    ['type' => 'choice', 'q' => 'Is jealousy a sign of love?', 'options' => ['A little is cute', "Only if it's controlled", 'No — trust is love', 'Sometimes']],
    ['type' => 'open',   'q' => 'What do you want our home to feel like when we walk in?'],
    ['type' => 'choice', 'q' => 'Your house is on fire (and I am safe). What do you grab first?', 'options' => ['My phone', 'Photos & memories', 'Important documents', 'Nothing — just get out']],
    ['type' => 'open',   'q' => 'Tell me a family memory that always makes you smile.'],
    ['type' => 'choice', 'q' => 'Marriage — when do you imagine it?', 'options' => ['Soon', 'In a few years', "When we're financially ready", "I'm not sure yet"]],
    ['type' => 'open',   'q' => 'What is a fear you have never said out loud?'],
    ['type' => 'choice', 'q' => 'When things get hard between us, you…', 'options' => ['Fight for us', 'Need space first', 'Overthink everything', 'Hold on even tighter']],

    // ── love, trust & the future ──────────────────────────────
    ['type' => 'open',   'q' => 'If I lost everything tomorrow — job, money, looks — what would you still love about me?'],
    ['type' => 'choice', 'q' => 'Do you want to know my whole past?', 'options' => ['Everything', 'Only what you want to share', 'Only what affects us', 'No — the past is past']],
    ['type' => 'open',   'q' => 'What have you learned about yourself because of me?'],
    ['type' => 'choice', 'q' => 'How do you want me to apologize?', 'options' => ['Clear words', 'A long hug', 'Changed actions', 'A small gesture or gift']],
    ['type' => 'open',   'q' => 'What is the most romantic thing you have ever imagined us doing?'],
    ['type' => 'choice', 'q' => 'Who do you talk to when we have problems?', 'options' => ['Nobody — only you', 'A close friend', 'Family', 'I keep it inside']],
    ['type' => 'open',   'q' => 'What were you like as a teenager? Tell me a story.'],
    ['type' => 'choice', 'q' => 'If you could spend one hour inside my head, what would you look for?', 'options' => ['What you really think of me', 'Your fears', 'Your memories', 'Your dreams']],
    ['type' => 'open',   'q' => "What does 'forever' mean to you?"],
    ['type' => 'choice', 'q' => 'What do you miss most when we are apart?', 'options' => ['Your voice', 'Your touch', 'Your smell', 'Your laugh']],

    ['type' => 'open',   'q' => 'Tell me about your first heartbreak.'],
    ['type' => 'choice', 'q' => 'What must never happen in our relationship?', 'options' => ['Cheating', 'Lying', 'Disrespect', 'Giving up']],
    ['type' => 'open',   'q' => 'If our love story were a book, what would this chapter be called — and why?'],
    ['type' => 'choice', 'q' => 'Holding hands in public?', 'options' => ['Always!', 'Sometimes', "I'm shy about it", 'Only if you start']],
    ['type' => 'open',   'q' => "What is one habit of mine you've secretly grown to love?"],
    ['type' => 'choice', 'q' => 'If I became very sick for a long time, would you stay?', 'options' => ['Without question', "Yes, even if it's hard", "I'd try my very best", "I'm scared to even imagine it"]],
    ['type' => 'open',   'q' => 'What do you wish people understood about you?'],
    ['type' => 'choice', 'q' => 'What matters most in a partner?', 'options' => ['Loyalty', 'Kindness', 'Ambition', 'Humor']],
    ['type' => 'open',   'q' => 'Describe the exact moment you knew you loved me.'],
    ['type' => 'choice', 'q' => 'How many kids do you dream of one day?', 'options' => ['None', 'One or two', 'Three or more', "Let's talk about it"]],

    ['type' => 'open',   'q' => 'What promise do you want us to make to each other tonight?'],
    ['type' => 'choice', 'q' => 'When are you most yourself with me?', 'options' => ['Late-night calls', 'Face to face', 'Over text', 'In comfortable silence']],
    ['type' => 'open',   'q' => 'What is the kindest thing anyone has ever done for you?'],
    ['type' => 'choice', 'q' => 'Knowing everything you know now, would you choose me again?', 'options' => ['A thousand times', 'Yes — and even faster', "Yes, but I'd do some things differently", 'Ask me again tomorrow 😏']],
    ['type' => 'open',   'q' => 'What is something from your past I should know, so I understand you better?'],
    ['type' => 'choice', 'q' => 'Morning kisses or good-night kisses?', 'options' => ['Morning', 'Good night', 'Both, obviously', 'Random ones all day']],
    ['type' => 'open',   'q' => 'If you ever push me away, what do you want me to do?'],
    ['type' => 'choice', 'q' => "If we're upset with each other at bedtime…", 'options' => ['We fix it before sleeping', 'Sleep, talk tomorrow', 'Silent treatment', 'Cuddle anyway']],
    ['type' => 'open',   'q' => 'Write me the message you want me to read on my worst day.'],
    ['type' => 'choice', 'q' => 'Big career chance vs. more time with me?', 'options' => ['Time with you', 'Career first, then us', 'A careful balance', "You'd push me to take it"]],

    ['type' => 'open',   'q' => 'What is one thing you want us to stop doing — and one thing to start?'],
    ['type' => 'choice', 'q' => 'Our biggest strength as a couple?', 'options' => ['Communication', 'Laughter', 'Loyalty', 'Chemistry']],
    ['type' => 'open',   'q' => 'Tell me about a moment you felt really proud of us.'],
    ['type' => 'choice', 'q' => 'How do you feel after we talk every day?', 'options' => ['Lighter', 'Happier', 'Safe', 'Addicted 😅']],
    ['type' => 'open',   'q' => 'What would you tell your younger self about love?'],
    ['type' => 'choice', 'q' => 'Will we still be this silly at 70?', 'options' => ['Even sillier', 'Yes!', 'A bit calmer', "We'll be the old couple everyone envies"]],
    ['type' => 'open',   'q' => 'What does my love give you that you never had before?'],
    ['type' => 'choice', 'q' => 'Which would you rather hear from me right now?', 'options' => ["I'm proud of you", 'I miss you', 'I choose you', "You're safe with me"]],
    ['type' => 'open',   'q' => 'If today were our last day together, how would you spend it?'],
    ['type' => 'open',   'q' => 'Tell me something you want me to remember forever.'],
];

$truthOrDare = [
    ['truth' => ['type' => 'open', 'q' => "What is a secret you've never told me?"],
     'dare'  => 'Send a voice note singing the chorus of our song (or any love song) 🎤'],
    ['truth' => ['type' => 'choice', 'q' => 'Have you ever secretly checked my phone or social media?', 'options' => ['Never', 'Once or twice', "More than I'd admit", 'I wanted to, but didn\'t']],
     'dare'  => 'Send the 7th photo in your gallery — no skipping! 📸'],
    ['truth' => ['type' => 'open', 'q' => 'What is the most embarrassing thing you did to impress me?'],
     'dare'  => "Call me right now and say 'I love you' in three different languages."],
    ['truth' => ['type' => 'choice', 'q' => 'Have you ever been jealous of one of my friends?', 'options' => ['Yes', 'No', 'A little', "I'm not saying 🙈"]],
     'dare'  => 'Set a photo of us as your phone wallpaper for the next 24 hours.'],
    ['truth' => ['type' => 'open', 'q' => 'What is the first physical thing you noticed about me?'],
     'dare'  => 'Write a 4-line love poem about me, right now, in the chat ✍️'],
    ['truth' => ['type' => 'choice', 'q' => 'Have you ever cried because of me?', 'options' => ['Yes — happy tears', 'Yes — sad tears', 'Both', 'Never']],
     'dare'  => 'Send me a selfie with your funniest face 🤪'],
    ['truth' => ['type' => 'open', 'q' => 'What is one thing you pretend to like only because I like it?'],
     'dare'  => 'Do your best impression of me in a voice note.'],
    ['truth' => ['type' => 'choice', 'q' => 'Who fell in love first?', 'options' => ['Me', 'You', 'At the same time', "I'm still falling"]],
     'dare'  => 'Tell me 5 things you love about me in under 30 seconds (voice note) ⏱️'],
    ['truth' => ['type' => 'open', 'q' => 'When did you last lie to me — and about what?'],
     'dare'  => 'Let me choose your profile picture for the next 24 hours.'],
    ['truth' => ['type' => 'choice', 'q' => 'Since we started, have you ever thought about someone else?', 'options' => ['Never', 'Only in passing', 'Honestly, once', 'Pass 🙊']],
     'dare'  => 'Send a voice note whispering the sweetest thing you can think of 🤫'],
    ['truth' => ['type' => 'open', 'q' => 'What is your biggest insecurity in our relationship?'],
     'dare'  => 'Dance to the next song on your playlist — send a video, or describe every move!'],
    ['truth' => ['type' => 'choice', 'q' => 'What is your guilty pleasure?', 'options' => ['Junk food', "Checking exes' profiles", 'Trashy TV shows', 'Sleeping all day']],
     'dare'  => 'Draw me in 60 seconds and send the masterpiece 🎨'],
    ['truth' => ['type' => 'open', 'q' => 'If you could change one thing about me, what would it be?'],
     'dare'  => 'Post a status/story about me — your words, your choice.'],
    ['truth' => ['type' => 'choice', 'q' => 'How many times a day do you think about me?', 'options' => ['Once or twice', '10+', 'Constantly', 'Only when you text 😅']],
     'dare'  => 'Send your last 3 emojis used and explain each one.'],
    ['truth' => ['type' => 'open', 'q' => 'Tell me about a moment you wanted to kiss me but didn\'t.'],
     'dare'  => 'Record a 20-second video telling me why you chose me 🎥'],
    ['truth' => ['type' => 'choice', 'q' => 'Have you ever scrolled all the way back through my old photos?', 'options' => ['Yes, to the very beginning', 'A little', 'Never', "I'm doing it right now 👀"]],
     'dare'  => 'Plan our next date in the chat: place, time, outfit — everything.'],
    ['truth' => ['type' => 'open', 'q' => "What's the most daring thought you've had about me? (keep it PG 😏)"],
     'dare'  => 'Call me and give me a 1-minute compliment without laughing.'],
    ['truth' => ['type' => 'choice', 'q' => 'If I read your chats with your best friend about me, I would feel…', 'options' => ['Flattered', 'Embarrassed for you', 'Shocked 😳', 'Nothing to see here']],
     'dare'  => 'Send me a photo of what you are wearing right now.'],
    ['truth' => ['type' => 'open', 'q' => "What is one question you've never had the courage to ask me?"],
     'dare'  => "For the next 3 turns, end every message with 'my love' 💗"],
    ['truth' => ['type' => 'open', 'q' => 'What do you think I am most afraid of — honestly?'],
     'dare'  => 'Send me a voice note of your best romantic movie line 🎬'],
];

// Heart numbers where the Truth-or-Dare hearts hide (scattered so they stay a surprise).
$truthOrDareHearts = [4, 9, 13, 18, 22, 27, 31, 36, 42, 47, 51, 56, 62, 67, 71, 76, 82, 87, 93, 98];

$hearts = [];
$tod = $truthOrDare;
$regular = $deep;
$total = count($deep) + count($truthOrDare);
for ($n = 1; $n <= $total; $n++) {
    $hearts[] = (in_array($n, $truthOrDareHearts, true) && $tod) || !$regular
        ? ['type' => 'tod'] + array_shift($tod)
        : array_shift($regular);
}
return $hearts;
