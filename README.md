# Weather Forecast API Service

A PHP 8.3/Symfony 7.2 REST API for weather forecasts and email subscriptions, fully Dockerized with MySQL and MailHog for local development and testing.

## Features
- Subscribe to weather updates for a city (hourly/daily)
- Email confirmation and unsubscribe links
- Periodic weather update emails (via Symfony command)
- Interactive API documentation (Stoplight UI)
- Automated setup and test suite

---

## Prerequisites
- [Docker](https://www.docker.com/) and Docker Compose
- [Git](https://git-scm.com/)
- Obtain a free API key from [WeatherAPI.com](https://www.weatherapi.com/)

---

## Quick Start

1. **Clone the repository:**
   ```bash
   git clone git@github.com:Olga1108/weather_api.git
   cd weather-api
   ```

2. **Create your environment file:**
   ```bash
   cp .env .env.local
   # Edit .env.local and set:
   #   APP_SECRET=<your-random-secret> (e.g. openssl rand -hex 16)
   #   WEATHER_API_KEY=<your_weatherapi.com_key>
   #   (DATABASE_URL and MAILER_DSN are correct by default for Docker)
   ```

3. **Build and start all services:**
   ```bash
   docker compose up -d --build
   ```

4. **Run the setup script (installs Composer deps, creates DBs, runs migrations, runs tests):**
   ```bash
   docker compose run --rm setup
   ```
   - This will: install dependencies, create and migrate dev/test DBs, and run the test suite.

5. **Access the app:**
   - **API base URL:** [http://localhost:8081](http://localhost:8081)
   - **API documentation (Stoplight UI):** [http://localhost:8081/api/doc](http://localhost:8081/api/doc)
   - **MailHog (view emails):** [http://localhost:8025](http://localhost:8025)

---

## API Usage

- **Get weather forecast:**
  ```http
  GET /api/weather?city=London&days=3
  ```
- **Subscribe:**
  ```http
  POST /api/subscribe
  Content-Type: application/json
  {
    "email": "user@example.com",
    "city": "Paris",
    "frequency": "hourly"  // or "daily"
  }
  ```
- **Confirm subscription:**
  - Click the link in the confirmation email (see MailHog inbox).
- **Unsubscribe:**
  - Click the link in the weather update email (see MailHog inbox).

All endpoints are documented and testable via [http://localhost:8081/api/doc](http://localhost:8081/api/doc).

---

## Running the Weather Update Command

Send weather updates to subscribers (emails appear in MailHog):
```bash
docker compose exec php php bin/console app:send-weather-updates hourly
docker compose exec php php bin/console app:send-weather-updates daily
```

---

## Running Tests

Run the full test suite (after setup, or any time):
```bash
docker compose exec php bin/phpunit
```
All tests should pass.

---

## Stopping and Cleaning Up

- Stop all services:
  ```bash
  docker compose down
  ```
- Stop and remove database data (for a clean DB):
  ```bash
  docker compose down -v
  ```

---

## Notes
- The setup script (`docker/php/init.sh`) automates all onboarding steps.
- MailHog works out of the box for email testing.
- The API documentation uses Stoplight UI at `/api/doc`.
- For any issues, check Docker logs or ensure your `.env.local` is correct.
