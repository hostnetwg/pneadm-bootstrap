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
        if ($this->platform === 'youtube') {
            // Konwersja URL YouTube na embed URL
            $videoId = $this->extractYouTubeId($this->video_url);
            return $videoId ? "https://www.youtube.com/embed/{$videoId}" : $this->video_url;
        } elseif ($this->platform === 'vimeo') {
            // Konwersja URL Vimeo na embed URL
            $videoId = $this->extractVimeoId($this->video_url);
            return $videoId ? "https://player.vimeo.com/video/{$videoId}" : $this->video_url;
        }
        
        return $this->video_url;
    }

    /**
     * Wyciąga ID wideo z URL YouTube
     */
    private function extractYouTubeId(string $url): ?string
    {
        $patterns = [
            '/(?:youtube\.com\/watch\?v=|youtu\.be\/|youtube\.com\/embed\/)([a-zA-Z0-9_-]{11})/',
            '/youtube\.com\/watch\?.*v=([a-zA-Z0-9_-]{11})/'
        ];

        foreach ($patterns as $pattern) {
            if (preg_match($pattern, $url, $matches)) {
                return $matches[1];
            }
        }

        return null;
    }

    /**
     * Wyciąga ID wideo z URL Vimeo
     */
    private function extractVimeoId(string $url): ?string
    {
        if (preg_match('/vimeo\.com\/(\d+)/', $url, $matches)) {
            return $matches[1];
        }

        return null;
    }
}

