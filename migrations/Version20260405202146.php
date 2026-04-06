<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260405202146 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings ALTER outcome_status DROP DEFAULT');
        $this->addSql('ALTER TABLE bookings ALTER payout_applied DROP DEFAULT');
        $this->addSql('ALTER INDEX idx_ab55e24ffb88e14f RENAME TO IDX_7A853C35A76ED395');
        $this->addSql('ALTER INDEX idx_ab55e24f62671590 RENAME TO IDX_7A853C35A5BC2E0E');
        $this->addSql('ALTER INDEX idx_8f91abf0fb88e14f RENAME TO IDX_6970EB0FA76ED395');
        $this->addSql('ALTER INDEX idx_reviews_trip RENAME TO IDX_6970EB0FA5BC2E0E');
        $this->addSql('ALTER INDEX idx_reviews_author RENAME TO IDX_6970EB0FF675F31B');
        $this->addSql('ALTER INDEX idx_reviews_moderated_by RENAME TO IDX_6970EB0F8EDA19B0');
        $this->addSql('ALTER INDEX idx_acc7904173f32dd8 RENAME TO IDX_E545A0C5E7CDCC79');
        $this->addSql('ALTER INDEX idx_trip_issues_trip RENAME TO IDX_35722429A5BC2E0E');
        $this->addSql('ALTER INDEX idx_trip_issues_booking RENAME TO IDX_357224293301C60');
        $this->addSql('ALTER INDEX idx_trip_issues_participant RENAME TO IDX_357224299D1C3019');
        $this->addSql('ALTER INDEX idx_trip_issues_driver RENAME TO IDX_35722429C3423909');
        $this->addSql('ALTER INDEX idx_trip_issues_resolved_by RENAME TO IDX_357224296713A32B');
        $this->addSql('ALTER INDEX idx_28c79e89fb88e14f RENAME TO IDX_AA7370DAC3423909');
        $this->addSql('ALTER INDEX idx_28c79e89181a8ba RENAME TO IDX_AA7370DA545317D1');
        $this->addSql('ALTER INDEX idx_e9e2810ffb88e14f RENAME TO IDX_1FCE69FA7E3C61F9');
        $this->addSql('ALTER INDEX idx_e9e2810f4827b9b2 RENAME TO IDX_1FCE69FA44F5D008');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE bookings ALTER outcome_status SET DEFAULT \'pending\'');
        $this->addSql('ALTER TABLE bookings ALTER payout_applied SET DEFAULT false');
        $this->addSql('ALTER INDEX idx_7a853c35a5bc2e0e RENAME TO idx_ab55e24f62671590');
        $this->addSql('ALTER INDEX idx_7a853c35a76ed395 RENAME TO idx_ab55e24ffb88e14f');
        $this->addSql('ALTER INDEX idx_6970eb0fa76ed395 RENAME TO idx_8f91abf0fb88e14f');
        $this->addSql('ALTER INDEX idx_6970eb0fa5bc2e0e RENAME TO idx_reviews_trip');
        $this->addSql('ALTER INDEX idx_6970eb0ff675f31b RENAME TO idx_reviews_author');
        $this->addSql('ALTER INDEX idx_6970eb0f8eda19b0 RENAME TO idx_reviews_moderated_by');
        $this->addSql('ALTER INDEX idx_e545a0c5e7cdcc79 RENAME TO idx_acc7904173f32dd8');
        $this->addSql('ALTER INDEX idx_357224296713a32b RENAME TO idx_trip_issues_resolved_by');
        $this->addSql('ALTER INDEX idx_35722429a5bc2e0e RENAME TO idx_trip_issues_trip');
        $this->addSql('ALTER INDEX idx_357224293301c60 RENAME TO idx_trip_issues_booking');
        $this->addSql('ALTER INDEX idx_357224299d1c3019 RENAME TO idx_trip_issues_participant');
        $this->addSql('ALTER INDEX idx_35722429c3423909 RENAME TO idx_trip_issues_driver');
        $this->addSql('ALTER INDEX idx_aa7370da545317d1 RENAME TO idx_28c79e89181a8ba');
        $this->addSql('ALTER INDEX idx_aa7370dac3423909 RENAME TO idx_28c79e89fb88e14f');
        $this->addSql('ALTER INDEX idx_1fce69fa7e3c61f9 RENAME TO idx_e9e2810ffb88e14f');
        $this->addSql('ALTER INDEX idx_1fce69fa44f5d008 RENAME TO idx_e9e2810f4827b9b2');
    }
}
