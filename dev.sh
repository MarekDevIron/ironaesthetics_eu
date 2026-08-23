#!/bin/bash
# Dev pomocníky pre eu-prepinac (PHP nemusí byť nainštalované lokálne).
# Použitie:
#   ./dev.sh test [kod]   — CLI lookup test proti živým DB
#   ./dev.sh serve        — spustí dev server na http://localhost:8070
set -e
DIR="$(cd "$(dirname "$0")" && pwd)"
IMAGE="php:7.4-cli"

case "${1:-}" in
  test)
    docker run --rm -v "$DIR":/app -w /app $IMAGE \
      sh -c "docker-php-ext-install pdo_mysql >/dev/null 2>&1 && php test.php '${2:-P2805A15382}'"
    ;;
  serve)
    docker rm -f eu-prepinac-dev >/dev/null 2>&1 || true
    docker run -d --name eu-prepinac-dev -p 8070:8080 -v "$DIR":/app -w /app $IMAGE \
      sh -c "docker-php-ext-install pdo_mysql >/dev/null 2>&1 && php -S 0.0.0.0:8080 index.php"
    echo "bezi: http://localhost:8070"
    ;;
  stop)
    docker rm -f eu-prepinac-dev >/dev/null 2>&1 || true
    ;;
  *)
    echo "pouzitie: $0 test|serve|stop"
    exit 1
    ;;
esac
