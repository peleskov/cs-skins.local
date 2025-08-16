#!/bin/bash

# Перезапуск сервисов CS-SKINS
echo "Перезапуск Horizon..."
sudo supervisorctl stop cs_skins_horizon
sleep 3
sudo supervisorctl start cs_skins_horizon

echo "Перезапуск Reverb..."
sudo supervisorctl restart cs_skins_reverb

echo "Перезапуск Scheduler..."
sudo supervisorctl restart cs_skins_scheduler

echo "Сервисы перезапущены!"