<?php

declare( strict_types=1 );

namespace KimaiPlugin\QuickTimerBarBundle\Exception;

use RuntimeException;

/**
 * A typed name could not be used, or the user may not create it. The message is a translation
 * key in the timer_bar domain.
 */
final class InvalidInputException extends RuntimeException
{
}
