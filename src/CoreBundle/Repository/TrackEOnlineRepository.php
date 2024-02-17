<?php

declare(strict_types=1);

/* For licensing terms, see /license.txt */

namespace Chamilo\CoreBundle\Repository;

use Chamilo\CoreBundle\Entity\TrackEOnline;
use Chamilo\CoreBundle\Settings\SettingsManager;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\NonUniqueResultException;
use Doctrine\Persistence\ManagerRegistry;

class TrackEOnlineRepository extends ServiceEntityRepository
{
    private $settingsManager;

    public function __construct(ManagerRegistry $registry, SettingsManager $settingsManager)
    {
        parent::__construct($registry, TrackEOnline::class);
        $this->settingsManager = $settingsManager;
    }

    public function isUserOnline(int $userId): bool
    {
        $accessUrlId = 1;
        $timeLimit = $this->settingsManager->getSetting('display.time_limit_whosonline');

        $onlineTime = new \DateTime();
        $onlineTime->modify("-{$timeLimit} minutes");

        $qb = $this->createQueryBuilder('t')
            ->select('COUNT(t.loginUserId)')
            ->where('t.loginUserId = :userId')
            ->andWhere('t.accessUrlId = :accessUrlId')
            ->andWhere('t.loginDate >= :limitDate')
            ->setParameter('userId', $userId)
            ->setParameter('accessUrlId', $accessUrlId)
            ->setParameter('limitDate', $onlineTime)
            ->setMaxResults(1);

        try {
            $count = $qb->getQuery()->getSingleScalarResult();
            return $count > 0;
        } catch (NonUniqueResultException $e) {
            // Handle exception
            return false;
        }
    }
}
