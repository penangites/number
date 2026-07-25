<?php

declare(strict_types=1);

namespace Penangites\Number;

/**
 * How a value is rounded when digits beyond the requested scale must go.
 */
enum RoundingMode
{
    /**
     * Round halves away from zero (2.5 -> 3, -2.5 -> -3). The default.
     */
    case HalfAwayFromZero;

    /**
     * Always round away from zero when any fraction remains.
     */
    case Up;

    /**
     * Always round toward zero (truncate).
     */
    case Down;

    /**
     * Round toward positive infinity.
     */
    case Ceiling;

    /**
     * Round toward negative infinity.
     */
    case Floor;
}
