<?php

namespace App\Services;

use App\Mail\ChangelogVersionReleasedMail;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Mail;

class ReleaseChangelogNotifyService
{
    public function __construct(private ReleaseChangelogService $changelog) {}

    /**
     * @return Collection<int, User>
     */
    public function recipients(): Collection
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('role', function ($query): void {
                $query->whereIn('name', ['admin', 'super_admin']);
            })
            ->orderBy('id')
            ->get();
    }

    /**
     * @return array{sent: int, dry_run: bool, version: string|null, recipients: list<string>, error: string|null}
     */
    public function notify(string $app, bool $dryRun = false): array
    {
        if (! array_key_exists($app, config('release.apps', []))) {
            return [
                'sent' => 0,
                'dry_run' => $dryRun,
                'version' => null,
                'recipients' => [],
                'error' => 'Nieznana aplikacja.',
            ];
        }

        $data = $this->changelog->forApp($app);
        $current = $data['releases'][0] ?? null;
        $version = $current['version'] ?? null;

        if (! $data['readable'] || $version === null) {
            return [
                'sent' => 0,
                'dry_run' => $dryRun,
                'version' => $version,
                'recipients' => [],
                'error' => $data['error'] ?? 'Brak aktualnej wersji w changelogu.',
            ];
        }

        $users = $this->recipients();
        $emails = $users->pluck('email')->filter()->values()->all();
        $bullets = array_map(
            static fn (string $bullet): string => self::plainBullet($bullet),
            $current['bullets'] ?? []
        );
        $url = route('changelog.show', $app);

        if (! $dryRun) {
            foreach ($users as $user) {
                $email = trim((string) $user->email);
                if ($email === '') {
                    continue;
                }

                Mail::to($email)->send(new ChangelogVersionReleasedMail(
                    appLabel: $data['label'],
                    version: (string) $version,
                    date: $current['date'] ?? null,
                    bullets: $bullets,
                    changelogUrl: $url,
                    recipientName: trim((string) $user->name),
                ));
            }
        }

        return [
            'sent' => $dryRun ? 0 : count($emails),
            'dry_run' => $dryRun,
            'version' => (string) $version,
            'recipients' => $emails,
            'error' => null,
        ];
    }

    public static function plainBullet(string $bullet): string
    {
        $plain = (string) preg_replace('/\[(.*?)\]\((.*?)\)/', '$1', $bullet);

        return trim(html_entity_decode($plain, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }
}
