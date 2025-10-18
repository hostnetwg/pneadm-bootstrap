# AI Context Documentation

This directory contains documentation specifically designed to provide context for AI assistants (like Cursor AI, GitHub Copilot, etc.) and developers working on this project.

## 📁 Documentation Files

### [DEV_ENVIRONMENT.md](./DEV_ENVIRONMENT.md)
**Comprehensive development environment documentation**

- Complete system architecture overview
- All Docker services and their configurations
- Port mappings and service URLs
- Detailed command reference
- Troubleshooting guides
- AI assistant guidelines

**Best for**: Understanding the entire development setup in detail.

### [QUICK_START.md](./QUICK_START.md)
**Fast reference guide for daily development**

- Quick setup instructions
- Daily workflow commands
- Common commands cheatsheet
- Important reminders about Sail usage
- Quick troubleshooting tips

**Best for**: Quick lookups during development, onboarding new developers.

### [ENVIRONMENT_VARIABLES.md](./ENVIRONMENT_VARIABLES.md)
**Complete guide to environment variables**

- All .env variables explained
- Laravel Sail specific variables
- Database, cache, queue configuration
- Third-party service credentials
- Security best practices
- Common issues and solutions

**Best for**: Understanding and configuring environment variables, troubleshooting configuration issues.

### [FILES_INDEX.txt](./FILES_INDEX.txt)
**Visual documentation index and file tree**

- All documentation files listed
- File sizes and purposes
- Relationships and hierarchy
- Quick access commands
- Best practices

**Best for**: Getting an overview of all documentation, understanding file structure.

### [SETUP_SUMMARY.md](./SETUP_SUMMARY.md)
**Complete setup summary and usage guide**

- What was created and why
- Benefits for AI and developers
- How to use the documentation
- Testing the setup
- Next steps and checklist

**Best for**: Understanding the complete documentation system, onboarding, getting started.

## 🎯 For AI Assistants

### Critical Rules
1. **ALWAYS** prefix PHP/Laravel/Composer/NPM commands with `sail`
2. Project runs in Docker via Laravel Sail on Windows WSL2
3. Database uses port 3307 (not 3306) from host
4. Frontend uses Bootstrap 5.3.3 (not Tailwind)
5. Laravel version is 11.31+ (use modern syntax)

### Quick Reference
```bash
# Correct command format
sail artisan migrate
sail composer require vendor/package
sail npm install

# Wrong format (DON'T USE)
php artisan migrate
composer require vendor/package
npm install
```

### Project Context
- **Framework**: Laravel 11.31+
- **PHP**: 8.4 (in Docker container)
- **Frontend**: Bootstrap 5.3.3 + Alpine.js 3.4.2
- **Database**: MySQL 8.0
- **Build Tool**: Vite 6.0
- **Environment**: Windows WSL2 + Docker + Laravel Sail

## 🔧 Project Root Files

### ../.cursorrules
Main configuration file for Cursor AI. Contains:
- Development environment rules
- Command execution guidelines
- Tech stack information
- Code style preferences
- Common tasks and workflows

### ../setup-sail-alias.sh
Bash script to set up the `sail` alias in your shell configuration. Run once:
```bash
./setup-sail-alias.sh
source ~/.bashrc  # or ~/.zshrc
```

## 📚 Related Documentation

### Project-Specific Documentation (in project root)
- `FORM_ORDERS_README.md` - Form orders system
- `LOGO_SYSTEM_README.md` - Logo management system
- `PUBLIGO_API_SETUP.md` - Publigo API integration
- `PUBLIGO_WEBHOOK_SETUP.md` - Webhook configuration
- `SENDY_INTEGRATION.md` - Sendy email integration
- `SECURITY_RECOMMENDATIONS.md` - Security guidelines
- `PRODUCTION_FILE_MIGRATION.md` - Production deployment guide

### External Resources
- [Laravel 11 Documentation](https://laravel.com/docs/11.x)
- [Laravel Sail Documentation](https://laravel.com/docs/11.x/sail)
- [Bootstrap 5.3 Documentation](https://getbootstrap.com/docs/5.3/)
- [Alpine.js Documentation](https://alpinejs.dev/)
- [Vite Documentation](https://vitejs.dev/)

## 🎨 Project Structure Context

```
pneadm-bootstrap/
├── .ai/                          # ← You are here
│   ├── README.md                 # This file
│   ├── DEV_ENVIRONMENT.md        # Detailed environment docs
│   └── QUICK_START.md            # Quick reference
├── .cursorrules                  # Cursor AI configuration
├── setup-sail-alias.sh           # Sail alias setup script
├── app/                          # Laravel application code
├── bootstrap/                    # Laravel bootstrap files
├── config/                       # Configuration files
├── database/                     # Migrations, seeders, factories
├── resources/                    # Views, CSS, JS
│   ├── css/
│   │   └── app.css              # Main CSS (imports Bootstrap)
│   └── js/
│       └── app.js               # Main JS (Alpine.js entry)
├── routes/                       # Route definitions
│   ├── web.php                  # Web routes
│   ├── api.php                  # API routes
│   └── console.php              # Console routes
├── docker-compose.yml            # Docker services configuration
├── composer.json                 # PHP dependencies
├── package.json                  # NPM dependencies
└── vite.config.js               # Vite configuration
```

## 💡 Usage Tips

### For Developers
1. Read `QUICK_START.md` first for immediate productivity
2. Refer to `DEV_ENVIRONMENT.md` for deep dives
3. Run `./setup-sail-alias.sh` to simplify commands
4. Keep `.cursorrules` updated with project-specific conventions

### For AI Assistants
1. Always check `.cursorrules` for project-specific rules
2. Use `DEV_ENVIRONMENT.md` for accurate environment context
3. Follow the "ALWAYS use sail" principle strictly
4. Consider Bootstrap 5 conventions for frontend suggestions
5. Use Laravel 11 syntax (avoid deprecated methods)

## 🔄 Keeping Documentation Updated

When project configuration changes:
- [ ] Update `DEV_ENVIRONMENT.md` with new services/ports
- [ ] Update `.cursorrules` with new conventions
- [ ] Update `QUICK_START.md` if workflow changes
- [ ] Update this README if new docs are added

## ❓ Questions?

If you're unsure about:
- **Environment setup**: Read `DEV_ENVIRONMENT.md`
- **Quick commands**: Check `QUICK_START.md`
- **AI behavior**: Review `.cursorrules`
- **Specific features**: See project-specific docs in root

---

**Last Updated**: October 2025  
**Project**: pneadm-bootstrap  
**Laravel Version**: 11.31+  
**Environment**: Windows WSL2 + Docker + Laravel Sail

