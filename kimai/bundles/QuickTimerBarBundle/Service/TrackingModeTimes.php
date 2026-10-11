<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\Service;

use App\Timesheet\TimesheetService;

/**
 * Tells which times Kimai's tracking mode lets users enter. In the "punch in/out" and "fixed
 * start time" modes, Kimai's own forms offer no start or end time: records start and stop now.
 */
final class TrackingModeTimes
{
  /**
   * @param TimesheetService $timesheetService Knows the active tracking mode.
   */
  public function __construct( private readonly TimesheetService $timesheetService )
  {
  }

  /**
   * Tells whether users may enter a date and start time.
   *
   * @return bool
   */
  public function canEnterBegin() : bool
  {
    return $this->timesheetService->getActiveTrackingMode()->canEditBegin();
  }

  /**
   * Tells whether users may enter an end time.
   *
   * @return bool
   */
  public function canEnterEnd() : bool
  {
    return $this->timesheetService->getActiveTrackingMode()->canEditEnd();
  }

  /**
   * Tells whether users may enter both, which the bar's manual mode and editable start time need.
   *
   * @return bool
   */
  public function canEnterTimes() : bool
  {
    return $this->canEnterBegin() && $this->canEnterEnd();
  }
}
