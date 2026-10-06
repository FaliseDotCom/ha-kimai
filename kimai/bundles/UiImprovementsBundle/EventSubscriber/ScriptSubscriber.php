<?php

declare( strict_types=1 );

namespace KimaiPlugin\UiImprovementsBundle\EventSubscriber;

use App\Entity\User;
use App\Event\ThemeEvent;
use KimaiPlugin\UiImprovementsBundle\Controller\AssetController;
use KimaiPlugin\UiImprovementsBundle\UiImprovementsBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Adds the plugin's script to every page for logged-in users.
 */
final class ScriptSubscriber implements EventSubscriberInterface
{
  /**
   * The script added to every page.
   *
   * @var string
   */
  private const SCRIPT = 'ui-improvements.js';

  /**
   * @param UrlGeneratorInterface $urlGenerator Builds the script address.
   */
  public function __construct( private readonly UrlGeneratorInterface $urlGenerator )
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
   * Adds the script tag; the login page and other anonymous pages are skipped.
   *
   * @param ThemeEvent $event The event that collects scripts for the end of the page.
   * @return void
   */
  public function onJavascript( ThemeEvent $event ) : void
  {
    if ( !$event->getUser() instanceof User )
    {
      return;
    }

    $url = $this->urlGenerator->generate( AssetController::ROUTE, [
      'name' => self::SCRIPT,
      'v' => UiImprovementsBundle::getAssetVersion(),
    ] );

    $event->addContent( '<script src="' . htmlspecialchars( $url, ENT_QUOTES ) . '"></script>' );
  }
}
