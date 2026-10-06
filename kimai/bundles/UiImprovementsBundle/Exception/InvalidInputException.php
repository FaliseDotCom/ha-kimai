<?php

declare( strict_types=1 );

namespace KimaiPlugin\UiImprovementsBundle\Exception;

use RuntimeException;

/**
 * A value typed into the list could not be used. The message is a translation key in the
 * ui_improvements domain.
 */
final class InvalidInputException extends RuntimeException
{
}
