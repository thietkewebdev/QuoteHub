# Heroku-style native PHP. On Render with Docker, use render.yaml (web + worker) instead.
# Set QUEUE_CONNECTION (database or redis) in the dashboard env.
web: php artisan serve --host=0.0.0.0 --port=${PORT:-8000}
worker: php artisan queue:work --sleep=3 --tries=3 --max-time=3600
