<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\EventSubscriber;

use App\Entity\User;
use App\Entity\UserPreference;
use App\Event\UserPreferenceEvent;
use App\Form\Type\YesNoType;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Adds a user preference that turns the quick start bar on or off. It is on by default.
 */
final class PreferenceSubscriber implements EventSubscriberInterface
{
  /**
   * Name of the preference; also the translation key of its label.
   *
   * @var string
   */
  public const PREFERENCE = 'timer_bar_enabled';

  /**
   * Preferences section the setting is shown in.
   *
   * @var string
   */
  private const SECTION = 'behaviour';

  /**
   * Position within the section: directly after Kimai's "Show daily stats in timesheet".
   *
   * @var int
   */
  private const ORDER = 810;

  /**
   * Returns the events this subscriber listens to.
   *
   * @return array<string, string>
   */
  public static function getSubscribedEvents() : array
  {
    return [
      UserPreferenceEvent::class => 'onUserPreferences',
    ];
  }

  /**
   * Tells whether the user wants to see the quick start bar.
   *
   * @param User $user The logged-in user.
   * @return bool
   */
  public static function isEnabled( User $user ) : bool
  {
    return (bool) $user->getPreferenceValue( self::PREFERENCE, true, false );
  }

  /**
   * Adds the quick start bar preference.
   *
   * @param UserPreferenceEvent $event The event that collects the user preferences.
   * @return void
   */
  public function onUserPreferences( UserPreferenceEvent $event ) : void
  {
    $event->addPreference(
      ( new UserPreference( self::PREFERENCE, true ) )
        ->setOrder( self::ORDER )
        ->setSection( self::SECTION )
        ->setType( YesNoType::class )
    );
  }
}
