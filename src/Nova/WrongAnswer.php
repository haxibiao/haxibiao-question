<?php

namespace Haxibiao\Question\Nova;

use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Resource;

class WrongAnswer extends Resource
{
    public static $model  = 'Haxibiao\Question\WrongAnswer';
    public static $title  = 'id';
    public static $search = [
        'id',
    ];

    public static $with  = ['user'];
    public static $group = "答题中心";

    public static function label()
    {
        return '错题本';
    }

    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('用户', 'user', 'App\Nova\User')->exceptOnForms(),
            Code::make('错题记录', 'data')->json(JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE)->exceptOnForms()->hideFromIndex(),
            Number::make('数量', 'count')->exceptOnForms(),
            DateTime::make('创建时间', 'created_at')->exceptOnForms(),
        ];
    }

    /**
     * Get the cards available for the request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function cards(Request $request)
    {
        return [];
    }

    /**
     * Get the filters available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function filters(Request $request)
    {
        return [];
    }

    /**
     * Get the lenses available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function lenses(Request $request)
    {
        return [];
    }

    /**
     * Get the actions available for the resource.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function actions(Request $request)
    {
        return [];
    }
}
