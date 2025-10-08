<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\CertificateTemplate;

class CertificateTemplateSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Szablon domyślny - istniejący szablon
        CertificateTemplate::create([
            'name' => 'Szablon Domyślny',
            'slug' => 'default',
            'description' => 'Standardowy szablon certyfikatu z logo i pełnymi informacjami o uczestniku i kursie.',
            'config' => [
                'blocks' => [
                    'header' => [
                        'type' => 'header',
                        'config' => [
                            'title' => 'ZAŚWIADCZENIE',
                            'show_logo' => false,
                        ]
                    ],
                    'participant_info' => [
                        'type' => 'participant_info',
                        'config' => [
                            'show_birth_info' => true,
                        ]
                    ],
                    'course_info' => [
                        'type' => 'course_info',
                        'config' => [
                            'show_duration' => true,
                            'show_description' => true,
                            'organizer_name' => 'Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji',
                        ]
                    ],
                    'instructor_signature' => [
                        'type' => 'instructor_signature',
                        'config' => []
                    ],
                    'footer' => [
                        'type' => 'footer',
                        'config' => [
                            'text' => 'Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -'
                        ]
                    ],
                ],
                'settings' => [
                    'font_family' => 'DejaVu Sans',
                    'orientation' => 'portrait',
                    'title_size' => 38,
                    'title_color' => '#000000',
                    'course_title_size' => 32,
                ]
            ],
            'is_active' => true,
        ]);

        // Szablon minimalistyczny
        CertificateTemplate::create([
            'name' => 'Szablon Minimalistyczny',
            'slug' => 'minimal',
            'description' => 'Prosty, minimalistyczny szablon z podstawowymi informacjami.',
            'config' => [
                'blocks' => [
                    'header' => [
                        'type' => 'header',
                        'config' => [
                            'title' => 'CERTYFIKAT UKOŃCZENIA',
                            'show_logo' => false,
                        ]
                    ],
                    'participant_info' => [
                        'type' => 'participant_info',
                        'config' => [
                            'show_birth_info' => false,
                        ]
                    ],
                    'course_info' => [
                        'type' => 'course_info',
                        'config' => [
                            'show_duration' => false,
                            'show_description' => false,
                            'organizer_name' => 'Platforma Nowoczesnej Edukacji',
                        ]
                    ],
                    'instructor_signature' => [
                        'type' => 'instructor_signature',
                        'config' => []
                    ],
                ],
                'settings' => [
                    'font_family' => 'DejaVu Sans',
                    'orientation' => 'portrait',
                    'title_size' => 42,
                    'title_color' => '#2c3e50',
                    'course_title_size' => 28,
                ]
            ],
            'is_active' => true,
        ]);

        // Szablon poziomy
        CertificateTemplate::create([
            'name' => 'Szablon Poziomy',
            'slug' => 'landscape',
            'description' => 'Szablon w orientacji poziomej, idealny dla bardziej rozbudowanych certyfikatów.',
            'config' => [
                'blocks' => [
                    'header' => [
                        'type' => 'header',
                        'config' => [
                            'title' => 'ZAŚWIADCZENIE O UKOŃCZENIU SZKOLENIA',
                            'show_logo' => false,
                        ]
                    ],
                    'participant_info' => [
                        'type' => 'participant_info',
                        'config' => [
                            'show_birth_info' => true,
                        ]
                    ],
                    'course_info' => [
                        'type' => 'course_info',
                        'config' => [
                            'show_duration' => true,
                            'show_description' => true,
                            'organizer_name' => 'Niepubliczny Ośrodek Doskonalenia Nauczycieli<br>Platforma Nowoczesnej Edukacji',
                        ]
                    ],
                    'instructor_signature' => [
                        'type' => 'instructor_signature',
                        'config' => []
                    ],
                    'footer' => [
                        'type' => 'footer',
                        'config' => [
                            'text' => 'Niepubliczny Ośrodek Doskonalenia Nauczycieli Platforma Nowoczesnej Edukacji<br>ul. Andrzeja Zamoyskiego 30/14, 09-320 Bieżuń<br>- AKREDYTACJA MAZOWIECKIEGO KURATORA OŚWIATY -'
                        ]
                    ],
                ],
                'settings' => [
                    'font_family' => 'DejaVu Sans',
                    'orientation' => 'landscape',
                    'title_size' => 36,
                    'title_color' => '#000000',
                    'course_title_size' => 30,
                ]
            ],
            'is_active' => true,
        ]);
    }
}
