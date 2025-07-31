#!/bin/bash

# Перезапуск сервисов CS-SKINS
echo "Перезапуск Horizon..."
sudo supervisorctl restart cs_skins_horizon

echo "Перезапуск Reverb..."
sudo supervisorctl restart cs_skins_reverb:cs_skins_reverb_00

echo "Сервисы перезапущены!"