<?php

declare( strict_types=1 );

namespace KimaiPlugin\UiImprovementsBundle\Service;

use App\Configuration\SystemConfiguration;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Timesheet\DateTimeFactory;
use App\Timesheet\TimesheetService;
use App\Validator\ValidationFailedException;
use DateTime;
use DateTimeImmutable;
use DateTimeZone;
use KimaiPlugin\UiImprovementsBundle\Exception\InvalidInputException;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Changes one field of a time record edited in the list, and saves it through Kimai's
 * TimesheetService, so its validation, rounding and rate calculation apply.
 *
 * Times arrive as 24-hour HH:MM and dates as YYYY-MM-DD, in the user's time zone; durations
 * and breaks arrive in minutes. The script converts what the user typed.
 */
final class EntryEditor
{
  public const FIELD_DATE = 'date';
  public const FIELD_BEGIN = 'begin';
  public const FIELD_END = 'end';
  public const FIELD_DURATION = 'duration';
  public const FIELD_BREAK = 'break';
  public const FIELD_DESCRIPTION = 'description';
  public const FIELD_PROJECT = 'project';
  public const FIELD_ACTIVITY = 'activity';
  public const FIELD_TAGS = 'tags';
  public const FIELD_BILLABLE = 'billable';

  /**
   * A 24-hour time as the script sends it.
   *
   * @var string
   */
  private const TIME_PATTERN = '/^(\d{1,2}):(\d{2})$/';

  /**
   * Date format the script sends.
   *
   * @var string
   */
  private const DATE_FORMAT = 'Y-m-d';

  /**
   * Shortest and longest tag name Kimai accepts.
   *
   * @var array{0: int, 1: int}
   */
  private const TAG_LENGTH = [ 2, 100 ];

  /**
   * Seconds per minute.
   *
   * @var int
   */
  private const MINUTE = 60;

  /**
   * @param TimesheetService $timesheetService Validates and saves records, and knows the tracking mode.
   * @param SystemConfiguration $configuration Tells whether breaks are enabled.
   * @param AuthorizationCheckerInterface $security Checks the billable permission.
   * @param BookingOptions $options Finds bookable projects, activities and tags.
   */
  public function __construct(
    private readonly TimesheetService $timesheetService,
    private readonly SystemConfiguration $configuration,
    private readonly AuthorizationCheckerInterface $security,
    private readonly BookingOptions $options
  )
  {
  }

  /**
   * Returns the fields of a record the user may change in the list.
   *
   * @param Timesheet $entry The record.
   * @return array<int, string>
   */
  public function getEditableFields( Timesheet $entry ) : array
  {
    $mode = $this->timesheetService->getActiveTrackingMode();
    $fields = [ self::FIELD_DESCRIPTION, self::FIELD_PROJECT, self::FIELD_ACTIVITY, self::FIELD_TAGS ];

    if ( $mode->canEditBegin() )
    {
      array_push( $fields, self::FIELD_DATE, self::FIELD_BEGIN );
    }

    if ( $mode->canEditEnd() )
    {
      $fields[] = self::FIELD_END;
    }

    if ( $mode->canEditDuration() )
    {
      $fields[] = self::FIELD_DURATION;
    }

    if ( $this->configuration->isBreakTimeEnabled() && $mode->canEditDuration() )
    {
      $fields[] = self::FIELD_BREAK;
    }

    if ( $this->security->isGranted( 'edit_billable', $entry ) )
    {
      $fields[] = self::FIELD_BILLABLE;
    }

    return $fields;
  }

  /**
   * Changes one field and saves the record.
   *
   * @param Timesheet $entry The record, owned by the user.
   * @param User $user The logged-in user.
   * @param string $field One of the FIELD_ constants.
   * @param array<string, string> $values The posted values: "value", and "activity" with a project.
   * @return void
   * @throws InvalidInputException When a value cannot be used.
   * @throws ValidationFailedException When Kimai refuses the changed record.
   */
  public function update( Timesheet $entry, User $user, string $field, array $values ) : void
  {
    if ( !in_array( $field, $this->getEditableFields( $entry ), true ) )
    {
      throw new InvalidInputException( 'inline_edit.not_editable' );
    }

    $value = trim( $values[ 'value' ] ?? '' );
    $timezone = DateTimeFactory::createByUser( $user )->getTimezone();

    match ( $field )
    {
      self::FIELD_DATE => $this->changeDate( $entry, $value, $timezone ),
      self::FIELD_BEGIN => $this->changeBegin( $entry, $value, $timezone ),
      self::FIELD_END => $this->changeEnd( $entry, $value, $timezone ),
      self::FIELD_DURATION => $this->changeDuration( $entry, $value ),
      self::FIELD_BREAK => $this->changeBreak( $entry, $value ),
      self::FIELD_DESCRIPTION => $entry->setDescription( $value === '' ? null : $value ),
      self::FIELD_PROJECT => $this->changeProject( $entry, $user, $value, trim( $values[ self::FIELD_ACTIVITY ] ?? '' ) ),
      self::FIELD_ACTIVITY => $this->changeActivity( $entry, $user, $value ),
      self::FIELD_TAGS => $this->changeTags( $entry, $value ),
      self::FIELD_BILLABLE => $this->changeBillable( $entry, $value === '1' ),
      default => throw new InvalidInputException( 'inline_edit.not_editable' ),
    };

    $this->timesheetService->validateTimesheet( $entry );
    $this->timesheetService->saveTimesheet( $entry );
  }

  /**
   * Moves the record to another day, keeping its times and duration.
   *
   * @param Timesheet $entry The record.
   * @param string $value The new date.
   * @param DateTimeZone $timezone The user's time zone.
   * @return void
   */
  private function changeDate( Timesheet $entry, string $value, DateTimeZone $timezone ) : void
  {
    $day = DateTimeImmutable::createFromFormat( '!' . self::DATE_FORMAT, $value, $timezone );
    $begin = $this->getLocalBegin( $entry, $timezone );

    if ( $day === false || $day->format( self::DATE_FORMAT ) !== $value )
    {
      throw new InvalidInputException( 'inline_edit.invalid_date' );
    }

    $newBegin = $day->setTime( (int) $begin->format( 'G' ), (int) $begin->format( 'i' ) );
    $end = $entry->getEnd();

    $entry->setBegin( DateTime::createFromImmutable( $newBegin ) );
    if ( $end !== null )
    {
      $length = $end->getTimestamp() - $begin->getTimestamp();
      $this->setEnd( $entry, $newBegin->modify( '+' . $length . ' seconds' ) );
    }
  }

  /**
   * Changes the start time on the same day; the end stays where it is.
   *
   * @param Timesheet $entry The record.
   * @param string $value The new start time.
   * @param DateTimeZone $timezone The user's time zone.
   * @return void
   */
  private function changeBegin( Timesheet $entry, string $value, DateTimeZone $timezone ) : void
  {
    $entry->setBegin( DateTime::createFromImmutable( $this->parseTime( $value, $this->getLocalBegin( $entry, $timezone ) ) ) );

    $end = $entry->getEnd();
    if ( $end !== null )
    {
      $this->setEnd( $entry, DateTimeImmutable::createFromMutable( $end ) );
    }
  }

  /**
   * Changes the end time. An end before the start is on the next day.
   *
   * @param Timesheet $entry The record.
   * @param string $value The new end time.
   * @param DateTimeZone $timezone The user's time zone.
   * @return void
   */
  private function changeEnd( Timesheet $entry, string $value, DateTimeZone $timezone ) : void
  {
    $begin = $this->getLocalBegin( $entry, $timezone );
    $end = $this->parseTime( $value, $begin );

    $this->setEnd( $entry, $end <= $begin ? $end->modify( '+1 day' ) : $end );
  }

  /**
   * Changes the duration by moving the end.
   *
   * @param Timesheet $entry The record.
   * @param string $value The new duration in minutes.
   * @return void
   */
  private function changeDuration( Timesheet $entry, string $value ) : void
  {
    $minutes = $this->parseMinutes( $value );
    $begin = $entry->getBegin();

    if ( $begin === null || $minutes === 0 )
    {
      throw new InvalidInputException( 'inline_edit.invalid_duration' );
    }

    $seconds = $minutes * self::MINUTE + $entry->getBreak();
    $this->setEnd( $entry, DateTimeImmutable::createFromMutable( $begin )->modify( '+' . $seconds . ' seconds' ) );
  }

  /**
   * Changes the break; the record keeps its start and end, so its duration changes.
   *
   * @param Timesheet $entry The record.
   * @param string $value The new break in minutes.
   * @return void
   */
  private function changeBreak( Timesheet $entry, string $value ) : void
  {
    $entry->setBreak( $this->parseMinutes( $value ) * self::MINUTE );
    $this->recalculateDuration( $entry );
  }

  /**
   * Changes the project, and the activity when one is given or the current one does not fit.
   *
   * @param Timesheet $entry The record.
   * @param User $user The logged-in user.
   * @param string $projectId The new project ID.
   * @param string $activityId The new activity ID, or empty to keep the current one.
   * @return void
   */
  private function changeProject( Timesheet $entry, User $user, string $projectId, string $activityId ) : void
  {
    $project = $this->options->findProject( $user, (int) $projectId );
    if ( $project === null )
    {
      throw new InvalidInputException( 'inline_edit.invalid_project' );
    }

    $activity = $activityId === '' ? $entry->getActivity() : $this->options->findActivity( $user, (int) $activityId, $project );
    if ( $activity === null || !$this->options->fitsProject( $activity, $project ) )
    {
      throw new InvalidInputException( 'inline_edit.choose_activity' );
    }

    $entry->setProject( $project );
    $entry->setActivity( $activity );
  }

  /**
   * Changes the activity to one that fits the record's project.
   *
   * @param Timesheet $entry The record.
   * @param User $user The logged-in user.
   * @param string $value The new activity ID.
   * @return void
   */
  private function changeActivity( Timesheet $entry, User $user, string $value ) : void
  {
    $project = $entry->getProject();
    $activity = $project === null ? null : $this->options->findActivity( $user, (int) $value, $project );

    if ( $activity === null )
    {
      throw new InvalidInputException( 'inline_edit.invalid_activity' );
    }

    $entry->setActivity( $activity );
  }

  /**
   * Replaces the tags with the comma-separated names.
   *
   * @param Timesheet $entry The record.
   * @param string $value Comma-separated tag names.
   * @return void
   */
  private function changeTags( Timesheet $entry, string $value ) : void
  {
    [ $tags, $missing ] = $this->options->findOrCreateTags( $this->parseTagNames( $value ) );
    if ( !empty( $missing ) )
    {
      throw new InvalidInputException( 'inline_edit.unknown_tags' );
    }

    foreach ( $entry->getTags()->toArray() as $tag )
    {
      $entry->removeTag( $tag );
    }

    foreach ( $tags as $tag )
    {
      $entry->addTag( $tag );
    }
  }

  /**
   * Marks the record billable or not. Kimai only stores the result, so both the mode and the
   * flag are set, the way its edit form does.
   *
   * @param Timesheet $entry The record.
   * @param bool $billable Whether the record is billable.
   * @return void
   */
  private function changeBillable( Timesheet $entry, bool $billable ) : void
  {
    $entry->setBillableMode( $billable ? Timesheet::BILLABLE_YES : Timesheet::BILLABLE_NO );
    $entry->setBillable( $billable );
  }

  /**
   * Sets the end and recalculates the duration, so Kimai validates the new length.
   *
   * @param Timesheet $entry The record.
   * @param DateTimeImmutable $end The new end.
   * @return void
   */
  private function setEnd( Timesheet $entry, DateTimeImmutable $end ) : void
  {
    $entry->setEnd( DateTime::createFromImmutable( $end ) );
    $this->recalculateDuration( $entry );
  }

  /**
   * Recalculates the duration of a finished record from its start, end and break.
   *
   * @param Timesheet $entry The record.
   * @return void
   */
  private function recalculateDuration( Timesheet $entry ) : void
  {
    if ( $entry->getEnd() !== null )
    {
      $entry->setDuration( $entry->getCalculatedDuration() );
    }
  }

  /**
   * Returns the start of the record in the user's time zone.
   *
   * @param Timesheet $entry The record.
   * @param DateTimeZone $timezone The user's time zone.
   * @return DateTimeImmutable
   */
  private function getLocalBegin( Timesheet $entry, DateTimeZone $timezone ) : DateTimeImmutable
  {
    $begin = $entry->getBegin();
    if ( $begin === null )
    {
      throw new InvalidInputException( 'inline_edit.invalid_time' );
    }

    return DateTimeImmutable::createFromMutable( $begin )->setTimezone( $timezone );
  }

  /**
   * Returns the given time on the day of another moment.
   *
   * @param string $value A 24-hour time such as 9:45.
   * @param DateTimeImmutable $day A moment on the day.
   * @return DateTimeImmutable
   */
  private function parseTime( string $value, DateTimeImmutable $day ) : DateTimeImmutable
  {
    if ( preg_match( self::TIME_PATTERN, $value, $matches ) !== 1 || (int) $matches[ 1 ] > 23 || (int) $matches[ 2 ] > 59 )
    {
      throw new InvalidInputException( 'inline_edit.invalid_time' );
    }

    return $day->setTime( (int) $matches[ 1 ], (int) $matches[ 2 ] );
  }

  /**
   * Reads a whole number of minutes.
   *
   * @param string $value The minutes.
   * @return int
   */
  private function parseMinutes( string $value ) : int
  {
    if ( !ctype_digit( $value ) )
    {
      throw new InvalidInputException( 'inline_edit.invalid_duration' );
    }

    return (int) $value;
  }

  /**
   * Splits comma-separated tag names into valid, unique names.
   *
   * @param string $value Comma-separated tag names.
   * @return array<int, string>
   */
  private function parseTagNames( string $value ) : array
  {
    $names = [];
    foreach ( explode( ',', $value ) as $name )
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
}
