<?php

declare( strict_types=1 );

namespace KimaiPlugin\UiImprovementsBundle\Controller;

use App\Controller\AbstractController;
use App\Entity\User;
use App\Validator\ValidationFailedException;
use KimaiPlugin\UiImprovementsBundle\EventSubscriber\PreferenceSubscriber;
use KimaiPlugin\UiImprovementsBundle\Exception\InvalidInputException;
use KimaiPlugin\UiImprovementsBundle\Service\BookingOptions;
use KimaiPlugin\UiImprovementsBundle\Service\EntryDescriber;
use KimaiPlugin\UiImprovementsBundle\Service\EntryEditor;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Reads and saves the user's own records for editing them directly in the list.
 */
#[Route( path: '/ui-improvements/inline-edit' )]
#[IsGranted( 'IS_AUTHENTICATED_REMEMBERED' )]
final class InlineEditController extends AbstractController
{
  public const CSRF_TOKEN_ID = 'ui_improvements_inline_edit';
  public const ROUTE_ENTRIES = 'ui_improvements_inline_edit_entries';
  public const ROUTE_OPTIONS = 'ui_improvements_inline_edit_options';
  public const ROUTE_SAVE = 'ui_improvements_inline_edit_save';

  /**
   * Translation domain of the error messages.
   *
   * @var string
   */
  public const TRANSLATION_DOMAIN = 'ui_improvements';

  /**
   * @param EntryDescriber $describer Finds and describes the user's editable records.
   * @param EntryEditor $editor Changes and saves a record.
   * @param BookingOptions $options The projects, activities and tags to pick from.
   * @param TranslatorInterface $translator Translates error messages for the script.
   */
  public function __construct(
    private readonly EntryDescriber $describer,
    private readonly EntryEditor $editor,
    private readonly BookingOptions $options,
    private readonly TranslatorInterface $translator
  )
  {
  }

  /**
   * Describes the records in the list that the user may edit.
   *
   * @param Request $request The request, with comma-separated record IDs in "ids".
   * @return JsonResponse
   */
  #[Route( path: '/entries', name: self::ROUTE_ENTRIES, methods: [ 'GET' ] )]
  public function entries( Request $request ) : JsonResponse
  {
    $user = $this->getEnabledUser();
    $ids = array_map( 'intval', explode( ',', (string) $request->query->get( 'ids' ) ) );

    return new JsonResponse( [ 'entries' => (object) $this->describer->describe( $user, $ids ) ] );
  }

  /**
   * Lists the projects, activities and tags the user can pick.
   *
   * @return JsonResponse
   */
  #[Route( path: '/options', name: self::ROUTE_OPTIONS, methods: [ 'GET' ] )]
  public function options() : JsonResponse
  {
    return new JsonResponse( $this->options->describe( $this->getEnabledUser() ) );
  }

  /**
   * Changes one field of a record.
   *
   * @param Request $request The posted change: _token, timesheet, field, value and, with a project, activity.
   * @return JsonResponse
   */
  #[Route( path: '/save', name: self::ROUTE_SAVE, methods: [ 'POST' ] )]
  public function save( Request $request ) : JsonResponse
  {
    $user = $this->getEnabledUser();
    $entry = $this->describer->findEditable( $user, $request->request->getInt( 'timesheet' ) );

    if ( $entry === null || !$this->isCsrfTokenValid( self::CSRF_TOKEN_ID, (string) $request->request->get( '_token' ) ) )
    {
      return $this->respondWithError( $this->translate( 'inline_edit.not_editable' ) );
    }

    try
    {
      $this->editor->update( $entry, $user, (string) $request->request->get( 'field' ), [
        'value' => (string) $request->request->get( 'value' ),
        EntryEditor::FIELD_ACTIVITY => (string) $request->request->get( EntryEditor::FIELD_ACTIVITY ),
      ] );
    }
    catch ( InvalidInputException $exception )
    {
      return $this->respondWithError( $this->translate( $exception->getMessage() ) );
    }
    catch ( ValidationFailedException $exception )
    {
      return $this->respondWithError( $this->describeViolations( $exception ) );
    }

    return new JsonResponse( [ 'saved' => true ] );
  }

  /**
   * Returns the logged-in user, when they have editing in the list turned on.
   *
   * @return User
   * @throws AccessDeniedException When the user turned editing in the list off.
   */
  private function getEnabledUser() : User
  {
    $user = $this->getUser();
    if ( !PreferenceSubscriber::isEnabled( $user ) )
    {
      throw new AccessDeniedException();
    }

    return $user;
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
   * Translates an error message key.
   *
   * @param string $key The translation key.
   * @return string
   */
  private function translate( string $key ) : string
  {
    return $this->translator->trans( $key, [], self::TRANSLATION_DOMAIN );
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
}
