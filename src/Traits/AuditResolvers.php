<?php

namespace  Haxibiao\Question\Traits;


use GraphQL\Type\Definition\ResolveInfo;
use Haxibiao\Question\Audit;

trait AuditResolvers
{
    public function resolveStore($root, $args, $context, ResolveInfo $info)
    {
        app_track_event('审题', '题目ID', $args['question_id'] ?? null);
        $user = getUser();
        return Audit::store($user, $args);
    }
}
