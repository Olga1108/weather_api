# Weather Forecast API Service

This project provides a Weather Forecast API service allowing users to subscribe to weather updates for a specific city, confirm their subscription via email, and receive periodic weather forecasts.

## 1. Prerequisites

*   Docker and Docker Compose installed.
*   Git installed.
*   A `.env.local` file (see step 2c).

## 2. Initial Setup

a.  **Clone the Repository:**
    ```bash
    git clone <your-repository-url> # Replace <your-repository-url> with the actual URL
    cd weather-api 
    ```

b.  **Build Docker Images (if first time or Dockerfile changed):**
    ```bash
    docker compose build
    ```

c.  **Create Environment File:**
    Copy `.env` to `.env.local` (or create `.env.local` directly if `.env` is not committed):
    ```bash
    cp .env .env.local
    ```
    **Edit `.env.local` and ensure the following are set correctly:**
    *   `APP_ENV=dev`
    *   `APP_SECRET=<generate_a_strong_secret_key>` (You can use `openssl rand -hex 16` to generate one)
    *   `DATABASE_URL="mysql://app:password@db:3306/app_db?serverVersion=8.0&charset=utf8mb4"` (This should be correct by default if using the provided Docker setup.)
    *   `MAILER_DSN="smtp://mailhog:1025"` (This should be correct for MailHog if you are using it.)
    *   `WEATHER_API_KEY=<your_actual_weatherapi.com_api_key>` **(Crucial for the `/api/weather` endpoint and the `SendWeatherUpdatesCommand` to work. Obtain a key from [WeatherAPI.com](https://www.weatherapi.com/))**

d.  **Install Composer Dependencies (inside the PHP container):**
    First, ensure no old `vendor` directory exists if you're re-setting up or encountering issues:
    ```bash
    rm -rf vendor
    ```
    Then, start services (if not already running) and install:
    ```bash
    docker compose up -d
    docker compose exec php composer install
    ```

e.  **Database Migrations:**
    Run the database migrations to set up the schema:
    ```bash
    docker compose exec php php bin/console doctrine:migrations:migrate
    ```
    (Type `yes` if prompted to confirm.)

## 3. Running the Application

*   Start all services:
    ```bash
    docker compose up -d
    ```
*   **API Base URL:** The Nginx service is mapped to port `8081` on your host by default (see `compose.yaml`). The application will be accessible at `http://localhost:8081`.

## 4. Accessing Key Application Pages/Endpoints

a.  **API Documentation (Swagger UI):**
    Open your browser and navigate to:
    `http://localhost:8081/api/doc`
    This page lists all available API endpoints and allows you to interact with them directly.

b.  **MailHog (Email Catching Service):**
    If MailHog is running (as configured in `compose.yaml`), you can see emails sent by the application (like subscription confirmations):
    Open your browser and navigate to:
    `http://localhost:8025`

c.  **Example API Interactions (can also be performed via the Swagger UI at `/api/doc`):**

    i.  **GET Weather Forecast:**
        `GET http://localhost:8081/api/weather?city=London&days=3`
        (Requires `WEATHER_API_KEY` to be correctly set in `.env.local`)

    ii. **POST Subscribe to Weather Updates:**
        Send a POST request to `http://localhost:8081/api/subscribe`
        *   **Using `application/json` Content-Type:**
            ```json
            {
                "email": "user@example.com",
                "city": "Paris",
                "frequency": "hourly" 
            }
            ```
            (Valid frequencies are "hourly" or "daily")
        *   **Using `application/x-www-form-urlencoded` Content-Type:**
            `email=user@example.com&city=Paris&frequency=hourly`

        A confirmation email should be sent. Check MailHog.

    iii. **GET Confirm Subscription:**
        Click the confirmation link from the email received in MailHog. The link will be in the format:
        `http://localhost:8081/api/confirm/<confirmation_token>`

    iv. **GET Unsubscribe:**
        To unsubscribe, a user would click a link (typically provided in weather update emails). The link format is:
        `http://localhost:8081/api/unsubscribe/<unsubscribe_token>`
        (The `unsubscribe_token` is generated and stored upon successful subscription confirmation.)

## 5. Running the `SendWeatherUpdatesCommand`

This Symfony console command fetches weather forecasts and sends them to confirmed subscribers based on their chosen frequency.

a.  **To run for hourly subscriptions:**
    ```bash
    docker compose exec php php bin/console app:send-weather-updates hourly
    ```

b.  **To run for daily subscriptions:**
    ```bash
    docker compose exec php php bin/console app:send-weather-updates daily
    ```
    Output will indicate if emails were sent or if no subscriptions were found. Emails will appear in MailHog. This command requires a valid `WEATHER_API_KEY` in `.env.local`.

## 6. Running Tests (Current Status)

*   The controller tests for subscriptions (`SubscriptionControllerTest.php`) are currently **skipped** due to a persistent issue with the test database setup when using the `dama/doctrine-test-bundle`. This is to allow the application to be used and other tests to pass.
*   The command tests (`SendWeatherUpdatesCommandTest.php`) should pass.
*   To run all tests:
    ```bash
    docker compose exec php vendor/bin/phpunit
    ```

## 7. Stopping the Application

*   To stop all services:
    ```bash
    docker compose down
    ```
*   To stop services and remove the database volume (useful for a clean restart of the database):
    ```bash
    docker compose down -v
    ```

## 8. Troubleshooting / Notes

*   If you encounter issues with Docker services not starting (e.g., port conflicts), check the `ports` configuration in `compose.yaml` and ensure the host ports are free.
*   The MailHog service might show a platform warning (`linux/amd64` vs `linux/arm64/v8`) when starting on ARM-based machines (like Apple Silicon Macs). This is generally a warning and MailHog should still function. If it causes critical issues, an alternative MailHog image or configuration might be needed.
