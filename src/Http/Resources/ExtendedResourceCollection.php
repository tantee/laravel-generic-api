<?php

namespace TaNteE\LaravelGenericApi\Http\Resources;

use Illuminate\Http\Resources\Json\ResourceCollection;

class ExtendedResourceCollection extends ResourceCollection
{
    /**
     * Transform the resource collection into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return parent::toArray($request);
    }

    public function __construct($resource,$success=true,$errors=[]) {
        if (!\method_exists($resource,'toArray')) $resource = collect($resource);
        parent::__construct($resource);

        $this->additional([
          'success' => $success,
          'errorTexts' => $errors
        ]);
    }

    public function additional($data) {
      $this->additional = array_merge($this->additional,$data);

      return $this;
    }
}
