#!/bin/bash

# CS-Skins Deploy Script
# Скрипт для деплоя на продакшн сервер

set -e  # Остановка при ошибке

# Цвета для вывода
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}==========================================${NC}"
echo -e "${BLUE}     CS-Skins Production Deployment      ${NC}"
echo -e "${BLUE}==========================================${NC}"
echo ""

# Проверка наличия Deployer
if [ ! -f "vendor/bin/dep" ]; then
    echo -e "${RED}❌ Deployer не найден!${NC}"
    echo -e "${YELLOW}Установите Deployer командой: composer install${NC}"
    exit 1
fi

# Проверка наличия файла deploy.php
if [ ! -f "deploy.php" ]; then
    echo -e "${RED}❌ Файл deploy.php не найден!${NC}"
    exit 1
fi

# Проверка наличия .env.prod
if [ ! -f ".env.prod" ]; then
    echo -e "${RED}❌ Файл .env.prod не найден!${NC}"
    echo -e "${YELLOW}Создайте файл .env.prod с настройками для продакшн сервера${NC}"
    exit 1
fi

# Коммит изменений перед деплоем
echo -e "${YELLOW}Проверка git статуса...${NC}"
if [[ -n $(git status -s) ]]; then
    echo -e "${YELLOW}⚠️  Найдены незакоммиченные изменения${NC}"
    read -p "Хотите закоммитить все изменения перед деплоем? (y/n): " -n 1 -r
    echo
    if [[ $REPLY =~ ^[Yy]$ ]]; then
        git add .
        read -p "Введите сообщение коммита: " commit_message
        git commit -m "$commit_message"
        echo -e "${GREEN}✅ Изменения закоммичены${NC}"
    fi
fi

# Проверка подключения к серверу (опционально)
echo -e "${YELLOW}Начинаю деплой на продакшн сервер...${NC}"
echo ""

# Запуск деплоя с подробным выводом
echo -e "${BLUE}Выполняется команда: vendor/bin/dep deploy production -vvv${NC}"
echo ""

vendor/bin/dep deploy production -vvv

# Проверка результата
if [ $? -eq 0 ]; then
    echo ""
    echo -e "${GREEN}==========================================${NC}"
    echo -e "${GREEN}✅ ДЕПЛОЙ УСПЕШНО ЗАВЕРШЕН!${NC}"
    echo -e "${GREEN}==========================================${NC}"
    echo ""
    echo -e "${BLUE}Что было сделано:${NC}"
    echo -e "  • Код обновлен из репозитория"
    echo -e "  • Зависимости установлены (composer & npm)"
    echo -e "  • .env.prod скопирован в .env"
    echo -e "  • Миграции выполнены"
    echo -e "  • Фронтенд собран (npm run build)"
    echo -e "  • Кэши оптимизированы"
    echo -e "  • Horizon перезапущен"
    echo -e "  • Reverb перезапущен"
    echo -e "  • Scheduler проверен"
    echo ""
    echo -e "${GREEN}🚀 Сайт доступен по адресу: https://cs-skins.s1temaker.ru${NC}"
else
    echo ""
    echo -e "${RED}==========================================${NC}"
    echo -e "${RED}❌ ОШИБКА ДЕПЛОЯ!${NC}"
    echo -e "${RED}==========================================${NC}"
    echo -e "${YELLOW}Проверьте логи выше для определения причины ошибки${NC}"
    echo -e "${YELLOW}Возможные причины:${NC}"
    echo -e "  • Неверные параметры подключения в deploy.php"
    echo -e "  • Отсутствует SSH доступ к серверу"
    echo -e "  • Ошибки в коде или конфигурации"
    echo -e "  • Недостаточно прав на сервере"
    exit 1
fi