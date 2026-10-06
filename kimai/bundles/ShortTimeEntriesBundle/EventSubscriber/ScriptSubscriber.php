<?php

declare( strict_types=1 );

namespace KimaiPlugin\ShortTimeEntriesBundle\EventSubscriber;

use App\Entity\User;
use App\Event\ThemeEvent;
use KimaiPlugin\ShortTimeEntriesBundle\Controller\AssetController;
use KimaiPlugin\ShortTimeEntriesBundle\ShortTimeEntriesBundle;
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
  private const SCRIPT = 'short-time-entries.js';

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
      'v' => ShortTimeEntriesBundle::getAssetVersion(),
    ] );

    $event->addContent( '<script type="module" src="' . htmlspecialchars( $url, ENT_QUOTES ) . '"></script>' );
  }
}
