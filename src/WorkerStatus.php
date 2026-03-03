<?php

namespace ShopWatch\MessengerWorkerRegistry;

enum WorkerStatus: string
{
    case Running = 'running';
    case Stopped = 'stopped';
    case Dead = 'dead';
}
