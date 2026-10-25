<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Odt;

use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

/**
 * Fills an OpenDocument text template (.odt) with values.
 *
 * Placeholders are written as `${name}`. The part of the document that holds an `${entry.…}`
 * placeholder repeats once per record: the table row it is in, otherwise the list item,
 * otherwise the paragraph. All other placeholders are replaced once, in the body as well as
 * in headers and footers.
 */
final class OdtDocument
{
  public const EXTENSION = 'odt';
  public const CONTENT_TYPE = 'application/vnd.oasis.opendocument.text';

  /**
   * The prefix of the placeholders that take a record's values.
   */
  public const RECORD_PREFIX = 'entry.';

  private const NS_TEXT = 'urn:oasis:names:tc:opendocument:xmlns:text:1.0';
  private const NS_TABLE = 'urn:oasis:names:tc:opendocument:xmlns:table:1.0';

  /**
   * The parts of the package that hold text: the body, and the headers and footers.
   */
  private const PARTS = [ 'content.xml', 'styles.xml' ];

  /**
   * Matches a placeholder; the first group is its name.
   */
  private const PLACEHOLDER = '/\$\{([^{}\s]+)\}/';

  /**
   * Creates a document from a template file.
   *
   * @param string $templatePath The absolute path of the .odt template.
   */
  public function __construct(
    private readonly string $templatePath,
  )
  {
  }

  /**
   * Renders the template and returns it as a download that is removed once sent.
   *
   * @param TemplateData $data The values to fill in.
   * @param string $filename The download's file name, without extension.
   * @return BinaryFileResponse
   */
  public function download( TemplateData $data, string $filename ) : BinaryFileResponse
  {
    $response = new BinaryFileResponse( $this->render( $data ) );
    $disposition = $response->headers->makeDisposition(
      ResponseHeaderBag::DISPOSITION_ATTACHMENT,
      $filename . '.' . self::EXTENSION
    );

    $response->headers->set( 'Content-Type', self::CONTENT_TYPE );
    $response->headers->set( 'Content-Disposition', $disposition );
    $response->deleteFileAfterSend( true );

    return $response;
  }

  /**
   * Renders the template into a temporary file and returns its path.
   *
   * @param TemplateData $data The values to fill in.
   * @return string
   */
  public function render( TemplateData $data ) : string
  {
    $file = tempnam( sys_get_temp_dir(), 'kimai-odt' );
    if ( $file === false || !copy( $this->templatePath, $file ) )
    {
      throw new \RuntimeException( 'Could not create a temporary file for the ODT document.' );
    }

    $zip = new \ZipArchive();
    if ( $zip->open( $file ) !== true )
    {
      throw new \RuntimeException( sprintf( 'Could not open the ODT template %s.', $this->templatePath ) );
    }

    foreach ( self::PARTS as $part )
    {
      $xml = $zip->getFromName( $part );
      if ( $xml === false )
      {
        continue;
      }

      $zip->addFromString( $part, $this->fill( $xml, $data ) );
    }

    $zip->close();

    return $file;
  }

  /**
   * Fills one XML part of the package.
   *
   * @param string $xml The part's XML.
   * @param TemplateData $data The values to fill in.
   * @return string
   */
  private function fill( string $xml, TemplateData $data ) : string
  {
    $document = new \DOMDocument();
    if ( !$document->loadXML( $xml, LIBXML_NONET ) || $document->documentElement === null )
    {
      throw new \RuntimeException( sprintf( 'The ODT template %s contains invalid XML.', $this->templatePath ) );
    }

    $xpath = new \DOMXPath( $document );
    $xpath->registerNamespace( 'text', self::NS_TEXT );

    $this->joinSplitPlaceholders( $xpath );
    $this->repeatRecordParts( $xpath, $data );
    $this->replaceIn( $document->documentElement, $data->getValues(), false );

    return (string) $document->saveXML();
  }

  /**
   * Puts every placeholder in a single text node. Word processors split text into pieces
   * when its formatting or editing history differs, which can cut a placeholder in two.
   *
   * @param \DOMXPath $xpath An XPath object for the part.
   * @return void
   */
  private function joinSplitPlaceholders( \DOMXPath $xpath ) : void
  {
    $paragraphs = $xpath->query( '//text:p | //text:h' );
    if ( $paragraphs === false )
    {
      return;
    }

    foreach ( $paragraphs as $paragraph )
    {
      $this->joinInParagraph( $paragraph );
    }
  }

  /**
   * Joins the split placeholders of one paragraph. The whole placeholder moves into the text
   * node where it starts, and so takes that node's formatting.
   *
   * @param \DOMNode $paragraph The paragraph or heading.
   * @return void
   */
  private function joinInParagraph( \DOMNode $paragraph ) : void
  {
    $nodes = $this->textNodes( $paragraph, true );
    $offsets = [];
    $text = '';
    foreach ( $nodes as $index => $node )
    {
      $offsets[ $index ] = strlen( $text );
      $text .= $node->data;
    }

    if ( !preg_match_all( self::PLACEHOLDER, $text, $matches, PREG_OFFSET_CAPTURE ) )
    {
      return;
    }

    // From the last placeholder back, so the offsets of the earlier ones stay valid.
    foreach ( array_reverse( $matches[ 0 ] ) as [ $placeholder, $start ] )
    {
      $end = $start + strlen( $placeholder );
      $first = $this->nodeAt( $offsets, $start );
      $last = $this->nodeAt( $offsets, $end - 1 );
      if ( $first === $last )
      {
        continue;
      }

      $nodes[ $first ]->data = substr( $nodes[ $first ]->data, 0, $start - $offsets[ $first ] ) . $placeholder;
      for ( $index = $first + 1; $index < $last; $index++ )
      {
        $nodes[ $index ]->data = '';
      }
      $nodes[ $last ]->data = substr( $nodes[ $last ]->data, $end - $offsets[ $last ] );
    }
  }

  /**
   * Returns the index of the text node that holds a position in the joined text.
   *
   * @param array<int, int> $offsets The start offset of each text node.
   * @param int $position The position in the joined text.
   * @return int
   */
  private function nodeAt( array $offsets, int $position ) : int
  {
    $found = 0;
    foreach ( $offsets as $index => $offset )
    {
      if ( $offset > $position )
      {
        break;
      }

      $found = $index;
    }

    return $found;
  }

  /**
   * Repeats each part of the document that holds a record placeholder once per record, and
   * removes the original.
   *
   * @param \DOMXPath $xpath An XPath object for the part.
   * @param TemplateData $data The values to fill in.
   * @return void
   */
  private function repeatRecordParts( \DOMXPath $xpath, TemplateData $data ) : void
  {
    $nodes = $xpath->query( '//text()[contains(., "${' . self::RECORD_PREFIX . '")]' );
    if ( $nodes === false )
    {
      return;
    }

    $parts = [];
    foreach ( $nodes as $node )
    {
      $part = $this->recordPart( $node );
      if ( $part !== null )
      {
        $parts[ spl_object_id( $part ) ] = $part;
      }
    }

    foreach ( $parts as $part )
    {
      if ( $this->isInsideAny( $part, $parts ) )
      {
        continue;
      }

      $this->repeatPart( $part, $data );
    }
  }

  /**
   * Returns the part of the document that repeats for a record placeholder: the table row
   * around it, otherwise the list item, otherwise the paragraph.
   *
   * @param \DOMNode $node The text node with the placeholder.
   * @return \DOMElement|null
   */
  private function recordPart( \DOMNode $node ) : ?\DOMElement
  {
    $paragraph = null;
    for ( $parent = $node->parentNode; $parent instanceof \DOMElement; $parent = $parent->parentNode )
    {
      if ( $this->is( $parent, self::NS_TABLE, 'table-row' ) || $this->is( $parent, self::NS_TEXT, 'list-item' ) )
      {
        return $parent;
      }

      if ( $paragraph === null && $this->isParagraph( $parent ) )
      {
        $paragraph = $parent;
      }
    }

    return $paragraph;
  }

  /**
   * Checks whether an element lies inside one of the other repeating parts.
   *
   * @param \DOMElement $part The element to check.
   * @param array<int, \DOMElement> $parts All repeating parts, by object ID.
   * @return bool
   */
  private function isInsideAny( \DOMElement $part, array $parts ) : bool
  {
    for ( $parent = $part->parentNode; $parent !== null; $parent = $parent->parentNode )
    {
      if ( isset( $parts[ spl_object_id( $parent ) ] ) )
      {
        return true;
      }
    }

    return false;
  }

  /**
   * Inserts a filled copy of a part for each record, then removes the part itself.
   *
   * @param \DOMElement $part The table row, list item or paragraph.
   * @param TemplateData $data The values to fill in.
   * @return void
   */
  private function repeatPart( \DOMElement $part, TemplateData $data ) : void
  {
    $parent = $part->parentNode;
    if ( $parent === null )
    {
      return;
    }

    foreach ( $data->getRows() as $row )
    {
      $copy = $part->cloneNode( true );
      $this->replaceIn( $copy, array_merge( $data->getValues(), $row ), true );
      $parent->insertBefore( $copy, $part );
    }

    $parent->removeChild( $part );
  }

  /**
   * Replaces the placeholders in all text below a node.
   *
   * @param \DOMNode $root The node to fill.
   * @param array<string, mixed> $values The values, by placeholder name.
   * @param bool $blankRecordValues Whether a record placeholder without a value becomes empty,
   *                                as records do not all have the same optional values.
   * @return void
   */
  private function replaceIn( \DOMNode $root, array $values, bool $blankRecordValues ) : void
  {
    foreach ( $this->textNodes( $root, false ) as $node )
    {
      if ( !str_contains( $node->data, '${' ) )
      {
        continue;
      }

      $text = (string) preg_replace_callback(
        self::PLACEHOLDER,
        fn( array $match ) : string => $this->valueFor( $match[ 0 ], $match[ 1 ], $values, $blankRecordValues ),
        $node->data
      );

      if ( $text !== $node->data )
      {
        $this->setText( $node, $text );
      }
    }
  }

  /**
   * Returns the text for one placeholder. Unknown placeholders stay as they are, so a typing
   * error shows in the document.
   *
   * @param string $placeholder The placeholder as written.
   * @param string $name The placeholder's name.
   * @param array<string, mixed> $values The values, by placeholder name.
   * @param bool $blankRecordValues Whether a record placeholder without a value becomes empty.
   * @return string
   */
  private function valueFor( string $placeholder, string $name, array $values, bool $blankRecordValues ) : string
  {
    if ( !array_key_exists( $name, $values ) )
    {
      return $blankRecordValues && str_starts_with( $name, self::RECORD_PREFIX ) ? '' : $placeholder;
    }

    $value = $values[ $name ];
    if ( $value !== null && !is_scalar( $value ) )
    {
      // Lists, such as an invoice's tax rows, have no text form.
      return $placeholder;
    }

    // Control characters other than tabs and line breaks are not allowed in XML.
    return (string) preg_replace( '/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', (string) $value );
  }

  /**
   * Sets the text of a text node, turning tabs and line breaks into their ODF elements.
   *
   * @param \DOMText $node The text node.
   * @param string $text The new text.
   * @return void
   */
  private function setText( \DOMText $node, string $text ) : void
  {
    $pieces = preg_split( '/(\r\n|\r|\n|\t)/', $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY );
    $document = $node->ownerDocument;
    $parent = $node->parentNode;
    if ( $pieces === false || count( $pieces ) < 2 || $document === null || $parent === null )
    {
      $node->data = $text;

      return;
    }

    foreach ( $pieces as $piece )
    {
      $insert = match ( $piece )
      {
        "\t" => $document->createElementNS( self::NS_TEXT, 'text:tab' ),
        "\r\n", "\r", "\n" => $document->createElementNS( self::NS_TEXT, 'text:line-break' ),
        default => $document->createTextNode( $piece ),
      };
      $parent->insertBefore( $insert, $node );
    }

    $parent->removeChild( $node );
  }

  /**
   * Returns the text nodes below a node, in document order.
   *
   * @param \DOMNode $node The node to search.
   * @param bool $ownParagraphOnly Whether to skip paragraphs nested inside, such as footnotes.
   * @return array<int, \DOMText>
   */
  private function textNodes( \DOMNode $node, bool $ownParagraphOnly ) : array
  {
    $nodes = [];
    foreach ( $node->childNodes as $child )
    {
      if ( $child instanceof \DOMText )
      {
        $nodes[] = $child;
        continue;
      }

      if ( $child instanceof \DOMElement && !( $ownParagraphOnly && $this->isParagraph( $child ) ) )
      {
        array_push( $nodes, ...$this->textNodes( $child, $ownParagraphOnly ) );
      }
    }

    return $nodes;
  }

  /**
   * Checks whether an element is a paragraph or a heading.
   *
   * @param \DOMElement $element The element.
   * @return bool
   */
  private function isParagraph( \DOMElement $element ) : bool
  {
    return $this->is( $element, self::NS_TEXT, 'p' ) || $this->is( $element, self::NS_TEXT, 'h' );
  }

  /**
   * Checks an element's namespace and name.
   *
   * @param \DOMElement $element The element.
   * @param string $namespace The namespace URI.
   * @param string $name The local name.
   * @return bool
   */
  private function is( \DOMElement $element, string $namespace, string $name ) : bool
  {
    return $element->namespaceURI === $namespace && $element->localName === $name;
  }
}
