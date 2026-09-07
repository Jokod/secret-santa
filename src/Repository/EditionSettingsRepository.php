<?php

namespace App\Repository;

use App\Entity\EditionSettings;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<EditionSettings>
 */
class EditionSettingsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EditionSettings::class);
    }

    public function getSettings(): EditionSettings
    {
        $settings = $this->find(EditionSettings::SINGLETON_ID);
        if ($settings instanceof EditionSettings) {
            return $settings;
        }

        $settings = new EditionSettings();
        $em = $this->getEntityManager();
        $em->persist($settings);
        $em->flush();

        return $settings;
    }
}
