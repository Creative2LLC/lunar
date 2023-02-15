<?php

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Lunar\DataTypes\Price;
use Illuminate\Support\Carbon;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

if (! function_exists('max_upload_filesize')) {
    function max_upload_filesize()
    {
        return (int) ini_get('upload_max_filesize') * 1000;
    }
}

if (! function_exists('get_validation')) {
    function get_validation($reference, $field, $defaults = [], Model $model = null)
    {
        $config = config("lunar-hub.{$reference}.{$field}", []);

        $rules = $defaults;

        $rules[] = ! empty($config['required']) ? 'required' : 'nullable';

        if (($config['unique'] ?? false) && $model) {
            $rule = 'unique:'.get_class($model).','.$field;

            if ($model->id) {
                $rule .= ','.$model->id;
            }

            $rules[] = $rule;
        }

        return $rules;
    }
}

if (! function_exists('db_date')) {
    function db_date($column, $format, $alias = null)
    {
        $connection = config('database.default');

        $driver = config("database.connections.{$connection}.driver");

        $select = "DATE_FORMAT({$column}, '{$format}')";

        if ($driver == 'pgsql') {
            $format = str_replace('%', '', $format);
            $select = "TO_CHAR({$column} :: DATE, '{$format}')";
        }

        if ($driver == 'sqlite') {
            $select = "strftime('{$format}', {$column})";
        }

        if ($alias) {
            $select .= " as {$alias}";
        }

        return DB::RAW($select);
    }
}

if (! function_exists('price')) {
    function price($value, $currency, $unitQty = 1)
    {
        return new Price($value, $currency, $unitQty);
    }
}

if (! function_exists('impersonate_link')) {
    function impersonate_link(Authenticatable $authenticatable)
    {
        $class = config('lunar-hub.customers.impersonate');

        if (! $class) {
            return null;
        }

        return app($class)->getUrl($authenticatable);
    }
}

if (! function_exists('lang')) {
    function lang($key, $replace = [], $locale = null, $prefix = 'adminhub::', $lower = false)
    {
        $key = $prefix.$key;

        $value = __($key, $replace, $locale);

        return $lower ? mb_strtolower($value) : $value;
    }
}


/* C2 Helpers */

if (!function_exists('spatie_asset')) {
    function spatie_asset($media = null, $variant = '')
    {
        if ($media) {
            if (app()->environment() == 'local') {
                return $media->getUrl($variant);
            }

            return $media->getTemporaryUrl(Carbon::now()->addHours(2), $variant);
        }

        return $media;
    }
}

if (!function_exists('loadMedia')) {
    function loadMedia($id)
    {
        return Media::findOrFail($id);
    }
}

if (!function_exists('getProductOptionValues')) {
    function getProductOptionValues($product)
    {
        return $product->variants->pluck('values')->flatten();
    }
}

if (!function_exists('getProductOptions')) {
    function getProductOptions($product)
    {
        $values = getProductOptionValues($product);

        $variants = $product->variants->map(function ($variant) {
            return [
                'sku' => $variant->sku,
                'name' => $variant->values->first()?->option->translate('name'),
                'value' => $variant->values->first()?->translate('name'),
            ];
        })->groupBy('name');

        return $variants;

        return $values->unique('id')->groupBy('product_option_id')
            ->map(function ($options) {
                // ray($options);
                $values = $options->map(function ($option) {
                    // ray($option->option);
                    return [
                        'id' => $option->id,
                        'name' => $option->translate('name'),
                        'sku' => $option->pivot->pivotParent->sku,
                    ];
                });
                // ray($values);

                return [
                    'option' => $options->first()->option,
                    'values' => $values->toArray(),
                ];
            })->values();
    }
}

if (!function_exists('getSelectedOptionValues')) {
    function getSelectedOptionValues($product)
    {
        return getProductOptions($product)->mapWithKeys(function ($data) {
            ray($data);
            // return [$data['option']->id => $data['values']->first()->id];
        })->toArray();
    }
}

if (!function_exists('getVariant')) {
    function getVariant($product, $sku = null)
    {

        if ($sku) {
            return $product->variants->first(function ($variant) use ($sku) {
                return $variant->sku == $sku;
            });
        }

        return $product->variants->first();

        return $product->variants->first(function ($variant) use ($product) {
            return !$variant->values->pluck('id')
                ->diff(
                    collect(getSelectedOptionValues($product))->values()
                )->count();
        });
    }
}

if (!function_exists('isEarlyBird')) {
    function isEarlyBird($event)
    {
        return now()->isBetween($event->sales_price_starts_at, $event->sales_price_ends_at);
    }
}
