#!/bin/bash
# Runs one throwaway site in two containers that share its folder, and checks
# what has to hold on several nodes: a login is valid on both, writes to one
# record from both lose nothing, a scheduled task runs once, and request
# statistics written from both stay whole.
#
# Usage: core/tests/multi-node.sh        (needs Docker; not part of test:run)
set -euo pipefail

root="$(cd "$(dirname "$0")/../.." && pwd)"
site="$(mktemp -d "${TMPDIR:-/tmp}/nimbly-nodes-XXXXXX")"
compose=(docker compose -p nimbly-nodes -f "$root/docker/dev/docker-compose.nodes.yml")
export NIMBLY_NODES_SITE="$site"
port_a="${NIMBLY_NODES_PORT_A:-8091}"
port_b="${NIMBLY_NODES_PORT_B:-8092}"
email=admin@nodes.test
password=Nodes-test-123

cleanup() {
    "${compose[@]}" down --timeout 2 >/dev/null 2>&1 || true
    rm -rf "$site"
}
trap cleanup EXIT

fail() { echo "FAIL: $1" >&2; exit 1; }
node() { local name="$1"; shift; "${compose[@]}" exec -T -u www-data "$name" "$@"; }

chmod 755 "$site"
(cd "$root" && git ls-files -z core index.php | tar -c --null -T - ) | tar -x -C "$site"
printf 'APP_ENV=dev\nPEPPER=%s\nSITE_NAME=Nodes\nSTATS_ENABLED=1\n' "$(head -c 24 /dev/urandom | od -An -tx1 | tr -d ' \n')" > "$site/.env"

"${compose[@]}" up -d >/dev/null 2>&1 || fail "the containers did not start"
"${compose[@]}" exec -T -e ADMIN_EMAIL="$email" -e ADMIN_PASSWORD="$password" -e SITE_NAME=Nodes -u www-data node-a \
    php core/cli/nimbly.php system:setup >/dev/null || fail "system:setup"
for port in "$port_a" "$port_b"; do
    curl -sf --retry 20 --retry-delay 1 --retry-all-errors -o /dev/null "http://localhost:$port/health" || fail "no answer on port $port"
done

# 1. A login on one node is a login on the other.
jar="$site/cookies.txt"
form="$(curl -s -c "$jar" "http://localhost:$port_a/login")"
fields=()
while IFS= read -r input; do
    name="$(sed -n 's/.*name="\([^"]*\)".*/\1/p' <<<"$input")"
    value="$(sed -n 's/.*value="\([^"]*\)".*/\1/p' <<<"$input")"
    [ -n "$name" ] && fields+=(--data-urlencode "$name=$value")
done < <(grep -o '<input[^>]*type="hidden"[^>]*>' <<<"$form")
curl -s -o /dev/null -b "$jar" -c "$jar" "${fields[@]}" \
    --data-urlencode "email=$email" --data-urlencode "password=$password" "http://localhost:$port_a/login"
for port in "$port_a" "$port_b"; do
    status="$(curl -s -o /dev/null -w '%{http_code}' -b "$jar" "http://localhost:$port/nb-admin/")"
    [ "$status" = 200 ] || fail "the admin answers $status on port $port after a login on port $port_a"
done
status="$(curl -s -o /dev/null -w '%{http_code}' "http://localhost:$port_b/nb-admin/")"
[ "$status" != 200 ] || fail "the admin is open without a login"
echo "ok   a login on one node is valid on the other"

# 2. Both nodes write other fields of one record, 150 times each: nothing is lost.
mkdir -p "$site/ext/data/notes"
echo '{"fields":[]}' > "$site/ext/data/notes/.meta"
echo '{"uuid":"n1","title":"first"}' > "$site/ext/data/notes/n1"
node node-a php core/tests/data-write-lock-test.php worker /var/www/nimbly a 150 & worker_a=$!
node node-b php core/tests/data-write-lock-test.php worker /var/www/nimbly b 150 & worker_b=$!
wait "$worker_a" && wait "$worker_b" || fail "a node could not save its update"
record="$(cat "$site/ext/data/notes/n1")"
grep -q '"title":"first"' <<<"$record" && grep -q '"wa":150' <<<"$record" && grep -q '"wb":150' <<<"$record" || fail "an update was lost: $record"
echo "ok   writes from both nodes to one record are all kept"

# 3. Both nodes start the scheduler in the same second, five times: every task runs once.
runs=""
for round in 1 2 3 4 5; do
    rm -f "$site/ext/data/.state/schedule"
    node node-a php core/cli/nimbly.php schedule:run > "$site/schedule-a.txt" 2>&1 &
    node node-b php core/cli/nimbly.php schedule:run > "$site/schedule-b.txt" 2>&1 &
    wait
    twice="$(cat "$site/schedule-a.txt" "$site/schedule-b.txt" | awk '$1 == "run" { print $2 }' | sort | uniq -d)"
    [ -z "$twice" ] || fail "scheduled twice in round $round: $twice"
    runs="$runs$(cat "$site/schedule-a.txt" "$site/schedule-b.txt" | awk '$1 == "run"' | wc -l) "
done
[ "$(tr -d ' 0' <<<"$runs")" != "" ] || fail "the scheduler ran nothing"
echo "ok   a scheduled task runs on one node only (tasks run per round: $runs)"

# 4. Request statistics: both nodes append to the one day file.
before="$(cat "$site"/ext/data/.tmp/stats/running-*.log 2>/dev/null | wc -l)"
for port in "$port_a" "$port_b"; do
    (for i in $(seq 1 100); do curl -s -o /dev/null "http://localhost:$port/health?n=$i"; done) &
done
wait
node node-a php -r '
    $lines = array_merge(...array_map(fn($f) => file($f, FILE_IGNORE_NEW_LINES), glob("ext/data/.tmp/stats/running-*.log")));
    $broken = count(array_filter($lines, fn($l) => !is_array(json_decode($l, true))));
    echo count($lines), " ", $broken, "\n";' > "$site/stats.txt"
read -r lines broken < "$site/stats.txt"
[ "$broken" = 0 ] || fail "$broken broken lines in the statistics"
[ $((lines - before)) -ge 200 ] || fail "statistics lines are missing: $((lines - before)) of 200"
echo "ok   request statistics from both nodes are whole ($((lines - before)) lines)"

echo "multi-node test passed"
