<?php

namespace Haxibiao\Question\Traits;

use Haxibiao\Question\Explanation;

trait ExplanationResolvers
{
    public function resolveCreateExplanation($root, $args, $context, $info)
    {
        return Explanation::store(getUser(), $args);
    }
}
