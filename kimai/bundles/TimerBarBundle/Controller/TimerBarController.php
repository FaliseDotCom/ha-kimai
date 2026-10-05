<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimerBarBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Activity;
use App\Entity\Project;
use App\Timesheet\TimesheetService;
use App\Validator\ValidationFailedException;
use KimaiPlugin\TimerBarBundle\Repository\TimerBarRepository;
use KimaiPlugin\TimerBarBundle\TimerBarBundle;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Starts and stops time records from the timer bar, and serves its script and stylesheet.
 */
#[Route( path: '/timer-bar' )]
#[IsGranted( 'create_own_timesheet' )]
final class TimerBarController extends AbstractController
{
  public const CSRF_TOKEN_ID = 'timer_bar';
  public const ROUTE_START = 'timer_bar_start';
  public const ROUTE_STOP = 'timer_bar_stop';
  public const ROUTE_ASSET = 'timer_bar_asset';

  /**
   * The page users return to when the request names no valid page.
   *
   * @var string
   */
  private const FALLBACK_ROUTE = 'timesheet';

  /**
   * How long browsers may cache the assets, in seconds.
   *
   * @var int
   */
  private const ASSET_MAX_AGE = 86400;

  /**
   * @param TimerBarRepository $repository Finds bookable projects, activities and running entries.
   * @param TimesheetService $timesheetService Creates and stops time records the way Kimai does.
   */
  public function __construct(
    private readonly TimerBarRepository $repository,
    private readonly TimesheetService $timesheetService
  )
  {
  }

  /**
   * Starts a new time record for the posted description, project and activity.
   *
   * @param Request $request The posted timer bar form.
   * @return Response
   */
  #[Route( path: '/start', name: self::ROUTE_START, methods: [ 'POST' ] )]
  public function start( Request $request ) : Response
  {
    if ( !$this->isCsrfTokenValid( self::CSRF_TOKEN_ID, (string) $request->request->get( '_token' ) ) )
    {
      $this->flashError( 'timesheet.start.error' );

      return $this->redirectBack( $request );
    }

    $user = $this->getUser();
    $project = $this->repository->findProjects( $user )[ $request->request->getInt( 'project' ) ] ?? null;
    $activity = $this->repository->findActivities( $user )[ $request->request->getInt( 'activity' ) ] ?? null;

    if ( $project === null || $activity === null || !$this->canCombine( $project, $activity ) )
    {
      $this->flashError( 'timer_bar.invalid_selection' );

      return $this->redirectBack( $request );
    }

    $timesheet = $this->timesheetService->createNewTimesheet( $user );
    $this->timesheetService->prepareNewTimesheet( $timesheet );
    $timesheet->setProject( $project );
    $timesheet->setActivity( $activity );
    $timesheet->setDescription( trim( (string) $request->request->get( 'description' ) ) );

    try
    {
      $this->timesheetService->saveTimesheet( $timesheet );
    }
    catch ( ValidationFailedException $exception )
    {
      $this->flashError( 'action.update.error', $this->describeViolations( $exception ) );
    }
    catch ( AccessDeniedException $exception )
    {
      $this->flashError( 'timesheet.start.error' );
    }

    return $this->redirectBack( $request );
  }

  /**
   * Stops one of the user's running time records.
   *
   * @param Request $request The posted stop form.
   * @return Response
   */
  #[Route( path: '/stop', name: self::ROUTE_STOP, methods: [ 'POST' ] )]
  public function stop( Request $request ) : Response
  {
    $entry = $this->repository->findRunningEntryById( $this->getUser(), $request->request->getInt( 'timesheet' ) );

    if ( $entry === null || !$this->isCsrfTokenValid( self::CSRF_TOKEN_ID, (string) $request->request->get( '_token' ) ) )
    {
      $this->flashError( 'timesheet.stop.error' );

      return $this->redirectBack( $request );
    }

    try
    {
      $this->timesheetService->stopTimesheet( $entry );
    }
    catch ( ValidationFailedException $exception )
    {
      $this->flashError( 'action.update.error', $this->describeViolations( $exception ) );
    }

    return $this->redirectBack( $request );
  }

  /**
   * Serves the timer bar's script or stylesheet.
   *
   * @param string $name The file name, one of the keys of TimerBarBundle::ASSETS.
   * @return Response
   */
  #[Route( path: '/assets/{name}', name: self::ROUTE_ASSET, methods: [ 'GET' ] )]
  public function asset( string $name ) : Response
  {
    if ( !isset( TimerBarBundle::ASSETS[ $name ] ) )
    {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse( TimerBarBundle::ASSET_DIRECTORY . '/' . $name );
    $response->headers->set( 'Content-Type', TimerBarBundle::ASSETS[ $name ] );
    $response->setPublic();
    $response->setMaxAge( self::ASSET_MAX_AGE );

    return $response;
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

  /**
   * Joins the messages of a failed validation into one line.
   *
   * @param ValidationFailedException $exception The failed validation.
   * @return string
   */
  private function describeViolations( ValidationFailedException $exception ) : string
  {
    $messages = [];
    foreach ( $exception->getViolations() as $violation )
    {
      $messages[] = (string) $violation->getMessage();
    }

    return implode( ' ', $messages );
  }

  /**
   * Redirects to the page the form was posted from, if it is a page on this site.
   *
   * @param Request $request The posted form.
   * @return RedirectResponse
   */
  private function redirectBack( Request $request ) : RedirectResponse
  {
    $target = (string) $request->request->get( '_target_path' );

    // Only same-site paths: "//host" and "/\host" would send the browser to another site.
    if ( preg_match( '#^/(?![/\\\\])#', $target ) === 1 )
    {
      return $this->redirect( $target );
    }

    return $this->redirectToRoute( self::FALLBACK_ROUTE );
  }
}
