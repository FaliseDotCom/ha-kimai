<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimesheetExtrasBundle\EventSubscriber;

use App\Entity\User;
use App\Event\ThemeEvent;
use KimaiPlugin\TimesheetExtrasBundle\Controller\AssetController;
use KimaiPlugin\TimesheetExtrasBundle\TimesheetExtrasBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Adds the closable search filters script to every page, and the collapsible days script and
 * stylesheet to the record lists when the user has Kimai's daily stats turned on, because the
 * day rows only exist then.
 */
final class ScriptSubscriber implements EventSubscriberInterface
{
  /**
   * Translation domain of the plugin's messages.
   *
   * @var string
   */
  public const TRANSLATION_DOMAIN = 'timesheet_extras';

  /**
   * The script that closes the search filters on a press outside them, and adds their close
   * button. Kimai's lists have search filters on many pages, so it is added to every page.
   *
   * @var string
   */
  private const FILTER_SCRIPT = 'search-filter.js';

  /**
   * Kimai's own label of close buttons, in its messages domain.
   *
   * @var string
   */
  private const CLOSE_MESSAGE = 'action.close';

  /**
   * The script that makes the days collapsible.
   *
   * @var string
   */
  private const DAYS_SCRIPT = 'timesheet-days.js';

  /**
   * The stylesheet of the collapsible days.
   *
   * @var string
   */
  private const DAYS_STYLESHEET = 'timesheet-days.css';

  /**
   * Kimai's preference "Show daily stats in timesheet", which adds a total row per day.
   *
   * @var string
   */
  private const DAILY_STATS_PREFERENCE = 'daily_stats';

  /**
   * The record lists by route, with the name the script remembers their state under: "My
   * times" and "All times", with their paginated variants.
   *
   * @var array<string, string>
   */
  private const LIST_ROUTES = [
    'timesheet' => 'own',
    'timesheet_paginated' => 'own',
    'admin_timesheet' => 'all',
    'admin_timesheet_paginated' => 'all',
  ];

  /**
   * Messages the script shows, by the key it uses.
   *
   * @var array<string, string>
   */
  private const MESSAGES = [
    'collapseAll' => 'timesheet_days.collapse_all',
    'expandAll' => 'timesheet_days.expand_all',
    'collapseDay' => 'timesheet_days.collapse_day',
    'expandDay' => 'timesheet_days.expand_day',
  ];

  /**
   * @param UrlGeneratorInterface $urlGenerator Builds the asset addresses.
   * @param RequestStack $requestStack Tells which page is being rendered.
   * @param TranslatorInterface $translator Translates the messages of the script.
   */
  public function __construct(
    private readonly UrlGeneratorInterface $urlGenerator,
    private readonly RequestStack $requestStack,
    private readonly TranslatorInterface $translator
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
      ThemeEvent::STYLESHEET => 'onStylesheet',
    ];
  }

  /**
   * Adds the search filters script to every page, and the collapsible days script to the
   * record lists.
   *
   * @param ThemeEvent $event The event that collects scripts for the end of the page.
   * @return void
   */
  public function onJavascript( ThemeEvent $event ) : void
  {
    $user = $event->getUser();
    if ( !$user instanceof User )
    {
      return;
    }

    $event->addContent( $this->renderScript( self::FILTER_SCRIPT, [
      'close' => $this->translator->trans( self::CLOSE_MESSAGE ),
    ] ) );

    $list = $this->getDaysList( $event );
    if ( $list === '' )
    {
      return;
    }

    // The user ID keeps the collapsed days of two accounts in one browser apart.
    $event->addContent( $this->renderScript( self::DAYS_SCRIPT, [
      'list' => $list,
      'user' => (string) $user->getId(),
      'messages' => (string) json_encode( $this->translateMessages() ),
    ] ) );
  }

  /**
   * Returns the tag of a module script, with values for it in data attributes.
   *
   * @param string $name The file name of the script.
   * @param array<string, string> $attributes The values, by data attribute name without its prefix.
   * @return string
   */
  private function renderScript( string $name, array $attributes ) : string
  {
    $html = '<script type="module" src="' . htmlspecialchars( $this->getAssetUrl( $name ), ENT_QUOTES ) . '"';
    foreach ( $attributes as $key => $value )
    {
      $html .= ' data-' . $key . '="' . htmlspecialchars( $value, ENT_QUOTES ) . '"';
    }

    return $html . '></script>';
  }

  /**
   * Adds the collapsible days stylesheet to the record lists.
   *
   * @param ThemeEvent $event The event that collects stylesheets for the page head.
   * @return void
   */
  public function onStylesheet( ThemeEvent $event ) : void
  {
    if ( $this->getDaysList( $event ) === '' )
    {
      return;
    }

    $event->addContent( '<link rel="stylesheet" href="' . htmlspecialchars( $this->getAssetUrl( self::DAYS_STYLESHEET ), ENT_QUOTES ) . '">' );
  }

  /**
   * Returns the name of the record list on the page when it shows day rows, or an empty
   * string on other pages and when the user has the daily stats turned off.
   *
   * @param ThemeEvent $event The theme event, which carries the logged-in user.
   * @return string
   */
  private function getDaysList( ThemeEvent $event ) : string
  {
    $user = $event->getUser();
    if ( !$user instanceof User || !$user->getPreferenceValue( self::DAILY_STATS_PREFERENCE, false, false ) )
    {
      return '';
    }

    $route = $this->requestStack->getMainRequest()?->attributes->get( '_route' );

    return is_string( $route ) ? ( self::LIST_ROUTES[ $route ] ?? '' ) : '';
  }

  /**
   * Returns the versioned address of an asset.
   *
   * @param string $name The file name.
   * @return string
   */
  private function getAssetUrl( string $name ) : string
  {
    return $this->urlGenerator->generate( AssetController::ROUTE, [
      'name' => $name,
      'v' => TimesheetExtrasBundle::getAssetVersion(),
    ] );
  }

  /**
   * Translates the messages of the script.
   *
   * @return array<string, string>
   */
  private function translateMessages() : array
  {
    return array_map(
      fn( string $key ) : string => $this->translator->trans( $key, [], self::TRANSLATION_DOMAIN ),
      self::MESSAGES
    );
  }
}
