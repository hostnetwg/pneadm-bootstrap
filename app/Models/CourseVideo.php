<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Traits\LogsActivity;

class CourseVideo extends Model
{
    use HasFactory, LogsActivity;

    protected $fillable = [
        'course_id',
        'video_url',
        'platform',
        'title',
        'order'
    ];

    protected $casts = [
        'order' => 'integer'
    ];

    /**
     * Relacja do kursu
     */
    public function course()
    {
        return $this->belongsTo(Course::class);
    }

    /**
     * Pobiera pełny URL do embedowania wideo
     */
    public function getEmbedUrl(): string
    {
        $raw = $this->normalizedVideoUrl();

        if ($this->platform === 'youtube') {
            $videoId = $this->extractYouTubeId($raw);
            if ($videoId) {
                return "https://www.youtube.com/embed/{$videoId}";
            }

            return $raw;
        }

        if ($this->platform === 'vimeo') {
            $videoId = $this->extractVimeoId($raw);
            if ($videoId) {
                return "https://player.vimeo.com/video/{$videoId}?badge=0&autopause=0&player_id=0&app_id=58479";
            }

            return $raw;
        }

        return $raw;
    }

    private function normalizedVideoUrl(): string
    {
        $url = trim((string) $this->video_url);
        if ($url === '') {
            return '';
        }

        return html_entity_decode($url, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    /**
     * Wyciąga ID wideo z URL YouTube
     */
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

    /**
     * Wyciąga ID wideo z URL Vimeo
     */
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

