<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\QuickTimerBarBundle\QuickTimerBarBundle;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the quick start bar's script and stylesheet, which Kimai's asset build does not include.
 */
#[Route( path: '/timer-bar/assets' )]
#[IsGranted( 'create_own_timesheet' )]
final class AssetController extends AbstractController
{
  public const ROUTE = 'timer_bar_asset';

  /**
   * How long browsers may cache the assets, in seconds.
   *
   * @var int
   */
  private const MAX_AGE = 86400;

  /**
   * Serves one asset.
   *
   * @param string $name The file name, one of the keys of QuickTimerBarBundle::ASSETS.
   * @return Response
   */
  #[Route( path: '/{name}', name: self::ROUTE, methods: [ 'GET' ] )]
  public function asset( string $name ) : Response
  {
    if ( !isset( QuickTimerBarBundle::ASSETS[ $name ] ) )
    {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse( QuickTimerBarBundle::ASSET_DIRECTORY . '/' . $name );
    $response->headers->set( 'Content-Type', QuickTimerBarBundle::ASSETS[ $name ] );
    $response->setPublic();
    $response->setMaxAge( self::MAX_AGE );

    return $response;
  }
}
