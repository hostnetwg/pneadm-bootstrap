<?php

namespace App\Services;

class ReleaseChangelogService
{
    /**
     * @return list<array{app: string, label: string, version: string}>
     */
    public function menuItems(): array
    {
        $items = [];
        foreach (array_keys(config('release.apps', [])) as $app) {
            $changelog = $this->forApp($app);
            $items[] = [
                'app' => $app,
                'label' => $changelog['label'],
                'version' => $changelog['version'] ?? '—',
            ];
        }

        return $items;
    }

    /**
     * @return array{
     *     app: string,
     *     label: string,
     *     version: string|null,
     *     path: string,
     *     readable: bool,
     *     error: string|null,
     *     releases: list<array{version: string, date: string|null, summary: list<string>, bullets: list<string>}>
     * }
     */
    public function forApp(string $app): array
    {
        $meta = config('release.apps.'.$app);
        if (! is_array($meta)) {
            return [
                'app' => $app,
                'label' => $app,
                'version' => null,
                'path' => '',
                'readable' => false,
                'error' => 'Nieznana aplikacja.',
                'releases' => [],
            ];
        }

        $path = $this->resolvePath($app, $meta['changelog_path'] ?? null);
        $label = (string) ($meta['label'] ?? $app);

        if ($path === '' || ! is_readable($path)) {
            return [
                'app' => $app,
                'label' => $label,
                'version' => null,
                'path' => $path,
                'readable' => false,
                'error' => 'Nie znaleziono pliku historii zmian. Na produkcji ustaw PNEDU_CHANGELOG_PATH (dla pnedu.pl).',
                'releases' => [],
            ];
        }

        $releases = $this->parseMarkdown((string) file_get_contents($path));

        return [
            'app' => $app,
            'label' => $label,
            'version' => $releases[0]['version'] ?? null,
            'path' => $path,
            'readable' => true,
            'error' => null,
            'releases' => $releases,
        ];
    }

    public static function formatBulletHtml(string $bullet): string
    {
        $escaped = e($bullet);

        return (string) preg_replace_callback(
            '/\[(.*?)\]\((.*?)\)/',
            static function (array $matches): string {
                $label = $matches[1];
                $url = html_entity_decode($matches[2], ENT_QUOTES | ENT_HTML5, 'UTF-8');
                $isHttp = preg_match('#^https?://#i', $url) === 1;
                $isPanelPath = str_starts_with($url, '/') && ! str_starts_with($url, '//');
                if ($isHttp || $isPanelPath) {
                    return '<a href="'.e($url).'" target="_blank" rel="noopener noreferrer">'.$label.'</a>';
                }

                return $label.' (<code>'.e($url).'</code>)';
            },
            $escaped
        );
    }

    /**
     * @return list<array{version: string, date: string|null, summary: list<string>, bullets: list<string>}>
     */
    public function parseMarkdown(string $markdown): array
    {
        $releases = [];
        $parts = preg_split('/^##\s+/m', $markdown) ?: [];
        array_shift($parts);

        foreach ($parts as $part) {
            $lines = preg_split("/\r\n|\n|\r/", trim($part)) ?: [];
            $heading = trim((string) array_shift($lines));
            if ($heading === '') {
                continue;
            }

            $version = $heading;
            $date = null;
            if (preg_match('/^(\d+\.\d+)\s*[—–-]\s*(.+)$/u', $heading, $matches) === 1) {
                $version = $matches[1];
                $date = trim($matches[2]);
            }

            $summary = [];
            $bullets = [];
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '') {
                    continue;
                }
                if (str_starts_with($line, '- ')) {
                    $bullets[] = trim(substr($line, 2));

                    continue;
                }
                $summary[] = $line;
            }

            $releases[] = [
                'version' => $version,
                'date' => $date,
                'summary' => $summary,
                'bullets' => $bullets,
            ];
        }

        return $releases;
    }

    private function resolvePath(string $app, mixed $configured): string
    {
        if (is_string($configured) && trim($configured) !== '') {
            return trim($configured);
        }

        if ($app === 'adm') {
            return base_path('CHANGELOG.md');
        }

        if ($app === 'pnedu') {
            $candidates = [
                dirname(base_path()).DIRECTORY_SEPARATOR.'pnedu'.DIRECTORY_SEPARATOR.'CHANGELOG.md',
                '/var/www/pnedu/CHANGELOG.md',
                '/home/srv66127/domains/pnedu.pl/app/CHANGELOG.md',
            ];
            foreach ($candidates as $candidate) {
                if (is_readable($candidate)) {
                    return $candidate;
                }
            }

            return $candidates[0];
        }

        return '';
    }
}
