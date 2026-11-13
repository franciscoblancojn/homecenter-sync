#!/usr/bin/env bash
# Sube la versión de index.php, cambia "## [Sin publicar]" por "## [X.Y.Z] - AAAA-MM-DD" en
# CHANGELOG.md y publica (npm run push-tag: commit, tag X.Y.Z y push).
# Uso: npm run push-v -- major|minor|patch
set -e

if [ $# -ne 1 ] || [[ ! "$1" =~ ^(major|minor|patch)$ ]]; then
    echo "Uso: npm run push-v -- major|minor|patch"
    exit 1
fi

VERSION=$(grep -m1 "^Version:" index.php | sed 's/.*Version:[[:space:]]*//' | tr -d '\r')
MAJOR=$(echo "$VERSION" | cut -d. -f1)
MINOR=$(echo "$VERSION" | cut -d. -f2)
PATCH=$(echo "$VERSION" | cut -d. -f3)

case "$1" in
    major)
        MAJOR=$((MAJOR + 1))
        MINOR=0
        PATCH=0
        ;;
    minor)
        MINOR=$((MINOR + 1))
        PATCH=0
        ;;
    patch)
        PATCH=$((PATCH + 1))
        ;;
esac

NEW_VERSION="$MAJOR.$MINOR.$PATCH"

if git rev-parse -q --verify "refs/tags/$NEW_VERSION" > /dev/null; then
    echo "✗ El tag $NEW_VERSION ya existe"
    exit 1
fi

# Se comprueba antes de tocar nada: no se publica una versión sin notas en el changelog.
if ! grep -q '^## \[Sin publicar\]' CHANGELOG.md; then
    echo "✗ CHANGELOG.md no tiene una sección '## [Sin publicar]' con los cambios de esta versión"
    exit 1
fi

# Validaciones del proyecto (php -l, ES5…): no se publica si fallan.
bash bin/harness.sh

sed -i "s/^\(Version: *\)[0-9.]\+/\1$NEW_VERSION/" index.php
sed -i "s/^## \[Sin publicar\].*/## [$NEW_VERSION] - $(date +%F)/" CHANGELOG.md

echo "✓ Versión: $VERSION → $NEW_VERSION"
echo "✓ CHANGELOG.md: [Sin publicar] → [$NEW_VERSION]"

# Sin "|| true" ni "2>/dev/null": si el commit o el push fallan, se ve el error.
npm run push-tag --silent
