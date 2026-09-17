<?php

declare(strict_types=1);

namespace Vellum\Markdown\Extensions\Preview;

/**
 * The standalone HTML document a preview is shown inside.
 *
 * Previews run in an iframe rather than in the page. A preview exists to show
 * a component from the host application, which is styled by the host's own
 * build, and loading that stylesheet into the docs page would restyle the
 * docs. A frame gets the application's CSS and nothing else, and the docs
 * chrome cannot leak into the preview either.
 *
 * The document is inlined as srcdoc rather than served from a route, so a
 * static export carries it with no extra files and no runtime.
 */
final class PreviewDocument
{
    /**
     * A few lines, and they have to be inline: the frame is its own document
     * and loading a script into it would mean another request per preview.
     *
     * Height is measured rather than assumed, because a component's size is
     * the thing an author is usually trying to show. Theme comes from the
     * parent, since the frame cannot see the reader's choice.
     */
    private const SCRIPT = <<<'JS'
    (function () {
      var send = function () {
        var height = Math.ceil(document.documentElement.getBoundingClientRect().height);
        parent.postMessage({ vellumPreview: 'height', id: ID, height: height }, '*');
      };
      window.addEventListener('message', function (event) {
        var data = event.data || {};
        if (data.vellumPreview === 'theme') {
          document.documentElement.classList.toggle('dark', !!data.dark);
          document.documentElement.style.colorScheme = data.dark ? 'dark' : 'light';
          send();
        }
      });
      if (typeof ResizeObserver !== 'undefined') {
        new ResizeObserver(send).observe(document.documentElement);
      }
      window.addEventListener('load', send);
      send();
    })();
    JS;

    /**
     * @param  list<string>  $stylesheets
     */
    public static function build(string $id, string $body, array $stylesheets, string $padding): string
    {
        $links = '';

        foreach ($stylesheets as $href) {
            $links .= '<link rel="stylesheet" href="'.htmlspecialchars($href, ENT_QUOTES).'">';
        }

        $pad = match ($padding) {
            'none' => '0',
            'sm' => '0.75rem',
            'lg' => '2rem',
            default => '1.25rem',
        };

        return '<!DOCTYPE html><html lang="en"><head><meta charset="utf-8">'
            .'<meta name="viewport" content="width=device-width, initial-scale=1">'
            .$links
            // Only enough of a reset to stop the frame's own margin showing
            // as a gap. Everything else is the host application's business.
            .'<style>html{background:transparent}body{margin:0;padding:'.$pad.';font-family:system-ui,sans-serif}</style>'
            .'</head><body>'.$body
            .'<script>'.self::script($id).'</script></body></html>';
    }

    public static function script(string $id): string
    {
        return str_replace('ID', json_encode($id, JSON_THROW_ON_ERROR), self::SCRIPT);
    }
}
