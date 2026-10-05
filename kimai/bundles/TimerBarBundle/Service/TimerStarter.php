<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimerBarBundle\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Timesheet\TimesheetService;
use KimaiPlugin\TimerBarBundle\Repository\TimerBarRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Starts time records from the quick start bar: new ones from the bar's fields, and continued ones
 * that copy a past record. Both start now, and go through Kimai's TimesheetService so its
 * validation, rounding and running-record limit apply.
 */
final class TimerStarter
{
  public const BILLABLE_AUTOMATIC = 'auto';
  public const BILLABLE_YES = 'yes';
  public const BILLABLE_NO = 'no';

  /**
   * Shortest and longest tag name Kimai accepts.
   *
   * @var array{0: int, 1: int}
   */
  private const TAG_LENGTH = [ 2, 100 ];

  /**
   * @param TimesheetService $timesheetService Creates and saves time records the way Kimai does.
   * @param TimerBarRepository $repository Finds and creates tags.
   * @param AuthorizationCheckerInterface $security Checks the billable and tag permissions.
   */
  public function __construct(
    private readonly TimesheetService $timesheetService,
    private readonly TimerBarRepository $repository,
    private readonly AuthorizationCheckerInterface $security
  )
  {
  }

  /**
   * Starts a new record now.
   *
   * @param User $user The logged-in user.
   * @param Project $project The project to book on.
   * @param Activity $activity The activity to book on.
   * @param string $description What the user is working on.
   * @param array<int, Tag> $tags Existing tags to add.
   * @param array<int, string> $newTagNames Names of tags to create and add, if the user may.
   * @param string $billable One of the BILLABLE_ constants.
   * @return Timesheet
   */
  public function start( User $user, Project $project, Activity $activity, string $description, array $tags, array $newTagNames, string $billable ) : Timesheet
  {
    $timesheet = $this->createStartedNow( $user );
    $timesheet->setProject( $project );
    $timesheet->setActivity( $activity );
    $timesheet->setDescription( $description );

    foreach ( array_merge( $tags, $this->createTags( $newTagNames ) ) as $tag )
    {
      $timesheet->addTag( $tag );
    }

    $this->applyBillable( $timesheet, $billable );

    return $this->timesheetService->saveTimesheet( $timesheet );
  }

  /**
   * Starts a new record now with the project, activity, description, tags and billable
   * setting of a past record.
   *
   * @param User $user The logged-in user.
   * @param Timesheet $source The record to continue.
   * @return Timesheet
   */
  public function continueEntry( User $user, Timesheet $source ) : Timesheet
  {
    $timesheet = $this->createStartedNow( $user );
    $timesheet->setProject( $source->getProject() );
    $timesheet->setActivity( $source->getActivity() );
    $timesheet->setDescription( $source->getDescription() );

    foreach ( $source->getTags() as $tag )
    {
      $timesheet->addTag( $tag );
    }

    $timesheet->setBillableMode( $source->getBillableMode() );

    return $this->timesheetService->restartTimesheet( $timesheet, $source );
  }

  /**
   * Splits the free-text tag field into valid, unique tag names.
   *
   * @param string $input Comma-separated tag names.
   * @return array<int, string>
   */
  public function parseTagNames( string $input ) : array
  {
    $names = [];
    foreach ( explode( ',', $input ) as $name )
    {
      $name = trim( $name );
      $length = mb_strlen( $name );

      if ( $length >= self::TAG_LENGTH[ 0 ] && $length <= self::TAG_LENGTH[ 1 ] )
      {
        $names[ mb_strtolower( $name ) ] = $name;
      }
    }

    return array_values( $names );
  }

  /**
   * Creates a record for the user that starts now.
   *
   * @param User $user The logged-in user.
   * @return Timesheet
   */
  private function createStartedNow( User $user ) : Timesheet
  {
    $timesheet = $this->timesheetService->createNewTimesheet( $user );
    $this->timesheetService->prepareNewTimesheet( $timesheet );

    return $timesheet;
  }

  /**
   * Returns the tags with the given names, creating missing ones when the user may create tags.
   *
   * @param array<int, string> $names Tag names.
   * @return array<int, Tag>
   */
  private function createTags( array $names ) : array
  {
    if ( empty( $names ) || !$this->security->isGranted( 'create_tag' ) )
    {
      return [];
    }

    return array_map( [ $this->repository, 'findOrCreateTag' ], $names );
  }

  /**
   * Applies the billable choice when the user may change it; otherwise Kimai decides.
   *
   * @param Timesheet $timesheet The new record.
   * @param string $billable One of the BILLABLE_ constants.
   * @return void
   */
  private function applyBillable( Timesheet $timesheet, string $billable ) : void
  {
    if ( $billable === self::BILLABLE_AUTOMATIC || !$this->security->isGranted( 'edit_billable', $timesheet ) )
    {
      return;
    }

    $timesheet->setBillableMode( $billable === self::BILLABLE_YES ? Timesheet::BILLABLE_YES : Timesheet::BILLABLE_NO );
  }
}
