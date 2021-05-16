<?php

namespace TaNteE\LaravelGenericApi\Http\Controllers;

use Illuminate\Http\Request;
use TaNteE\LaravelGenericApi\LaravelGenericApi;

class GenericAPIController extends Controller
{
    public static function route(Request $request, $methodNamespace, $methodClass, $methodName, $customParameters = [], $directReturn = false)
    {
        $methodClassName = "\\App\\Http\\".(($methodNamespace) ? "Controllers\\$methodNamespace\\" : "Controllers\\")."$methodClass";

        if (method_exists($methodClassName, $methodName)) {
            $method = new \ReflectionMethod($methodClassName, $methodName);
            $parameters = $method->getParameters();

            $callParameters = [];

            foreach ($parameters as $parameter) {
                if ($parameter->getType() && $parameter->getType()->getName() == "Illuminate\\Http\\Request") {
                    array_push($callParameters, $request);
                } else {
                    if (isset($customParameters[$parameter->name])) {
                        array_push($callParameters, $customParameters[$parameter->name]);
                    } elseif (isset($request->data[$parameter->name])) {
                        array_push($callParameters, $request->data[$parameter->name]);
                    } elseif (isset($request->data) && ($parameter->name == 'data')) {
                        array_push($callParameters, $request->data);
                    } elseif ($request->has($parameter->name)) {
                        array_push($callParameters, $request->input($parameter->name));
                    } elseif ($parameter->isDefaultValueAvailable()) {
                        array_push($callParameters, $parameter->getDefaultValue());
                    } else {
                        array_push($callParameters, null);
                    }
                }
            }

            if (! $directReturn) {
                $returnResult = $methodClassName::$methodName(...$callParameters);

                return LaravelGenericApi::resultToResource($returnResult);
            } else {
                return $methodClassName::$methodName(...$callParameters);
            }
        } else {
            return response("Method not found", 404);
        }
    }

    public static function routeDirect(Request $request, $methodNamespace, $methodClass, $methodName)
    {
        return self::route($request, $methodNamespace, $methodClass, $methodName, true);
    }
}
