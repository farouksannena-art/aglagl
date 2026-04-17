<?php
session_start();
$loggedIn = isset($_SESSION['user_id']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>FitPlanner — Train Smarter</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Bebas+Neue&family=DM+Sans:ital,wght@0,300;0,400;0,500;0,600;1,300&display=swap" rel="stylesheet">
    <style>
        :root {
            --black:   #0a0a0a;
            --dark:    #111114;
            --panel:   #16161a;
            --border:  #1e1e24;
            --muted:   #3a3a44;
            --text:    #e8e8ec;
            --soft:    #8888a0;
            --accent:  #4f8ef7;
            --accent2: #7b5ea7;
            --green:   #3ecf8e;
            --orange:  #f97316;
        }
        *, *::before, *::after { margin: 0; padding: 0; box-sizing: border-box; }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'DM Sans', sans-serif;
            background: var(--black);
            color: var(--text);
            overflow-x: hidden;
        }

        /* ── NOISE OVERLAY ── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: url("data:image/svg+xml,%3Csvg viewBox='0 0 200 200' xmlns='http://www.w3.org/2000/svg'%3E%3Cfilter id='n'%3E%3CfeTurbulence type='fractalNoise' baseFrequency='0.9' numOctaves='4' stitchTiles='stitch'/%3E%3C/filter%3E%3Crect width='100%25' height='100%25' filter='url(%23n)' opacity='0.03'/%3E%3C/svg%3E");
            pointer-events: none;
            z-index: 9999;
        }

        /* ══════════════════════════════
           NAVBAR
        ══════════════════════════════ */
        .nav {
            position: fixed;
            top: 0; left: 0; right: 0;
            z-index: 100;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 18px 48px;
            backdrop-filter: blur(24px);
            -webkit-backdrop-filter: blur(24px);
            border-bottom: 1px solid rgba(255,255,255,0.04);
            background: rgba(10,10,10,0.7);
            transition: background 0.3s;
        }
        .nav-brand {
            display: flex;
            align-items: center;
            gap: 10px;
            text-decoration: none;
        }
        .nav-brand img { height: 36px; }
        .nav-brand-text {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 22px;
            letter-spacing: 2px;
            color: var(--text);
        }
        .nav-brand-text span { color: var(--accent); }
        .nav-links {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .nav-links a {
            color: var(--soft);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            padding: 8px 16px;
            border-radius: 8px;
            transition: all 0.2s;
        }
        .nav-links a:hover { color: var(--text); background: rgba(255,255,255,0.05); }
        .nav-cta {
            background: var(--accent) !important;
            color: #fff !important;
            font-weight: 600 !important;
            padding: 9px 20px !important;
            border-radius: 10px !important;
        }
        .nav-cta:hover { background: #3a7cf0 !important; transform: translateY(-1px); }

        /* ══════════════════════════════
           HERO
        ══════════════════════════════ */
        .hero {
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 120px 24px 80px;
            position: relative;
            overflow: hidden;
        }
        /* Gradient blobs */
        .hero::after {
            content: '';
            position: absolute;
            width: 700px;
            height: 700px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(79,142,247,0.12) 0%, transparent 70%);
            top: -200px; right: -200px;
            pointer-events: none;
        }
        .hero-blob2 {
            position: absolute;
            width: 500px;
            height: 500px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(123,94,167,0.1) 0%, transparent 70%);
            bottom: -100px; left: -100px;
            pointer-events: none;
        }
        .hero-pill {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            background: rgba(79,142,247,0.1);
            border: 1px solid rgba(79,142,247,0.25);
            border-radius: 100px;
            padding: 6px 14px;
            font-size: 12px;
            font-weight: 600;
            color: var(--accent);
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 28px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.6s ease 0.1s forwards;
        }
        .hero-pill .dot {
            width: 6px; height: 6px;
            border-radius: 50%;
            background: var(--accent);
            animation: pulse 1.5s infinite;
        }
        @keyframes pulse {
            0%,100% { opacity:1; transform: scale(1); }
            50% { opacity:0.5; transform: scale(1.4); }
        }
        .hero-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(64px, 10vw, 120px);
            line-height: 0.95;
            text-align: center;
            letter-spacing: 2px;
            color: var(--text);
            max-width: 900px;
            opacity: 0;
            transform: translateY(30px);
            animation: fadeUp 0.7s ease 0.2s forwards;
        }
        .hero-title .highlight {
            background: linear-gradient(135deg, var(--accent) 0%, var(--accent2) 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        .hero-sub {
            max-width: 540px;
            text-align: center;
            color: var(--soft);
            font-size: 17px;
            line-height: 1.7;
            margin-top: 24px;
            font-weight: 400;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.7s ease 0.35s forwards;
        }
        .hero-actions {
            display: flex;
            gap: 14px;
            margin-top: 40px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.7s ease 0.5s forwards;
        }
        .btn-primary {
            padding: 14px 32px;
            background: var(--accent);
            color: #fff;
            border: none;
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-primary:hover { background: #3a7cf0; transform: translateY(-2px); box-shadow: 0 8px 24px rgba(79,142,247,0.3); }
        .btn-ghost {
            padding: 14px 32px;
            background: transparent;
            color: var(--text);
            border: 1px solid var(--border);
            border-radius: 12px;
            font-family: 'DM Sans', sans-serif;
            font-size: 15px;
            font-weight: 500;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-ghost:hover { border-color: var(--muted); background: rgba(255,255,255,0.03); }

        /* Stats row */
        .hero-stats {
            display: flex;
            gap: 48px;
            margin-top: 72px;
            opacity: 0;
            transform: translateY(20px);
            animation: fadeUp 0.7s ease 0.65s forwards;
        }
        .stat { text-align: center; }
        .stat-num {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 40px;
            color: var(--text);
            letter-spacing: 1px;
        }
        .stat-num span { color: var(--accent); }
        .stat-label { font-size: 12px; color: var(--soft); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
        .stat-divider { width: 1px; background: var(--border); }

        @keyframes fadeUp {
            to { opacity: 1; transform: translateY(0); }
        }

        /* ══════════════════════════════
           FEATURES / TOOLS SECTION
        ══════════════════════════════ */
        .section {
            padding: 100px 48px;
            max-width: 1200px;
            margin: 0 auto;
        }
        .section-label {
            font-size: 11px;
            font-weight: 600;
            letter-spacing: 3px;
            text-transform: uppercase;
            color: var(--accent);
            margin-bottom: 16px;
        }
        .section-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: clamp(40px, 5vw, 60px);
            line-height: 1;
            color: var(--text);
            letter-spacing: 1px;
            margin-bottom: 16px;
        }
        .section-sub {
            color: var(--soft);
            font-size: 16px;
            max-width: 480px;
            line-height: 1.7;
        }

        /* Tools grid */
        .tools-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 20px;
            overflow: hidden;
            margin-top: 60px;
        }
        .tool-card {
            background: var(--panel);
            padding: 48px 44px;
            position: relative;
            overflow: hidden;
            cursor: pointer;
            text-decoration: none;
            display: block;
            transition: background 0.25s;
        }
        .tool-card:hover { background: #1a1a20; }
        .tool-card::before {
            content: '';
            position: absolute;
            inset: 0;
            opacity: 0;
            transition: opacity 0.3s;
        }
        .tool-card:nth-child(1)::before { background: radial-gradient(circle at 0 0, rgba(79,142,247,0.07) 0%, transparent 60%); }
        .tool-card:nth-child(2)::before { background: radial-gradient(circle at 100% 0, rgba(62,207,142,0.07) 0%, transparent 60%); }
        .tool-card:hover::before { opacity: 1; }
        .tool-icon {
            width: 52px; height: 52px;
            border-radius: 14px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 22px;
            margin-bottom: 24px;
        }
        .icon-blue { background: rgba(79,142,247,0.12); border: 1px solid rgba(79,142,247,0.2); }
        .icon-green { background: rgba(62,207,142,0.12); border: 1px solid rgba(62,207,142,0.2); }
        .tool-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            padding: 3px 9px;
            border-radius: 100px;
            margin-bottom: 14px;
        }
        .badge-ai { background: rgba(79,142,247,0.15); color: var(--accent); border: 1px solid rgba(79,142,247,0.25); }
        .badge-ml { background: rgba(62,207,142,0.15); color: var(--green); border: 1px solid rgba(62,207,142,0.25); }
        .tool-title {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 32px;
            letter-spacing: 1px;
            color: var(--text);
            margin-bottom: 12px;
        }
        .tool-desc {
            color: var(--soft);
            font-size: 14px;
            line-height: 1.7;
            max-width: 320px;
        }
        .tool-link {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            margin-top: 28px;
            font-size: 13px;
            font-weight: 600;
            color: var(--accent);
            transition: gap 0.2s;
        }
        .tool-card:hover .tool-link { gap: 10px; }
        .tool-link-green { color: var(--green) !important; }
        .tool-number {
            position: absolute;
            top: 44px; right: 44px;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 64px;
            color: rgba(255,255,255,0.03);
            letter-spacing: 2px;
            line-height: 1;
        }

        /* ══════════════════════════════
           HOW IT WORKS
        ══════════════════════════════ */
        .how-section {
            padding: 100px 48px;
            background: var(--panel);
            border-top: 1px solid var(--border);
            border-bottom: 1px solid var(--border);
        }
        .how-inner {
            max-width: 1200px;
            margin: 0 auto;
        }
        .steps-row {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 2px;
            margin-top: 60px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: 16px;
            overflow: hidden;
        }
        .step {
            background: var(--dark);
            padding: 40px 28px;
            position: relative;
        }
        .step-num {
            font-family: 'Bebas Neue', sans-serif;
            font-size: 48px;
            color: var(--border);
            line-height: 1;
            margin-bottom: 16px;
        }
        .step-icon { font-size: 28px; margin-bottom: 16px; }
        .step-title {
            font-size: 15px;
            font-weight: 600;
            color: var(--text);
            margin-bottom: 10px;
        }
        .step-desc { font-size: 13px; color: var(--soft); line-height: 1.6; }

        /* ══════════════════════════════
           AI SECTION
        ══════════════════════════════ */
        .ai-section {
            padding: 100px 48px;
        }
        .ai-inner {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 80px;
            align-items: center;
        }
        .ai-visual {
            background: var(--panel);
            border: 1px solid var(--border);
            border-radius: 20px;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        .ai-visual::before {
            content: '';
            position: absolute;
            top: -40px; right: -40px;
            width: 200px; height: 200px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(79,142,247,0.12) 0%, transparent 70%);
        }
        .ai-label {
            font-size: 10px;
            font-weight: 700;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: var(--soft);
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .ai-label::before {
            content: '';
            width: 20px; height: 1px;
            background: var(--muted);
        }
        .gru-diagram {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .gru-row {
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .gru-box {
            flex: 1;
            padding: 12px;
            border-radius: 8px;
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            letter-spacing: 0.5px;
        }
        .gru-input { background: rgba(79,142,247,0.1); border: 1px solid rgba(79,142,247,0.2); color: var(--accent); }
        .gru-hidden { background: rgba(123,94,167,0.1); border: 1px solid rgba(123,94,167,0.2); color: #9b7fd4; }
        .gru-output { background: rgba(62,207,142,0.1); border: 1px solid rgba(62,207,142,0.2); color: var(--green); }
        .gru-arrow { color: var(--muted); font-size: 10px; }
        .gru-tag {
            font-size: 10px;
            color: var(--soft);
            text-align: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid var(--border);
        }
        .ai-features { list-style: none; margin-top: 28px; display: flex; flex-direction: column; gap: 12px; }
        .ai-features li {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            font-size: 14px;
            color: var(--soft);
            line-height: 1.5;
        }
        .ai-features li::before {
            content: '↗';
            color: var(--accent);
            font-size: 12px;
            margin-top: 2px;
            flex-shrink: 0;
        }

        /* ══════════════════════════════
           CTA SECTION
        ══════════════════════════════ */
        .cta-section {
            padding: 100px 48px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        .cta-section::before {
            content: '';
            position: absolute;
            inset: 0;
            background: radial-gradient(ellipse at 50% 0%, rgba(79,142,247,0.08) 0%, transparent 60%);
            pointer-events: none;
        }
        .cta-section .section-title { font-size: clamp(48px, 6vw, 72px); }
        .cta-section .section-sub { max-width: 400px; margin: 16px auto 0; }
        .cta-section .hero-actions { justify-content: center; }

        /* ══════════════════════════════
           FOOTER
        ══════════════════════════════ */
        footer {
            border-top: 1px solid var(--border);
            padding: 28px 48px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        footer .foot-brand {
            display: flex;
            align-items: center;
            gap: 8px;
            font-family: 'Bebas Neue', sans-serif;
            font-size: 18px;
            letter-spacing: 2px;
            color: var(--text);
        }
        footer .foot-brand img { height: 28px; }
        footer p { font-size: 12px; color: var(--muted); }

        /* ══════════════════════════════
           SCROLL ANIMATIONS
        ══════════════════════════════ */
        .reveal {
            opacity: 0;
            transform: translateY(32px);
            transition: opacity 0.7s ease, transform 0.7s ease;
        }
        .reveal.visible {
            opacity: 1;
            transform: translateY(0);
        }
        .reveal-delay-1 { transition-delay: 0.1s; }
        .reveal-delay-2 { transition-delay: 0.2s; }
        .reveal-delay-3 { transition-delay: 0.3s; }
        .reveal-delay-4 { transition-delay: 0.4s; }
    </style>
</head>
<body>

<!-- NAV -->
<nav class="nav">
    <a class="nav-brand" href="index.php">
        <img src="FullLogo_Transparent_NoBuffer.png" alt="FitPlanner">
        <span class="nav-brand-text">FIT<span>PLANNER</span></span>
    </a>
    <div class="nav-links">
        <a href="#tools">Tools</a>
        <a href="#how">How it works</a>
        <a href="#ai">AI Engine</a>
        <?php if ($loggedIn): ?>
            <a href="workout_generator.php">Dashboard</a>
            <a href="logout.php" class="nav-cta">Logout</a>
        <?php else: ?>
            <a href="login.html">Sign in</a>
            <a href="register.html" class="nav-cta">Get Started →</a>
        <?php endif; ?>
    </div>
</nav>

<!-- HERO -->
<section class="hero">
    <div class="hero-blob2"></div>
    <div class="hero-pill"><span class="dot"></span> AI-Powered Fitness Platform</div>
    <h1 class="hero-title">TRAIN WITH<br><span class="highlight">INTELLIGENCE</span></h1>
    <p class="hero-sub">
        FitPlanner uses a GRU neural network to understand your goals, history, and body — then builds workout plans that actually evolve with you.
    </p>
    <div class="hero-actions">
        <?php if ($loggedIn): ?>
            <a href="workout_generator.php" class="btn-primary">Go to Dashboard →</a>
            <a href="diet_plan_generator.php" class="btn-ghost">Nutrition Planner</a>
        <?php else: ?>
            <a href="register.html" class="btn-primary">Start For Free →</a>
            <a href="login.html" class="btn-ghost">I have an account</a>
        <?php endif; ?>
    </div>
    <div class="hero-stats">
        <div class="stat">
            <div class="stat-num">200<span>+</span></div>
            <div class="stat-label">Exercises</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
            <div class="stat-num">GRU<span></span></div>
            <div class="stat-label">AI Model</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
            <div class="stat-num">2<span> tools</span></div>
            <div class="stat-label">Built-in</div>
        </div>
        <div class="stat-divider"></div>
        <div class="stat">
            <div class="stat-num">0<span>$</span></div>
            <div class="stat-label">Forever free</div>
        </div>
    </div>
</section>

<!-- TOOLS -->
<section class="section" id="tools">
    <div class="reveal">
        <p class="section-label">The Platform</p>
        <h2 class="section-title">TWO TOOLS.<br>ONE MISSION.</h2>
        <p class="section-sub">Everything you need to train smarter and eat better — built into one cohesive platform.</p>
    </div>
    <div class="tools-grid reveal">
        <a href="workout_generator.php" class="tool-card">
            <div class="tool-number">01</div>
            <div class="tool-icon icon-blue">🏋️</div>
            <div class="tool-badge badge-ai">✦ GRU-Powered</div>
            <h3 class="tool-title">SMART WORKOUT<br>GENERATOR</h3>
            <p class="tool-desc">
                Input your goal and fitness level. Our GRU model analyzes exercise patterns and generates personalized, full-body workout plans — not random picks, real intelligence.
            </p>
            <div class="tool-link">Open Workout Generator →</div>
        </a>
        <a href="diet_plan_generator.php" class="tool-card">
            <div class="tool-number">02</div>
            <div class="tool-icon icon-green">🥗</div>
            <div class="tool-badge badge-ml">✦ LSTM-Powered</div>
            <h3 class="tool-title">NUTRITION<br>PLANNER</h3>
            <p class="tool-desc">
                Built on a trained LSTM model, the nutrition planner calculates your ideal macros based on your real body stats — BMR, TDEE, BMI, and water needs included.
            </p>
            <div class="tool-link tool-link-green">Open Nutrition Planner →</div>
        </a>
    </div>
</section>

<!-- HOW IT WORKS -->
<section class="how-section" id="how">
    <div class="how-inner">
        <div class="reveal">
            <p class="section-label">The Process</p>
            <h2 class="section-title">HOW IT WORKS</h2>
        </div>
        <div class="steps-row reveal">
            <div class="step">
                <div class="step-num">01</div>
                <div class="step-icon">👤</div>
                <div class="step-title">Create your profile</div>
                <p class="step-desc">Enter your age, weight, height, activity level and fitness goal once.</p>
            </div>
            <div class="step">
                <div class="step-num">02</div>
                <div class="step-icon">🧠</div>
                <div class="step-title">AI analyzes your data</div>
                <p class="step-desc">The GRU model processes your inputs against thousands of training patterns.</p>
            </div>
            <div class="step">
                <div class="step-num">03</div>
                <div class="step-icon">⚡</div>
                <div class="step-title">Get your plan</div>
                <p class="step-desc">Receive a complete workout with sets, reps, rest periods and calorie burn.</p>
            </div>
            <div class="step">
                <div class="step-num">04</div>
                <div class="step-icon">📈</div>
                <div class="step-title">Track & evolve</div>
                <p class="step-desc">Save workouts, track history, let the system learn your progress over time.</p>
            </div>
        </div>
    </div>
</section>

<!-- AI ENGINE -->
<section class="ai-section" id="ai">
    <div class="ai-inner">
        <div class="reveal">
            <p class="section-label">Under The Hood</p>
            <h2 class="section-title">THE GRU<br>ENGINE</h2>
            <p class="section-sub" style="margin-top:16px;">
                A Gated Recurrent Unit network trained on fitness data. Unlike simple random generators, it captures the relationship between your goal, level, and which exercises actually work together.
            </p>
            <ul class="ai-features">
                <li>Goal-aware: differentiates muscle gain, weight loss, and maintenance at a structural level</li>
                <li>Level-adaptive: progressions scale intelligently between beginner and advanced</li>
                <li>Balanced output: no 2-exercise plans — always a proper session volume</li>
                <li>Muscle group logic: avoids redundant pairings, covers all target areas</li>
            </ul>
        </div>
        <div class="ai-visual reveal reveal-delay-2">
            <div class="ai-label">GRU Architecture</div>
            <div class="gru-diagram">
                <div class="gru-row">
                    <div class="gru-box gru-input">Goal</div>
                    <div class="gru-arrow">→</div>
                    <div class="gru-box gru-input">Level</div>
                    <div class="gru-arrow">→</div>
                    <div class="gru-box gru-input">Profile</div>
                </div>
                <div style="text-align:center; color: var(--muted); font-size:18px; margin: 4px 0;">↓</div>
                <div class="gru-row">
                    <div class="gru-box gru-hidden">GRU Layer 1 — 128 units</div>
                </div>
                <div style="text-align:center; color: var(--muted); font-size:18px; margin: 4px 0;">↓</div>
                <div class="gru-row">
                    <div class="gru-box gru-hidden">GRU Layer 2 — 64 units</div>
                </div>
                <div style="text-align:center; color: var(--muted); font-size:18px; margin: 4px 0;">↓</div>
                <div class="gru-row">
                    <div class="gru-box gru-output">Exercise Selection</div>
                    <div class="gru-arrow">+</div>
                    <div class="gru-box gru-output">Volume Plan</div>
                </div>
                <p class="gru-tag">Dropout 0.3 · Adam optimizer · Trained on 5,000+ workout patterns</p>
            </div>
        </div>
    </div>
</section>

<!-- CTA -->
<section class="cta-section">
    <div class="reveal">
        <p class="section-label">Ready?</p>
        <h2 class="section-title">START TRAINING<br>SMARTER TODAY</h2>
        <p class="section-sub">No subscription. No credit card. Just your goals and a model that actually understands them.</p>
        <div class="hero-actions">
            <a href="register.html" class="btn-primary">Create Free Account →</a>
        </div>
    </div>
</section>

<!-- FOOTER -->
<footer>
    <div class="foot-brand">
        <img src="FullLogo_Transparent_NoBuffer.png" alt="FitPlanner">
        FITPLANNER
    </div>
    <p>© 2025 FitPlanner. Built with ♥ and PyTorch.</p>
</footer>

<script>
// Scroll reveal
const observer = new IntersectionObserver((entries) => {
    entries.forEach(el => {
        if (el.isIntersecting) {
            el.target.classList.add('visible');
        }
    });
}, { threshold: 0.1 });

document.querySelectorAll('.reveal').forEach(el => observer.observe(el));

// Navbar scroll effect
window.addEventListener('scroll', () => {
    const nav = document.querySelector('.nav');
    if (window.scrollY > 50) {
        nav.style.background = 'rgba(10,10,10,0.95)';
    } else {
        nav.style.background = 'rgba(10,10,10,0.7)';
    }
});
</script>
</body>
</html>
