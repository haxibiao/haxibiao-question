<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;

use Haxibiao\Question\Category;

class CategoryTest extends GraphQLTestCase
{
    /**
     * @group category
     */
    public function testCategoriesQuery()
    {
        $query = file_get_contents(__DIR__ . '/gqls/category/CategoriesQuery.gql');

        $variables = [
            'keyword' => '初中',
            'offset' => 10,
            'limit' => 5,
        ];
        $this->runGQL($query, $variables);
    }

    public function testCategoryQuery()
    {
        $query = file_get_contents(__DIR__ . '/gqls/category/CategoryQuery.gql');
        $this->runGQL($query, [
            'id' => Category::first()->id,
        ]);
    }
}
