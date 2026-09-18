<?php

declare(strict_types=1);

namespace Sanchescom\Serial;

enum FlowControl: string
{
    case None = 'none';
    case RtsCts = 'rtscts';
    case XonXoff = 'xonxoff';
}
