<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\Service;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Tag;
use App\Entity\User;
use KimaiPlugin\QuickTimerBarBundle\Model\EntryInput;
use KimaiPlugin\QuickTimerBarBundle\Repository\TimerBarRepository;
use Symfony\Component\HttpFoundation\Request;

/**
 * Reads the quick start bar's posted fields and checks them against what the user may book:
 * only their bookable projects and activities, visible tags, and known billable choices.
 */
final class EntryInputReader
{
  /**
   * @param TimerBarRepository $repository Finds bookable projects, activities and tags.
   * @param EntryWriter $writer Splits the typed tag names.
   */
  public function __construct(
    private readonly TimerBarRepository $repository,
    private readonly EntryWriter $writer
  )
  {
  }

  /**
   * Returns the posted entry, or null when the project and activity cannot be booked together.
   *
   * @param Request $request The posted quick start bar form.
   * @param User $user The logged-in user.
   * @return EntryInput|null
   */
  public function read( Request $request, User $user ) : ?EntryInput
  {
    $project = $this->repository->findProjects( $user )[ $request->request->getInt( 'project' ) ] ?? null;
    $activity = $this->repository->findActivities( $user )[ $request->request->getInt( 'activity' ) ] ?? null;

    if ( $project === null || $activity === null || !$this->canCombine( $project, $activity ) )
    {
      return null;
    }

    return new EntryInput(
      $project,
      $activity,
      trim( (string) $request->request->get( 'description' ) ),
      $this->getTags( $request ),
      $this->writer->parseTagNames( (string) $request->request->get( 'new_tags' ) ),
      $this->getBillable( $request )
    );
  }

  /**
   * Returns the posted existing tags that are visible; unknown IDs are ignored.
   *
   * @param Request $request The posted form.
   * @return array<int, Tag>
   */
  private function getTags( Request $request ) : array
  {
    $tags = $this->repository->findTags();

    $selected = [];
    foreach ( $request->request->all( 'tags' ) as $id )
    {
      if ( is_scalar( $id ) && isset( $tags[ (int) $id ] ) )
      {
        $selected[] = $tags[ (int) $id ];
      }
    }

    return $selected;
  }

  /**
   * Returns the posted billable choice, falling back to Kimai's automatic setting.
   *
   * @param Request $request The posted form.
   * @return string One of the EntryInput::BILLABLE_ constants.
   */
  private function getBillable( Request $request ) : string
  {
    $billable = (string) $request->request->get( 'billable' );
    $choices = [ EntryInput::BILLABLE_YES, EntryInput::BILLABLE_NO ];

    return in_array( $billable, $choices, true ) ? $billable : EntryInput::BILLABLE_AUTOMATIC;
  }

  /**
   * Returns whether an activity may be booked on a project: its own activities, or global
   * activities when the project allows them.
   *
   * @param Project $project The selected project.
   * @param Activity $activity The selected activity.
   * @return bool
   */
  private function canCombine( Project $project, Activity $activity ) : bool
  {
    $activityProject = $activity->getProject();

    if ( $activityProject === null )
    {
      return $project->isGlobalActivities();
    }

    return $activityProject->getId() === $project->getId();
  }
}
