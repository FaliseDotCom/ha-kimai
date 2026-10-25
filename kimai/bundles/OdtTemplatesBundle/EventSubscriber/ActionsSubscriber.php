<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\EventSubscriber;

use App\Event\PageActionsEvent;
use KimaiPlugin\OdtTemplatesBundle\Controller\TemplateController;
use KimaiPlugin\OdtTemplatesBundle\Form\TemplateUploadForm;
use KimaiPlugin\OdtTemplatesBundle\Odt\OdtTemplate;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Adds the buttons for the ODT templates page: a link from Kimai's invoice templates page,
 * a back link on the page itself, and download and delete for each template in its list.
 */
final class ActionsSubscriber implements EventSubscriberInterface
{
  /**
   * The name of the actions on Kimai's invoice templates page.
   */
  private const ACTIONS_INVOICE_TEMPLATES = 'invoice_templates';

  /**
   * The permission of Kimai's own invoice document upload, which the page shares.
   */
  private const PERMISSION = 'upload_invoice_template';

  /**
   * Creates the subscriber.
   *
   * @param AuthorizationCheckerInterface $auth Checks the user's permissions.
   * @param UrlGeneratorInterface $urlGenerator Builds the links.
   * @param CsrfTokenManagerInterface $csrfTokenManager Signs the delete links.
   */
  public function __construct(
    private readonly AuthorizationCheckerInterface $auth,
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly CsrfTokenManagerInterface $csrfTokenManager,
  )
  {
  }

  /**
   * Returns the action events this subscriber adds buttons to.
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents() : array
  {
    return [
      'actions.' . self::ACTIONS_INVOICE_TEMPLATES => 'onInvoiceTemplates',
      'actions.' . TemplateController::ACTIONS_PAGE => 'onTemplatesPage',
      'actions.' . TemplateController::ACTIONS_TEMPLATE => 'onTemplate',
    ];
  }

  /**
   * Adds a button to the ODT templates page on Kimai's invoice templates page, next to
   * Kimai's own upload button.
   *
   * @param PageActionsEvent $event The actions of the invoice templates page.
   * @return void
   */
  public function onInvoiceTemplates( PageActionsEvent $event ) : void
  {
    if ( !$this->auth->isGranted( self::PERMISSION ) )
    {
      return;
    }

    $event->addAction( TemplateController::ACTIONS_PAGE, [
      'url' => $this->path( $event, TemplateController::ROUTE ),
      'icon' => 'upload',
      'title' => 'odt.title',
      'translation_domain' => TemplateUploadForm::TRANSLATION_DOMAIN,
    ] );
  }

  /**
   * Adds a link back to Kimai's invoice templates on the ODT templates page.
   *
   * @param PageActionsEvent $event The actions of the ODT templates page.
   * @return void
   */
  public function onTemplatesPage( PageActionsEvent $event ) : void
  {
    $event->addAction( 'back', [ 'url' => $this->path( $event, 'admin_invoice_template' ), 'title' => 'back' ] );
  }

  /**
   * Adds download and delete to a template in the list. An invoice document that a
   * template uses cannot be deleted, as in Kimai's own list.
   *
   * @param PageActionsEvent $event The actions of one template.
   * @return void
   */
  public function onTemplate( PageActionsEvent $event ) : void
  {
    $template = $event->getPayload()[ 'template' ] ?? null;
    if ( !$template instanceof OdtTemplate )
    {
      return;
    }

    $parameters = [
      'kind' => $template->getKind()->value,
      'name' => $template->getFileName(),
    ];

    $event->addAction( 'download', [ 'url' => $this->path( $event, TemplateController::ROUTE_DOWNLOAD, $parameters ) ] );

    if ( !$template->isUsed() )
    {
      $parameters[ 'token' ] = $this->csrfTokenManager->getToken( TemplateController::DELETE_TOKEN )->getValue();
      $event->addDelete( $this->path( $event, TemplateController::ROUTE_DELETE, $parameters ), false );
    }
  }

  /**
   * Builds a link in the event's language.
   *
   * @param PageActionsEvent $event The actions event.
   * @param string $route The route name.
   * @param array<string, string> $parameters The route parameters.
   * @return string
   */
  private function path( PageActionsEvent $event, string $route, array $parameters = [] ) : string
  {
    if ( $event->getLocale() !== null )
    {
      $parameters[ '_locale' ] = $event->getLocale();
    }

    return $this->urlGenerator->generate( $route, $parameters );
  }
}
