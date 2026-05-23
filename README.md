# task_perfomance_optimizer
Web-based system for tracking student contributions, task progress, and performance in academic group projects.

## Render Deployment

This project can run on Render as a Docker web service.

Required Render environment variables:

- `DATABASE_URL` recommended, for example a Railway MySQL URL

Alternatively, set these separate variables:

- `DB_HOST`
- `DB_PORT` default: `3306`
- `DB_NAME`
- `DB_USER`
- `DB_PASS`

The app still works locally in XAMPP because `db.php` falls back to:

- host: `localhost`
- user: `root`
- password: empty
- database: `group_tracker`
