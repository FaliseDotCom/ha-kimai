<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\Activity;
use App\Entity\Project;
use App\Validator\ValidationFailedException;
use KimaiPlugin\QuickTimerBarBundle\Exception\InvalidInputException;
use KimaiPlugin\QuickTimerBarBundle\QuickTimerBarBundle;
use KimaiPlugin\QuickTimerBarBundle\Service\QuickCreator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Creates a project (with its customer) or an activity from the "+" buttons in the quick start
 * bar, and returns it as JSON for the pickers.
 */
#[Route( path: '/timer-bar' )]
#[IsGranted( 'create_own_timesheet' )]
final class QuickCreateController extends AbstractController
{
  public const ROUTE_PROJECT = 'timer_bar_create_project';
  public const ROUTE_ACTIVITY = 'timer_bar_create_activity';

  /**
   * @param QuickCreator $creator Creates projects, customers and activities.
   * @param TranslatorInterface $translator Translates error messages for the script.
   */
  public function __construct(
    private readonly QuickCreator $creator,
    private readonly TranslatorInterface $translator
  )
  {
  }

  /**
   * Creates a project, and its customer when that is new.
   *
   * @param Request $request The posted _token, name and customer.
   * @return JsonResponse
   */
  #[Route( path: '/project', name: self::ROUTE_PROJECT, methods: [ 'POST' ] )]
  public function project( Request $request ) : JsonResponse
  {
    return $this->respond( $request, fn() : array => $this->describeProject( $this->creator->createProject(
      $this->getUser(),
      (string) $request->request->get( 'name' ),
      (string) $request->request->get( 'customer' )
    ) ) );
  }

  /**
   * Creates an activity, for the selected project when that only allows its own activities.
   *
   * @param Request $request The posted _token, name and project.
   * @return JsonResponse
   */
  #[Route( path: '/activity', name: self::ROUTE_ACTIVITY, methods: [ 'POST' ] )]
  public function activity( Request $request ) : JsonResponse
  {
    return $this->respond( $request, fn() : array => $this->describeActivity( $this->creator->createActivity(
      $this->getUser(),
      (string) $request->request->get( 'name' ),
      $this->creator->findProject( $this->getUser(), $request->request->getInt( 'project' ) )
    ) ) );
  }

  /**
   * Checks the token, runs the creation and turns refusals into an error message.
   *
   * @param Request $request The posted form.
   * @param callable(): array<string, mixed> $create Creates the item and describes it.
   * @return JsonResponse
   */
  private function respond( Request $request, callable $create ) : JsonResponse
  {
    if ( !$this->isCsrfTokenValid( TimerBarController::CSRF_TOKEN_ID, (string) $request->request->get( '_token' ) ) )
    {
      return $this->respondWithError( $this->translate( 'quick_create.failed' ) );
    }

    try
    {
      return new JsonResponse( $create() );
    }
    catch ( InvalidInputException $exception )
    {
      return $this->respondWithError( $this->translate( $exception->getMessage() ) );
    }
    catch ( ValidationFailedException $exception )
    {
      $messages = [];
      foreach ( $exception->getViolations() as $violation )
      {
        $messages[] = (string) $violation->getMessage();
      }

      return $this->respondWithError( implode( ' ', $messages ) );
    }
  }

  /**
   * Describes a project for the project picker.
   *
   * @param Project $project The project.
   * @return array{id: int, name: string, customer: string, globalActivities: bool, billable: bool}
   */
  private function describeProject( Project $project ) : array
  {
    $customer = $project->getCustomer();

    return [
      'id' => (int) $project->getId(),
      'name' => (string) $project->getName(),
      'customer' => (string) $customer?->getName(),
      'globalActivities' => $project->isGlobalActivities(),
      'billable' => $project->isBillable() && ( $customer === null || $customer->isBillable() ),
    ];
  }

  /**
   * Describes an activity for the activity picker; project ID 0 marks a global activity.
   *
   * @param Activity $activity The activity.
   * @return array{id: int, name: string, projectId: int, billable: bool}
   */
  private function describeActivity( Activity $activity ) : array
  {
    return [
      'id' => (int) $activity->getId(),
      'name' => (string) $activity->getName(),
      'projectId' => (int) $activity->getProject()?->getId(),
      'billable' => $activity->isBillable(),
    ];
  }

  /**
   * Returns an error message for the script.
   *
   * @param string $message The translated message.
   * @return JsonResponse
   */
  private function respondWithError( string $message ) : JsonResponse
  {
    return new JsonResponse( [ 'message' => $message ], Response::HTTP_UNPROCESSABLE_ENTITY );
  }

  /**
   * Translates a message key of the quick start bar.
   *
   * @param string $key The translation key.
   * @return string
   */
  private function translate( string $key ) : string
  {
    return $this->translator->trans( $key, [], QuickTimerBarBundle::TRANSLATION_DOMAIN );
  }
}
