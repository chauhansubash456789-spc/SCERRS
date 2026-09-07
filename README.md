# SCERRS — Smart Citizen Emergency Reporting & Response System

SCERRS is a web-based emergency reporting platform built for Nepal. Citizens can report
accidents, fires, floods, crimes, and other emergencies in seconds — with automatic GPS
location capture and photo evidence attached. Reports are routed to the correct responding
agency (Police, Fire Brigade, Ambulance, Municipality, or NEOC Disaster Management) and
tracked in real time from *pending* through *resolved*, cutting response times when every
second counts.

> Developed as a 4th Semester BCA project proposal under Tribhuvan University.

---

## ✨ Features

- **One-tap emergency reporting** with auto-detected GPS coordinates and street address
- **Photo evidence upload** (up to 5 MB per file) for faster verification
- **Automatic agency routing** based on incident category
- **Severity classification** (Low / Medium / High / Critical) with color-coded badges
- **Role-based dashboards** for citizens, authority departments, and administrators
- **Live status tracker** — citizens watch their report move from pending → acknowledged → dispatched → resolved
- **Interactive live map** (Leaflet / OpenStreetMap) of active incidents
- **Analytics dashboard** (Chart.js) — incident trends, category breakdowns, response stats
- **In-app notifications** for status changes and assignments
- **Light/Dark theme** with saved preference
- **Emergency hotlines marquee** for quick dial access (100 / 101 / 102 / 103 / 1098 / 1155)

---

## 🛠 Tech Stack

| Layer      | Technology                                        |
|------------|---------------------------------------------------|
| Backend    | PHP 8.x (procedural + PDO, no framework)          |
| Database   | MySQL / MariaDB                                   |
| Frontend   | HTML5, CSS3, Vanilla JavaScript                   |
| UI Kit     | Bootstrap 5.3.3 + Bootstrap Icons 1.11            |
| Maps       | Leaflet 1.9.4 + OpenStreetMap tiles               |
| Charts     | Chart.js 4.4.3                                    |
| Auth       | PHP sessions + `password_hash()` (bcrypt)         |

---

## 📂 Project Structure

```
SCERRS/
├── assets/            # CSS, JS, images
│   ├── css/           # style.css (theme tokens), register.css
│   └── js/            # theme.js, page scripts
├── auth/              # login.php, register.php, logout.php
├── citizen/           # dashboard, report_new, report_view
├── authority/         # dashboard (incidents), analytics, incident_view
├── admin/             # users, categories, teams management
├── config/            # config.php (app + DB constants), db.php
├── database/
│   └── scerrs.sql     # Full schema + seed data (import this)
├── includes/          # header.php, footer.php, functions.php
└── uploads/           # Incident photo evidence (auto-created)
```

---

## 🚀 How to Run the Project

### Prerequisites

- **XAMPP**, **WAMP**, or **Laragon** (Apache + PHP 8.0+ + MySQL 5.7+/MariaDB)
- A modern web browser (Chrome, Firefox, Edge)

> The project was developed on **Laragon** with **PHP 8.3** — any equivalent stack works.

### 1. Place the Project Files

Clone or copy the `SCERRS` folder into your web root:

- **XAMPP/WAMP:** `C:\xampp\htdocs\SCERRS`
- **Laragon:** `C:\laragon\www\SCERRS`

### 2. Create the Database

1. Start **Apache** and **MySQL** from your control panel.
2. Open **phpMyAdmin** → `http://localhost/phpmyadmin`.
3. Click **Import** → choose `database/scerrs.sql` → **Go**.

   This creates the `scerrs` database with all tables **and seed data**
   (departments, categories, response teams, demo users, sample incidents).

### 3. Configure the Connection

DB credentials live in [`config/config.php`](config/config.php):

```php
define('DB_HOST', '127.0.0.1');
define('DB_PORT', '3306');
define('DB_NAME', 'scerrs');
define('DB_USER', 'root');
define('DB_PASS', '');
```

Adjust to match your local MySQL setup (XAMPP/WAMP defaults shown above).

### 4. Start & Access

Open your browser and visit:

```
http://localhost/SCERRS
```

(Laragon pretty URLs also work: `http://scerrs.test`.)

Ensure `uploads/incidents/` is writable by the web server so photo evidence
can be saved.

---

## 🔒 Development & Testing Credentials
*(INTERNAL ONLY — do not ship these accounts to production)*

All seed accounts use one of three passwords:

| Password       | Used by                        |
|----------------|--------------------------------|
| `Admin@123`    | System Admin                   |
| `Authority@123`| All department authorities     |
| `Citizen@123`  | Demo citizen account           |

| Role                  | Email                     | Password         |
|-----------------------|---------------------------|------------------|
| Admin                 | `admin@scerrs.np`         | `Admin@123`      |
| Police                | `police@scerrs.np`        | `Authority@123`  |
| Fire Brigade          | `fire@scerrs.np`          | `Authority@123`  |
| Ambulance             | `ambulance@scerrs.np`     | `Authority@123`  |
| Municipality          | `municipality@scerrs.np`  | `Authority@123`  |
| NEOC Disaster         | `disaster@scerrs.np`      | `Authority@123`  |
| Citizen               | `citizen@scerrs.np`       | `Citizen@123`    |

Log in at `http://localhost/SCERRS/auth/login.php` to test each role-based dashboard.
New citizen accounts can be created via the public registration form; admin-created
authority accounts are managed under **Users** in the admin panel.

---

## 👥 Contributors

- **Subash Prasad Chauhan** — Developer
- **Nirjal Basnet** — Developer

*4th Semester Project Proposal · BCA · Tribhuvan University*
