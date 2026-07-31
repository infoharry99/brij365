<!-- BRIJ Group Minimal 2-Second Project Loader -->
<div class="brij-loader-screen" id="brijLoaderScreen">
    <div class="brij-minimal-loader" role="status" aria-label="BRIJ Group is loading">
        <div class="brij-minimal-loader__logo" aria-hidden="true">
            <!-- Ghost Logo -->
            <div class="brij-minimal-loader__ghost logo-content">
                <svg viewBox="0 0 300 80" width="100%" height="100%">
                    <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="'Inter', system-ui, -apple-system, sans-serif" font-weight="900" font-size="38" fill="#232326">BRIJ <tspan fill="#f58220">GROUP</tspan></text>
                </svg>
            </div>
            <!-- Reveal Logo -->
            <div class="brij-minimal-loader__reveal logo-content" style="position: absolute; inset: 0;">
                <svg viewBox="0 0 300 80" width="100%" height="100%">
                    <text x="50%" y="55%" dominant-baseline="middle" text-anchor="middle" font-family="'Inter', system-ui, -apple-system, sans-serif" font-weight="900" font-size="38" fill="#232326">BRIJ <tspan fill="#f58220">GROUP</tspan></text>
                </svg>
            </div>
            <div class="brij-minimal-loader__scan"></div>
        </div>
        <div class="brij-minimal-loader__progress"></div>
        <p class="brij-minimal-loader__status">Loading</p>
    </div>
</div>

<style>
    :root {
        color-scheme: light dark;
        --cycle: 2s;
        --brand: #f58220;
        --ink: #232326;
        --track: rgba(35, 35, 38, .16);
        --stage: #f7f7f5;
    }

    .brij-loader-screen {
        position: fixed;
        inset: 0;
        z-index: 999999;

        /* Transparent overlay */
        background: rgba(255, 255, 255, 0.15);

        /* Blur the content behind */
        backdrop-filter: blur(8px);
        -webkit-backdrop-filter: blur(8px);

        display: grid;
        place-items: center;
        transition: opacity 0.4s ease, visibility 0.4s ease;
    }

    .brij-loader-screen.fade-out {
        opacity: 0;
        visibility: hidden;
        pointer-events: none;
    }

    .brij-minimal-loader {
        --logo-width: min(360px, 76vw);
        position: relative;
        width: var(--logo-width);
        display: grid;
        justify-items: center;
        gap: 18px;
        background: transparent;
        isolation: isolate;
        animation: brij-settle var(--cycle) cubic-bezier(.45, 0, .2, 1) infinite;
    }
     

    .brij-minimal-loader__logo {
        position: relative;
        width: 100%;
        aspect-ratio: 2.5 / 1;
        overflow: hidden;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .logo-content {
        width: 100%;
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: center;
    }

    .brij-minimal-loader__ghost {
        opacity: .09;
        filter: saturate(.55);
        animation: brij-ghost var(--cycle) ease-in-out infinite;
    }

    .brij-minimal-loader__reveal {
        clip-path: inset(0 100% 0 0);
        opacity: 0;
        animation: brij-logo-reveal var(--cycle) cubic-bezier(.65, 0, .22, 1) infinite;
    }

    .brij-minimal-loader__scan {
        position: absolute;
        z-index: 2;
        top: 8%;
        bottom: 8%;
        left: 0;
        width: 2px;
        border-radius: 999px;
        background: var(--brand);
        box-shadow: 0 0 8px var(--brand);
        opacity: 0;
        transform: translateX(-4px);
        animation: brij-scan var(--cycle) cubic-bezier(.65, 0, .22, 1) infinite;
        pointer-events: none;
    }

    .brij-minimal-loader__progress {
        position: relative;
        width: 54%;
        height: 3px;
        overflow: hidden;
        border-radius: 999px;
        background: var(--track);
    }

    .brij-minimal-loader__progress::after {
        content: "";
        position: absolute;
        top: 0;
        left: 0;
        width: 30%;
        height: 100%;
        border-radius: inherit;
        background: var(--brand);
        transform: translateX(-115%);
        animation: brij-progress var(--cycle) cubic-bezier(.65, 0, .22, 1) infinite;
    }

    .brij-minimal-loader__status {
        margin: -4px 0 0;
        color: var(--ink);
        font-size: 10px;
        font-weight: 700;
        line-height: 1;
        letter-spacing: .28em;
        text-transform: uppercase;
        opacity: 0;
        animation: brij-status var(--cycle) ease-in-out infinite;
    }

    @keyframes brij-logo-reveal {
        0%, 7% { clip-path: inset(0 100% 0 0); opacity: 0; }
        14% { opacity: 1; }
        55%, 80% { clip-path: inset(0 0 0 0); opacity: 1; }
        100% { clip-path: inset(0 0 0 100%); opacity: 0; }
    }

    @keyframes brij-scan {
        0%, 7% { transform: translateX(-4px); opacity: 0; }
        13% { opacity: .95; }
        55% { transform: translateX(calc(var(--logo-width) - 1px)); opacity: .95; }
        63%, 100% { transform: translateX(calc(var(--logo-width) - 1px)); opacity: 0; }
    }

    @keyframes brij-progress {
        0%, 9% { transform: translateX(-115%); opacity: 0; }
        17% { opacity: 1; }
        76% { transform: translateX(385%); opacity: 1; }
        88%, 100% { transform: translateX(385%); opacity: 0; }
    }

    @keyframes brij-ghost {
        0%, 100% { opacity: .055; }
        38%, 75% { opacity: .11; }
    }

    @keyframes brij-status {
        0%, 24%, 100% { opacity: 0; transform: translateY(3px); }
        40%, 78% { opacity: .48; transform: translateY(0); }
    }

    @keyframes brij-settle {
        0%, 100% { transform: translateY(2px) scale(.992); }
        52%, 78% { transform: translateY(0) scale(1); }
    }

    @media (prefers-color-scheme: dark) {
        :root {
            --ink: #f2f2f0;
            --track: rgba(255, 255, 255, .18);
            --stage: #161617;
        }
    }
</style>

<script>
    (function() {
        let hardForceHideTimer = null;
        let navFailSafeTimer = null;

        function hideBrijLoader() {
            const loader = document.getElementById('brijLoaderScreen');
            if (loader) {
                loader.classList.add('fade-out');
            }
            if (hardForceHideTimer) clearTimeout(hardForceHideTimer);
            if (navFailSafeTimer) clearTimeout(navFailSafeTimer);
        }

        function showBrijLoader() {
            const loader = document.getElementById('brijLoaderScreen');
            if (loader) {
                loader.classList.remove('fade-out');
            }
            if (navFailSafeTimer) clearTimeout(navFailSafeTimer);
            // Fail-safe: Force hide after 5s if page doesn't actually unload
            navFailSafeTimer = setTimeout(hideBrijLoader, 5000);
        }

        window.hideBrijLoader = hideBrijLoader;
        window.showBrijLoader = showBrijLoader;

        // Hide 1.5s after DOM ready
        document.addEventListener('DOMContentLoaded', function() {
            setTimeout(hideBrijLoader, 1500);
        });

        // Fail-safe: Hide when window load completes
        window.addEventListener('load', function() {
            setTimeout(hideBrijLoader, 1000);
        });

        hardForceHideTimer = setTimeout(hideBrijLoader, 10000);

        window.addEventListener('pageshow', hideBrijLoader);
        window.addEventListener('focus', function() {
            setTimeout(hideBrijLoader, 1000);
        });

        window.addEventListener('beforeunload', function() {
            showBrijLoader();
        });

        // Emergency click to dismiss if stuck
        document.addEventListener('click', function(e) {
            const loader = document.getElementById('brijLoaderScreen');
            if (loader && !loader.classList.contains('fade-out')) {
                hideBrijLoader();
            }
        });
    })();
</script>

  