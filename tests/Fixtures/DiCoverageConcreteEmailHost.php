<?php

declare(strict_types=1);

namespace Switon\Di\Tests\Fixtures;

use Switon\Core\Attribute\Autowired;

/** Concrete class where sibling `EmailServiceInterface` exists — DIP guard in Injector. */
class DiCoverageConcreteEmailHost
{
    #[Autowired]
    public EmailService $mailer;
}
