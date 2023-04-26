<?php

namespace TaNteE\LaravelGenericApi\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Http\Resources\Json\ResourceCollection;
use TaNteE\LaravelGenericApi\Http\Resources\ExtendedResource;
use TaNteE\LaravelGenericApi\Http\Resources\ExtendedResourceCollection;


class ApiController extends Controller
{
    public static function RemoteApiRequest(Request $request,$ApiName,$ApiMethod,$ApiUrl,$ETLCode,$ETLCodeError,$isFlatten,$isMaskError,$args,$overrides=null) {
      if ($ApiMethod=="GET" || $ApiMethod=="POST" || $ApiMethod=="PUT" || $ApiMethod=="PATCH") {
        return self::RemoteRESTApiRequest($request,$ApiName,$ApiMethod,$ApiUrl,$ETLCode,$ETLCodeError,$isFlatten,$isMaskError,$args,$overrides);
      } else {
        return self::RemoteSOAPApiRequest($request,$ApiName,$ApiMethod,$ApiUrl,$ETLCode,$ETLCodeError,$isFlatten,$isMaskError,$args,$overrides);
      }
    }

    public static function RemoteRESTApiRequest(Request $request,$ApiName,$ApiMethod,$ApiUrl,$ETLCode,$ETLCodeError,$isFlatten,$isMaskError,$args,$overrides=null) {
      $success = true;
      $errorTexts = [];
      $returnModels = [];

      $argsKey = array_keys($args);
      for($i=0;$i<count($args);$i++) {
        $ApiUrl = str_replace('{'.($i+1).'}',$args[$argsKey[$i]],$ApiUrl);
        if ('{'.($i+1).'}' != '{'.$argsKey[$i].'}') $ApiUrl = str_replace('{'.$argsKey[$i].'}',$args[$argsKey[$i]],$ApiUrl);
      }

      $requestData = [
        'headers' => [
          'Accept' => 'application/json',
        ]
      ];

      parse_str(parse_url($ApiUrl, PHP_URL_QUERY), $queryarray);
      $queryarray = array_merge($queryarray,$request->query());

      if (!empty($queryarray)) $requestData['query']=$queryarray;

      if ($request->header('Content-Type')=="application/json") {
        $requestData['json'] = $request->json()->all();
        $requestData['headers']['Content-Type'] = "application/json";
      }
      if ($request->header('Content-Type')=="application/x-www-form-urlencoded") {
        $requestData['form_params'] = array_diff($request->input(),$request->query());
        $requestData['headers']['Content-Type'] = "application/x-www-form-urlencoded";
      }

      if ($overrides) {
        foreach($overrides as $key=>$override) {
          data_set($requestData['json'],'data.'.$key,$override);
          if (isset($requestData['json'][$key])) $requestData['json'][$key] = $override;
          if (isset($requestData['query'][$key])) $requestData['query'][$key] = $override;
        }
      }

      $client = new \GuzzleHttp\Client();
      $httpResponseCode = '';
      if ($ApiMethod != null && $ApiUrl != null) {
        try {

          $res = $client->request($ApiMethod,$ApiUrl,$requestData);
          Log::debug('Calling '.$ApiName.' ('.$ApiMethod.' '.$ApiUrl.')',["RequestData"=>$requestData]);

          $httpResponseCode = $res->getStatusCode();
          if ($httpResponseCode!==200) {
            $success = false;
            array_push($errorTexts,['errorText'=>$res->getBody(),'errorType'=>2]);

            try {
              if (!empty($ETLCodeError)) eval($ETLCodeError);
            } catch(\Exception $e) {
              log::error("Data transformation logic error (API Error)");
              return response("Data transformation logic error",501);
            }
          } else {
            $ApiData = json_decode((String)$res->getBody(),true);
            if ($ApiData != null) {
              $success = Arr::pull($ApiData,'success',$success);
              $errorTexts = Arr::wrap(Arr::pull($ApiData,'errorTexts',$errorTexts));
            }

            if ($isMaskError) {
              array_walk($errorTexts,function(&$value,$key) {
                if (isset($value['errorType']) && $value['errorType']!=1) {
                  $value['errorText'] = 'Internal Server Error';
                }
              });
            }

            try {
              if (!empty($ETLCode)) eval($ETLCode);
              else $returnModels = $ApiData;
            } catch(\Exception $e) {
              log::error("Data transformation logic error (API Data)",["ETLCode"=>$ETLCode,"APIData"=>$ApiData]);
              return response("Data transformation logic error",501);
            }
          }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
          log::error("Error calling to $ApiMethod $ApiUrl.",["Message"=>$e->getMessage(),"RequestData"=>$requestData]);

          $response = $e->getResponse();
          if ($response) {
            $httpResponseCode = $response->getStatusCode();
          } else {
            $httpResponseCode = 500;
          }

          $success = false;
          array_push($errorTexts,['errorText'=>$e->getMessage(),'errorType'=>2]);

          if ($isMaskError) {
            $httpResponseCode = 500;
            $errorTexts = [
              [
                'errorText' => 'Internal Server Error',
                'errorType' => 2
              ]
            ];
          }

          try {
            if (!empty($ETLCodeError)) eval($ETLCodeError);
          } catch(\Exception $e) {
            log::error("Data transformation logic error (API Error)");
            return response("Data transformation logic error",501);
          }
        }
      } else {
        try {
          if (!empty($ETLCode)) eval($ETLCode);
        } catch(\Exception $e) {
          log::error("Data transformation logic error (API Data)",["ETLCode"=>$ETLCode]);
          return response("Data transformation logic error",501);
        }
      }

      if ($isFlatten) JsonResource::withoutWrapping();

      if ($returnModels instanceof \Illuminate\Database\Eloquent\Collection) {
        if (!$isFlatten) return new ExtendedResourceCollection($returnModels,$success,$errorTexts);
        else return new \Illuminate\Http\Resources\Json\ResourceCollection($returnModels);
      } else {
        if (!is_array($returnModels)) $returnModels = (array)$returnModels;
        if (!$isFlatten) return new ExtendedResource($returnModels,$success,$errorTexts);
        else return new JsonResource($returnModels);
      }
    }

    public static function RemoteRESTApi($ApiName,$CallData,$cache=0) {
      $success = true;
      $errorTexts = [];
      $returnModels = [];

      $CallDataHash = md5(json_encode($CallData));
      $cacheKey = $ApiName.'#'.$CallDataHash;

      if ($cache && Cache::has($cacheKey)) {
        log::debug('retrieve data from cache - '.$cacheKey,$CallData);
        return Cache::get($cacheKey);
      }

      $api = config('generic-api.api-model')::where('name',$ApiName)->first();
      if ($api == null) {
        $success = false;
        array_push($errorTexts,['errorText'=>"API Not Found",'errorType'=>2]);
      } else {
        $requestData = [
          'headers' => [
            'Accept' => 'application/json',
          ],
        ];
        $ApiUrl = $api->sourceApiUrl;

        if (is_array($CallData)) {
          $keys = array_keys($CallData);
          for($i=0;$i<count($keys);$i++) {
            $ApiUrl = str_replace('{'.($i+1).'}',$CallData[$keys[$i]],$ApiUrl);
            if ('{'.($i+1).'}' != '{'.$keys[$i].'}') $ApiUrl = str_replace('{'.$keys[$i].'}',$CallData[$keys[$i]],$ApiUrl);
          }
        }
        
        $apiMethod = (!empty($api->sourceApiMethod)) ? $api->sourceApiMethod : $api->ApiMethod;

        if ($apiMethod=="GET") {
          parse_str(parse_url($ApiUrl, PHP_URL_QUERY), $queryarray);
          $queryarray = array_merge($queryarray,$CallData);

          if (!empty($queryarray)) $requestData['query']=$queryarray;
        } else {
          $requestData['json'] = is_array($CallData) ? $CallData : [$CallData];
          $requestData['headers']['Content-Type'] = "application/json";
        }

        $client = new \GuzzleHttp\Client();
        $httpResponseCode = '';

        try {

          $res = $client->request($apiMethod,$ApiUrl,$requestData);
          Log::debug('Calling '.$ApiName.' ('.$apiMethod.' '.$ApiUrl.')',["RequestData"=>$requestData]);

          $httpResponseCode = $res->getStatusCode();
          if ($httpResponseCode!==200) {
            $success = false;
            array_push($errorTexts,['errorText'=>$res->getBody(),'errorType'=>2]);

            try {
              if (!empty($api->ETLCodeError)) eval($api->ETLCodeError);
            } catch(\Exception $e) {
              array_push($errorTexts,['errorText'=>"Data transformation logic error (API Error)",'errorType'=>1]);
            }
          } else {
            $ApiData = json_decode((String)$res->getBody(),true);
            if ($ApiData != null) {
              $success = Arr::pull($ApiData,'success',$success);
              $errorTexts = Arr::pull($ApiData,'errorTexts',$errorTexts);
            }

            try {
              if (!empty($api->ETLCode)) eval($api->ETLCode);
              else $returnModels = $ApiData;
            } catch(\Exception $e) {
              array_push($errorTexts,['errorText'=>"Data transformation logic error (API Error)",'errorType'=>1]);
            }
          }

          if ($cache) {
            Cache::put($cacheKey,["success" => $success, "errorTexts" => $errorTexts, "returnModels" => $returnModels],$cache);
          }
        } catch (\GuzzleHttp\Exception\RequestException $e) {
          log::error("Error calling to $apiMethod $ApiUrl.",["Message"=>$e->getMessage(),"RequestData"=>$requestData]);

          $success = false;
          array_push($errorTexts,['errorText'=>$e->getMessage(),'errorType'=>2]);
        }
      }

      return ["success" => $success, "errorTexts" => $errorTexts, "returnModels" => $returnModels];
    }

    public static function RemoteSOAPApiRequest(Request $request,$ApiName,$ApiMethod,$ApiUrl,$ETLCode,$ETLCodeError,$isFlatten,$isMaskError,$args,$overrides=null) {
      $success = true;
      $errorTexts = [];
      $returnModels = [];

      if ($ApiMethod != null && $ApiUrl != null) {

        $CallData = [];

        if ($request->header('Content-Type')=="application/json") {
          $requestJson = $request->json()->all();
          if (isset($requestJson['data'])) $CallData = $requestJson['data'];
          else $CallData = $requestJson;
        }
        if ($request->header('Content-Type')=="application/x-www-form-urlencoded") {
          $CallData = $request->input();
        }

        $CallData = array_merge($CallData,$args);

        if ($overrides) {
          foreach($overrides as $key=>$override) {
            if (isset($CallData[$key])) $CallData[$key] = $override;
          }
        }

        $result = self::RemoteSOAPApiRaw($ApiUrl,$ApiMethod,$CallData,$ETLCode,$ETLCodeError);

        $success = $result['success'];
        $errorTexts = $result['errorTexts'];
        $returnModels = $result['returnModels'];

      } else {
        try {
          if (!empty($ETLCode)) eval($ETLCode);
        } catch(\Exception $e) {
          log::error("Data transformation logic error (API Data)",["ETLCode"=>$ETLCode]);
          return response("Data transformation logic error",501);
        }
      }

      if ($isMaskError) {
        array_walk($errorTexts,function(&$value,$key) {
          if (isset($value['errorType']) && $value['errorType']!=1) {
            $value['errorText'] = 'Internal Server Error';
          }
        });
      }

      if ($isFlatten) JsonResource::withoutWrapping();

      if ($returnModels instanceof \Illuminate\Database\Eloquent\Collection) {
        if (!$isFlatten) return new ExtendedResourceCollection($returnModels,$success,$errorTexts);
        else return new ResourceCollection($returnModels);
      } else {
        if (!is_array($returnModels)) $returnModels = (array)$returnModels;
        if (!$isFlatten) return new ExtendedResource($returnModels,$success,$errorTexts);
        else return new JsonResource($returnModels);
      }
    }

    public static function RemoteSOAPApi($ApiName,$CallData,$cache=0) {
      $success = true;
      $errorTexts = [];
      $returnModels = [];

      $CallDataHash = md5(json_encode($CallData));
      $cacheKey = $ApiName.'#'.$CallDataHash;

      if ($cache && Cache::has($cacheKey)) {
        log::debug('retrieve data from cache - '.$cacheKey,$CallData);
        return Cache::get($cacheKey);
      }

      $api = config('generic-api.api-model')::where('name',$ApiName)->first();
      if ($api == null) {
        $success = false;
        array_push($errorTexts,['errorText'=>"API Not Found",'errorType'=>2]);
      } else {
        $ApiUrl = $api->sourceApiUrl;
        $ApiMethod = $api->sourceApiMethod;
        $ETLCode = $api->ETLCode;
        $ETLCodeError = $api->ETLCodeError;

        return self::RemoteSOAPApiRaw($ApiUrl,$ApiMethod,$CallData,$ETLCode,$ETLCodeError,$cache);
      }

      return ["success" => $success, "errorTexts" => $errorTexts, "returnModels" => $returnModels];
    }

    public static function RemoteSOAPApiRaw($ApiUrl,$functionName,$CallData,$ETLCode=null,$ETLCodeError=null,$cache=0) {
      $success = true;
      $errorTexts = [];
      $returnModels = [];

      $CallDataHash = md5(json_encode($CallData));
      $cacheKey = $ApiUrl.'#'.$functionName.'#'.$CallDataHash;

      if ($cache && Cache::has($cacheKey)) {
        log::debug('retrieve data from cache - '.$cacheKey,$CallData);
        return Cache::get($cacheKey);
      }

      $client = new \SoapClient($ApiUrl,[
          'trace'=> true,
          'exceptions' => true,
          'cache_wsdl'=> WSDL_CACHE_NONE,
          'keep-alive' => false,
          'user_agent' => 'Mozilla/1.0N (Windows)'
        ]);
      try {
        Log::debug('Calling '.$ApiUrl.' ('.$functionName.')',["CallData"=>$CallData]);

        $client->__setLocation($ApiUrl);
        $ApiData = $client->__soapCall($functionName,$CallData);
        if (is_object($ApiData) && isset($ApiData->return)) $ApiData = $ApiData->return;
        $ApiData = json_decode(json_encode(simplexml_load_string($ApiData)),true);

        if (!empty($ETLCode)) eval($ETLCode);
        else $returnModels = $ApiData;

        try {
          if (!empty($ETLCode)) eval($ETLCode);
          else $returnModels = $ApiData;
        } catch(\Exception $e) {
          $success = false;
          array_push($errorTexts,['errorText'=>"Data transformation logic error (API Error)",'errorType'=>1]);
        }

        if ($success && $cache) {
          Cache::put($cacheKey,["success" => $success, "errorTexts" => $errorTexts, "returnModels" => $returnModels],$cache);
        }
      } catch (\SoapFault $e) {
        log::error("Error calling to $functionName in $ApiUrl.",["Message"=>$e->getMessage()]);

        $success = false;

        try {
          if (!empty($ETLCodeError)) eval($ETLCodeError);
        } catch(\Exception $e) {
          array_push($errorTexts,['errorText'=>"Data transformation logic error (API Error)",'errorType'=>1]);
        }

        array_push($errorTexts,['errorText'=>$e->getMessage(),"errorType"=>2]);
      }
      
      return ["success" => $success, "errorTexts" => $errorTexts, "returnModels" => $returnModels];
    }

    public static function wildcardRequest(Request $request,$path,$overrides=null) {
      $api = strval(config('generic-api.api-model'))::where('apiRoute','regexp',self::pathToQuery($path))->where('apiMethod',$request->method())->first();
      if ($api) {
        $param = self::pathParameters($path,$api->apiRoute,$overrides);
        $apiMethod = (!empty($api->sourceApiMethod)) ? $api->sourceApiMethod : $api->ApiMethod;
        return self::RemoteApiRequest($request,$api->apiName,$apiMethod,$api->sourceApiUrl,$api->ETLCode,$api->ETLCodeError,$api->isFlatten,$api->isMaskError,$param,$overrides);
      } else {
        return response("API Endpoint not found",404);
      }
    }

    public static function pathToQuery($path) {
      $pathArray = [];
      $pathSplits = explode('/',trim(trim($path),'/'));
      foreach ($pathSplits as $pathSplit) {
        $pathArray[] = '('.$pathSplit.'|'.'\\{[[:alnum:]]+\\}'.')';
      }

      return '^\/?'.implode("\/",$pathArray).'\/?$';
    }

    public static function pathParameters($path,$pattern,$overrides=null) {
      $returnParam = [];

      $patternSplits = explode('/',trim(trim($pattern),'/'));
      $pathSplits = explode('/',trim(trim($path),'/'));

      foreach ($patternSplits as $key=>$patternSplit) {
        $param = rtrim(ltrim($patternSplit,'{'),'}');
        if ($param != $patternSplit) {
          if ($overrides && isset($overrides[$param])) $returnParam[$param] = $overrides[$param];
          else $returnParam[$param] = $pathSplits[$key];
        }
      }

      return $returnParam;
    }

    public static function version() {
      $returnModels = [
        "version" => env('APP_VERSION', 'unspecified'),
        "environment " => env('APP_ENV', 'unspecified')
      ];

      return new ExtendedResource($returnModels);
    }
}
