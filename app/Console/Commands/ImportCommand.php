<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Connectors\ConnectorCommand;

/** Base of the core import commands; connector packages extend App\Support\Connectors\ConnectorCommand directly. */
abstract class ImportCommand extends ConnectorCommand {}
