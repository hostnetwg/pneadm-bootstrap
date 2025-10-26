# Quick Start Guide - pneadm-bootstrap

## For AI Assistants
This project runs in Docker using Laravel Sail. **ALWAYS use `sail` prefix for all commands.**

## First Time Setup

### 1. Set up Sail alias (Optional but recommended)
Add to your `~/.bashrc` or `~/.zshrc`:
```bash
alias sail='[ -f sail ] && sh sail || sh vendor/bin/sail'
```

Then reload:
```bash
source ~/.bashrc
```

### 2. Start the environment
```bash
sail up -d
```

### 3. Install dependencies (if needed)
```bash
sail composer install
sail npm install
```

### 4. Run migrations
```bash
sail artisan migrate
```

### 5. Start Vite dev server
```bash
sail npm run dev
```

## Daily Development Workflow

### Start working
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail up -d
sail npm run dev  # In a separate terminal
```

### Access the application
- **App**: http://localhost:8083
- **phpMyAdmin**: http://localhost:8084
- **Mailpit**: http://localhost:8026

### Stop working
```bash
sail down
```

## Common Commands Cheatsheet

| Task | Command |
|------|---------|
| Create controller | `sail artisan make:controller ControllerName` |
| Create model | `sail artisan make:model ModelName -m` |
| Run migration | `sail artisan migrate` |
| Rollback migration | `sail artisan migrate:rollback` |
| Fresh database | `sail artisan migrate:fresh --seed` |
| Run tests | `sail artisan test` |
| Clear cache | `sail artisan cache:clear` |
| Access shell | `sail shell` |
| Access MySQL | `sail mysql` |
| View logs | `sail artisan pail` |
| Format code | `sail pint` |
| Install package | `sail composer require vendor/package` |
| Build assets | `sail npm run build` |

## Important Notes

### ⚠️ ALWAYS Use 'sail' Prefix
❌ **Wrong**: `php artisan migrate`  
✅ **Correct**: `sail artisan migrate`

❌ **Wrong**: `composer require package`  
✅ **Correct**: `sail composer require package`

❌ **Wrong**: `npm install`  
✅ **Correct**: `sail npm install`

### Tech Stack Quick Reference
- **Laravel**: 11.31+
- **PHP**: 8.4 (in Docker)
- **Frontend**: Bootstrap 5.3.3 + Alpine.js 3.4.2
- **Database**: MySQL 8.0 (port 3307)
- **Cache**: Redis (port 6380)
- **Build**: Vite 6.0

### Ports
| Service | Port |
|---------|------|
| Laravel | 8083 |
| MySQL | 3307 |
| Redis | 6380 |
| phpMyAdmin | 8084 |
| Mailpit Web | 8026 |
| Vite | 5173 |

### Environment Files
- `.env` - Application configuration (not in git)
- `docker-compose.yml` - Container configuration
- `.cursorrules` - AI assistant rules
- `.ai/DEV_ENVIRONMENT.md` - Detailed environment docs

## Troubleshooting

### Containers won't start
```bash
sail down
sail up -d
```

### Permission errors
```bash
sail shell
chmod -R 775 storage bootstrap/cache
```

### Database issues
```bash
sail artisan migrate:fresh --seed
```

### Port conflicts
Check if ports 8083, 3307, 6380, 8084, 8026, 5173 are available:
```bash
netstat -tuln | grep -E '8083|3307|6380|8084|8026|5173'
```

### Vite not connecting
Make sure dev server is running:
```bash
sail npm run dev
```

## For AI Code Suggestions

When generating code or commands:
1. ✅ Use `sail` prefix for all PHP/NPM/Composer commands
2. ✅ Follow Laravel 11 conventions
3. ✅ Use Bootstrap 5 classes for UI
4. ✅ Use modern PHP 8.2+ syntax
5. ✅ Remember database is on port 3307 from host
6. ✅ Consider Alpine.js for interactive components

## Resources
- Laravel Docs: https://laravel.com/docs/11.x
- Bootstrap Docs: https://getbootstrap.com/docs/5.3/
- Alpine.js Docs: https://alpinejs.dev/
- Sail Docs: https://laravel.com/docs/11.x/sail

