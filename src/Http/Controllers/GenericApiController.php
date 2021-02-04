<?php

namespace TaNteE\LaravelGenericApi\Http\Controllers;

use log;
use Illuminate\Http\Request;
use TaNteE\LaravelGenericApi\LaravelGenericApi;

class GenericApiController extends Controller
{
  public static function route(Request $request,$methodNamespace,$methodClass,$methodName,$customParameters=[],$directReturn=false) {
    $methodClassName = "\\App\\Http\\".(($methodNamespace) ? "Controllers\\$methodNamespace\\" : "Controllers\\")."$methodClass";

    if (method_exists($methodClassName,$methodName)) {
      $method = new \ReflectionMethod($methodClassName,$methodName);
      $parameters = $method->getParameters();

      $callParameters = [];

      foreach($parameters as $parameter) {
        if ($parameter->getClass()!=null && $parameter->getClass()->name == "Illuminate\\Http\\Request") {
          array_push($callParameters,$request);
        } else {
          if (isset($customParameters[$parameter->name])) array_push($callParameters,$customParameters[$parameter->name]);
          else if (isset($request->data[$parameter->name])) array_push($callParameters,$request->data[$parameter->name]);
          else if (isset($request->data) && ($parameter->name=='data')) array_push($callParameters,$request->data);
          else if ($request->has($parameter->name)) array_push($callParameters,$request->input($parameter->name));
          else if ($parameter->isDefaultValueAvailable()) array_push($callParameters,$parameter->getDefaultValue());
          else array_push($callParameters,null);
        }
      }

      if (!$directReturn) {
        $returnResult = $methodClassName::$methodName(...$callParameters);

        return LaravelGenericApi::resultToResource($returnResult);
      } else {
        return $methodClassName::$methodName(...$callParameters);
      }
    } else {
      return response("Method not found",404);
    }
  }

  public static function routeDirect(Request $request,$methodNamespace,$methodClass,$methodName) {
    return self::route($request,$methodNamespace,$methodClass,$methodName,true);
  }
}