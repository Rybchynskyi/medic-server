<?php

namespace App\Traits;

trait HasIdentifier
{
    protected function generateIdentifier(): string
    {
        return substr(md5(uniqid((string) mt_rand())), 0, 12);
    }
}
