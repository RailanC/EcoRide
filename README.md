# EcoRide

EcoRide is a carpooling web application built with Symfony. The project focuses on the backend challenges behind a real booking platform: user accounts, trip management, route estimation, booking validation, credit handling, notifications, and trip history.

This repository is a strong junior backend / full-stack portfolio project because it goes beyond basic CRUD. It includes business workflows, transactional domain logic, database modeling, access control, external API integration, and automated tests.

## Project Overview

The application allows users to:

- register and verify their account by email
- log in securely with Symfony Security
- manage a profile and vehicles
- publish carpool trips as a driver
- search and filter available trips
- join a trip as a passenger
- cancel participation or cancel a trip
- mark a trip as started or arrived
- validate trip outcomes after arrival
- leave reviews linked to completed trips
- view active and historical trips in the profile area

The codebase uses server-rendered Twig pages for the main product flows, plus JSON endpoints for route preview features used by the frontend.

## Key Backend Features

### Authentication and Access Control

- Form-based authentication with Symfony Security
- Password hashing through Symfony password hashers
- Email verification during registration
- Route protection with role-based access control
- CSRF protection on login and sensitive user actions
- Signed cancellation links for booking cancellation from email

Relevant parts of the codebase:

- `src/Controller/RegistrationController.php`
- `src/Controller/SecurityController.php`
- `src/Security/EmailVerifier.php`
- `config/packages/security.yaml`

### Business Logic

The most important backend logic is centralized in the service layer rather than scattered across controllers.

Implemented workflows include:

- trip creation with route estimation and automatic arrival time calculation
- booking confirmation with seat updates and passenger credit deduction
- trip cancellation with passenger refunds
- passenger cancellation with seat restoration and refund handling
- trip lifecycle transitions: planned, in progress, arrived, completed, canceled, disputed
- post-trip validation by participants
- review creation tied to trip validation
- issue/dispute creation when a participant reports a bad outcome
- deferred driver payout logic after participant confirmation

Relevant parts of the codebase:

- `src/Service/TripParticipationService.php`
- `src/Service/TripRouteEstimator.php`
- `src/Service/TripNotificationService.php`
- `src/Controller/TripController.php`
- `src/Controller/BookingController.php`

### APIs and Integrations

This project does not expose a large internal REST API layer. Instead, it combines server-rendered pages with targeted backend endpoints and external service integrations.

Implemented API/integration logic includes:

- JSON route preview endpoints for trip planning
- OpenRouteService integration for geocoding and route calculation
- Symfony Mailer for notification emails
- Symfony Messenger for asynchronous email delivery

Relevant parts of the codebase:

- `src/Service/TripRouteEstimator.php`
- `src/Controller/TripController.php`
- `config/packages/messenger.yaml`
- `config/packages/mailer.yaml`

## Database Design Summary

The application uses Doctrine ORM with a relational database configuration based on PostgreSQL.

Core entities:

- `User`: account details, roles, verification status, credit balance, user type
- `Vehicle`: driver-owned vehicles, seat count, energy type, preferences
- `Trip`: departure/arrival data, price, seats, driver, vehicle, trip status
- `Booking`: passenger participation, confirmation state, outcome status, refund/payout tracking
- `Review`: passenger feedback for drivers, moderation fields, trip association
- `TripIssue`: dispute records created when a participant reports a problem

The schema shows that the project is designed around real business relationships rather than isolated forms. For example:

- a user can own multiple vehicles
- a driver can publish multiple trips
- a trip can have multiple bookings
- bookings store financial and validation state
- reviews are tied to both trip and author/driver relationships
- trip issues link a trip, booking, participant, and driver

Doctrine migrations in `migrations/` show incremental database evolution, including additions for payout tracking, moderation metadata, and dispute resolution.

## Tech Stack

- Backend: PHP 8.4, Symfony 8
- Database: PostgreSQL, Doctrine ORM, Doctrine Migrations
- Frontend: Twig, JavaScript, CSS
- Async processing: Symfony Messenger
- Email: Symfony Mailer
- External API: OpenRouteService
- Testing: PHPUnit, Symfony test utilities
- Local tooling: Docker Compose for database and mail testing services

## Running Locally

### Prerequisites

- PHP 8.4+
- Composer
- PostgreSQL 16+ or Docker Compose

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment variables

The repository already contains a `.env` file with example/default configuration. For a safer local setup, create a `.env.local` file and override values there.

Important variables used by the project:

- `DATABASE_URL`
- `MESSENGER_TRANSPORT_DSN`
- `MAILER_DSN`
- `MAILER_FROM_ADDRESS`
- `MAILER_FROM_NAME`
- `OPENROUTESERVICE_API_KEY`

### 3. Start supporting services

If you want to use Docker for local services:

```bash
docker compose up -d
```

This starts:

- a PostgreSQL database
- a Mailpit container for local email testing

### 4. Create the database schema

```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

### 5. Run the Symfony application

Using the Symfony CLI:

```bash
symfony server:start
```

Or with PHP's built-in server if needed:

```bash
php -S 127.0.0.1:8000 -t public
```

### 6. Run the Messenger worker

Trip notification emails are routed to the `async` transport, so a worker must be running to process them:

```bash
php bin/console messenger:consume async -vv
```

### 7. Run tests

```bash
php bin/phpunit
```

## What I Implemented / Learned

This project helped me practice the backend responsibilities expected in a junior backend or full-stack role:

- designing a relational domain model with Doctrine entities and migrations
- structuring business logic inside reusable Symfony services
- handling authentication, verification, access control, and CSRF protection
- integrating an external API for route estimation
- implementing transactional workflows for bookings, refunds, and trip completion
- modeling review and dispute flows instead of only basic CRUD operations
- writing automated tests for services, repositories, controllers, and entities

It also reinforced good backend habits such as separating controllers from domain logic, thinking about data consistency, and building features around real user workflows.
