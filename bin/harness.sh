#!/usr/bin/env bash
# Validaciones del plugin Homecenter Sync (solo lectura). Uso: npm run check
#   1. php -l en todo el plugin (excluye libs/).
#   2. JavaScript ES5 en src/js (sin arrow functions, let/const ni template literals).
#   3. Cada require_once de los cargadores apunta a un archivo que existe.
#   4. Sin tokens de GitHub escritos en el código.
#   5. Dependencias de libs/ presentes (se publican con el plugin: el zip de master las incluye).
set -u
DIR="$(cd "$(dirname "$0")/.." && pwd)"
FAIL=0
fail() { printf '  [FAIL] %s\n' "$*"; FAIL=$((FAIL + 1)); }

echo "1. Sintaxis PHP"
while IFS= read -r f; do
    php -l "$f" > /dev/null 2>&1 || fail "php -l: ${f#$DIR/}"
done < <(find "$DIR" -name '*.php' -not -path "$DIR/libs/*" -not -path "$DIR/.git/*")

echo "2. JavaScript ES5"
while IFS= read -r f; do
    grep -nE '=>|\blet |\bconst |`' "$f" | sed "s#^#  ${f#$DIR/}:#" | grep . && fail "sintaxis no ES5 en ${f#$DIR/}"
done < <(find "$DIR/src/js" -name '*.js')

echo "3. require_once"
[ -f "$DIR/libs/autoload.php" ] || fail "no existe libs/autoload.php"
while IFS= read -r line; do
    file=$(echo "$line" | sed -E "s/.*HCS_DIR \. '([^']+)'.*/\1/")
    [ -f "$DIR/$file" ] || fail "no existe $file"
done < <(grep -rhoE "require_once HCS_DIR \. '[^']+\.php'" "$DIR/index.php" "$DIR/src")

echo "4. Secretos"
grep -rnE 'gh[pousr]_[A-Za-z0-9]{20,}|github_pat_' "$DIR" --include='*.php' --include='*.js' --exclude-dir=libs --exclude-dir=.git && fail "posible token en el código"

echo "5. Dependencias"
[ -d "$DIR/libs/franciscoblancojn/wordpress_utils" ] || fail "falta franciscoblancojn/wordpress_utils en libs/ (npm run install)"
grep -q "ComposerAutoloaderInitHCS\b" "$DIR/libs/composer/autoload_real.php" 2>/dev/null \
    || fail "el autoloader de libs/ no usa el sufijo HCS (composer.json → autoloader-suffix; npm run update)"

if [ "$FAIL" -gt 0 ]; then
    echo "✗ $FAIL validaciones fallaron"
    exit 1
fi
echo "✓ Todo OK"
