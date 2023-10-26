<?php

namespace App\Helpers;
use Illuminate\Support\Arr;

class FilterHelper
{
  public static function getOrder($request)
  {
    if (Arr::exists($request, 'order')) {
      $order = $request['order'] === 'asc' ? 'asc' : 'desc';
    } else {
      $order = 'asc';
    }
    return $order;
  }
}
