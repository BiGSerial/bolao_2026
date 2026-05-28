<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Sem conexão | BolãoVF</title>
    <style>
        :root {
            color-scheme: dark;
        }

        body {
            margin: 0;
            min-height: 100vh;
            display: grid;
            place-items: center;
            font-family: "Instrument Sans", system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            background: radial-gradient(circle at 20% 20%, #1f2937, #0b1017 60%);
            color: #e5e7eb;
            padding: 24px;
        }

        main {
            width: min(560px, 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 16px;
            background: rgba(17, 24, 39, 0.8);
            backdrop-filter: blur(6px);
            padding: 24px;
        }

        h1 {
            margin-top: 0;
            font-size: 1.4rem;
        }

        p {
            color: #cbd5e1;
            line-height: 1.6;
        }

        a {
            color: #f59e0b;
            text-decoration: none;
            font-weight: 600;
        }
    </style>
</head>
<body>
<main>
    <h1>Você está offline</h1>
    <p>Não foi possível carregar esta página agora. Quando a conexão voltar, tente novamente.</p>
    <p><a href="{{ url('/') }}">Voltar para o início</a></p>
</main>
</body>
</html>
