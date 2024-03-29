<?php

namespace TaNteE\LaravelGenericApi;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use TaNteE\LaravelGenericApi\Http\Controllers\ApiController;
use TaNteE\LaravelGenericApi\Http\Controllers\GenericAPIController;
use TaNteE\LaravelGenericApi\Http\Resources\ExtendedResource;
use TaNteE\LaravelGenericApi\Http\Resources\ExtendedResourceCollection;

class LaravelGenericApi
{
    public static function resultToResource($result)
    {
        if (is_array($result)) {
            if (isset($result['returnModels']) && isset($result['success'])) {
                if ($result['returnModels'] instanceof \Illuminate\Database\Eloquent\Collection || $result['returnModels'] instanceof \Illuminate\Pagination\AbstractPaginator) {
                    return new ExtendedResourceCollection($result['returnModels'], $result['success'], $result['errorTexts']);
                } else {
                    return new ExtendedResource($result['returnModels'], $result['success'], $result['errorTexts'], true);
                }
            } else {
                return new ExtendedResource($result, true, [], true);
            }
        } else {
            if ($result instanceof \Illuminate\Database\Eloquent\Collection || $result instanceof \Illuminate\Pagination\AbstractPaginator) {
                return new ExtendedResourceCollection($result);
            }
            if ($result instanceof \Illuminate\Http\Resources\Json\ResourceCollection || $result instanceof \Illuminate\Http\Resources\Json\JsonResource) {
                return $result;
            } else {
                return new ExtendedResource($result, true, [], true);
            }
        }
    }

    public static function routes($prefix = null, $middleware = null)
    {
        Route::prefix($prefix)->middleware(Arr::wrap($middleware))->group(function () {
            Route::get('{methodNamespace}/{methodClass}/{methodName}', [GenericAPIController::class,'route']);
            Route::post('{methodNamespace}/{methodClass}/{methodName}', [GenericAPIController::class,'route']);
            Route::get('direct/{methodNamespace}/{methodClass}/{methodName}', [GenericAPIController::class,'routeDirect']);
            Route::post('direct/{methodNamespace}/{methodClass}/{methodName}', [GenericAPIController::class,'routeDirect']);
        });

        Route::get('version',[ApiController::class,'version']);
    }

    public static function routesWrapper($prefix = 'wrapper', $middleware = null) {
      Route::prefix($prefix)->middleware(Arr::wrap($middleware))->group(function () {
        try {
          $apis = Cache::store('file')->remember('api_wrapper_route', 60*5 , function () {
                        return strval(config('generic-api.api-model'))::all();
                    });
          foreach($apis as $api) {
            Route::match([$api->apiMethod],ltrim($api->apiRoute,'/'),function(Request $request) use ($api) {
              $args = func_get_args();
              array_shift($args);
              $apiMethod = (!empty($api->sourceApiMethod)) ? $api->sourceApiMethod : $api->ApiMethod;
              return \TaNteE\LaravelGenericApi\Http\Controllers\ApiController::RemoteApiRequest($request,$api->name,$apiMethod,$api->sourceApiUrl,$api->ETLCode,$api->ETLCodeError,$api->isFlatten,$api->isMaskError,$args);
            });
          };
        } catch (\Exception $e) {
        }
      });
    }

    public static function routesWrapperWildcard($prefix = 'wrapper', $middleware = null) {
        if (DB::Connection()->getDriverName()=="mysql") {
            Route::prefix($prefix)->middleware(Arr::wrap($middleware))->group(function () {
              Route::any('{path}',[ApiController::class,'wildcardRequest'])->where('path','.*'); 
            });
        } else {
            self::routesWrapper($prefix,$middleware);
        }   
    }
}
