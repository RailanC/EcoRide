<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260404223000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Rename French database tables and columns to English equivalents.';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE avis RENAME TO reviews');
        $this->addSql('ALTER TABLE configuration RENAME TO app_configurations');
        $this->addSql('ALTER TABLE covoiturage RENAME TO trips');
        $this->addSql('ALTER TABLE marque RENAME TO brands');
        $this->addSql('ALTER TABLE parametre RENAME TO settings');
        $this->addSql('ALTER TABLE participation RENAME TO bookings');
        $this->addSql('ALTER TABLE utilisateur RENAME TO users');
        $this->addSql('ALTER TABLE voiture RENAME TO vehicles');

        $this->addSql('ALTER TABLE reviews RENAME COLUMN commentaire TO comment');
        $this->addSql('ALTER TABLE reviews RENAME COLUMN note TO rating');
        $this->addSql('ALTER TABLE reviews RENAME COLUMN utilisateur_id TO user_id');

        $this->addSql('ALTER TABLE trips RENAME COLUMN date_depart TO departure_date');
        $this->addSql('ALTER TABLE trips RENAME COLUMN heure_depart TO departure_time');
        $this->addSql('ALTER TABLE trips RENAME COLUMN lieu_depart TO departure_location');
        $this->addSql('ALTER TABLE trips RENAME COLUMN date_arrivee TO arrival_date');
        $this->addSql('ALTER TABLE trips RENAME COLUMN heure_arrivee TO arrival_time');
        $this->addSql('ALTER TABLE trips RENAME COLUMN lieu_arrivee TO arrival_location');
        $this->addSql('ALTER TABLE trips RENAME COLUMN total_places TO available_seats');
        $this->addSql('ALTER TABLE trips RENAME COLUMN prix_personne TO price_per_person');
        $this->addSql('ALTER TABLE trips RENAME COLUMN statut TO status');
        $this->addSql('ALTER TABLE trips RENAME COLUMN utilisateur_id TO driver_id');
        $this->addSql('ALTER TABLE trips RENAME COLUMN voiture_id TO vehicle_id');

        $this->addSql('ALTER TABLE brands RENAME COLUMN libelle TO label');

        $this->addSql('ALTER TABLE settings RENAME COLUMN propriete TO property');
        $this->addSql('ALTER TABLE settings RENAME COLUMN valeur TO value');
        $this->addSql('ALTER TABLE settings RENAME COLUMN configuration_id TO app_configuration_id');

        $this->addSql('ALTER TABLE bookings RENAME COLUMN credits_utilise TO credits_used');
        $this->addSql('ALTER TABLE bookings RENAME COLUMN utilisateur_id TO user_id');
        $this->addSql('ALTER TABLE bookings RENAME COLUMN covoiturage_id TO trip_id');

        $this->addSql('ALTER TABLE users RENAME COLUMN nom TO last_name');
        $this->addSql('ALTER TABLE users RENAME COLUMN prenom TO first_name');
        $this->addSql('ALTER TABLE users RENAME COLUMN telephone TO phone');
        $this->addSql('ALTER TABLE users RENAME COLUMN adresse TO address');
        $this->addSql('ALTER TABLE users RENAME COLUMN date_naissance TO birth_date');
        $this->addSql('ALTER TABLE users RENAME COLUMN pseudo TO username');
        $this->addSql('ALTER TABLE users RENAME COLUMN credit TO credit_balance');

        $this->addSql('ALTER TABLE vehicles RENAME COLUMN modele TO model');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN immatriculation TO registration_number');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN energie TO energy_type');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN couleur TO color');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN date_premiere_immatriculation TO first_registration_date');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN utilisateur_id TO owner_id');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN marque_id TO brand_id');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN owner_id TO utilisateur_id');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN brand_id TO marque_id');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN first_registration_date TO date_premiere_immatriculation');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN color TO couleur');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN energy_type TO energie');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN registration_number TO immatriculation');
        $this->addSql('ALTER TABLE vehicles RENAME COLUMN model TO modele');

        $this->addSql('ALTER TABLE users RENAME COLUMN credit_balance TO credit');
        $this->addSql('ALTER TABLE users RENAME COLUMN username TO pseudo');
        $this->addSql('ALTER TABLE users RENAME COLUMN birth_date TO date_naissance');
        $this->addSql('ALTER TABLE users RENAME COLUMN address TO adresse');
        $this->addSql('ALTER TABLE users RENAME COLUMN phone TO telephone');
        $this->addSql('ALTER TABLE users RENAME COLUMN first_name TO prenom');
        $this->addSql('ALTER TABLE users RENAME COLUMN last_name TO nom');

        $this->addSql('ALTER TABLE bookings RENAME COLUMN trip_id TO covoiturage_id');
        $this->addSql('ALTER TABLE bookings RENAME COLUMN user_id TO utilisateur_id');
        $this->addSql('ALTER TABLE bookings RENAME COLUMN credits_used TO credits_utilise');

        $this->addSql('ALTER TABLE settings RENAME COLUMN app_configuration_id TO configuration_id');
        $this->addSql('ALTER TABLE settings RENAME COLUMN value TO valeur');
        $this->addSql('ALTER TABLE settings RENAME COLUMN property TO propriete');

        $this->addSql('ALTER TABLE brands RENAME COLUMN label TO libelle');

        $this->addSql('ALTER TABLE trips RENAME COLUMN vehicle_id TO voiture_id');
        $this->addSql('ALTER TABLE trips RENAME COLUMN driver_id TO utilisateur_id');
        $this->addSql('ALTER TABLE trips RENAME COLUMN status TO statut');
        $this->addSql('ALTER TABLE trips RENAME COLUMN price_per_person TO prix_personne');
        $this->addSql('ALTER TABLE trips RENAME COLUMN available_seats TO total_places');
        $this->addSql('ALTER TABLE trips RENAME COLUMN arrival_location TO lieu_arrivee');
        $this->addSql('ALTER TABLE trips RENAME COLUMN arrival_time TO heure_arrivee');
        $this->addSql('ALTER TABLE trips RENAME COLUMN arrival_date TO date_arrivee');
        $this->addSql('ALTER TABLE trips RENAME COLUMN departure_location TO lieu_depart');
        $this->addSql('ALTER TABLE trips RENAME COLUMN departure_time TO heure_depart');
        $this->addSql('ALTER TABLE trips RENAME COLUMN departure_date TO date_depart');

        $this->addSql('ALTER TABLE reviews RENAME COLUMN user_id TO utilisateur_id');
        $this->addSql('ALTER TABLE reviews RENAME COLUMN rating TO note');
        $this->addSql('ALTER TABLE reviews RENAME COLUMN comment TO commentaire');

        $this->addSql('ALTER TABLE reviews RENAME TO avis');
        $this->addSql('ALTER TABLE app_configurations RENAME TO configuration');
        $this->addSql('ALTER TABLE trips RENAME TO covoiturage');
        $this->addSql('ALTER TABLE brands RENAME TO marque');
        $this->addSql('ALTER TABLE settings RENAME TO parametre');
        $this->addSql('ALTER TABLE bookings RENAME TO participation');
        $this->addSql('ALTER TABLE users RENAME TO utilisateur');
        $this->addSql('ALTER TABLE vehicles RENAME TO voiture');
    }
}
