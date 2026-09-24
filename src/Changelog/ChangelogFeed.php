<?php

declare(strict_types=1);

namespace Vellum\Changelog;

use Illuminate\Support\Carbon;

/**
 * Builds an Atom feed of published changelog releases.
 */
final class ChangelogFeed
{
    public function render(Changelog $changelog): string
    {
        $name = (string) config('vellum.name', 'Docs');
        $pageUrl = route('vellum.changelog', absolute: true);
        $selfUrl = route('vellum.changelog.atom', absolute: true);
        $releases = $changelog->published();
        $updated = $this->feedUpdated($changelog, $releases);

        $entries = '';

        foreach ($releases as $release) {
            $entries .= $this->entry($release, $pageUrl, $changelog->mtime);
        }

        return '<?xml version="1.0" encoding="utf-8"?>'."\n"
            .'<feed xmlns="http://www.w3.org/2005/Atom">'."\n"
            .'  <title>'.$this->e($name.' Changelog').'</title>'."\n"
            .'  <link href="'.$this->e($selfUrl).'" rel="self"/>'."\n"
            .'  <link href="'.$this->e($pageUrl).'"/>'."\n"
            .'  <updated>'.$this->e($updated).'</updated>'."\n"
            .'  <id>'.$this->e($pageUrl).'</id>'."\n"
            .'  <author><name>'.$this->e($name).'</name></author>'
            .$entries."\n"
            .'</feed>'."\n";
    }

    /**
     * @param  list<ChangelogRelease>  $releases
     */
    private function feedUpdated(Changelog $changelog, array $releases): string
    {
        foreach ($releases as $release) {
            if ($release->date !== null) {
                return $this->atomDate($release->date, $changelog->mtime);
            }
        }

        return Carbon::createFromTimestamp($changelog->mtime)->utc()->toAtomString();
    }

    private function entry(ChangelogRelease $release, string $pageUrl, int $mtime): string
    {
        $url = $pageUrl.'#'.$release->id;
        $updated = $this->atomDate($release->date, $mtime);
        $title = $release->version.($release->date !== null ? ' - '.$release->date : '');

        return "\n  <entry>\n"
            .'    <title>'.$this->e($title).'</title>'."\n"
            .'    <link href="'.$this->e($url).'"/>'."\n"
            .'    <id>'.$this->e($url).'</id>'."\n"
            .'    <updated>'.$this->e($updated).'</updated>'."\n"
            .'    <content type="html">'.$this->e($release->html).'</content>'."\n"
            .'  </entry>';
    }

    private function atomDate(?string $date, int $mtime): string
    {
        if ($date !== null) {
            return $date.'T00:00:00Z';
        }

        return Carbon::createFromTimestamp($mtime)->utc()->toAtomString();
    }

    private function e(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
