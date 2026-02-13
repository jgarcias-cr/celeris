# Docker setup for PHP 8.4

Quick commands:

Build and start services:
```bash
docker compose up --build -d
```

Stop services:
```bash
docker compose down
```

MVC app is served at http://localhost:8082 and API app is served at http://localhost:8081.
PHP-FPM listens on port 9000 inside the compose network.
