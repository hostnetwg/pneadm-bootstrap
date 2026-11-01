#!/usr/bin/env bash
# Skrypt serwerowy (SeoHost) dla synchronizacji PROD -> DEV. TYLKO ODCZYT.
#
# Uruchamiany WYŁĄCZNIE przez SSH jako "forced command" w ~/.ssh/authorized_keys, np.:
#   command="/bin/bash /home/srv66127/bin/pne-devsync-readonly.sh",restrict ssh-ed25519 AAAA... dev-laptop-1
#
# Klucz z taką opcją NIE dostaje powłoki. Może wykonać tylko poniższe polecenia
# (przekazane przez klienta jako SSH_ORIGINAL_COMMAND), wszystko inne jest odrzucane:
#
#   ping                          -> "OK pne-devsync"
#   list                          -> lista zrzutów *.sql.gz (najnowsze pierwsze)
#   cat <plik.sql.gz>             -> strumień wskazanego zrzutu (nazwa walidowana)
#   heads                         -> HEAD gita obu aplikacji (tylko odczyt)
#   rsync --server --sender ...   -> pobieranie storage/app (tylko 2 dozwolone ścieżki)
#
# Czego skrypt NIGDY nie robi: nie zapisuje, nie kasuje, nie uruchamia artisan/php,
# nie czyta .env, nie wykonuje niczego z danych klienta przez eval/shell.
#
# Zmienne opcjonalne (ustawiane w środowisku, nie przez klienta):
#   BACKUP_ROOT      domyślnie $HOME/backups/mysql
#   PNEADM_DIR       domyślnie $HOME/domains/adm.pnedu.pl/pneadm
#   PNEDU_DIR        domyślnie $HOME/domains/pnedu.pl/app
#   BWLIMIT_KBPS     limit prędkości rsync w KB/s (domyślnie 4000 = ok. 4 MB/s)
#   ACCESS_LOG       domyślnie $HOME/backups/devsync-access.log

set -euo pipefail
umask 077

BACKUP_ROOT="${BACKUP_ROOT:-${HOME}/backups/mysql}"
PNEADM_DIR="${PNEADM_DIR:-${HOME}/domains/adm.pnedu.pl/pneadm}"
PNEDU_DIR="${PNEDU_DIR:-${HOME}/domains/pnedu.pl/app}"
BWLIMIT_KBPS="${BWLIMIT_KBPS:-4000}"
ACCESS_LOG="${ACCESS_LOG:-${HOME}/backups/devsync-access.log}"
RSYNC_BIN="${RSYNC_BIN:-rsync}"

# Dozwolone źródła rsync (względem $HOME), z lub bez końcowego "/".
ALLOWED_RSYNC_PATHS=(
  "domains/adm.pnedu.pl/pneadm/storage/app"
  "domains/pnedu.pl/app/storage/app"
)

# Pliki, które nigdy nie opuszczają serwera (sekrety), niezależnie od klienta.
SERVER_EXCLUDES=(
  "--exclude=.env*"
  "--exclude=*.pem"
  "--exclude=*.key"
  "--exclude=*credential*"
  "--exclude=*service-account*"
  "--exclude=*service_account*"
  "--exclude=id_rsa*"
  "--exclude=id_ed25519*"
)

log() {
  # Audyt: kto (SSH_CLIENT) i co. Błąd zapisu logu nie przerywa pracy.
  {
    printf '%s client=%s %s\n' "$(TZ=Europe/Warsaw date '+%Y-%m-%d %H:%M:%S')" "${SSH_CLIENT:-local}" "$*" \
      >> "$ACCESS_LOG"
  } 2>/dev/null || true
}

deny() {
  log "DENY: $1"
  echo "pne-devsync: odmowa ($1)" >&2
  exit 126
}

ORIG="${SSH_ORIGINAL_COMMAND:-}"
[[ -n "$ORIG" ]] || deny "brak polecenia"

# Biała lista znaków: żadnych cudzysłowów, $, `, ;, |, &, *, \, nawiasów, #, nowych linii.
if [[ ! "$ORIG" =~ ^[A-Za-z0-9\ ._/=,@:+-]+$ ]]; then
  deny "niedozwolone znaki"
fi
[[ "$ORIG" != *".."* ]] || deny "sekwencja .. niedozwolona"

# Tokenizacja bez eval (tylko podział po spacjach).
read -r -a TOK <<< "$ORIG"
CMD="${TOK[0]}"

case "$CMD" in
  ping)
    [[ ${#TOK[@]} -eq 1 ]] || deny "ping bez argumentów"
    log "ping"
    echo "OK pne-devsync"
    ;;

  list)
    [[ ${#TOK[@]} -eq 1 ]] || deny "list bez argumentów"
    log "list"
    [[ -d "$BACKUP_ROOT" ]] || deny "brak katalogu kopii"
    # nazwa<TAB>bajty<TAB>mtime(epoch); tylko poprawne pliki zrzutów
    find "$BACKUP_ROOT" -maxdepth 1 -type f -name '*.sql.gz' -printf '%T@\t%f\t%s\n' \
      | sort -rn | head -n 40 \
      | awk -F'\t' '{ printf "%s\t%s\t%d\n", $2, $3, $1 }'
    ;;

  cat)
    [[ ${#TOK[@]} -eq 2 ]] || deny "cat wymaga jednej nazwy pliku"
    NAME="${TOK[1]}"
    if [[ ! "$NAME" =~ ^[0-9]{4}-[0-9]{2}-[0-9]{2}_(pneadm|certgen|analytics|pnedu)_[A-Za-z0-9_]+\.sql\.gz$ ]]; then
      deny "nazwa pliku niezgodna ze wzorcem zrzutu"
    fi
    FILE="${BACKUP_ROOT}/${NAME}"
    [[ -f "$FILE" && ! -L "$FILE" ]] || deny "plik nie istnieje albo jest linkiem"
    log "cat ${NAME}"
    exec cat -- "$FILE"
    ;;

  heads)
    [[ ${#TOK[@]} -eq 1 ]] || deny "heads bez argumentów"
    log "heads"
    export GIT_OPTIONAL_LOCKS=0
    for pair in "pneadm:${PNEADM_DIR}" "pnedu:${PNEDU_DIR}"; do
      label="${pair%%:*}"
      dir="${pair#*:}"
      if [[ -d "$dir/.git" ]]; then
        printf '%s\t%s\t%s\n' "$label" \
          "$(git -C "$dir" rev-parse --short HEAD 2>/dev/null || echo '?')" \
          "$(git -C "$dir" log -1 --format=%cI 2>/dev/null || echo '?')"
      else
        printf '%s\t?\t?\n' "$label"
      fi
    done
    ;;

  rsync)
    # Oczekiwany kształt: rsync --server --sender <opcje...> . <ścieżka>
    [[ ${#TOK[@]} -ge 5 ]] || deny "rsync: za mało argumentów"
    [[ "${TOK[1]}" == "--server" && "${TOK[2]}" == "--sender" ]] || deny "rsync: tylko tryb sender (odczyt)"

    last=$(( ${#TOK[@]} - 1 ))
    SRC="${TOK[$last]}"
    [[ "${TOK[$((last - 1))]}" == "." ]] || deny "rsync: oczekiwano '.' przed ścieżką"

    # Opcje (od indeksu 3 do przed '.'), wyłącznie z białej listy.
    OPTS=()
    for (( i = 3; i < last - 1; i++ )); do
      t="${TOK[$i]}"
      if [[ "$t" =~ ^-[vlogDtprzn]*(e\.[A-Za-z]+)?$ && "$t" != "-" ]]; then
        # Skupiska krótkich opcji: bez -L/-k/-K/-R/-S/-W/-m itd. (poza flagami po "e.")
        OPTS+=("$t")
      elif [[ "$t" == "--numeric-ids" || "$t" == "--partial" || "$t" == "--no-inc-recursive" ]]; then
        OPTS+=("$t")
      elif [[ "$t" =~ ^--timeout=[0-9]+$ ]]; then
        OPTS+=("$t")
      else
        deny "rsync: niedozwolona opcja '${t}'"
      fi
    done

    # Ścieżka: dokładnie jedna z dozwolonych (względna lub absolutna pod $HOME).
    norm="${SRC%/}"
    norm="${norm#"${HOME}"/}"
    ok=0
    for allowed in "${ALLOWED_RSYNC_PATHS[@]}"; do
      if [[ "$norm" == "$allowed" ]]; then ok=1; break; fi
    done
    [[ "$ok" -eq 1 ]] || deny "rsync: niedozwolona ścieżka '${SRC}'"

    cd "$HOME"
    log "rsync ${norm}/"
    exec nice -n 19 "$RSYNC_BIN" --server --sender "${OPTS[@]}" \
      "--bwlimit=${BWLIMIT_KBPS}" "${SERVER_EXCLUDES[@]}" . "${norm}/"
    ;;

  *)
    deny "nieznane polecenie '${CMD}'"
    ;;
esac
