<?php

namespace Haxibiao\Question\Nova;

use Haxibiao\Question\Tag as QuestionTag;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\MorphToMany;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Resource;

class Tag extends Resource
{

    public static $model = 'Haxibiao\Question\Tag';

    public static $title  = 'name';
    public static $search = [
        'name',
    ];

    public static function label()
    {
        return '标签';
    }
    public static $group = "答题中心";

    public static $with = ['user'];

    public function fields(Request $request)
    {
        return [
            ID::make()->sortable(),
            Text::make('名称', 'name'),
            Text::make('统计数', 'count')->exceptOnForms(),
            Text::make('排名', 'rank'),
            Select::make('状态', 'status')->options(QuestionTag::getStatuses())->displayUsingLabels(),
            BelongsTo::make('父标签', 'tag', 'App\Nova\Tag')->nullable()->exceptOnForms(),
            Text::make('备注', 'remark')->exceptOnForms(),
            BelongsTo::make('用户', 'user', 'App\Nova\Tag')->exceptOnForms(),
            MorphToMany::make('反馈', 'feedbacks', 'App\Nova\Feedback'),
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
        return [
            new \Haxibiao\Question\Nova\Actions\Tag\UpdateParentTag,
        ];
    }
}
