{{-- Where an OAuth provider sends the reader back to after they sign in.
     It hands the code to the playground that opened it, on this origin
     only, and closes. The code is worth nothing without the verifier the
     playground kept. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">
    <meta name="referrer" content="no-referrer">
    <title>{{ __('vellum-openapi::playground.callback.title') }}</title>
    <style>body { margin: 3rem auto; max-width: 28rem; padding: 0 1rem; font: 15px/1.5 system-ui, sans-serif; color: #555 }</style>
</head>
<body>
    <p id="status">{{ __('vellum-openapi::playground.callback.status') }}</p>
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
                document.getElementById('status').textContent = @js(__('vellum-openapi::playground.callback.orphan'));
            }
        })();
    </script>
</body>
</html>
