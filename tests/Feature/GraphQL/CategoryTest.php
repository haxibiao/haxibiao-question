<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;

use Haxibiao\Question\Category;
use Haxibiao\Breeze\GraphQLTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class CategoryTest extends GraphQLTestCase
{
    use DatabaseTransactions;
    /**
     * @group category
     */
    protected function testCategoriesQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/category/CategoriesQuery.gql');

        $variables = [
            'keyword' => '初中',
            'offset' => 10,
            'limit' => 5,
        ];
        $this->runGQL($query, $variables);
    }
    /**
     * @group category
     */
    protected function testCategoryQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/category/CategoryQuery.gql');
        $this->runGQL($query, [
            'id' => Category::first()->id,
        ]);
    }
}
