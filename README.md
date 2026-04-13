# Smart Daily Planner

A full-stack **Smart Daily Planner** application with **Laravel** backend and **Vue.js** frontend.

The backend provides a RESTful API to manage daily tasks and users, while the Vue.js frontend offers a modern and responsive user interface for better productivity.

## ✨ Features

- User Authentication (Register, Login, Profile)
- Full Task Management (CRUD)
- Versioned RESTful API (`/api/v1`)
- Modern Vue.js Frontend
- Responsive and clean UI
- Built with Laravel 11 + Vue.js

## 🛠 Tech Stack

- **Backend**: Laravel 11 (PHP 8.3+)
- **Frontend**: Vue.js
- **Database**: SQLite (default)
- **API**: RESTful JSON with versioning (`/api/v1`)

## 🚀 Quick Start

### Backend Setup (Laravel)

```bash
git clone https://github.com/mrnhyie/smart-daily-planner.git
cd smart-daily-planner

# Install PHP dependencies
composer install

# Setup environment
cp .env.example .env
php artisan key:generate

# Run migrations
php artisan migrate

# Start backend server
php artisan serve
