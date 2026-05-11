<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>NEXUM | Accesso utenti</title>
    <link rel="stylesheet" href="{{ asset('nexum-app/styles.css') }}">
    <style>
        .login-shell {
            min-height: 100vh;
            display: grid;
            place-items: center;
            padding: 24px;
            background:
                radial-gradient(circle at 18% 18%, rgba(111, 166, 207, 0.14), transparent 26rem),
                var(--surface-page);
        }

        .login-panel {
            width: min(100%, 440px);
            display: grid;
            gap: 18px;
            padding: 28px;
            border: 1px solid var(--border);
            border-radius: var(--radius);
            background: var(--surface);
            box-shadow: var(--shadow);
        }

        .login-logo {
            width: 142px;
            margin: 0 auto;
            border-radius: var(--radius);
        }

        .login-heading {
            display: grid;
            gap: 6px;
            text-align: center;
        }

        .login-heading h1 {
            margin: 0;
            color: var(--text);
            font-size: 1.45rem;
        }

        .login-links {
            display: flex;
            justify-content: center;
        }

        .alert-error {
            margin: 0;
            padding: 12px 14px;
            border-radius: var(--radius);
            background: var(--warning-soft);
            color: var(--warning-text);
            font-weight: 700;
            line-height: 1.45;
        }
    </style>
</head>
<body>
    <main class="login-shell">
        <section class="login-panel" aria-labelledby="login-title">
            <img class="login-logo" src="{{ asset('nexum-app/eggon_logo_43542.png') }}" alt="Eggon logo">

            <div class="login-heading">
                <p class="eyebrow">NEXUM</p>
                <h1 id="login-title">Accesso utenti</h1>
                <p class="status-message">Entra nell’applicativo operativo con le credenziali ricevute dall’amministratore.</p>
            </div>

            @if ($errors->any())
                <p class="alert-error">{{ $errors->first() }}</p>
            @endif

            <form class="field-stack" method="POST" action="{{ route('user.login.store') }}">
                @csrf

                <label class="field">
                    <span>Email</span>
                    <input name="email" type="email" value="{{ old('email') }}" autocomplete="username" required autofocus>
                </label>

                <label class="field">
                    <span>Password</span>
                    <input name="password" type="password" autocomplete="current-password" required>
                </label>

                <label class="field">
                    <span>
                        <input name="remember" type="checkbox" value="1">
                        Ricordami
                    </span>
                </label>

                <div class="button-row">
                    <button class="primary-button" type="submit">Accedi</button>
                </div>
            </form>

            <div class="login-links">
                <a class="text-button" href="/admin/login">Accesso amministratore</a>
            </div>
        </section>
    </main>
</body>
</html>
