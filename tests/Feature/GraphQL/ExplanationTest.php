<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;

class ExplanationTest extends GraphQLTestCase
{
    public function testCreateExplanationMutation()
    {
        $mutation  = file_get_contents(__DIR__ . '/gql/explanation/createExplanationMutation.gql');
        $variables = [
            "content"  => "张志明很帅,于是写了一个测试用例来测试这个接口",
            "video_id" => 1,
        ];
        $this->runGQL($mutation, $variables);
    }
}
