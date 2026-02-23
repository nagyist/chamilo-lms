<?php

/* For licensing terms, see /license.txt */

declare(strict_types=1);

namespace Chamilo\CoreBundle\Controller\Api;

use Chamilo\CoreBundle\Entity\AccessUrl;
use Chamilo\CoreBundle\Entity\Session;
use Chamilo\CoreBundle\Entity\User;
use Chamilo\CoreBundle\Helpers\AccessUrlHelper;
use Chamilo\CoreBundle\Repository\SessionRepository;
use Chamilo\CourseBundle\Entity\CCalendarEvent;
use Chamilo\CourseBundle\Entity\CStudentPublication;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class CalendarMyStudentsScheduleAction
{
    public function __construct(
        private readonly Security $security,
        private readonly AccessUrlHelper $accessUrlHelper,
        private readonly SessionRepository $sessionRepository,
        private readonly EntityManagerInterface $em,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        /** @var User|null $user */
        $user = $this->security->getUser();
        if (!$user instanceof User) {
            return new JsonResponse([]);
        }

        $accessUrl = $this->accessUrlHelper->getCurrent();
        if (!$accessUrl instanceof AccessUrl) {
            return new JsonResponse([]);
        }

        $sid = $request->query->getInt('sid');

        // 1) No sid => return sessions where user is a coach (general or course).
        if ($sid <= 0) {
            $sessions = $this->sessionRepository
                ->getUserFollowedSessionsInAccessUrl($user, $accessUrl)
                ->getQuery()
                ->getResult()
            ;

            $sessions = array_values(array_filter(
                $sessions,
                static fn (Session $s): bool => $s->hasCoach($user)
            ));

            return new JsonResponse(array_map(
                static fn (Session $s): array => [
                    'id' => (int) $s->getId(),
                    'name' => $s->getTitle(),
                ],
                $sessions
            ));
        }

        $session = $this->sessionRepository->find($sid);
        if (!$session instanceof Session) {
            return new JsonResponse([]);
        }

        // Must be a coach in the session (tutor requirement).
        if (!$session->hasCoach($user)) {
            throw new AccessDeniedHttpException('Not allowed');
        }

        // Extra safety: ensure session is in current AccessUrl.
        if (!$this->isSessionInAccessUrl($session, $accessUrl)) {
            throw new AccessDeniedHttpException('Not allowed');
        }

        $start = $this->parseDateTime((string) $request->query->get('start', ''));
        $end = $this->parseDateTime((string) $request->query->get('end', ''));

        if (!$start || !$end) {
            return new JsonResponse([]);
        }

        // Exception rule: once coach in session => see ALL events in that session.
        $calendarEvents = $this->findCalendarEventsForSession($session, $start, $end);
        $assignmentEvents = $this->findAssignmentDeadlineEventsForSession($session, $start, $end);

        return new JsonResponse(array_merge($calendarEvents, $assignmentEvents));
    }

    private function parseDateTime(string $value): ?DateTimeImmutable
    {
        $v = trim($value);
        if ('' === $v) {
            return null;
        }

        try {
            return new DateTimeImmutable($v);
        } catch (\Throwable) {
            return null;
        }
    }

    private function isSessionInAccessUrl(Session $session, AccessUrl $accessUrl): bool
    {
        foreach ($session->getUrls() as $rel) {
            if (!method_exists($rel, 'getUrl')) {
                continue;
            }
            $url = $rel->getUrl();
            if ($url && method_exists($url, 'getId') && (int) $url->getId() === (int) $accessUrl->getId()) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findCalendarEventsForSession(Session $session, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $qb = $this->em->createQueryBuilder();

        // NOTE: This assumes CCalendarEvent is a resource linked through ResourceNode -> ResourceLinks.
        // If your field names differ, adjust start/end fields accordingly.
        $qb
            ->select('e')
            ->from(CCalendarEvent::class, 'e')
            ->innerJoin('e.resourceNode', 'rn')
            ->innerJoin('rn.resourceLinks', 'rl')
            ->andWhere('rl.session = :session')
            ->andWhere('e.startDate IS NOT NULL')
            ->andWhere('(e.startDate < :end) AND (e.endDate IS NULL OR e.endDate > :start)')
            ->setParameter('session', $session)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
        ;

        /** @var CCalendarEvent[] $events */
        $events = $qb->getQuery()->getResult();

        $out = [];
        foreach ($events as $e) {
            $id = method_exists($e, 'getIid') ? (string) $e->getIid() : (string) $e->getId();

            $startDt = method_exists($e, 'getStartDate') ? $e->getStartDate() : null;
            $endDt = method_exists($e, 'getEndDate') ? $e->getEndDate() : null;

            $out[] = [
                'id' => 'ce-'.$id,
                'title' => method_exists($e, 'getTitle') ? $e->getTitle() : 'Event',
                'start' => $startDt ? DateTimeImmutable::createFromInterface($startDt)->format(DateTimeImmutable::ATOM) : null,
                'end' => $endDt ? DateTimeImmutable::createFromInterface($endDt)->format(DateTimeImmutable::ATOM) : null,
                'allDay' => method_exists($e, 'isAllDay') ? (bool) $e->isAllDay() : false,
                'color' => method_exists($e, 'getColor') ? ($e->getColor() ?: null) : null,
                'extendedProps' => [
                    'objectType' => 'calendar_event',
                    'sessionId' => (int) $session->getId(),
                ],
            ];
        }

        return $out;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findAssignmentDeadlineEventsForSession(Session $session, DateTimeImmutable $start, DateTimeImmutable $end): array
    {
        $qb = $this->em->createQueryBuilder();

        // Deadline = expiresOn if set, else endsOn.
        // We show it as all-day event.
        $qb
            ->select('p', 'a', 'rn', 'rl', 'c')
            ->from(CStudentPublication::class, 'p')
            ->innerJoin('p.assignment', 'a')
            ->innerJoin('p.resourceNode', 'rn')
            ->innerJoin('rn.resourceLinks', 'rl')
            ->leftJoin('rl.course', 'c')
            ->andWhere('rl.session = :session')
            ->andWhere('(a.expiresOn IS NOT NULL OR a.endsOn IS NOT NULL)')
            ->andWhere('(
                (a.expiresOn IS NOT NULL AND a.expiresOn >= :start AND a.expiresOn < :end)
                OR
                (a.expiresOn IS NULL AND a.endsOn IS NOT NULL AND a.endsOn >= :start AND a.endsOn < :end)
            )')
            ->setParameter('session', $session)
            ->setParameter('start', $start)
            ->setParameter('end', $end)
        ;

        /** @var CStudentPublication[] $pubs */
        $pubs = $qb->getQuery()->getResult();

        $out = [];
        foreach ($pubs as $p) {
            $assignment = $p->getAssignment();
            if (!$assignment) {
                continue;
            }

            $deadline = $assignment->getExpiresOn() ?: $assignment->getEndsOn();
            if (!$deadline) {
                continue;
            }

            $deadlineI = DateTimeImmutable::createFromInterface($deadline);

            // Try to get course title from the first resource link (if any).
            $courseTitle = null;
            $firstLink = method_exists($p, 'getFirstResourceLink') ? $p->getFirstResourceLink() : null;
            if ($firstLink && method_exists($firstLink, 'getCourse') && $firstLink->getCourse()) {
                $courseTitle = $firstLink->getCourse()->getTitle();
            }

            $out[] = [
                'id' => 'as-'.(string) $p->getIid(),
                'title' => $p->getTitle(),
                'start' => $deadlineI->format('Y-m-d'),
                'end' => $deadlineI->format('Y-m-d'),
                'allDay' => true,
                'color' => 'rgba(255,140,0,0.9)',
                'extendedProps' => [
                    'objectType' => 'assignment',
                    'sessionId' => (int) $session->getId(),
                    'courseTitle' => $courseTitle,
                    'publicationId' => (int) $p->getIid(),
                ],
            ];
        }

        return $out;
    }
}
