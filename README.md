# Syncithium (PHP) – GEM 201 Quiz App (Multi-user)

Syncithium is a lightweight, multi-user question practice app.

## What it does
- Multi-user accounts (first registered user becomes Admin)
- Admin CSV import for questions
- Users can generate practice tests by subject/topic and number of questions
- Auto-marking, attempt history, and review of wrong answers

## Requirements
- PHP 8.0+ (recommended 8.1+)
- MySQL/MariaDB
- Apache or Nginx (XAMPP is OK)

## Setup (XAMPP / Local)
1. Copy folder `syncithium/` into your web root (e.g. `htdocs/syncithium`).
2. Create a database named `syncithium`.
3. Import `schema.sql` into the database.
4. Copy `config.example.php` to `config.php` and update DB credentials.
5. Browse to: `http://localhost/syncithium/public/`
6. Register. The first user becomes **Admin** automatically.

## CSV Import format
Headers required:
- subject, topic, question, option_a, option_b, option_c, option_d, correct_option

`correct_option` must be one of: A, B, C, D

A sample is in: `storage/uploads/sample_questions.csv`

## Security notes
- Passwords are hashed with `password_hash()`
- CSRF tokens are enabled for form posts

