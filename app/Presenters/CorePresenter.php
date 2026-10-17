<?php

namespace App\Presenters;

abstract class CorePresenter
{
    protected function includeOne($data, string $presenterName): ?array
    {
        if ($data) {
            return (new $presenterName)->transform($data);
        }

        return null;
    }

    protected function includeMany($data, string $presenterName): array
    {
        $response = [];
        $presenter = new $presenterName;

        foreach ($data as $item) {
            $response[] = $presenter->transform($item);
        }

        return $response;
    }
}
