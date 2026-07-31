<?php $__env->startSection('title', 'Login — Build365 ERP CRM'); ?>

<link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,300,0,0" rel="stylesheet">
<style>
    /* ── Reset & Root ──────────────────────────────────────────── */
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
        --bg-page:     #F5F6FA;
        --bg-white:    #FFFFFF;
        --bg-input:    #F9FAFB;
        --border:      #E5E7EB;
        --border-focus:#F97316;
        --accent:      #F97316;
        --accent-dark: #EA6C0A;
        --text:        #111827;
        --muted:       #9CA3AF;
        --muted-2:     #6B7280;
        --label:       #374151;
        --grid-dot:    rgba(249,115,22,0.12);
        --red:         #EF4444;
    }

    html, body {
        height: 100%;
        font-family: 'Inter', sans-serif;
        background: var(--bg-page);
        color: var(--text);
        -webkit-font-smoothing: antialiased;
    }

    /* ── Page Shell ─────────────────────────────────────────────── */
    .login-shell {
        display: grid;
        grid-template-columns: 1fr 480px;
        min-height: 100vh;
    }

    /* ── LEFT PANEL ─────────────────────────────────────────────── */
    .left-panel {
        position: relative;
        background: linear-gradient(145deg, #EEF2FF 0%, #E8EFFF 55%, #FEF3E8 100%);
        overflow: hidden;
        display: flex;
        flex-direction: column;
        justify-content: flex-end;
        padding: 48px 52px;
    }

    /* Animated grid */
    .left-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image:
            linear-gradient(var(--grid-line) 1px, transparent 1px),
            linear-gradient(90deg, var(--grid-line) 1px, transparent 1px);
        background-size: 48px 48px;
        z-index: 0;
    }

    /* Large radial glow — left center */
    .left-panel::after {
        content: '';
        position: absolute;
        top: 30%;
        left: -10%;
        width: 70%;
        height: 60%;
        background: radial-gradient(ellipse, rgba(249,115,22,0.09) 0%, transparent 70%);
        z-index: 1;
        pointer-events: none;
    }

    /* Isometric building illustration */
    .iso-scene {
        position: absolute;
        inset: 0;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: center;
        pointer-events: none;
    }

    /* SVG Buildings */
    .iso-svg {
        width: min(560px, 80%);
        height: auto;
        opacity: 0.92;
        filter: drop-shadow(0 8px 30px rgba(249,115,22,0.16));
        animation: float 6s ease-in-out infinite;
    }

    @keyframes float {
        0%, 100% { transform: translateY(0px); }
        50%       { transform: translateY(-10px); }
    }

    /* Bottom glow under buildings */
    .iso-glow {
        position: absolute;
        bottom: 15%;
        left: 50%;
        transform: translateX(-50%);
        width: 55%;
        height: 80px;
        background: radial-gradient(ellipse, rgba(249,115,22,0.20) 0%, transparent 70%);
        z-index: 3;
        filter: blur(12px);
        pointer-events: none;
    }

    /* Second glow — top right accent */
    .iso-glow-2 {
        position: absolute;
        top: 12%;
        right: 10%;
        width: 260px;
        height: 260px;
        background: radial-gradient(ellipse, rgba(249,115,22,0.08) 0%, transparent 70%);
        z-index: 2;
        pointer-events: none;
    }

    /* Panel bottom copy */
    .panel-copy {
        position: relative;
        z-index: 10;
    }

    .panel-tag {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        font-size: 11px;
        font-weight: 600;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--accent-dark);
        background: rgba(249,115,22,0.10);
        border: 1px solid rgba(249,115,22,0.25);
        padding: 6px 14px;
        border-radius: 100px;
        margin-bottom: 20px;
    }

    .panel-tag::before {
        content: '';
        width: 6px;
        height: 6px;
        border-radius: 50%;
        background: var(--accent);
        animation: pulse 2s ease-in-out infinite;
        box-shadow: 0 0 6px var(--accent);
    }

    @keyframes pulse {
        0%, 100% { opacity: 1; transform: scale(1); }
        50%       { opacity: 0.5; transform: scale(0.8); }
    }

    .panel-headline {
        font-size: clamp(28px, 3.5vw, 40px);
        font-weight: 800;
        line-height: 1.18;
        letter-spacing: -0.025em;
        color: #111827;
        margin-bottom: 12px;
    }

    .panel-headline span {
        color: var(--accent);
    }

    .panel-sub {
        font-size: 15px;
        color: var(--muted-2);
        line-height: 1.6;
        max-width: 380px;
        margin-bottom: 32px;
    }

    .panel-stats {
        display: flex;
        gap: 32px;
    }

    .p-stat {
        display: flex;
        flex-direction: column;
        gap: 3px;
    }

    .p-stat-num {
        font-size: 22px;
        font-weight: 800;
        color: #111827;
    }

    .p-stat-label {
        font-size: 11px;
        font-weight: 500;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: var(--muted-2);
    }

    .panel-divider {
        width: 1px;
        height: 36px;
        background: var(--border);
        align-self: center;
    }

    /* ── RIGHT PANEL ─────────────────────────────────────────────── */
    .right-panel {
        background: var(--bg-white);
        border-left: 1px solid var(--border);
        display: flex;
        flex-direction: column;
        position: relative;
        overflow: hidden;
    }

    /* Subtle top glow in right panel */
    .right-panel::before {
        content: '';
        position: absolute;
        top: -80px;
        right: -80px;
        width: 320px;
        height: 320px;
        background: radial-gradient(ellipse, rgba(249,115,22,0.05) 0%, transparent 70%);
        z-index: 0;
        pointer-events: none;
    }

    /* Top bar */
    .rp-topbar {
        position: relative;
        z-index: 2;
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 24px 40px;
        border-bottom: 1px solid var(--border);
    }

    .rp-logo {
        display: flex;
        align-items: center;
        gap: 10px;
        text-decoration: none;
    }

    .rp-logo-mark {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        background: linear-gradient(135deg, var(--accent) 0%, #FB923C 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-weight: 900;
        font-size: 17px;
        color: white;
        box-shadow: 0 4px 14px rgba(249,115,22,0.32);
    }

    .rp-logo-text {
        font-family: 'Syne', sans-serif;
        font-size: 16px;
        font-weight: 700;
        color: var(--text);
        letter-spacing: -0.01em;
    }

    .rp-logo-text span {
        color: var(--muted);
        font-weight: 400;
    }

    .rp-version {
        font-family: 'JetBrains Mono', monospace;
        font-size: 11px;
        color: var(--muted);
        background: rgba(255,255,255,0.04);
        border: 1px solid var(--border);
        padding: 4px 10px;
        border-radius: 6px;
    }

    /* Form container */
    .rp-form-wrap {
        flex: 1;
        display: flex;
        flex-direction: column;
        justify-content: center;
        padding: 48px 40px;
        position: relative;
        z-index: 2;
    }

    .rp-heading {
        margin-bottom: 32px;
    }

    .rp-eyebrow {
        font-family: 'JetBrains Mono', monospace;
        font-size: 10px;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--accent-2);
        margin-bottom: 8px;
    }

    .rp-title {
        font-family: 'Syne', sans-serif;
        font-size: 26px;
        font-weight: 700;
        letter-spacing: -0.02em;
        color: var(--white);
        margin-bottom: 6px;
    }

    .rp-subtitle {
        font-size: 14px;
        color: var(--muted-2);
        line-height: 1.5;
    }

    /* Field group */
    .field-group {
        display: flex;
        flex-direction: column;
        gap: 16px;
        margin-bottom: 24px;
    }

    .field {
        display: flex;
        flex-direction: column;
        gap: 7px;
    }

    .field-label {
        font-size: 13px;
        font-weight: 600;
        color: var(--muted-2);
        letter-spacing: 0.01em;
    }

    .field-inner {
        position: relative;
        display: flex;
        align-items: center;
    }

    .field-icon {
        position: absolute;
        left: 14px;
        font-size: 18px;
        color: var(--muted);
        font-variation-settings: 'FILL' 0, 'wght' 300;
        pointer-events: none;
        transition: color 0.2s;
    }

    .field-input {
        width: 100%;
        padding: 13px 14px 13px 44px;
        background: var(--bg-input);
        border: 1px solid var(--border);
        border-radius: 10px;
        color: var(--text);
        font-family: 'Inter', sans-serif;
        font-size: 14px;
        outline: none;
        transition: border-color 0.2s, box-shadow 0.2s;
        -webkit-appearance: none;
    }

    .field-input::placeholder { color: var(--muted); }

    .field-input:focus {
        border-color: var(--border-focus);
        box-shadow: 0 0 0 3px rgba(249,115,22,0.12);
    }

    .field-input:focus + .field-icon,
    .field-inner:focus-within .field-icon {
        color: var(--accent-2);
    }

    /* Reorder icon inside for correct z-stacking */
    .field-inner .field-input { order: 1; }
    .field-inner .field-icon  { order: 2; z-index: 1; left: 14px; }

    .field-error {
        font-size: 12px;
        color: var(--red);
        display: flex;
        align-items: center;
        gap: 4px;
    }

    /* Footer row inside form */
    .form-footer-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-bottom: 24px;
    }

    .remember-check {
        display: flex;
        align-items: center;
        gap: 8px;
        cursor: pointer;
        user-select: none;
    }

    .remember-check input[type="checkbox"] {
        width: 16px;
        height: 16px;
        accent-color: var(--accent);
        border-radius: 4px;
        cursor: pointer;
    }

    .remember-check span {
        font-size: 13px;
        color: var(--muted-2);
    }

    .forgot-link {
        font-size: 13px;
        font-weight: 600;
        color: var(--accent);
        text-decoration: none;
        transition: color 0.2s;
    }

    .forgot-link:hover { color: var(--accent-dark); text-decoration: underline; }

    /* Submit button — solid orange, matches dashboard "New task" */
    .btn-login {
        width: 100%;
        padding: 13px;
        border: none;
        border-radius: 10px;
        background: var(--accent);
        color: white;
        font-family: 'Inter', sans-serif;
        font-size: 15px;
        font-weight: 700;
        letter-spacing: 0.01em;
        cursor: pointer;
        transition: background 0.2s, transform 0.15s, box-shadow 0.2s;
        box-shadow: 0 4px 16px rgba(249,115,22,0.30);
    }

    .btn-login:hover {
        background: var(--accent-dark);
        transform: translateY(-1px);
        box-shadow: 0 6px 22px rgba(249,115,22,0.38);
    }

    .btn-login:active { transform: translateY(0); }

    /* Bottom bar */
    .rp-footer {
        position: relative;
        z-index: 2;
        padding: 20px 40px;
        border-top: 1px solid var(--border);
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .rp-footer p {
        font-size: 12px;
        color: var(--muted);
    }

    /* ── Responsive ─────────────────────────────────────────────── */
    @media (max-width: 860px) {
        .login-shell {
            grid-template-columns: 1fr;
        }

        .left-panel {
            display: none;
        }

        .right-panel {
            border-left: none;
        }
    }
</style>

<?php $__env->startSection('content'); ?>
<div class="login-shell">
    
    <div class="left-panel">

        
        <div class="iso-glow-2"></div>
        <div class="iso-glow"></div>

        
        <div class="iso-scene">
            <svg class="iso-svg" viewBox="0 0 600 420" fill="none" xmlns="http://www.w3.org/2000/svg">
                <!-- Platform base -->
                <ellipse cx="300" cy="365" rx="240" ry="30" fill="rgba(249,115,22,0.05)" />

                <!-- Grid platform -->
                <path d="M60 340 L300 260 L540 340 L300 420Z" fill="#DDE5FF" stroke="rgba(249,115,22,0.18)" stroke-width="1"/>
                <!-- Grid lines on platform -->
                <line x1="140" y1="308" x2="460" y2="308" stroke="rgba(249,115,22,0.10)" stroke-width="0.5"/>
                <line x1="180" y1="295" x2="420" y2="295" stroke="rgba(249,115,22,0.10)" stroke-width="0.5"/>
                <line x1="220" y1="283" x2="380" y2="283" stroke="rgba(249,115,22,0.10)" stroke-width="0.5"/>
                <line x1="200" y1="355" x2="200" y2="270" stroke="rgba(249,115,22,0.07)" stroke-width="0.5"/>
                <line x1="260" y1="378" x2="260" y2="262" stroke="rgba(249,115,22,0.07)" stroke-width="0.5"/>
                <line x1="340" y1="378" x2="340" y2="262" stroke="rgba(249,115,22,0.07)" stroke-width="0.5"/>
                <line x1="400" y1="355" x2="400" y2="270" stroke="rgba(249,115,22,0.07)" stroke-width="0.5"/>

                <!-- MAIN TALL BUILDING (center) -->
                <!-- Left face -->
                <path d="M270 80 L270 280 L300 295 L300 95Z" fill="#C7D5FF"/>
                <!-- Right face -->
                <path d="M300 95 L300 295 L330 280 L330 80Z" fill="#B0C2FF"/>
                <!-- Top face -->
                <path d="M270 80 L300 65 L330 80 L300 95Z" fill="#E6ECFF"/>
                <!-- Glowing windows - left face -->
                <rect x="275" y="100" width="8" height="12" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="290" y="100" width="8" height="12" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="275" y="120" width="8" height="12" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="290" y="120" width="8" height="12" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="275" y="140" width="8" height="12" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="290" y="140" width="8" height="12" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="275" y="160" width="8" height="12" rx="1" fill="rgba(249,115,22,0.52)"/>
                <rect x="290" y="160" width="8" height="12" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="275" y="180" width="8" height="12" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="290" y="180" width="8" height="12" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="275" y="200" width="8" height="12" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="290" y="200" width="8" height="12" rx="1" fill="rgba(249,115,22,0.52)"/>
                <rect x="275" y="220" width="8" height="12" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="290" y="220" width="8" height="12" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="275" y="240" width="8" height="12" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="290" y="240" width="8" height="12" rx="1" fill="rgba(249,115,22,0.62)"/>
                <!-- Right face windows -->
                <rect x="307" y="100" width="8" height="12" rx="1" fill="rgba(150,180,255,0.72)"/>
                <rect x="318" y="100" width="8" height="12" rx="1" fill="rgba(150,180,255,0.55)"/>
                <rect x="307" y="120" width="8" height="12" rx="1" fill="rgba(150,180,255,0.65)"/>
                <rect x="318" y="120" width="8" height="12" rx="1" fill="rgba(150,180,255,0.72)"/>
                <rect x="307" y="140" width="8" height="12" rx="1" fill="rgba(150,180,255,0.50)"/>
                <rect x="318" y="140" width="8" height="12" rx="1" fill="rgba(150,180,255,0.65)"/>
                <rect x="307" y="160" width="8" height="12" rx="1" fill="rgba(150,180,255,0.72)"/>
                <rect x="307" y="180" width="8" height="12" rx="1" fill="rgba(150,180,255,0.55)"/>
                <rect x="318" y="180" width="8" height="12" rx="1" fill="rgba(150,180,255,0.65)"/>
                <!-- Antenna -->
                <line x1="300" y1="45" x2="300" y2="65" stroke="rgba(249,115,22,0.80)" stroke-width="2"/>
                <circle cx="300" cy="43" r="3" fill="rgba(249,115,22,1)" opacity="0.9"/>
                <!-- Glow under main building -->
                <ellipse cx="300" cy="290" rx="30" ry="6" fill="rgba(249,115,22,0.15)" filter="url(#blur)"/>

                <!-- LEFT SHORT BUILDING -->
                <path d="M155 170 L155 295 L205 322 L205 197Z" fill="#C7D5FF"/>
                <path d="M205 197 L205 322 L230 308 L230 183Z" fill="#B0C2FF"/>
                <path d="M155 170 L193 152 L230 170 L205 197 L155 170Z" fill="#E0E8FF"/>
                <!-- Windows left building -->
                <rect x="162" y="185" width="7" height="10" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="174" y="185" width="7" height="10" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="186" y="185" width="7" height="10" rx="1" fill="rgba(249,115,22,0.52)"/>
                <rect x="162" y="202" width="7" height="10" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="174" y="202" width="7" height="10" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="162" y="219" width="7" height="10" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="174" y="219" width="7" height="10" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="186" y="219" width="7" height="10" rx="1" fill="rgba(249,115,22,0.52)"/>
                <rect x="162" y="236" width="7" height="10" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="174" y="236" width="7" height="10" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="162" y="253" width="7" height="10" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="174" y="253" width="7" height="10" rx="1" fill="rgba(249,115,22,0.42)"/>
                <rect x="162" y="270" width="7" height="10" rx="1" fill="rgba(249,115,22,0.92)"/>
                <!-- Right face left building -->
                <rect x="212" y="200" width="10" height="8" rx="1" fill="rgba(150,180,255,0.72)"/>
                <rect x="212" y="215" width="10" height="8" rx="1" fill="rgba(150,180,255,0.55)"/>
                <rect x="212" y="230" width="10" height="8" rx="1" fill="rgba(150,180,255,0.65)"/>
                <rect x="212" y="245" width="10" height="8" rx="1" fill="rgba(150,180,255,0.50)"/>
                <rect x="212" y="260" width="10" height="8" rx="1" fill="rgba(150,180,255,0.72)"/>
                <rect x="212" y="275" width="10" height="8" rx="1" fill="rgba(150,180,255,0.55)"/>

                <!-- RIGHT SHORT BUILDING -->
                <path d="M370 175 L370 295 L410 318 L410 195Z" fill="#C7D5FF"/>
                <path d="M410 195 L410 318 L445 300 L445 178Z" fill="#B0C2FF"/>
                <path d="M370 175 L407 155 L445 175 L410 195 L370 175Z" fill="#E0E8FF"/>
                <!-- Windows right building -->
                <rect x="377" y="190" width="7" height="10" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="389" y="190" width="7" height="10" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="401" y="190" width="7" height="10" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="377" y="207" width="7" height="10" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="389" y="207" width="7" height="10" rx="1" fill="rgba(249,115,22,0.52)"/>
                <rect x="377" y="224" width="7" height="10" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="389" y="224" width="7" height="10" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="401" y="224" width="7" height="10" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="377" y="241" width="7" height="10" rx="1" fill="rgba(249,115,22,0.52)"/>
                <rect x="389" y="241" width="7" height="10" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="377" y="258" width="7" height="10" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="389" y="258" width="7" height="10" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="401" y="258" width="7" height="10" rx="1" fill="rgba(249,115,22,0.52)"/>
                <!-- Right face right building -->
                <rect x="417" y="198" width="10" height="8" rx="1" fill="rgba(150,180,255,0.65)"/>
                <rect x="417" y="213" width="10" height="8" rx="1" fill="rgba(150,180,255,0.50)"/>
                <rect x="417" y="228" width="10" height="8" rx="1" fill="rgba(150,180,255,0.72)"/>
                <rect x="417" y="243" width="10" height="8" rx="1" fill="rgba(150,180,255,0.55)"/>
                <rect x="417" y="258" width="10" height="8" rx="1" fill="rgba(150,180,255,0.45)"/>
                <rect x="417" y="273" width="10" height="8" rx="1" fill="rgba(150,180,255,0.65)"/>

                <!-- SMALL BOX LEFT -->
                <path d="M110 260 L110 310 L145 328 L145 278Z" fill="#CFDAFF"/>
                <path d="M145 278 L145 328 L165 318 L165 268Z" fill="#B8C8FF"/>
                <path d="M110 260 L137 248 L165 260 L145 278 L110 260Z" fill="#E2EAFF"/>
                <rect x="117" y="275" width="6" height="8" rx="1" fill="rgba(249,115,22,0.72)"/>
                <rect x="128" y="275" width="6" height="8" rx="1" fill="rgba(249,115,22,0.52)"/>
                <rect x="117" y="289" width="6" height="8" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="128" y="289" width="6" height="8" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="117" y="303" width="6" height="8" rx="1" fill="rgba(249,115,22,0.82)"/>

                <!-- SMALL BOX RIGHT -->
                <path d="M445 255 L445 305 L478 322 L478 272Z" fill="#CFDAFF"/>
                <path d="M478 272 L478 322 L498 310 L498 260Z" fill="#B8C8FF"/>
                <path d="M445 255 L472 242 L498 255 L478 272 L445 255Z" fill="#E2EAFF"/>
                <rect x="453" y="270" width="6" height="8" rx="1" fill="rgba(249,115,22,0.82)"/>
                <rect x="464" y="270" width="6" height="8" rx="1" fill="rgba(249,115,22,0.62)"/>
                <rect x="453" y="284" width="6" height="8" rx="1" fill="rgba(249,115,22,0.92)"/>
                <rect x="464" y="284" width="6" height="8" rx="1" fill="rgba(249,115,22,0.52)"/>

                <!-- Floating data nodes -->
                <circle cx="420" cy="110" r="4" fill="rgba(249,115,22,0.90)"/>
                <circle cx="420" cy="110" r="8" fill="none" stroke="rgba(249,115,22,0.30)" stroke-width="1"/>
                <circle cx="420" cy="110" r="14" fill="none" stroke="rgba(249,115,22,0.10)" stroke-width="1"/>

                <circle cx="175" cy="130" r="3" fill="rgba(249,115,22,0.82)"/>
                <circle cx="175" cy="130" r="6" fill="none" stroke="rgba(249,115,22,0.22)" stroke-width="1"/>

                <!-- Connection lines between nodes -->
                <line x1="175" y1="130" x2="270" y2="80" stroke="rgba(249,115,22,0.20)" stroke-width="0.8" stroke-dasharray="4 4"/>
                <line x1="420" y1="110" x2="330" y2="80" stroke="rgba(249,115,22,0.20)" stroke-width="0.8" stroke-dasharray="4 4"/>

                <defs>
                    <filter id="blur" x="-50%" y="-50%" width="200%" height="200%">
                        <feGaussianBlur stdDeviation="4"/>
                    </filter>
                </defs>
            </svg>
        </div>

        
        <div class="panel-copy">
            <div class="panel-tag">ERP–CRM Platform</div>
            <h2 class="panel-headline">
                Build. Track.<br><span>Close deals</span> faster.
            </h2>
            <p class="panel-sub">
                Build365 centralises your projects, contacts, and pipeline in one intelligent workspace.
            </p>
        </div>

    </div>

    <div class="right-panel">

        <div class="rp-form-wrap">
            <div class="rp-heading">
                <p class="rp-eyebrow">Workspace Access</p>
                <h1 class="rp-title">Sign in to Build365</h1>
                <p class="rp-subtitle">Secure ERP–CRM workspace. Enter your credentials to continue.</p>
            </div>

            <form method="POST" action="<?php echo e(route('login.store')); ?>" novalidate>
                <?php echo csrf_field(); ?>

                <div class="field-group">
                    
                    <div class="field">
                        <label for="email" class="field-label">Email address</label>
                        <div class="field-inner">
                            <input
                                id="email"
                                name="email"
                                type="email"
                                value="<?php echo e(old('email')); ?>"
                                required
                                autofocus
                                autocomplete="username"
                                placeholder="you@company.com"
                                class="field-input"
                            >
                            <span class="field-icon material-symbols-rounded">mail</span>
                        </div>
                        <?php $__errorArgs = ['email'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="field-error">
                                <span class="material-symbols-rounded" style="font-size:14px">error</span>
                                <?php echo e($message); ?>

                            </span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>

                    
                    <div class="field">
                        <label for="password" class="field-label">Password</label>
                        <div class="field-inner">
                            <input
                                id="password"
                                name="password"
                                type="password"
                                required
                                autocomplete="current-password"
                                placeholder="Enter your password"
                                class="field-input"
                            >
                            <span class="field-icon material-symbols-rounded">lock</span>
                        </div>
                        <?php $__errorArgs = ['password'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                            <span class="field-error">
                                <span class="material-symbols-rounded" style="font-size:14px">error</span>
                                <?php echo e($message); ?>

                            </span>
                        <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>
                    </div>
                </div>

                
                <div class="form-footer-row">
                    <label class="remember-check">
                        <input name="remember" type="checkbox" value="1">
                        <span>Remember this device</span>
                    </label>
                    <a href="<?php echo e(route('password.request')); ?>" class="forgot-link">Forgot password?</a>
                </div>

                
                <button class="btn-login" type="submit">
                    Login to Build365
                </button>

            </form>
        </div>

        

    </div>

</div>
<?php $__env->stopSection(); ?>
<?php echo $__env->make('layouts.builder360-auth', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /home/developer/public_html/build365/resources/views/auth/login.blade.php ENDPATH**/ ?>