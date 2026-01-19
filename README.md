# Syncithium (PHP) – GEM 201 Quiz App (Multi-user)

Syncithium is a lightweight, multi-user question practice app designed for academic quiz generation and practice.

## Key Features

- **Multi-user accounts**: The first registered user automatically becomes the Admin.
- **Hierarchical Taxonomy**: Organizes content by Level → Module → Subject → Topic.
- **Practice Tests**: Users can generate quizzes based on specific topics with customizable question counts and timers.
- **Admin Tools**:
  - Manage users and roles.
  - Import Taxonomy (Levels, Modules, Subjects, Topics) via CSV.
  - Import Questions via CSV.
- **Assessment**: Auto-marking, attempt history, and review of incorrect answers.

## Requirements

- PHP 8.0+ (recommended 8.1+)
- MySQL/MariaDB
- Apache or Nginx (XAMPP/WAMP/LAMP compatible)

## Setup (Local Development)

1. Copy the `syncithium/` folder into your web root (e.g., `htdocs/syncithium`).
2. Create a MySQL database named `syncithium`.
3. Import `schema.sql` into the database.
4. Copy `app/config.example.php` to `app/config.php` and update the database credentials.
5. Access the application:
   - If using the provided `.htaccess` in root: `http://localhost/syncithium/`
   - Otherwise: `http://localhost/syncithium/public/`
6. Register a new account. The first user is granted **Admin** privileges.

## Deployment (InfinityFree / Shared Hosting)

1. **Upload**: Copy the project files (including `.htaccess`, `app/`, and `public/`) into the `htdocs` folder.
2. **Security**: The root `.htaccess` file automatically routes traffic to `public/` and blocks direct access to `app/`.
3. **Database**: Import your SQL schema and update `app/config.php` with production credentials.
4. **Automation**: A `deploy.fzcli` script is provided for FileZilla Pro CLI users.

## CSV Import Formats

### 1. Taxonomy Import

Used to structure the curriculum.
**Headers:** `level_code,module_code,subject_name,topic_name`
_Example:_ `200,GEM 201,Mathematics,Calculus`

### 2. Question Import

Used to populate the question bank.
**Headers:** `subject, topic, question, option_a, option_b, option_c, option_d, correct_option`
_Note:_ `correct_option` must be one of: A, B, C, D.

## Security

- Passwords are hashed using `password_hash()`.
- CSRF protection is enabled for all forms.
- Application logic (`app/`) is protected from direct access via `.htaccess`.
