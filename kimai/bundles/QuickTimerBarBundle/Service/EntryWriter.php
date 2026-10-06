<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\Service;

use App\Entity\Tag;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Timesheet\TimesheetService;
use DateTime;
use DateTimeImmutable;
use KimaiPlugin\QuickTimerBarBundle\Model\EntryInput;
use KimaiPlugin\QuickTimerBarBundle\Repository\TimerBarRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Creates and changes time records from the quick start bar: records that start now, records
 * entered with a start and end time, continued records, and changes to the running record.
 * Everything goes through Kimai's TimesheetService, so its validation, rounding and
 * running-record limit apply.
 */
final class EntryWriter
{
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
   * Starts a new running record, now or at an earlier time.
   *
   * @param User $user The logged-in user.
   * @param EntryInput $input What the user entered.
   * @param DateTimeImmutable|null $begin When the work started, or null for now.
   * @return Timesheet
   */
  public function start( User $user, EntryInput $input, ?DateTimeImmutable $begin = null ) : Timesheet
  {
    $timesheet = $this->createNew( $user );
    if ( $begin !== null )
    {
      $timesheet->setBegin( DateTime::createFromImmutable( $begin ) );
    }
    $this->apply( $timesheet, $input );

    return $this->timesheetService->saveTimesheet( $timesheet );
  }

  /**
   * Adds a finished record with the given start and end.
   *
   * @param User $user The logged-in user.
   * @param EntryInput $input What the user entered.
   * @param DateTimeImmutable $begin When the work started.
   * @param DateTimeImmutable $end When the work ended.
   * @return Timesheet
   */
  public function add( User $user, EntryInput $input, DateTimeImmutable $begin, DateTimeImmutable $end ) : Timesheet
  {
    $timesheet = $this->createNew( $user );
    $timesheet->setBegin( DateTime::createFromImmutable( $begin ) );
    $timesheet->setEnd( DateTime::createFromImmutable( $end ) );
    $this->apply( $timesheet, $input );

    return $this->timesheetService->saveTimesheet( $timesheet );
  }

  /**
   * Changes the running record: what it is, and optionally when it started.
   *
   * @param Timesheet $timesheet The running record.
   * @param EntryInput $input What the user entered.
   * @param DateTimeImmutable|null $begin The new start, or null to keep it.
   * @return Timesheet
   */
  public function update( Timesheet $timesheet, EntryInput $input, ?DateTimeImmutable $begin ) : Timesheet
  {
    foreach ( $timesheet->getTags()->toArray() as $tag )
    {
      $timesheet->removeTag( $tag );
    }

    $this->apply( $timesheet, $input );

    if ( $begin !== null )
    {
      $timesheet->setBegin( DateTime::createFromImmutable( $begin ) );
    }

    $this->timesheetService->validateTimesheet( $timesheet );

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
    $timesheet = $this->createNew( $user );
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
  private function createNew( User $user ) : Timesheet
  {
    $timesheet = $this->timesheetService->createNewTimesheet( $user );
    $this->timesheetService->prepareNewTimesheet( $timesheet );

    return $timesheet;
  }

  /**
   * Copies what the user entered onto a record.
   *
   * @param Timesheet $timesheet The record.
   * @param EntryInput $input What the user entered.
   * @return void
   */
  private function apply( Timesheet $timesheet, EntryInput $input ) : void
  {
    $timesheet->setProject( $input->getProject() );
    $timesheet->setActivity( $input->getActivity() );
    $timesheet->setDescription( $input->getDescription() );

    foreach ( array_merge( $input->getTags(), $this->createTags( $input->getNewTagNames() ) ) as $tag )
    {
      $timesheet->addTag( $tag );
    }

    $this->applyBillable( $timesheet, $input->getBillable() );
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
   * @param Timesheet $timesheet The record.
   * @param string $billable One of the EntryInput::BILLABLE_ constants.
   * @return void
   */
  private function applyBillable( Timesheet $timesheet, string $billable ) : void
  {
    if ( !$this->security->isGranted( 'edit_billable', $timesheet ) )
    {
      return;
    }

    $timesheet->setBillableMode( match ( $billable )
    {
      EntryInput::BILLABLE_YES => Timesheet::BILLABLE_YES,
      EntryInput::BILLABLE_NO => Timesheet::BILLABLE_NO,
      default => Timesheet::BILLABLE_AUTOMATIC,
    } );
  }
}
