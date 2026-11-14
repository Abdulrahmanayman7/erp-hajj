<?php

namespace App\Modules\Gps\Exceptions;

use RuntimeException;

/**
 * Thrown when GPS_PROVIDER requests a live/vendor binding that is not Owner-approved.
 * G4 remains BLOCKED until an approved adapter is registered (ADR-0021).
 */
final class GpsProviderNotApprovedException extends RuntimeException {}
