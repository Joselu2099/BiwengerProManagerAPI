# scripts/start-local.sh
# Start the application in development (local) mode using PHP built-in server.
# Usage:
#   ./scripts/start-local.sh           # uses defaults: localhost:8000
#   ./scripts/start-local.sh 8081      # use port 8081
#   ./scripts/start-local.sh 8081 0.0.0.0
#!/usr/bin/env bash
# scripts/start-local.sh
# Start the application in development (local) mode using PHP built-in server.
# Usage:
#   ./scripts/start-local.sh           # uses defaults: localhost:8000
#   ./scripts/start-local.sh 8081      # use port 8081
#   ./scripts/start-local.sh 8081 0.0.0.0

PORT=${1:-8000}
HOST=${2:-localhost}

# Resolve script directory and repo root (parent of scripts/)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/.." && pwd)"

cd "$REPO_ROOT" || exit 1

# Load config/.env if present and export variables
if [ -r "$REPO_ROOT/config/.env" ]; then
	echo "Loading configuration from $REPO_ROOT/config/.env"
	# shellcheck disable=SC1090
	while IFS= read -r line; do
		line="$(echo "$line" | sed -e 's/^\s*//;s/\s*$//')"
		case "$line" in
			''|\#*) continue ;;
		esac
		if [[ "$line" != *"="* ]]; then continue; fi
		key="$(echo "$line" | cut -d '=' -f1 | sed -e 's/\s*$//')"
		# get the rest after the first '=' and trim spaces
		val="$(echo "$line" | cut -d '=' -f2- | sed -e 's/^\s*//;s/\s*$//')"
		# remove surrounding single or double quotes if present
		if [[ ( ${val:0:1} == '"' && ${val: -1} == '"' ) || ( ${val:0:1} == "'" && ${val: -1} == "'" ) ]]; then
			val="${val:1:$((${#val}-2))}"
		fi
		export "$key=$val"
	done < "$REPO_ROOT/config/.env"
fi

# Determine effective port/host: CLI args override config, config overrides defaults
if [ "$1" != "" ]; then
	PORT="$1"
elif [ -n "$APP_PORT" ]; then
	PORT="$APP_PORT"
fi
if [ -n "$APP_HOST" ]; then
	HOST="$APP_HOST"
fi

echo "APP_ENV set to '$APP_ENV'"
echo "Serving project from: $REPO_ROOT/public"
echo "Starting PHP built-in server at http://$HOST:$PORT (press Ctrl+C to stop)"

php -S "$HOST:$PORT" -t public

# When server exits, nothing else to do.
# When server exits, nothing else to do.
