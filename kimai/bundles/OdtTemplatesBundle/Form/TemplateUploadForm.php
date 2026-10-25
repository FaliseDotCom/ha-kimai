<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Form;

use KimaiPlugin\OdtTemplatesBundle\Odt\OdtDocument;
use KimaiPlugin\OdtTemplatesBundle\Odt\TemplateKind;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\File;
use Symfony\Component\Validator\Constraints\NotNull;

/**
 * The form to upload an ODT template: the file, what it is for, and whether it may replace a
 * template with the same name.
 *
 * @extends AbstractType<array<string, mixed>>
 */
final class TemplateUploadForm extends AbstractType
{
  public const TRANSLATION_DOMAIN = 'odt_templates';

  /**
   * The largest file accepted, the same as PHP's upload limit in the Kimai images.
   */
  private const MAX_SIZE = '2M';

  /**
   * Adds the fields.
   *
   * @param FormBuilderInterface $builder The form builder.
   * @param array<string, mixed> $options The form options.
   * @return void
   */
  public function buildForm( FormBuilderInterface $builder, array $options ) : void
  {
    $builder
      ->add( 'document', FileType::class, [
        'label' => 'odt.document',
        'help' => 'odt.document.help',
        'required' => true,
        'attr' => [ 'accept' => '.' . OdtDocument::EXTENSION . ',' . OdtDocument::CONTENT_TYPE ],
        'constraints' => [
          new NotNull(),
          new File( [ 'maxSize' => self::MAX_SIZE ] ),
        ],
      ] )
      ->add( 'kind', EnumType::class, [
        'class' => TemplateKind::class,
        'label' => 'odt.kind',
        'expanded' => true,
        'data' => TemplateKind::Export,
        'choice_label' => fn( TemplateKind $kind ) : string => $kind->getLabel(),
      ] )
      ->add( 'replace', CheckboxType::class, [
        'label' => 'odt.replace',
        'required' => false,
      ] );
  }

  /**
   * Sets the translation domain of the labels.
   *
   * @param OptionsResolver $resolver The options resolver.
   * @return void
   */
  public function configureOptions( OptionsResolver $resolver ) : void
  {
    $resolver->setDefaults( [
      'translation_domain' => self::TRANSLATION_DOMAIN,
    ] );
  }
}
