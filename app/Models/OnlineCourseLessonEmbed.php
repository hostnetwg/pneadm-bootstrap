<?php

namespace App\Models;

use App\Traits\LogsActivity;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class OnlineCourseLessonEmbed extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'online_course_lesson_id',
        'video_url',
        'platform',
        'title',
        'sort_order',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    public function lesson(): BelongsTo
    {
        return $this->belongsTo(OnlineCourseLesson::class, 'online_course_lesson_id');
    }

    /**
     * @param  bool  $enablePlayerApi  YouTube: enablejsapi=1 + origin; Vimeo: api=1 (Vimeo Player SDK)
     */
    public function getEmbedUrl(bool $enablePlayerApi = false, ?string $playerParentOrigin = null): string
    {
        $raw = $this->normalizedVideoUrl();

        if ($this->platform === 'youtube') {
            $videoId = $this->extractYouTubeId($raw);
            if ($videoId) {
                $base = "https://www.youtube.com/embed/{$videoId}";
                if ($enablePlayerApi) {
                    return $this->mergeEmbedQueryString($base, array_filter([
                        'enablejsapi' => '1',
                        'origin' => $playerParentOrigin !== null && $playerParentOrigin !== '' ? $playerParentOrigin : null,
                    ]));
                }

                return $base;
            }

            return $raw;
        }

        if ($this->platform === 'vimeo') {
            $videoId = $this->extractVimeoId($raw);
            if ($videoId) {
                $base = 'https://player.vimeo.com/video/'.$videoId.'?badge=0&autopause=0&player_id=0&app_id=58479';
                if ($enablePlayerApi) {
                    return $this->mergeEmbedQueryString($base, ['api' => '1']);
                }

                return $base;
            }

            return $raw;
        }

        return $raw;
    }

    private function mergeEmbedQueryString(string $url, array $add): string
    {
        $parsed = parse_url($url);
        if ($parsed === false) {
            return $url;
        }
        $query = [];
        if (! empty($parsed['query'])) {
            parse_str($parsed['query'], $query);
        }
        foreach ($add as $key => $value) {
            if ($value !== null && $value !== '') {
                $query[$key] = $value;
            }
        }
        $parsed['query'] = http_build_query($query);

        return $this->rebuildHttpUrl($parsed);
    }

    /** @param  array{scheme?:string,user?:string,pass?:string,host?:string,port?:int,path?:string,query?:string,fragment?:string}  $parts */
    private function rebuildHttpUrl(array $parts): string
    {
        $scheme = isset($parts['scheme']) ? $parts['scheme'].'://' : '';
        $user = $parts['user'] ?? '';
        $pass = isset($parts['pass']) ? ':'.$parts['pass'] : '';
        $pass = ($user !== '' || $pass !== '') ? "{$pass}@" : '';
        $host = $parts['host'] ?? '';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = $parts['path'] ?? '';
        $query = isset($parts['query']) && $parts['query'] !== '' ? '?'.$parts['query'] : '';
        $fragment = isset($parts['fragment']) ? '#'.$parts['fragment'] : '';

        return "{$scheme}{$user}{$pass}{$host}{$port}{$path}{$query}{$fragment}";
    }

    private function normalizedVideoUrl(): string
    {
        $url = trim((string) $this->video_url);
        if ($url === '') {
            return '';
        }

        return html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function extractYouTubeId(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $tryUrl = urldecode($url);
        foreach ([$url, $tryUrl] as $candidate) {
            $query = parse_url($candidate, PHP_URL_QUERY);
            if (is_string($query) && $query !== '') {
                parse_str($query, $params);
                if (! empty($params['v']) && preg_match('/^[a-zA-Z0-9_-]{11}$/', (string) $params['v'])) {
                    return (string) $params['v'];
                }
            }
        }

        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/|youtube-nocookie\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
            '/(?:m\.)?(?:youtube\.com|youtube-nocookie\.com)\/(?:embed|shorts|live)\/([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/watch\?[^#]*[&?]v=([a-zA-Z0-9_-]{11})/',
            '/(?:www\.)?youtu\.be\/([a-zA-Z0-9_-]{11})(?:[?&#]|$)/',
        ];

        foreach ([$url, $tryUrl] as $candidate) {
            foreach ($patterns as $pattern) {
                if (preg_match($pattern, $candidate, $matches)) {
                    return $matches[1];
                }
            }
        }

        return null;
    }

    private function extractVimeoId(string $url): ?string
    {
        $patterns = [
            '/vimeo\.com\/(\d+)/',
            '/player\.vimeo\.com\/video\/(\d+)/',
        ];
        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }
}
