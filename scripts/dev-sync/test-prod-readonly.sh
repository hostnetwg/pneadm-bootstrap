#!/usr/bin/env bash
# Test lokalny skryptu serwerowego docs/deploy/scripts/prod-devsync-readonly.sh.
# NIE łączy się z produkcją: tworzy atrapę $HOME w katalogu tymczasowym i atrapę ssh.
#
# Użycie: bash scripts/dev-sync/test-prod-readonly.sh

set -uo pipefail

REPO_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
WRAPPER="$REPO_DIR/docs/deploy/scripts/prod-devsync-readonly.sh"

TMP="$(mktemp -d)"
trap 'rm -rf "$TMP"' EXIT

FAKE_HOME="$TMP/home"
mkdir -p "$FAKE_HOME/backups/mysql" \
  "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/app/public/courses" \
  "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/app/private" \
  "$FAKE_HOME/domains/pnedu.pl/app/storage/app/public" \
  "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/logs"

# Zrzuty
echo "stary"  | gzip -c > "$FAKE_HOME/backups/mysql/2026-10-30_pneadm_pneadm.sql.gz"
echo "nowy"   | gzip -c > "$FAKE_HOME/backups/mysql/2026-10-31_pneadm_pneadm.sql.gz"
touch -d '2 days ago' "$FAKE_HOME/backups/mysql/2026-10-30_pneadm_pneadm.sql.gz"
echo "x" > "$FAKE_HOME/backups/mysql/inne.txt"

# Pliki aplikacji + sekrety, które NIE mogą wyjść
echo "obraz" > "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/app/public/courses/a.jpg"
echo "SEKRET" > "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/app/private/google-credentials.json"
echo "SEKRET" > "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/app/private/.env.bak"
echo "SEKRET" > "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/app/private/klucz.pem"
echo "log"    > "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/logs/laravel.log"
echo "front"  > "$FAKE_HOME/domains/pnedu.pl/app/storage/app/public/f.txt"

# Repozytoria gita do "heads"
for d in "$FAKE_HOME/domains/adm.pnedu.pl/pneadm" "$FAKE_HOME/domains/pnedu.pl/app"; do
  git -C "$d" init -q 2>/dev/null
  git -C "$d" -c user.name=t -c user.email=t@t commit -q --allow-empty -m init 2>/dev/null
done

# Atrapa ssh: pomija opcje i host, resztę przekazuje jako SSH_ORIGINAL_COMMAND
cat > "$TMP/fake-ssh" <<EOF
#!/usr/bin/env bash
while [[ "\$1" == -* ]]; do shift; done   # opcje
shift                                     # host
export HOME="$FAKE_HOME"
export SSH_CLIENT="127.0.0.1 1 2"
export SSH_ORIGINAL_COMMAND="\$*"
exec bash "$WRAPPER"
EOF
chmod +x "$TMP/fake-ssh"

PASS=0
FAIL=0
ok()   { echo "  OK   $1"; PASS=$((PASS + 1)); }
bad()  { echo "  FAIL $1"; FAIL=$((FAIL + 1)); }

run_wrapper() { # $1 = polecenie klienta
  HOME="$FAKE_HOME" SSH_CLIENT="127.0.0.1 1 2" SSH_ORIGINAL_COMMAND="$1" bash "$WRAPPER" 2>"$TMP/stderr"
}

expect_denied() { # $1 opis, $2 polecenie
  local out rc
  out="$(run_wrapper "$2")"; rc=$?
  if [[ $rc -eq 126 && -z "$out" ]]; then ok "odmowa: $1"; else bad "powinna być odmowa: $1 (rc=$rc, out='$out')"; fi
}

echo "== polecenia podstawowe"
[[ "$(run_wrapper ping)" == "OK pne-devsync" ]] && ok "ping" || bad "ping"

out="$(run_wrapper list)"
first="$(echo "$out" | head -1 | cut -f1)"
[[ "$first" == "2026-10-31_pneadm_pneadm.sql.gz" ]] && ok "list: najnowszy pierwszy" || bad "list kolejność ($first)"
echo "$out" | grep -q 'inne.txt' && bad "list pokazuje obce pliki" || ok "list: tylko *.sql.gz"

[[ "$(run_wrapper 'cat 2026-10-31_pneadm_pneadm.sql.gz' | gunzip -c)" == "nowy" ]] && ok "cat: treść zrzutu" || bad "cat"

heads="$(run_wrapper heads)"
echo "$heads" | grep -q '^pneadm' && echo "$heads" | grep -q '^pnedu' && ok "heads: obie aplikacje" || bad "heads"

echo "== odmowy"
expect_denied "pusta komenda"            ""
expect_denied "powłoka"                  "bash"
expect_denied "ls"                       "ls -la"
expect_denied "rm"                       "rm -rf domains"
expect_denied "cat /etc/passwd"          "cat /etc/passwd"
expect_denied "cat ../ucieczka"          "cat ../secrets.sql.gz"
expect_denied "cat zła etykieta"         "cat 2026-10-31_evil_x.sql.gz"
expect_denied "cat nie-zrzut"            "cat inne.txt"
expect_denied "średnik"                  "ping; id"
expect_denied "podstawienie \$()"        'cat $(id).sql.gz'
expect_denied "backtick"                 'ping `id`'
expect_denied "pipe"                     "list | sh"
expect_denied "rsync push (bez --sender)" "rsync --server -vlogDtpre.iLsfxCIvu . domains/adm.pnedu.pl/pneadm/storage/app/"
expect_denied "rsync zła ścieżka /etc"   "rsync --server --sender -vlogDtpre.iLsfxCIvu . /etc/"
expect_denied "rsync cały home"          "rsync --server --sender -vlogDtpre.iLsfxCIvu . ."
expect_denied "rsync .env aplikacji"     "rsync --server --sender -vlogDtpre.iLsfxCIvu . domains/adm.pnedu.pl/pneadm/.env"
expect_denied "rsync --remove-source-files" "rsync --server --sender --remove-source-files -vlogDtpre.iLsfxCIvu . domains/adm.pnedu.pl/pneadm/storage/app/"
expect_denied "rsync --rsync-path"       "rsync --server --sender --rsync-path=x -vlogDtpre.iLsfxCIvu . domains/adm.pnedu.pl/pneadm/storage/app/"
expect_denied "rsync -L (follow symlinks)" "rsync --server --sender -vLlogDtpre.iLsfxCIvu . domains/adm.pnedu.pl/pneadm/storage/app/"
expect_denied "rsync ../"                "rsync --server --sender -vlogDtpre.iLsfxCIvu . domains/adm.pnedu.pl/pneadm/storage/app/../../.env"

echo "== rsync end-to-end (prawdziwy rsync + atrapa ssh)"
DEST="$TMP/dest"
mkdir -p "$DEST/pneadm" "$DEST/pnedu"

if rsync -a --delete -e "$TMP/fake-ssh" x:domains/adm.pnedu.pl/pneadm/storage/app/ "$DEST/pneadm/" 2>"$TMP/rsync.err"; then
  ok "rsync pneadm: kod wyjścia 0"
else
  bad "rsync pneadm nie powiódł się: $(cat "$TMP/rsync.err")"
fi
[[ -f "$DEST/pneadm/public/courses/a.jpg" ]] && ok "rsync: plik aplikacji pobrany" || bad "rsync: brak pliku aplikacji"
[[ ! -e "$DEST/pneadm/private/google-credentials.json" ]] && ok "rsync: *credential* wykluczone przez serwer" || bad "WYCIEK credentials"
[[ ! -e "$DEST/pneadm/private/.env.bak" ]]                 && ok "rsync: .env* wykluczone przez serwer" || bad "WYCIEK .env"
[[ ! -e "$DEST/pneadm/private/klucz.pem" ]]                && ok "rsync: *.pem wykluczone przez serwer" || bad "WYCIEK pem"

if rsync -a -e "$TMP/fake-ssh" x:domains/pnedu.pl/app/storage/app/ "$DEST/pnedu/" 2>/dev/null && [[ -f "$DEST/pnedu/public/f.txt" ]]; then
  ok "rsync pnedu"
else
  bad "rsync pnedu"
fi

# Próba pobrania logów (poza dozwoloną ścieżką) przez prawdziwego rsync
if rsync -a -e "$TMP/fake-ssh" x:domains/adm.pnedu.pl/pneadm/storage/logs/ "$TMP/logs/" 2>/dev/null; then
  bad "rsync logów powinien być odrzucony"
else
  ok "rsync logów odrzucony"
fi

# Próba wysłania pliku na serwer przez prawdziwego rsync (push)
echo "x" > "$TMP/push.txt"
if rsync -a -e "$TMP/fake-ssh" "$TMP/push.txt" x:domains/adm.pnedu.pl/pneadm/storage/app/ 2>/dev/null; then
  bad "PUSH przeszedł!"
else
  ok "push na serwer odrzucony"
fi
[[ ! -e "$FAKE_HOME/domains/adm.pnedu.pl/pneadm/storage/app/push.txt" ]] && ok "push: nic nie zapisano na serwerze" || bad "push zapisał plik"

echo "== audyt"
[[ -s "$FAKE_HOME/backups/devsync-access.log" ]] && ok "log audytu zapisany" || bad "brak logu audytu"

echo
echo "Wynik: ${PASS} OK, ${FAIL} FAIL"
[[ "$FAIL" -eq 0 ]]
