<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Timesheet\DateTimeFactory;
use DateTimeImmutable;
use KimaiPlugin\QuickTimerBarBundle\Model\EntryInput;
use KimaiPlugin\QuickTimerBarBundle\Repository\TimerBarRepository;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Collects everything the quick start bar template shows.
 *
 * @phpstan-import-type Suggestion from TimerBarRepository
 * @phpstan-type SuggestionOption array{description: string, projectId: int, activityId: int, projectName: string}
 * @phpstan-type ProjectOption array{id: int, name: string, globalActivities: bool, billable: bool}
 * @phpstan-type CustomerGroup array{name: string, projects: array<int, ProjectOption>}
 * @phpstan-type ActivityOption array{id: int, name: string, projectId: int, billable: bool}
 * @phpstan-type TagOption array{id: int, name: string}
 * @phpstan-type EntryView array{id: int, description: string, projectId: int, activityId: int, begin: string, beginDate: string, beginTime: string, tagIds: array<int, int>, billableMode: string}
 * @phpstan-type QuickCreateView array{project: bool, customer: bool, activity: bool, customers: array<int, string>}
 * @phpstan-type TimerBarView array{
 *   running: EntryView|array{},
 *   customers: array<int, CustomerGroup>,
 *   activities: array<int, ActivityOption>,
 *   suggestions: array<int, SuggestionOption>,
 *   tags: array<int, TagOption>,
 *   canCreateTags: bool,
 *   canEditBillable: bool,
 *   quickCreate: QuickCreateView,
 *   defaultProjectId: int,
 *   defaultActivityId: int,
 *   timeFormat: string,
 *   today: string
 * }
 */
final class TimerBarViewFactory
{
  /**
   * @param TimerBarRepository $repository Reads projects, activities, tags, suggestions and running entries.
   * @param AuthorizationCheckerInterface $security Checks the tag and billable permissions.
   * @param TimeInput $timeInput Formats times in the user's clock.
   * @param QuickCreator $creator Tells what the user may create, and offers the customer names.
   */
  public function __construct(
    private readonly TimerBarRepository $repository,
    private readonly AuthorizationCheckerInterface $security,
    private readonly TimeInput $timeInput,
    private readonly QuickCreator $creator
  )
  {
  }

  /**
   * Builds the view data for one user.
   *
   * @param User $user The logged-in user.
   * @param string $locale The locale of the page, which decides the time format.
   * @return TimerBarView
   */
  public function create( User $user, string $locale ) : array
  {
    $timezone = DateTimeFactory::createByUser( $user )->getTimezone();
    $projects = $this->repository->findProjects( $user );
    $activities = $this->repository->findActivities( $user );
    $suggestions = $this->filterSuggestions( $this->repository->findSuggestions( $user ), $projects, $activities );
    $running = $this->repository->findRunningEntry( $user );

    return [
      'running' => $running === null ? [] : $this->describeEntry( $running, $user, $locale ),
      'customers' => $this->groupProjectsByCustomer( $projects ),
      'activities' => $this->describeActivities( $activities ),
      'suggestions' => $suggestions,
      'tags' => $this->describeTags( $this->repository->findTags() ),
      'canCreateTags' => $this->security->isGranted( 'create_tag' ),
      'canEditBillable' => $this->security->isGranted( 'edit_billable_own_timesheet' ),
      'quickCreate' => $this->describeQuickCreate( $user ),
      'defaultProjectId' => $suggestions[ 0 ][ 'projectId' ] ?? 0,
      'defaultActivityId' => $suggestions[ 0 ][ 'activityId' ] ?? 0,
      'timeFormat' => $this->timeInput->getFormats( $locale )[ 'js' ],
      'today' => ( new DateTimeImmutable( 'today', $timezone ) )->format( 'Y-m-d' ),
    ];
  }

  /**
   * Describes the "+" buttons: which ones to show, and the customers to suggest for a new
   * project. The customers are only loaded when the user may create projects.
   *
   * @param User $user The logged-in user.
   * @return QuickCreateView
   */
  private function describeQuickCreate( User $user ) : array
  {
    $permissions = $this->creator->getPermissions();

    return $permissions + [ 'customers' => $permissions[ 'project' ] ? $this->creator->getCustomerNames( $user ) : [] ];
  }

  /**
   * Keeps the most recent suggestion per description, for projects and activities the user
   * may still book on, and adds the project name.
   *
   * @param array<int, Suggestion> $suggestions Recent entries, most recent first.
   * @param array<int, Project> $projects Bookable projects by ID.
   * @param array<int, Activity> $activities Bookable activities by ID.
   * @return array<int, SuggestionOption>
   */
  private function filterSuggestions( array $suggestions, array $projects, array $activities ) : array
  {
    $options = [];
    foreach ( $suggestions as $suggestion )
    {
      $project = $projects[ $suggestion[ 'projectId' ] ] ?? null;

      if ( $project === null || !isset( $activities[ $suggestion[ 'activityId' ] ] ) || isset( $options[ $suggestion[ 'description' ] ] ) )
      {
        continue;
      }

      $options[ $suggestion[ 'description' ] ] = $suggestion + [ 'projectName' => (string) $project->getName() ];
    }

    return array_values( $options );
  }

  /**
   * Describes a record, so the bar can show it in its editable fields: the running record, or
   * a past one copied into the bar in manual mode.
   *
   * @param Timesheet $entry The record.
   * @param User $user The logged-in user, whose time zone applies.
   * @param string $locale The locale of the page, which decides the time format.
   * @return EntryView
   */
  public function describeEntry( Timesheet $entry, User $user, string $locale ) : array
  {
    $timezone = DateTimeFactory::createByUser( $user )->getTimezone();
    $begin = $entry->getBegin() === null ? null : DateTimeImmutable::createFromMutable( $entry->getBegin() )->setTimezone( $timezone );
    $tagIds = [];
    foreach ( $entry->getTags() as $tag )
    {
      if ( $tag->getId() !== null )
      {
        $tagIds[] = $tag->getId();
      }
    }

    return [
      'id' => (int) $entry->getId(),
      'description' => (string) $entry->getDescription(),
      'projectId' => (int) $entry->getProject()?->getId(),
      'activityId' => (int) $entry->getActivity()?->getId(),
      'begin' => $begin?->format( DATE_ATOM ) ?? '',
      'beginDate' => $begin?->format( 'Y-m-d' ) ?? '',
      'beginTime' => $begin === null ? '' : $this->timeInput->format( $begin, $locale ),
      'tagIds' => $tagIds,
      'billableMode' => match ( $entry->getBillableMode() )
      {
        Timesheet::BILLABLE_YES => EntryInput::BILLABLE_YES,
        Timesheet::BILLABLE_NO => EntryInput::BILLABLE_NO,
        default => EntryInput::BILLABLE_AUTOMATIC,
      },
    ];
  }

  /**
   * Groups the projects under their customer, keeping the repository's order.
   *
   * @param array<int, Project> $projects Bookable projects by ID.
   * @return array<int, CustomerGroup>
   */
  private function groupProjectsByCustomer( array $projects ) : array
  {
    $customers = [];
    foreach ( $projects as $id => $project )
    {
      $customer = $project->getCustomer();
      $customerId = (int) $customer?->getId();

      $customers[ $customerId ] ??= [ 'name' => $customer?->getName() ?? '', 'projects' => [] ];
      $customers[ $customerId ][ 'projects' ][] = [
        'id' => $id,
        'name' => (string) $project->getName(),
        'globalActivities' => $project->isGlobalActivities(),
        'billable' => $project->isBillable() && ( $customer === null || $customer->isBillable() ),
      ];
    }

    return array_values( $customers );
  }

  /**
   * Describes the activities for the activity picker; project ID 0 marks a global activity.
   *
   * @param array<int, Activity> $activities Bookable activities by ID.
   * @return array<int, ActivityOption>
   */
  private function describeActivities( array $activities ) : array
  {
    $options = [];
    foreach ( $activities as $id => $activity )
    {
      $options[] = [
        'id' => $id,
        'name' => (string) $activity->getName(),
        'projectId' => (int) $activity->getProject()?->getId(),
        'billable' => $activity->isBillable(),
      ];
    }

    return $options;
  }

  /**
   * Describes the tags for the tag picker.
   *
   * @param array<int, Tag> $tags Visible tags by ID.
   * @return array<int, TagOption>
   */
  private function describeTags( array $tags ) : array
  {
    $options = [];
    foreach ( $tags as $id => $tag )
    {
      $options[] = [ 'id' => $id, 'name' => (string) $tag->getName() ];
    }

    return $options;
  }
}
