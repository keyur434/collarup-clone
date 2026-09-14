<?php

class AuthMiddleware
{
    public static function handle()
    {
        require_auth();
    }
}
