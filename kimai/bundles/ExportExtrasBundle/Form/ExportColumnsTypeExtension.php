<?php

declare( strict_types=1 );

namespace KimaiPlugin\ExportExtrasBundle\Form;

use App\Form\Type\ExportColumnsType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Lets the columns of an export template be put in order.
 *
 * Kimai keeps the columns in the order they were picked and exports them in that order, but
 * its select box shows the picked columns in the order of its own list, and saving the form
 * again stores that order. This turns on Kimai's drag and drop for the columns, and gives the
 * select box the stored order, which `export-columns.js` restores once the box is set up.
 */
final class ExportColumnsTypeExtension extends AbstractTypeExtension
{
  /**
   * The data attribute that carries the stored column order, as a JSON list.
   *
   * @var string
   */
  public const ORDER_ATTRIBUTE = 'data-column-order';

  /**
   * The help text below the columns, in Kimai's messages domain.
   *
   * @var string
   */
  private const HELP_MESSAGE = 'export_extras.columns_help';

  /**
   * Returns the form types this extension applies to.
   *
   * @return iterable<class-string>
   */
  public static function getExtendedTypes() : iterable
  {
    return [ ExportColumnsType::class ];
  }

  /**
   * Turns on drag and drop through Kimai's `order` option of its select boxes, and explains it.
   *
   * @param OptionsResolver $resolver The options of the columns field.
   * @return void
   */
  public function configureOptions( OptionsResolver $resolver ) : void
  {
    $resolver->setDefaults( [
      'order' => true,
      'help' => self::HELP_MESSAGE,
    ] );
  }

  /**
   * Adds the stored column order to the select box.
   *
   * @param FormView $view The view of the columns field.
   * @param FormInterface $form The columns field.
   * @param array<string, mixed> $options The options of the columns field.
   * @return void
   */
  public function buildView( FormView $view, FormInterface $form, array $options ) : void
  {
    $columns = $form->getData();
    if ( !is_array( $columns ) )
    {
      return;
    }

    $columns = array_values( array_filter( $columns, 'is_string' ) );
    $view->vars[ 'attr' ][ self::ORDER_ATTRIBUTE ] = (string) json_encode( $columns );
  }
}
