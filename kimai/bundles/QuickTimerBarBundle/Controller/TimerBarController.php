<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Timesheet;
use App\Entity\User;
use App\Timesheet\DateTimeFactory;
use App\Timesheet\TimesheetService;
use App\Validator\ValidationFailedException;
use DateTimeImmutable;
use KimaiPlugin\QuickTimerBarBundle\Repository\TimerBarRepository;
use KimaiPlugin\QuickTimerBarBundle\Service\EntryInputReader;
use KimaiPlugin\QuickTimerBarBundle\Service\EntryWriter;
use KimaiPlugin\QuickTimerBarBundle\Service\TimeInput;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Starts, adds, changes, continues and stops time records from the quick start bar.
 */
#[Route( path: '/timer-bar' )]
#[IsGranted( 'create_own_timesheet' )]
final class TimerBarController extends AbstractController
{
  public const CSRF_TOKEN_ID = 'timer_bar';
  public const ROUTE_START = 'timer_bar_start';
  public const ROUTE_UPDATE = 'timer_bar_update';
  public const ROUTE_STOP = 'timer_bar_stop';
  public const ROUTE_CONTINUE = 'timer_bar_continue';

  /**
   * The page users return to when the request names no valid page.
   *
   * @var string
   */
  private const FALLBACK_ROUTE = 'timesheet';

  /**
   * @param TimerBarRepository $repository Finds the user's running and past records.
   * @param TimesheetService $timesheetService Stops time records the way Kimai does.
   * @param EntryInputReader $inputReader Reads and checks the posted fields.
   * @param EntryWriter $writer Creates and changes records.
   * @param TimeInput $timeInput Reads typed times.
   * @param TranslatorInterface $translator Translates error messages for the script.
   */
  public function __construct(
    private readonly TimerBarRepository $repository,
    private readonly TimesheetService $timesheetService,
    private readonly EntryInputReader $inputReader,
    private readonly EntryWriter $writer,
    private readonly TimeInput $timeInput,
    private readonly TranslatorInterface $translator
  )
  {
  }

  /**
   * Starts or adds a record, depending on the times entered: none starts a timer now, a start
   * time starts a timer from that time, and a start and end time add a finished record.
   *
   * @param Request $request The posted quick start bar form.
   * @return Response
   */
  #[Route( path: '/start', name: self::ROUTE_START, methods: [ 'POST' ] )]
  public function start( Request $request ) : Response
  {
    $user = $this->getUser();
    $input = $this->isTokenValid( $request ) ? $this->inputReader->read( $request, $user ) : null;

    if ( $input === null )
    {
      $this->flashError( 'timer_bar.invalid_selection' );

      return $this->redirectBack( $request );
    }

    $period = $this->getEnteredPeriod( $request, $user );
    if ( $period === null )
    {
      $this->flashError( 'timer_bar.invalid_time' );

      return $this->redirectBack( $request );
    }

    [ $begin, $end ] = $period;
    $this->flashFailure( fn() => $end === null
      ? $this->writer->start( $user, $input, $begin )
      : $this->writer->add( $user, $input, $begin ?? $end, $end ) );

    return $this->redirectBack( $request );
  }

  /**
   * Changes the running record. The script calls this whenever a field in the running bar
   * changes and gets JSON back; without the script, the form posts here and returns to the page.
   *
   * @param Request $request The posted running bar form.
   * @return Response
   */
  #[Route( path: '/update', name: self::ROUTE_UPDATE, methods: [ 'POST' ] )]
  public function update( Request $request ) : Response
  {
    $user = $this->getUser();
    $entry = $this->repository->findRunningEntryById( $user, $request->request->getInt( 'timesheet' ) );
    $input = $entry !== null && $this->isTokenValid( $request ) ? $this->inputReader->read( $request, $user ) : null;

    if ( $entry === null || $input === null )
    {
      return $this->respondToUpdate( $request, null, 'timer_bar.invalid_selection' );
    }

    $beginTime = trim( (string) $request->request->get( 'begin_time' ) );
    $begin = $beginTime === '' ? null : $this->getRunningBegin( $request, $entry, $user );

    if ( $beginTime !== '' && $begin === null )
    {
      return $this->respondToUpdate( $request, null, 'timer_bar.invalid_time' );
    }

    try
    {
      return $this->respondToUpdate( $request, $this->writer->update( $entry, $input, $begin ), '' );
    }
    catch ( ValidationFailedException $exception )
    {
      return $this->respondToUpdate( $request, null, $this->describeViolations( $exception ) );
    }
  }

  /**
   * Starts a new record now that copies one of the user's past records.
   *
   * @param Request $request The posted continue form.
   * @return Response
   */
  #[Route( path: '/continue', name: self::ROUTE_CONTINUE, methods: [ 'POST' ] )]
  public function continueEntry( Request $request ) : Response
  {
    $user = $this->getUser();
    $source = $this->repository->findOwnEntry( $user, $request->request->getInt( 'timesheet' ) );

    if ( $source === null || !$this->isTokenValid( $request ) )
    {
      $this->flashError( 'timesheet.start.error' );

      return $this->redirectBack( $request );
    }

    $this->flashFailure( fn() => $this->writer->continueEntry( $user, $source ) );

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

    if ( $entry === null || !$this->isTokenValid( $request ) )
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
   * Returns whether the posted form carries a valid CSRF token.
   *
   * @param Request $request The posted form.
   * @return bool
   */
  private function isTokenValid( Request $request ) : bool
  {
    return $this->isCsrfTokenValid( self::CSRF_TOKEN_ID, (string) $request->request->get( '_token' ) );
  }

  /**
   * Returns the entered start and end: each is null when left empty. An end before the start
   * is on the next day. Returns null when a time is invalid, or when only an end was entered.
   *
   * @param Request $request The posted form with date, begin_time and end_time.
   * @param User $user The logged-in user.
   * @return array{0: DateTimeImmutable|null, 1: DateTimeImmutable|null}|null
   */
  private function getEnteredPeriod( Request $request, User $user ) : ?array
  {
    $beginTime = trim( (string) $request->request->get( 'begin_time' ) );
    $endTime = trim( (string) $request->request->get( 'end_time' ) );

    if ( $beginTime === '' && $endTime === '' )
    {
      return [ null, null ];
    }

    $day = $this->timeInput->parseDay( (string) $request->request->get( 'date' ), DateTimeFactory::createByUser( $user )->getTimezone() );
    $begin = $day === null || $beginTime === '' ? null : $this->timeInput->parse( $beginTime, $day );
    $end = $day === null || $endTime === '' ? null : $this->timeInput->parse( $endTime, $day );

    if ( $begin === null || ( $endTime !== '' && $end === null ) )
    {
      return null;
    }

    return [ $begin, $end !== null && $end <= $begin ? $end->modify( '+1 day' ) : $end ];
  }

  /**
   * Returns the new start of the running record: the typed time on the entered date (or the
   * day it started), or the day before when that would lie in the future.
   *
   * @param Request $request The posted form with date and begin_time.
   * @param Timesheet $entry The running record.
   * @param User $user The logged-in user.
   * @return DateTimeImmutable|null
   */
  private function getRunningBegin( Request $request, Timesheet $entry, User $user ) : ?DateTimeImmutable
  {
    $timezone = DateTimeFactory::createByUser( $user )->getTimezone();
    $current = $entry->getBegin();
    $date = (string) $request->request->get( 'date' );
    $day = $date !== '' ? $this->timeInput->parseDay( $date, $timezone ) : null;
    $day ??= $current === null ? new DateTimeImmutable( 'today', $timezone ) : DateTimeImmutable::createFromMutable( $current )->setTimezone( $timezone )->setTime( 0, 0 );
    $begin = $this->timeInput->parse( (string) $request->request->get( 'begin_time' ), $day );

    if ( $begin !== null && $begin > new DateTimeImmutable( 'now', $timezone ) )
    {
      $begin = $begin->modify( '-1 day' );
    }

    return $begin;
  }

  /**
   * Answers a change to the running record: JSON for the script, or a redirect for a plain post.
   *
   * @param Request $request The posted form.
   * @param Timesheet|null $entry The changed record, or null when the change failed.
   * @param string $error A translation key or message explaining a failure.
   * @return Response
   */
  private function respondToUpdate( Request $request, ?Timesheet $entry, string $error ) : Response
  {
    if ( $request->getPreferredFormat() !== 'json' )
    {
      if ( $entry === null )
      {
        $this->flashError( 'action.update.error', $error );
      }

      return $this->redirectBack( $request );
    }

    if ( $entry === null )
    {
      return new JsonResponse( [ 'message' => $this->translator->trans( $error, [], 'flashmessages' ) ], Response::HTTP_UNPROCESSABLE_ENTITY );
    }

    return new JsonResponse( [ 'begin' => $entry->getBegin()?->format( DATE_ATOM ) ?? '' ] );
  }

  /**
   * Runs a start action and turns Kimai's refusals into error messages.
   *
   * @param callable(): mixed $action Starts or adds the record.
   * @return void
   */
  private function flashFailure( callable $action ) : void
  {
    try
    {
      $action();
    }
    catch ( ValidationFailedException $exception )
    {
      $this->flashError( 'action.update.error', $this->describeViolations( $exception ) );
    }
    catch ( AccessDeniedException $exception )
    {
      $this->flashError( 'timesheet.start.error' );
    }
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
