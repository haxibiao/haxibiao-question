<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;

use App\User;
use App\Video;
use Haxibiao\Breeze\GraphQLTestCase;
use Illuminate\Foundation\Testing\DatabaseTransactions;
class ExplanationTest extends GraphQLTestCase
{
    use DatabaseTransactions;

    /**
     * @group explanation
     */
    public function testCreateExplanationMutation()
    {
        $query  = file_get_contents(__DIR__ . '/gql/Explanation/createExplanationMutation.graphql');
        $user = User::factory([
            'api_token' => str_random(60),
            'account'   => rand(10000000000, 99999999999),
        ])->create();
        $video = Video::create([
            'user_id' => $user->id,
        ]);
        $variables = [
            "content"  => "张志明很帅,于是写了一个测试用例来测试这个接口",
            "video_id" => $video->id,
        ];
        $this->startGraphQL($query, $variables,$this->getHeaders($user));
    }
}
