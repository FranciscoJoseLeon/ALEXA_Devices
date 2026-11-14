#!/bin/bash
# Script de instalación de dependencias para el plugin alexa_devices de Jeedom

PROGRESS_FILE=/tmp/jeedom_install_in_progress_alexa_devices
if [ -f "$PROGRESS_FILE" ]; then
    echo "Instalación de dependencias ya en curso"
    exit 0
fi

touch "$PROGRESS_FILE"
echo 0 > "$PROGRESS_FILE"

BASEDIR=$(dirname "$0")
cd "$BASEDIR" || exit 1

echo "=========================================================="
echo "Iniciando instalación de dependencias para Alexa Devices..."
echo "=========================================================="

echo 10 > "$PROGRESS_FILE"
apt-get update

echo 30 > "$PROGRESS_FILE"
apt-get install -y python3 python3-pip python3-venv python3-dev libffi-dev libssl-dev git

echo 60 > "$PROGRESS_FILE"
mkdir -p demond/venv
mkdir -p data/storage

if [ ! -f demond/venv/bin/activate ]; then
    echo "Creando entorno virtual Python 3..."
    python3 -m venv demond/venv
fi

echo 75 > "$PROGRESS_FILE"
source demond/venv/bin/activate
pip install --upgrade pip setuptools wheel
pip install -r requirements.txt

echo 95 > "$PROGRESS_FILE"
chown -R www-data:www-data "$BASEDIR/data"
chmod -R 775 "$BASEDIR/data"

echo 100 > "$PROGRESS_FILE"
rm -f "$PROGRESS_FILE"

echo "=========================================================="
echo "Dependencias de Alexa Devices instaladas con éxito!"
echo "=========================================================="
