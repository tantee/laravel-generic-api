<?php

namespace TaNteE\LaravelGenericApi;

use Illuminate\Support\Facades\Route;
use TaNteE\LaravelGenericApi\Http\Resources\ExtendedResource;
use TaNteE\LaravelGenericApi\Http\Resources\ExtendedResourceCollection;
use TaNteE\LaravelGenericApi\Http\Controllers\GenericApiController;

class LaravelGenericApi
{
  public static function resultToResource($result) {
    if (is_array($result)) {
      if (isset($result['returnModels']) && isset($result['success'])) {
        if ($result['returnModels'] instanceof \Illuminate\Database\Eloquent\Collection || $result['returnModels'] instanceof \Illuminate\Pagination\AbstractPaginator) {
          return new ExtendedResourceCollection($result['returnModels'],$result['success'],$result['errorTexts']);
        } else {
          return new ExtendedResource($result['returnModels'],$result['success'],$result['errorTexts'],true);
        }
      } else {
        return new ExtendedResource($result,true,[],true);
      }
    } else {
      if ($result instanceof \Illuminate\Database\Eloquent\Collection || $result instanceof \Illuminate\Pagination\AbstractPaginator) {
        return new ExtendedResourceCollection($result);
      } if ($result instanceof \Illuminate\Http\Resources\Json\ResourceCollection || $result instanceof \Illuminate\Http\Resources\Json\JsonResource) {
        return $result;
      } else {
        return new ExtendedResource($result,true,[],true);
      }
    }
  }

  public static function routes() {
    Route::get('{methodNamespace}/{methodClass}/{methodName}',[GenericApiController::class,'route']);
    Route::post('{methodNamespace}/{methodClass}/{methodName}',[GenericApiController::class,'route']);
    Route::get('direct/{methodNamespace}/{methodClass}/{methodName}',[GenericApiController::class,'routeDirect']);
    Route::post('direct/{methodNamespace}/{methodClass}/{methodName}',[GenericApiController::class,'routeDirect']);
  }
}
