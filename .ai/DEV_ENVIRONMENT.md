# Development Environment Documentation

## Overview
This document describes the development environment setup for the pneadm-bootstrap project. This information is used by AI assistants to provide accurate context-aware suggestions.

## Host System
- **Operating System**: Windows with WSL2
- **WSL Kernel**: 6.6.87.2-microsoft-standard-WSL2
- **Project Path**: `/home/hostnet/WEB-APP/pneadm-bootstrap`

## Containerization Stack

### Docker Compose Services
The project runs entirely in Docker containers managed by Laravel Sail.

#### 1. Application Container (`laravel.test`)
- **Base Image**: `sail-8.4/app` (PHP 8.4)
- **Web Server**: Built-in PHP server / Nginx
- **HTTP Port**: 8083 (host) → 80 (container)
- **Vite Port**: 5173 (host) → 5173 (container)
- **Working Directory**: `/var/www/html`
- **User**: WWWUSER from environment
- **Xdebug**: Available (controlled via SAIL_XDEBUG_MODE)

#### 2. MySQL Container (`mysql`)
- **Image**: `mysql/mysql-server:8.0`
- **Port**: 3307 (host) → 3306 (container)
- **Timezone**: UTC (+00:00)
- **Root Password**: From `DB_PASSWORD` env variable
- **Database**: From `DB_DATABASE` env variable
- **Persistent Storage**: `sail-mysql` Docker volume

#### 3. Redis Container (`redis`)
- **Image**: `redis:alpine`
- **Port**: 6380 (host) → 6379 (container)
- **Use Cases**: Cache, sessions, queues
- **Persistent Storage**: `sail-redis` Docker volume

#### 4. Mailpit Container (`mailpit`)
- **Image**: `axllent/mailpit:latest`
- **SMTP Port**: 1027 (host) → 1026 (container)
- **Web UI Port**: 8026 (host) → 8025 (container)
- **Purpose**: Local email testing and debugging

#### 5. phpMyAdmin Container (`phpmyadmin`)
- **Image**: `phpmyadmin/phpmyadmin`
- **Port**: 8084 (host) → 80 (container)
- **Upload Limit**: 256M
- **Purpose**: Visual MySQL database management

### Network
- **Network Name**: `sail`
- **Driver**: bridge
- **Container Communication**: All containers communicate via `sail` network

## Application Stack

### Backend
- **Framework**: Laravel 11.31+
- **PHP Version**: 8.2+ (running on 8.4 in container)
- **Key Packages**:
  - `barryvdh/laravel-dompdf` (v3.1) - PDF generation
  - `guzzlehttp/guzzle` (v7.9) - HTTP client
  - `laravel/tinker` (v2.9) - Interactive REPL
  - `laravel/breeze` (v2.3) - Authentication scaffolding

### Frontend
- **CSS Framework**: Bootstrap 5.3.3
- **JavaScript Framework**: Alpine.js 3.4.2
- **Build Tool**: Vite 6.0
- **Package Manager**: NPM
- **HTTP Client**: Axios 1.7.4
- **Additional**: @popperjs/core 2.11.8 (for Bootstrap)

### Asset Pipeline
- **Tool**: Vite 6.0 with Laravel plugin
- **Entry Points**:
  - `resources/css/app.css`
  - `resources/js/app.js`
- **Dev Server**: Hot Module Replacement (HMR) on port 5173
- **Build Command**: `sail npm run build`
- **Dev Command**: `sail npm run dev`

## Development Commands Reference

### Starting/Stopping Environment
```bash
# Start all services in background
sail up -d

# Stop all services
sail down

# Restart services
sail restart

# View logs
sail logs

# View logs for specific service
sail logs laravel.test
```

### Application Commands (Always use 'sail' prefix!)
```bash
# Artisan commands
sail artisan [command]

# Composer commands
sail composer [command]

# NPM commands
sail npm [command]

# PHP commands
sail php [script]

# Access container shell
sail shell

# Access MySQL CLI
sail mysql

# Access Redis CLI
sail redis
```

### Common Development Tasks
```bash
# Install dependencies
sail composer install
sail npm install

# Run migrations
sail artisan migrate

# Seed database
sail artisan db:seed

# Run tests
sail artisan test

# Start frontend dev server
sail npm run dev

# Build production assets
sail npm run build

# Clear caches
sail artisan cache:clear
sail artisan config:clear
sail artisan view:clear

# Code formatting
sail pint
```

## Key Environment Considerations

### 1. Command Execution
**CRITICAL**: All PHP, Artisan, Composer, and NPM commands MUST be executed through Sail:
- ✅ `sail artisan migrate`
- ❌ `php artisan migrate`

The project runs in Docker containers, so direct host commands won't work correctly.

### 2. File Permissions
- Files created inside containers are owned by the `sail` user
- Host file system is mounted at `/var/www/html` in container
- Permissions are managed via WWWUSER and WWWGROUP environment variables

### 3. Database Connections
- **From Host**: Use `localhost:3307`
- **From Container**: Use `mysql:3306`
- **From Laravel**: Configure in `.env` as `DB_HOST=mysql` and `DB_PORT=3306`

### 4. URLs and Ports
- **Application**: http://localhost:8083
- **Vite Dev Server**: http://localhost:5173
- **phpMyAdmin**: http://localhost:8084
- **Mailpit UI**: http://localhost:8026

### 5. Hot Module Replacement (Vite)
- Vite dev server must be running for HMR: `sail npm run dev`
- Vite server is exposed on port 5173
- Changes to CSS/JS auto-refresh browser

### 6. Queue Workers
- Queue workers run in separate processes
- Must be restarted after code changes: `sail artisan queue:restart`
- For development, can use: `sail artisan queue:work`

## Project-Specific Features

### Custom Middleware
- `publigo.webhook` - PubligoWebhookMiddleware
- `noindex` - NoIndexMiddleware
- `check.user.status` - CheckUserStatus

### API Routes
- API routes defined in `routes/api.php`
- Web routes in `routes/web.php`
- Console commands in `routes/console.php`
- Health check endpoint: `/up`

### Bootstrap Frontend Integration
- Bootstrap 5.3.3 is the UI framework (not Laravel's bootstrap folder)
- Components and utilities follow Bootstrap conventions
- No jQuery - Bootstrap 5 uses vanilla JavaScript
- Popper.js included for interactive components

### Development Tools
- **Laravel Pail**: Real-time log monitoring (`sail artisan pail`)
- **Laravel Tinker**: REPL for testing code (`sail artisan tinker`)
- **Xdebug**: Available when SAIL_XDEBUG_MODE is set
- **Laravel Pint**: Code formatter (`sail pint`)

## AI Assistant Guidelines

When providing suggestions:
1. **Always** prefix PHP/Laravel/NPM commands with `sail`
2. Consider the Laravel 11 syntax and conventions
3. Use Bootstrap 5 classes for frontend components
4. Remember database is on port 3307 when connecting from host
5. Suggest Sail commands for Docker management
6. Account for WSL2 environment peculiarities
7. Use modern PHP 8.2+ syntax (readonly, enums, typed properties)
8. Follow PSR-12 coding standards

## Troubleshooting

### Container Issues
```bash
# Rebuild containers
sail build --no-cache

# Restart from scratch
sail down -v
sail up -d
```

### Permission Issues
```bash
sail shell
chmod -R 775 storage bootstrap/cache
chown -R sail:sail storage bootstrap/cache
```

### Database Issues
```bash
# Reset database
sail artisan migrate:fresh --seed

# Check migration status
sail artisan migrate:status
```

### Asset Build Issues
```bash
# Clear npm cache
sail npm cache clean --force

# Reinstall node modules
sail shell
rm -rf node_modules
exit
sail npm install
```

## Additional Resources
- [Laravel 11 Documentation](https://laravel.com/docs/11.x)
- [Laravel Sail Documentation](https://laravel.com/docs/11.x/sail)
- [Bootstrap 5.3 Documentation](https://getbootstrap.com/docs/5.3/)
- [Alpine.js Documentation](https://alpinejs.dev/)
- [Vite Documentation](https://vitejs.dev/)

