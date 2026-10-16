<?php
/**
 * Domain validation error. REST controllers translate this into an HTTP 400
 * with the human-readable message.
 *
 * @package TradeSheetStudio\Support
 */

declare( strict_types=1 );

namespace TradeSheetStudio\Support;

class ValidationException extends \RuntimeException {

}
