#!/bin/bash
set -e

# Ścieżka do archiwum (zmień jeśli inne)
ARCHIVE="android-studio-*.tar.gz"

# 1. Instalacja zależności
sudo dnf install -y java-17-openjdk java-17-openjdk-devel zlib.i686 ncurses-libs.i686 bzip2-libs.i686 --skip-unavailable

# 2. Rozpakowanie Android Studio
if [ ! -d "android-studio" ]; then
  echo "Rozpakowuję Android Studio..."
  tar -xzf $ARCHIVE
fi

# 3. Uruchomienie instalatora
cd android-studio/bin
./studio.sh &

echo "Android Studio uruchomione. Jeśli pojawi się błąd, wklej go tutaj."
