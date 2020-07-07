<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;


use App\User;
use Haxibiao\Question\Question;
use Yansongda\Supports\Str;

class CurationTest extends GraphQLTestCase
{
    protected $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = factory(User::class)->create([
            'api_token' => str_random(60),
            'account' => rand(10000000000, 99999999999),
        ]);
    }
    public function testcurationQuery()
    {
        $query = file_get_contents(__DIR__ . '/gql/curation/curationQuery.gql');
        $variables = [
            'limit' => 10,
            'offset' => 0,
        ];
        $this->runGQL($query, $variables, $this->getHeaders($this->user));
    }
    public function testCreateCurationMutation()
    {
        $query = file_get_contents(__DIR__ . '/gql/curation/createCuration.gql');
        $variables = [
            'question_id' => Question::where("submit", ">", 0)->take(100)->get()->random()->id,
            'type' => \random_int(1, 4),
            'content' => Str::random(5),
        ];
        $this->runGQL($query, $variables, $this->getHeaders($this->user));
    }

    public function getHeaders($user)
    {
        $token = $user->api_token;

        $headers = [
            'token' => $token,
            'Authorization' => 'Bearer ' . $token,
            'Accept' => 'application/json',
        ];

        return $headers;
    }
}
