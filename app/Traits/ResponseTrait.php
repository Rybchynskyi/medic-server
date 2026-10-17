<?php

namespace App\Traits;

use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Http\Response;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait ResponseTrait
{
    public function present($data, string $presenterName, array $includes = [], ?string $message = null): ?array
    {
        $presenter = new $presenterName;

        if (
            is_array($data)
            || $data instanceof Collection
            || $data instanceof EloquentCollection
            || $data instanceof LengthAwarePaginator
        ) {
            $response = [];
            foreach ($data as $key => $item) {
                $response[$key] = $presenter->transform($item);

                foreach ($includes as $include) {
                    $methodName = 'include'.ucfirst($include);
                    $response[$key][$include] = $presenter->{$methodName}($item);
                }
            }

            if ($data instanceof LengthAwarePaginator) {
                $items = $response;
                unset($response);
                $response['data'] = $items;
                $response['meta'] = [
                    'total' => $data->total(),
                    'lastPage' => $data->lastPage(),
                    'perPage' => $data->perPage(),
                    'currentPage' => $data->currentPage(),
                ];
            }
        } elseif (is_null($data)) {
            return null;
        } else {
            $response = $presenter->transform($data);

            foreach ($includes as $include) {
                $methodName = 'include'.ucfirst($include);
                $response[$include] = $presenter->{$methodName}($data);
            }
        }

        if ($message) {
            if (count(array_filter(array_keys($response), 'is_string')) === 0) {
                $response = ['data' => $response];
            }
            $response['message'] = $message;
        }

        return $response;
    }

    public function presentValue($value, ?string $key = null)
    {
        if ($key) {
            return [$key => $value];
        }

        return $value;
    }

    public function successResponse(string $message)
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_OK);
    }

    public function validationErrorResponse(string $message)
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    public function serverErrorResponse(string $message)
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_INTERNAL_SERVER_ERROR);
    }

    public function forbiddenResponse(string $message)
    {
        return response()->json([
            'message' => $message,
        ], Response::HTTP_FORBIDDEN);
    }
}
