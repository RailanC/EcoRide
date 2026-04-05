# EcoRide

EcoRide is a French startup's eco-friendly carpooling platform designed to reduce the environmental impact of travel. Led by Technical Director José, this web application connects environmentally conscious travelers for car-only trips. Our mission is to become the leading solution for sustainable, economical, and community-driven mobility.

## Features

## Technologies Used

- Backend: PHP with Symfony.
- Frontend: Twig, JavaScript, Bootstrap, HTML5, CSS3.
- Database: MongoDB (NoSql), PostgreSQL.
- Others: LocalStorage for visitor carts, REST APIs for frontend-backend communication.

## Installation

## Async email delivery

Trip notification emails are sent through Symfony Mailer and routed to Messenger `async`.
To actually deliver queued emails outside tests, run a Messenger worker such as:

```bash
php bin/console messenger:consume async -vv
```

If no worker is running, trip emails may be queued successfully but will not be delivered immediately.
   
