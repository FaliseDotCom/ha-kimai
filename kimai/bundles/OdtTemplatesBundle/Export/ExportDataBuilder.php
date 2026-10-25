<?php

declare( strict_types=1 );

namespace KimaiPlugin\OdtTemplatesBundle\Export;

use App\Configuration\LocaleService;
use App\Entity\Customer;
use App\Entity\ExportableItem;
use App\Entity\InvoiceTemplate;
use App\Entity\User;
use App\Invoice\Calculator\DefaultCalculator;
use App\Invoice\DefaultInvoiceFormatter;
use App\Invoice\InvoiceItem;
use App\Invoice\InvoiceModel;
use App\Invoice\InvoiceModelFactory;
use App\Repository\Query\InvoiceQuery;
use App\Repository\Query\TimesheetQuery;
use KimaiPlugin\OdtTemplatesBundle\Odt\OdtDocument;
use KimaiPlugin\OdtTemplatesBundle\Odt\TemplateData;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * Turns exported records into template values.
 *
 * Each record gets the same `entry.…` values as in Kimai's invoice documents, made by Kimai's
 * own invoice code, so templates can move between exports and invoices. The document gets
 * `export.…` values: the period, totals and the names involved.
 */
final class ExportDataBuilder
{
  /**
   * The prefixes of the record values that show money, hidden from users who may not see rates.
   */
  private const RATE_PREFIXES = [ 'entry.rate', 'entry.total' ];

  /**
   * The prefix of the document-wide values.
   */
  private const PREFIX = 'export.';

  /**
   * The prefix of a record's custom field values.
   */
  private const META_PREFIX = OdtDocument::RECORD_PREFIX . 'meta.';

  private const LIST_SEPARATOR = ', ';

  /**
   * Creates the builder.
   *
   * @param InvoiceModelFactory $modelFactory Kimai's factory for invoice models, with all hydrators.
   * @param LocaleService $localeService Kimai's locale settings, for formatting.
   * @param Security $security The security helper, for the rate permissions.
   */
  public function __construct(
    private readonly InvoiceModelFactory $modelFactory,
    private readonly LocaleService $localeService,
    private readonly Security $security,
  )
  {
  }

  /**
   * Builds the template values for exported records.
   *
   * @param array<int, ExportableItem> $exportItems The records, as Kimai passes them to an export.
   * @param TimesheetQuery $query The filter they were selected with.
   * @return TemplateData
   */
  public function build( array $exportItems, TimesheetQuery $query ) : TemplateData
  {
    $user = $this->getUser( $query );
    $formatter = new DefaultInvoiceFormatter( $this->localeService, $user?->getLocale() ?? User::DEFAULT_LANGUAGE );

    // The model only lends its formatting and its record values; it is never saved.
    $model = $this->modelFactory->createModel( $formatter, new Customer( '' ), new InvoiceTemplate(), new InvoiceQuery() );
    $calculator = new DefaultCalculator();
    $model->setCalculator( $calculator );
    $model->addEntries( $exportItems );

    $showRates = $this->canSeeRates( $query );
    $hiddenFields = $this->hiddenFieldNames( $exportItems );

    $rows = [];
    foreach ( $calculator->getEntries() as $item )
    {
      $rows[] = $this->rowValues( $model->itemToArray( $item ), $hiddenFields, $showRates );
    }

    return new TemplateData( $this->documentValues( $model, $calculator, $query, $user, $showRates ), $rows );
  }

  /**
   * Returns the document-wide values.
   *
   * @param InvoiceModel $model The model that formats the values.
   * @param DefaultCalculator $calculator The calculator with the records.
   * @param TimesheetQuery $query The filter the records were selected with.
   * @param User|null $user The user who exports.
   * @param bool $showRates Whether the user may see rates.
   * @return array<string, mixed>
   */
  private function documentValues( InvoiceModel $model, DefaultCalculator $calculator, TimesheetQuery $query, ?User $user, bool $showRates ) : array
  {
    $formatter = $model->getFormatter();
    $period = $model->getInvoicePeriod();
    $items = $calculator->getEntries();
    $duration = $calculator->getTimeWorked();
    $currency = $this->currency( $items );
    $total = $calculator->getSubtotal();

    return [
      self::PREFIX . 'date' => $formatter->getFormattedDateTime( new \DateTimeImmutable() ),
      self::PREFIX . 'begin' => $formatter->getFormattedDateTime( $query->getBegin() ?? $period->getStart() ),
      self::PREFIX . 'end' => $formatter->getFormattedDateTime( $query->getEnd() ?? $period->getEnd() ),
      self::PREFIX . 'count' => count( $items ),
      self::PREFIX . 'duration' => $formatter->getFormattedDuration( $duration ),
      self::PREFIX . 'duration_decimal' => $formatter->getFormattedDecimalDuration( $duration ),
      self::PREFIX . 'duration_minutes' => intdiv( $duration, 60 ),
      self::PREFIX . 'currency' => $currency ?? '',
      self::PREFIX . 'total' => $showRates ? $formatter->getFormattedMoney( $total, $currency ) : '',
      self::PREFIX . 'total_nc' => $showRates ? $formatter->getFormattedMoney( $total, $currency, false ) : '',
      self::PREFIX . 'total_plain' => $showRates ? $total : '',
      self::PREFIX . 'customers' => $this->names( $items, fn( InvoiceItem $item ) : ?string => $item->getProject()?->getCustomer()?->getName() ),
      self::PREFIX . 'projects' => $this->names( $items, fn( InvoiceItem $item ) : ?string => $item->getProject()?->getName() ),
      self::PREFIX . 'activities' => $this->names( $items, fn( InvoiceItem $item ) : ?string => $item->getActivity()?->getName() ),
      self::PREFIX . 'users' => $this->names( $items, fn( InvoiceItem $item ) : ?string => $item->getUser()?->getDisplayName() ),
      self::PREFIX . 'author' => $user?->getDisplayName() ?? '',
    ];
  }

  /**
   * Removes the values a record's reader may not see: hidden custom fields, and money when
   * rates are hidden.
   *
   * @param array<string, mixed> $values The record's values, by placeholder name.
   * @param array<string, bool> $hiddenFields The names of the hidden custom fields.
   * @param bool $showRates Whether the user may see rates.
   * @return array<string, mixed>
   */
  private function rowValues( array $values, array $hiddenFields, bool $showRates ) : array
  {
    foreach ( array_keys( $hiddenFields ) as $name )
    {
      unset( $values[ self::META_PREFIX . $name ] );
    }

    if ( $showRates )
    {
      return $values;
    }

    foreach ( array_keys( $values ) as $key )
    {
      foreach ( self::RATE_PREFIXES as $prefix )
      {
        if ( str_starts_with( $key, $prefix ) )
        {
          $values[ $key ] = '';
        }
      }
    }

    return $values;
  }

  /**
   * Returns the names of the custom fields that are hidden from users, as Kimai's own exports
   * leave those out.
   *
   * @param array<int, ExportableItem> $exportItems The records.
   * @return array<string, bool>
   */
  private function hiddenFieldNames( array $exportItems ) : array
  {
    $hidden = [];
    foreach ( $exportItems as $exportItem )
    {
      foreach ( $exportItem->getMetaFields() as $field )
      {
        $name = $field->getName();
        if ( $name !== null && !$field->isVisible() )
        {
          $hidden[ $name ] = true;
        }
      }
    }

    return $hidden;
  }

  /**
   * Returns the currency of the records when they all share one, or null when they differ.
   *
   * @param array<int, InvoiceItem> $items The records.
   * @return string|null
   */
  private function currency( array $items ) : ?string
  {
    $currencies = [];
    foreach ( $items as $item )
    {
      $currency = $item->getProject()?->getCustomer()?->getCurrency();
      if ( $currency !== null )
      {
        $currencies[ $currency ] = true;
      }
    }

    return count( $currencies ) === 1 ? (string) array_key_first( $currencies ) : null;
  }

  /**
   * Returns the distinct names the records have for one property, sorted and joined.
   *
   * @param array<int, InvoiceItem> $items The records.
   * @param callable( InvoiceItem ) : ?string $nameOf Returns a record's name for the property.
   * @return string
   */
  private function names( array $items, callable $nameOf ) : string
  {
    $names = [];
    foreach ( $items as $item )
    {
      $name = $nameOf( $item );
      if ( $name !== null && $name !== '' )
      {
        $names[ $name ] = $name;
      }
    }

    natcasesort( $names );

    return implode( self::LIST_SEPARATOR, $names );
  }

  /**
   * Returns the user who exports, or null for an export from the command line.
   *
   * @param TimesheetQuery $query The filter of the export.
   * @return User|null
   */
  private function getUser( TimesheetQuery $query ) : ?User
  {
    $user = $query->getCurrentUser() ?? $this->security->getUser();

    return $user instanceof User ? $user : null;
  }

  /**
   * Checks whether the user may see rates, with the same rule as Kimai's own exports.
   *
   * @param TimesheetQuery $query The filter of the export.
   * @return bool
   */
  private function canSeeRates( TimesheetQuery $query ) : bool
  {
    if ( $this->security->getUser() === null )
    {
      return true;
    }

    if ( $query->getUser() !== null )
    {
      return $this->security->isGranted( 'view_rate_own_timesheet' );
    }

    return $this->security->isGranted( 'view_rate_other_timesheet' );
  }
}
