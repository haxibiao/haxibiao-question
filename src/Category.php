<?php

namespace Haxibiao\Question;

use Haxibiao\Breeze\Traits\ModelHelpers;
use Haxibiao\Content\Category as ContentCategory;
use Haxibiao\Question\Traits\CategoryAttrs;
use Haxibiao\Question\Traits\CategoryRepo;
use Haxibiao\Question\Traits\CategoryResolvers;
use Haxibiao\Question\Traits\CategoryScopes;

class Category extends ContentCategory
{
    use CategoryRepo;
    use CategoryAttrs;
    use CategoryResolvers;
    use CategoryScopes;

    use ModelHelpers;
}
