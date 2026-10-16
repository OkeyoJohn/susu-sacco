# Susu Savings Management System

A web-based application built with PHP and MySQL to manage rotating savings groups, weekly member contributions, outstanding balances, and payout distribution.

The system follows a simple principle: every member contributes a fixed amount each week, and the entire collected pot is paid to one member according to the order in which members joined the group.

## Features

- **Member Management:** Register members and prevent duplicate names.
- **Contribution Tracking:** Record full or partial weekly contributions.
- **Balance Monitoring:** Calculate each member's outstanding contribution balance.
- **Payment Validation:** Reject invalid payments and prevent overpayment.
- **Week Closure:** Prevent a week from closing while members have outstanding balances.
- **Payout Management:** Record the weekly payout recipient and amount.
- **Automatic Rotation:** Advance to the next recipient according to member joining order.
- **Dashboard:** Display group statistics, current week information, and payout history.
- **Data Persistence:** Store members, contributions, weekly records, and payouts in MySQL.

## Technology Stack

| Technology | Purpose |
|---|---|
| HTML5 | Page structure |
| CSS3 | Styling and responsive layout |
| JavaScript | Frontend interactions and API requests |
| PHP | Backend logic and validation |
| MySQL | Database storage |
| PDO | PHP database connectivity |
| XAMPP | Local development environment |
| Git and GitHub | Version control and project hosting |

## Project Structure

```text
susu/
├── api/
│   ├── members.php
│   ├── contributions.php
│   ├── close-week.php
│   └── report.php
├── config/
│   └── database.php
├── database/
│   └── schema.sql
├── public/
│   ├── dashboard.html
│   ├── members.html
│   ├── contributions.html
│   ├── close-week.html
│   ├── css/
│   │   └── style.css
│   └── js/
│       ├── app.js
│       ├── contributions.js
│       ├── close-week.js
│       └── dashboard.js
├── .gitignore
└── README.md
```

## Requirements

Before running the application, install:

- [XAMPP](https://www.apachefriends.org/)
- A modern web browser
- [Git](https://git-scm.com/) (optional, for cloning and version control)

## Installation and Setup

### 1. Clone the repository

Open a terminal and run:

```bash
git clone YOUR_GITHUB_REPOSITORY_URL
```

Replace `YOUR_GITHUB_REPOSITORY_URL` with the actual URL of this repository.

Alternatively, download the repository as a ZIP file.

### 2. Move the project into XAMPP

Place the project folder inside:

```text
C:\xampp\htdocs\susu
```

Ensure that the project structure matches the one shown above.

### 3. Start Apache and MySQL

Open the XAMPP Control Panel and start:

- Apache
- MySQL

### 4. Create the database

1. Open [phpMyAdmin](http://localhost/phpmyadmin/).
2. Create a database named `susu_db`.
3. Select the database.
4. Import `database/schema.sql`.

The schema should create the tables required for members, contributions, weeks, payouts, and group settings.

### 5. Configure the database connection

Open `config/database.php` and configure the database host, database name, username, and password for your local MySQL installation.

Do not commit real passwords or private credentials to a public repository. Use environment variables or a local configuration file excluded through `.gitignore` for sensitive settings.

### 6. Run the application

Open the dashboard in your browser:

[http://localhost/susu/public/dashboard.html](http://localhost/susu/public/dashboard.html)

Use the navigation bar to access the dashboard, members, contributions, and week-closure pages.

**Important:** Run the application through XAMPP's Apache server rather than opening the HTML files directly or using VS Code Live Server, because the backend requires PHP.

## How the System Works

1. Create a savings group with a fixed weekly contribution.
2. Register members in the order they join.
3. Record contributions from members throughout the week.
4. Review outstanding balances.
5. Close the week only after all members have contributed the required amount.
6. Record the total pot as a payout to the next eligible member.
7. Advance the application to the next week and recipient.
8. Review contribution summaries and payout history on the dashboard.

### Example

Suppose a group has five members, each contributing KSh 500 per week.

- Weekly contribution per member: KSh 500
- Number of members: 5
- Total weekly pot: KSh 2,500

Each week, one member receives the KSh 2,500 pot. The recipient rotates through the group in joining order.

## Validation and Data Integrity

The application is designed to:

- Prevent duplicate member registration.
- Reject invalid or non-positive payment amounts.
- Prevent contributions from exceeding the required weekly amount.
- Block week closure when contributions are incomplete.
- Prevent duplicate payouts for the same week.
- Keep contribution, payout, and week records consistent through database transactions.

## Limitations

- The application is intended for local development and demonstration unless separately secured and deployed.
- Payouts are recorded in the database; the system does not transfer money automatically.
- Online payments, mobile-money integration, notifications, and user authentication may be added in future versions.

## Future Improvements

- User authentication and role-based access control.
- Mobile-money integration, such as M-Pesa.
- Printable financial reports and exports.
- Email or SMS contribution reminders.
- Improved automated tests.
- Secure cloud deployment and automated backups.

## Author

**John Okeyo**

GitHub: [OkeyoJohn](https://github.com/OkeyoJohn)

## License

A license has not yet been specified. Add a `LICENSE` file before redistributing the project under an open-source license.