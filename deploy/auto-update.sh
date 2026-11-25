#!/bin/sh
# LE GENTLEMAN : met le site à jour depuis GitHub (branche main) dès qu'un nouveau commit existe.
# Lancé toutes les 2 minutes par la crontab de l'utilisateur ubuntu. Journal : /opt/legentleman/auto-update.log
set -u
cd /opt/legentleman/app || exit 1
exec 9>/tmp/legentleman-update.lock
flock -n 9 || exit 0                                  # une seule mise à jour à la fois

git fetch -q origin main 2>/dev/null || exit 0        # GitHub injoignable : on réessaiera
LOCAL=$(git rev-parse HEAD)
REMOTE=$(git rev-parse origin/main)
[ "$LOCAL" = "$REMOTE" ] && exit 0

FAILED=/opt/legentleman/.auto-update-failed
if git merge --ff-only -q origin/main 2>/tmp/legentleman-update.err; then
  rm -f "$FAILED"
  echo "$(date '+%F %T') mise à jour ${LOCAL%"${LOCAL#???????}"} -> ${REMOTE%"${REMOTE#???????}"} : $(git log -1 --format=%s)"
  if git diff --name-only "$LOCAL" "$REMOTE" | grep -q '^sql/'; then
    echo "  ATTENTION : fichiers sql/ modifiés, une migration de la base est peut-être à lancer à la main."
  fi
else
  # On ne journalise l'échec qu'une fois par commit (pas toutes les 2 minutes).
  if [ "$(cat "$FAILED" 2>/dev/null)" != "$REMOTE" ]; then
    echo "$REMOTE" > "$FAILED"
    echo "$(date '+%F %T') ÉCHEC de la mise à jour vers $REMOTE (le site reste sur la version actuelle) :"
    sed 's/^/  /' /tmp/legentleman-update.err
  fi
fi
