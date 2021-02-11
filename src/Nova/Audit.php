<?php

namespace Haxibiao\Question\Nova;

use Haxibiao\Question\Audit as QuestionAudit;
use Haxibiao\Question\Question;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Resource;

class Audit extends Resource
{
    public static $model = 'Haxibiao\Question\Audit';

    public static $title  = 'id';
    public static $search = [
        'id',
    ];

    public static function label()
    {
        return '审题';
    }

    public static $group = "答题中心";

    public static $with = ['user', 'question'];

    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('用户', 'user', 'App\Nova\User')->exceptOnForms(),
            BelongsTo::make('题目', 'question', 'App\Nova\Question')->exceptOnForms(),
            Select::make('状态', 'status')->options(QuestionAudit::getStatuses())->displayUsingLabels()->exceptOnForms(),
            DateTime::make('投票时间', 'created_at')->exceptOnForms(),

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
        return [
            new \Haxibiao\Question\Nova\Filters\Audit\AuditStatusFilter,
        ];
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
