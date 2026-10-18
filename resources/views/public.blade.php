<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="Replay a policy change before your agent takes its next action. Deterministic decisions, traceable review, no external execution.">
    <title>ScopeTwin — Know what your policy changes</title>
    @viteReactRefresh
    @vite('resources/js/public.tsx')
</head>
<body>
    <div id="public-root" data-payload="{{ json_encode($payload, JSON_THROW_ON_ERROR) }}"></div>
    <noscript>ScopeTwin requires JavaScript. Sign in at /login or create a workspace at /register.</noscript>
</body>
</html>
