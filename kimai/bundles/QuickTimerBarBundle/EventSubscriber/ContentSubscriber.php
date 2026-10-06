<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\EventSubscriber;

use App\Entity\User;
use App\Event\ThemeEvent;
use KimaiPlugin\QuickTimerBarBundle\Service\TimerBarViewFactory;
use KimaiPlugin\QuickTimerBarBundle\QuickTimerBarBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * Adds the quick start bar to every page, in place of Kimai's own start button, unless the
 * user turned it off in their preferences. Its script moves it to its own row directly below
 * the top bar, and removes the copy that comes along when Kimai reloads the page content. Also
 * enables the continue buttons on "My times".
 */
final class ContentSubscriber implements EventSubscriberInterface
{
  /**
   * The "My times" page, whose entries get a continue button.
   *
   * @var string
   */
  private const LIST_ROUTE = 'timesheet';

  /**
   * @param RequestStack $requestStack Tells which page is being rendered.
   * @param AuthorizationCheckerInterface $security Checks whether the user may track time.
   * @param TimerBarViewFactory $viewFactory Collects the data the bar shows.
   * @param Environment $twig Renders the bar.
   */
  public function __construct(
    private readonly RequestStack $requestStack,
    private readonly AuthorizationCheckerInterface $security,
    private readonly TimerBarViewFactory $viewFactory,
    private readonly Environment $twig
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
      ThemeEvent::CONTENT_START => 'onContentStart',
    ];
  }

  /**
   * Adds the quick start bar to the page, when the user has it turned on.
   *
   * @param ThemeEvent $event The event that collects content for the top of the page.
   * @return void
   */
  public function onContentStart( ThemeEvent $event ) : void
  {
    $user = $event->getUser();
    $request = $this->requestStack->getMainRequest();

    if ( !$user instanceof User || $request === null || !PreferenceSubscriber::isEnabled( $user ) )
    {
      return;
    }

    $route = $request->attributes->get( '_route' );

    if ( !$this->security->isGranted( 'create_own_timesheet' ) )
    {
      return;
    }

    $event->addContent( $this->twig->render( '@QuickTimerBar/bar.html.twig', [
      'timer_bar' => $this->viewFactory->create( $user, \Locale::getDefault() ),
      'target_path' => $request->getRequestUri(),
      'timer_bar_version' => QuickTimerBarBundle::getAssetVersion(),
      'show_continue' => $route === self::LIST_ROUTE,
    ] ) );
  }
}
