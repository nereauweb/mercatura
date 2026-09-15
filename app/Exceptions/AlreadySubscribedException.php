<?php

declare(strict_types=1);

namespace App\Exceptions;

use RuntimeException;

/** Raised by NewsletterProvider::subscribe() when the contact is already on the list. */
class AlreadySubscribedException extends RuntimeException {}
