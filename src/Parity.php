<?php

declare(strict_types=1);

namespace Sanchescom\Serial;

enum Parity: string
{
    case None = 'none';
    case Even = 'even';
    case Odd = 'odd';
}
