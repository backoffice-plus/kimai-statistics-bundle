<?php

namespace KimaiPlugin\StatisticsBundle\Controller;

use App\API\BaseApiController;
use App\Entity\Activity;
use App\Entity\User;
use App\Repository\Query\UserQuery;
use App\Repository\TimesheetRepository;
use App\Repository\UserRepository;
use App\Timesheet\TimesheetService;
use Doctrine\ORM\Query\Expr;
use FOS\RestBundle\Controller\Annotations as Rest;
use FOS\RestBundle\Request\ParamFetcherInterface;
use FOS\RestBundle\View\View;
use FOS\RestBundle\View\ViewHandlerInterface;
use Nelmio\ApiDocBundle\Annotation\Security as ApiSecurity;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints;


#[Route(path: '/statistics')]
#[IsGranted('IS_AUTHENTICATED_REMEMBERED')]
final class StatisticsController extends BaseApiController
{
    public const GROUPS_ENTITY = ['Default', 'Entity', 'Timesheet', 'Timesheet_Entity', 'Not_Expanded'];
    public const GROUPS_ENTITY_FULL = ['Default', 'Entity', 'Timesheet', 'Timesheet_Entity', 'Expanded'];
    public const GROUPS_FORM = ['Default', 'Entity', 'Timesheet', 'Not_Expanded'];
    public const GROUPS_COLLECTION = ['Default', 'Collection', 'Timesheet', 'Not_Expanded'];
    public const GROUPS_COLLECTION_FULL = ['Default', 'Collection', 'Timesheet', 'Expanded'];

    public function __construct(
        private ViewHandlerInterface $viewHandler,
        private TimesheetRepository  $repository,
        private UserRepository  $userRepository,
        private TimesheetService     $service,
        private AuthorizationCheckerInterface     $security,
    ) {
    }


    #[Rest\Get(path: '', name: 'get_statistics')]
    #[ApiSecurity(name: 'apiUser')]
    #[ApiSecurity(name: 'apiToken')]
    #[Rest\QueryParam(name: 'begin', requirements: [new Constraints\DateTime(format: 'Y-m-d\TH:i:s')], strict: true, nullable: true, description: 'Only records after this date will be included (format: HTML5)')]
    #[Rest\QueryParam(name: 'end', requirements: [new Constraints\DateTime(format: 'Y-m-d\TH:i:s')], strict: true, nullable: true, description: 'Only records before this date will be included (format: HTML5)')]

    public function getAction(ParamFetcherInterface $paramFetcher): Response
    {
        /** @var User $user */
        $user = $this->getUser();
        $dateTimeFactory = $this->getDateTimeFactory($user);

        $beginStr = $paramFetcher->get('begin');
        if (\is_string($beginStr) && $beginStr !== '') {
           $begin = $dateTimeFactory->createDateTime($beginStr);
        }
        else {
            $begin = $dateTimeFactory->createStartOfYear();
        }

        $endStr = $paramFetcher->get('end');
        if (\is_string($endStr) && $endStr !== '') {
            $end = $dateTimeFactory->createDateTime($endStr);
        }
        else {
            $end = $dateTimeFactory->createEndOfYear();
        }

        $dateRange = new \DatePeriod($begin, new \DateInterval('P1D'), $end);

        $allUsers = [];
        if ($this->security->isGranted('view_all_data')) {
            $allUsers = null;
        }
        elseif($this->security->isGranted('export_other_timesheet')) {
            $query = new UserQuery();
            $query->setSystemAccount(false);
            $query->setCurrentUser($user);
            $query->setSearchTeams($user->getTeams());
            $allUsers = $this->userRepository->getUsersForQuery($query);
        }
        elseif ($this->security->isGranted('export_own_timesheet')) {
            $allUsers = [$user];
        }

        $statistics = $this->getYearStatistics($dateRange, $allUsers);

        $view = new View($statistics, 200);
        $view->getContext()->setGroups(self::GROUPS_ENTITY);

        return $this->viewHandler->handle($view);
    }

    private function getYearStatistics(\DatePeriod $datePeriod, array|null $users = []): array
    {
        $qb = $this->repository->createQueryBuilder('t');
        $qb
            ->leftJoin(Activity::class, 'a', Expr\Join::WITH, 'a.id = t.activity')
            ->leftJoin(User::class, 'u', Expr\Join::WITH, 'u.id = t.user')
            ->select('COALESCE(SUM(t.duration), 0) as duration')
            ->addSelect('DATE(t.date) as day')
            ->addSelect('min(t.begin) as min_date')
            ->addSelect('max(t.end) as max_date')
            ->addSelect('t.category')
            ->addSelect('t.billable')
            ->addSelect('a.id as activity_id')
            ->addSelect('u.id as user_id')
            ->where($qb->expr()->isNotNull('t.end'))
            ->andWhere($qb->expr()->between('t.begin', ':begin', ':end'))
            ->setParameter('begin', $datePeriod->getStartDate())
            ->setParameter('end', $datePeriod->getEndDate())
            ->addGroupBy('day')
            ->addGroupBy('t.category')
            ->addGroupBy('t.billable')
            ->addGroupBy('a.id')
            ->addGroupBy('u.id');

        if(is_array($users)) {
            if(0 === count($users)) {
                return [];
            }

            $ids = array_map(fn($user)=>$user->getId(), $users);
            $qb
                ->andWhere($qb->expr()->in('t.user', ':users'))
                ->setParameter('users', $ids);
        }

        return $qb->getQuery()->getResult();
    }
}
