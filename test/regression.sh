#!/usr/bin/env bash
#
# LunarSystem smoke + security-regression suite.
#
# Exercises the hardening from the 2026 security pass (docs/security.md) plus a
# basic render smoke test, against a RUNNING stack. Run it after `docker compose
# up -d`:
#
#   BASE=http://localhost:8080 test/regression.sh
#
# Env:
#   BASE          base URL of the running app (default http://localhost:8080)
#   ADMIN_EMAIL   admin login (default admin@lunarsystem.local)
#   ADMIN_PASS    admin password (default luna)
#   DB_CONTAINER  mysql container for an optional pre-test throttle reset
#                 (default lunarsystem-db-1; reset is best-effort/skippable)
#
# Exits non-zero if any check fails.
set -u

BASE="${BASE:-http://localhost:8080}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@lunarsystem.local}"
ADMIN_PASS="${ADMIN_PASS:-luna}"
DB_CONTAINER="${DB_CONTAINER:-lunarsystem-db-1}"

fails=0
pass() { printf '  \033[32mPASS\033[0m %s\n' "$1"; }
fail() { printf '  \033[31mFAIL\033[0m %s\n' "$1"; fails=$((fails + 1)); }
note() { printf '  \033[33mNOTE\033[0m %s\n' "$1"; }

code()  { curl -s -o /dev/null -w '%{http_code}' "$BASE$1"; }
body()  { curl -s "$BASE$1"; }
tokfrom() { grep -oE 'csrf_token"[^>]*value="[^"]*"' "$1" | grep -oE 'value="[^"]*"' | head -1 | sed 's/value="//;s/"//'; }

# best-effort: clear the per-IP login throttle so login latency is deterministic
docker exec "$DB_CONTAINER" mysql -uroot -proot lunadb \
  -e "DELETE FROM luna_login_throttle;" >/dev/null 2>&1 \
  && note "reset login throttle via $DB_CONTAINER" \
  || note "skipped throttle reset (no DB access; fine on a fresh stack)"

echo "== smoke: public pages render =="
for p in / /node /login; do
  c=$(code "$p"); [ "$c" = 200 ] && pass "GET $p -> 200" || fail "GET $p -> $c (expected 200)"
done
body / | grep -q "lunarSystem" && pass "home shows the site footer" || fail "home missing expected content"

echo "== source/secret disclosure: sensitive paths denied (case-insensitive) =="
for p in /.git/HEAD /.GIT/HEAD /Dockerfile /DOCKERFILE /docker-compose.yml /DOCKER-COMPOSE.YML \
         /semantic/ontop/ontop.properties /luna/luna.domains/luna.default/ini/db.ini; do
  c=$(code "$p"); [ "$c" != 200 ] && pass "GET $p -> $c (denied)" || fail "GET $p -> 200 (LEAK)"
done

echo "== security headers present on the app response =="
H=$(curl -s -D - -o /dev/null "$BASE/")
echo "$H" | grep -qi "Content-Security-Policy:" && pass "CSP header present" || fail "CSP header missing"
echo "$H" | grep -qi "X-Frame-Options: *DENY" && pass "X-Frame-Options: DENY" || fail "X-Frame-Options missing/!=DENY"
echo "$H" | grep -qi "X-Content-Type-Options: *nosniff" && pass "X-Content-Type-Options: nosniff" || fail "X-Content-Type-Options missing"
echo "$H" | grep -qi "X-Powered-By:" && fail "X-Powered-By leaks the PHP version" || pass "no X-Powered-By"

echo "== authentication =="
JAR=$(mktemp); PAGE=$(mktemp)
curl -s -c "$JAR" "$BASE/login" -o "$PAGE"; T=$(tokfrom "$PAGE")
# correct credentials + token -> authenticated
curl -s -b "$JAR" -c "$JAR" --data-urlencode submit=login --data-urlencode "email=$ADMIN_EMAIL" \
  --data-urlencode "password=$ADMIN_PASS" --data-urlencode "csrf_token=$T" "$BASE/login" -o /dev/null
curl -s -b "$JAR" "$BASE/admin" | grep -qi "Administration" \
  && pass "valid login reaches the admin dashboard" || fail "valid login did NOT reach admin"
# wrong password -> not authenticated
JBAD=$(mktemp); PBAD=$(mktemp); curl -s -c "$JBAD" "$BASE/login" -o "$PBAD"; TB=$(tokfrom "$PBAD")
curl -s -b "$JBAD" -c "$JBAD" --data-urlencode submit=login --data-urlencode "email=$ADMIN_EMAIL" \
  --data-urlencode "password=wrong-$RANDOM" --data-urlencode "csrf_token=$TB" "$BASE/login" -o /dev/null
curl -s -b "$JBAD" "$BASE/admin" | grep -qi "Administration" \
  && fail "wrong password reached admin (auth bypass!)" || pass "wrong password rejected"
# CSRF gate: a tokenless login POST must not authenticate
JNT=$(mktemp); curl -s -c "$JNT" "$BASE/login" -o /dev/null
curl -s -b "$JNT" -c "$JNT" --data-urlencode submit=login --data-urlencode "email=$ADMIN_EMAIL" \
  --data-urlencode "password=$ADMIN_PASS" "$BASE/login" -o /dev/null
curl -s -b "$JNT" "$BASE/admin" | grep -qi "Administration" \
  && fail "tokenless login authenticated (CSRF gate bypass!)" || pass "tokenless login rejected (CSRF gate)"

echo "== SQL injection: start/limit are clamped (no stacked SLEEP) =="
# authenticated; a vulnerable LIMIT sink would execute SLEEP(3) and take >3s
t0=$(date +%s.%N)
curl -s -b "$JAR" --get --data-urlencode 'limit=20;SELECT SLEEP(3)' "$BASE/edition/edit_texts/" -o /dev/null
t1=$(date +%s.%N)
el=$(echo "$t1 - $t0" | bc)
awk "BEGIN{exit !($el < 2.0)}" \
  && pass "injected limit returned in ${el}s (clamped)" || fail "injected limit took ${el}s (possible SQLi)"

rm -f "$JAR" "$PAGE" "$JBAD" "$PBAD" "$JNT" "$PAGE"
echo
if [ "$fails" -eq 0 ]; then echo "ALL CHECKS PASSED"; exit 0; else echo "$fails CHECK(S) FAILED"; exit 1; fi
