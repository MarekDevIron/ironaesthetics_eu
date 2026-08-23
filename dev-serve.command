#!/bin/bash
# Dvojklik / `open` spustí dev server eu-prepinac v novom okne Terminalu.
cd "$(dirname "$0")"
clear
echo "== eu-prepinac dev server =="
bash dev.sh serve
echo
echo "Otvor: http://localhost:8070/P2805A15382"
echo "(okno zatvor alebo pusti dev.sh stop pre zastavenie)"
sleep 31536000
