<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Сканер ссылок на безопасность</title>
    <style>
        :root {
            /* Cybersec Palette */
            --bg-body: #0a0a0a;
            --bg-card: #171717;
            --bg-input: #262626;
            --bg-hover: #404040;
            
            --text-main: #e5e5e5;
            --text-muted: #a3a3a3;
            --text-dim: #525252;

            /* Semantic Colors (High Contrast) */
            --accent: #22c55e; /* Green for safe/accent */
            --success: #22c55e;
            --warning: #facc15;
            --danger: #ef4444;
            --info: #38bdf8;
            
            --border: #404040;
            --radius-lg: 4px; /* More angular */
            --radius-md: 2px;
            
            --font-main: 'Inter', system-ui, sans-serif;
            --font-mono: 'JetBrains Mono', 'Fira Code', 'Roboto Mono', monospace;
        }

        *, *::before, *::after {
            box-sizing: border-box;
        }

        body {
            font-family: var(--font-main);
            background-color: var(--bg-body);
            color: var(--text-main);
            margin: 0;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        header {
            background-color: #000000;
            border-bottom: 1px solid var(--border);
            padding: 1rem 0;
            position: sticky;
            top: 0;
            z-index: 100;
        }

        .container {
            width: 100%;
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 1rem;
        }

        .nav-flex {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .brand {
            font-family: var(--font-mono);
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--success);
            text-decoration: none;
            letter-spacing: -0.5px;
            text-transform: uppercase;
        }

        main {
            flex: 1;
            padding: 2rem 0;
        }

        .card {
            background-color: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: var(--radius-lg);
            padding: 1.5rem;
            box-shadow: none;
        }

        h1, h2, h3, h4, h5, h6 {
            margin-top: 0;
            font-weight: 600;
            color: var(--text-main);
        }
        
        h1 { font-size: 1.5rem; margin-bottom: 1.5rem; text-transform: uppercase; letter-spacing: 1px; }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background-color: var(--success);
            color: #000000;
            height: 38px;
            padding: 0 1.5rem;
            border-radius: 2px;
            text-decoration: none;
            font-weight: 700;
            border: 1px solid var(--success);
            cursor: pointer;
            transition: all 0.2s cubic-bezier(0.4, 0, 0.2, 1);
            font-family: var(--font-mono);
            text-transform: uppercase;
            font-size: 0.75rem;
            letter-spacing: 1px;
            box-shadow: 0 1px 2px rgba(0,0,0,0.5);
        }

        .btn:hover {
            background-color: #16a34a;
            border-color: #16a34a;
            transform: translateY(-1px);
        }
        
        .btn:active {
            transform: translateY(0);
        }

        .form-group {
            margin-bottom: 1.5rem;
        }

        label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--text-muted);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        input[type="text"], textarea {
            width: 100%;
            background-color: var(--bg-input);
            border: 1px solid var(--border);
            color: var(--text-main);
            padding: 0.75rem;
            border-radius: var(--radius-md);
            font-size: 0.875rem;
            font-family: var(--font-mono);
            box-sizing: border-box;
            transition: border-color 0.2s;
        }

        input[type="text"]:focus, textarea:focus {
            outline: none;
            border-color: var(--success);
        }

        .alert { padding: 1rem; border-radius: var(--radius-md); margin-bottom: 1.5rem; border: 1px solid transparent; }
        .alert-error { background-color: rgba(239, 68, 68, 0.1); border-color: rgba(239, 68, 68, 0.3); color: #fca5a5; }
        
        /* Utility classes */
        .text-success { color: var(--success); }
        .text-warning { color: var(--warning); }
        .text-danger { color: var(--danger); }
        .text-muted { color: var(--text-muted); }
        .font-mono { font-family: var(--font-mono); }
        
        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 2px;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
        }
        .badge-success { background-color: rgba(34, 197, 94, 0.1); color: var(--success); border: 1px solid rgba(34, 197, 94, 0.2); }
        .badge-warning { background-color: rgba(250, 204, 21, 0.1); color: var(--warning); border: 1px solid rgba(250, 204, 21, 0.2); }
        .badge-danger { background-color: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        
        footer {
            text-align: center;
            padding: 2rem;
            color: var(--text-dim);
            font-size: 0.75rem;
            border-top: 1px solid var(--border);
            font-family: var(--font-mono);
        }
    </style>
    @stack('styles')
</head>
<body>
    <header>
        <div class="container nav-flex">
            <a href="/" class="brand">🔒 Сканер ссылок</a>
        </div>
    </header>
    <main>
        <div class="container">
            @yield('content')
        </div>
    </main>
    <footer>
        &copy; {{ date('Y') }} Сканер ссылок на безопасность. Powered by AI.
    </footer>
</body>
</html>
