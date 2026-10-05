<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimerBarBundle\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Timesheet;
use App\Entity\User;
use KimaiPlugin\TimerBarBundle\Repository\TimerBarRepository;

/**
 * Collects everything the timer bar template shows.
 *
 * @phpstan-import-type Suggestion from TimerBarRepository
 * @phpstan-type SuggestionOption array{description: string, projectId: int, activityId: int, projectName: string}
 * @phpstan-type ProjectOption array{id: int, name: string, globalActivities: bool}
 * @phpstan-type CustomerGroup array{name: string, projects: array<int, ProjectOption>}
 * @phpstan-type ActivityOption array{id: int, name: string, projectId: int}
 * @phpstan-type RunningEntry array{id: int, description: string, project: string, customer: string, activity: string, begin: string}
 * @phpstan-type TimerBarView array{
 *   running: RunningEntry|array{},
 *   customers: array<int, CustomerGroup>,
 *   activities: array<int, ActivityOption>,
 *   suggestions: array<int, SuggestionOption>,
 *   defaultProjectId: int,
 *   defaultActivityId: int
 * }
 */
final class TimerBarViewFactory
{
  /**
   * @param TimerBarRepository $repository Reads projects, activities, suggestions and running entries.
   */
  public function __construct( private readonly TimerBarRepository $repository )
  {
  }

  /**
   * Builds the view data for one user.
   *
   * @param User $user The logged-in user.
   * @return TimerBarView
   */
  public function create( User $user ) : array
  {
    $projects = $this->repository->findProjects( $user );
    $activities = $this->repository->findActivities( $user );
    $suggestions = $this->filterSuggestions( $this->repository->findSuggestions( $user ), $projects, $activities );
    $running = $this->repository->findRunningEntry( $user );

    return [
      'running' => $running === null ? [] : $this->describeRunningEntry( $running ),
      'customers' => $this->groupProjectsByCustomer( $projects ),
      'activities' => $this->describeActivities( $activities ),
      'suggestions' => $suggestions,
      'defaultProjectId' => $suggestions[ 0 ][ 'projectId' ] ?? 0,
      'defaultActivityId' => $suggestions[ 0 ][ 'activityId' ] ?? 0,
    ];
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
   * Describes the running entry for display.
   *
   * @param Timesheet $entry The running entry.
   * @return RunningEntry
   */
  private function describeRunningEntry( Timesheet $entry ) : array
  {
    $project = $entry->getProject();

    return [
      'id' => (int) $entry->getId(),
      'description' => (string) $entry->getDescription(),
      'project' => $project?->getName() ?? '',
      'customer' => $project?->getCustomer()?->getName() ?? '',
      'activity' => $entry->getActivity()?->getName() ?? '',
      'begin' => $entry->getBegin()?->format( DATE_ATOM ) ?? '',
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
      ];
    }

    return $options;
  }
}
