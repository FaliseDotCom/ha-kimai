<?php

declare( strict_types=1 );

namespace KimaiPlugin\ExportExtrasBundle\EventSubscriber;

use App\Event\ThemeEvent;
use KimaiPlugin\ExportExtrasBundle\Controller\AssetController;
use KimaiPlugin\ExportExtrasBundle\ExportExtrasBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;

/**
 * Adds the column order script to the pages that show the export template form: Kimai's
 * export page, which opens the form in a dialog, and the form's own pages.
 */
final class ScriptSubscriber implements EventSubscriberInterface
{
  /**
   * The script that restores the column order.
   *
   * @var string
   */
  private const COLUMNS_SCRIPT = 'export-columns.js';

  /**
   * Kimai's permission to create and edit export templates.
   *
   * @var string
   */
  private const TEMPLATE_PERMISSION = 'create_export_template';

  /**
   * The routes of the pages that show the export template form.
   *
   * @var array<int, string>
   */
  private const ROUTES = [
    'export',
    'export_template_create',
    'export_template_edit',
  ];

  /**
   * @param UrlGeneratorInterface $urlGenerator Builds the asset address.
   * @param RequestStack $requestStack Tells which page is being rendered.
   * @param AuthorizationCheckerInterface $authorizationChecker Checks the template permission.
   */
  public function __construct(
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly RequestStack $requestStack,
    private readonly AuthorizationCheckerInterface $authorizationChecker
  )
  {
  }

  /**
   * Returns the events this subscriber listens to.
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents() : array
  {
    return [
      ThemeEvent::JAVASCRIPT => 'onJavascript',
    ];
  }

  /**
   * Adds the column order script for users who may edit export templates.
   *
   * @param ThemeEvent $event The event that collects scripts for the end of the page.
   * @return void
   */
  public function onJavascript( ThemeEvent $event ) : void
  {
    $route = $this->requestStack->getMainRequest()?->attributes->get( '_route' );
    if ( !in_array( $route, self::ROUTES, true ) || !$this->authorizationChecker->isGranted( self::TEMPLATE_PERMISSION ) )
    {
      return;
    }

    $url = $this->urlGenerator->generate( AssetController::ROUTE, [
      'name' => self::COLUMNS_SCRIPT,
      'v' => ExportExtrasBundle::getAssetVersion(),
    ] );
    $event->addContent( '<script type="module" src="' . htmlspecialchars( $url, ENT_QUOTES ) . '"></script>' );
  }
}
