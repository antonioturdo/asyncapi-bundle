<?php

declare(strict_types=1);

namespace Zeusi\AsyncApiBundle\Document;

/**
 * AsyncAPI operation action, from the point of view of the documented application.
 *
 * `send` = the app publishes (producer); `receive` = the app consumes.
 */
enum OperationAction: string
{
    case Send = 'send';
    case Receive = 'receive';
}
