<!DOCTYPE html>
<html lang="de">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <title>E-Mail-Benachrichtigungen abbestellen – {{ $sender }}</title>
    <style>
        :root { color-scheme: light dark; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 16px;
            box-sizing: border-box;
            font-family: Inter, system-ui, -apple-system, 'Segoe UI', sans-serif;
            background: #f5f5f5;
            color: #262626;
        }
        .card {
            width: 100%;
            max-width: 440px;
            background: #fff;
            border-radius: 16px;
            border-top: 4px solid #1fadae;
            padding: 32px;
            box-shadow: 0 20px 50px -20px rgba(0, 0, 0, 0.25);
        }
        h1 { margin: 0 0 12px; font-size: 22px; color: #0a0a0a; }
        p { margin: 0 0 12px; line-height: 1.6; font-size: 15px; }
        .muted { color: #737373; font-size: 13px; }
        button {
            margin-top: 8px;
            cursor: pointer;
            border: 0;
            border-radius: 10px;
            padding: 12px 20px;
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            background: #1fadae;
        }
        button:hover { background: #1a9596; }
        button.secondary { color: #1a9596; background: transparent; box-shadow: inset 0 0 0 1.5px #1fadae; }
        button.secondary:hover { background: rgba(31, 173, 174, 0.08); }
        @media (prefers-color-scheme: dark) {
            body { background: #0a0a0a; color: #d4d4d4; }
            .card { background: #171717; }
            h1 { color: #fff; }
        }
    </style>
</head>
<body>
    <div class="card">
        @if ($state === 'unsubscribed')
            <h1>Abgemeldet ✓</h1>
            <p>Sie erhalten keine weiteren Infos per E-Mail von {{ $sender }}.</p>
            <p class="muted">Rechnungen und Nachrichten zu Ihren Aufträgen bekommen Sie weiterhin.</p>
            <form method="POST">
                {{-- Signed URL instead of a CSRF token: the route is excluded from the CSRF check --}}
                <input type="hidden" name="action" value="resubscribe">
                <button type="submit" class="secondary">Doch wieder anmelden</button>
            </form>
        @elseif ($state === 'resubscribed')
            <h1>Wieder angemeldet ✓</h1>
            <p>Schön, dass Sie dabei bleiben! Sie erhalten wieder Infos per E-Mail von {{ $sender }}.</p>
            <p class="muted">Abmelden geht jederzeit über den Link unten in jeder dieser E-Mails.</p>
        @else
            <h1>E-Mail-Benachrichtigungen abbestellen</h1>
            <p>Möchten Sie keine weiteren Infos per E-Mail von {{ $sender }} erhalten?</p>
            <form method="POST">
                {{-- Signed URL instead of a CSRF token: the route is excluded from the CSRF check --}}
                <button type="submit">Ja, abmelden</button>
            </form>
        @endif
    </div>
</body>
</html>
