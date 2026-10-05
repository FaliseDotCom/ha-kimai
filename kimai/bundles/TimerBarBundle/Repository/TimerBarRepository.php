<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimerBarBundle\Repository;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\ProjectRepository;
use App\Repository\Query\ActivityFormTypeQuery;
use App\Repository\Query\ProjectFormTypeQuery;
use App\Repository\TimesheetRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Reads what the timer bar offers: the projects and activities a user may book on, their
 * recent entries for suggestions, and their running entry.
 *
 * @phpstan-type Suggestion array{description: string, projectId: int, activityId: int}
 */
final class TimerBarRepository
{
  /**
   * How many recent descriptions are offered as suggestions.
   *
   * @var int
   */
  private const SUGGESTION_LIMIT = 50;

  /**
   * How far back suggestions reach.
   *
   * @var string
   */
  private const SUGGESTION_PERIOD = '-120 days';

  /**
   * @param EntityManagerInterface $entityManager Runs the suggestion query.
   * @param ProjectRepository $projectRepository Finds the projects a user may book on.
   * @param ActivityRepository $activityRepository Finds the activities a user may book on.
   * @param TimesheetRepository $timesheetRepository Finds running entries.
   */
  public function __construct(
    private readonly EntityManagerInterface $entityManager,
    private readonly ProjectRepository $projectRepository,
    private readonly ActivityRepository $activityRepository,
    private readonly TimesheetRepository $timesheetRepository
  )
  {
  }

  /**
   * Returns the visible projects the user may book on, ordered by customer and name.
   *
   * @param User $user The logged-in user.
   * @return array<int, Project>
   */
  public function findProjects( User $user ) : array
  {
    $query = new ProjectFormTypeQuery();
    $query->setUser( $user );
    $query->setWithCustomer( true );

    $projects = [];
    foreach ( $this->projectRepository->getQueryBuilderForFormType( $query )->getQuery()->getResult() as $project )
    {
      if ( $project instanceof Project && $project->getId() !== null )
      {
        $projects[ $project->getId() ] = $project;
      }
    }

    return $projects;
  }

  /**
   * Returns the visible activities the user may book on: global ones and project ones.
   *
   * @param User $user The logged-in user.
   * @return array<int, Activity>
   */
  public function findActivities( User $user ) : array
  {
    $query = new ActivityFormTypeQuery();
    $query->setUser( $user );

    $activities = [];
    foreach ( $this->activityRepository->getQueryBuilderForFormType( $query )->getQuery()->getResult() as $activity )
    {
      if ( $activity instanceof Activity && $activity->getId() !== null )
      {
        $activities[ $activity->getId() ] = $activity;
      }
    }

    return $activities;
  }

  /**
   * Returns the user's recent distinct combinations of description, project and activity,
   * most recent first.
   *
   * @param User $user The logged-in user.
   * @return array<int, Suggestion>
   */
  public function findSuggestions( User $user ) : array
  {
    $rows = $this->entityManager->createQueryBuilder()
      ->select(
        't.description AS description',
        'IDENTITY( t.project ) AS projectId',
        'IDENTITY( t.activity ) AS activityId',
        'MAX( t.begin ) AS lastUsed'
      )
      ->from( Timesheet::class, 't' )
      ->where( 't.user = :user' )
      ->andWhere( 't.begin >= :since' )
      ->andWhere( "t.description IS NOT NULL AND t.description <> ''" )
      ->groupBy( 't.description, t.project, t.activity' )
      ->orderBy( 'lastUsed', 'DESC' )
      ->setMaxResults( self::SUGGESTION_LIMIT )
      ->setParameter( 'user', $user )
      ->setParameter( 'since', new DateTimeImmutable( self::SUGGESTION_PERIOD ) )
      ->getQuery()
      ->getArrayResult();

    $suggestions = [];
    foreach ( $rows as $row )
    {
      $suggestions[] = [
        'description' => trim( (string) $row[ 'description' ] ),
        'projectId' => (int) $row[ 'projectId' ],
        'activityId' => (int) $row[ 'activityId' ],
      ];
    }

    return $suggestions;
  }

  /**
   * Returns the user's most recently started running entry, or null when nothing runs.
   *
   * @param User $user The logged-in user.
   * @return Timesheet|null
   */
  public function findRunningEntry( User $user ) : ?Timesheet
  {
    $latest = null;
    foreach ( $this->timesheetRepository->getActiveEntries( $user ) as $entry )
    {
      if ( $latest === null || $entry->getBegin() > $latest->getBegin() )
      {
        $latest = $entry;
      }
    }

    return $latest;
  }

  /**
   * Returns one of the user's running entries by ID, or null when it is not theirs or not running.
   *
   * @param User $user The logged-in user.
   * @param int $id The entry ID.
   * @return Timesheet|null
   */
  public function findRunningEntryById( User $user, int $id ) : ?Timesheet
  {
    foreach ( $this->timesheetRepository->getActiveEntries( $user ) as $entry )
    {
      if ( $entry->getId() === $id )
      {
        return $entry;
      }
    }

    return null;
  }
}
