#!/usr/bin/env bash
#
# Render-diff harness for the legacy-model retirement.
#
# Snapshots the rendered HTML (and the RDF output) of every page type, normalised for volatile
# bits (CSRF tokens, timings, dates), so a change to the in-memory model or the XSLT can be
# proven output-neutral — or its diff inspected deliberately. The HTML is deterministic, so a
# byte-identical normalised render means the change preserved behaviour.
#
#   test/render_diff.sh capture   # save baselines into test/render-baseline/
#   test/render_diff.sh check     # re-render and diff against the baselines
#
set -u
BASE="${BASE:-http://localhost:8080}"
DB="${DB_CONTAINER:-lunarsystem-db-1}"
ADMIN_EMAIL="${ADMIN_EMAIL:-admin@lunarsystem.local}"
ADMIN_PASS="${ADMIN_PASS:-luna}"
DIR="test/render-baseline"
MODE="${1:-check}"

sql(){ docker exec "$DB" mysql -uroot -proot lunadb -N -e "$1" 2>/dev/null; }
tok(){ grep -oE 'csrf_token"[^>]*value="[^"]*"' "$1" | grep -oE 'value="[^"]*"' | head -1 | sed 's/value="//;s/"//'; }

# Strip the bits that legitimately change between two identical requests, so structural diffs
# stand out: CSRF synchroniser tokens, render timings, and wall-clock dates/times.
norm(){ sed -E \
  -e 's/(csrf_token[^>]*value=")[^"]*"/\1CSRF"/g' \
  -e 's/(value=")[0-9a-f]{32,}"/\1HASH"/g' \
  -e 's/[0-9]{4}-[0-9]{2}-[0-9]{2}[ T][0-9]{2}:[0-9]{2}(:[0-9]{2})?/DATE/g' \
  -e 's/[0-9]+\.[0-9]+ ?(s|ms|sec|seconds)/TIME/g' \
  -e 's/(generated|rendered)[^<]*/\1 TIME/Ig' \
  -e 's/[0-9]+ ?(seconde|minute|heure|jour|semaine|mois|second|minute|hour|day|week|month|year|an)s?/AGO/Ig' \
  -e 's/(log_id=|message |id="|aria-label="message )[0-9]+/\1ID/g' \
  -e 's/(PHPSESSID=|sessionid=|session=)[A-Za-z0-9]+/\1SESS/Ig' \
  -e 's|<td class="nowrap">[^<]*</td>|<td class="nowrap">SEEN</td>|g' \
  -e 's/[0-9]+\.[0-9]+\.[0-9]+-alpha/VER/g' ; }

# id | auth(guest|admin) | url   — covers each html.xsl stylesheet + the RDF output formats
PAGES="
admin|admin|/admin
home|guest|/
node|guest|/node
login|guest|/login
notfound|guest|/no-such-page-xyz
out_xml|guest|/?output=xml
out_n3|guest|/?output=n3
out_json|guest|/?output=json
out_jsonld|guest|/?output=jsonld
admin_users|admin|/admin/admin_users
admin_groups|admin|/admin/admin_groups
admin_levels|admin|/admin/admin_levels
admin_pages|admin|/admin/admin_pages
admin_mods|admin|/admin/admin_mods
journal_analyse|admin|/admin/journal?log_id=999
edit_texts|admin|/edition/edit_texts
home_admin|admin|/
node_admin|admin|/node
"

mkdir -p "$DIR"
# Reset volatile server state so the render is deterministic across runs (dev harness):
# the audit log grows per request, and the online-users widget reflects live sessions.
sql "DELETE FROM luna_login_throttle; DELETE FROM luna_logs; DELETE FROM luna_sessions;"
# Seed one fixed log row so the journal "analyse" view (a name()/local-name()-driven
# admin tool outside the public page set) has a deterministic target — this guards its
# per-field i18n label lookup. All volatile bits (date, log_id) are normalised below.
sql "INSERT INTO luna_logs (id,logtime,ident,priority,message) VALUES (999,'2020-01-01 00:00:00','test',6,'{\"message\":\"probe\"}')"
# fresh admin session
AJ=$(mktemp); AP=$(mktemp); curl -s -c "$AJ" "$BASE/login" -o "$AP"
curl -s -b "$AJ" -c "$AJ" --data-urlencode submit=login --data-urlencode "email=$ADMIN_EMAIL" \
  --data-urlencode "password=$ADMIN_PASS" --data-urlencode "csrf_token=$(tok $AP)" "$BASE/login" -o /dev/null

fails=0; diffs=0
printf '%s\n' "--- render-diff: $MODE ---"
while IFS='|' read -r id auth url; do
  [ -z "$id" ] && continue
  if [ "$auth" = admin ]; then code=$(curl -s -b "$AJ" -o /tmp/rd.raw -w '%{http_code}' "$BASE$url")
  else code=$(curl -s -o /tmp/rd.raw -w '%{http_code}' "$BASE$url"); fi
  norm < /tmp/rd.raw > /tmp/rd.norm
  base="$DIR/$id.html"
  if [ "$MODE" = capture ]; then
    cp /tmp/rd.norm "$base"
    printf '  saved   %-14s HTTP %s  %5s bytes\n' "$id" "$code" "$(wc -c </tmp/rd.norm | tr -d ' ')"
  else
    if [ ! -f "$base" ]; then printf '  \033[33mNOBASE\033[0m  %-14s (run capture first)\n' "$id"; fails=$((fails+1)); continue; fi
    if diff -q "$base" /tmp/rd.norm >/dev/null 2>&1; then
      printf '  \033[32mSAME\033[0m    %-14s HTTP %s\n' "$id" "$code"
    else
      printf '  \033[31mDIFF\033[0m    %-14s HTTP %s\n' "$id" "$code"; diffs=$((diffs+1))
      diff "$base" /tmp/rd.norm | head -12 | sed 's/^/        /'
    fi
  fi
done <<< "$PAGES"
rm -f "$AJ" "$AP"

echo
if [ "$MODE" = capture ]; then printf '\033[32mbaselines captured in %s\033[0m\n' "$DIR"; exit 0; fi
if [ "$diffs" -eq 0 ] && [ "$fails" -eq 0 ]; then printf '\033[32mRENDER UNCHANGED — all pages byte-identical\033[0m\n'; exit 0; fi
printf '\033[31m%d page(s) differ, %d missing baseline\033[0m\n' "$diffs" "$fails"; exit 1
