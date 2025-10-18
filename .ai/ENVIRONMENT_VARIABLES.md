# Environment Variables Guide

## Overview
This document describes all environment variables used in the pneadm-bootstrap project. These variables are stored in the `.env` file (not tracked in git).

## Laravel Sail Environment Variables

### WWWUSER & WWWGROUP
```env
WWWUSER=1000
WWWGROUP=1000
```
- **Purpose**: User/group IDs for file permissions in Docker container
- **Default**: 1000 (typical for first user in WSL)
- **Note**: Should match your WSL user ID (check with `id -u`)

### SAIL_XDEBUG_MODE
```env
SAIL_XDEBUG_MODE=off
```
- **Purpose**: Control Xdebug behavior in Sail container
- **Options**: 
  - `off` - Xdebug disabled (default, better performance)
  - `develop` - Development helpers
  - `debug` - Step debugging
  - `coverage` - Code coverage
  - `profile` - Profiling
- **Note**: Restart containers after changing: `sail down && sail up -d`

### SAIL_XDEBUG_CONFIG
```env
SAIL_XDEBUG_CONFIG=client_host=host.docker.internal
```
- **Purpose**: Xdebug client configuration
- **Default**: Points to host machine for IDE integration
- **Note**: Usually doesn't need to be changed

## Application Configuration

### APP_NAME
```env
APP_NAME="PNE Admin Bootstrap"
```
- **Purpose**: Application name (used in emails, UI)
- **Type**: String (quote if contains spaces)

### APP_ENV
```env
APP_ENV=local
```
- **Purpose**: Application environment
- **Options**: `local`, `staging`, `production`
- **Note**: Affects error reporting, caching, debugging

### APP_KEY
```env
APP_KEY=base64:RandomGeneratedKey==
```
- **Purpose**: Application encryption key
- **Generation**: `sail artisan key:generate`
- **IMPORTANT**: Never commit this key, never share it, backup for production

### APP_DEBUG
```env
APP_DEBUG=true
```
- **Purpose**: Enable detailed error pages
- **Local**: `true` (shows stack traces)
- **Production**: `false` (shows generic errors)

### APP_TIMEZONE
```env
APP_TIMEZONE=UTC
```
- **Purpose**: Default timezone for the application
- **Recommendation**: Keep as UTC, convert in views if needed
- **Note**: Database also uses UTC (set in docker-compose.yml)

### APP_URL
```env
APP_URL=http://localhost:8083
```
- **Purpose**: Base URL for the application
- **Local**: http://localhost:8083
- **Production**: Your actual domain
- **Note**: Used for generating URLs in emails, notifications

### APP_LOCALE
```env
APP_LOCALE=en
```
- **Purpose**: Default language
- **Options**: `en`, `pl`, etc. (must have corresponding lang files)

### APP_FALLBACK_LOCALE
```env
APP_FALLBACK_LOCALE=en
```
- **Purpose**: Fallback language if translation is missing

### APP_FAKER_LOCALE
```env
APP_FAKER_LOCALE=en_US
```
- **Purpose**: Locale for Faker (test data generation)

## Database Configuration

### DB_CONNECTION
```env
DB_CONNECTION=mysql
```
- **Purpose**: Database driver
- **Options**: `mysql`, `pgsql`, `sqlite`, `sqlsrv`
- **Default**: mysql (MySQL 8.0 via Docker)

### DB_HOST
```env
DB_HOST=mysql
```
- **Purpose**: Database host
- **From Laravel/Sail**: Use `mysql` (Docker service name)
- **From Host**: Use `127.0.0.1` or `localhost`
- **Note**: This should always be `mysql` for Sail

### DB_PORT
```env
DB_PORT=3306
```
- **Purpose**: Database port
- **From Laravel/Sail**: Use `3306` (internal Docker port)
- **From Host**: Use `3307` (mapped to host)
- **Note**: This should always be `3306` for Sail

### DB_DATABASE
```env
DB_DATABASE=pneadm_bootstrap
```
- **Purpose**: Database name
- **Note**: Must match MYSQL_DATABASE in docker-compose.yml

### DB_USERNAME
```env
DB_USERNAME=sail
```
- **Purpose**: Database user
- **Default**: `sail` (created by Sail)
- **Note**: Must match MYSQL_USER in docker-compose.yml

### DB_PASSWORD
```env
DB_PASSWORD=password
```
- **Purpose**: Database password
- **Local**: Can be simple (like `password`)
- **Production**: MUST be strong and secure
- **Note**: Must match MYSQL_PASSWORD in docker-compose.yml

## Session Configuration

### SESSION_DRIVER
```env
SESSION_DRIVER=database
```
- **Purpose**: Where to store sessions
- **Options**:
  - `file` - Store in storage/framework/sessions
  - `cookie` - Store in encrypted cookies
  - `database` - Store in database (requires migration)
  - `redis` - Store in Redis (best for production)
- **Recommendation**: `redis` for production, `database` or `file` for local

### SESSION_LIFETIME
```env
SESSION_LIFETIME=120
```
- **Purpose**: Session lifetime in minutes
- **Default**: 120 (2 hours)

### SESSION_ENCRYPT
```env
SESSION_ENCRYPT=false
```
- **Purpose**: Whether to encrypt session data
- **Note**: Sessions are already secure, this adds extra layer

### SESSION_PATH
```env
SESSION_PATH=/
```
- **Purpose**: Cookie path
- **Default**: `/` (entire application)

### SESSION_DOMAIN
```env
SESSION_DOMAIN=null
```
- **Purpose**: Cookie domain
- **Default**: null (current domain)
- **Production**: Set to your domain for subdomain sharing

## Cache Configuration

### CACHE_STORE
```env
CACHE_STORE=redis
```
- **Purpose**: Default cache driver
- **Options**:
  - `file` - Store in storage/framework/cache
  - `database` - Store in database
  - `redis` - Store in Redis (recommended)
  - `array` - Memory only (testing)
- **Recommendation**: `redis` for production

### CACHE_PREFIX
```env
CACHE_PREFIX=pneadm_bootstrap_cache_
```
- **Purpose**: Prefix for cache keys
- **Note**: Useful when sharing Redis with multiple apps

## Queue Configuration

### QUEUE_CONNECTION
```env
QUEUE_CONNECTION=redis
```
- **Purpose**: Queue driver for background jobs
- **Options**:
  - `sync` - Execute immediately (no queue)
  - `database` - Store in database
  - `redis` - Store in Redis (recommended)
  - `sqs` - Amazon SQS
- **Local**: Can use `sync` for simplicity
- **Production**: Use `redis` or `sqs`

## Redis Configuration

### REDIS_HOST
```env
REDIS_HOST=redis
```
- **Purpose**: Redis server host
- **From Laravel/Sail**: Use `redis` (Docker service name)
- **From Host**: Use `127.0.0.1`

### REDIS_PASSWORD
```env
REDIS_PASSWORD=null
```
- **Purpose**: Redis password
- **Local**: null (no password)
- **Production**: Set a strong password

### REDIS_PORT
```env
REDIS_PORT=6379
```
- **Purpose**: Redis port
- **From Laravel/Sail**: Use `6379` (internal)
- **From Host**: Use `6380` (mapped)

### REDIS_CLIENT
```env
REDIS_CLIENT=phpredis
```
- **Purpose**: Redis PHP client
- **Options**: `phpredis` (faster), `predis` (pure PHP)
- **Recommendation**: `phpredis` if available

## Mail Configuration

### MAIL_MAILER
```env
MAIL_MAILER=smtp
```
- **Purpose**: Mail driver
- **Options**: `smtp`, `sendmail`, `mailgun`, `ses`, `postmark`
- **Local**: Use `smtp` with Mailpit

### MAIL_HOST
```env
MAIL_HOST=mailpit
```
- **Purpose**: SMTP server host
- **Local**: `mailpit` (Docker service for testing)
- **Production**: Your SMTP server

### MAIL_PORT
```env
MAIL_PORT=1025
```
- **Purpose**: SMTP port
- **Local**: `1025` (Mailpit SMTP port)
- **Production**: Usually `587` (TLS) or `465` (SSL)

### MAIL_USERNAME
```env
MAIL_USERNAME=null
```
- **Purpose**: SMTP username
- **Local**: null (Mailpit doesn't require auth)
- **Production**: Your SMTP username

### MAIL_PASSWORD
```env
MAIL_PASSWORD=null
```
- **Purpose**: SMTP password
- **Local**: null
- **Production**: Your SMTP password

### MAIL_ENCRYPTION
```env
MAIL_ENCRYPTION=null
```
- **Purpose**: Encryption method
- **Options**: `tls`, `ssl`, null
- **Production**: Usually `tls`

### MAIL_FROM_ADDRESS
```env
MAIL_FROM_ADDRESS="hello@example.com"
```
- **Purpose**: Default sender email
- **Note**: Should be a valid email from your domain

### MAIL_FROM_NAME
```env
MAIL_FROM_NAME="${APP_NAME}"
```
- **Purpose**: Default sender name
- **Note**: Can reference other variables with `${VAR_NAME}`

## Logging Configuration

### LOG_CHANNEL
```env
LOG_CHANNEL=stack
```
- **Purpose**: Logging channel
- **Options**: `stack`, `single`, `daily`, `slack`, `papertrail`, `stderr`
- **Default**: `stack` (multiple channels)

### LOG_STACK
```env
LOG_STACK=single
```
- **Purpose**: Channels to include in stack
- **Options**: `single`, `daily`, `slack`, etc.

### LOG_DEPRECATIONS_CHANNEL
```env
LOG_DEPRECATIONS_CHANNEL=null
```
- **Purpose**: Where to log deprecation warnings
- **Options**: null (use default), or any channel name

### LOG_LEVEL
```env
LOG_LEVEL=debug
```
- **Purpose**: Minimum log level
- **Options**: `debug`, `info`, `notice`, `warning`, `error`, `critical`, `alert`, `emergency`
- **Local**: `debug` (log everything)
- **Production**: `error` or `warning`

## Vite Configuration

### VITE_PORT
```env
VITE_PORT=5173
```
- **Purpose**: Vite dev server port
- **Default**: 5173
- **Note**: Must match port in docker-compose.yml

## Broadcasting Configuration

### BROADCAST_CONNECTION
```env
BROADCAST_CONNECTION=log
```
- **Purpose**: Broadcasting driver
- **Options**: `log`, `redis`, `pusher`, `ably`
- **Note**: For real-time events (websockets)

## Filesystem Configuration

### FILESYSTEM_DISK
```env
FILESYSTEM_DISK=local
```
- **Purpose**: Default filesystem driver
- **Options**: `local`, `public`, `s3`
- **Note**: `public` for publicly accessible files

### AWS_* (if using S3)
```env
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=
AWS_USE_PATH_STYLE_ENDPOINT=false
```
- **Purpose**: Amazon S3 configuration
- **Note**: Only needed if using S3 for file storage

## Third-Party Services (Project Specific)

### PUBLIGO_API_KEY
```env
PUBLIGO_API_KEY=your_api_key_here
```
- **Purpose**: Publigo API authentication
- **Note**: See PUBLIGO_API_SETUP.md for details

### PUBLIGO_WEBHOOK_SECRET
```env
PUBLIGO_WEBHOOK_SECRET=your_webhook_secret
```
- **Purpose**: Publigo webhook verification
- **Note**: See PUBLIGO_WEBHOOK_SETUP.md for details

### SENDY_API_KEY
```env
SENDY_API_KEY=your_sendy_api_key
```
- **Purpose**: Sendy email service integration
- **Note**: See SENDY_INTEGRATION.md for details

### SENDY_INSTALLATION_URL
```env
SENDY_INSTALLATION_URL=https://your-sendy-url.com
```
- **Purpose**: Your Sendy installation URL
- **Note**: Should be full URL including protocol

## Environment File Management

### .env (LOCAL)
- Contains your local development settings
- **NOT** tracked in git
- Each developer has their own copy
- Never commit this file

### .env.example (TEMPLATE)
- Template for creating .env
- **IS** tracked in git
- Should have all keys with placeholder values
- Update when adding new variables

### .env.production (PRODUCTION)
- Production environment settings
- **NOT** tracked in git
- Stored securely on production server
- Contains sensitive production credentials

## Setting Up .env

### First Time Setup
```bash
# Copy example file
cp .env.example .env

# Generate application key
sail artisan key:generate

# Update database credentials (should work out of box with Sail)
# Update mail settings (Mailpit is pre-configured)
# Add any API keys you need
```

### Accessing Environment Variables in Code

#### In PHP/Laravel
```php
// Get value
$appName = env('APP_NAME');

// Get value with default
$debug = env('APP_DEBUG', false);

// In config files (preferred)
// config/app.php
'name' => env('APP_NAME', 'Laravel'),

// Then access via config helper
$appName = config('app.name');
```

#### In Blade Templates
```blade
{{ config('app.name') }}
{{ env('APP_NAME') }} {{-- Avoid this, use config() --}}
```

#### In JavaScript (Via Vite)
Only variables prefixed with `VITE_` are available:
```javascript
const apiUrl = import.meta.env.VITE_API_URL;
```

## Security Best Practices

1. **Never Commit .env**: Always in .gitignore
2. **Strong Keys in Production**: Use strong passwords and keys
3. **Rotate Secrets**: Change API keys periodically
4. **Backup .env**: Store production .env securely
5. **Environment Specific**: Different values for local/staging/production
6. **Minimal Exposure**: Only expose what's needed to frontend (VITE_ prefix)
7. **No Defaults in Code**: Use .env.example for documentation

## Common Issues

### Database Connection Failed
- Check `DB_HOST=mysql` (not 127.0.0.1)
- Check `DB_PORT=3306` (not 3307)
- Check `DB_DATABASE` matches docker-compose.yml
- Verify containers are running: `sail ps`

### Cache Issues
- Clear config cache: `sail artisan config:clear`
- Clear all caches: `sail artisan optimize:clear`

### Session Not Working
- Check SESSION_DRIVER is valid
- If using database: `sail artisan session:table && sail artisan migrate`
- If using redis: Verify Redis is running

### Mail Not Sending
- Check Mailpit is running: http://localhost:8026
- Verify MAIL_HOST=mailpit and MAIL_PORT=1025
- Check `sail logs mailpit` for errors

## For AI Assistants

When suggesting environment variable changes:
1. ✅ Always mention to restart Sail if needed: `sail down && sail up -d`
2. ✅ Clear config cache after changes: `sail artisan config:clear`
3. ✅ Remember Docker service names (mysql, redis, mailpit) not localhost
4. ✅ Document security implications of changes
5. ✅ Suggest updating .env.example if adding new variables

