#!/bin/sh
set -eu

if [ "$#" -ne 1 ]; then
    printf 'Usage: %s OUTPUT.zip\n' "$0" >&2
    exit 2
fi

command -v zip >/dev/null 2>&1 || {
    printf 'Error: zip is required to build the plugin archive.\n' >&2
    exit 1
}

destination=$1
case "$destination" in
    /*) ;;
    *) destination="$(pwd)/$destination" ;;
esac

if [ -e "$destination" ]; then
    printf 'Error: refusing to overwrite existing output: %s\n' "$destination" >&2
    exit 1
fi

root=$(CDPATH= cd -- "$(dirname -- "$0")/.." && pwd)
tmp=$(mktemp -d "${TMPDIR:-/tmp}/comfy-image-package.XXXXXX")
trap 'rm -rf "$tmp"' EXIT HUP INT TERM

stage="$tmp/comfy-image"
mkdir -p "$stage/assets" "$stage/includes" "$stage/examples" "$(dirname -- "$destination")"
cp "$root/comfy-image/comfy-image.php" "$stage/comfy-image.php"
cp "$root/comfy-image/readme.txt" "$stage/readme.txt"
cp "$root/comfy-image/assets/block.js" "$stage/assets/block.js"
cp "$root/comfy-image/includes/endpoints.php" "$stage/includes/endpoints.php"
cp "$root/comfy-image/examples/flux2-klein-4b.api.json" "$stage/examples/flux2-klein-4b.api.json"
cp "$root/comfy-image/examples/z-image-turbo.api.json" "$stage/examples/z-image-turbo.api.json"
cp "$root/comfy-image/examples/kandinsky5-lite.api.json" "$stage/examples/kandinsky5-lite.api.json"
chmod 0644 \
    "$stage/comfy-image.php" \
    "$stage/readme.txt" \
    "$stage/assets/block.js" \
    "$stage/includes/endpoints.php" \
    "$stage/examples/flux2-klein-4b.api.json" \
    "$stage/examples/z-image-turbo.api.json" \
    "$stage/examples/kandinsky5-lite.api.json"

source_date_epoch=${SOURCE_DATE_EPOCH:-315532800}
case "$source_date_epoch" in
    ''|*[!0-9]*)
        printf 'Error: SOURCE_DATE_EPOCH must be an integer Unix timestamp.\n' >&2
        exit 2
        ;;
esac
export TZ=UTC
touch -d "@$source_date_epoch" \
    "$stage/comfy-image.php" \
    "$stage/readme.txt" \
    "$stage/assets/block.js" \
    "$stage/includes/endpoints.php" \
    "$stage/examples/flux2-klein-4b.api.json" \
    "$stage/examples/z-image-turbo.api.json" \
    "$stage/examples/kandinsky5-lite.api.json"
archive="$tmp/comfy-image.zip"
(
    cd "$tmp"
    zip -X -9 -q "$archive" \
        comfy-image/comfy-image.php \
        comfy-image/readme.txt \
        comfy-image/assets/block.js \
        comfy-image/includes/endpoints.php \
        comfy-image/examples/flux2-klein-4b.api.json \
        comfy-image/examples/z-image-turbo.api.json \
        comfy-image/examples/kandinsky5-lite.api.json
)

mv "$archive" "$destination"
printf 'Built %s\n' "$destination"
