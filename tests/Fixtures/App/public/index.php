<?php

declare(strict_types=1);

use Symfony\Component\HttpFoundation\Request;
use Zeusi\AsyncApiBundle\Tests\Fixtures\App\TestKernel;

require \dirname(__DIR__, 4) . '/vendor/autoload.php';

$kernel = new TestKernel('server');
$request = Request::createFromGlobals();
$response = $kernel->handle($request);
$response->send();
$kernel->terminate($request, $response);
