#!/usr/bin/env bash
# Krótki helper: wykrywa pliki PHP bez docblocka pliku (kompatybilny z macOS i GNU)

set -euo pipefail

ROOT_DIR="$(pwd)"

if ! command -v php >/dev/null 2>&1; then
  echo "PHP nie jest zainstalowane lub nie znajduje się w PATH. Potrzebne do bezpiecznego parsowania." >&2
fi

echo "Skanuję repozytorium w poszukiwaniu plików PHP bez docblocka (pierwszy blok /** ... */)..."

# Funkcja: sprawdza czy plik zaczyna się od docblocka /** ... */ lub shebangu + docblock
has_docblock() {
  local file="$1"
  # Odczyt pierwszych 10 linii bezpośrednio i sprawdzenie czy występuje /**
  head -n 12 "$file" | awk 'BEGIN{found=0} {if($0 ~ /^\/\*\*/){print "yes"; exit} } END{if(found==0) exit 1}' >/dev/null 2>&1
}

export -f has_docblock


# Parse args
INSERT_MODE=0
TARGET_DIRS=("app/Services" "app/Http/Controllers" "app/Models" "app/Traits" "app/Console" "app/Providers")
if [[ ${1-} == "--insert" ]]; then
  INSERT_MODE=1
  echo "Tryb: wstawianie docblocków (bezpośrednio modyfikuje pliki)"
else
  echo "Tryb: podgląd (nie modyfikuje plików). Użyj --insert aby wstawić docblocki." 
fi

missing=()
files_checked=0

while IFS= read -r -d '' f; do
  files_checked=$((files_checked+1))
  # Sprawdzenie prostym grepem: czy w pierwszych 12 liniach jest '/**'
  if ! head -n 12 "$f" | grep -q '/\*\*'; then
    missing+=("${f#$ROOT_DIR/}")
  fi
done < <(find "$ROOT_DIR" -type f -name "*.php" -not -path "*/vendor/*" -print0)

echo "Znaleziono ${files_checked} plików PHP, z których ${#missing[@]} nie mają docblocka file-level (pierwsze 40 wymienione):"

if [[ ${#missing[@]} -eq 0 ]]; then
  echo "Wszystkie sprawdzone pliki mają docblock file-level w pierwszych 12 liniach. Nic do zrobienia."
  exit 0
fi

for i in "${!missing[@]}"; do
  if [[ $i -ge 40 ]]; then break; fi
  echo " - ${missing[$i]}"
done

echo "Możesz wstawić docblocki ręcznie lub zmodyfikować skrypt by wstawiał szablon automatycznie (zalecane: najpierw uruchomić testy)."

if [[ "$INSERT_MODE" -eq 1 ]]; then
  echo "Wstawiam docblocki do plików w katalogach core (tylko jeśli plik bez docblocka):"
  for p in "${missing[@]}"; do
    for base in "${TARGET_DIRS[@]}"; do
      if [[ "$p" == $base* ]]; then
        file="$ROOT_DIR/$p"
        echo " - Aktualizuję: $p"
        # Wygeneruj prosty docblock
        doc="/**\n * Plik projektu sor3events.\n *\n * Krótki opis: (dodaj szczegóły).\n *\n * @package Sor3Events\n */\n\n"

        # Wstaw docblock po shebang lub po otwarciu <?php
        # Zachowaj oryginalny plik jako *.bak
        cp "$file" "$file.bak"
        awk -v doc="$doc" 'NR==1{if($0 ~ /^#!\/){print; getline} if($0 ~ /^<\?php/){print $0; print doc; next} } {print}' "$file.bak" > "$file.tmp" || true
        # Jeśli nie zmodyfikowaliśmy (brak <?php w pierwszej linii), preprend doc
        if ! head -n 20 "$file.tmp" | grep -q '/\*\*'; then
          printf "%s%s" "<?php\n" "$doc" > "$file.tmp2"
          cat "$file.bak" >> "$file.tmp2"
          mv "$file.tmp2" "$file"
        else
          mv "$file.tmp" "$file"
        fi
        rm -f "$file.bak"
        break
      fi
    done
  done
  echo "Wstawianie zakończone. Uruchom testy by upewnić się, że nie ma błędów składniowych." 
fi
