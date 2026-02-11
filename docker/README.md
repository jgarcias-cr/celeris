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

App is served at http://localhost:8080 (nginx) and PHP-FPM listens on port 9000 inside the compose network.
