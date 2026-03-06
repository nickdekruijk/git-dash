# Git Dash

A personal GitHub commit dashboard that shows all your commits across every repository and organization you have access to — with time estimation, filtering by date range, shareable links, and support for multiple GitHub accounts.

## Features

- View commits across all repositories and organizations
- Timeline view grouped by day and work session
- By Repository view with time breakdown
- Estimated time worked per session and repository
- Multiple GitHub connections (personal + organization accounts)
- Shareable read-only links (optionally scoped to a single repository)
- Date range filters with quick presets
- GitHub API response caching to minimise API calls
- Dark mode support

## Requirements

- PHP 8.2 or higher (8.4 recommended)
- Composer
- Node.js & npm
- A MySQL, PostgreSQL or SQLite database
- A GitHub Personal Access Token

## Installation

**1. Clone the repository**

```bash
git clone https://github.com/nickdekruijk/git-dash.git
cd git-dash
```

**2. Install dependencies**

```bash
composer install
npm install
npm run build
```

**3. Configure the environment**

```bash
cp .env.example .env
php artisan key:generate
```

Open `.env` and set the following:

```env
APP_URL=http://your-local-url

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=git_dash
DB_USERNAME=your_db_user
DB_PASSWORD=your_db_password

# Password to protect your dashboard
DASHBOARD_PASSWORD=your_secret_password
```

**4. Run migrations**

```bash
php artisan migrate
```

**5. Serve the app**

```bash
php artisan serve
```

Or use [Laravel Herd](https://herd.laravel.com/) / Valet for a local domain.

## Adding a GitHub Connection

Once the dashboard is running, open it in your browser and navigate to **Connections** (via the menu in the top right). Add a connection with:

- **Name** — a short identifier (e.g. `personal`)
- **Label** — display name shown in the UI
- **Token** — a GitHub Personal Access Token

### Creating a GitHub Personal Access Token

1. Go to [GitHub → Settings → Developer Settings → Personal access tokens → Fine-grained tokens](https://github.com/settings/tokens)
2. Click **Generate new token**
3. Set a name and expiration
4. Under **Permissions**, grant:
   - **Contents**: Read-only (to read commit data)
   - **Metadata**: Read-only (required)
5. Under **Repository access**, choose **All repositories** (to see commits across all repos)
6. Click **Generate token** and copy it into the dashboard

For classic tokens, the `repo` scope is sufficient.

## Share Links

From the **Share Links** page you can generate read-only URLs to share your commit history with others. Links can optionally be scoped to a single repository.

## Caching

GitHub API responses (commits, repository lists, user info) are cached for **1 hour** by default to reduce the number of API calls made. If you need fresh data before the cache expires, use the **Clear Cache** option in the dropdown menu in the top-right corner of the dashboard.

## Tech Stack

- [Laravel 12](https://laravel.com)
- [Livewire 4](https://livewire.laravel.com)
- [Tailwind CSS 4](https://tailwindcss.com)
- [Alpine.js](https://alpinejs.dev)
- [knplabs/github-api](https://github.com/KnpLabs/php-github-api)

## License

MIT
