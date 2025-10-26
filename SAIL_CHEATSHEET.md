# 🚢 Laravel Sail Cheatsheet

> **Remember**: All commands MUST be prefixed with `sail` in this Docker environment!

## 🔧 Setup & Configuration

```bash
# Set up sail alias (run once)
./setup-sail-alias.sh
source ~/.bashrc

# Start environment
sail up -d

# Stop environment
sail down

# Restart environment
sail restart

# Rebuild containers
sail build --no-cache
sail up -d
```

## 📦 Dependencies

```bash
# Install PHP dependencies
sail composer install
sail composer update

# Add new package
sail composer require vendor/package

# Remove package
sail composer remove vendor/package

# Install Node dependencies
sail npm install
sail npm update

# Add Node package
sail npm install package-name

# Remove Node package
sail npm uninstall package-name
```

## 🎨 Frontend Development

```bash
# Start Vite dev server (with HMR)
sail npm run dev

# Build for production
sail npm run build

# Watch and rebuild
sail npm run watch
```

## 🗄️ Database Operations

```bash
# Run migrations
sail artisan migrate

# Rollback migrations
sail artisan migrate:rollback

# Rollback all migrations
sail artisan migrate:reset

# Fresh database with seeds
sail artisan migrate:fresh --seed

# Run seeders only
sail artisan db:seed

# Run specific seeder
sail artisan db:seed --class=UserSeeder

# Check migration status
sail artisan migrate:status

# Access MySQL CLI
sail mysql

# Dump database
sail mysql pneadm_bootstrap > backup.sql

# Import database
sail mysql pneadm_bootstrap < backup.sql
```

## 🛠️ Artisan Commands

```bash
# Create controller
sail artisan make:controller ControllerName

# Create model with migration
sail artisan make:model ModelName -m

# Create model with everything
sail artisan make:model ModelName -mcr

# Create migration
sail artisan make:migration create_table_name

# Create seeder
sail artisan make:seeder SeederName

# Create request
sail artisan make:request RequestName

# Create middleware
sail artisan make:middleware MiddlewareName

# Create command
sail artisan make:command CommandName

# Create job
sail artisan make:job JobName

# Create event
sail artisan make:event EventName

# Create listener
sail artisan make:listener ListenerName

# Create policy
sail artisan make:policy PolicyName

# Create notification
sail artisan make:notification NotificationName
```

## 🧹 Cache & Optimization

```bash
# Clear all caches
sail artisan optimize:clear

# Clear application cache
sail artisan cache:clear

# Clear config cache
sail artisan config:clear

# Clear route cache
sail artisan route:clear

# Clear compiled views
sail artisan view:clear

# Cache config (production)
sail artisan config:cache

# Cache routes (production)
sail artisan route:cache

# Cache views
sail artisan view:cache

# Optimize application
sail artisan optimize
```

## 🔄 Queue Management

```bash
# Start queue worker
sail artisan queue:work

# Start queue with restart on code change
sail artisan queue:listen

# Restart queue workers
sail artisan queue:restart

# Clear failed jobs
sail artisan queue:clear

# Retry failed job
sail artisan queue:retry [id]

# Retry all failed jobs
sail artisan queue:retry all

# List failed jobs
sail artisan queue:failed

# Monitor queue
sail artisan queue:monitor
```

## 🧪 Testing

```bash
# Run all tests
sail artisan test

# Run tests in parallel
sail artisan test --parallel

# Run specific test file
sail artisan test tests/Feature/ExampleTest.php

# Run specific test method
sail artisan test --filter test_example

# Run tests with coverage
sail artisan test --coverage
```

## 🐛 Debugging & Logs

```bash
# Real-time log monitoring (Laravel Pail)
sail artisan pail

# View container logs
sail logs

# View specific service logs
sail logs laravel.test
sail logs mysql
sail logs redis

# Follow logs
sail logs -f

# Interactive shell (tinker)
sail artisan tinker

# Access container bash
sail shell

# Access as root
sail root-shell
```

## 💾 Redis Operations

```bash
# Access Redis CLI
sail redis

# Within Redis CLI:
# View all keys
KEYS *

# Get value
GET key_name

# Delete key
DEL key_name

# Flush all data
FLUSHALL

# Check Redis info
INFO
```

## 📋 Container Management

```bash
# List running containers
sail ps

# View resource usage
sail stats

# Execute command in container
sail exec laravel.test [command]

# Access container as root
sail root-shell

# Restart specific service
sail restart mysql
```

## 🔐 Permissions

```bash
# Fix storage permissions
sail shell
chmod -R 775 storage bootstrap/cache
chown -R sail:sail storage bootstrap/cache
exit
```

## 📝 Code Quality

```bash
# Format code with Pint
sail pint

# Check code without fixing
sail pint --test

# Format specific file
sail pint app/Models/User.php

# Format specific directory
sail pint app/Http/Controllers
```

## 🌐 URLs & Access

| Service | URL | Port |
|---------|-----|------|
| Application | http://localhost:8083 | 8083 |
| Vite Dev | http://localhost:5173 | 5173 |
| phpMyAdmin | http://localhost:8084 | 8084 |
| Mailpit | http://localhost:8026 | 8026 |
| MySQL | localhost:3307 | 3307 |
| Redis | localhost:6380 | 6380 |

## 🚀 Daily Workflow

### Morning Startup
```bash
cd /home/hostnet/WEB-APP/pneadm-bootstrap
sail up -d
sail npm run dev  # In separate terminal
```

### Development
```bash
# Make changes to code
sail artisan migrate  # If database changes
sail artisan test     # Run tests
sail pint             # Format code
```

### End of Day
```bash
# Ctrl+C to stop Vite dev server
sail down
```

## 🆘 Common Issues

### Port already in use
```bash
# Find process using port
lsof -i :8083
# or
netstat -tuln | grep 8083

# Kill process
kill -9 [PID]
```

### Permission denied
```bash
sail shell
chmod -R 775 storage bootstrap/cache
```

### Database connection refused
```bash
# Restart MySQL
sail restart mysql

# Check logs
sail logs mysql
```

### Container won't start
```bash
# Stop all containers
sail down

# Remove volumes (WARNING: deletes data)
sail down -v

# Rebuild
sail build --no-cache
sail up -d
```

### Node modules issues
```bash
sail shell
rm -rf node_modules package-lock.json
exit
sail npm install
```

### Composer issues
```bash
sail shell
rm -rf vendor composer.lock
exit
sail composer install
```

## 💡 Pro Tips

1. **Create alias**: Run `./setup-sail-alias.sh` to use `sail` instead of `./vendor/bin/sail`

2. **Multiple terminals**: Keep one for Vite dev server, one for commands

3. **Watch logs**: Use `sail artisan pail` for real-time debugging

4. **Queue workers**: Remember to restart after code changes: `sail artisan queue:restart`

5. **Database GUI**: Use phpMyAdmin at http://localhost:8084 for visual management

6. **Test emails**: Check Mailpit at http://localhost:8026 to see sent emails

7. **Quick restart**: `sail restart` is faster than `sail down && sail up -d`

8. **Resource usage**: Check with `sail stats` if containers are using too much resources

9. **Background tasks**: Always run Sail containers in background with `-d` flag

10. **Code formatting**: Run `sail pint` before committing to maintain code style

## 📚 Related Documentation

- `.cursorrules` - AI assistant configuration
- `.ai/DEV_ENVIRONMENT.md` - Detailed environment documentation
- `.ai/QUICK_START.md` - Quick start guide
- `.ai/ENVIRONMENT_VARIABLES.md` - Environment variables reference

## 🔗 Useful Links

- [Laravel 11 Docs](https://laravel.com/docs/11.x)
- [Laravel Sail Docs](https://laravel.com/docs/11.x/sail)
- [Bootstrap 5.3 Docs](https://getbootstrap.com/docs/5.3/)
- [Alpine.js Docs](https://alpinejs.dev/)
- [Vite Docs](https://vitejs.dev/)

---

**Remember**: In this Docker environment, ALWAYS prefix commands with `sail`! ⚓

