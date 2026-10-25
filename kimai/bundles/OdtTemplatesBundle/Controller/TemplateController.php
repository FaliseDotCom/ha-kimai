<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Controller;

use App\Controller\AbstractController;
use App\Utils\PageSetup;
use KimaiPlugin\OdtTemplatesBundle\Form\TemplateUploadForm;
use KimaiPlugin\OdtTemplatesBundle\Odt\TemplateKind;
use KimaiPlugin\OdtTemplatesBundle\Odt\TemplateLibrary;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * The page to upload, download and delete ODT templates, next to Kimai's own invoice
 * document page. Kimai's upload only accepts a fixed list of file types, without ODT.
 * It needs the same permission as Kimai's upload.
 */
#[Route( path: '/invoice/odt-templates' )]
#[IsGranted( 'upload_invoice_template' )]
final class TemplateController extends AbstractController
{
  public const ROUTE = 'odt_templates';
  public const ROUTE_DOWNLOAD = 'odt_template_download';
  public const ROUTE_DELETE = 'odt_template_delete';

  /**
   * The name of the page's actions, and of the actions of each template in its list.
   */
  public const ACTIONS_PAGE = 'odt_templates';
  public const ACTIONS_TEMPLATE = 'odt_template';

  /**
   * The CSRF token ID of the delete links.
   */
  public const DELETE_TOKEN = 'odt_template_delete';

  private const FILE_NAME_PATTERN = '[A-Za-z0-9_-]+\.odt';

  /**
   * Creates the controller.
   *
   * @param TemplateLibrary $library Lists, stores and removes the templates.
   * @param TranslatorInterface $translator Translates the reasons an upload is refused.
   */
  public function __construct(
    private readonly TemplateLibrary $library,
    private readonly TranslatorInterface $translator,
  )
  {
  }

  /**
   * Shows the upload form and the templates, and stores an uploaded template.
   *
   * @param Request $request The current request.
   * @return Response
   */
  #[Route( path: '', name: self::ROUTE, methods: [ 'GET', 'POST' ] )]
  public function index( Request $request ) : Response
  {
    $form = $this->createForm( TemplateUploadForm::class, null, [
      'action' => $this->generateUrl( self::ROUTE ),
      'method' => 'POST',
    ] );
    $form->handleRequest( $request );

    if ( $form->isSubmitted() && $form->isValid() && $this->upload( $form ) )
    {
      $this->flashSuccess( 'action.update.success' );

      return $this->redirectToRoute( self::ROUTE );
    }

    $page = new PageSetup( 'odt.title' );
    $page->setTranslationDomain( TemplateUploadForm::TRANSLATION_DOMAIN );
    $page->setActionName( self::ACTIONS_PAGE );

    return $this->render( '@OdtTemplates/templates.html.twig', [
      'page_setup' => $page,
      'form' => $form->createView(),
      'templates' => $this->library->findAll(),
    ] );
  }

  /**
   * Downloads a template.
   *
   * @param string $kind `export` or `invoice`.
   * @param string $name The file name.
   * @return Response
   */
  #[Route( path: '/{kind}/{name}', name: self::ROUTE_DOWNLOAD, requirements: [ 'kind' => 'export|invoice', 'name' => self::FILE_NAME_PATTERN ], methods: [ 'GET' ] )]
  public function download( string $kind, string $name ) : Response
  {
    $template = $this->library->find( TemplateKind::from( $kind ), $name );
    if ( $template === null )
    {
      throw $this->createNotFoundException( 'Unknown ODT template: ' . $name );
    }

    return $this->file( $template->getPath(), $template->getFileName() );
  }

  /**
   * Deletes a template, after the confirmation in the list. The link carries a CSRF token.
   *
   * @param string $kind `export` or `invoice`.
   * @param string $name The file name.
   * @param Request $request The current request.
   * @return Response
   */
  #[Route( path: '/{kind}/{name}/delete', name: self::ROUTE_DELETE, requirements: [ 'kind' => 'export|invoice', 'name' => self::FILE_NAME_PATTERN ], methods: [ 'GET' ] )]
  public function delete( string $kind, string $name, Request $request ) : Response
  {
    if ( !$this->isCsrfTokenValid( self::DELETE_TOKEN, (string) $request->query->get( 'token' ) ) )
    {
      throw $this->createAccessDeniedException( 'Invalid security token for deleting a template.' );
    }

    $template = $this->library->find( TemplateKind::from( $kind ), $name );
    if ( $template === null )
    {
      throw $this->createNotFoundException( 'Unknown ODT template: ' . $name );
    }

    try
    {
      $this->library->delete( $template );
      $this->flashSuccess( 'action.delete.success' );
    }
    catch ( \RuntimeException $exception )
    {
      $this->flashDeleteException( $exception );
    }

    return $this->redirectToRoute( self::ROUTE );
  }

  /**
   * Stores the uploaded template, or adds the reason it is refused to the form.
   *
   * @param FormInterface $form The submitted, valid form.
   * @return bool
   */
  private function upload( FormInterface $form ) : bool
  {
    $file = $form->get( 'document' )->getData();
    $kind = $form->get( 'kind' )->getData();
    if ( !$file instanceof UploadedFile || !$kind instanceof TemplateKind )
    {
      return false;
    }

    $fileName = $this->library->fileNameFor( $file->getClientOriginalName() );
    $problem = $this->library->findProblem( $file, $kind, $fileName, (bool) $form->get( 'replace' )->getData() );
    if ( $problem !== '' )
    {
      $message = $this->translator->trans( $problem, [ '%name%' => $fileName ], TemplateUploadForm::TRANSLATION_DOMAIN );
      $form->get( 'document' )->addError( new FormError( $message ) );

      return false;
    }

    try
    {
      $this->library->save( $file, $kind, $fileName );
    }
    catch ( \Exception $exception )
    {
      $this->flashException( $exception, 'action.upload.error' );

      return false;
    }

    return true;
  }
}
