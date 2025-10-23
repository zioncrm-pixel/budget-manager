# AGENTS.md — budget-manager

## 🧭 Project Overview
This repository contains **budget-manager**, a Home Budget Management System built with **Laravel 12 (PHP 8.2)** and **Inertia Vue 3 + Vite + TailwindCSS**.  
The system allows users to upload or manually enter income/expense data, store it in a SQLite database, and view monthly summaries by date, category, and source (checking account, card, or cash).

The project is developed mainly in the evenings.  
Conversation language: **Hebrew**.  
Code, comments, file names: **English**.

---

## ⚙️ Tech Stack
- **Backend:** Laravel 12.23.1, PHP 8.2, SQLite (local)
- **Frontend:** Vue 3 + Inertia + Vite + TailwindCSS + Axios
- **Auth:** Laravel Sanctum  
- **Key Composer Packages:**  
  `laravel/framework`, `inertiajs/inertia-laravel`, `laravel/sanctum`, `phpoffice/phpspreadsheet`, `tightenco/ziggy`
- **Key NPM Packages:**  
  `@inertiajs/vue3`, `vite`, `laravel-vite-plugin`, `vue`, `tailwindcss`, `axios`, `@tailwindcss/forms`

---

## 📁 Project Structure
app/Models
├─ Budget.php
├─ Transaction.php
├─ Category.php
├─ CashFlowSource(.php / Budget.php)
├─ SpecialExpense.php
├─ SystemSetting.php
└─ User.php

resources/js
├─ Components/
├─ Layouts/
├─ Pages/
├─ app.js
├─ bootstrap.js
└─ utils/

routes/
├─ web.php
├─ api.php
├─ auth.php
└─ console.php



---

## 🧩 Build & Run
- Start backend: `php artisan serve`
- Start frontend (Vite): `npm run dev`
- Build frontend: `npm run build`
- Migrate DB: `php artisan migrate`
- Seed DB: `php artisan db:seed`

---

## 🎨 Code & Style Conventions
- Use **Controllers + FormRequests + Resources**.  
- Follow **PSR-12** for PHP and **camelCase** for JS.  
- Keep functions small and clear.  
- Do not print or expose `.env` secrets.  
- Place new Vue pages under `resources/js/Pages` and components under `resources/js/Components`.

---

## 🧠 How to Help Me
- When uncertain, **ask one short clarifying question** or make a reasonable assumption.  
- Prefer **ready-to-paste code** snippets.  
- Show full file paths when suggesting edits.  
- Keep Laravel-specific syntax compatible with **v12+**.  
- Assume **SQLite** unless told otherwise.  
- Don’t add external stacks or services unless requested.

---

## 🪄 Example Commands
```bash
php artisan make:model Transaction -mcr
php artisan migrate:fresh --seed
npm run dev
