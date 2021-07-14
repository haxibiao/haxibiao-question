<?php

namespace Haxibiao\Question\Nova;

use App\Nova\Resource;
use Illuminate\Http\Request;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;

class AuditRank extends Resource
{
    public static $model = "App\AuditRank";

    public static $title = 'name';

    public static $category = "审题段位";

    public static function label()
    {
        return '审题段位';
    }

    public static function singularLabel()
    {
        return '审题段位';
    }

    public static $search = [
        'id', 'name',
    ];

    public static $parent = null;

    public function fields(Request $request)
    {
        $fields = [
            ID::make()->sortable(),
            Text::make('段位名称', 'name'),
            Number::make('晋级需要分数', 'up_score'),
            Number::make('周考核保底分数', 'min_score'),
            Number::make('当前段位人数', 'count_users'),
            String::make('奖励说明', 'reward'),
            Code::make('星级配置', 'level_score')->json(JSON_PRETTY_PRINT + JSON_UNESCAPED_UNICODE),
        ];

        return $fields;
    }

    public function cards(Request $request)
    {
        return [

        ];
    }

    public function filters(Request $request)
    {
        return [
        ];
    }

    public function lenses(Request $request)
    {
        return [];
    }

    public function actions(Request $request)
    {
        return [

        ];
    }
}
