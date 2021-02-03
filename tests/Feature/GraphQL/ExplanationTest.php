<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;

use App\User;
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
        $query  = file_get_contents(__DIR__ . '/gql/explanation/createExplanationMutation.gql');
        $user = User::factory([
            'api_token' => str_random(60),
            'account'   => rand(10000000000, 99999999999),
        ])->create();
        $variables = [
            "content"  => "张志明很帅,于是写了一个测试用例来测试这个接口",
            "video_id" => 1,
        ];
        $this->runGuestGQL($query, $variables,$this->getHeaders($user));
    }
}
