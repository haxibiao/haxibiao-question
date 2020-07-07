<?php

namespace Haxibiao\Question\Traits;

use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Question\Curation;

trait CurationResolvers
{
    public function resolveCurations($root, $args, $context, ResolveInfo $info)
    {
        app_track_event('纠题', '纠题记录');
        $user = getUser();
        return $user->curations()->latest('id');
    }

    public function resolveCurateQuestion($root, $args, $context, ResolveInfo $info)
    {
        app_track_event('纠题', '题目ID', $args['question_id']);
        $user = getUser();

        return Curation::curateQuestion($user, $args['question_id'], $args['type'], $args['content']);
    }
}
