<?php

namespace App\Repository;

use App\Entity\Covoiturage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Covoiturage>
 */
class CovoiturageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Covoiturage::class);
    }

    public function searchAvailableCovoiturages(
        ?string $departure,
        ?string $arrival,
        ?string $date,
        ?int $passengers = null,
        bool $eco = false,
        ?float $maxPrice = null,
        ?int $maxDuration = null,
        ?float $minRating = null
    ): array {
        $qb = $this->createQueryBuilder('c')
            ->leftJoin('c.voiture', 'v')
            ->leftJoin('c.utilisateur', 'u')
            ->orderBy('c.date_depart', 'ASC');

        if (!empty($departure)) {
            $qb->andWhere('c.lieu_depart LIKE :departure')
            ->setParameter('departure', '%' . $departure . '%');
        }

        if (!empty($arrival)) {
            $qb->andWhere('c.lieu_arrivee LIKE :arrival')
            ->setParameter('arrival', '%' . $arrival . '%');
        }

        if (!empty($date)) {
            $start = new \DateTime($date . ' 00:00:00');
            $end = new \DateTime($date . ' 23:59:59');

            $qb->andWhere('c.date_depart BETWEEN :start AND :end')
            ->setParameter('start', $start)
            ->setParameter('end', $end);
        }

        if ($eco) {
            $qb->andWhere('v.energie = :energie')
            ->setParameter('energie', 'Electrique');
        }

        if ($maxPrice !== null) {
            $qb->andWhere('c.prix_personne <= :maxPrice')
            ->setParameter('maxPrice', $maxPrice);
        }

        $results = $qb->getQuery()->getResult();

        if ($minRating !== null) {
            $results = array_filter($results, function ($trip) use ($minRating) {
                $user = $trip->getUtilisateur();
                $rating = $user?->getAverageValidatedRating();

                return $rating !== null && $rating >= $minRating;
            });

            $results = array_values($results);
        }

        if ($passengers !== null) {
            $results = array_filter($results, function ($trip) use ($passengers) {
                return $trip->getTotalPlaces() >= $passengers;
            });
        }

        if ($maxDuration !== null) {
            $results = array_filter($results, function ($trip) use ($maxDuration) {
            $dateDepart = $trip->getDateDepart();
            $heureDepart = $trip->getHeureDepart();
            $dateArrivee = $trip->getDateArrivee();
            $heureArrivee = $trip->getHeureArrivee();

            if (!$dateDepart || !$heureDepart || !$dateArrivee || !$heureArrivee) {
                return false;
            }

            $start = new \DateTime(
                $dateDepart->format('Y-m-d') . ' ' . $heureDepart->format('H:i:s')
            );

            $end = new \DateTime(
                $dateArrivee->format('Y-m-d') . ' ' . $heureArrivee->format('H:i:s')
            );

            if ($end < $start) {
                return false;
            }

            $duration = $start->diff($end);

            $minutes =
                ($duration->days * 24 * 60) +
                ($duration->h * 60) +
                $duration->i;

            return $minutes <= $maxDuration;
        });

        $results = array_values($results);
        }

        return array_values($results);
    }

    public function findAvailableCovoiturages(): array
    {
        return $this->createQueryBuilder('c')
            ->leftJoin('c.voiture', 'v')
            ->leftJoin('c.utilisateur', 'u')
            ->andWhere('c.total_places > 0')
            ->orderBy('c.date_depart', 'ASC')
            ->getQuery()
            ->getResult();
    }
    //    /**
    //     * @return Covoiturage[] Returns an array of Covoiturage objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('c.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?Covoiturage
    //    {
    //        return $this->createQueryBuilder('c')
    //            ->andWhere('c.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
}
