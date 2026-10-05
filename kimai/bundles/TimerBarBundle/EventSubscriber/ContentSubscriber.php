<?php

declare( strict_types=1 );

namespace KimaiPlugin\TimerBarBundle\EventSubscriber;

use App\Entity\User;
use App\Event\ThemeEvent;
use KimaiPlugin\TimerBarBundle\Service\TimerBarViewFactory;
use KimaiPlugin\TimerBarBundle\TimerBarBundle;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Twig\Environment;

/**
 * Adds the quick start bar to every page, in place of Kimai's own start button. Its script
 * moves it into the top navigation on wide screens; on narrow screens it stays on its own row
 * above the page content. Also enables the continue buttons on "My times".
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
   * Adds the quick start bar to the page.
   *
   * @param ThemeEvent $event The event that collects content for the top of the page.
   * @return void
   */
  public function onContentStart( ThemeEvent $event ) : void
  {
    $user = $event->getUser();
    $request = $this->requestStack->getMainRequest();

    if ( !$user instanceof User || $request === null )
    {
      return;
    }

    $route = $request->attributes->get( '_route' );

    if ( !$this->security->isGranted( 'create_own_timesheet' ) )
    {
      return;
    }

    $event->addContent( $this->twig->render( '@TimerBar/bar.html.twig', [
      'timer_bar' => $this->viewFactory->create( $user ),
      'target_path' => $request->getRequestUri(),
      'timer_bar_version' => TimerBarBundle::getAssetVersion(),
      'show_continue' => $route === self::LIST_ROUTE,
    ] ) );
  }
}
