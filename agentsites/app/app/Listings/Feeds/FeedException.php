<?php

declare(strict_types=1);

namespace App\Listings\Feeds;

use RuntimeException;

/** A feed could not be fetched or parsed; recorded in listing_feeds.last_error, never thrown at a visitor. */
final class FeedException extends RuntimeException {}
