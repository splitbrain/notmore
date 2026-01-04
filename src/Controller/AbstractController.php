<?php

namespace splitbrain\notmore\Controller;

use splitbrain\notmore\App;

abstract class AbstractController
{
    public function __construct(protected App $app)
    {
    }
}
