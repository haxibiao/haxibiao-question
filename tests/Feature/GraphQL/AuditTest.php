<?php

namespace Haxibiao\Question\Tests\Feature\GraphQL;

use Haxibiao\Question\Question;

class AuditTest extends GraphQLTestCase
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
    public function testAuditMutation()
    {
        $query = file_get_contents(__DIR__ . '/gql/audit/auditMutation.gql');
        $question_id = Question::inRandomOrder()->first()->id;
        $variables = [
            'question_id' => $question_id,
            'status' => \random_int(1, 2) % 2 == 0 ? true : false,
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
