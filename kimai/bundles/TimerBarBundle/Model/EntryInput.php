<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimerBarBundle\Model;

use App\Entity\Activity;
use App\Entity\Project;
use App\Entity\Tag;

/**
 * What the user entered in the quick start bar, after checking it against what they may book.
 */
final class EntryInput
{
  public const BILLABLE_AUTOMATIC = 'auto';
  public const BILLABLE_YES = 'yes';
  public const BILLABLE_NO = 'no';

  /**
   * @param Project $project The project to book on.
   * @param Activity $activity The activity to book on.
   * @param string $description What the user is working on.
   * @param array<int, Tag> $tags Existing tags.
   * @param array<int, string> $newTagNames Names of tags to create, if the user may.
   * @param string $billable One of the BILLABLE_ constants.
   */
  public function __construct(
    private readonly Project $project,
    private readonly Activity $activity,
    private readonly string $description,
    private readonly array $tags,
    private readonly array $newTagNames,
    private readonly string $billable
  )
  {
  }

  /**
   * Returns the project to book on.
   *
   * @return Project
   */
  public function getProject() : Project
  {
    return $this->project;
  }

  /**
   * Returns the activity to book on.
   *
   * @return Activity
   */
  public function getActivity() : Activity
  {
    return $this->activity;
  }

  /**
   * Returns what the user is working on.
   *
   * @return string
   */
  public function getDescription() : string
  {
    return $this->description;
  }

  /**
   * Returns the chosen existing tags.
   *
   * @return array<int, Tag>
   */
  public function getTags() : array
  {
    return $this->tags;
  }

  /**
   * Returns the names of tags to create.
   *
   * @return array<int, string>
   */
  public function getNewTagNames() : array
  {
    return $this->newTagNames;
  }

  /**
   * Returns the billable choice, one of the BILLABLE_ constants.
   *
   * @return string
   */
  public function getBillable() : string
  {
    return $this->billable;
  }
}
