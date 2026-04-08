<?php

namespace ShopWatch\MessengerWorkerRegistry;

use Symfony\Component\HttpKernel\Bundle\Bundle;

class MessengerWorkerRegistryBundle extends Bundle
{
    public function getPath(): string
    {
        return \dirname(__DIR__);
    }
}
