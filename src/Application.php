<?php

namespace App;
use App\controller\Controller;
use Exception;

class Application
{
    /**
     * @throws Exception
     */
    public static function run(): void
    {
        Database::connect();
        Controller::run();
    }
}