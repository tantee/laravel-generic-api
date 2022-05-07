<?php

namespace TaNteE\LaravelGenericApi\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class Apis extends Model
{
  public static function boot() {
      static::saved(function($model) {
        Cache::store('file')->forget('api_wrapper_route');
      });

      parent::boot();
  }
}
