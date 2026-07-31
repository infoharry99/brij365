<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title') - Builder360 ERP CRM</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700;800&family=Inter:wght@400;500;600&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    
    <style>
        :root {
            --text-main: #F8FAFC;
            --text-muted: #94A3B8;
        }

        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: #090D16;
            background-image: 
                radial-gradient(at 0% 0%, rgba(99, 102, 241, 0.15) 0px, transparent 50%),
                radial-gradient(at 100% 100%, rgba(14, 165, 233, 0.12) 0px, transparent 50%),
                radial-gradient(at 50% 50%, rgba(139, 92, 246, 0.1) 0px, transparent 50%);
            color: var(--text-main);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            overflow-x: hidden;
            position: relative;
        }

        /* Ambient floating light shapes */
        .ambient-glow {
            position: absolute;
            width: 450px;
            height: 450px;
            border-radius: 50%;
            filter: blur(120px);
            opacity: 0.25;
            pointer-events: none;
            z-index: 0;
            animation: pulseGlow 8s ease-in-out infinite alternate;
        }

        .ambient-1 {
            top: -100px;
            left: -100px;
            background: @yield('glow-color-1', '#4F46E5');
        }

        .ambient-2 {
            bottom: -100px;
            right: -100px;
            background: @yield('glow-color-2', '#06B6D4');
        }

        @keyframes pulseGlow {
            0% { transform: scale(1) translate(0, 0); opacity: 0.2; }
            100% { transform: scale(1.15) translate(30px, -20px); opacity: 0.35; }
        }

        /* Glassmorphic Error Container */
        .error-card {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 560px;
            background: rgba(15, 23, 42, 0.75);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 24px;
            padding: 48px 36px;
            text-align: center;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 0 1px rgba(255, 255, 255, 0.05);
            animation: slideUp 0.6s cubic-bezier(0.16, 1, 0.3, 1);
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(24px); }
            to { opacity: 1; transform: translateY(0); }
        }

        /* Brand badge */
        .brand-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            font-family: 'Outfit', sans-serif;
            font-size: 13px;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
            color: #94A3B8;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.08);
            padding: 6px 16px;
            border-radius: 30px;
            margin-bottom: 28px;
        }

        .brand-badge i {
            color: #6366F1;
        }

        /* Big Status Code Header */
        .error-code {
            font-family: 'Outfit', sans-serif;
            font-size: 88px;
            font-weight: 800;
            line-height: 1;
            margin-bottom: 12px;
            background: @yield('code-gradient', 'linear-gradient(135deg, #6366F1 0%, #A855F7 100%)');
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            letter-spacing: -0.04em;
            filter: drop-shadow(0 4px 20px rgba(99, 102, 241, 0.3));
        }

        /* Icon Graphic */
        .error-icon-wrapper {
            width: 80px;
            height: 80px;
            margin: 0 auto 20px auto;
            border-radius: 20px;
            background: @yield('icon-bg', 'rgba(99, 102, 241, 0.12)');
            border: 1px solid @yield('icon-border', 'rgba(99, 102, 241, 0.25)');
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: @yield('icon-color', '#818CF8');
            box-shadow: inset 0 0 20px rgba(255, 255, 255, 0.05);
        }

        .error-title {
            font-family: 'Outfit', sans-serif;
            font-size: 26px;
            font-weight: 700;
            color: #F8FAFC;
            margin-bottom: 12px;
        }

        .error-message {
            font-size: 14.5px;
            line-height: 1.65;
            color: #94A3B8;
            margin-bottom: 36px;
            max-width: 440px;
            margin-left: auto;
            margin-right: auto;
        }

        /* Action Buttons */
        .error-actions {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 12px;
            flex-wrap: wrap;
        }

        .btn-primary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 24px;
            background: @yield('btn-bg', 'linear-gradient(135deg, #4F46E5 0%, #6366F1 100%)');
            color: #FFFFFF;
            font-weight: 600;
            font-size: 14px;
            border-radius: 12px;
            text-decoration: none;
            border: none;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.35);
            transition: all 0.2s ease;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(79, 70, 229, 0.5);
            color: #FFFFFF;
        }

        .btn-secondary {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 22px;
            background: rgba(255, 255, 255, 0.06);
            color: #CBD5E1;
            font-weight: 600;
            font-size: 14px;
            border-radius: 12px;
            text-decoration: none;
            border: 1px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
            transition: all 0.2s ease;
        }

        .btn-secondary:hover {
            background: rgba(255, 255, 255, 0.12);
            color: #F8FAFC;
            transform: translateY(-2px);
        }

        .error-footer {
            margin-top: 32px;
            padding-top: 20px;
            border-top: 1px solid rgba(255, 255, 255, 0.08);
            font-size: 12px;
            color: #64748B;
        }
    </style>
</head>
<body>
    <div class="ambient-glow ambient-1"></div>
    <div class="ambient-glow ambient-2"></div>

    <div class="error-card">
        <div class="brand-badge">
            <i class="fa-solid fa-cube"></i> Builder360 ERP CRM
        </div>

        <div class="error-icon-wrapper">
            @yield('icon')
        </div>

        <h1 class="error-code">@yield('code')</h1>
        <h2 class="error-title">@yield('headline')</h2>
        <p class="error-message">@yield('message')</p>

        <div class="error-actions">
            @yield('actions')
        </div>

        <div class="error-footer">
            &copy; {{ date('Y') }} Builder360 ERP CRM. All rights reserved.
        </div>
    </div>
</body>
</html>
