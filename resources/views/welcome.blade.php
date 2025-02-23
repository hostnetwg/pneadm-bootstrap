<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laravel</title>

    <!-- Fonts -->
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet" />

    <!-- Styles / Scripts -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-light text-dark">
    <div class="container py-5">
        <div class="row justify-content-center text-center">
            <div class="col-lg-8 col-md-10">
                <header class="mb-5">
                    <h1 class="display-4 fw-bold">Welcome to Laravel</h1>
                    <p class="lead text-muted">Explore the powerful ecosystem and tools Laravel offers.</p>
                </header>

                @if (Route::has('login'))
                <nav class="mb-5">
                    @auth
                        <a href="{{ url('/dashboard') }}" class="btn btn-primary mx-2">Dashboard</a>
                    @else
                        <a href="{{ route('login') }}" class="btn btn-outline-secondary mx-2">Log in</a>
                        @if (Route::has('register'))
                            <a href="{{ route('register') }}" class="btn btn-outline-primary mx-2">Register</a>
                        @endif
                    @endauth
                </nav>
                @endif

                <main>
                    <div class="row g-4">
                        @foreach ([
                            ['title' => 'Documentation', 'text' => 'Laravel has wonderful documentation covering every aspect of the framework. Whether you are a newcomer or have prior experience with Laravel, we recommend reading our documentation from beginning to end.', 'link' => 'https://laravel.com/docs'],
                            ['title' => 'Laracasts', 'text' => 'Laracasts offers thousands of video tutorials on Laravel, PHP, and JavaScript development. Check them out, see for yourself, and massively level up your development skills in the process.', 'link' => 'https://laracasts.com'],
                            ['title' => 'Laravel News', 'text' => 'Laravel News is a community-driven portal and newsletter aggregating all of the latest and most important news in the Laravel ecosystem, including new package releases and tutorials.', 'link' => 'https://laravel-news.com'],
                            ['title' => 'Vibrant Ecosystem', 'text' => 'Laravel\'s robust library of first-party tools and libraries help you take your projects to the next level.', 'link' => 'https://laravel.com']
                        ] as $card)
                        <div class="col-md-6">
                            <div class="card shadow-sm h-100 border-0">
                                <div class="card-body">
                                    <h2 class="card-title fs-4 fw-semibold">{{ $card['title'] }}</h2>
                                    <p class="card-text text-muted">{{ $card['text'] }}</p>
                                    <a href="{{ $card['link'] }}" class="btn btn-primary">Learn More</a>
                                </div>
                            </div>
                        </div>
                        @endforeach
                    </div>
                </main>

                <footer class="mt-5 text-center text-muted small">
                    Laravel v{{ Illuminate\Foundation\Application::VERSION }} (PHP v{{ PHP_VERSION }})
                </footer>
            </div>
        </div>
    </div>
</body>
</html>
