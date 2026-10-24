{{-- Where an OAuth provider sends the reader back to after they sign in.
     It hands the code to the playground that opened it, on this origin
     only, and closes. The code is worth nothing without the verifier the
     playground kept. --}}
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>Signing in</title>
    <style>body { margin: 3rem auto; max-width: 28rem; padding: 0 1rem; font: 15px/1.5 system-ui, sans-serif; color: #555 }</style>
</head>
<body>
    <p id="status">Signing in. This window closes by itself.</p>
    <script>
        (function () {
            var query = new URLSearchParams(window.location.search);
            var message = {
                type: 'vellum-oauth',
                state: query.get('state'),
                code: query.get('code'),
                error: query.get('error'),
                description: query.get('error_description'),
            };

            if (window.opener && !window.opener.closed) {
                window.opener.postMessage(message, window.location.origin);
                window.close();
            } else {
                document.getElementById('status').textContent = 'Signing in only works from the playground that opened this window. Close it and try again from there.';
            }
        })();
    </script>
</body>
</html>
