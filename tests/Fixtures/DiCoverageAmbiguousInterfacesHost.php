<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

/** Two service interfaces in a union without Lazy — ambiguous. */
class DiCoverageAmbiguousInterfacesHost
{
    #[Autowired]
    public EmailServiceInterface|PaymentServiceInterface $svc;
}
