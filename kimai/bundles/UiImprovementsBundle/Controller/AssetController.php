<?php

declare( strict_types=1 );

namespace KimaiPlugin\UiImprovementsBundle\Controller;

use App\Controller\AbstractController;
use KimaiPlugin\UiImprovementsBundle\UiImprovementsBundle;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Serves the plugin's script, which Kimai's asset build does not include.
 */
#[Route( path: '/ui-improvements/assets' )]
#[IsGranted( 'IS_AUTHENTICATED_REMEMBERED' )]
final class AssetController extends AbstractController
{
  public const ROUTE = 'ui_improvements_asset';

  /**
   * How long browsers may cache the assets, in seconds.
   *
   * @var int
   */
  private const MAX_AGE = 86400;

  /**
   * Serves one asset.
   *
   * @param string $name The file name, one of the keys of UiImprovementsBundle::ASSETS.
   * @return Response
   */
  #[Route( path: '/{name}', name: self::ROUTE, methods: [ 'GET' ] )]
  public function asset( string $name ) : Response
  {
    if ( !isset( UiImprovementsBundle::ASSETS[ $name ] ) )
    {
      throw new NotFoundHttpException();
    }

    $response = new BinaryFileResponse( UiImprovementsBundle::ASSET_DIRECTORY . '/' . $name );
    $response->headers->set( 'Content-Type', UiImprovementsBundle::ASSETS[ $name ] );
    $response->setPublic();
    $response->setMaxAge( self::MAX_AGE );

    return $response;
  }
}
