# task_perfomance_optimizer
Web-based system for tracking student contributions, task progress, and performance in academic group projects.

## Render Deployment

This project runs on Render as a Docker web service.

Live service URL:

- `https://task-perfomance-optimizer.onrender.com`

Required Render environment variable:

- `DATABASE_URL`

Example format:

```text
mysql://USER:PASSWORD@HOST:PORT/DATABASE
```

Deployment files:

- `Dockerfile` builds the PHP/Apache container.
- `render/start.sh` configures Apache to listen on Render's assigned `PORT`.
- `render.yaml` defines the Render web service and health check.
- `health.php` provides the Render health endpoint.

After adding or changing `DATABASE_URL` in Render, run:

```text
Manual Deploy -> Clear build cache & deploy
```

Local XAMPP fallback:

- host: `localhost`
- user: `root`
- password: empty
- database: `group_tracker`
