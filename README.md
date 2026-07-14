# Grade Assessment - Интерактивная оценка грейда сотрудников

Приложение для оценки навыков бизнес- и системных аналитиков с использованием адаптивной AI-системы тестирования.

## Возможности

- **Адаптивная оценка** — вопросы генерируются через Deepseek API и подстраиваются под уровень отвечающего в реальном времени
- **Hard & Soft Skills** — оценка технических и личностных компетенций
- **AI-оценка ответов** — анализ глубины и полноты ответов по 10-балльной шкале
- **Детальный отчёт** — матрица навыков, сильные/слабые стороны, рекомендации по развитию
- **Админ-панель** — дашборд, список оценок, статистика, управление
- **Экспорт CSV** — выгрузка отчётов
- **JWT-аутентификация** — безопасный доступ к админ-панели

## Стек технологий

- **Frontend:** React 19 + TypeScript + Vite + Tailwind CSS + shadcn/ui
- **Backend:** PHP 8.0+ (REST API)
- **Database:** MySQL 8.0+
- **AI:** Deepseek API (deepseek-chat)

## Установка

### Требования
- PHP 8.0+
- MySQL 8.0+
- Apache с mod_rewrite (или аналог)
- API-ключ Deepseek

### Шаги установки

1. Клонируйте репозиторий:
```bash
git clone https://github.com/sinoptics-lev/grade-assessment.git
cd grade-assessment
```

2. Создайте базу данных:
```sql
CREATE DATABASE grade_assessment CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

3. Скопируйте и настройте `.env`:
```bash
cp .env.example .env
```

Отредактируйте `.env`:
```env
DB_HOST=localhost
DB_NAME=grade_assessment
DB_USER=ваш_пользователь
DB_PASS=ваш_пароль
DEEPSEEK_API_KEY=sk-your-api-key
JWT_SECRET=сгенерируйте-случайную-строку
```

4. Запустите установку через браузер:
```
https://ваш-домен/api/install.php
```

5. **Удалите** `api/install.php` после установки для безопасности!

### Доступ администратора
- **Логин:** `admin`
- **Пароль:** `password`
- ⚠️ **Обязательно смените пароль после первого входа!**

## Структура проекта

```
├── api/                    # PHP backend
│   ├── index.php          # API роутер
│   ├── config.php         # Конфигурация
│   ├── db.php             # Подключение к БД
│   ├── deepseek.php       # Интеграция с Deepseek API
│   ├── assessment.php     # API для опроса
│   ├── report.php         # API для отчётов
│   ├── admin.php          # API для админ-панели
│   ├── auth.php           # Аутентификация
│   └── install.php        # Скрипт установки
├── db/
│   └── schema.sql         # SQL схема
├── src/                    # React frontend
│   ├── pages/
│   │   ├── HomePage.tsx   # Главная (форма)
│   │   ├── AssessmentPage.tsx  # Опрос
│   │   ├── ReportPage.tsx      # Отчёт
│   │   └── AdminPage.tsx       # Админ-панель
│   ├── hooks/
│   │   └── useApi.ts     # API хуки
│   ├── types/
│   │   └── index.ts      # TypeScript типы
│   └── App.tsx            # Роутинг
├── .env.example
├── .htaccess              # Apache конфигурация
└── README.md
```

## API Endpoints

### Оценка
| Метод | Endpoint | Описание |
|-------|----------|----------|
| POST | `/api/assessment?path=start` | Начать оценку |
| POST | `/api/assessment?path=question` | Получить вопрос |
| POST | `/api/assessment?path=answer` | Отправить ответ |
| POST | `/api/assessment?path=complete` | Завершить оценку |

### Отчёты
| Метод | Endpoint | Описание |
|-------|----------|----------|
| GET | `/api/report?path=by-token&token=TOKEN` | Получить отчёт |
| GET | `/api/report?path=list` | Список (admin) |

### Админ
| Метод | Endpoint | Описание |
|-------|----------|----------|
| POST | `/api/admin?path=login` | Авторизация |
| GET | `/api/admin?path=dashboard` | Дашборд |
| GET | `/api/admin?path=assessments` | Список оценок |

## Как работает адаптивная оценка

1. Пользователь заполняет форму (ФИО, позиция, опыт)
2. AI генерирует первый вопрос среднего уровня сложности
3. После каждого ответа AI оценивает его (0-10 баллов)
4. Следующий вопрос адаптируется: если ответ хороший — сложность растёт, если есть пробелы — задаётся уточняющий вопрос
5. После 15 вопросов генерируется детальный отчёт с матрицей навыков

## Лицензия

MIT
